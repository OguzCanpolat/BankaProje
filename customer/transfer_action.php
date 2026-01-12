<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Form verilerini al
    $senderID = $_POST['sender_account_id'];
    $targetNo = $_POST['target_account_no'];
    $amount = $_POST['amount'];
    $desc = $_POST['description'];

    // Basit kontroller
    if ($amount <= 0) {
        header("Location: transfer.php?error=Geçersiz tutar!");
        exit;
    }

    try {
        // SQL Prosedürünü Çağır
        $stmt = $pdo->prepare("CALL sp_MakeTransfer(?, ?, ?, ?)");
        $stmt->execute([$senderID, $targetNo, $amount, $desc]);

        // İşlem başarılıysa
        header("Location: transfer.php?success=1");
        exit;

    } catch (PDOException $e) {
        // SQL'den dönen hatayı yakala (Yetersiz bakiye vs.)
        $errorMsg = $e->getMessage();
        
        // Kullanıcıya temiz hata göstermek için SQL kodlarını temizle
        if (strpos($errorMsg, 'Yetersiz bakiye') !== false) {
            $msg = "Hesabınızda yeterli bakiye yok.";
        } elseif (strpos($errorMsg, 'Alıcı hesap bulunamadı') !== false) {
            $msg = "Girilen alıcı hesap numarası sistemde kayıtlı değil.";
        } else {
            $msg = "Bir hata oluştu: İşlem tamamlanamadı.";
        }
        
        header("Location: transfer.php?error=" . urlencode($msg));
        exit;
    }

} else {
    header("Location: transfer.php");
    exit;
}