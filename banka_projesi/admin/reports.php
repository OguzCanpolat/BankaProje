<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Admin değilse at
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: ../auth/login.php");
    exit;
}

$suspicious_transactions = [];

try {
    // --- KURAL 1: BÜYÜK PARA ÇIKIŞLARI (50.000 TL Üzeri) ---
    // NOT: Transactions tablosunda 'Amount' ve 'TransactionDate' olduğunu varsayıyorum. 
    // Eğer isimler farklıysa (örn: Tutar, Tarih) aşağıdaki SQL'de düzeltmelisin.
    
    $sql = "SELECT 
                t.TransactionID,
                t.Amount, 
                t.TransactionDate,
                c.FirstName, 
                c.LastName, 
                c.CustomerID
            FROM Transactions t
            INNER JOIN Accounts a ON t.SenderAccountID = a.AccountID
            INNER JOIN Customers c ON a.CustomerID = c.CustomerID
            WHERE t.Amount >= 50000
            ORDER BY t.TransactionDate DESC
            LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['reason'] = "🚨 Yüksek Tutar (Limit Aşımı)";
        $suspicious_transactions[] = $row;
    }

    // --- KURAL 2: GECE İŞLEMLERİ (02:00 - 05:00) ---
    // SQL'deki HOUR fonksiyonu ile saati yakalıyoruz
    $sql_night = "SELECT 
                    t.TransactionID,
                    t.Amount, 
                    t.TransactionDate,
                    c.FirstName, 
                    c.LastName, 
                    c.CustomerID
                FROM Transactions t
                INNER JOIN Accounts a ON t.SenderAccountID = a.AccountID
                INNER JOIN Customers c ON a.CustomerID = c.CustomerID
                WHERE HOUR(t.TransactionDate) BETWEEN 2 AND 5
                ORDER BY t.TransactionDate DESC
                LIMIT 20";
                
    $stmt = $pdo->query($sql_night);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['reason'] = "🌙 Gece İşlemi (02:00-05:00)";
        $suspicious_transactions[] = $row;
    }

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Fraud Monitor - Risk Takip</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fraud-row { border-left: 5px solid #dc3545; background-color: #fff5f5; }
        .badge-danger { background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-danger"><i class="fas fa-shield-alt"></i> Fraud Monitor (Risk İzleme)</h2>
        <a href="reports.php" class="btn btn-secondary">Raporlara Dön</a>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Müşteri</th>
                        <th>Risk Nedeni</th>
                        <th>İşlem Tutarı</th>
                        <th>Tarih</th>
                        <th>Aksiyon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suspicious_transactions)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i><br>
                                <span class="text-muted">Harika! Şüpheli bir işlem görünmüyor.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($suspicious_transactions as $sus): ?>
                        <tr class="fraud-row">
                            <td>
                                <strong><?php echo htmlspecialchars($sus['FirstName'] . ' ' . $sus['LastName']); ?></strong><br>
                                <small class="text-muted">Müşteri ID: <?php echo $sus['CustomerID']; ?></small>
                            </td>
                            <td>
                                <span class="badge-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $sus['reason']; ?>
                                </span>
                            </td>
                            <td class="fw-bold"><?php echo number_format($sus['Amount'], 2); ?></td>
                            <td><?php echo $sus['TransactionDate']; ?></td>
                            <td>
                                <button class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-ban"></i> Hesabı İncele
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle"></i> 
        <strong>Nasıl Çalışır?</strong> Sistem otomatik olarak 50.000 TL üzeri transferleri ve gece 02:00-05:00 arası yapılan işlemleri buraya düşürür.
    </div>
</div>

</body>
</html>