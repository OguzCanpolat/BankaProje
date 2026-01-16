<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Admin değilse at
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

// DÜZELTME: 'CreatedAt' sütunu hata verdiği için sorgudan çıkardık.
// Sadece var olduğundan emin olduğumuz sütunları çekiyoruz.
$sql = "SELECT a.AccountID, a.AccountNumber, a.Balance, a.Currency, 
               c.FirstName, c.LastName, c.TCKN 
        FROM Accounts a
        JOIN Customers c ON a.CustomerID = c.CustomerID
        ORDER BY a.AccountID DESC"; // ID'ye göre sırala

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Hata olursa ekrana daha şık basalım
    $error = "Veri hatası: " . $e->getMessage();
    $accounts = []; // Hata durumunda boş dizi ata ki alttaki döngü patlamasın
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Tüm Hesaplar | Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fc; }
        
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 25px;
            border: none;
        }
        
        .table-custom thead th {
            background-color: #2c3e50;
            color: white;
            border: none;
            padding: 15px;
            font-weight: 500;
        }
        
        .table-custom tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.2s;
        }
        
        .table-custom tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            z-index: 1;
            position: relative;
        }
        
        .table-custom td {
            padding: 15px;
            vertical-align: middle;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            margin-right: 12px;
            box-shadow: 0 3px 10px rgba(118, 75, 162, 0.3);
        }

        .badge-currency { font-size: 0.8rem; padding: 6px 12px; border-radius: 8px; font-weight: 600; }
        .bg-tl { background-color: #e3f2fd; color: #0d47a1; }
        .bg-usd { background-color: #e8f5e9; color: #1b5e20; }
        .bg-eur { background-color: #fff3e0; color: #e65100; }
        .bg-gold { background-color: #fffde7; color: #fbc02d; }

        .page-header {
            background: linear-gradient(45deg, #11998e, #38ef7d);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(56, 239, 125, 0.3);
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark px-4 py-3 mb-4 shadow">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-arrow-left me-2"></i> Yönetim Paneline Dön
            </a>
            <span class="text-white-50 small"><i class="fas fa-user-shield me-1"></i> Admin Modu</span>
        </div>
    </nav>

    <div class="container">
        
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-0"><i class="fas fa-wallet me-2"></i> Tüm Hesaplar</h2>
                <p class="mb-0 opacity-90">Banka genelindeki müşteri hesapları ve bakiye durumları.</p>
            </div>
            <div class="display-6">
                <i class="fas fa-university opacity-50"></i>
            </div>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger shadow-sm"><?= $error ?></div>
        <?php endif; ?>

        <div class="table-card">
            <div class="d-flex justify-content-between mb-3 align-items-center">
                <h5 class="text-secondary fw-bold mb-0">Hesap Listesi</h5>
                <span class="badge bg-secondary rounded-pill"><?= count($accounts) ?> Kayıt</span>
            </div>

            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Müşteri</th>
                            <th>TCKN</th>
                            <th>Hesap Numarası</th>
                            <th>Bakiye</th>
                            <th class="text-end pe-4">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">Hiç hesap bulunamadı.</td></tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $acc): ?>
                                <?php 
                                    $curr = isset($acc['Currency']) ? strtoupper($acc['Currency']) : 'TL';
                                    $badgeClass = 'bg-tl';
                                    if($curr == 'USD') $badgeClass = 'bg-usd';
                                    if($curr == 'EUR') $badgeClass = 'bg-eur';
                                    if($curr == 'XAU' || $curr == 'ALTIN') $badgeClass = 'bg-gold';
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar">
                                                <?= strtoupper(substr($acc['FirstName'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= $acc['FirstName'] . ' ' . $acc['LastName'] ?></div>
                                                <small class="text-muted">ID: #<?= $acc['AccountID'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <i class="fas fa-id-card me-1 text-secondary"></i> <?= $acc['TCKN'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-primary fw-bold bg-light px-2 py-1 rounded">
                                            <?= $acc['AccountNumber'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="fw-bold text-dark fs-5 me-2">
                                                <?= number_format($acc['Balance'], 2, ',', '.') ?>
                                            </span>
                                            <span class="badge badge-currency <?= $badgeClass ?>">
                                                <?= $curr ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill" title="Detaylar">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="text-center mt-4 text-muted small">
            &copy; 2026 NeoBank Yönetim Paneli
        </div>
    </div>

</body>
</html>