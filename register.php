<?php
session_start();
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php');
}

$show_success_overlay = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db.php';
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required.";
    }
    
    if (!empty($email) && !preg_match('/^[^\s@]+@gmail\.com$/i', $email)) {
        $errors[] = "Please provide a valid @gmail.com address.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if (empty($errors)) {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($duplicates) > 0) {
            foreach ($duplicates as $row) {
                if (strcasecmp($row['username'], $username) === 0 && !in_array("Username already exists.", $errors)) {
                    $errors[] = "Username already exists.";
                }
                if (strcasecmp($row['email'], $email) === 0 && !in_array("Email address already exists.", $errors)) {
                    $errors[] = "Email address already exists.";
                }
            }
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hashed_password])) {
                $show_success_overlay = true;
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join QuizPro — Create Your Account</title>
    <meta name="description" content="Register for QuizPro — test your knowledge, compete on leaderboards, and level up your skills with thousands of quizzes.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="register-page">

<canvas id="particles-canvas"></canvas>
<div id="mouse-glow"></div>

<div class="auth-wrapper">
    <div class="auth-container">

        <!-- Left Panel -->
        <div class="auth-left">
            <div class="brand-area">
                <div class="brand-icon"><i class="fas fa-bolt"></i></div>
                <span class="brand-name">QuizApp</span>
            </div>

            <div class="illustration-area">
                <div class="central-graphic"></div>
                <div class="floating-icon"><i class="fas fa-brain"></i></div>
                <div class="floating-icon"><i class="fas fa-trophy"></i></div>
                <div class="floating-icon"><i class="fas fa-question-circle"></i></div>
                <div class="floating-icon"><i class="fas fa-chart-line"></i></div>
                <div class="floating-icon"><i class="fas fa-stopwatch"></i></div>
            </div>

            <div class="quote-area">
                <p class="quote-text">
                    <i class="fas fa-quote-left"></i> Test Your Knowledge. Level Up Your Skills. <i class="fas fa-quote-right"></i>
                </p>
            </div>

            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number" data-count="10">0K+</div>
                    <div class="stat-label">Users</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="50">0K+</div>
                    <div class="stat-label">Quizzes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" data-count="1">0M+</div>
                    <div class="stat-label">Attempts</div>
                </div>
            </div>

            <div class="leaderboard-mini">
                <div class="lb-header"><i class="fas fa-crown"></i> Leaderboard Top</div>
                <div class="lb-entry"><span class="lb-rank gold">1</span><span class="lb-name">AlexByte</span><span class="lb-score">98,450</span></div>
                <div class="lb-entry"><span class="lb-rank silver">2</span><span class="lb-name">QuizMaster</span><span class="lb-score">95,200</span></div>
                <div class="lb-entry"><span class="lb-rank bronze">3</span><span class="lb-name">CodeWizard</span><span class="lb-score">91,780</span></div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="auth-right">
            <div class="form-header">
                <h2>Create Account</h2>
                <p>Join thousands of learners and start your journey.</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="register.php" method="POST" novalidate>
                <div class="form-group floating-label">
                    <input type="text" id="username" name="username" placeholder="Username" required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           autocomplete="username">
                    <i class="fas fa-user input-icon"></i>
                    <label for="username">Username</label>
                    <div class="validation-msg"></div>
                </div>

                <div class="form-group floating-label">
                    <input type="email" id="email" name="email" placeholder="Email address" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           autocomplete="email">
                    <i class="fas fa-envelope input-icon"></i>
                    <label for="email">Email address</label>
                    <div class="validation-msg"></div>
                </div>

                <div class="form-group floating-label">
                    <input type="password" id="password" name="password" placeholder="Password" required
                           autocomplete="new-password">
                    <i class="fas fa-lock input-icon"></i>
                    <label for="password">Password</label>
                    <button type="button" class="password-toggle" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="validation-msg"></div>
                </div>

                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText"></div>

                <div class="form-group floating-label">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm password" required
                           autocomplete="new-password">
                    <i class="fas fa-shield-alt input-icon"></i>
                    <label for="confirm_password">Confirm password</label>
                    <button type="button" class="password-toggle" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="validation-msg"></div>
                </div>

                <div class="form-checkbox">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="#" tabindex="-1">Terms of Service</a> &amp; <a href="#" tabindex="-1">Privacy Policy</a></label>
                </div>

                <button type="submit" class="btn-submit" id="registerBtn" data-ripple>
                    <i class="fas fa-rocket"></i> Create Account
                </button>

                <div class="divider">Or continue with</div>

                <div class="social-row">
                    <a href="#" class="social-btn google" aria-label="Sign up with Google">
                        <i class="fab fa-google"></i> Google
                    </a>
                    <a href="#" class="social-btn github" aria-label="Sign up with GitHub">
                        <i class="fab fa-github"></i> GitHub
                    </a>
                </div>

                <div class="auth-links">
                    <p>Already have an account? <a href="login.php">Sign in</a></p>
                </div>
            </form>

            <div class="auth-footer">
                &copy; 2026 QuizPro. <a href="#">Privacy</a> &middot; <a href="#">Terms</a>
            </div>
        </div>

    </div>
</div>

<?php if (isset($show_success_overlay) && $show_success_overlay): ?>
<div class="success-overlay" id="successOverlay">
    <div class="success-modal">
        <div class="success-icon-wrap">
            <div class="success-icon-check"><i class="fas fa-check"></i></div>
        </div>
        <h2>Welcome to QuizPro!</h2>
        <p>Your account has been created successfully. Start your journey to test your knowledge and level up your skills!</p>
        <button type="button" class="btn-submit" id="goToLoginBtn">
            <i class="fas fa-arrow-right"></i> Go to Login
        </button>
    </div>
</div>
<?php endif; ?>

<script src="assets/js/script.js"></script>
</body>
</html>
