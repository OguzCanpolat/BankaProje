<?php
session_start();
require_once '../includes/db.php';

// Sadece Müşteri Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Musteri') {
    header("Location: ../index.php");
    exit;
}

// Müşteri ID'sini bul
$userID = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT CustomerID FROM Customers WHERE UserID = ?");
$stmt->execute([$userID]);
$customer = $stmt->fetch();
$customerID = $customer['CustomerID'];

// Müşterinin Hesaplarını Çek
$stmt = $pdo->prepare("SELECT * FROM Accounts WHERE CustomerID = ?");
$stmt->execute([$customerID]);
$accounts = $stmt->fetchAll();

// Hesapları Türlerine Göre Ayır (TL ve USD)
$tl_accounts = [];
$usd_accounts = [];

foreach ($accounts as $acc) {
    if ($acc['TypeID'] == 1) { 
        $tl_accounts[] = $acc;
    } elseif ($acc['TypeID'] == 2) { 
        $usd_accounts[] = $acc;
    }
}

// --- TCMB'den Canlı Veri Çekme ---
$dolar_kuru = 34.50; 
try {
    $tcmb_url = "https://www.tcmb.gov.tr/kurlar/today.xml";
    $xml = @simplexml_load_file($tcmb_url);
    if ($xml) {
        foreach ($xml->Currency as $currency) {
            if ($currency['Kod'] == "USD") {
                $dolar_kuru = (float) $currency->BanknoteSelling;
                break;
            }
        }
    }
} catch (Exception $e) { }
// ---------------------------------

$message = "";

// --- İŞLEM YAPILDI MI? ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fromAccountID = $_POST['from_account'];
    $toAccountID   = $_POST['to_account'];
    $amount        = $_POST['amount']; 
    $islemTuru     = $_POST['islem_turu']; 

    if ($amount > 0 && !empty($fromAccountID) && !empty($toAccountID)) {
        
        $stmt = $pdo->prepare("SELECT Balance FROM Accounts WHERE AccountID = ?");
        $stmt->execute([$fromAccountID]);
        $currentBalance = $stmt->fetchColumn();

        if ($currentBalance >= $amount) {
            
            if ($islemTuru == 'satin_al') {
                $dusulecek_tutar = $amount; // TL
                $eklenecek_tutar = $amount / $dolar_kuru; // Dolar
                $aciklama = "Döviz Alış ($amount TL karşılığında)";
            } else {
                $dusulecek_tutar = $amount; // Dolar
                $eklenecek_tutar = $amount * $dolar_kuru; // TL
                $aciklama = "Döviz Satış ($amount USD karşılığında)";
            }

            try {
                $pdo->beginTransaction();

                // 1. Kaynaktan Parayı Düş
                $sql1 = "UPDATE Accounts SET Balance = Balance - ? WHERE AccountID = ?";
                $stmt = $pdo->prepare($sql1);
                $stmt->execute([$dusulecek_tutar, $fromAccountID]);

                // 2. Hedefe Parayı Ekle
                $sql2 = "UPDATE Accounts SET Balance = Balance + ? WHERE AccountID = ?";
                $stmt = $pdo->prepare($sql2);
                $stmt->execute([$eklenecek_tutar, $toAccountID]);

                // 3. İŞLEM KAYDI (GARANTİLİ YÖNTEM)
                // TypeID sütununu tamamen sildik. Sadece standart verileri kaydediyoruz.
                // Veritabanındaki TypeID sütunu boş (NULL) kalacak, sorun değil.
                
                $sql3 = "INSERT INTO Transactions (SenderAccountID, ReceiverAccountID, Amount, TransactionDate, Description) VALUES (?, ?, ?, NOW(), ?)";
                
                $stmt = $pdo->prepare($sql3);
                $stmt->execute([$fromAccountID, $toAccountID, $dusulecek_tutar, $aciklama]);

                $pdo->commit();
                $message = "<div class='alert alert-success'>İşlem Başarılı! Hesabınıza ".number_format($eklenecek_tutar, 2)." birim eklendi.</div>";

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Hata oluştu: " . $e->getMessage() . "</div>";
            }

        } else {
            $message = "<div class='alert alert-warning'>Yetersiz Bakiye!</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Lütfen geçerli bir tutar girin.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Döviz İşlemleri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        function hesapla() {
            var miktar = parseFloat(document.getElementById('amount').value);
            var islem = document.getElementById('islem_turu').value;
            var kur = <?php echo $dolar_kuru; ?>;
            var sonuc = 0;
            var sonucMetin = "";

            if (isNaN(miktar)) miktar = 0;

            if (islem == 'satin_al') {
                sonuc = miktar / kur;
                sonucMetin = miktar + " TL karşılığında yaklaşık " + sonuc.toFixed(2) + " USD alacaksınız.";
                document.getElementById('from_acc_div').style.display = 'block'; 
                document.getElementById('to_acc_div').style.display = 'block';   
                document.getElementById('select_tl').name = 'from_account';
                document.getElementById('select_usd').name = 'to_account';
            } else {
                sonuc = miktar * kur;
                sonucMetin = miktar + " USD karşılığında yaklaşık " + sonuc.toFixed(2) + " TL alacaksınız.";
                document.getElementById('from_acc_div').style.display = 'block'; 
                document.getElementById('to_acc_div').style.display = 'block';   
                document.getElementById('select_usd').name = 'from_account';
                document.getElementById('select_tl').name = 'to_account';
            }
            document.getElementById('bilgi').innerText = sonucMetin;
        }
    </script>
</head>
<body class="bg-light">

    <div class="container mt-5" style="max-width: 700px;">
        <div class="d-flex justify-content-between mb-3">
            <a href="../index.php" class="btn btn-secondary">&larr; Panele Dön</a>
            <span class="badge bg-warning text-dark fs-6">Güncel Dolar Kuru: <?= $dolar_kuru ?> TL</span>
        </div>

        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4><i class="fa fa-exchange-alt"></i> Döviz Alım / Satım</h4>
            </div>
            <div class="card-body">
                <?= $message ?>

                <?php if (empty($tl_accounts) || empty($usd_accounts)): ?>
                    <div class="alert alert-danger">
                        Döviz işlemi yapabilmek için en az bir <b>TL</b> ve bir <b>Dolar</b> hesabınızın olması gerekir.
                    </div>
                <?php else: ?>

                <form method="POST">
                    <div class="mb-4 text-center">
                        <label class="form-label fw-bold">Ne Yapmak İstiyorsunuz?</label>
                        <select name="islem_turu" id="islem_turu" class="form-select form-select-lg mb-2 text-center" onchange="hesapla()">
                            <option value="satin_al">Dolar Satın Al (TL -> USD)</option>
                            <option value="bozdur">Dolar Bozdur (USD -> TL)</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3" id="from_acc_div">
                            <label class="form-label">TL Hesabı</label>
                            <select id="select_tl" name="from_account" class="form-select">
                                <?php foreach ($tl_accounts as $acc): ?>
                                    <option value="<?= $acc['AccountID'] ?>">
                                        <?= $acc['AccountNumber'] ?> (<?= $acc['Balance'] ?> TL)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3" id="to_acc_div">
                            <label class="form-label">Dolar Hesabı</label>
                            <select id="select_usd" name="to_account" class="form-select">
                                <?php foreach ($usd_accounts as $acc): ?>
                                    <option value="<?= $acc['AccountID'] ?>">
                                        <?= $acc['AccountNumber'] ?> (<?= $acc['Balance'] ?> USD)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">İşlem Yapılacak Miktar</label>
                        <div class="input-group">
                            <input type="number" name="amount" id="amount" class="form-control" placeholder="Örn: 100" step="0.01" oninput="hesapla()" required>
                            <span class="input-group-text"><i class="fa fa-money-bill-wave"></i></span>
                        </div>
                        <small class="text-muted" id="bilgi">Miktar girince hesaplama burada görünecek...</small>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2">İşlemi Onayla</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>hesapla();</script>
</body>
</html>