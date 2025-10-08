<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mailer.php';

// If you need to deploy this online in the future, just update the $reset_link to your real domain.
$email = $_POST["email"];

$token = bin2hex(random_bytes(16));

$token_hash = hash("sha256", $token);

$expiry = date("Y-m-d H:i:s", time() + 60 * 30);

$mysqli = require __DIR__ . "/connect.php";

$sql = "UPDATE users
        SET reset_token_hash = ?,
            reset_token_expires_at = ?
        WHERE email = ?";

$stmt = $mysqli->prepare($sql);

$stmt->bind_param("sss", $token_hash, $expiry, $email);

$stmt->execute();

if ($mysqli->affected_rows) {
    // Check if email is configured
    if (!file_exists(__DIR__ . '/config.php')) {
        $_SESSION['error_message'] = "Email is not configured. Please set up email settings first.";
        header("Location: pages/forgot-password.php");
        exit();
    }

    $config = require __DIR__ . '/config.php';
    
    if (empty($config['smtp']['username']) || empty($config['smtp']['password'])) {
        $_SESSION['error_message'] = "Email credentials not configured. Please complete email setup.";
        header("Location: pages/forgot-password.php");
        exit();
    }

    $mail = require __DIR__ . "/mailer.php";

    $mail->addAddress($email);
    $mail->Subject = "Password Reset";
    
    $reset_link = "http://localhost/NovaGuard/NovaGuard2/reset-password.php?token=$token";
    
    $mail->Body = <<<END
    Click <a href="$reset_link">here</a>
    to reset your password.
    END;

    try {
        $mail->send();
        $_SESSION['success_message'] = "Password reset link has been sent to your email!";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Message could not be sent. Please try again later.";
        header("Location: pages/forgot-password.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Email not found in our records.";
    header("Location: pages/forgot-password.php");
    exit();
}
?>

