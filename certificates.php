<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Get user's certificates
$stmt = $pdo->prepare("SELECT c.id, q.title, c.downloaded_at, c.certificate_path 
                      FROM certificates c
                      JOIN user_attempts ua ON c.attempt_id = ua.id
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      ORDER BY c.downloaded_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - My Certificates</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
    <style>
        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .certificate-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .certificate-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .certificate-date {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .certificate-preview {
            background-color: #f5f5f5;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            border-radius: 4px;
            overflow: hidden;
        }
        .certificate-preview img {
            max-width: 100%;
            max-height: 100%;
        }
        .no-certificates {
            text-align: center;
            padding: 40px;
            background-color: #f9f9f9;
            border-radius: 8px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>My Certificates</h1>
            <nav>
                <ul>
                    <li><a href="dashboard_user.php">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <li><a href="join_quiz.php">Join Quiz</a></li>
                    <li><a href="certificates.php" class="active">Certificates</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="includes/logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main class="dashboard-content">
            <?php displayMessage(); ?>
            
            <section class="dashboard-section">
                <h2>My Achievements</h2>
                <?php if (empty($certificates)): ?>
                    <div class="no-certificates">
                        <p>You haven't earned any certificates yet.</p>
                        <p>Complete quizzes with a score of 70% or higher to earn certificates.</p>
                        <a href="quiz.php" class="btn">Take a Quiz</a>
                    </div>
                <?php else: ?>
                    <div class="certificate-grid">
                        <?php foreach ($certificates as $cert): ?>
                            <div class="certificate-card">
                                <div class="certificate-title"><?php echo htmlspecialchars($cert['title']); ?></div>
                                <div class="certificate-date">
                                    Earned on <?php echo date('M j, Y', strtotime($cert['downloaded_at'])); ?>
                                </div>
                                <div class="certificate-preview">
                                    <img src="assets/images/certificate_icon.png" alt="Certificate Preview">
                                </div>
                                <a href="<?php echo $cert['certificate_path']; ?>" class="btn" download>Download Certificate</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>



