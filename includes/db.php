<?php
// includes/db.php

$host = 'localhost';
$dbname = 'banka_db';
$username = 'root'; // XAMPP varsayılan kullanıcısı
$password = '';     // XAMPP varsayılan şifresi (boştur)

try {
    // PDO ile güvenli bağlantı oluşturuyoruz
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Hata modunu aktif et (Hata olursa ekranda görelim)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Varsayılan fetch modunu ayarla (Verileri dizi olarak çek)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Bağlantı başarılıysa bu değişkeni diğer sayfalarda kullanacağız.
} catch (PDOException $e) {
    // Hata olursa çalışmayı durdur ve hatayı göster
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>