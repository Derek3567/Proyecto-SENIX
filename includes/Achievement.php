<?php
/**
 * ==========================================================
 * SignAI v2.0 — Modelo Achievement
 * ==========================================================
 */

if (basename($_SERVER['PHP_SELF']) === 'Achievement.php') die('Acceso denegado');

class Achievement {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function getAll(bool $includeHidden = false): array {
        $sql = "SELECT * FROM achievements WHERE is_active = 1";
        if (!$includeHidden) $sql .= " AND is_hidden = 0";
        $sql .= " ORDER BY requirement_type, requirement_value";
        return $this->db->query($sql)->fetchAll();
    }
    
    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM achievements WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function getByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM achievements WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }
    
    public function getUserAchievements(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT a.*, ua.unlocked, ua.progress, ua.unlocked_at
             FROM achievements a
             LEFT JOIN user_achievements ua 
                ON ua.achievement_id = a.id AND ua.user_id = ?
             WHERE a.is_active = 1
             ORDER BY ua.unlocked DESC, a.requirement_type, a.requirement_value"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getUserUnlocked(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT a.*, ua.unlocked_at
             FROM user_achievements ua
             INNER JOIN achievements a ON a.id = ua.achievement_id
             WHERE ua.user_id = ? AND ua.unlocked = 1
             ORDER BY ua.unlocked_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function unlock(int $userId, int $achievementId): array {
        $ach = $this->find($achievementId);
        if (!$ach) return ['success' => false];
        
        $stmt = $this->db->prepare(
            "SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?"
        );
        $stmt->execute([$userId, $achievementId]);
        $existing = $stmt->fetch();
        
        if ($existing && (int)$existing['unlocked'] === 1) {
            return ['success' => true, 'already_unlocked' => true];
        }
        
        $stmt = $this->db->prepare(
            "INSERT INTO user_achievements (user_id, achievement_id, unlocked, unlocked_at)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE unlocked = 1, unlocked_at = NOW()"
        );
        $stmt->execute([$userId, $achievementId]);
        
        // Notificación
        $this->db->prepare(
            "INSERT INTO notifications (user_id, type, title, message, icon, link)
             VALUES (?, 'achievement', ?, ?, ?, '/achievements.php')"
        )->execute([
            $userId,
            '¡Logro desbloqueado!',
            $ach['name'] . ' - ' . $ach['description'],
            $ach['icon']
        ]);
        
        return [
            'success'      => true,
            'achievement'  => $ach,
            'xp_reward'    => (int)$ach['xp_reward']
        ];
    }
    
    public function checkAll(int $userId): array {
        $unlocked = [];
        $userModel = new User($this->db);
        $stats = $userModel->getStats($userId);
        
        $allAch = $this->db->query("SELECT * FROM achievements WHERE is_active = 1")->fetchAll();
        
        foreach ($allAch as $ach) {
            $current = 0;
            
            switch ($ach['requirement_type']) {
                case 'lessons':    $current = (int)$stats['lessons_completed']; break;
                case 'letters':    $current = (int)$stats['letters_learned']; break;
                case 'words':      $current = (int)$stats['words_learned']; break;
                case 'streak':     $current = (int)$stats['streak']; break;
                case 'level':      $current = (int)$stats['level']; break;
                case 'xp':         $current = (int)$stats['total_xp']; break;
                case 'accuracy':   $current = (int)$stats['accuracy']; break;
                case 'sessions':   $current = (int)$stats['sessions_count']; break;
                case 'detections': $current = (int)$stats['detections_total']; break;
                default:           continue 2;
            }
            
            if ($current >= $ach['requirement_value']) {
                $result = $this->unlock($userId, $ach['id']);
                if ($result['success'] && empty($result['already_unlocked'])) {
                    $unlocked[] = $result['achievement'];
                }
            }
        }
        
        return $unlocked;
    }
    
    public function countUnlocked(int $userId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_achievements WHERE user_id = ? AND unlocked = 1"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
    
    public function countTotal(): int {
        return (int)$this->db->query("SELECT COUNT(*) FROM achievements WHERE is_active = 1")->fetchColumn();
    }
}
?>