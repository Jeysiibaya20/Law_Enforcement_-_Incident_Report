<?php
session_start();
// Only allow logged-in non-admin/non-officer users
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : 'user';
if ($role === 'admin' || $role === 'officer') {
    echo "Access denied.";
    exit;
}

$page_title = 'AI Chat';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">AI Chat</h5>
            <small class="text-muted">For user accounts only</small>
        </div>
        <div class="card-body" style="min-height:300px;">
            <div id="chatBox" style="height:320px; overflow:auto; border:1px solid #eee; padding:10px; border-radius:6px; background:#fafafa;"></div>
            <div id="statusMessage" class="mt-2 small text-muted"></div>
        </div>
        <div class="card-footer">
            <form id="chatForm" onsubmit="return false;">
                <div class="input-group">
                    <input id="messageInput" class="form-control" placeholder="Ask the assistant..." autocomplete="off">
                    <button id="sendBtn" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>
    <div class="mt-3 text-muted small">Tip: If OpenAI key is not configured, the assistant will use a basic built-in responder.</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const chatBox = document.getElementById('chatBox');
const msgInput = document.getElementById('messageInput');
const sendBtn = document.getElementById('sendBtn');
const statusMessage = document.getElementById('statusMessage');

function appendMessage(who, text) {
    const el = document.createElement('div');
    el.style.marginBottom = '10px';
    el.innerHTML = `<strong>${who}:</strong> ${text}`;
    chatBox.appendChild(el);
    chatBox.scrollTop = chatBox.scrollHeight;
}

function updateStatus(message, type = 'info') {
    statusMessage.innerHTML = message;
    statusMessage.className = `mt-2 small text-${type}`;
}

// Check authentication on page load
window.addEventListener('load', async () => {
    try {
        const res = await fetch('../api/chatbot_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: 'status_check' })
        });

        if (res.status === 401) {
            updateStatus('❌ Not logged in. Please <a href="../auth/login.php">login</a> first.', 'danger');
            msgInput.disabled = true;
            sendBtn.disabled = true;
            return;
        } else if (res.status === 403) {
            updateStatus('❌ Access denied. This chat is for regular users only.', 'warning');
            msgInput.disabled = true;
            sendBtn.disabled = true;
            return;
        }

        updateStatus('✅ Connected to AI Assistant', 'success');
        appendMessage('Assistant', 'Hello! I\'m your AI assistant. How can I help you with incident reporting today?');

    } catch (e) {
        updateStatus(`❌ Connection failed: ${e.message}`, 'danger');
        msgInput.disabled = true;
        sendBtn.disabled = true;
    }
});

sendBtn.addEventListener('click', async () => {
    const text = msgInput.value.trim();
    if (!text) return;
    appendMessage('You', text);
    msgInput.value = '';

    try {
        const res = await fetch('../api/chatbot_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        });

        if (!res.ok) {
            const errorText = await res.text();
            appendMessage('Assistant', `⚠ Network Error (${res.status}): ${errorText || 'Unknown error'}`);
            return;
        }

        const data = await res.json();
        if (data.reply) {
            appendMessage('Assistant', data.reply);
        } else if (data.error) {
            appendMessage('Assistant', `⚠ Error: ${data.error}`);
        } else {
            appendMessage('Assistant', '⚠ Unexpected response from server');
        }
    } catch (e) {
        appendMessage('Assistant', `⚠ Network error: ${e.message}`);
    }
});

msgInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendBtn.click();
    }
});

</script>
