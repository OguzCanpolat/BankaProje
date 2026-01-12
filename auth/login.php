<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap | NeoBank</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">

    <style>
        /* Bu sayfaya özel yerleşim ayarları */
        .login-container {
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }
        
        /* Sol Taraf: Marka Alanı */
        .login-brand {
            flex: 1;
            background: var(--primary-gradient); /* style.css'ten geliyor */
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 50px;
        }

        /* Dekoratif Yuvarlaklar */
        .circle { position: absolute; background: rgba(255, 255, 255, 0.1); border-radius: 50%; }
        .circle-1 { width: 300px; height: 300px; top: -50px; left: -50px; }
        .circle-2 { width: 400px; height: 400px; bottom: -100px; right: -100px; }
        
        /* Sağ Taraf: Form Alanı */
        .login-form-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #fff;
        }

        /* Mobil Uyumluluk */
        @media (max-width: 768px) {
            .login-container { flex-direction: column; }
            .login-brand { flex: 0.3; padding: 30px; }
            .login-form-side { flex: 0.7; }
        }
    </style>
</head>
<body>

<div class="login-container">
    
    <div class="login-brand">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        
        <div class="text-center" style="z-index: 2;">
            <i class="fas fa-university fa-4x mb-4"></i>
            <h1 class="fw-bold mb-3">NeoBank</h1>
            <p class="lead opacity-75">Güvenli, Hızlı ve Modern Bankacılık</p>
        </div>
    </div>

    <div class="login-form-side">
        <div style="width: 100%; max-width: 400px;">
            
            <div class="mb-5">
                <h2 class="fw-bold text-dark">Hoş Geldiniz</h2>
                <p class="text-muted">Hesabınıza erişmek için giriş yapın.</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm border-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <div><?= htmlspecialchars($_GET['error']) ?></div>
                </div>
            <?php endif; ?>

            <form action="login_action.php" method="POST">
                
                <div class="mb-4">
                    <label class="form-label text-uppercase fw-bold text-secondary" style="font-size: 12px;">E-Posta Adresi</label>
                    <div class="input-group">
                        <span class="input-group-text border-0 bg-light"><i class="fas fa-envelope text-muted"></i></span>
                        <input type="email" name="email" class="form-control bg-light border-0" placeholder="ornek@banka.com" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-uppercase fw-bold text-secondary" style="font-size: 12px;">Şifre</label>
                    <div class="input-group">
                        <span class="input-group-text border-0 bg-light"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" name="password" class="form-control bg-light border-0" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label text-muted small" for="remember">Beni Hatırla</label>
                    </div>
                    <a href="#" class="text-decoration-none small text-primary fw-bold">Şifremi Unuttum?</a>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-custom">
                        GİRİŞ YAP <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>

            </form>

            <div class="mt-4 p-3 bg-light rounded text-center border border-dashed">
                <small class="text-muted d-block fw-bold mb-1">Geliştirici Notu:</small>
                <code class="text-primary">admin@banka.com</code> / <code class="text-primary">1234</code>
            </div>

            <div class="mt-4 text-center text-muted small">
                Banka müşterisi değil misiniz? <a href="#" class="text-primary fw-bold">Hemen Başvurun</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>