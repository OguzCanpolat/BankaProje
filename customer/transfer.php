<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Sadece Müşteri Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Musteri') {
    header("Location: ../auth/login.php");
    exit;
}

// --- YENİ EKLENTİ: TCMB CANLI KUR FONKSİYONU ---
function getCanliDolarKuru() {
    try {
        // @ işareti internet yoksa hata vermesini engeller
        $xml = @simplexml_load_file("https://www.tcmb.gov.tr/kurlar/today.xml");
        
        if ($xml) {
            foreach ($xml->Currency as $currency) {
                if ($currency['Kod'] == "USD") {
                    return (float) $currency->BanknoteSelling;
                }
            }
        }
    } catch (Exception $e) {
        return 0; 
    }
    return 0;
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// --- 1. TRANSFER İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $gonderen_id = $_POST['sender_account_id'];
    $alici_iban = trim($_POST['receiver_iban']);
    $tutar = (float) $_POST['amount'];

    if (empty($alici_iban) || $tutar <= 0) {
        $error = "Lütfen geçerli bir IBAN ve tutar giriniz.";
    } else {
        try {
            $pdo->beginTransaction(); 

            // 1. CANLI KURU AL
            $DOLAR_KURU = getCanliDolarKuru();
            // Eğer kur çekilemezse (internet yoksa) varsayılan bir değer ata
            if ($DOLAR_KURU <= 0) { $DOLAR_KURU = 34.50; }

            // A) GÖNDEREN HESABI KONTROL ET (Currency eklendi)
            $stmt = $pdo->prepare("SELECT Balance, AccountNumber, Currency FROM Accounts WHERE AccountID = ? AND CustomerID IN (SELECT CustomerID FROM Customers WHERE UserID = ?)");
            $stmt->execute([$gonderen_id, $user_id]);
            $gonderen = $stmt->fetch();

            if (!$gonderen) {
                throw new Exception("Gönderen hesap bulunamadı veya size ait değil.");
            }
            if ($gonderen['Balance'] < $tutar) {
                throw new Exception("Yetersiz bakiye! Mevcut: " . number_format($gonderen['Balance'], 2) . " " . ($gonderen['Currency'] ?? 'TL'));
            }
            if ($gonderen['AccountNumber'] == $alici_iban) {
                throw new Exception("Kendi hesabınıza transfer yapamazsınız.");
            }

            // B) ALICI HESABI BUL (Currency eklendi)
            $stmt2 = $pdo->prepare("SELECT AccountID, Balance, Currency FROM Accounts WHERE AccountNumber = ?");
            $stmt2->execute([$alici_iban]);
            $alici = $stmt2->fetch();

            if (!$alici) {
                throw new Exception("Alıcı hesap numarası (IBAN) hatalı.");
            }

            // --- KUR ÇEVİRİM MANTIĞI BAŞLANGICI ---
            $gonderilen_miktar = $tutar;
            $aliciya_gececek_miktar = $tutar;
            $aciklama_ek = "";

            // Para birimi boşsa varsayılan TL kabul et
            $g_para = $gonderen['Currency'] ?? 'TL';
            $a_para = $alici['Currency'] ?? 'TL';

            // Durum 1: USD -> TL
            if ($g_para == 'USD' && $a_para == 'TL') {
                $aliciya_gececek_miktar = $tutar * $DOLAR_KURU;
                $aciklama_ek = " (Kur: $DOLAR_KURU)";
            }
            // Durum 2: TL -> USD
            elseif ($g_para == 'TL' && $a_para == 'USD') {
                $aliciya_gececek_miktar = $tutar / $DOLAR_KURU;
                $aciklama_ek = " (Kur: $DOLAR_KURU)";
            }
            // --- MANTIK BİTİŞİ ---

            // C) PARAYI AKTAR (Update İşlemleri)
            
            // 1. Gönderenden Düş (Kendi para biriminden)
            $updateSrc = $pdo->prepare("UPDATE Accounts SET Balance = Balance - ? WHERE AccountID = ?");
            $updateSrc->execute([$gonderilen_miktar, $gonderen_id]);

            // 2. Alıcıya Ekle (Hesaplanmış miktar)
            $updateDest = $pdo->prepare("UPDATE Accounts SET Balance = Balance + ? WHERE AccountID = ?");
            $updateDest->execute([$aliciya_gececek_miktar, $alici['AccountID']]);

            // --- İŞLEMİ KAYDET (INSERT) ---
            $sql_log = "INSERT INTO Transactions (SenderAccountID, ReceiverAccountID, Amount, Description) VALUES (?, ?, ?, ?)";
            $stmt_log = $pdo->prepare($sql_log);
            
            // Açıklamayı detaylandır: "Transfer: 100 USD -> 3450 TL"
            $detayli_aciklama = "Transfer: " . number_format($gonderilen_miktar, 2) . " " . $g_para . 
                                " -> " . number_format($aliciya_gececek_miktar, 2) . " " . $a_para . $aciklama_ek;

            $stmt_log->execute([$gonderen_id, $alici['AccountID'], $gonderilen_miktar, $detayli_aciklama]);

            // Oluşan işlemin ID'sini al
            $last_id = $pdo->lastInsertId();

            $pdo->commit(); 
            
            // Mesajı güncelle
            $message = "Transfer Başarılı!<br>
                        <b>Gönderilen:</b> $gonderilen_miktar $g_para <br>
                        <b>Alıcıya Geçen:</b> " . number_format($aliciya_gececek_miktar, 2) . " $a_para <br>
                        <a href='receipt.php?id=$last_id' target='_blank' class='btn btn-warning btn-sm mt-2'>Dekontu Görüntüle</a>";

        } catch (Exception $e) {
            $pdo->rollBack(); 
            $error = "Hata: " . $e->getMessage();
        }
    }
}

// --- 2. VERİLERİ ÇEKME ---

// Kendi Hesaplarımı Çek (Currency eklendi)
$stmt = $pdo->prepare("SELECT a.AccountID, a.AccountNumber, a.Balance, a.Currency 
                       FROM Accounts a 
                       JOIN Customers c ON a.CustomerID = c.CustomerID 
                       WHERE c.UserID = ?");
$stmt->execute([$user_id]);
$my_accounts = $stmt->fetchAll();

// Başkalarının Hesaplarını Çek
$stmt2 = $pdo->prepare("SELECT a.AccountNumber, c.FirstName, c.LastName 
                        FROM Accounts a 
                        JOIN Customers c ON a.CustomerID = c.CustomerID 
                        WHERE c.UserID != ? 
                        ORDER BY c.FirstName ASC");
$stmt2->execute([$user_id]);
$other_accounts = $stmt2->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer | NeoBank</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); /* Koyu Mavi Gradyan */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        /* Ana Kart Tasarımı */
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            overflow: hidden;
            border: none;
        }

        .card-header-custom {
            background: transparent;
            padding: 25px;
            border-bottom: 1px solid #eee;
        }

        /* Sol Taraftaki Sanal Kart */
        .credit-card-visual {
            background: linear-gradient(45deg, #ff9a9e 0%, #fad0c4 99%, #fad0c4 100%);
            border-radius: 15px;
            padding: 20px;
            color: white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
            height: 100%;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .credit-card-visual::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }

        /* Form Elemanları */
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid #e0e0e0;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 4px rgba(42, 82, 152, 0.1);
            border-color: #2a5298;
            background-color: #fff;
        }

        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 1px solid #e0e0e0;
            background: #fff;
            color: #666;
        }
        
        /* Buton */
        .btn-transfer {
            background: linear-gradient(90deg, #1e3c72, #2a5298);
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
            transition: transform 0.2s;
        }

        .btn-transfer:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.4);
        }

        /* Hızlı Seçim Listesi */
        .quick-select-wrapper {
            background: #f0f4f8;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4 d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2 fs-4"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success shadow-sm border-0 rounded-3 mb-4 d-flex align-items-center">
                        <i class="fas fa-check-circle me-2 fs-4"></i>
                        <div><?= $message ?></div>
                    </div>
                <?php endif; ?>

                <div class="card main-card">
                    <div class="row g-0">
                        <div class="col-md-4 p-4 bg-light d-none d-md-block border-end">
                            <h5 class="text-muted mb-4">Özet</h5>
                            
                            <div class="credit-card-visual mb-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <i class="fas fa-wifi fa-2x opacity-50"></i>
                                    <span class="badge bg-dark opacity-50">Debit</span>
                                </div>
                                <div>
                                    <div class="fs-5 mb-1" style="letter-spacing: 2px;">**** **** **** 1234</div>
                                    <small class="opacity-75">Müşteri Hesabı</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-end">
                                    <div>
                                        <small class="d-block opacity-75" style="font-size: 10px;">SAHİBİ</small>
                                        <span class="fw-bold">SAYIN MÜŞTERİ</span>
                                    </div>
                                    <i class="fab fa-cc-visa fa-2x"></i>
                                </div>
                            </div>

                            <div class="alert alert-info border-0 small">
                                <i class="fas fa-info-circle me-1"></i>
                                Güvenli işlem bölgesi. Tüm transferler 256-bit şifreleme ile korunmaktadır.
                            </div>
                            
                            <div class="text-center mt-auto">
                                <a href="../index.php" class="text-decoration-none text-muted small">
                                    <i class="fas fa-arrow-left me-1"></i> Ana Menüye Dön
                                </a>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="card-header-custom d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 fw-bold text-dark">Para Transferi</h4>
                                <span class="badge bg-primary rounded-pill">Hızlı İşlem</span>
                            </div>
                            
                            <div class="card-body p-4 p-md-5">
                                <form method="POST" action="">
                                    
                                    <div class="mb-4">
                                        <label class="form-label text-muted small fw-bold text-uppercase">Hangi Hesaptan?</label>
                                        <select name="sender_account_id" class="form-select form-select-lg" required>
                                            <?php foreach ($my_accounts as $acc): ?>
                                                <option value="<?= $acc['AccountID'] ?>">
                                                    <?= $acc['AccountNumber'] ?> — <?= number_format($acc['Balance'], 2) ?> <?= $acc['Currency'] ?? 'TL' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label text-muted small fw-bold text-uppercase">Kime?</label>
                                        
                                        <div class="quick-select-wrapper">
                                            <select id="hizliSecim" class="form-select mb-3" onchange="ibanDoldur()">
                                                <option value="">★ Kayıtlı Alıcılardan Seç</option>
                                                <?php foreach ($other_accounts as $other): ?>
                                                    <option value="<?= $other['AccountNumber'] ?>">
                                                        <?= $other['FirstName'] ?> <?= $other['LastName'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-university"></i></span>
                                                <input type="text" name="receiver_iban" id="aliciIban" class="form-control" placeholder="TR00 0000..." required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label text-muted small fw-bold text-uppercase">Ne Kadar?</label>
                                        <div class="input-group input-group-lg">
                                            <input type="number" name="amount" class="form-control fw-bold text-primary" step="0.01" min="1" placeholder="0.00" required>
                                            <span class="input-group-text bg-white text-muted">Tutar</span>
                                        </div>
                                        <div class="form-text text-end mt-2 text-muted fst-italic">
                                            <i class="fas fa-coins me-1"></i> Güncel kur otomatik uygulanır.
                                        </div>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-transfer text-white">
                                            TRANSFERİ ONAYLA <i class="fas fa-paper-plane ms-2"></i>
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-white-50 small">
                    &copy; 2026 Banka Veri Modeli Projesi
                </div>

            </div>
        </div>
    </div>

    <script>
    function ibanDoldur() {
        var secim = document.getElementById("hizliSecim").value;
        if(secim !== "") {
            document.getElementById("aliciIban").value = secim;
            // Ufak bir animasyon efekti
            document.getElementById("aliciIban").style.backgroundColor = "#e8f0fe";
            setTimeout(() => {
                document.getElementById("aliciIban").style.backgroundColor = "#fff";
            }, 500);
        }
    }
    </script>

</body>
</html>