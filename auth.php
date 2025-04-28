<?php
session_start();
require_once 'config/db.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare SQL to prevent SQL injection
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'faculty':
                header('Location: faculty/dashboard.php');
                break;
            case 'staff':
                header('Location: staff/dashboard.php');
                break;
            case 'student':
                header('Location: student/dashboard.php');
                break;
            default:
                $_SESSION['error'] = "Invalid user role";
                header('Location: index.php');
        }
        exit();
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header('Location: index.php');
        exit();
    }
}

// If someone tries to access auth.php directly
header('Location: index.php');
exit();
?>