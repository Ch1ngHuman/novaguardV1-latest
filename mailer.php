<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/vendor/autoload.php";

// Load configuration
$config = require __DIR__ . "/config.php";

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->SMTPAuth = true;

// Uncomment the line below for debugging (remove for production)
// $mail->SMTPDebug = SMTP::DEBUG_SERVER;

$mail->Host = $config['smtp']['host'];
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = $config['smtp']['port'];
$mail->Username = $config['smtp']['username'];
$mail->Password = $config['smtp']['password'];

// Set the from name and email
$mail->setFrom($config['smtp']['username'], 'NovaGuard System', false);
if (!empty($config['smtp']['from_email'])) {
    $mail->addReplyTo($config['smtp']['from_email'], $config['smtp']['from_name']);
}

$mail->isHtml(true);

return $mail;