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

// --- MEVCUT SQL SORGUSU (DOKUNULMADI) ---
$sql_check = "SELECT a.*, c.FirstName, c.LastName, t.CurrencyCode 
              FROM Accounts a 
              JOIN Customers c ON a.CustomerID = c.CustomerID 
              JOIN AccountTypes t ON a.TypeID = t.TypeID 
              WHERE a.AccountID = ? AND c.UserID = ?";
              
$check = $pdo->prepare($sql_check);
$check->execute([$account_id, $user_id]);
$my_acc = $check->fetch();

if (!$my_acc) {
    die("Hata: Bu hesaba erişim yetkiniz yok.");
}

// HAREKETLERİ ÇEKEN SORGU (DOKUNULMADI)
$sql = "SELECT 
            t.TransactionID,
            t.TransactionDate,
            t.Amount,
            t.Description,
            t.SenderAccountID,
            t.ReceiverAccountID,
            acc_sender.AccountNumber as GonderenNo,
            acc_receiver.AccountNumber as AliciNo
        FROM Transactions t
        LEFT JOIN Accounts acc_sender ON t.SenderAccountID = acc_sender.AccountID
        LEFT JOIN Accounts acc_receiver ON t.ReceiverAccountID = acc_receiver.AccountID
        WHERE t.SenderAccountID = ? OR t.ReceiverAccountID = ?
        ORDER BY t.TransactionDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$account_id, $account_id]);
$hareketler = $stmt->fetchAll();

// --- YENİ: TARİH FORMATLAYICI FONKSİYON ---
function formatTarihDostu($tarihString) {
    setlocale(LC_TIME, 'tr_TR.UTF-8', 'tr_TR', 'turkish'); // Türkçe Tarih
    $tarih = new DateTime($tarihString);
    $bugun = new DateTime();
    $dun = new DateTime('yesterday');

    if ($tarih->format('Y-m-d') == $bugun->format('Y-m-d')) {
        return "Bugün";
    } elseif ($tarih->format('Y-m-d') == $dun->format('Y-m-d')) {
        return "Dün";
    } else {
        // Örn: 12 Ekim 2023
        $formatter = new IntlDateFormatter('tr_TR', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
        return $formatter->format($tarih);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesap Hareketleri | NeoBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">

    <style>
        /* Bu sayfaya özel stiller */
        .transaction-item {
            background: #fff;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .transaction-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: #e0e0e0;
        }

        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .date-header {
            font-size: 0.85rem;
            font-weight: 600;
            color: #888;
            margin: 25px 0 10px 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .balance-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 0 0 30px 30px;
            padding-bottom: 30px;
            margin-bottom: -20px; /* İçeriği yukarı çekmek için */
        }
    </style>
</head>
<body style="background-color: #f8f9fa;">

    <div class="balance-header pt-4 px-4 shadow-sm">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="../index.php" class="text-white text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i> Ana Sayfa
                </a>
                <span class="opacity-75 font-monospace"><?= chunk_split($my_acc['AccountNumber'], 4, ' ') ?></span>
            </div>
            
            <div class="text-center pb-4">
                <small class="opacity-75 text-uppercase fw-bold">Mevcut Bakiye</small>
                <h1 class="display-4 fw-bold mb-0">
                    <?= number_format($my_acc['Balance'], 2) ?> <span class="fs-4"><?= $my_acc['CurrencyCode'] ?></span>
                </h1>
            </div>
        </div>
    </div>

    <div class="container" style="max-width: 800px; margin-top: 0px;">
        
        <div class="d-flex justify-content-center gap-3 mb-4 mt-3">
            <a href="transfer.php" class="btn btn-light shadow-sm rounded-pill px-4 text-primary fw-bold">
                <i class="fas fa-paper-plane me-2"></i> Transfer
            </a>
            <a href="exchange.php" class="btn btn-light shadow-sm rounded-pill px-4 text-success fw-bold">
                <i class="fas fa-sync-alt me-2"></i> Döviz
            </a>
        </div>

        <?php 
        $currentDate = '';
        
        if (count($hareketler) == 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
                <p>Henüz bu hesapta bir işlem yok.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($hareketler as $h): ?>
            <?php 
                // Tarih Başlığı Kontrolü
                $txnDate = date('Y-m-d', strtotime($h['TransactionDate']));
                if ($txnDate != $currentDate) {
                    echo '<div class="date-header">' . formatTarihDostu($txnDate) . '</div>';
                    $currentDate = $txnDate;
                }

                // Gelen/Giden Mantığı
                $is_incoming = ($h['ReceiverAccountID'] == $account_id);
                $colorClass = $is_incoming ? 'text-success' : 'text-danger';
                $bgClass = $is_incoming ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10';
                $icon = $is_incoming ? 'fa-arrow-down' : 'fa-arrow-up';
                $sign = $is_incoming ? '+' : '-';
                
                // Karşı Taraf Belirleme
                $karsi_taraf = $is_incoming ? ($h['GonderenNo'] ?? 'Bilinmiyor') : ($h['AliciNo'] ?? 'Bilinmiyor');
                if($h['SenderAccountID'] == $h['ReceiverAccountID']) {
                    $karsi_taraf = "ATM / KASA";
                    $icon = 'fa-sync'; // Kendi kendine transfer için farklı ikon
                    $colorClass = 'text-warning';
                    $bgClass = 'bg-warning bg-opacity-10';
                    $sign = '';
                }
            ?>

            <div class="transaction-item">
                <div class="d-flex align-items-center">
                    <div class="icon-box <?= $bgClass ?> <?= $colorClass ?>">
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    
                    <div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($h['Description']) ?></div>
                        <div class="text-muted small">
                            <i class="far fa-clock me-1"></i><?= date("H:i", strtotime($h['TransactionDate'])) ?> 
                            &bull; 
                            <span class="font-monospace"><?= $karsi_taraf ?></span>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <div class="fw-bold fs-5 <?= $colorClass ?>">
                        <?= $sign ?><?= number_format($h['Amount'], 2) ?> <small><?= $my_acc['CurrencyCode'] ?></small>
                    </div>
                    
                    <a href="receipt.php?id=<?= $h['TransactionID'] ?>" target="_blank" class="text-decoration-none small text-secondary">
                        <i class="fas fa-file-invoice me-1"></i> Dekont
                    </a>
                </div>
            </div>

        <?php endforeach; ?>
        
        <div class="text-center mt-5 mb-5">
            <small class="text-muted">Son 30 günlük hareketler listelenmektedir.</small>
        </div>

    </div>

</body>
</html>