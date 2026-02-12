<?php
session_start();
require_once "../config/db_connect.php";
require '../vendor/autoload.php'; // PHPMailer autoload
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;  
$msg = "";

if (isset($_GET["token"])) {
    $token = $_GET["token"];

    $stmt = $conn->prepare("SELECT user_id, expires FROM reset_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $user_id = $row["user_id"];
        $expires = $row["expires"];

        if (strtotime($expires) < time()) {
            $msg = "Token expired.";
        } elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
            $newPass = password_hash($_POST["password"], PASSWORD_DEFAULT);

            $stmt2 = $conn->prepare("UPDATE signup SET password = ? WHERE user_id = ?");
            $stmt2->bind_param("si", $newPass, $user_id);
            $stmt2->execute();

            $msg = "Password updated successfully. <a href='login.php'>Login</a>";
        }
    } else {
        $msg = "Invalid token.";
    }
} else {
    $msg = "No token provided.";
}
?>
<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body>
  <h2>Reset Password</h2>
  <?php if (isset($user_id) && strtotime($expires) >= time()): ?>
    <form method="post">
      <input type="password" name="password" placeholder="Enter new password" required>
      <button type="submit">Reset Password</button>
    </form>
  <?php endif; ?>
  <div><?= $msg ?></div>
</body>
</html>