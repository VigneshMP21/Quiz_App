<?php
session_start();
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

require_once '../includes/db.php';

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Get quiz details
$stmt = $pdo->prepare("SELECT id, title, no_of_questions FROM quizzes WHERE id = ? AND created_by = ?");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    redirect('create_quiz.php', 'Quiz not found or you dont have permission to edit it.');
}

// Get existing questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle question submission
    $action = $_POST['action'];
    
    if ($action === 'add_question') {
        $question_text = trim($_POST['question_text']);
        $option1 = trim($_POST['option1']);
        $option2 = trim($_POST['option2']);
        $option3 = trim($_POST['option3']);
        $option4 = trim($_POST['option4']);
        $correct_option = (int)$_POST['correct_option'];
        $marks = (int)$_POST['marks'];
        
        // Validate
        if (empty($question_text) || empty($option1) || empty($option2) || empty($option3) || empty($option4)) {
            $error = "Please fill all question fields.";
        } elseif ($correct_option < 1 || $correct_option > 4) {
            $error = "Please select a valid correct option.";
        } else {
            // Insert question
            $stmt = $pdo->prepare("INSERT INTO questions 
                                 (quiz_id, question_text, option1, option2, option3, option4, correct_option, marks) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$quiz_id, $question_text, $option1, $option2, $option3, $option4, $correct_option, $marks])) {
                $success = "Question added successfully!";
                
                // Check if quiz is complete
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
                $stmt->execute([$quiz_id]);
                $question_count = $stmt->fetchColumn();
                
                if ($question_count >= $quiz['no_of_questions']) {
                    $quiz_complete = true;
                }
            } else {
                $error = "Failed to add question. Please try again.";
            }
        }
    } elseif ($action === 'delete_question') {
        $question_id = (int)$_POST['question_id'];
        
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ? AND quiz_id = ?");
        if ($stmt->execute([$question_id, $quiz_id])) {
            $success = "Question deleted successfully!";
        } else {
            $error = "Failed to delete question.";
        }
    }
    
    // Refresh questions after modification
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Add Questions</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Add Questions to: <?php echo htmlspecialchars($quiz['title']); ?></h1>
            <nav>
                <ul>
                    <li><a href="../dashboard_admin.php">Home</a></li>
                    <li><a href="../quiz.php">Quiz</a></li>
                    <li><a href="../create_quiz.php">Create Quiz</a></li>
                    <li><a href="../contact.php">Contact</a></li>
                    <li><a href="view_leaderboard.php">Leaderboard</a></li>
                    <li><a href="../includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <?php displayMessage(); ?>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
            
            <div class="quiz-progress">
                <p>Questions added: <?php echo count($questions); ?> / <?php echo $quiz['no_of_questions']; ?></p>
                <div class="progress-bar">
                    <div class="progress" style="width: <?php echo (count($questions) / $quiz['no_of_questions']) * 100; ?>%"></div>
                </div>
            </div>
            
            <?php if (isset($quiz_complete) && $quiz_complete): ?>
                <div class="quiz-complete">
                    <h2>Quiz Complete!</h2>
                    <p>You've added all the questions for this quiz.</p>
                    <a href="../quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn">View Quiz</a>
                    <a href="../dashboard_admin.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            <?php else: ?>
                <section class="add-question-form">
                    <h2>Add New Question</h2>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="add_question">
                        
                        <div class="form-group">
                            <label for="question_text">Question Text*</label>
                            <textarea id="question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="option1">Option 1*</label>
                                <input type="text" id="option1" name="option1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="option2">Option 2*</label>
                                <input type="text" id="option2" name="option2" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="option3">Option 3*</label>
                                <input type="text" id="option3" name="option3" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="option4">Option 4*</label>
                                <input type="text" id="option4" name="option4" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="correct_option">Correct Option*</label>
                                <select id="correct_option" name="correct_option" required>
                                    <option value="1">Option 1</option>
                                    <option value="2">Option 2</option>
                                    <option value="3">Option 3</option>
                                    <option value="4">Option 4</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="marks">Marks*</label>
                                <input type="number" id="marks" name="marks" min="1" value="1" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Add Question</button>
                    </form>
                </section>
            <?php endif; ?>
            
            <?php if (!empty($questions)): ?>
                <section class="question-list">
                    <h2>Existing Questions</h2>
                    
                    <div class="questions">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-card">
                                <div class="question-header">
                                    <h3>Question <?php echo $index + 1; ?></h3>
                                    <form action="" method="POST" class="delete-form">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" class="btn-small btn-danger">Delete</button>
                                    </form>
                                </div>
                                
                                <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                
                                <div class="question-options">
                                    <div class="option <?php echo $question['correct_option'] == 1 ? 'correct' : ''; ?>">
                                        <span>1.</span> <?php echo htmlspecialchars($question['option1']); ?>
                                    </div>
                                    <div class="option <?php echo $question['correct_option'] == 2 ? 'correct' : ''; ?>">
                                        <span>2.</span> <?php echo htmlspecialchars($question['option2']); ?>
                                    </div>
                                    <div class="option <?php echo $question['correct_option'] == 3 ? 'correct' : ''; ?>">
                                        <span>3.</span> <?php echo htmlspecialchars($question['option3']); ?>
                                    </div>
                                    <div class="option <?php echo $question['correct_option'] == 4 ? 'correct' : ''; ?>">
                                        <span>4.</span> <?php echo htmlspecialchars($question['option4']); ?>
                                    </div>
                                </div>
                                
                                <div class="question-meta">
                                    <span>Marks: <?php echo $question['marks']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>