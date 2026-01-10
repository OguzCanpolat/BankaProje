<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Admin girişi kontrolü (Kendi sistemine göre açarsın)
// if (!isset($_SESSION['admin_logged_in'])) { die("Yetkisiz erişim."); }

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']); // SQL Injection'a karşı intval

    // Active = 0 yaparak hesabı donduruyoruz
    $sql = "UPDATE users SET is_active = 0 WHERE id = :id";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute(['id' => $user_id])) {
        // İşlem başarılıysa geri dön
        echo "<script>
            alert('Kullanıcı başarıyla bloke edildi! 🚫');
            window.location.href = 'fraud_monitor.php';
        </script>";
    } else {
        echo "Hata oluştu.";
    }
} else {
    header("Location: fraud_monitor.php");
}
?>