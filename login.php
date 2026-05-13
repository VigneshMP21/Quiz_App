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

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
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
    <title>QuizPro - Sign In</title>
    <meta name="description" content="Sign in to QuizPro and continue your learning journey.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="register-page">

<canvas id="particles-canvas"></canvas>
<div id="mouse-glow"></div>

<div class="auth-wrapper">
    <div class="auth-container">

        <div class="auth-left">
            <div class="brand-area">
                <div class="brand-icon"><i class="fas fa-bolt"></i></div>
                <span class="brand-name">QuizPro</span>
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

        <div class="auth-right">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to continue your learning journey.</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-alert">
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php
            ob_start();
            displayMessage();
            $flashContent = ob_get_clean();
            if ($flashContent):
            ?>
                <div class="flash-wrapper"><?php echo $flashContent; ?></div>
            <?php endif; ?>

            <form class="auth-form" action="login.php" method="POST" novalidate>
                <div class="form-group floating-label">
                    <input type="text" id="username" name="username" placeholder="Username" required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           autocomplete="username">
                    <i class="fas fa-user input-icon"></i>
                    <label for="username">Username</label>
                    <div class="validation-msg"></div>
                </div>

                <div class="form-group floating-label">
                    <input type="password" id="password" name="password" placeholder="Password" required
                           autocomplete="current-password">
                    <i class="fas fa-lock input-icon"></i>
                    <label for="password">Password</label>
                    <button type="button" class="password-toggle" tabindex="-1" aria-label="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                    <div class="validation-msg"></div>
                </div>

                <div class="form-options">
                    <div class="form-checkbox">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-submit" id="loginBtn" data-ripple>
                    <i class="fas fa-arrow-right"></i> Sign In
                </button>

                <div class="divider">Or continue with</div>

                <div class="social-row">
                    <a href="#" class="social-btn google" aria-label="Sign in with Google">
                        <i class="fab fa-google"></i> Google
                    </a>
                    <a href="#" class="social-btn github" aria-label="Sign in with GitHub">
                        <i class="fab fa-github"></i> GitHub
                    </a>
                </div>

                <div class="auth-links">
                    <p>New here? <a href="register.php">Create an account</a></p>
                </div>
            </form>

            <div class="auth-footer">
                &copy; 2026 QuizPro. <a href="#">Privacy</a> &middot; <a href="#">Terms</a>
            </div>
        </div>

    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>
