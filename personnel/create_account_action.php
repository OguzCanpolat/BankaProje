<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['role'] == 'Personel') {
    
    // Formdan gelen verileri alıyoruz
    $customerID = $_POST['customer_id'];
    $typeID     = $_POST['type_id'];
    $balance    = $_POST['balance'];
    
    // --- YENİ: Formdan gelen Şube ID'sini alıyoruz ---
    $branchID   = $_POST['branch_id']; 

    try {
        // --- GÜNCELLEME: Prosedür artık 4 parametre alıyor (Müşteri, Tip, Bakiye, Şube) ---
        // Soru işareti sayısını 4 yaptık: (?, ?, ?, ?)
        $stmt = $pdo->prepare("CALL sp_CreateAccount(?, ?, ?, ?)");
        
        // Şube ID'sini de (BranchID) gönderme sırasına ekledik
        $stmt->execute([$customerID, $typeID, $balance, $branchID]);
        
        // Yeni oluşan hesap numarasını (IBAN) al
        $result = $stmt->fetch();
        $newAccountNo = $result['NewAccountNumber'];

        header("Location: create_account.php?success=1&account_no=" . $newAccountNo);
        exit;

    } catch (PDOException $e) {
        die("Hata oluştu: " . $e->getMessage());
    }

} else {
    header("Location: ../index.php");
}
?>