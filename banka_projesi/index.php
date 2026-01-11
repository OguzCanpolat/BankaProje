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
if ($_SESSION['role'] == 'Musteri') {
    try {
        $stmt = $pdo->prepare("CALL sp_GetCustomerAccounts(?)");
        $stmt->execute([$_SESSION['user_id']]);
        $hesaplar = $stmt->fetchAll();
        $stmt->closeCursor();
    } catch (PDOException $e) {
        $hata = "Hesaplar yüklenirken hata: " . $e->getMessage();
    }
}
// ---------------------------------------------------------
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Ana Sayfa | Banka Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fc; }
        
        /* Kart Efektleri */
        .card-hover { transition: transform 0.3s, box-shadow 0.3s; border: none; border-radius: 15px; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.15) !important; }
        
        /* Admin Kart Renkleri (Gradient) */
        .bg-gradient-risk { background: linear-gradient(45deg, #eb3349, #f45c43); color: white; }
        .bg-gradient-report { background: linear-gradient(45deg, #1d976c, #93f9b9); color: white; }
        .bg-gradient-users { background: linear-gradient(45deg, #4facfe, #00f2fe); color: white; }
        .bg-gradient-accounts { background: linear-gradient(45deg, #43e97b, #38f9d7); color: white; } /* Daha canlı yeşil/turkuaz */
        .bg-gradient-branch { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .bg-gradient-audit { background: linear-gradient(45deg, #434343, #000000); color: white; }

        /* Kart İçi İkonlar */
        .card-icon-bg {
            position: absolute;
            right: 10px;
            bottom: 10px;
            font-size: 5rem;
            opacity: 0.2;
            transform: rotate(-15deg);
        }
        .admin-link { text-decoration: none; color: inherit; display: block; height: 100%; }
        .admin-link:hover { color: inherit; }
    </style>
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 shadow">
        <a class="navbar-brand fw-bold" href="#"><i class="fas fa-university me-2"></i> Banka Sistemi</a>
        
        <div class="ms-auto d-flex text-white align-items-center">
            
            <?php if ($_SESSION['role'] == 'Musteri'): ?>
                <a href="customer/atm.php" class="btn btn-warning btn-sm me-3 fw-bold text-dark">
                    <i class="fa fa-money-bill-transfer"></i> ATM
                </a>
            <?php endif; ?>

            <span class="me-3"><i class="fa fa-user-circle"></i> <?= $_SESSION['fullname'] ?> <small class="text-secondary">(<?= $_SESSION['role'] ?>)</small></span>
            <a href="auth/logout.php" class="btn btn-danger btn-sm rounded-pill px-3">Çıkış</a>
        </div>
    </nav>

    <div class="container mt-4">
        
        <?php if ($_SESSION['role'] == 'Musteri'): ?>
            <div class="row">
                <div class="col-md-12">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="mb-0">Hesaplarım</h3>
                        <div>
                            <a href="customer/credit_application.php" class="btn btn-success me-2 fw-bold">
                                <i class="fa fa-hand-holding-dollar"></i> Kredi Başvurusu
                            </a>
                            <a href="customer/atm.php" class="btn btn-outline-warning text-dark fw-bold">
                                <i class="fa fa-university"></i> ATM / Para Yatır-Çek
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($hesaplar)): ?>
                        <div class="alert alert-warning">Henüz açık bir hesabınız bulunmuyor.</div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($hesaplar as $hesap): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card shadow-sm border-start border-5 border-primary h-100 card-hover bg-white">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <h6 class="text-muted fw-bold"><?= $hesap['TypeName'] ?></h6>
                                                    <h4><?= number_format($hesap['Balance'], 2) ?> <?= $hesap['CurrencyCode'] ?></h4>
                                                    <small class="text-secondary font-monospace"><?= $hesap['AccountNumber'] ?></small>
                                                </div>
                                                <i class="fa fa-wallet fa-2x text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-white d-flex gap-2 p-2">
                                            <a href="customer/transactions.php?id=<?= $hesap['AccountID'] ?>" class="btn btn-sm btn-info text-white w-50">
                                                <i class="fa fa-list"></i> Hareketler
                                            </a>
                                            <a href="customer/transfer.php" class="btn btn-sm btn-outline-primary w-50">
                                                Transfer
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($_SESSION['role'] == 'Admin'): ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="text-dark fw-bold"><i class="fas fa-chart-pie me-2"></i> Yönetim Paneli</h3>
                <span class="badge bg-secondary"><?php echo date("d.m.Y"); ?></span>
            </div>

            <div class="row g-4">
                
                <div class="col-md-4">
                    <a href="admin/fraud_monitor.php" class="admin-link">
                        <div class="card card-hover bg-gradient-risk h-100 overflow-hidden position-relative">
                            <div class="card-body p-4">
                                <h4 class="fw-bold"><i class="fas fa-shield-alt"></i> Güvenlik & Risk</h4>
                                <p class="opacity-75 mt-2">Şüpheli işlemleri ve limit aşımlarını anlık izle.</p>
                                <i class="fas fa-user-secret card-icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="admin/reports.php" class="admin-link">
                        <div class="card card-hover bg-gradient-report h-100 overflow-hidden position-relative">
                            <div class="card-body p-4">
                                <h4 class="fw-bold"><i class="fas fa-chart-line"></i> Finansal Raporlar</h4>
                                <p class="opacity-75 mt-2">Banka varlıkları, döviz durumları ve müşteri analizleri.</p>
                                <i class="fas fa-poll card-icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="admin/all_users.php" class="admin-link">
                        <div class="card card-hover bg-gradient-users h-100 overflow-hidden position-relative">
                            <div class="card-body p-4">
                                <h4 class="fw-bold"><i class="fas fa-users"></i> Kullanıcı Yönetimi</h4>
                                <p class="opacity-75 mt-2">Müşteri ve personelleri listele, düzenle veya yeni ekle.</p>
                                <i class="fas fa-users-cog card-icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="admin/list_accounts.php" class="admin-link">
                        <div class="card card-hover bg-gradient-accounts text-dark h-100 overflow-hidden position-relative" style="color: #333 !important;">
                            <div class="card-body p-4">
                                <h4 class="fw-bold"><i class="fas fa-wallet"></i> Tüm Hesaplar</h4>
                                <p class="opacity-75 mt-2">Bankadaki tüm aktif ve pasif hesapları görüntüle.</p>
                                <i class="fas fa-money-check-alt card-icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="admin/branches.php" class="admin-link">
                        <div class="card card-hover bg-gradient-branch h-100 overflow-hidden position-relative">
                            <div class="card-body p-4">
                                <h4 class="fw-bold"><i class="fas fa-building"></i> Şubeler</h4>
                                <p class="opacity-75 mt-2">Şube ağını yönet, yeni lokasyon ekle.</p>
                                <i class="fas fa-map-marked-alt card-icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="admin/audit_logs.php" class="admin-link">
                        <div class="card card-hover bg-gradient-audit h-100 overflow-hidden position-relative">
                            <div class="card-body p-4">
                                <h4 class="fw-bold"><i class="fas fa-history"></i> Denetim Kayıtları</h4>
                                <p class="opacity-75 mt-2">Sistemdeki tüm hareketlerin güvenlik logları.</p>
                                <i class="fas fa-file-contract card-icon-bg"></i>
                            </div>
                        </div>
                    </a>
                </div>

            </div>

        <?php elseif ($_SESSION['role'] == 'Personel'): ?>
            
            <h3 class="mb-4 text-primary"><i class="fas fa-id-badge"></i> Personel İşlem Merkezi</h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center shadow-sm h-100 card-hover">
                        <div class="card-body">
                            <i class="fa fa-user-plus fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Müşteri İşlemleri</h5>
                            <p class="card-text text-muted">Sisteme yeni müşteri ekle veya bilgilerini güncelle.</p>
                            <a href="personnel/list_customers.php" class="btn btn-success w-100">Müşteri Listesi / Ekle</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card text-center shadow-sm h-100 card-hover">
                        <div class="card-body">
                            <i class="fa fa-credit-card fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Hesap Açılışı</h5>
                            <p class="card-text text-muted">Mevcut müşterilere yeni vadesiz veya döviz hesabı aç.</p>
                            <a href="personnel/create_account.php" class="btn btn-primary w-100">Hesap Aç</a>
                        </div>
                    </div>
                </div>

                 </div>

        <?php endif; ?>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>