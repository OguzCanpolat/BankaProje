<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Sadece Personel Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Personel') {
    header("Location: ../auth/login.php");
    exit;
}

$personel_id = $_SESSION['user_id']; // İşlemi yapan personel
$message = "";
$error = "";

// --- ONAY / RED İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loan_id = $_POST['loan_id'];
    $action = $_POST['action']; // 'approve' veya 'reject'

    try {
        // Önce başvurunun detaylarını çek (Hangi hesap? Ne kadar?)
        $stmt = $pdo->prepare("SELECT * FROM LoanRequests WHERE LoanID = ? AND Status = 'Pending'");
        $stmt->execute([$loan_id]);
        $basvuru = $stmt->fetch();

        if (!$basvuru) {
            throw new Exception("Başvuru bulunamadı veya zaten işlem yapılmış.");
        }

        if ($action == 'approve') {
            // --- ONAYLA ---
            $pdo->beginTransaction();

            // 1. Durumu Güncelle
            $update = $pdo->prepare("UPDATE LoanRequests SET Status = 'Approved', ProcessedBy = ? WHERE LoanID = ?");
            $update->execute([$personel_id, $loan_id]);

            // 2. Parayı Hesaba Yatır
            $addMoney = $pdo->prepare("UPDATE Accounts SET Balance = Balance + ? WHERE AccountID = ?");
            $addMoney->execute([$basvuru['Amount'], $basvuru['TargetAccountID']]);

            // 3. İşlem Geçmişine (Dekontlara) Yaz
            // Kredi işlemlerinde Gönderen ve Alıcı aynı hesap olur (ATM mantığı gibi)
            $log = $pdo->prepare("INSERT INTO Transactions (SenderAccountID, ReceiverAccountID, Amount, Description) VALUES (?, ?, ?, ?)");
            $desc = "Kredi Onayı - Kredi Kullandırımı";
            $log->execute([$basvuru['TargetAccountID'], $basvuru['TargetAccountID'], $basvuru['Amount'], $desc]);

            $pdo->commit();
            $message = "Kredi onaylandı ve para müşterinin hesabına aktarıldı.";

        } elseif ($action == 'reject') {
            // --- REDDET ---
            $update = $pdo->prepare("UPDATE LoanRequests SET Status = 'Rejected', ProcessedBy = ? WHERE LoanID = ?");
            $update->execute([$personel_id, $loan_id]);
            $message = "Kredi başvurusu reddedildi.";
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = "Hata: " . $e->getMessage();
    }
}

// --- BEKLEYEN BAŞVURULARI LİSTELE ---
// Müşteri adını ve Hesap para birimini de çekiyoruz
$sql = "SELECT lr.*, c.FirstName, c.LastName, a.AccountNumber, a.Currency 
        FROM LoanRequests lr
        JOIN Customers c ON lr.CustomerID = c.CustomerID
        JOIN Accounts a ON lr.TargetAccountID = a.AccountID
        WHERE lr.Status = 'Pending'
        ORDER BY lr.RequestDate ASC";

$requests = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kredi Başvuruları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4 px-4">
        <span class="navbar-brand">Personel Paneli</span>
        <a href="../index.php" class="btn btn-outline-light btn-sm">Ana Sayfa</a>
    </nav>

    <div class="container">
        <h3 class="mb-4 text-secondary"><i class="fa fa-clipboard-list"></i> Onay Bekleyen Krediler</h3>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tarih</th>
                            <th>Müşteri</th>
                            <th>Hedef Hesap</th>
                            <th>Tutar</th>
                            <th>Açıklama</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?= date("d.m.Y", strtotime($req['RequestDate'])) ?></td>
                                
                                <td class="fw-bold">
                                    <?= $req['FirstName'] ?> <?= $req['LastName'] ?>
                                </td>
                                
                                <td>
                                    <span class="font-monospace text-muted"><?= $req['AccountNumber'] ?></span>
                                    <span class="badge bg-info text-dark"><?= $req['Currency'] ?? 'TL' ?></span>
                                </td>
                                
                                <td class="fs-5 text-success fw-bold">
                                    <?= number_format($req['Amount'], 2) ?>
                                </td>
                                
                                <td class="text-muted fst-italic">
                                    "<?= $req['Message'] ?>"
                                </td>
                                
                                <td class="text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="loan_id" value="<?= $req['LoanID'] ?>">
                                        
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm me-1">
                                            <i class="fa fa-check"></i> Onayla
                                        </button>
                                        
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Reddetmek istediğine emin misin?')">
                                            <i class="fa fa-times"></i> Reddet
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (empty($requests)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="fa fa-check-double fa-3x mb-3 text-success"></i>
                        <p class="fs-5">Şu an bekleyen kredi başvurusu yok.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>