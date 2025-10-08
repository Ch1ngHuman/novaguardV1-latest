<?php

session_start();
require_once '../connect.php';

if(isset($_POST['register'])) {
    $name = $_POST['firstName'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['user_role'];

    // Use prepared statements to prevent SQL injection
    $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['register_error'] = 'Email is already registered!';
        $_SESSION['active_form'] = 'register';
        header("Location: signup.php");
        exit();
    } else {
        // Use prepared statement for INSERT
        $insertUser = $conn->prepare("INSERT INTO users(name, email, password, user_role) VALUES (?, ?, ?, ?)");
        $insertUser->bind_param("ssss", $name, $email, $password, $role);
        
        if ($insertUser->execute()) {
            $_SESSION['register_success'] = 'Registration successful! Please log in.';
            if ($role === 'admin') {
                header("Location: login_admin.php");
            } else {
                header("Location: login_guard.php");
            }
            exit();
        } else {
            $_SESSION['register_error'] = 'Registration failed: ' . $conn->error;
        }
    }

    header("Location: ../index.php");
    exit();
}

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['user_role'];
    
    // Validate role selection
    if (empty($role)) {
        $_SESSION['login_error'] = 'Please select a role (Guard or Admin)';
        $_SESSION['active_form'] = 'login';
        header("Location: ../index.php");
        exit();
    }
    
    // Use prepared statement for login with role check
    $loginQuery = $conn->prepare("SELECT * FROM users WHERE email = ? AND user_role = ?");
    $loginQuery->bind_param("ss", $email, $role);
    $loginQuery->execute();
    $result = $loginQuery->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['user_role'];
            $_SESSION['user_id'] = $user['id'];
            
            // Redirect based on role
            if ($user['user_role'] === 'admin') {
                header("Location: admin-dashboard.php");
            } else {
                $_SESSION['role'] = $user['user_role'];
                header("Location: guard-dashboard.php");
            }
            exit();
        } else {
            $_SESSION['login_error'] = 'Incorrect email or password';
        }
    } else {
        $_SESSION['login_error'] = 'Incorrect email, password, or user selection';
    }

    $_SESSION['active_form'] = 'login';
    header("Location: login_" . $role . ".php");
    exit();
}

?>