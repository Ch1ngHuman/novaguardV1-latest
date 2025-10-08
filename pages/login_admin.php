<?php

session_start();

$errors = [
    'login' => $_SESSION['login_error']??'',
    'register' => $_SESSION['register_error']??''
];
$success = $_SESSION['register_success']??'';
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset();

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" :'';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>login</title>
    <link rel="stylesheet" href="../styles/style.css">    
    <script type="text/javascript">
    // Password eye toggle for login
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
<body class="login-page">
    <div class="login-container">
        <div class="login-content">
            <h1>LOG IN</h1>
            <p id="errorMessage"></p>
            <?php if (!empty($errors['login'])): ?>
                <div class="notification error"><?= $errors['login']; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="notification success"><?= $success; ?></div>
            <?php endif; ?>

            <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form id="form" class="globalForm" action="./login_register.php" method="post">
                <input type="hidden" name="user_role" value="admin">
                <div>
                    <label for="inputEmail">
                        <span>@</span>
                    </label>
                    <input type="email" name="email" id="inputEmail" placeholder="Email">
                </div>
                <div class="input-wrapper">
                <label for="inputPassword"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5 58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm240-200q33 0 56.5-23.5T560-360q0-33-23.5-56.5T480-440q-33 0-56.5 23.5T400-360q0 33 23.5 56.5T480-280ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg></label>
                <input type="password" name="password" id="inputPassword" placeholder="Password">
                <span class="eye-toggle" id="eye_inputPassword" onclick="togglePassword('inputPassword','eye_inputPassword')">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
                </span>
            </div>
            <button type="submit" name="login">log in</button>
        </form>
            <div class="wrapper">
                <p><a href="../index.php">Change User</a></p>
                <p>Don't have an account? <a href="signup.php">Create Account</a></p>
                <p>forgot password? <a href="forgot-password.php">Reset Password</a></p>
            </div>
            </div>
        </div>
    </div>
</body>
</html>