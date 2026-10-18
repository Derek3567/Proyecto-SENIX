<?php
/**
 * Senix v2.0 — Login
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/bootstrap.php';

// Si ya está logueado, redirige
if ($auth->isLoggedIn()) {
    redirect(APP_URL . '/menu.php');
}

$error = '';
$success = '';

if (isPost()) {
    if (!csrf_verify()) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');
        $remember   = isset($_POST['remember']) && $_POST['remember'] === '1';

        // Validación del lado del servidor: nunca dependemos solo del navegador.
        if ($identifier === '' || $password === '') {
            $error = 'Completa tu usuario o email y tu contraseña.';
        } elseif (strlen($identifier) > 254 || strlen($password) > 1024) {
            $error = 'Los datos introducidos superan la longitud permitida.';
        } elseif (preg_match('/[\x00-\x1F\x7F]/', $identifier)) {
            $error = 'El usuario o email contiene caracteres no permitidos.';
        } elseif (strpos($identifier, '@') !== false ? !validate_email($identifier) : !validate_username($identifier)) {
            $error = 'Introduce un usuario válido o una dirección de email válida.';
        } else {
            $result = $auth->login($identifier, $password, $remember);
            
            if ($result['success']) {
                log_security_event('login_success', ['user_id' => $result['user']['id']]);
                redirect(APP_URL . '/menu.php');
            } else {
                $error = $result['error'];
                log_security_event('login_failed', ['identifier_hash' => hash('sha256', strtolower($identifier))]);
            }
        }
    }
}

if ($msg = getFlash('info')) $success = $msg;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — Senix</title>
    <meta name="theme-color" content="#00f0ff">
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/animations.css') ?>">
    <script type="module" src="https://cdn.spline.design/@splinetool/viewer@2.0.65/build/spline-viewer.js"></script>
</head>
<body class="page-login">

<div class="auth-wrapper">
    <div class="auth-spline">
        <spline-viewer url="https://prod.spline.design/iTzov2BzGaC6r45Y/scene.splinecode"></spline-viewer>
        
        <div class="auth-spline-overlay">
            <h2 class="glitch" data-text="SENIX">SENIX</h2>
            <p>Tecnología del futuro, hoy</p>
            <div class="spline-features">
                <div class="feature-pill">🤖 IA en tiempo real</div>
                <div class="feature-pill">✋ Detección 3D</div>
                <div class="feature-pill">🎓 Lecciones interactivas</div>
            </div>
        </div>
    </div>
    
    <div class="auth-form-side">
        <a href="<?= APP_URL ?>/index.php" class="auth-logo">
            <span class="logo-text">SENIX</span>
        </a>
        
        <form class="auth-form" method="POST" autocomplete="on" id="login-form" data-auth-form>
            <div class="auth-header">
                <h1 class="auth-title">ACCESO</h1>
                <p class="auth-subtitle">Ingresa a tu cuenta Senix</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive">
                    <span class="alert-icon">!</span>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="status" aria-live="polite">
                    <span class="alert-icon">✓</span>
                    <span><?= e($success) ?></span>
                </div>
            <?php endif; ?>
            
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="identifier">Usuario o Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input 
                        type="text" 
                        id="identifier" 
                        name="identifier" 
                        class="form-control" 
                        placeholder="Usuario o correo electrónico"
                        required
                        autofocus
                        maxlength="254"
                        autocapitalize="none"
                        spellcheck="false"
                        autocomplete="username"
                        aria-describedby="identifier-hint"
                        value="<?= e($_POST['identifier'] ?? '') ?>"
                    >
                </div>
                <small class="input-hint" id="identifier-hint">Usa tu nombre de usuario o el email con el que te registraste.</small>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="••••••••"
                        required
                        maxlength="1024"
                        autocomplete="current-password"
                        aria-describedby="password-hint"
                    >
                    <button type="button" class="input-toggle" data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false">
                        <span aria-hidden="true">◉</span>
                    </button>
                </div>
                <small class="input-hint" id="password-hint">La contraseña distingue entre mayúsculas y minúsculas.</small>
            </div>
            
            <div class="form-row">
                <label class="checkbox-wrapper">
                    <input type="checkbox" name="remember" value="1">
                    <span class="checkbox-custom"></span>
                    <span class="checkbox-label">Recordarme</span>
                </label>
                
                <a href="<?= APP_URL ?>/forgot-password.php" class="forgot-link">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit" data-submit-button>
                <span class="submit-label">Iniciar sesión</span>
                <span class="btn-arrow" aria-hidden="true">→</span>
            </button>
            
            <div class="auth-divider">
                <span>o</span>
            </div>
            
            <a href="<?= APP_URL ?>/register.php" class="btn btn-outline btn-block">
                <span>CREAR CUENTA NUEVA</span>
            </a>
            
            <p class="auth-footer-text">
                Al continuar aceptas nuestros 
                <a href="<?= APP_URL ?>/terms.php">Términos</a> y 
                <a href="<?= APP_URL ?>/privacy.php">Política de Privacidad</a>
            </p>
        </form>
    </div>
</div>

<script src="<?= asset('js/auth.js') ?>" defer></script>

</body>
</html>
