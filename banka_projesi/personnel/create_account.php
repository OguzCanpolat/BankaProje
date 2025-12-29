<?php
session_start();
require_once '../includes/db.php';

// Sadece Personel Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Personel') {
    header("Location: ../auth/login.php");
    exit;
}

// Müşterileri Listele (Dropdown için)
$musteriler = $pdo->query("SELECT CustomerID, FirstName, LastName, TCKN FROM Customers")->fetchAll();

// Hesap Türlerini Listele
$turler = $pdo->query("SELECT * FROM AccountTypes")->fetchAll();

// --- YENİ EKLENEN KISIM: Şubeleri Listele ---
$subeler = $pdo->query("SELECT * FROM branches")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Hesap Aç</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4>Yeni Hesap Açılışı</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">
                                Hesap başarıyla açıldı! <br> 
                                Yeni Hesap No: <strong><?= htmlspecialchars($_GET['account_no']) ?></strong>
                            </div>
                        <?php endif; ?>

                        <form action="create_account_action.php" method="POST">
                            
                            <div class="mb-3">
                                <label class="form-label">Müşteri Seçin</label>
                                <select name="customer_id" class="form-select" required>
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($musteriler as $m): ?>
                                        <option value="<?= $m['CustomerID'] ?>">
                                            <?= $m['FirstName'] ?> <?= $m['LastName'] ?> (TC: <?= $m['TCKN'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Hesap Türü</label>
                                <select name="type_id" class="form-select" required>
                                    <?php foreach ($turler as $t): ?>
                                        <option value="<?= $t['TypeID'] ?>">
                                            <?= $t['TypeName'] ?> (<?= $t['CurrencyCode'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Hesabın Açılacağı Şube</label>
                                <select name="branch_id" class="form-select" required>
                                    <option value="">Şube Seçiniz...</option>
                                    <?php foreach ($subeler as $sube): ?>
                                        <option value="<?= $sube['BranchID'] ?>">
                                            <?= $sube['BranchName'] ?> (<?= $sube['City'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Başlangıç Bakiyesi</label>
                                <input type="number" name="balance" class="form-control" value="0.00" min="0" step="0.01">
                            </div>

                            <button type="submit" class="btn btn-success w-100">Hesabı Oluştur</button>
                            <a href="../index.php" class="btn btn-secondary w-100 mt-2">Geri Dön</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>