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
    <title>Ana Sayfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-hover:hover { transform: translateY(-5px); transition: 0.3s; box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    </style>
</head>
<body class="bg-light">
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 shadow">
        <a class="navbar-brand" href="#">🏦 Banka Sistemi</a>
        
        <div class="ms-auto d-flex text-white align-items-center">
            
            <?php if ($_SESSION['role'] == 'Musteri'): ?>
                <a href="customer/atm.php" class="btn btn-warning btn-sm me-3 fw-bold text-dark">
                    <i class="fa fa-money-bill-transfer"></i> ATM
                </a>
            <?php endif; ?>

            <span class="me-3"><i class="fa fa-user-circle"></i> <?= $_SESSION['fullname'] ?> (<?= $_SESSION['role'] ?>)</span>
            <a href="auth/logout.php" class="btn btn-danger btn-sm">Çıkış</a>
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
                                    <div class="card shadow-sm border-start border-5 border-primary h-100">
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
            
            <h3 class="mb-4 text-danger"><i class="fa fa-user-shield"></i> Yönetici Paneli</h3>
            <div class="row">
                
                <div class="col-md-4">
                    <div class="card text-center shadow card-hover h-100 border-danger">
                        <div class="card-body">
                            <i class="fa fa-users-cog fa-3x text-danger mb-3"></i>
                            <h5 class="card-title">Kullanıcı & Güvenlik</h5>
                            <p class="card-text text-muted small">Kullanıcıları yönet ve şüpheli işlemleri izle.</p>
                            
                            <div class="d-grid gap-2">
                                <a href="admin/all_users.php" class="btn btn-outline-danger">
                                    <i class="fa fa-list"></i> Kullanıcıları Listele
                                </a>
                                
                                <a href="admin/fraud_monitor.php" class="btn btn-dark">
                                    <i class="fa fa-user-secret"></i> Fraud (Risk) İzle
                                </a>

                                <a href="admin/audit_logs.php" class="btn btn-secondary btn-sm">
                                    <i class="fa fa-history"></i> Güvenlik Logları
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card text-center shadow card-hover h-100">
                        <div class="card-body">
                            <i class="fa fa-chart-line fa-3x text-secondary mb-3"></i>
                            <h5 class="card-title">Raporlar</h5>
                            <p class="card-text text-muted small">En çok işlem yapan müşteriler ve şube varlık raporları.</p>
                            <a href="admin/reports.php" class="btn btn-secondary w-100 mt-3">Raporları Görüntüle</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                     <div class="card text-center shadow card-hover h-100">
                        <div class="card-body">
                            <i class="fa fa-wallet fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Tüm Hesaplar</h5>
                            <p class="card-text text-muted small">Bankadaki tüm hesapları ve bakiyeleri gör.</p>
                            <a href="admin/list_accounts.php" class="btn btn-success w-100 mt-3">Hesap Listesi</a>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($_SESSION['role'] == 'Personel'): ?>
            
            <h3 class="mb-4 text-primary">Personel İşlem Merkezi</h3>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center shadow-sm h-100">
                        <div class="card-body">
                            <i class="fa fa-user-plus fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Müşteri İşlemleri</h5>
                            <p class="card-text">Sisteme yeni müşteri ekle veya bilgilerini güncelle.</p>
                            <a href="personnel/list_customers.php" class="btn btn-success">Müşteri Listesi / Ekle</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card text-center shadow-sm h-100">
                        <div class="card-body">
                            <i class="fa fa-credit-card fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Hesap Açılışı</h5>
                            <p class="card-text">Mevcut müşterilere yeni vadesiz veya döviz hesabı aç.</p>
                            <a href="personnel/create_account.php" class="btn btn-primary">Hesap Aç</a>
                        </div>
                    </div>
                </div>

                <div class="col-