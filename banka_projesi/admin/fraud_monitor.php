<?php
// 1. HATALARI AÇ (Beyaz ekranı engellemek için)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h3>🔍 Hata Ayıklama Modu Başladı</h3>";

// 2. DB BAĞLANTISINI KONTROL ET
$db_path = '../includes/db.php';
if (file_exists($db_path)) {
    echo "✅ db.php dosyası bulundu.<br>";
    require_once $db_path;
} else {
    die("❌ HATA: db.php dosyası bulunamadı! Yol: $db_path");
}

if (!isset($conn)) {
    die("❌ HATA: \$conn değişkeni tanımlı değil. db.php dosyasını kontrol et.");
} else {
    echo "✅ Veritabanı bağlantısı nesnesi (\$conn) mevcut.<br>";
}

// 3. TABLO VE SÜTUN İSİMLERİNİ TEST ET
echo "<hr><h4>Sorgu Testi:</h4>";

try {
    // Basit bir sorgu ile sütunları doğrulayalım
    // DİKKAT: Buradaki tablo isminin senin veritabanında "transactions" olduğundan emin misin?
    // Eğer tablonun adı "islemler" veya "hareketler" ise burayı düzeltmelisin.
    $sql = "SELECT * FROM transactions LIMIT 1"; 
    $stmt = $conn->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo "✅ 'transactions' tablosuna erişildi. Örnek Veri:<br>";
        echo "<pre>" . print_r($row, true) . "</pre>";
        
        echo "<b>Sütun İsimleri Kontrolü:</b><br>";
        $columns = array_keys($row);
        echo "Tablondaki sütunlar: " . implode(", ", $columns) . "<br>";
        
        // Benim kodumda kullandığım sütunlar var mı kontrol et
        $required = ['sender_id', 'amount', 'created_at']; 
        foreach($required as $req) {
            if(!in_array($req, $columns)) {
                echo "<span style='color:red'>⚠️ DİKKAT: '$req' sütunu tablonda yok! Kod bu yüzden patlıyor.</span><br>";
            } else {
                echo "✅ '$req' sütunu mevcut.<br>";
            }
        }

    } else {
        echo "⚠️ Tabloya erişildi ama içinde hiç veri yok.<br>";
    }

} catch (PDOException $e) {
    echo "<h3 style='color:red'>🚨 KRİTİK SQL HATASI:</h3>";
    echo "Hata Mesajı: " . $e->getMessage() . "<br>";
    echo "<b>Muhtemel Çözüm:</b> Tablo ismini veya sütun isimlerini yanlış yazdık. Lütfen veritabanındaki gerçek isimleri kontrol et.";
}
?>