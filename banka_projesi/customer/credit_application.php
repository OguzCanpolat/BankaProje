<?php
session_start();
require_once '../includes/db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Musteri') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// --- BAŞVURU İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_id = $_POST['account_id'];
    $amount = (float) $_POST['amount'];
    $reason = trim($_POST['reason']);

    if ($amount <= 0 || empty($reason)) {
        $error = "Lütfen tutar ve açıklama giriniz.";
    } else {
        try {
            // Müşteri ID'sini bul
            $stmt = $pdo->prepare("SELECT CustomerID FROM Customers WHERE UserID = ?");
            $stmt->execute([$user_id]);
            $cust = $stmt->fetch();
            $customer_id = $cust['CustomerID'];

            // Başvuruyu Kaydet (Durum otomatik 'Pending' olacak)
            $sql = "INSERT INTO LoanRequests (CustomerID, TargetAccountID, Amount, Message) VALUES (?, ?, ?, ?)";
            $stmt2 = $pdo->prepare($sql);
            $stmt2->execute([$customer_id, $account_id, $amount, $reason]);

            $message = "Kredi başvurunuz başarıyla alındı! Personel onayından sonra hesabınıza yatacaktır.";

        } catch (Exception $e) {
            $error = "Hata oluştu: " . $e->getMessage();
        }
    }
}

// Müşterinin Hesaplarını Çek (Paranın yatacağı hesabı seçsin)
$stmt = $pdo->prepare("SELECT AccountID, AccountNumber, Balance, Currency FROM Accounts 
                       WHERE CustomerID = (SELECT CustomerID FROM Customers WHERE UserID = ?)");
$stmt->execute([$user_id]);
$my_accounts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kredi Başvurusu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        
        <?php if ($message): ?>
            <div class="alert alert-success d-flex align-items-center">
                <i class="fa fa-check-circle fa-2x me-3"></i>
                <div><?= $message ?></div>
            </div>
            <div class="text-center mt-3">
                <a href="../index.php" class="btn btn-primary">Ana Sayfaya Dön</a>
            </div>
        <?php else: ?>

            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0"><i class="fa fa-hand-holding-dollar"></i> İhtiyaç Kredisi Başvurusu</h4>
                        </div>
                        <div class="card-body">
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Paranın Yatacağı Hesap</label>
                                    <select name="account_id" class="form-select" required>
                                        <?php foreach ($my_accounts as $acc): ?>
                                            <option value="<?= $acc['AccountID'] ?>">
                                                <?= $acc['AccountNumber'] ?> (<?= $acc['Currency'] ?? 'TL' ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Talep Edilen Tutar</label>
                                    <input type="number" name="amount" class="form-control" min="1000" step="100" placeholder="Örn: 10000" required>
                                    <small class="text-muted">Minimum 1.000 Birim</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Kullanım Amacı / Mesaj</label>
                                    <textarea name="reason" class="form-control" rows="3" placeholder="Örn: Ev tadilatı için..." required></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">Başvuruyu Gönder</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>