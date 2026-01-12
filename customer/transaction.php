<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Sadece Müşteri Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Musteri') {
    header("Location: ../auth/login.php");
    exit;
}

// URL'den Hesap ID'sini al
$account_id = isset($_GET['id']) ? $_GET['id'] : 0;

try {
    // 1. Önce hesap bilgilerini çek (Başlıkta göstermek için)
    // Güvenlik notu: Bu hesap gerçekten bu müşteriye mi ait? Kontrol edilmeli ama şimdilik basitleştiriyoruz.
    $stmtAcc = $pdo->prepare("SELECT AccountNumber, Balance FROM Accounts WHERE AccountID = ?");
    $stmtAcc->execute([$account_id]);
    $accInfo = $stmtAcc->fetch();

    if (!$accInfo) {
        die("Hesap bulunamadı!");
    }

    // 2. Hareketleri Çek (Prosedürü Çağır)
    $stmt = $pdo->prepare("CALL sp_GetAccountTransactions(?)");
    $stmt->execute([$account_id]);
    $islemler = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Hata: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Hesap Hareketleri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .plus { color: green; font-weight: bold; } /* Para Girişi */
        .minus { color: red; font-weight: bold; }  /* Para Çıkışı */
    </style>
</head>
<body class="bg-light">

    <div class="container mt-5">
        
        <div class="card shadow mb-4">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0 text-primary"><i class="fa fa-list-alt"></i> Hesap Hareketleri</h4>
                    <small class="text-muted">Hesap No: <?= $accInfo['AccountNumber'] ?></small>
                </div>
                <div class="text-end">
                    <h3 class="mb-0"><?= number_format($accInfo['Balance'], 2) ?></h3>
                    <small>Güncel Bakiye</small>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Tarih</th>
                            <th>İşlem Türü</th>
                            <th>Açıklama</th>
                            <th class="text-end">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($islemler as $islem): ?>
                            <tr>
                                <td><?= date("d.m.Y H:i", strtotime($islem['TransactionDate'])) ?></td>
                                <td>
                                    <?php 
                                        // İkon belirleme
                                        if($islem['TypeName'] == 'Para Yatırma' || strpos($islem['Description'], 'Gelen') !== false) 
                                            echo '<i class="fa fa-arrow-down text-success"></i> ' . $islem['TypeName'];
                                        else 
                                            echo '<i class="fa fa-arrow-up text-danger"></i> ' . $islem['TypeName'];
                                    ?>
                                </td>
                                <td><?= $islem['Description'] ?></td>
                                <td class="text-end">
                                    <?php 
                                        // Renklendirme (Giriş mi Çıkış mı?)
                                        // Veritabanında TransTypeID 1=Yatırma (Artı), diğerleri Eksi
                                        // Ancak burada isme veya açıklamaya göre basit bir mantık kuralım:
                                        if($islem['TypeName'] == 'Para Yatırma' || strpos($islem['Description'], 'Gelen') !== false) {
                                            echo '<span class="plus">+' . number_format($islem['Amount'], 2) . '</span>';
                                        } else {
                                            echo '<span class="minus">-' . number_format($islem['Amount'], 2) . '</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (empty($islemler)): ?>
                    <p class="text-center text-muted mt-3">Bu hesaba ait geçmiş işlem bulunamadı.</p>
                <?php endif; ?>

            </div>
            <div class="card-footer">
                <a href="../index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Geri Dön</a>
            </div>
        </div>
    </div>

</body>
</html>