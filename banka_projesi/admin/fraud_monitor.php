<?php
session_start();
// Veritabanı bağlantısı (Dosya yoluna dikkat et)
require_once '../includes/db.php';

// Admin girişi kontrolü (Projenin auth yapısına göre düzenle)
// if (!isset($_SESSION['admin_logged_in'])) { header("Location: ../auth/login.php"); exit; }

$suspicious_transactions = [];

// --- 1. KURAL: LİMİT AŞIMI (50.000 TL Üzeri) ---
$sql_limit = "SELECT t.*, u.name, u.surname, u.id as user_id 
              FROM transactions t 
              JOIN users u ON t.sender_id = u.id 
              WHERE t.amount > 50000 
              ORDER BY t.created_at DESC";
$stmt = $conn->prepare($sql_limit);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['reason'] = "🚨 Yüksek Tutar (Limit Aşımı)";
    $suspicious_transactions[] = $row;
}

// --- 2. KURAL: GECE KUŞU (02:00 - 05:00 Arası Döviz) ---
// Not: transaction_type sütununun döviz işlemleri için 'exchange' olduğunu varsayıyorum.
$sql_night = "SELECT t.*, u.name, u.surname, u.id as user_id 
              FROM transactions t 
              JOIN users u ON t.sender_id = u.id 
              WHERE (HOUR(t.created_at) BETWEEN 2 AND 5) 
              AND t.transaction_type = 'exchange'
              ORDER BY t.created_at DESC";
$stmt = $conn->prepare($sql_night);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['reason'] = "🌙 Gece İşlemi (02:00-05:00)";
    $suspicious_transactions[] = $row;
}

// --- 3. KURAL: ARDIŞIK İŞLEM (Son 10 dk içinde aynı kişiye 3+ transfer) ---
// Bu sorgu biraz daha komplekstir (Aggregate function kullanımı)
$sql_spam = "SELECT t.sender_id, t.receiver_id, COUNT(*) as tx_count, MAX(t.created_at) as last_tx_time, u.name, u.surname, u.id as user_id
             FROM transactions t
             JOIN users u ON t.sender_id = u.id
             WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             GROUP BY t.sender_id, t.receiver_id
             HAVING tx_count >= 3";
$stmt = $conn->prepare($sql_spam);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['amount'] = "---"; // Toplu işlem olduğu için tutar belirsiz
    $row['created_at'] = $row['last_tx_time'];
    $row['reason'] = "⚡ Ardışık Transfer ({$row['tx_count']} kez)";
    // id ve diğer bilgileri array yapısına uyduruyoruz
    $suspicious_transactions[] = $row;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Fraud Monitor - Şüpheli İşlemler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .fraud-alert { background-color: #fff3f3; border-left: 5px solid red; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="text-danger mb-4">🚨 Şüpheli İşlem Monitörü (Fraud Monitor)</h2>
        
        <div class="card shadow">
            <div class="card-body">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Müşteri</th>
                            <th>Tespit Nedeni</th>
                            <th>Tutar</th>
                            <th>Tarih</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suspicious_transactions)): ?>
                            <tr><td colspan="5" class="text-center">Şüpheli işlem bulunamadı. Her şey temiz. ✅</td></tr>
                        <?php else: ?>
                            <?php foreach ($suspicious_transactions as $sus): ?>
                            <tr class="fraud-alert">
                                <td>
                                    <strong><?php echo htmlspecialchars($sus['name'] . ' ' . $sus['surname']); ?></strong><br>
                                    <small class="text-muted">ID: <?php echo $sus['user_id']; ?></small>
                                </td>
                                <td class="text-danger fw-bold"><?php echo $sus['reason']; ?></td>
                                <td><?php echo $sus['amount']; ?> TL</td>
                                <td><?php echo $sus['created_at']; ?></td>
                                <td>
                                    <a href="block_user.php?id=<?php echo $sus['user_id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Bu kullanıcıyı bloke etmek istediğinize emin misiniz?');">
                                        🚫 Bloke Et
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>