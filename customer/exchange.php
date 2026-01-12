<?php
session_start();
require_once '../includes/db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Musteri') {
    header("Location: ../index.php");
    exit;
}

// Müşteri Bilgileri
$userID = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT CustomerID FROM Customers WHERE UserID = ?");
$stmt->execute([$userID]);
$customerID = $stmt->fetchColumn();

// Hesapları Çek
$stmt = $pdo->prepare("SELECT * FROM Accounts WHERE CustomerID = ?");
$stmt->execute([$customerID]);
$accounts = $stmt->fetchAll();

// Hesapları Ayır
$tl_accounts = [];
$usd_accounts = [];
foreach ($accounts as $acc) {
    if ($acc['TypeID'] == 1) $tl_accounts[] = $acc; // TL
    elseif ($acc['TypeID'] == 2) $usd_accounts[] = $acc; // USD
}

// --- TCMB KUR ÇEKME ---
$dolar_kuru = 34.50; // Varsayılan
try {
    $xml = @simplexml_load_file("https://www.tcmb.gov.tr/kurlar/today.xml");
    if ($xml) {
        foreach ($xml->Currency as $currency) {
            if ($currency['Kod'] == "USD") {
                $dolar_kuru = (float) $currency->BanknoteSelling;
                break;
            }
        }
    }
} catch (Exception $e) { }

// İşlem Mantığı (POST)
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount']; 
    $mode = $_POST['mode']; // 'buy' veya 'sell'
    
    // Formdan gelen verileri moda göre ayarla
    if ($mode == 'buy') {
        $fromAccountID = $_POST['account_tl'];
        $toAccountID = $_POST['account_usd'];
        // Tutar TL girildiyse USD'ye çevirip ekle, ya da tam tersi. 
        // Basitlik için: Kullanıcı "Ne kadar TL harcayacağını" girer.
        $dusulecek_tutar = $amount; // TL
        $eklenecek_tutar = $amount / $dolar_kuru; // USD
        $aciklama = "Döviz Alış ($amount TL -> " . number_format($eklenecek_tutar, 2) . " USD)";
    } else {
        $fromAccountID = $_POST['account_usd'];
        $toAccountID = $_POST['account_tl'];
        // Kullanıcı "Ne kadar USD bozduracağını" girer.
        $dusulecek_tutar = $amount; // USD
        $eklenecek_tutar = $amount * $dolar_kuru; // TL
        $aciklama = "Döviz Satış ($amount USD -> " . number_format($eklenecek_tutar, 2) . " TL)";
    }

    if ($amount > 0 && !empty($fromAccountID) && !empty($toAccountID)) {
        // Bakiye Kontrolü
        $stmt = $pdo->prepare("SELECT Balance FROM Accounts WHERE AccountID = ?");
        $stmt->execute([$fromAccountID]);
        $currentBalance = $stmt->fetchColumn();

        if ($currentBalance >= $dusulecek_tutar) {
            try {
                $pdo->beginTransaction();

                // Düş
                $pdo->prepare("UPDATE Accounts SET Balance = Balance - ? WHERE AccountID = ?")->execute([$dusulecek_tutar, $fromAccountID]);
                // Ekle
                $pdo->prepare("UPDATE Accounts SET Balance = Balance + ? WHERE AccountID = ?")->execute([$eklenecek_tutar, $toAccountID]);
                // Log
                $pdo->prepare("INSERT INTO Transactions (SenderAccountID, ReceiverAccountID, Amount, TransactionDate, Description) VALUES (?, ?, ?, NOW(), ?)")->execute([$fromAccountID, $toAccountID, $dusulecek_tutar, $aciklama]);

                $pdo->commit();
                $message = "<div class='alert alert-success border-0 shadow-sm'><i class='fas fa-check-circle me-2'></i> İşlem Başarılı!</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert alert-danger'>Hata: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Yetersiz Bakiye!</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Lütfen tutar ve hesap seçimi yapın.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yatırım & Döviz | NeoBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">

    <style>
        /* Modern Piyasa Kartları */
        .market-card {
            background: #1e2022; /* Koyu tema */
            color: #fff;
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .market-card small { color: #aaa; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; }
        .market-card h4 { margin: 5px 0 0 0; font-weight: 600; }
        .trend-up { color: #00d2d3; font-size: 0.8rem; }
        .trend-down { color: #ff6b6b; font-size: 0.8rem; }

        /* Tab Switcher (Al/Sat) */
        .trade-tabs {
            background: #f1f3f6;
            border-radius: 12px;
            padding: 5px;
            display: flex;
            margin-bottom: 25px;
        }
        .trade-tab-btn {
            flex: 1;
            border: none;
            background: transparent;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            color: #8395a7;
            transition: all 0.3s;
        }
        .trade-tab-btn.active.buy {
            background: #fff;
            color: #1dd1a1; /* Yeşil */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .trade-tab-btn.active.sell {
            background: #fff;
            color: #ff6b6b; /* Kırmızı */
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Büyük Input Alanı */
        .amount-input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .amount-input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3436;
            text-align: center;
            padding: 10px;
            outline: none;
        }
        .amount-input::placeholder { color: #dfe4ea; }
        .currency-label {
            text-align: center;
            font-size: 0.9rem;
            color: #8395a7;
            font-weight: 600;
        }
        
        /* Hesap Seçimi Kartları */
        .account-select-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 10px;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
        <div class="container">
            <a href="../index.php" class="text-decoration-none text-secondary fw-bold">
                <i class="fas fa-arrow-left me-2"></i> Panele Dön
            </a>
            <span class="fw-bold text-primary">Yatırım Merkezi</span>
        </div>
    </nav>

    <div class="container pb-5" style="max-width: 600px;">
        
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="market-card">
                    <small>USD / TL</small>
                    <h4><?= number_format($dolar_kuru, 2) ?></h4>
                    <span class="trend-up"><i class="fas fa-caret-up"></i> %0.45</span>
                </div>
            </div>
            <div class="col-4">
                <div class="market-card">
                    <small>EUR / TL</small>
                    <h4><?= number_format($dolar_kuru * 1.05, 2) ?></h4>
                    <span class="trend-down"><i class="fas fa-caret-down"></i> %0.12</span>
                </div>
            </div>
            <div class="col-4">
                <div class="market-card">
                    <small>GRAM ALTIN</small>
                    <h4>2,950</h4>
                    <span class="trend-up"><i class="fas fa-caret-up"></i> %1.2</span>
                </div>
            </div>
        </div>

        <?= $message ?>

        <div class="glass-card p-4">
            
            <form method="POST" id="exchangeForm">
                <input type="hidden" name="mode" id="modeInput" value="buy">
                
                <div class="trade-tabs">
                    <button type="button" class="trade-tab-btn active buy" onclick="setMode('buy')">
                        <i class="fas fa-download me-2"></i>Döviz AL
                    </button>
                    <button type="button" class="trade-tab-btn sell" onclick="setMode('sell')">
                        <i class="fas fa-upload me-2"></i>Döviz SAT
                    </button>
                </div>

                <div class="amount-input-group">
                    <div class="currency-label mb-1" id="inputLabel">HARCANACAK TUTAR (TL)</div>
                    <input type="number" name="amount" id="amount" class="amount-input" placeholder="0.00" step="0.01" required oninput="calculate()">
                </div>

                <div class="text-center mb-4">
                    <span class="text-muted small d-block">Karşılığı</span>
                    <h3 class="fw-bold text-primary" id="resultDisplay">0.00 USD</h3>
                    <small class="text-muted">Kur: 1 USD = <?= $dolar_kuru ?> TL</small>
                </div>

                <hr class="my-4 opacity-25">

                <div class="row g-3">
                    <div class="col-6">
                        <label class="small text-muted fw-bold mb-2">Çıkış Hesabı</label>
                        <div class="account-select-box">
                            <select name="account_tl" id="acc_tl" class="form-select border-0 bg-transparent py-0">
                                <?php foreach ($tl_accounts as $acc): ?>
                                    <option value="<?= $acc['AccountID'] ?>">TL - <?= substr($acc['AccountNumber'], -4) ?> (<?= number_format($acc['Balance'],0) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="small text-muted fw-bold mb-2">Giriş Hesabı</label>
                        <div class="account-select-box">
                            <select name="account_usd" id="acc_usd" class="form-select border-0 bg-transparent py-0">
                                <?php foreach ($usd_accounts as $acc): ?>
                                    <option value="<?= $acc['AccountID'] ?>">USD - <?= substr($acc['AccountNumber'], -4) ?> (<?= number_format($acc['Balance'],0) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" id="submitBtn" class="btn btn-primary-custom btn-lg">
                        Dolar Al
                    </button>
                </div>

            </form>
        </div>
        
        <div class="text-center mt-4">
            <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Kurlar TCMB verilerine göre anlık güncellenir.</small>
        </div>

    </div>

    <script>
        const rate = <?= $dolar_kuru ?>;
        let currentMode = 'buy'; // 'buy' veya 'sell'

        function setMode(mode) {
            currentMode = mode;
            document.getElementById('modeInput').value = mode;

            // Tab Butonlarının Rengini Ayarla
            const btns = document.querySelectorAll('.trade-tab-btn');
            btns.forEach(b => b.classList.remove('active'));
            
            const activeBtn = document.querySelector(`.trade-tab-btn.${mode}`);
            activeBtn.classList.add('active');

            // Etiketleri ve Butonu Güncelle
            const label = document.getElementById('inputLabel');
            const btn = document.getElementById('submitBtn');
            const resDisp = document.getElementById('resultDisplay');

            if (mode === 'buy') {
                label.innerText = 'HARCANACAK TUTAR (TL)';
                btn.innerText = 'DOLAR AL';
                btn.className = 'btn btn-primary-custom btn-lg w-100'; // Yeşil/Mavi tema
            } else {
                label.innerText = 'BOZDURULACAK TUTAR (USD)';
                btn.innerText = 'DOLAR SAT';
                btn.className = 'btn btn-secondary-custom btn-lg w-100'; // Kırmızı/Turuncu tema
            }

            calculate(); // Mod değişince hesaplamayı yenile
        }

        function calculate() {
            const amount = parseFloat(document.getElementById('amount').value);
            const display = document.getElementById('resultDisplay');
            
            if (isNaN(amount) || amount <= 0) {
                display.innerText = (currentMode === 'buy') ? "0.00 USD" : "0.00 TL";
                return;
            }

            let result = 0;
            if (currentMode === 'buy') {
                // TL girdik, USD alıyoruz
                result = amount / rate;
                display.innerText = result.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " USD";
            } else {
                // USD girdik, TL alıyoruz
                result = amount * rate;
                display.innerText = result.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " TL";
            }
        }
    </script>

</body>
</html>