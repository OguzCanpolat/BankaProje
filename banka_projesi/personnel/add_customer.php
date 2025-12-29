<?php
session_start();
require_once '../includes/db.php';

// Sadece Personel Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Personel') {
    header("Location: ../auth/login.php");
    exit;
}

// Şubeleri Çek (Dropdown için lazım)
$subeler = $pdo->query("SELECT * FROM Branches")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Müşteri Ekle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4><i class="fa fa-user-plus"></i> Yeni Müşteri Kaydı</h4>
                    </div>
                    <div class="card-body">

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                        <?php endif; ?>
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">Müşteri başarıyla sisteme eklendi!</div>
                        <?php endif; ?>

                        <form action="add_customer_action.php" method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Ad</label>
                                    <input type="text" name="firstname" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Soyad</label>
                                    <input type="text" name="lastname" class="form-control" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label>TC Kimlik No</label>
                                <input type="text" name="tckn" class="form-control" maxlength="11" required>
                            </div>

                            <div class="mb-3">
                                <label>Telefon</label>
                                <input type="text" name="phone" class="form-control" placeholder="0555...">
                            </div>

                            <div class="mb-3">
                                <label>Bağlı Olduğu Şube</label>
                                <select name="branch_id" class="form-select" required>
                                    <?php foreach ($subeler as $sube): ?>
                                        <option value="<?= $sube['BranchID'] ?>">
                                            <?= $sube['BranchName'] ?> - <?= $sube['City'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr>
                            <h5 class="text-muted">Giriş Bilgileri</h5>

                            <div class="mb-3">
                                <label>E-Posta Adresi</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label>Geçici Şifre</label>
                                <input type="text" name="password" class="form-control" value="1234" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success">Müşteriyi Kaydet</button>
                                <a href="list_customers.php" class="btn btn-secondary">İptal / Listeye Dön</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>