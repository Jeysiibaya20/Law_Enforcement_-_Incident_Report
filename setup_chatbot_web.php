<?php
/**
 * Web-accessible chatbot tables setup
 * Access this via browser to set up database tables
 */

require_once __DIR__ . '/config/db_connect.php';

echo "<h1>Chatbot Database Setup</h1>";

try {
    $pdo = getDBConnection();

    // Create chatbot_conversations table
    $sql = "
    CREATE TABLE IF NOT EXISTS chatbot_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_message TEXT NOT NULL,
        bot_reply TEXT NOT NULL,
        source ENUM('openai', 'knowledge_base') DEFAULT 'knowledge_base',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_time (user_id, created_at),
        INDEX idx_source (source)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ chatbot_conversations table created successfully!</p>";

    // Check if table exists
    $result = $pdo->query("SHOW TABLES LIKE 'chatbot_conversations'");
    if ($result->rowCount() > 0) {
        echo "<p style='color: green;'>✅ Table verification successful!</p>";
    }

    echo "<p><strong>Setup complete!</strong> You can now use the AI Chat Assistant.</p>";
    echo "<p><a href='chat/user_chat.php'>Go to Chat Interface</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please ensure:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL service is running</li>";
    echo "<li>Database 'law&inci' exists</li>";
    echo "<li>You have proper database permissions</li>";
    echo "</ul>";
}
?>