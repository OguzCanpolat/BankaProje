<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Giriş yapılmış mı?
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Musteri') {
    header("Location: ../auth/login.php");
    exit;
}

// URL'den Hesap ID'sini al
if (!isset($_GET['id'])) {
    die("Hesap ID belirtilmedi.");
}
$account_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// --- DÜZELTME BAŞLANGICI ---
// SQL'i güncelledik: AccountTypes tablosunu (t) ekledik ki CurrencyCode'u çekebilelim.
$sql_check = "SELECT a.*, c.FirstName, c.LastName, t.CurrencyCode 
              FROM Accounts a 
              JOIN Customers c ON a.CustomerID = c.CustomerID 
              JOIN AccountTypes t ON a.TypeID = t.TypeID 
              WHERE a.AccountID = ? AND c.UserID = ?";
              
$check = $pdo->prepare($sql_check);
$check->execute([$account_id, $user_id]);
$my_acc = $check->fetch();
// --- DÜZELTME BİTİŞİ ---

if (!$my_acc) {
    die("Hata: Bu hesaba erişim yetkiniz yok.");
}

// HAREKETLERİ ÇEKEN SORGU
$sql = "SELECT 
            t.TransactionID,
            t.TransactionDate,
            t.Amount,
            t.Description,
            t.SenderAccountID,
            t.ReceiverAccountID,
            acc_sender.AccountNumber as GonderenNo,
            /* Gönderenin para birimi için de join gerekebilir ama şimdilik basitleştirilmiş haliyle kalsın */
            acc_receiver.AccountNumber as AliciNo
        FROM Transactions t
        LEFT JOIN Accounts acc_sender ON t.SenderAccountID = acc_sender.AccountID
        LEFT JOIN Accounts acc_receiver ON t.ReceiverAccountID = acc_receiver.AccountID
        WHERE t.SenderAccountID = ? OR t.ReceiverAccountID = ?
        ORDER BY t.TransactionDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$account_id, $account_id]);
$hareketler = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Hesap Hareketleri</title>
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
        
        <div class="card shadow mb-4 border-start border-5 border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Hesap Hareketleri</h4>
                        <p class="text-muted mb-0">Hesap No: <b><?= $my_acc['AccountNumber'] ?></b></p>
                    </div>
                    
                    <div>
                        <a href="exchange.php" class="btn btn-warning shadow-sm">
                            <i class="fa fa-exchange-alt"></i> Döviz Al/Sat
                        </a>
                    </div>
                </div>
                
                <h3 class="text-primary mt-3">
                    <?= number_format($my_acc['Balance'], 2) ?> 
                    <span class="badge bg-primary fs-6 align-middle"><?= $my_acc['CurrencyCode'] ?></span>
                </h3>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-body p-0">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tarih</th>
                            <th>Açıklama</th>
                            <th>Karşı Taraf</th>
                            <th class="text-end pe-4">Tutar</th>
                            <th>Dekont</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hareketler as $h): ?>
                            <?php 
                                $is_incoming = ($h['ReceiverAccountID'] == $account_id);
                                $color = $is_incoming ? 'text-success' : 'text-danger';
                                $sign = $is_incoming ? '+' : '-';
                                $icon = $is_incoming ? 'fa-arrow-down' : 'fa-arrow-up';
                                $bg_icon = $is_incoming ? 'bg-success-subtle' : 'bg-danger-subtle';
                                
                                $karsi_taraf = $is_incoming ? ($h['GonderenNo'] ?? 'Bilinmiyor') : ($h['AliciNo'] ?? 'Bilinmiyor');
                                
                                if($h['SenderAccountID'] == $h['ReceiverAccountID']) {
                                    $karsi_taraf = "ATM / KASA";
                                }

                                // Hareketlerde de o anki hesabın para birimini gösterelim
                                $gosterilecek_birim = $my_acc['CurrencyCode'];
                            ?>
                            <tr>
                                <td><?= date("d.m.Y H:i", strtotime($h['TransactionDate'])) ?></td>
                                
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle p-2 me-2 <?= $bg_icon ?> text-center" style="width:35px; height:35px;">
                                            <i class="fa <?= $icon ?> <?= $color ?>" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <span><?= $h['Description'] ?></span>
                                    </div>
                                </td>

                                <td class="font-monospace text-muted small"><?= $karsi_taraf ?></td>
                                
                                <td class="fw-bold <?= $color ?> text-end pe-4">
                                    <?= $sign ?> <?= number_format($h['Amount'], 2) ?> 
                                    <span class="small text-secondary"><?= $gosterilecek_birim ?></span>
                                </td>
                                
                                <td>
                                    <a href="receipt.php?id=<?= $h['TransactionID'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary border-0">
                                        <i class="fa fa-file-invoice fa-lg"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>