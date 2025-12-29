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
    <title>Para Transferi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fa fa-exchange-alt"></i> Para Transferi</h4>
                        <a href="../index.php" class="btn btn-light btn-sm text-primary">Ana Sayfa</a>
                    </div>
                    <div class="card-body">
                        
                        <form method="POST" action="">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold">Gönderen Hesap</label>
                                <select name="sender_account_id" class="form-select" required>
                                    <?php foreach ($my_accounts as $acc): ?>
                                        <option value="<?= $acc['AccountID'] ?>">
                                            <?= $acc['AccountNumber'] ?> (<?= number_format($acc['Balance'], 2) ?> <?= $acc['Currency'] ?? 'TL' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3 p-3 bg-light border rounded">
                                <label class="form-label fw-bold text-primary">Kime Göndereceksin?</label>
                                
                                <select id="hizliSecim" class="form-select mb-2" onchange="ibanDoldur()">
                                    <option value="">-- Listeden Seç (İstersen) --</option>
                                    <?php foreach ($other_accounts as $other): ?>
                                        <option value="<?= $other['AccountNumber'] ?>">
                                            <?= $other['FirstName'] ?> <?= $other['LastName'] ?> - <?= $other['AccountNumber'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="input-group">
                                    <span class="input-group-text bg-white">IBAN</span>
                                    <input type="text" name="receiver_iban" id="aliciIban" class="form-control" placeholder="TR..." required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Tutar</label>
                                <div class="input-group">
                                    <input type="number" name="amount" class="form-control" step="0.01" min="1" placeholder="0.00" required>
                                    <span class="input-group-text">Tutar</span>
                                </div>
                                <small class="text-muted">Farklı para birimleri arasında işlem yaparsanız güncel kur üzerinden çevrilir.</small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">Transfer Yap</button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function ibanDoldur() {
        var secim = document.getElementById("hizliSecim").value;
        if(secim !== "") {
            document.getElementById("aliciIban").value = secim;
        }
    }
    </script>

</body>
</html>