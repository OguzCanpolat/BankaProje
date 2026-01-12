<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Giriş yapılmış mı?
if (!isset($_SESSION['user_id'])) {
    die("Erişim reddedildi.");
}

// URL'den İşlem ID'sini al
if (!isset($_GET['id'])) {
    die("Geçersiz işlem ID'si.");
}
$trans_id = $_GET['id'];

// İşlem detaylarını çek
$sql = "SELECT 
            t.TransactionDate, 
            t.Amount, 
            t.Description,
            t.TransactionID,
            acc_sender.AccountNumber as SenderIBAN,
            cust_sender.FirstName as SenderName, 
            cust_sender.LastName as SenderSurname,
            acc_sender.Currency as SenderCurrency,
            acc_receiver.AccountNumber as ReceiverIBAN,
            cust_receiver.FirstName as ReceiverName, 
            cust_receiver.LastName as ReceiverSurname,
            acc_receiver.Currency as ReceiverCurrency
        FROM Transactions t
        JOIN Accounts acc_sender ON t.SenderAccountID = acc_sender.AccountID
        JOIN Customers cust_sender ON acc_sender.CustomerID = cust_sender.CustomerID
        JOIN Accounts acc_receiver ON t.ReceiverAccountID = acc_receiver.AccountID
        JOIN Customers cust_receiver ON acc_receiver.CustomerID = cust_receiver.CustomerID
        WHERE t.TransactionID = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$trans_id]);
$dekont = $stmt->fetch();

if (!$dekont) {
    die("Dekont bulunamadı! İşlem ID hatalı olabilir.");
}

// --- AKILLI İSİM DEĞİŞTİRME (FIX) ---
// Eğer işlem bir "Kredi" veya "ATM" işlemiyse, Gönderen ismini düzeltelim.
$gonderen_ad = $dekont['SenderName'] . ' ' . $dekont['SenderSurname'];
$gonderen_iban = $dekont['SenderIBAN'];
$gonderen_para = $dekont['SenderCurrency'] ?? 'TL';

// 1. KREDİ İSE
if (strpos($dekont['Description'], 'Kredi') !== false) {
    $gonderen_ad = "BANKA KREDİ SERVİSİ";
    $gonderen_iban = "TR-BANKA-MERKEZ";
    $gonderen_para = "TRY";
}
// 2. ATM İSE
elseif (strpos($dekont['Description'], 'ATM') !== false) {
    if ($dekont['SenderIBAN'] == $dekont['ReceiverIBAN']) {
        // Para yatırma veya çekme
        $gonderen_ad = "ATM / NAKİT İŞLEM";
        $gonderen_iban = "ATM-NO-001";
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>İşlem Dekontu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .dekont-box {
            max-width: 700px;
            margin: 50px auto;
            border: 1px solid #ddd;
            padding: 40px;
            background: #fff;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0,0,0,0.03);
            font-weight: bold;
            pointer-events: none;
            z-index: 0;
        }
        .content { position: relative; z-index: 1; }
        .logo-area { border-bottom: 2px solid #0d6efd; padding-bottom: 20px; margin-bottom: 30px; }
    </style>
</head>
<body class="bg-light">

    <div class="container">
        <div class="dekont-box">
            <div class="watermark">BANKA</div>
            
            <div class="content">
                <div class="logo-area d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold text-primary mb-0"><i class="fa fa-university"></i> Banka Sistemi</h2>
                        <small class="text-muted">Resmi İşlem Dekontu</small>
                    </div>
                    <div class="text-end">
                        <h5 class="mb-0 text-secondary">Referans No</h5>
                        <span class="font-monospace fw-bold fs-5">TRX-<?= $dekont['TransactionID'] ?></span>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <h6 class="text-secondary text-uppercase mb-1">İşlem Tarihi</h6>
                        <p class="fw-bold fs-5"><?= date("d.m.Y H:i", strtotime($dekont['TransactionDate'])) ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <h6 class="text-secondary text-uppercase mb-1">İşlem Tutarı</h6>
                        <p class="fw-bold fs-3 text-success">
                            <?= number_format($dekont['Amount'], 2) ?> 
                            <small class="fs-6 text-muted"><?= $dekont['ReceiverCurrency'] ?? 'TL' ?></small>
                        </p>
                    </div>
                </div>

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body p-4">
                        <div class="row align-items-center">
                            
                            <div class="col-md-5">
                                <small class="text-muted d-block fw-bold mb-1">GÖNDEREN</small>
                                <span class="fs-5 fw-bold text-dark"><?= $gonderen_ad ?></span><br>
                                <span class="font-monospace text-secondary"><?= $gonderen_iban ?></span>
                            </div>
                            
                            <div class="col-md-2 text-center">
                                <i class="fa-solid fa-arrow-right fs-3 text-primary d-none d-md-block"></i>
                                <i class="fa-solid fa-arrow-down fs-3 text-primary d-md-none my-3"></i>
                            </div>

                            <div class="col-md-5 text-md-end text-start">
                                <small class="text-muted d-block fw-bold mb-1">ALICI</small>
                                <span class="fs-5 fw-bold text-dark"><?= $dekont['ReceiverName'] . ' ' . $dekont['ReceiverSurname'] ?></span><br>
                                <span class="font-monospace text-secondary"><?= $dekont['ReceiverIBAN'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <h6 class="text-secondary">Açıklama:</h6>
                    <p class="fst-italic border-start border-4 border-primary ps-3 bg-light py-2 mb-0">
                        <?= $dekont['Description'] ?>
                    </p>
                </div>

                <div class="text-center mt-5 d-print-none">
                    <button onclick="window.print()" class="btn btn-primary btn-lg px-4 me-2">
                        <i class="fa-solid fa-print"></i> Yazdır / PDF
                    </button>
                    <button onclick="window.close()" class="btn btn-outline-secondary btn-lg px-4">
                        Kapat
                    </button>
                    <div class="mt-3 text-muted small">
                        Bu belge elektronik ortamda üretilmiştir. Islak imza gerektirmez.
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>