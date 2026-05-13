<?php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('dashboard_admin.php');
    } else {
        redirect('dashboard_user.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Home</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>
<body>
    <div class="container">
        <header>
            <h1>Welcome to Quiz App</h1>
            <p>Test your knowledge in various subjects</p>
        </header>
        
        <main class="landing-page">
            <div class="cta-buttons">
                <a href="login.php" class="btn">Login</a>
                <a href="register.php" class="btn">Register</a>
            </div>
            
            <div class="features">
                <div class="feature">
                    <h3>Multiple Categories</h3>
                    <p>Choose from various subjects to test your knowledge.</p>
                </div>
                <div class="feature">
                    <h3>Track Progress</h3>
                    <p>View your scores and improve over time.</p>
                </div>
                <div class="feature">
                    <h3>Compete</h3>
                    <p>Join quizzes and compete with others.</p>
                </div>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>