<?php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';
    
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            // In a real app, you would send a password reset email here
            $message = "Password reset instructions have been sent to your email (simulated in this demo).";
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Forgot Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>
<body>
    <div class="container">
        <header>
            <h1>Forgot Password</h1>
        </header>
        
        <main class="auth-form">
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <?php if (isset($message)) echo "<div class='alert alert-success'>$message</div>"; ?>
            
            <form action="forgot_password.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <button type="submit" class="btn">Reset Password</button>
            </form>
            
            <div class="auth-links">
                <a href="login.php">Back to Login</a>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>