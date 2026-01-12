<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

try {
    // 1. GENEL İSTATİSTİKLER (Direkt SQL ile)
    // Toplam parayı çekiyoruz (Basitlik olsun diye tüm birimleri topluyoruz)
    $stmt = $pdo->query("SELECT SUM(Balance) as ToplamPara FROM Accounts");
    $toplamPara = $stmt->fetch()['ToplamPara'];

    // Müşteri Sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as Sayi FROM Customers");
    $toplamMusteri = $stmt->fetch()['Sayi'];

    // Personel Sayısı
    $stmt = $pdo->query("SELECT COUNT(*) as Sayi FROM Employees");
    $toplamPersonel = $stmt->fetch()['Sayi'];


    // 2. EN ÇOK İŞLEM YAPAN MÜŞTERİLER
    // Transactions tablosunu Customers ile birleştirip sayıyoruz
    $sqlTop = "SELECT c.FirstName, c.LastName, COUNT(t.TransactionID) as IslemSayisi 
               FROM Transactions t
               JOIN Accounts a ON (t.SenderAccountID = a.AccountID OR t.ReceiverAccountID = a.AccountID)
               JOIN Customers c ON a.CustomerID = c.CustomerID
               GROUP BY c.CustomerID
               ORDER BY IslemSayisi DESC 
               LIMIT 5";
    $topCustomers = $pdo->query($sqlTop)->fetchAll();


    // 3. HESAP TÜRÜNE GÖRE DAĞILIM (Şube yerine Para Birimi Raporu daha mantıklı)
    $sqlCurrency = "SELECT Currency, SUM(Balance) as ToplamTutar, COUNT(*) as HesapSayisi 
                    FROM Accounts 
                    GROUP BY Currency";
    $currencyStats = $pdo->query($sqlCurrency)->fetchAll();

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Banka Raporları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa fa-chart-pie text-primary"></i> Finansal Raporlar</h2>
            <a href="../index.php" class="btn btn-secondary">Ana Sayfa</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary shadow">
                    <div class="card-body">
                        <h5 class="card-title">Toplam Banka Hacmi</h5>
                        <h2><?= number_format($toplamPara, 2) ?> <small class="fs-6">(Karışık)</small></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success shadow">
                    <div class="card-body">
                        <h5 class="card-title">Aktif Müşteri</h5>
                        <h2><?= $toplamMusteri ?> Kişi</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-warning text-dark shadow">
                    <div class="card-body">
                        <h5 class="card-title">Personel Sayısı</h5>
                        <h2><?= $toplamPersonel ?> Kişi</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fa fa-trophy text-warning"></i> En Çok İşlem Yapanlar (Top 5)
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Müşteri Adı</th>
                                    <th class="text-end">İşlem Adedi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topCustomers as $tc): ?>
                                    <tr>
                                        <td><?= $tc['FirstName'] ?> <?= $tc['LastName'] ?></td>
                                        <td class="text-end fw-bold"><?= $tc['IslemSayisi'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if(empty($topCustomers)) echo "<p class='text-muted text-center'>Henüz işlem kaydı yok.</p>"; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow h-100">
                    <div class="card-header bg-dark text-white">
                        <i class="fa fa-money-bill-wave"></i> Varlık Dağılımı (Döviz/TL)
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Para Birimi</th>
                                    <th>Hesap Sayısı</th>
                                    <th class="text-end">Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencyStats as $cs): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $cs['Currency'] ?? 'TL' ?></td>
                                        <td><?= $cs['HesapSayisi'] ?></td>
                                        <td class="text-end text-success fw-bold">
                                            <?= number_format($cs['ToplamTutar'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>