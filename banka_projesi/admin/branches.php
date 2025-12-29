<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Sadece Admin ve Personel girebilsin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'Admin' && $_SESSION['role'] != 'Personel')) {
    header("Location: ../index.php");
    exit;
}

// Şubeleri Çek
$stmt = $pdo->query("SELECT * FROM branches");
$subeler = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Şube Listesi</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/style.css"> 
    <style>
        /* Senin mevcut özel stillerin */
        .container { width: 85%; margin: 30px auto; }
        /* Tablo tasarımını biraz daha modernleştirdik */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #333; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        
        /* Başlık stili */
        h2 { color: #333; margin: 0; } /* Alt çizgiyi kaldırdım çünkü flex yapısına aldık */
        
        .back-btn { display: inline-block; text-decoration: none; background: #555; color: #fff; padding: 8px 15px; border-radius: 4px;}
        .back-btn:hover { background: #333; color: #fff; }
    </style>
</head>
<body class="bg-light">

    <div class="container">
        <div class="mb-3">
            <a href="all_users.php" class="back-btn">&larr; Panele Dön</a>
        </div>
        
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
            <h2>🏢 Bankamızın Şubeleri</h2>

            <?php if ($_SESSION['role'] == 'Admin'): ?>
                <a href="add_branch.php" class="btn btn-success">
                    <i class="fa fa-plus"></i> Yeni Şube Ekle
                </a>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Şube Adı</th>
                    <th>Şehir</th>
                    <th>Adres</th>
                    <th>Telefon</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($subeler) > 0): ?>
                    <?php foreach ($subeler as $sube): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sube['BranchName']); ?></td>
                            <td><?php echo htmlspecialchars($sube['City']); ?></td>
                            <td><?php echo htmlspecialchars($sube['Address']); ?></td>
                            <td><?php echo htmlspecialchars($sube['Phone']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">Henüz kayıtlı şube bulunmuyor.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>