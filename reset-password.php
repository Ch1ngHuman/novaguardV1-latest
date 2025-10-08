<?php
// Get the token from the URL
$token = $_GET["token"] ?? null;

// Hash the token for secure lookup
$token_hash = $token ? hash("sha256", $token) : null;

// Connect to the database (use connect.php, not config.php)
$mysqli = require __DIR__ . "/connect.php";

// Prepare and execute the SQL statement to find the user with this reset token
$sql = "SELECT * FROM users WHERE reset_token_hash = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token_hash);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

// If no user is found, the token is invalid
if ($user === null){
    die("<div style='color:red; font-weight:bold;'>Token not found.</div>");
}

// If the token has expired, show an error
if (strtotime($user["reset_token_expires_at"]) <= time()){
    die("<div style='color:red; font-weight:bold;'>Token has expired.</div>");
}

// Handle form submission
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["new_password"] ?? '';
    $repeat_password = $_POST["repeat_password"] ?? '';

    // Validate passwords
    if (strlen($new_password) < 6) {
        $message = "<div id='errorMessage'>Password must be at least 6 characters.</div>";
    } elseif ($new_password !== $repeat_password) {
        $message = "<div id='errorMessage'>Passwords do not match.</div>";
    } else {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        // Update the user's password and clear the reset token
        $update_sql = "UPDATE users SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?";
        $update_stmt = $mysqli->prepare($update_sql);
        $update_stmt->bind_param("si", $password_hash, $user["id"]);
        if ($update_stmt->execute()) {
            $message = "<div style='color:green; font-weight:bold;'>Password has been reset successfully! Please close this tab and you may now login.</div>";
        } else {
            $message = "<div id='errorMessage'>An error occurred. Please try again.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="styles/style.css">
    <script type="text/javascript">
    // Password eye toggle for both fields
    function togglePassword(id, eyeId) {
        const input = document.getElementById(id);
        const eye = document.getElementById(eyeId);
        if (input.type === "password") {
            input.type = "text";
            eye.innerHTML = `<svg class='eye-icon' xmlns='http://www.w3.org/2000/svg' height='24px' viewBox='0 0 24 24' width='24px'><path d='M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z'/></svg>`;
        } else {
            input.type = "password";
            eye.innerHTML = `<svg class='eye-icon' xmlns='http://www.w3.org/2000/svg' height='24px' viewBox='0 0 24 24' width='24px'><path d='M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z'/><line x1='1' y1='1' x2='23' y2='23' stroke='black' stroke-width='2'/></svg>`;
        }
    }
    </script>
</head>
<body>
    <div>
        <h1>Nova Guard</h1>
        <h3>RESET PASSWORD</h3>
        <p id="errorMessage"></p>
        <?php if ($message) { 
            if (strpos($message, 'successfully') !== false) {
                echo '<div class="notification success">' . $message . '</div>';
            } else {
                echo '<div class="notification error">' . $message . '</div>';
            }
        } ?>
    </div>
    <div class="form-box active" id="reset-form">
        <form id="resetForm" class="globalForm" method="post" autocomplete="off">
            <div class="input-wrapper">
                <label for="new_password">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5-58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm240-200q33 0 56.5-23.5T560-360q0-33-23.5-56.5T480-440q-33 0-56.5 23.5T400-360q0 33 23.5 56.5T480-280ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg>
                </label>
                <input type="password" name="new_password" id="new_password" placeholder="New Password" required minlength="6">
                <span class="eye-toggle" id="eye_new_password" onclick="togglePassword('new_password','eye_new_password')">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
                </span>
            </div>
            <div class="input-wrapper">
                <label for="repeat_password">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5-58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm240-200q33 0 56.5-23.5T560-360q0-33-23.5-56.5T480-440q-33 0-56.5 23.5T400-360q0 33 23.5 56.5T480-280ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg>
                </label>
                <input type="password" name="repeat_password" id="repeat_password" placeholder="Repeat Password" required minlength="6">
                <span class="eye-toggle" id="eye_repeat_password" onclick="togglePassword('repeat_password','eye_repeat_password')">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
                </span>
            </div>
            <button type="submit" class="btn" style="margin-top: 10px;">Send</button>
        </form>
    </div>
</body>
</html>