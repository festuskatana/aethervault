<?php
class Auth {
    private $db;
    private $user_id;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function validate() {
        $token = getBearerToken();
        if ($token === '') {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT user_id, expires_at 
            FROM sessions 
            WHERE token = ? AND expires_at > NOW()
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $this->user_id = $row['user_id'];
            
            // Extend session if within last 7 days of expiry
            $expires = strtotime($row['expires_at']);
            if ($expires - time() < 7 * 24 * 3600) {
                $this->extendSession($token);
            }
            
            return $this->user_id;
        }
        
        return false;
    }
    
    public function getUserId() {
        return $this->user_id;
    }
    
    public function generateToken($user_id) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $this->db->prepare("
            INSERT INTO sessions (user_id, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $user_id, $token, $expiresAt);
        $stmt->execute();
        
        return $token;
    }
    
    public function extendSession($token) {
        $newExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $this->db->prepare("
            UPDATE sessions 
            SET expires_at = ? 
            WHERE token = ?
        ");
        $stmt->bind_param("ss", $newExpiry, $token);
        $stmt->execute();
    }
    
    public function revokeToken($token) {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE token = ?");
        $stmt->bind_param("s", $token);
        return $stmt->execute();
    }
    
    public function revokeAllUserTokens($user_id) {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>
