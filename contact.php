<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $message = trim($_POST['message']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name) || empty($email) || empty($message)) {
        $errors[] = "Please fill all required fields.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($errors)) {
        // Save contact message
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, mobile, message) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$name, $email, $mobile, $message])) {
            $success = "Thank you for your message! We'll get back to you soon.";
        } else {
            $errors[] = "Failed to send message. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz App - Contact Us</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- <link rel="stylesheet" href="Enhanced.css"> -->
</head>
<body>
    <div class="container">
        <header class="dashboard-header">
            <h1>Contact Us</h1>
            <nav>
                <ul>
                    <li><a href="<?php echo isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php'; ?>">Home</a></li>
                    <li><a href="quiz.php">Quiz</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="create_quiz.php">Create Quiz</a></li>
                    <?php else: ?>
                        <li><a href="join_quiz.php">Join Quiz</a></li>
                    <?php endif; ?>
                    <li><a href="certificates.php">Certificates</a></li>
                    <li><a href="contact.php" class="active">Contact</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/view_leaderboard.php">Leaderboard</a></li>
                    <?php endif; ?>
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
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <p><?php echo $success; ?></p>
                </div>
            <?php endif; ?>
            
            <section class="contact-section">
                <div class="contact-form">
                    <h2>Send us a Message</h2>
                    <form action="contact.php" method="POST">
                        <div class="form-group">
                            <label for="name">Name*</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email*</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mobile">Mobile Number</label>
                            <input type="tel" id="mobile" name="mobile">
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message*</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>
                
                <div class="contact-info">
                    <h2>Contact Information</h2>
                    <div class="info-item">
                        <h3>Email</h3>
                        <p>mpvignesh06@gmail.com</p>
                    </div>
                    <div class="info-item">
                        <h3>Phone</h3>
                        <p>+91 9393211095</p>
                    </div>
                    <div class="info-item">
                        <h3>Address</h3>
                        <p>4-50, bazar street<br>Chinthala Pattadai, Nagari(M), Chittoor(D) <br>Andhra Pradesh.</p>
                    </div>
                </div>
            </section>
        </main>
        
        <footer>
            <p>&copy; 2023 Quiz App. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>