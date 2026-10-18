<?php
/**
 * ==========================================================
 * SignAI v2.0 — Modelo Progress
 * ==========================================================
 */

if (basename($_SERVER['PHP_SELF']) === 'Progress.php') die('Acceso denegado');

class Progress {
    private PDO $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function start(int $userId, int $lessonId): int {
        $stmt = $this->db->prepare(
            "INSERT INTO user_progress (user_id, lesson_id, attempts, last_attempt)
             VALUES (?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()"
        );
        $stmt->execute([$userId, $lessonId]);
        
        $stmt = $this->db->prepare(
            "SELECT id FROM user_progress WHERE user_id = ? AND lesson_id = ?"
        );
        $stmt->execute([$userId, $lessonId]);
        return (int)$stmt->fetch()['id'];
    }
    
    public function complete(int $userId, int $lessonId, array $results): array {
        $score    = (int)($results['score'] ?? 0);
        $maxScore = (int)($results['max_score'] ?? 100);
        $accuracy = (float)($results['accuracy'] ?? 0);
        $time     = (int)($results['time_spent'] ?? 0);
        $perfect  = ($score >= $maxScore && $maxScore > 0) ? 1 : 0;
        
        $stmt = $this->db->prepare(
            "INSERT INTO user_progress 
                (user_id, lesson_id, completed, score, max_score, accuracy, time_spent, perfect, completed_at)
             VALUES (?, ?, 1, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                completed = 1,
                score = GREATEST(score, VALUES(score)),
                max_score = VALUES(max_score),
                accuracy = GREATEST(accuracy, VALUES(accuracy)),
                time_spent = time_spent + VALUES(time_spent),
                perfect = GREATEST(perfect, VALUES(perfect)),
                completed_at = NOW()"
        );
        $stmt->execute([$userId, $lessonId, $score, $maxScore, $accuracy, $time, $perfect]);
        
        return [
            'success'  => true,
            'perfect'  => (bool)$perfect,
            'score'    => $score,
            'accuracy' => $accuracy
        ];
    }
    
    public function isCompleted(int $userId, int $lessonId): bool {
        $stmt = $this->db->prepare(
            "SELECT completed FROM user_progress WHERE user_id = ? AND lesson_id = ? LIMIT 1"
        );
        $stmt->execute([$userId, $lessonId]);
        $r = $stmt->fetch();
        return $r && (int)$r['completed'] === 1;
    }
    
    public function getUserStats(int $userId): array {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total_lessons,
                SUM(completed) as completed_lessons,
                SUM(perfect) as perfect_lessons,
                AVG(accuracy) as avg_accuracy,
                SUM(time_spent) as total_time
             FROM user_progress WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: [];
    }
    
    public function getRecent(int $userId, int $limit = 5): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, l.title, l.icon, l.category
             FROM user_progress p
             INNER JOIN lessons l ON l.id = p.lesson_id
             WHERE p.user_id = ? AND p.completed = 1
             ORDER BY p.completed_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function saveDetection(int $userId, array $data): int {
        $stmt = $this->db->prepare(
            "INSERT INTO detection_history 
                (user_id, detected_char, expected_char, confidence, is_correct, session_type, lesson_id, hand_used, processing_time_ms, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $data['detected_char'] ?? null,
            $data['expected_char'] ?? null,
            $data['confidence'] ?? 0,
            $data['is_correct'] ?? 0,
            $data['session_type'] ?? 'practice',
            $data['lesson_id'] ?? null,
            $data['hand_used'] ?? null,
            $data['processing_time_ms'] ?? 0,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }
    
    public function getDetectionHistory(int $userId, int $limit = 50, int $offset = 0): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM detection_history 
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getDetectionStats(int $userId, int $days = 30): array {
        $stmt = $this->db->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(is_correct) as correct,
                AVG(confidence) as avg_confidence
             FROM detection_history
             WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>