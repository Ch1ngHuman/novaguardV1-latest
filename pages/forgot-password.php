<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>forgot password</title>
    <link rel="stylesheet" href="../styles/style.css">
    <script type="text/javascript" src="../scripts/script.js" defer></script>
    <script type="text/javascript">
        // Inline script to handle error message for forgot password
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("form");
            const input_email = document.getElementById("inputEmail");
            const error_Message = document.getElementById("errorMessage");

            form.addEventListener("submit", function(e) {
                let errors = [];
                if (input_email.value === "" || input_email.value == null) {
                    errors.push("Email is required");
                    input_email.parentElement.classList.add("incorrect");
                }
                if (errors.length > 0) {
                    e.preventDefault();
                    error_Message.innerText = errors.join(". ");
                }
            });

            input_email.addEventListener("input", function() {
                if (input_email.parentElement.classList.contains("incorrect")) {
                    input_email.parentElement.classList.remove("incorrect");
                    error_Message.innerText = "";
                }
            });
        });
    </script>
</head>
<body>
    <div>      
        <h1>Nova Guard</h1>
        <h3>forgot password</h3>
        <div class="wrapper">
            <p>Note: If you don't see the email in your inbox, please check your spam or junk folder.</p>
        </div>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background-color: #ff4757; color: white; padding: 15px; margin: 10px; border-radius: 5px; text-align: center;">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']); 
                ?>
            </div>
        <?php endif; ?>
        <p id="errorMessage"></p>
    </div>
    <div class="form-box">
        <form id="form" class="globalForm" method="post" action="../send-password-reset.php">
            <div>
                <label for="inputEmail">
                    <span>@</span>
                </label>
                <input type="email" name="email" id="inputEmail" placeholder="Email">
            </div>
            <button type="submit">Send</button>
        </form>
        <div class="wrapper_cancel">
            <p><a href="../index.php">Cancel</a></p>
        </div>
    </div>
</body>
</html>