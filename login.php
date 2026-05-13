<?php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php', 'Login successful!');
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>
<body>
    <div class="container">
        <header>
            <h1>Login to Quiz App</h1>
        </header>
        
        <main class="auth-form">
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <?php displayMessage(); ?>
            
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="auth-links">
                <a href="forgot_password.php">Forgot Password?</a>
                <span>|</span>
                <a href="register.php">Create an Account</a>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>