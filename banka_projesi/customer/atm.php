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

// --- İŞLEM YAPILDIĞINDA ÇALIŞACAK KISIM ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account_id = $_POST['account_id'];
    $islem_turu = $_POST['type']; // 'deposit' (Yatır) veya 'withdraw' (Çek)
    $tutar = (float) $_POST['amount'];

    if ($tutar <= 0) {
        $error = "Lütfen geçerli bir tutar girin.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Hesabı ve Mevcut Bakiyeyi Bul
            // (Güvenlik: Sadece giriş yapan kullanıcının hesabını seçebilir)
            $stmt = $pdo->prepare("SELECT Balance, AccountNumber FROM Accounts 
                                   WHERE AccountID = ? AND CustomerID IN (SELECT CustomerID FROM Customers WHERE UserID = ?)");
            $stmt->execute([$account_id, $user_id]);
            $hesap = $stmt->fetch();

            if (!$hesap) {
                throw new Exception("Hesap bulunamadı veya size ait değil.");
            }

            // 2. İşlem Türüne Göre Mantık
            if ($islem_turu == 'deposit') {
                // PARA YATIRMA: Bakiyeyi Artır
                $update = $pdo->prepare("UPDATE Accounts SET Balance = Balance + ? WHERE AccountID = ?");
                $update->execute([$tutar, $account_id]);
                
                $desc = "ATM Para Yatırma";
                $msg_text = "Para yatırma işlemi başarılı.";
                
            } elseif ($islem_turu == 'withdraw') {
                // PARA ÇEKME: Önce bakiye kontrolü, sonra düşür
                if ($hesap['Balance'] < $tutar) {
                    throw new Exception("Yetersiz bakiye! Çekebileceğiniz maksimum tutar: " . number_format($hesap['Balance'], 2));
                }

                $update = $pdo->prepare("UPDATE Accounts SET Balance = Balance - ? WHERE AccountID = ?");
                $update->execute([$tutar, $account_id]);

                $desc = "ATM Para Çekme";
                $msg_text = "Para çekme işlemi başarılı. Lütfen paranızı hazneden alınız.";
            }

            // 3. İŞLEMİ KAYDET (Transactions Tablosuna)
            // ATM işlemlerinde Gönderen ve Alıcı AYNI HESAP olarak kaydedilir (Genel standart)
            $sql_log = "INSERT INTO Transactions (SenderAccountID, ReceiverAccountID, Amount, Description) VALUES (?, ?, ?, ?)";
            $stmt_log = $pdo->prepare($sql_log);
            $stmt_log->execute([$account_id, $account_id, $tutar, $desc]);

            $pdo->commit();
            $message = $msg_text;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Hata: " . $e->getMessage();
        }
    }
}

// --- HESAPLARI ÇEK (Dropdown İçin) ---
$stmt = $pdo->prepare("SELECT AccountID, AccountNumber, Balance FROM Accounts 
                       WHERE CustomerID = (SELECT CustomerID FROM Customers WHERE UserID = ?)");
$stmt->execute([$user_id]);
$hesaplar = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>ATM - Para Yatır/Çek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="../index.php"> Banka Sistemi</a>
            <a href="../index.php" class="btn btn-outline-light btn-sm">Ana Sayfa</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark text-center">
                        <h4 class="mb-0"><i class="fa fa-university"></i> ATM İşlemleri</h4>
                    </div>
                    <div class="card-body">
                        
                        <form method="POST" action="">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">İşlem Yapılacak Hesap</label>
                                <select name="account_id" class="form-select" required>
                                    <?php foreach ($hesaplar as $h): ?>
                                        <option value="<?= $h['AccountID'] ?>">
                                            <?= $h['AccountNumber'] ?> (Bakiye: <?= number_format($h['Balance'], 2) ?> TL)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold d-block">İşlem Türü</label>
                                
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="type" id="yatir" value="deposit" checked>
                                    <label class="btn btn-outline-success" for="yatir">
                                        <i class="fa fa-arrow-down"></i> Para Yatır
                                    </label>

                                    <input type="radio" class="btn-check" name="type" id="cek" value="withdraw">
                                    <label class="btn btn-outline-danger" for="cek">
                                        <i class="fa fa-arrow-up"></i> Para Çek
                                    </label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Tutar</label>
                                <div class="input-group">
                                    <input type="number" name="amount" class="form-control" placeholder="0.00" min="1" step="0.01" required>
                                    <span class="input-group-text">TL</span>
                                </div>
                                <small class="text-muted">ATM'den sadece kağıt para yatırabilirsiniz (10 TL ve katları).</small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark btn-lg">İşlemi Onayla</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>