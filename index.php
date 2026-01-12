<?php
session_start();
require_once 'includes/db.php';

// Giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// -- Müşteriyse Hesaplarını Çek --
$hesaplar = [];
$total_balance = 0; // Toplam varlık için

if ($_SESSION['role'] == 'Musteri') {
    try {
        $stmt = $pdo->prepare("CALL sp_GetCustomerAccounts(?)");
        $stmt->execute([$_SESSION['user_id']]);
        $hesaplar = $stmt->fetchAll();
        $stmt->closeCursor();

        // Toplam Bakiyeyi Hesapla (Basit toplama, kur çevirisi hariç)
        foreach ($hesaplar as $h) {
            $total_balance += $h['Balance'];
        }

    } catch (PDOException $e) {
        $hata = "Hesaplar yüklenirken hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa | NeoBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">

    <style>
        /* Bu sayfaya özel cüzdan kartı stilleri */
        .wallet-card {
            border-radius: 20px;
            padding: 25px;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: transform 0.3s;
            border: none;
        }
        .wallet-card:hover { transform: translateY(-5px); }

        /* Kart Renkleri */
        .bg-gradient-gold { background: linear-gradient(135deg, #FFD700 0%, #FDB931 100%); color: #333; }
        .bg-gradient-dark { background: linear-gradient(135deg, #434343 0%, #000000 100%); }
        .bg-gradient-blue { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        .bg-gradient-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }

        /* Hızlı İşlem Butonları */
        .action-btn {
            background: rgba(255,255,255,0.9);
            border: none;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            display: block;
            text-decoration: none;
            color: #333;
        }
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            color: #1e3c72;
        }
        .action-icon { font-size: 2rem; margin-bottom: 10px; display: block; }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm px-4 mb-4">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary" href="#"><i class="fas fa-university me-2"></i>NeoBank</a>
            
            <div class="ms-auto d-flex align-items-center">
                <div class="me-3 text-end d-none d-md-block">
                    <small class="text-muted d-block" style="font-size: 0.75rem;">GİRİŞ YAPAN</small>
                    <span class="fw-bold text-dark"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                    <span class="badge bg-light text-dark border"><?= $_SESSION['role'] ?></span>
                </div>
                <a href="auth/logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        
        <?php if ($_SESSION['role'] == 'Musteri'): ?>
            
            <div class="row mb-5">
                <div class="col-md-12">
                    <div class="p-4 bg-white rounded-4 shadow-sm d-flex justify-content-between align-items-center border glass-card">
                        <div>
                            <span class="text-muted text-uppercase small fw-bold">Toplam Varlığım</span>
                            <h1 class="fw-bold text-dark mt-1 mb-0">
                                <?= number_format($total_balance, 2) ?> <span class="fs-4 text-muted">₺</span>
                            </h1>
                        </div>
                        <div class="d-none d-md-block">
                            <span class="p-3 bg-light rounded-circle text-primary">
                                <i class="fas fa-wallet fa-2x"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12 mb-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-secondary">Cüzdanım</h5>
                    </div>

                <?php if (empty($hesaplar)): ?>
                    <div class="col-12">
                        <div class="alert alert-warning border-0 shadow-sm">
                            <i class="fas fa-info-circle me-2"></i> Henüz açık bir hesabınız bulunmuyor. Şubemizle görüşebilirsiniz.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($hesaplar as $index => $hesap): ?>
                        <?php 
                            // Rengi para birimine göre belirle
                            $bg_class = 'bg-gradient-blue'; // Varsayılan (TL)
                            if ($hesap['CurrencyCode'] == 'USD') $bg_class = 'bg-gradient-green';
                            elseif ($hesap['CurrencyCode'] == 'EUR') $bg_class = 'bg-gradient-dark';
                            elseif ($hesap['CurrencyCode'] == 'XAU') $bg_class = 'bg-gradient-gold';
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="wallet-card <?= $bg_class ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <i class="fas fa-wifi opacity-50 fa-2x"></i>
                                    <span class="badge bg-white bg-opacity-25 backdrop-blur"><?= $hesap['CurrencyCode'] ?></span>
                                </div>
                                
                                <div class="mt-4">
                                    <small class="opacity-75 d-block mb-1 text-uppercase"><?= $hesap['TypeName'] ?></small>
                                    <h4 class="mb-0" style="font-family: monospace; letter-spacing: 2px;">
                                        <?= chunk_split($hesap['AccountNumber'], 4, ' ') ?>
                                    </h4>
                                </div>

                                <div class="d-flex justify-content-between align-items-end mt-4">
                                    <div>
                                        <small class="opacity-75 d-block" style="font-size: 10px;">BAKİYE</small>
                                        <span class="fs-4 fw-bold"><?= number_format($hesap['Balance'], 2) ?></span>
                                    </div>
                                    <a href="customer/transactions.php?id=<?= $hesap['AccountID'] ?>" class="btn btn-sm btn-light rounded-pill px-3" style="font-size: 12px;">
                                        Hareketler <i class="fas fa-chevron-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="row mb-5">
                <h5 class="fw-bold text-secondary mb-3">Hızlı İşlemler</h5>
                
                <div class="col-6 col-md-3 mb-3">
                    <a href="customer/transfer.php" class="action-btn">
                        <i class="fas fa-paper-plane action-icon text-primary"></i>
                        <span class="fw-bold d-block">Para Transferi</span>
                        <small class="text-muted">Havale / EFT</small>
                    </a>
                </div>

                <div class="col-6 col-md-3 mb-3">
                    <a href="customer/atm.php" class="action-btn">
                        <i class="fas fa-money-bill-wave action-icon text-success"></i>
                        <span class="fw-bold d-block">ATM İşlemleri</span>
                        <small class="text-muted">Yatır / Çek</small>
                    </a>
                </div>

                <div class="col-6 col-md-3 mb-3">
                    <a href="customer/credit_application.php" class="action-btn">
                        <i class="fas fa-hand-holding-dollar action-icon text-warning"></i>
                        <span class="fw-bold d-block">Kredi Başvurusu</span>
                        <small class="text-muted">Anında Onay</small>
                    </a>
                </div>

                <div class="col-6 col-md-3 mb-3">
                    <a href="#" class="action-btn">
                        <i class="fas fa-chart-line action-icon text-info"></i>
                        <span class="fw-bold d-block">Döviz / Altın</span>
                        <small class="text-muted">Yatırım Yap</small>
                    </a>
                </div>
            </div>


        <?php elseif ($_SESSION['role'] == 'Admin'): ?>
            
            <div class="row justify-content-center">
                <div class="col-12 mb-4">
                    <h3 class="fw-bold text-danger"><i class="fas fa-user-shield me-2"></i>Yönetici Paneli</h3>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card glass-card h-100 text-center p-4 border-0">
                        <div class="card-body">
                            <div class="mb-3 p-3 rounded-circle bg-danger bg-opacity-10 d-inline-block">
                                <i class="fa fa-users-cog fa-2x text-danger"></i>
                            </div>
                            <h5 class="card-title fw-bold">Kullanıcı Yönetimi</h5>
                            <p class="card-text text-muted small">Müşteri ve Personel hesaplarını düzenle.</p>
                            <a href="admin/all_users.php" class="btn btn-outline-danger w-100 mt-2">Listele</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card glass-card h-100 text-center p-4 border-0">
                        <div class="card-body">
                            <div class="mb-3 p-3 rounded-circle bg-secondary bg-opacity-10 d-inline-block">
                                <i class="fa fa-chart-pie fa-2x text-secondary"></i>
                            </div>
                            <h5 class="card-title fw-bold">Raporlar</h5>
                            <p class="card-text text-muted small">Finansal analiz ve varlık raporları.</p>
                            <a href="admin/reports.php" class="btn btn-outline-secondary w-100 mt-2">Görüntüle</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card glass-card h-100 text-center p-4 border-0">
                        <div class="card-body">
                            <div class="mb-3 p-3 rounded-circle bg-success bg-opacity-10 d-inline-block">
                                <i class="fa fa-wallet fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title fw-bold">Tüm Hesaplar</h5>
                            <p class="card-text text-muted small">Banka genelindeki tüm hesap listesi.</p>
                            <a href="admin/list_accounts.php" class="btn btn-outline-success w-100 mt-2">Hesaplar</a>
                        </div>
                    </div>
                </div>
            </div>


        <?php elseif ($_SESSION['role'] == 'Personel'): ?>
            
            <div class="row justify-content-center">
                <div class="col-12 mb-4">
                    <h3 class="fw-bold text-primary"><i class="fas fa-id-badge me-2"></i>Personel Paneli</h3>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card glass-card h-100 text-center p-4 border-0">
                        <div class="card-body">
                            <div class="mb-3 p-3 rounded-circle bg-success bg-opacity-10 d-inline-block">
                                <i class="fa fa-user-plus fa-2x text-success"></i>
                            </div>
                            <h5 class="card-title fw-bold">Müşteri İşlemleri</h5>
                            <p class="card-text text-muted small">Yeni müşteri ekle veya güncelle.</p>
                            <a href="personnel/list_customers.php" class="btn btn-primary-custom w-100 mt-2 text-white">Müşteri Yönetimi</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card glass-card h-100 text-center p-4 border-0">
                        <div class="card-body">
                            <div class="mb-3 p-3 rounded-circle bg-primary bg-opacity-10 d-inline-block">
                                <i class="fa fa-credit-card fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title fw-bold">Hesap Açılışı</h5>
                            <p class="card-text text-muted small">Mevcut müşteriye yeni hesap tanımla.</p>
                            <a href="personnel/create_account.php" class="btn btn-primary-custom w-100 mt-2 text-white">Hesap Aç</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <div class="card glass-card h-100 text-center p-4 border-0">
                        <div class="card-body">
                            <div class="mb-3 p-3 rounded-circle bg-warning bg-opacity-10 d-inline-block">
                                <i class="fa fa-file-signature fa-2x text-warning"></i>
                            </div>
                            <h5 class="card-title fw-bold">Kredi Talepleri</h5>
                            <p class="card-text text-muted small">Onay bekleyen kredi başvuruları.</p>
                            <a href="personnel/loan_requests.php" class="btn btn-primary-custom w-100 mt-2 text-white">Talepler</a>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>