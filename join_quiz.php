<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isAdmin()) {
    redirect('dashboard_admin.php');
}

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_code = trim($_POST['quiz_code']);
    
    if (empty($quiz_code)) {
        $error = "Please enter a quiz code.";
    } else {
        // Check if quiz exists
        $stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE unique_code = ?");
        $stmt->execute([$quiz_code]);
        $quiz = $stmt->fetch();
        
        if ($quiz) {
            redirect("quiz.php?quiz_id=" . $quiz['id'], "Joining quiz: " . htmlspecialchars($quiz['title']));
        } else {
            $error = "Invalid quiz code. Please check and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Join Quiz</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Join a Quiz</h1>
            <nav>
                <ul>
                    <li><a href="dashboard_user.php">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <li><a href="join_quiz.php" class="active">Join Quiz</a></li>
                    <li><a href="certificates.php">Certificates</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <?php displayMessage(); ?>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            
            <div class="join-quiz-form">
                <h2>Enter Quiz Code</h2>
                <p>Get the quiz code from your instructor or organizer and enter it below to join the quiz.</p>
                
                <form action="join_quiz.php" method="POST">
                    <div class="form-group">
                        <label for="quiz_code">Quiz Code</label>
                        <input type="text" id="quiz_code" name="quiz_code" required>
                    </div>
                    
                    <button type="submit" class="btn">Join Quiz</button>
                </form>
            </div>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>