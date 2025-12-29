<?php
session_start();
require_once '../includes/db.php'; // Veritabanı bağlantısı

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    try {
        // SQL'de yazdığımız 'sp_UserLogin' prosedürünü çağırıyoruz.
        $stmt = $pdo->prepare("CALL sp_UserLogin(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Stored Procedure kullandığımız için işimiz bitince imleci kapatmamız lazım.
        // Yoksa hemen ardından INSERT sorgusu çalıştırınca "Unbuffered queries" hatası verebilir.
        $stmt->closeCursor(); 

        // 1. Kullanıcı var mı? 
        // 2. Şifre doğru mu?
        if ($user && $user['Password'] === $password) {
            
            // Giriş Başarılı! Bilgileri Session'a kaydet
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['role'] = $user['RoleName']; // Admin, Personel, Musteri
            $_SESSION['email'] = $user['Email'];
            
            // İsim bilgisini role göre alalım
            if ($user['RoleName'] == 'Musteri') {
                $_SESSION['fullname'] = $user['CustName'] . " " . $user['CustSurname'];
            } elseif ($user['RoleName'] == 'Personel') {
                $_SESSION['fullname'] = $user['EmpName'] . " " . $user['EmpSurname'];
            } else {
                $_SESSION['fullname'] = "Yönetici";
            }

            // --- 🔴 LOG KAYDI BAŞLANGICI ---
            // Sisteme kimin girdiğini 'auditlogs' tablosuna kaydediyoruz.
            try {
                $ip_address = $_SERVER['REMOTE_ADDR']; // Kullanıcının IP adresi
                $action = "Sisteme Giriş Yapıldı";     // Yapılan işlem

                $logSql = "INSERT INTO auditlogs (UserID, Action, IPAddress) VALUES (?, ?, ?)";
                $logStmt = $pdo->prepare($logSql);
                $logStmt->execute([$user['UserID'], $action, $ip_address]);
                
            } catch (Exception $e) {
                // Log tutarken hata olursa sistemi durdurma, devam et.
            }
            // --- 🔴 LOG KAYDI BİTİŞİ ---

            // Ana sayfaya yönlendir
            header("Location: ../index.php");
            exit;

        } else {
            // Hatalı giriş
            header("Location: login.php?error=Hatalı e-posta veya şifre!");
            exit;
        }

    } catch (PDOException $e) {
        die("Hata: " . $e->getMessage());
    }
} else {
    // İzinsiz giriş denemesi
    header("Location: login.php");
    exit;
}
?>