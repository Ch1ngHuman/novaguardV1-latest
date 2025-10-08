<?php

session_start();

$errors = [
    'register' => $_SESSION['register_error']??''
];
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
    <title>signup</title>
    <link rel="stylesheet" href="../styles/style.css">
    <script type="text/javascript">
    // Password eye toggle for signup
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
        <h1 style="margin-top: 50px;">SIGN UP</h1>
        <p id="errorMessage"></p>
        <?php if (!empty($errors['register'])): ?>
            <div class="notification error"><?= $errors['register']; ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="notification success"><?= $success; ?></div>
        <?php endif; ?>
    </div>
    <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
        <form id="form" class="globalForm" method="post" action="login_register.php">
            <div>
                <label for="inputRole"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="black"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></label>
                <select name="user_role" id="inputRole" required>
                    <option value="" disabled selected>Select role</option>
                    <option value="admin">Admin</option>
                    <option value="guard">Guard</option>
                </select>
            </div>
            <div>
                <label for="inputFirstName"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Z"/></svg></label>
                <input type="text" name="firstName" id="inputFirstName" placeholder="First name">
            </div>
            <div class="wrapper">
            <p>Please make sure to use your school provided email</p>
            </div>
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
            <div class="input-wrapper">
                <label for="inputRepeatPassword"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="black"><path d="M240-80q-33 0-56.5-23.5T160-160v-400q0-33 23.5-56.5T240-640h40v-80q0-83 58.5-141.5T480-920q83 0 141.5-58.5T680-720v80h40q33 0 56.5 23.5T800-560v400q0 33-23.5 56.5T720-80H240Zm240-200q33 0 56.5-23.5T560-360q0-33-23.5-56.5T480-440q-33 0-56.5 23.5T400-360q0 33 23.5 56.5T480-280ZM360-640h240v-80q0-50-35-85t-85-35q-50 0-85 35t-35 85v80Z"/></svg></label>
                <input type="password" name="repeatPassword" id="inputRepeatPassword" placeholder="Repeat Password">
                <span class="eye-toggle" id="eye_inputRepeatPassword" onclick="togglePassword('inputRepeatPassword','eye_inputRepeatPassword')">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px"><path d="M12 6a9.77 9.77 0 0 1 8.94 6A9.77 9.77 0 0 1 12 18a9.77 9.77 0 0 1-8.94-6A9.77 9.77 0 0 1 12 6m0-2C6.5 4 2 8.5 2 12s4.5 8 10 8 10-4.5 10-8-4.5-8-10-8zm0 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/></svg>
                </span>
            </div>
            <button type="submit" name="register" value="Sign Up" class="btn">Sign up</button>
        </form>
        <div class="wrapper">
            <p>already have an account? <a href="../index.php">Log in</a></p>
        </div>
    </div>
    <script>
    document.getElementById('form').addEventListener('submit', function(event) {
        const password = document.getElementById('inputPassword').value;
        const repeatPassword = document.getElementById('inputRepeatPassword').value;
        const errorMessage = document.getElementById('errorMessage');
        if (password !== repeatPassword) {
            event.preventDefault();
            errorMessage.textContent = "Passwords do not match!";
            errorMessage.className = "notification error";
        } else {
            errorMessage.textContent = "";
            errorMessage.className = "";
        }
    });
    </script>
</body>
</html>