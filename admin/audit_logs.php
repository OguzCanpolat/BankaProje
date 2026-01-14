<?php
session_start();
require_once '../includes/db.php';

// Sadece Admin Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

// Logları Çek (Kullanıcı isimleriyle birleştirerek) BÜTÜN CSS DEGİSTİ. Yeni eklemeler yapılmadı
// LogID'ye göre AZALAN (DESC) sıralıyoruz ki en son yapılan işlem en üstte görünsün xxxxxxx.
$sql = "SELECT 
            al.*, 
            u.Email,
            r.RoleName,
            CASE 
                WHEN r.RoleName = 'Musteri' THEN CONCAT(c.FirstName, ' ', c.LastName)
                WHEN r.RoleName = 'Personel' THEN CONCAT(e.FirstName, ' ', e.LastName)
                ELSE 'Yönetici'
            END as FullName
        FROM auditlogs al
        LEFT JOIN Users u ON al.UserID = u.UserID
        LEFT JOIN Roles r ON u.RoleID = r.RoleID
        LEFT JOIN Customers c ON u.UserID = c.UserID
        LEFT JOIN Employees e ON u.UserID = e.UserID
        ORDER BY al.LogDate DESC";

$logs = $pdo->query($sql)->fetchAll();
?>


<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sistem Güvenlik Logları</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand"><i class="fa fa-shield-alt"></i> Güvenlik Logları</span>
            <div>
                <a href="../index.php" class="btn btn-sm btn-outline-light">Ana Sayfa</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sistem Hareket Kayıtları</h5>
                <span class="badge bg-light text-danger"><?= count($logs) ?> Kayıt</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Tarih / Saat</th>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Yapılan İşlem</th>
                            <th>IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="fa fa-info-circle fa-2x mb-2"></i><br>
                                    Henüz kaydedilmiş bir sistem hareketi yok.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="font-monospace" style="font-size: 0.9rem;">
                                        <?= date("d.m.Y H:i:s", strtotime($log['LogDate'])) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= $log['FullName'] ?></div>
                                        <small class="text-muted"><?= $log['Email'] ?></small>
                                    </td>
                                    <td>
                                        <?php if($log['RoleName'] == 'Admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php elseif($log['RoleName'] == 'Personel'): ?>
                                            <span class="badge bg-primary">Personel</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Müşteri</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <i class="fa fa-caret-right text-danger"></i> <?= $log['Action'] ?>
                                    </td>
                                    <td class="font-monospace text-secondary">
                                        <?= $log['IPAddress'] ?? '127.0.0.1' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>