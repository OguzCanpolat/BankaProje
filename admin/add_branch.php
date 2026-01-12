<?php
session_start();
require_once '../includes/db.php';

// Sadece Admin Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../index.php");
    exit;
}

$message = "";

// Form Gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $city = $_POST['city'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];

    if (!empty($name) && !empty($city)) {
        // Veritabanına Ekle
        $stmt = $pdo->prepare("INSERT INTO branches (BranchName, City, Address, Phone) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $city, $address, $phone])) {
            // Başarılıysa şube listesine yönlendir
            header("Location: branches.php");
            exit;
        } else {
            $message = "<div class='alert alert-danger'>Hata oluştu, eklenemedi.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Lütfen şube adı ve şehri giriniz.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Şube Ekle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏢 Yeni Şube Açılışı</h5>
            </div>
            <div class="card-body">
                <?php echo $message; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label>Şube Adı:</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: Taksim Şubesi" required>
                    </div>
                    <div class="mb-3">
                        <label>Şehir:</label>
                        <input type="text" name="city" class="form-control" placeholder="Örn: İstanbul" required>
                    </div>
                    <div class="mb-3">
                        <label>Adres:</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Açık adres..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Telefon:</label>
                        <input type="text" name="phone" class="form-control" placeholder="0212 000 00 00">
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">✅ Şubeyi Aç</button>
                    <a href="branches.php" class="btn btn-secondary w-100 mt-2">İptal</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>