<?php
/**
 * Senix v2.0 — Register
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/bootstrap.php';

if ($auth->isLoggedIn()) {
    redirect(APP_URL . '/menu.php');
}

$errors = [];
$old = ['username' => '', 'email' => '', 'full_name' => ''];

if (isPost()) {
    if (!csrf_verify()) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['password_confirm'] ?? '');
        $terms    = !empty($_POST['terms']);
        
        $old = ['username' => $username, 'email' => $email, 'full_name' => $fullName];
        
        // Validaciones
        // Renombrar para la validación 'confirmed'
        $validatorData = [
            'username' => $username,
            'email'    => $email,
            'password' => $password,
            'password_confirm' => $confirm
        ];
        $validator = Validator::make($validatorData, [
            'username' => 'required|username|min:3|max:50',
            'email'    => 'required|email|max:150',
            'password' => 'required|password',
            'password_confirm' => 'required'
        ]);
        
        if ($password !== $confirm) {
            $errors[] = 'Las contraseñas no coinciden';
        }
        if (strlen($password) > 72) {
            $errors[] = 'La contraseña no puede superar 72 bytes. Usa una frase más corta.';
        }
        if (strlen($email) > 150) {
            $errors[] = 'El email no puede superar 150 caracteres.';
        }
        if (strlen($fullName) > 150 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $fullName)) {
            $errors[] = 'El nombre completo es demasiado largo o contiene caracteres no permitidos.';
        }
        
        if (!$terms) {
            $errors[] = 'Debes aceptar los términos y condiciones';
        }
        
        // Rate limiting
        $rateCheck = rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 3600);
        if (!$rateCheck['allowed']) {
            $errors[] = 'Demasiados registros desde tu IP. Intenta más tarde.';
        }
        
        if ($validator->fails()) {
            foreach ($validator->errors() as $field => $msgs) {
                foreach ($msgs as $m) $errors[] = $m;
            }
        }
        
        if (empty($errors)) {
            $result = $auth->register($username, $email, $password, $fullName);
            
            if ($result['success']) {
                // Auto login
                $login = $auth->login($username, $password);
                
                if ($login['success']) {
                    setFlash('success', '¡Bienvenido a Senix, ' . $username . '!');
                    log_security_event('register_success', ['user_id' => $result['user_id']]);
                    redirect(APP_URL . '/menu.php');
                } else {
                    setFlash('success', 'Cuenta creada. Inicia sesión.');
                    redirect(APP_URL . '/login.php');
                }
            } else {
                $errors[] = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta — Senix</title>
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
<body class="page-register">

<div class="auth-wrapper auth-wrapper-reverse">
    <div class="auth-form-side">
        <a href="<?= APP_URL ?>/index.php" class="auth-logo">
            <span class="logo-text">SENIX</span>
        </a>
        
        <form class="auth-form" method="POST" autocomplete="on" id="register-form" data-auth-form>
            <div class="auth-header">
                <h1 class="auth-title">CREAR CUENTA</h1>
                <p class="auth-subtitle">Únete a la revolución Senix</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive">
                    <span class="alert-icon">✕</span>
                    <div>
                        <?php foreach ($errors as $err): ?>
                            <div><?= e($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-wrapper">
                    <span class="input-icon">@</span>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        placeholder="usuario_123"
                        required
                        minlength="3"
                        maxlength="50"
                        pattern="[a-zA-Z0-9_]{3,50}"
                        autocapitalize="none"
                        spellcheck="false"
                        aria-describedby="username-hint"
                        autocomplete="username"
                        value="<?= e($old['username']) ?>"
                        autofocus
                    >
                </div>
                <small class="input-hint" id="username-hint">De 3 a 50 caracteres. Solo letras sin acento, números y guion bajo (_).</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">✉</span>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="tu@email.com"
                        required
                        maxlength="150"
                        autocomplete="email"
                        inputmode="email"
                        autocapitalize="none"
                        value="<?= e($old['email']) ?>"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="full_name">Nombre completo <span class="optional">(opcional)</span></label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="form-control" 
                        placeholder="Juan Pérez"
                        maxlength="150"
                        autocomplete="name"
                        aria-describedby="full-name-hint"
                        value="<?= e($old['full_name']) ?>"
                    >
                </div>
                <small class="input-hint" id="full-name-hint">Opcional. Máximo 150 caracteres.</small>
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
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        aria-describedby="pw-reqs password-strength"
                    >
                    <button type="button" class="input-toggle" data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false"><span aria-hidden="true">◉</span></button>
                </div>
                <div class="password-strength" id="password-strength">
                    <div class="strength-bar"><div class="strength-fill"></div></div>
                    <span class="strength-text">Escribe una contraseña</span>
                </div>
                <ul class="password-requirements" id="pw-reqs">
                    <li data-req="length"><span class="req-icon">○</span> Mínimo 8 caracteres</li>
                    <li data-req="uppercase"><span class="req-icon">○</span> Una mayúscula</li>
                    <li data-req="lowercase"><span class="req-icon">○</span> Una minúscula</li>
                    <li data-req="number"><span class="req-icon">○</span> Un número</li>
                    <li data-req="special"><span class="req-icon">○</span> Un carácter especial</li>
                </ul>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirmar contraseña</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        class="form-control" 
                        placeholder="••••••••"
                        required
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        data-confirm-password="password"
                    >
                    <button type="button" class="input-toggle" data-toggle-password="password_confirm" aria-label="Mostrar contraseña" aria-pressed="false"><span aria-hidden="true">◉</span></button>
                </div>
            </div>
            
            <label class="checkbox-wrapper terms-check">
                <input type="checkbox" name="terms" value="1" required>
                <span class="checkbox-custom"></span>
                <span class="checkbox-label">
                    Acepto los <a href="<?= APP_URL ?>/terms.php" target="_blank">Términos</a> y la 
                    <a href="<?= APP_URL ?>/privacy.php" target="_blank">Política de Privacidad</a>
                </span>
            </label>
            
            <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit" id="submit-btn" data-submit-button>
                <span>CREAR CUENTA</span>
                <span class="btn-arrow">→</span>
            </button>
            
            <p class="auth-footer-text">
                ¿Ya tienes cuenta? 
                <a href="<?= APP_URL ?>/login.php">Inicia sesión aquí</a>
            </p>
        </form>
    </div>
    
    <div class="auth-spline">
        <spline-viewer url="https://prod.spline.design/iTzov2BzGaC6r45Y/scene.splinecode"></spline-viewer>
        <div class="auth-spline-overlay">
            <h2 class="glitch" data-text="ÚNETE">ÚNETE</h2>
            <p>Miles ya están aprendiendo</p>
            <div class="spline-features">
                <div class="feature-pill">🏆 +1000 usuarios</div>
                <div class="feature-pill">⭐ Logros diarios</div>
                <div class="feature-pill">💬 Simulador IA</div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/auth.js') ?>" defer></script>

</body>
</html>