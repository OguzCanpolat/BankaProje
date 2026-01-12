<?php
require_once 'includes/db.php'; // Veritabanı bağlantısı

echo "<h3>Dolar Hesabı Düzeltme Aracı</h3>";

// 1. Önce Ayşe'nin hesabını bulalım
// Hesap numarasını senin attığın resimden (US987654321) aldım.
$hesap_no = 'US987654321'; 

$stmt = $pdo->prepare("SELECT * FROM Accounts WHERE AccountNumber = ?");
$stmt->execute([$hesap_no]);
$hesap = $stmt->fetch();

if (!$hesap) {
    die("HATA: $hesap_no numaralı hesap veritabanında bulunamadı!");
}

echo "Bulunan Hesap: <b>" . $hesap['AccountNumber'] . "</b><br>";
echo "Şu anki Para Birimi: <b>[" . $hesap['Currency'] . "]</b> (Boşsa TL görünür)<br>";

// 2. ZORLA GÜNCELLEME YAPALIM
echo "<hr>Güncelleme yapılıyor...<br>";

try {
    $update = $pdo->prepare("UPDATE Accounts SET Currency = 'USD' WHERE AccountNumber = ?");
    $update->execute([$hesap_no]);
    
    echo "✅ BAŞARILI! Hesap birimi 'USD' olarak değiştirildi.<br>";
    
    // 3. Tekrar Kontrol Et
    $stmt->execute([$hesap_no]);
    $yeni_hesap = $stmt->fetch();
    echo "Yeni Para Birimi Değeri: <b style='color:green; font-size:20px;'>" . $yeni_hesap['Currency'] . "</b>";
    
} catch (Exception $e) {
    echo "HATA OLUŞTU: " . $e->getMessage();
}
?>