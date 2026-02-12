<?php
/**
 * AI Assistant Diagnostic Page
 * Helps identify issues with the chatbot
 */

session_start();
$page_title = 'AI Assistant Diagnostics';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="card">
        <div class="card-header">
            <h5>AI Assistant Diagnostics</h5>
        </div>
        <div class="card-body">
            <h6>System Status:</h6>
            <ul>
                <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
                <li><strong>Session Status:</strong>
                    <?php
                    if (isset($_SESSION['user_id'])) {
                        echo '<span class="text-success">Logged in as: ' . htmlspecialchars($_SESSION['user_id']) . '</span>';
                        echo '<br><strong>Role:</strong> ' . (isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'user');
                    } else {
                        echo '<span class="text-danger">Not logged in</span>';
                    }
                    ?>
                </li>
                <li><strong>MySQL Extension:</strong>
                    <?php echo extension_loaded('pdo_mysql') ? '<span class="text-success">Available</span>' : '<span class="text-danger">Not Available</span>'; ?>
                </li>
                <li><strong>cURL Extension:</strong>
                    <?php echo extension_loaded('curl') ? '<span class="text-success">Available</span>' : '<span class="text-danger">Not Available</span>'; ?>
                </li>
                <li><strong>OpenAI API Key:</strong>
                    <?php echo getenv('OPENAI_API_KEY') ? '<span class="text-success">Set</span>' : '<span class="text-warning">Not set (will use fallback)</span>'; ?>
                </li>
            </ul>

            <h6>Database Test:</h6>
            <?php
            try {
                require_once __DIR__ . '/config/db_connect.php';
                $pdo = getDBConnection();
                echo '<p class="text-success">✅ Database connection successful</p>';

                // Check if chatbot table exists
                $result = $pdo->query("SHOW TABLES LIKE 'chatbot_conversations'");
                if ($result->rowCount() > 0) {
                    echo '<p class="text-success">✅ Chatbot conversations table exists</p>';
                } else {
                    echo '<p class="text-warning">⚠ Chatbot conversations table missing</p>';
                    echo '<p><a href="setup_chatbot_web.php" class="btn btn-primary btn-sm">Create Tables</a></p>';
                }
            } catch (Exception $e) {
                echo '<p class="text-danger">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>

            <h6>API Test:</h6>
            <button id="testApiBtn" class="btn btn-primary">Test API Connection</button>
            <div id="apiResult" class="mt-2"></div>

            <h6>Actions:</h6>
            <div class="btn-group">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="chat/user_chat.php" class="btn btn-success">Go to Chat</a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-primary">Login First</a>
                <?php endif; ?>
                <a href="setup_chatbot_web.php" class="btn btn-secondary">Setup Database</a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('testApiBtn').addEventListener('click', async () => {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = 'Testing...';

    try {
        const res = await fetch('api/chatbot_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: 'status_check' })
        });

        const data = await res.json();
        resultDiv.innerHTML = `<pre>Status: ${res.status}\nResponse: ${JSON.stringify(data, null, 2)}</pre>`;

    } catch (e) {
        resultDiv.innerHTML = `<span class="text-danger">Error: ${e.message}</span>`;
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>