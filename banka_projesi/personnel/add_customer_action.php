<?php
session_start();
require_once '../includes/db.php';

// Yetki Kontrolü
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['role'] == 'Personel') {

    // Form verilerini al
    $fname = $_POST['firstname'];
    $lname = $_POST['lastname'];
    $tckn = $_POST['tckn'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $branch = $_POST['branch_id'];

    // --- YENİ EKLENEN KISIM: E-POSTA KONTROLÜ ---
    // Eğer e-posta adresi '@banka.com' ile bitmiyorsa işlemi durdur ve hata ver.
    if (!preg_match('/@banka\.com$/', $email)) {
        $errorMsg = "Hata: Güvenlik gereği sadece '@banka.com' uzantılı e-posta adresleri kabul edilmektedir!";
        // Hata mesajı ile forma geri gönder
        header("Location: add_customer.php?error=" . urlencode($errorMsg));
        exit; // Kodun geri kalanını çalıştırma
    }
    // ---------------------------------------------

    try {
        // Prosedürü Çağır
        $stmt = $pdo->prepare("CALL sp_AddCustomer(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$fname, $lname, $tckn, $phone, $email, $pass, $branch]);

        // Başarılıysa listeye geri dönme, formda kal (seri kayıt için)
        header("Location: add_customer.php?success=1");
        exit;

    } catch (PDOException $e) {
        $msg = "Hata: ";
        // SQL Hata kodlarına göre kullanıcıya mesaj verelim
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            if (strpos($e->getMessage(), 'Email') !== false) 
                $msg .= "Bu E-posta adresi zaten kullanılıyor.";
            elseif (strpos($e->getMessage(), 'TCKN') !== false) 
                $msg .= "Bu TC Kimlik No zaten kayıtlı.";
            else 
                $msg .= "Bu kayıt zaten mevcut.";
        } else {
            $msg .= $e->getMessage();
        }
        
        header("Location: add_customer.php?error=" . urlencode($msg));
        exit;
    }

} else {
    header("Location: ../index.php");
}