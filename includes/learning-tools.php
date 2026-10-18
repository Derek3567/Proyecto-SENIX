<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/bootstrap.php';
if (!$auth->isLoggedIn()) redirect(APP_URL . '/login.php');
$learnUser=$auth->user() ?: []; $learnUserId=(int)($learnUser['id']??0);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['learn_csrf'])) $_SESSION['learn_csrf']=bin2hex(random_bytes(32));
function learn_h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function learn_csrf(){return '<input type="hidden" name="csrf_token" value="'.learn_h($_SESSION['learn_csrf']).'">';}
function learn_verify_csrf(){ $t=(string)($_POST['csrf_token']??''); if(!$t||!hash_equals((string)($_SESSION['learn_csrf']??''),$t)){http_response_code(419);exit('La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.');}}
function learn_db(){global $pdo;return $pdo;}
function learn_ensure_tables(){
 static $checked=false; if($checked)return true;
 $sqls=[
 "CREATE TABLE IF NOT EXISTS custom_lessons (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,title VARCHAR(120) NOT NULL,description VARCHAR(500) DEFAULT '',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),CONSTRAINT fk_custom_lesson_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS custom_lesson_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,custom_lesson_id BIGINT UNSIGNED NOT NULL,prompt VARCHAR(255) NOT NULL,answer VARCHAR(255) NOT NULL,options_json TEXT NOT NULL,order_index INT NOT NULL DEFAULT 0,CONSTRAINT fk_custom_item_lesson FOREIGN KEY(custom_lesson_id) REFERENCES custom_lessons(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS custom_lesson_progress (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,custom_lesson_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,score INT NOT NULL,question_count INT NOT NULL,completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id),CONSTRAINT fk_clp_lesson FOREIGN KEY(custom_lesson_id) REFERENCES custom_lessons(id) ON DELETE CASCADE,CONSTRAINT fk_clp_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS daily_challenge_progress (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,challenge_date DATE NOT NULL,user_id INT UNSIGNED NOT NULL,score INT NOT NULL DEFAULT 0,completed TINYINT(1) NOT NULL DEFAULT 0,completed_at DATETIME DEFAULT NULL,UNIQUE KEY uq_daily_user(challenge_date,user_id),CONSTRAINT fk_dcp_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 "CREATE TABLE IF NOT EXISTS skill_assessments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,score INT NOT NULL,total INT NOT NULL,level_label VARCHAR(40) NOT NULL,answers_json TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id,created_at),CONSTRAINT fk_skill_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
 ]; $ok=true; foreach($sqls as $sql){try{learn_db()->exec($sql);}catch(Throwable $e){$ok=false;error_log('[Senix learning schema] '.$e->getMessage());}} $checked=$ok; return $ok;
}
function learn_header($title,$subtitle=''){ $u=learn_h($title);$s=learn_h($subtitle);$url=learn_h(APP_URL);echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$u.' · Senix</title><link rel="stylesheet" href="'.learn_h(asset('css/menu.css')).'"><link rel="stylesheet" href="'.learn_h(asset('css/learning-tools.css')).'"></head><body class="sx-app"><main class="learn-tool-shell"><header class="learn-tool-top"><a href="'. $url .'/menu.php">← Volver a Senix</a><span>✦ SENIX · APRENDIZAJE</span><a href="'. $url .'/logout.php">Cerrar sesión</a></header><section class="learn-tool-heading"><span class="eyebrow">TU ESPACIO DE APRENDIZAJE</span><h1>'.$u.'</h1>'.($s?'<p>'.$s.'</p>':'').'</section>'; }
function learn_footer(){echo '</main></body></html>';}
function learn_flash($message){echo '<div class="learn-notice" role="status">'.learn_h($message).'</div>'; }
function learn_redirect(string $url): never { header('Location: '.$url, true, 303); exit; }
function learn_record_practice(int $userId, int $xp=0): void { global $userModel, $auth, $achievementModel; try { if(isset($userModel) && method_exists($userModel,'updateStreak')) $userModel->updateStreak($userId); if($xp>0 && isset($userModel)) $userModel->addXP($userId,$xp); if(isset($achievementModel)) $achievementModel->checkAll($userId); } catch(Throwable $e) { error_log('[Senix practice record] '.$e->getMessage()); } }
