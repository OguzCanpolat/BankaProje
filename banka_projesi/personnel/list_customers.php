<?php
session_start();
require_once '../includes/db.php';

// Sadece Personel Girebilir
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Personel') {
    header("Location: ../auth/login.php");
    exit;
}

// Müşterileri ve E-posta adreslerini çekmek için JOIN işlemi
// NOT: update_customer_action.php işlem yaparken UserID'ye ihtiyaç duyabilir.
// Bu yüzden sorguya u.UserID ekledim.
$sql = "SELECT c.CustomerID, c.FirstName, c.LastName, c.TCKN, c.Phone, u.Email, c.BranchID, u.UserID 
        FROM Customers c 
        JOIN Users u ON c.UserID = u.UserID 
        ORDER BY c.FirstName ASC";

$musteriler = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Müşteri Listesi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

    <div class="container mt-5">
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                İşlem başarıyla tamamlandı!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Hata: Email adresi geçersiz veya işlem başarısız!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa fa-users text-primary"></i> Müşteri Listesi</h2>
            <div>
                <a href="add_customer.php" class="btn btn-success"><i class="fa fa-plus"></i> Yeni Müşteri Ekle</a>
                <a href="../index.php" class="btn btn-secondary">Ana Sayfa</a>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <table class="table table-hover table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Ad Soyad</th>
                            <th>TC Kimlik No</th>
                            <th>E-Posta</th>
                            <th>Telefon</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($musteriler as $m): ?>
                            <tr>
                                <td><?= $m['CustomerID'] ?></td>
                                <td class="fw-bold"><?= $m['FirstName'] ?> <?= $m['LastName'] ?></td>
                                <td><?= $m['TCKN'] ?></td>
                                <td><?= $m['Email'] ?></td>
                                <td><?= $m['Phone'] ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info text-white"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editUserModal"
                                            data-userid="<?= $m['UserID'] ?>" 
                                            data-name="<?= $m['FirstName'] . ' ' . $m['LastName'] ?>" 
                                            data-email="<?= $m['Email'] ?>">
                                        Detay / Düzenle
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($musteriler)): ?>
                    <p class="text-center mt-3 text-muted">Kayıtlı müşteri bulunamadı.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Müşteri Bilgilerini Düzenle</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          
          <form action="update_customer_action.php" method="POST">
              <div class="modal-body">
                
                <input type="hidden" name="user_id" id="modal_userid">

                <div class="mb-3">
                    <label>Ad Soyad</label>
                    <input type="text" id="modal_name" class="form-control" disabled>
                    <small class="text-muted">Ad soyad değiştirilemez.</small>
                </div>

                <div class="mb-3">
                    <label>E-Posta (Zorunlu: @banka.com)</label>
                    <input type="email" name="email" id="modal_email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Yeni Şifre</label>
                    <input type="text" name="password" class="form-control" placeholder="Değişmeyecekse boş bırakın">
                </div>

              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="submit" name="update_customer" class="btn btn-primary">Değişiklikleri Kaydet</button>
              </div>
          </form>

        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        var editModal = document.getElementById('editUserModal')
        editModal.addEventListener('show.bs.modal', function (event) {
            // Butona tıklanınca verileri al
            var button = event.relatedTarget
            
            var userid = button.getAttribute('data-userid')
            var name = button.getAttribute('data-name')
            var email = button.getAttribute('data-email')

            // Inputlara yaz
            document.getElementById('modal_userid').value = userid
            document.getElementById('modal_name').value = name
            document.getElementById('modal_email').value = email
        })
    </script>

</body>
</html>