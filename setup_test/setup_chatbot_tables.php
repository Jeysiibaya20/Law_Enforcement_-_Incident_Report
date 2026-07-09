<?php
/**
 * Setup Chatbot Conversation History Table
 */

require_once __DIR__ . '/config/db_connect.php';

try {
    // Create chatbot_conversations table
    $sql = "
        CREATE TABLE IF NOT EXISTS `chatbot_conversations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `user_message` TEXT NOT NULL,
            `bot_reply` TEXT NOT NULL,
            `source` VARCHAR(50) DEFAULT 'knowledge_base',
            `language` VARCHAR(10) DEFAULT 'en',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_user_time` (`user_id`, `created_at`),
            INDEX `idx_source` (`source`),
            FOREIGN KEY (`user_id`) REFERENCES `signup`(`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "<div style='color: green; font-weight: bold;'>✅ Chatbot conversations table created successfully!</div>";

    // Create indexes for better performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_created ON chatbot_conversations(user_id, created_at DESC)");
    echo "<div style='color: green;'>✅ Indexes created for optimal performance.</div>";

} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\Law_Enforcement_-_Incident_Report\setup_chatbot_tables.php