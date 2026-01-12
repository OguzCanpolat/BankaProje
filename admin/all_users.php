<?php
session_start();
require_once '../includes/db.php';

// Sadece Admin Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

// 1. ADIM: SQL Sorgusu (GÜNCELLENDİ)
// AccountTypes tablosunu (t) bağladık ve CurrencyCode'u çektik.
$sql = "SELECT 
            u.UserID, 
            u.Email, 
            u.Password, 
            r.RoleName,
            c.FirstName, c.LastName,
            e.FirstName as EmpName, e.LastName as EmpSurname,
            a.AccountNumber,
            a.Balance,
            t.CurrencyCode -- Burayı düzelttik
        FROM Users u
        LEFT JOIN Roles r ON u.RoleID = r.RoleID
        LEFT JOIN Customers c ON u.UserID = c.UserID
        LEFT JOIN Employees e ON u.UserID = e.UserID
        LEFT JOIN Accounts a ON c.CustomerID = a.CustomerID
        LEFT JOIN AccountTypes t ON a.TypeID = t.TypeID -- BU SATIRI EKLEDİK
        ORDER BY u.UserID ASC";

$raw_data = $pdo->query($sql)->fetchAll();

// 2. ADIM: PHP ile Veriyi Düzenleme
$users = [];
foreach ($raw_data as $row) {
    $id = $row['UserID'];
    
    // Kullanıcı ana kaydı
    if (!isset($users[$id])) {
        // İsim Belirleme
        if ($row['RoleName'] == 'Musteri') {
            $fullName = $row['FirstName'] . ' ' . $row['LastName'];
        } elseif ($row['RoleName'] == 'Personel') {
            $fullName = $row['EmpName'] . ' ' . $row['EmpSurname'];
        } else {
            $fullName = 'Sistem Yöneticisi';
        }

        $users[$id] = [
            'Role' => $row['RoleName'],
            'FullName' => $fullName,
            'Email' => $row['Email'],
            'Password' => $row['Password'],
            'Accounts' => [] 
        ];
    }

    // Hesap Ekleme
    if (!empty($row['AccountNumber'])) {
        $users[$id]['Accounts'][] = [
            'No' => $row['AccountNumber'],
            'Balance' => $row['Balance'],
            'Currency' => $row['CurrencyCode'] // Burayı da CurrencyCode olarak güncelledik
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Tüm Kullanıcılar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa fa-user-shield text-danger"></i> Kullanıcı Yönetim Paneli</h2>
            
            <div>
                <a href="branches.php" class="btn btn-primary me-2">
                    <i class="fa fa-building"></i> Şubeler
                </a>

                <a href="audit_logs.php" class="btn btn-danger me-2">
                        <i class="fa fa-history"></i> Log Kayıtları
                </a>
                
                <a href="../index.php" class="btn btn-secondary">
                    <i class="fa fa-home"></i> Ana Sayfa
                </a>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Sistemdeki Kayıtlı Tüm Kullanıcılar</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Rol</th>
                            <th>Ad Soyad</th>
                            <th>E-Posta</th>
                            <th class="text-danger">Şifre</th>
                            <th class="bg-warning" style="width: 400px;">Hesaplar (IBAN)</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $userId => $u): ?>
                            <tr>
                                <td><?= $userId ?></td>
                                <td>
                                    <?php if($u['Role'] == 'Admin'): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php elseif($u['Role'] == 'Personel'): ?>
                                        <span class="badge bg-primary">Personel</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Müşteri</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= $u['FullName'] ?></td>
                                <td><?= $u['Email'] ?></td>
                                <td class="text-danger fw-bold font-monospace"><?= $u['Password'] ?></td>
                                
                                <td class="p-2">
                                    <?php if (!empty($u['Accounts'])): ?>
                                        <?php foreach ($u['Accounts'] as $acc): ?>
                                            <div class="d-flex justify-content-between border-bottom py-1">
                                                <span class="fw-bold text-primary font-monospace" style="font-size:0.9rem">
                                                    <?= $acc['No'] ?>
                                                </span>
                                                <span class="badge bg-light text-dark border">
                                                    <?= number_format($acc['Balance'], 2) ?> 
                                                    <?= $acc['Currency'] ?? 'TL' ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php if ($u['Role'] == 'Musteri'): ?>
                                            <span class="badge bg-warning text-dark">Hesap Yok!</span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>