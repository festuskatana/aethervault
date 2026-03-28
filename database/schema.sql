-- =====================================================
-- Aether Vault Database Schema
-- Secure Private Media & Messaging Platform
-- =====================================================

-- Drop database if exists (uncomment for fresh install)
-- DROP DATABASE IF EXISTS aether_vault;

-- Create database
CREATE DATABASE IF NOT EXISTS aether_vault;
USE aether_vault;

-- =====================================================
-- USERS TABLE
-- Stores user account information
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(500) NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    bio TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    reset_token VARCHAR(255) NULL,
    reset_token_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    last_active TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_active (is_active),
    INDEX idx_last_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SESSIONS TABLE
-- Manages user authentication sessions
-- =====================================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    device_info TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- APP SETTINGS TABLE
-- Stores branding and SMTP configuration
-- =====================================================
CREATE TABLE IF NOT EXISTS app_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- PASSWORD RESET OTPS TABLE
-- Stores OTP requests for forgot-password flow
-- =====================================================
CREATE TABLE IF NOT EXISTS password_reset_otps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    email VARCHAR(120) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MEDIA TABLE
-- Stores uploaded media files (images and videos)
-- =====================================================
CREATE TABLE IF NOT EXISTS media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('image', 'video') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT NOT NULL,
    width INT NULL,
    height INT NULL,
    duration INT NULL,
    thumbnail_path VARCHAR(500) NULL,
    is_public BOOLEAN DEFAULT FALSE,
    description TEXT NULL,
    views INT DEFAULT 0,
    downloads INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (file_type),
    INDEX idx_created (created_at),
    INDEX idx_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MESSAGES TABLE
-- Stores private messages between users
-- =====================================================
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    is_delivered BOOLEAN DEFAULT FALSE,
    delivered_at TIMESTAMP NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    deleted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation (sender_id, receiver_id, created_at),
    INDEX idx_unread (receiver_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ACTIVITY LOGS TABLE
-- Tracks user actions for security and analytics
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CONTACTS TABLE
-- Manages user contacts/friends list
-- =====================================================
CREATE TABLE IF NOT EXISTS contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    contact_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
    nickname VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_contact (user_id, contact_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- NOTIFICATIONS TABLE
-- Stores push notifications for users
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_unread (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MEDIA SHARES TABLE
-- Tracks when media is shared between users
-- =====================================================
CREATE TABLE IF NOT EXISTS media_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    media_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with INT NOT NULL,
    permission ENUM('view', 'download', 'edit') DEFAULT 'view',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_media (media_id),
    INDEX idx_user (shared_with)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- USER SETTINGS TABLE
-- Stores user preferences and settings
-- =====================================================
CREATE TABLE IF NOT EXISTS user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    theme VARCHAR(20) DEFAULT 'light',
    notifications_enabled BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255) NULL,
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'Africa/Nairobi',
    privacy_level ENUM('public', 'contacts', 'private') DEFAULT 'contacts',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CHAT ROOMS TABLE (For Group Chat - Future Feature)
-- =====================================================
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    is_private BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- CHAT ROOM MEMBERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS chat_room_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member (room_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- REPORTED CONTENT TABLE
-- For reporting inappropriate content
-- =====================================================
CREATE TABLE IF NOT EXISTS reported_content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reported_by INT NOT NULL,
    content_type ENUM('message', 'media', 'user') NOT NULL,
    content_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    details TEXT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_type (content_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- DEFAULT DATA
-- =====================================================

-- Intentionally empty for deployment-ready installs.

-- =====================================================
-- CREATE VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for unread message count
CREATE OR REPLACE VIEW v_unread_counts AS
SELECT 
    receiver_id as user_id,
    sender_id,
    COUNT(*) as unread_count,
    MAX(created_at) as last_message_at
FROM messages 
WHERE is_read = FALSE AND is_deleted = FALSE
GROUP BY receiver_id, sender_id;

-- View for conversation list
CREATE OR REPLACE VIEW v_conversations AS
SELECT 
    u.id as user_id,
    u.username,
    u.email,
    (
        SELECT message 
        FROM messages 
        WHERE (sender_id = u.id AND receiver_id = cu.id) 
           OR (sender_id = cu.id AND receiver_id = u.id)
        ORDER BY created_at DESC 
        LIMIT 1
    ) as last_message,
    (
        SELECT created_at 
        FROM messages 
        WHERE (sender_id = u.id AND receiver_id = cu.id) 
           OR (sender_id = cu.id AND receiver_id = u.id)
        ORDER BY created_at DESC 
        LIMIT 1
    ) as last_message_time,
    (
        SELECT COUNT(*) 
        FROM messages 
        WHERE sender_id = u.id 
          AND receiver_id = cu.id 
          AND is_read = FALSE
    ) as unread_count
FROM users u
CROSS JOIN (
    SELECT id as user_id 
    FROM users 
    WHERE id = @current_user_id
) cu
WHERE u.id != cu.user_id;

-- =====================================================
-- CREATE STORED PROCEDURES
-- =====================================================

DELIMITER //

-- Procedure to cleanup expired sessions
CREATE PROCEDURE cleanup_expired_sessions()
BEGIN
    DELETE FROM sessions WHERE expires_at < NOW();
END//

-- Procedure to get conversation between two users
CREATE PROCEDURE get_conversation(
    IN p_user1_id INT,
    IN p_user2_id INT,
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT 
        m.*,
        u1.username as sender_name,
        u2.username as receiver_name
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.id
    JOIN users u2 ON m.receiver_id = u2.id
    WHERE (sender_id = p_user1_id AND receiver_id = p_user2_id) 
       OR (sender_id = p_user2_id AND receiver_id = p_user1_id)
    ORDER BY created_at DESC
    LIMIT p_limit OFFSET p_offset;
END//

-- Procedure to mark messages as read
CREATE PROCEDURE mark_messages_read(
    IN p_user_id INT,
    IN p_sender_id INT
)
BEGIN
    UPDATE messages 
    SET is_read = TRUE, read_at = NOW()
    WHERE receiver_id = p_user_id 
      AND sender_id = p_sender_id 
      AND is_read = FALSE;
END//

-- Procedure to get user statistics
CREATE PROCEDURE get_user_stats(IN p_user_id INT)
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM media WHERE user_id = p_user_id) as total_media,
        (SELECT SUM(file_size) FROM media WHERE user_id = p_user_id) as total_storage,
        (SELECT COUNT(*) FROM messages WHERE sender_id = p_user_id) as messages_sent,
        (SELECT COUNT(*) FROM messages WHERE receiver_id = p_user_id) as messages_received,
        (SELECT COUNT(*) FROM contacts WHERE user_id = p_user_id AND status = 'accepted') as total_contacts,
        (SELECT COUNT(*) FROM activity_logs WHERE user_id = p_user_id AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as activity_last_30_days;
END//

DELIMITER ;

-- =====================================================
-- CREATE TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger to update last_active when user logs in
CREATE TRIGGER update_last_active_on_login
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.last_login != OLD.last_login THEN
        UPDATE users SET last_active = NOW() WHERE id = NEW.id;
    END IF;
END//

-- Trigger to log message sent
CREATE TRIGGER log_message_sent
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, details, created_at)
    VALUES (NEW.sender_id, 'send_message', CONCAT('Sent message to user ', NEW.receiver_id), NOW());
END//

-- Trigger to log media upload
CREATE TRIGGER log_media_upload
AFTER INSERT ON media
FOR EACH ROW
BEGIN
    INSERT INTO activity_logs (user_id, action, details, created_at)
    VALUES (NEW.user_id, 'upload_media', CONCAT('Uploaded ', NEW.file_type, ': ', NEW.original_filename), NOW());
END//

DELIMITER ;

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================

-- Additional indexes for better query performance
CREATE INDEX idx_messages_composite ON messages(sender_id, receiver_id, created_at);
CREATE INDEX idx_media_user_composite ON media(user_id, created_at);
CREATE INDEX idx_activity_user_composite ON activity_logs(user_id, created_at);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_sessions_user ON sessions(user_id, expires_at);

-- =====================================================
-- END OF SCHEMA
-- =====================================================

-- Display success message
SELECT 'Database schema created successfully!' as status;
