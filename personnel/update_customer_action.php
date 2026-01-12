<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Sadece personel erişebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Personel') {
    header("Location: ../auth/login.php");
    exit;
}

if (isset($_POST['update_customer'])) {
    
    $userId = $_POST['user_id']; // Formdan gelen UserID
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 1. Email Kontrolü (@banka.com)
    if (strpos($email, '@banka.com') === false) {
        header("Location: list_customers.php?error=email_invalid");
        exit;
    }

    try {
        // 2. Güncelleme Sorgusu (PDO Formatı)
        if (!empty($password)) {
            // Şifre doluysa ikisini de güncelle (Password hashlemesi yoksa direkt yazıyoruz)
            // Eğer hash kullanıyorsan: $password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE Users SET Email = :email, Password = :password WHERE UserID = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $email, 'password' => $password, 'id' => $userId]);
        } else {
            // Şifre boşsa sadece emaili güncelle
            $sql = "UPDATE Users SET Email = :email WHERE UserID = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $email, 'id' => $userId]);
        }

        // Başarılı
        header("Location: list_customers.php?success=1");

    } catch (PDOException $e) {
        // Hata
        header("Location: list_customers.php?error=db_error");
    }
}
?>