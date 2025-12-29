<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Banka Sistemi - Giriş</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background: white; }
    </style>
</head>
<body>

    <div class="login-card">
        <h3 class="text-center mb-4">Banka Giriş</h3>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <form action="login_action.php" method="POST">
            <div class="mb-3">
                <label>E-Posta Adresi</label>
                <input type="email" name="email" class="form-control" required placeholder="ornek@banka.com">
            </div>
            <div class="mb-3">
                <label>Şifre</label>
                <input type="password" name="password" class="form-control" required placeholder="****">
            </div>
            <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
        </form>
        
        <div class="mt-3 text-center">
            <small class="text-muted">Test için: admin@banka.com / 1234</small>
        </div>
    </div>

</body>
</html>