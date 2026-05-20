<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'vendor/autoload.php';
require_once 'includes/env.php';
require_once 'includes/functions.php';

session_start();

if (isLoggedIn()) {
    redirect(isAdmin() ? 'dashboard_admin.php' : 'dashboard_user.php');
}

$email = '';
$error = null;
$message = null;

function sendUsernameReminderEmail(string $email, string $username): array
{
    $host = env('SMTP_HOST', '');
    $smtpUser = env('SMTP_USER', '');
    $smtpPass = env('SMTP_PASS', '');
    $port = (int) env('SMTP_PORT', '587');
    $fromEmail = env('SMTP_FROM_EMAIL', '');
    $fromName = env('SMTP_FROM_NAME', 'QuizPro');

    if ($host === '' || $smtpUser === '' || $smtpPass === '' || $port <= 0 || $fromEmail === '') {
        return ['ok' => false, 'message' => 'SMTP mail is not configured.'];
    }

    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $mail = null;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $username);
        $mail->isHTML(true);
        $mail->Subject = 'Your QuizPro Username';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; padding: 24px; color: #111827; background: #f8fafc;">
                <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 28px;">
                    <h2 style="margin: 0 0 16px; color: #111827;">QuizPro Username Reminder</h2>
                    <p style="margin: 0 0 14px;">Hello,</p>
                    <p style="margin: 0 0 12px;">The username linked with this email address is:</p>
                    <div style="font-size: 24px; font-weight: bold; color: #4f46e5; margin: 20px 0; padding: 16px; border-radius: 14px; background: #eef2ff; text-align: center;">
                        ' . $safeUsername . '
                    </div>
                    <p style="margin: 0 0 22px;">If you did not request this reminder, you can safely ignore this email.</p>
                    <p style="margin: 0;">- QuizPro Security Team</p>
                </div>
            </div>';
        $mail->AltBody = "Hello,\n\nThe username linked with this email address is: {$username}\n\nIf you did not request this reminder, you can safely ignore this email.\n\n- QuizPro Security Team";
        $mail->send();

        return ['ok' => true, 'message' => 'Username reminder sent successfully.'];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => 'SMTP Mail Error: ' . ($mail instanceof PHPMailer ? $mail->ErrorInfo : $e->getMessage())];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid registered email address.';
    } else {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'No account found with that email address.';
        } else {
            $mailResult = sendUsernameReminderEmail((string) $user['email'], (string) $user['username']);
            if ($mailResult['ok']) {
                $message = 'Your username has been sent to your registered email address.';
            } else {
                $error = $mailResult['message'];
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
    <title>QuizPro - Forgot Username</title>
    <meta name="description" content="Recover your QuizPro username using your registered email address.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/quizPro.png">
    <link rel="apple-touch-icon" href="assets/images/quizPro.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="register-page">
<div class="site-loader" aria-hidden="true">
    <span class="site-loader-mark"><img src="assets/images/quizPro.png" alt=""></span>
    <span class="site-loader-ring"></span>
</div>

<canvas id="particles-canvas"></canvas>
<div id="mouse-glow"></div>

<div class="auth-wrapper">
    <div class="auth-container auth-container-compact">
        <div class="auth-left">
            <div class="brand-area">
                <div class="brand-icon" style="width: 48px; height: 48px; border: 2px solid rgba(255, 255, 255, 0.15);">
                    <img src="assets/images/quizPro.png" alt="QuizPro Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: inherit; padding: 2px;">
                </div>
                <span class="brand-name">QuizPro</span>
            </div>

            <div class="illustration-area">
                <div class="central-graphic"></div>
                <div class="floating-icon"><i class="fas fa-user"></i></div>
                <div class="floating-icon"><i class="fas fa-envelope-open-text"></i></div>
                <div class="floating-icon"><i class="fas fa-id-card"></i></div>
                <div class="floating-icon"><i class="fas fa-shield-halved"></i></div>
                <div class="floating-icon"><i class="fas fa-circle-check"></i></div>
            </div>

            <div class="quote-area">
                <p class="quote-text">
                    <i class="fas fa-quote-left"></i> Recover your username and return to your learning flow. <i class="fas fa-quote-right"></i>
                </p>
            </div>
        </div>

        <div class="auth-right">
            <div class="form-header">
                <h2>Forgot Username</h2>
                <p>Enter your registered email address and we will send your username.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-alert">
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="auth-success-alert">
                    <p><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="forgot_username.php" method="POST" novalidate>
                <div class="form-group floating-label">
                    <input type="email" id="email" name="email" placeholder="Email address" required value="<?php echo htmlspecialchars($email); ?>" autocomplete="email">
                    <i class="fas fa-envelope input-icon"></i>
                    <label for="email">Registered email</label>
                    <div class="validation-msg"></div>
                </div>

                <button type="submit" class="btn-submit" data-ripple>
                    <i class="fas fa-paper-plane"></i> Send Username
                </button>

                <div class="auth-links">
                    <p>Remembered it? <a href="login.php">Back to sign in</a></p>
                    <p><a href="index.php">Back to home</a></p>
                </div>
            </form>

            <div class="auth-footer">
                &copy; <?php echo date('Y'); ?> QuizPro. <a href="#">Privacy</a> &middot; <a href="#">Terms</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/script.js"></script>
</body>
</html>
