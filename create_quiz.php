<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$categories = getQuizCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $no_of_questions = (int)$_POST['no_of_questions'];
    $total_marks = (int)$_POST['total_marks'];
    $timer_minutes = (int)$_POST['timer_minutes'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($title) || empty($category) || $no_of_questions <= 0 || $total_marks <= 0) {
        $errors[] = "Please fill all required fields with valid values.";
    }
    
    if (empty($errors)) {
        // Generate unique code
        $unique_code = generateUniqueCode();
        
        // Create quiz
        $stmt = $pdo->prepare("INSERT INTO quizzes (title, description, category, no_of_questions, total_marks, timer_minutes, unique_code, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $description, $category, $no_of_questions, $total_marks, $timer_minutes, $unique_code, $_SESSION['user_id']])) {
            $quiz_id = $pdo->lastInsertId();
            redirect("admin/add_questions.php?quiz_id=$quiz_id", "Quiz created successfully! Now add questions.");
        } else {
            $errors[] = "Failed to create quiz. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Create Quiz</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Create New Quiz</h1>
            <nav>
                <ul>
                    <li><a href="dashboard_admin.php">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <li><a href="create_quiz.php" class="active">Create Quiz</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="admin/view_leaderboard.php">Leaderboard</a></li>
                    <li><a href="includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <?php displayMessage(); ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <form action="create_quiz.php" method="POST" class="quiz-form">
                <div class="form-group">
                    <label for="title">Quiz Title*</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="category">Category*</label>
                    <select id="category" name="category" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="no_of_questions">Number of Questions*</label>
                        <input type="number" id="no_of_questions" name="no_of_questions" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="total_marks">Total Marks*</label>
                        <input type="number" id="total_marks" name="total_marks" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="timer_minutes">Timer (minutes)</label>
                        <input type="number" id="timer_minutes" name="timer_minutes" min="1" value="10">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Create Quiz</button>
                    <a href="dashboard_admin.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>