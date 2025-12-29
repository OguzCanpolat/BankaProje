<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

$sql = "SELECT a.AccountID, a.AccountNumber, a.Balance, a.Currency, a.CreatedAt, 
               c.FirstName, c.LastName, c.TCKN 
        FROM Accounts a
        JOIN Customers c ON a.CustomerID = c.CustomerID
        ORDER BY a.CreatedAt DESC";

$accounts = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Tüm Hesaplar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-danger"><i class="fa fa-wallet"></i> Bankadaki Tüm Hesaplar</h3>
            <a href="../index.php" class="btn btn-secondary">Ana Sayfa</a>
        </div>

        <div class="card shadow">
            <div class="card-body p-0">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Hesap No</th>
                            <th>Müşteri</th>
                            <th>TCKN</th>
                            <th>Bakiye</th>
                            <th>Açılış Tarihi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td class="font-monospace fw-bold"><?= $acc['AccountNumber'] ?></td>
                                <td><?= $acc['FirstName'] ?> <?= $acc['LastName'] ?></td>
                                <td><?= $acc['TCKN'] ?></td>
                                <td class="fw-bold fs-5 text-success">
                                    <?= number_format($acc['Balance'], 2) ?> 
                                    <span class="badge bg-secondary text-white" style="font-size: 0.7em;">
                                        <?= $acc['Currency'] ?? 'TL' ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= date("d.m.Y", strtotime($acc['CreatedAt'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($accounts)): ?>
                    <div class="p-4 text-center text-muted">Kayıtlı hesap bulunamadı.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>