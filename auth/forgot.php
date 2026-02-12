<?php
session_start();
require_once "../config/db_connect.php";
require '../vendor/autoload.php'; // PHPMailer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);

    if ($email === "") {
        $msg = "Please enter your email.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM signup WHERE emailadd = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $user_id = $row["user_id"];

            // Generate token
            $token = bin2hex(random_bytes(16));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Create reset_tokens table if not exists
            $conn->query("CREATE TABLE IF NOT EXISTS reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires DATETIME NOT NULL
            )");

            // Save token
            $stmt2 = $conn->prepare("INSERT INTO reset_tokens (user_id, token, expires) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $user_id, $token, $expires);
            $stmt2->execute();

            // Reset link
            $resetLink = "http://localhost/Law_Enforcement_-_Incident_Report/auth/token.php?token=$token";

            // Send email via Gmail SMTP
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'alertaraqc@gmail.com';
    $mail->Password   = 'fyyzywptnqlqemyt'; // Your App Password from .env
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SMTPS for port 465
    $mail->Port       = 465; // Gmail SSL port

    $mail->setFrom('alertaraqc@gmail.com', 'Alertara PH');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Request';
    $mail->Body    = "
        Click the link below to reset your password:<br><br>
        <a href='$resetLink'>$resetLink</a>
    ";

    $mail->send();
    $msg = "Password reset link has been sent to your email.";
} catch (Exception $e) {
    $msg = "Mailer Error: " . $mail->ErrorInfo;
    error_log("PHPMailer Error in forgot.php: " . $mail->ErrorInfo);
}
        } else {
            $msg = "No account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - Alertara PH</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: linear-gradient(135deg, rgba(76, 138, 137, 0.8) 0%, rgba(58, 80, 107, 0.7) 50%, rgba(28, 37, 65, 0.8) 100%);
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .forgot-container {
      background-color: rgba(0, 0, 0, 0.6);
      padding: 30px;
      border-radius: 10px;
      width: 350px;
      text-align: center;
    }
    .forgot-container h2 { margin-bottom: 20px; }
    .forgot-container input[type="email"] {
      width: 100%; padding: 10px; margin: 10px 0;
      border: none; border-radius: 5px;
    }
    .forgot-container button {
      background-color: #4c8a89; color: white; border: none;
      padding: 10px; width: 100%; border-radius: 5px; cursor: pointer;
    }
    .forgot-container a {
      color: #ccc; text-decoration: none; display: block; margin-top: 15px;
    }
    .message { margin-top: 10px; color: yellow; min-height: 20px; }
  </style>
</head>
<body>
  <div class="forgot-container">
    <h2><i class="bi bi-shield-lock"></i> Forgot Password</h2>
    <form method="post">
      <input type="email" name="email" placeholder="Enter your registered email" required>
      <button type="submit">Send Reset Link</button>
    </form>
    <div class="message"><?= htmlspecialchars($msg) ?></div>
    <a href="../auth/login.php"><i class="bi bi-arrow-left-circle"></i> Back to Login</a>
  </div>
</body>
</html>