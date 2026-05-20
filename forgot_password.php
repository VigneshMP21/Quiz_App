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

const RESET_OTP_TTL = 300;
const RESET_RESEND_SECONDS = 60;
const RESET_MAX_VERIFY_ATTEMPTS = 5;

if (empty($_SESSION['forgot_csrf_token'])) {
    $_SESSION['forgot_csrf_token'] = bin2hex(random_bytes(32));
}

function forgotJsonResponse(bool $ok, string $message, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $message,
    ], $payload));
    exit;
}

function forgotRequestData(): array
{
    $rawInput = file_get_contents('php://input');
    $data = json_decode((string) $rawInput, true);

    if (is_array($data)) {
        return $data;
    }

    return $_POST;
}

function forgotValidateCsrf(array $data): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');

    if (!is_string($token) || !hash_equals($_SESSION['forgot_csrf_token'] ?? '', $token)) {
        forgotJsonResponse(false, 'Security token expired. Refresh the page and try again.', [], 419);
    }
}

function forgotClearSession(): void
{
    unset(
        $_SESSION['forgot_reset_user_id'],
        $_SESSION['forgot_reset_email'],
        $_SESSION['forgot_reset_username'],
        $_SESSION['forgot_otp_hash'],
        $_SESSION['forgot_otp_expires_at'],
        $_SESSION['forgot_otp_last_sent_at'],
        $_SESSION['forgot_otp_verify_attempts'],
        $_SESSION['forgot_otp_verified']
    );
}

function forgotPasswordIsStrong(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[a-z]/', $password)
        && preg_match('/[A-Z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function forgotSendOtpEmail(string $email, string $username, string $otp): array
{
    $host = env('SMTP_HOST', '');
    $smtpUser = env('SMTP_USER', '');
    $smtpPass = env('SMTP_PASS', '');
    $port = (int) env('SMTP_PORT', '587');
    $fromEmail = env('SMTP_FROM_EMAIL', '');
    $fromName = env('SMTP_FROM_NAME', 'QuizPro');

    if ($host === '' || $smtpUser === '' || $smtpPass === '' || $port <= 0 || $fromEmail === '') {
        return [
            'ok' => false,
            'message' => 'SMTP mail is not configured. Add SMTP host, user, app password, port, and sender email to the .env file.',
        ];
    }

    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

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
        $mail->Subject = 'QuizPro Password Reset OTP';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; padding: 24px; color: #111827; background: #f8fafc;">
                <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 28px;">
                    <h2 style="margin: 0 0 16px; color: #111827;">QuizPro OTP Verification</h2>
                    <p style="margin: 0 0 14px;">Hello ' . $safeUsername . ',</p>
                    <p style="margin: 0 0 12px;">Your OTP code is:</p>
                    <div style="font-size: 34px; font-weight: bold; letter-spacing: 8px; color: #4f46e5; margin: 22px 0; text-align: center;">
                        ' . $safeOtp . '
                    </div>
                    <p style="margin: 0 0 14px;">This OTP expires in 5 minutes.</p>
                    <p style="margin: 0 0 22px;">If you did not request this password reset, please ignore this email.</p>
                    <p style="margin: 0;">- QuizPro Security Team</p>
                </div>
            </div>';
        $mail->AltBody = "Hello {$username},\n\nYour QuizPro OTP code is: {$otp}\n\nThis OTP expires in 5 minutes.\n\nIf you did not request this password reset, please ignore this email.\n\n- QuizPro Security Team";
        $mail->send();

        return ['ok' => true, 'message' => 'OTP sent successfully.'];
    } catch (Exception $e) {
        return [
            'ok' => false,
            'message' => 'SMTP Mail Error: ' . ($mail instanceof PHPMailer ? $mail->ErrorInfo : $e->getMessage()),
        ];
    }
}

function forgotSendPasswordChangedEmail(string $email, string $username): array
{
    $host = env('SMTP_HOST', '');
    $smtpUser = env('SMTP_USER', '');
    $smtpPass = env('SMTP_PASS', '');
    $port = (int) env('SMTP_PORT', '587');
    $fromEmail = env('SMTP_FROM_EMAIL', '');
    $fromName = env('SMTP_FROM_NAME', 'QuizPro');

    if ($host === '' || $smtpUser === '' || $smtpPass === '' || $port <= 0 || $fromEmail === '') {
        return [
            'ok' => false,
            'message' => 'SMTP mail is not configured.',
        ];
    }

    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $changedAt = date('Y-m-d H:i:s');
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
        $mail->Subject = 'QuizPro Password Changed Successfully';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; padding: 24px; color: #111827; background: #f8fafc;">
                <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 28px;">
                    <h2 style="margin: 0 0 16px; color: #111827;">Password Changed Successfully</h2>
                    <p style="margin: 0 0 14px;">Hello ' . $safeUsername . ',</p>
                    <p style="margin: 0 0 14px;">Your QuizPro account password was changed successfully.</p>
                    <div style="margin: 20px 0; padding: 16px; border-radius: 14px; background: #eef2ff; color: #312e81;">
                        <strong>Changed at:</strong> ' . htmlspecialchars($changedAt, ENT_QUOTES, 'UTF-8') . '
                    </div>
                    <p style="margin: 0 0 22px;">If you did not make this change, contact QuizPro support immediately.</p>
                    <p style="margin: 0;">- QuizPro Security Team</p>
                </div>
            </div>';
        $mail->AltBody = "Hello {$username},\n\nYour QuizPro account password was changed successfully at {$changedAt}.\n\nIf you did not make this change, contact QuizPro support immediately.\n\n- QuizPro Security Team";
        $mail->send();

        return ['ok' => true, 'message' => 'Password change confirmation sent.'];
    } catch (Exception $e) {
        return [
            'ok' => false,
            'message' => 'SMTP Mail Error: ' . ($mail instanceof PHPMailer ? $mail->ErrorInfo : $e->getMessage()),
        ];
    }
}

function forgotHandleSendOtp(PDO $pdo, array $data): void
{
    forgotValidateCsrf($data);

    $email = strtolower(trim((string) ($data['email'] ?? '')));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        forgotJsonResponse(false, 'Enter a valid registered email address.', [], 422);
    }

    $lastSentAt = (int) ($_SESSION['forgot_otp_last_sent_at'] ?? 0);
    $remaining = RESET_RESEND_SECONDS - (time() - $lastSentAt);

    if ($lastSentAt > 0 && $remaining > 0) {
        forgotJsonResponse(false, 'Please wait before requesting another OTP.', [
            'cooldown' => $remaining,
        ], 429);
    }

    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        forgotClearSession();
        forgotJsonResponse(false, 'No account found with that email address.', [], 404);
    }

    $otp = (string) random_int(100000, 999999);
    $mailResult = forgotSendOtpEmail($email, (string) $user['username'], $otp);

    if (!$mailResult['ok']) {
        forgotJsonResponse(false, $mailResult['message'], [], 502);
    }

    $_SESSION['forgot_reset_user_id'] = (int) $user['id'];
    $_SESSION['forgot_reset_email'] = (string) $user['email'];
    $_SESSION['forgot_reset_username'] = (string) $user['username'];
    $_SESSION['forgot_otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
    $_SESSION['forgot_otp_expires_at'] = time() + RESET_OTP_TTL;
    $_SESSION['forgot_otp_last_sent_at'] = time();
    $_SESSION['forgot_otp_verify_attempts'] = 0;
    $_SESSION['forgot_otp_verified'] = false;

    forgotJsonResponse(true, 'OTP sent to your registered email.', [
        'cooldown' => RESET_RESEND_SECONDS,
        'expiresIn' => RESET_OTP_TTL,
        'maskedEmail' => preg_replace('/(^.).*(@.*$)/', '$1***$2', $email),
    ]);
}

function forgotHandleVerifyOtp(array $data): void
{
    forgotValidateCsrf($data);

    $otp = preg_replace('/\D/', '', (string) ($data['otp'] ?? ''));

    if (strlen($otp) !== 6) {
        forgotJsonResponse(false, 'Enter the complete 6-digit OTP.', [], 422);
    }

    if (empty($_SESSION['forgot_otp_hash']) || empty($_SESSION['forgot_reset_user_id'])) {
        forgotJsonResponse(false, 'Request a new OTP to continue.', [], 409);
    }

    if (time() > (int) ($_SESSION['forgot_otp_expires_at'] ?? 0)) {
        unset($_SESSION['forgot_otp_hash']);
        forgotJsonResponse(false, 'OTP expired. Request a new OTP.', [
            'expired' => true,
        ], 410);
    }

    $attempts = (int) ($_SESSION['forgot_otp_verify_attempts'] ?? 0);

    if ($attempts >= RESET_MAX_VERIFY_ATTEMPTS) {
        forgotClearSession();
        forgotJsonResponse(false, 'Too many invalid attempts. Request a new OTP.', [], 429);
    }

    if (!password_verify($otp, (string) $_SESSION['forgot_otp_hash'])) {
        $_SESSION['forgot_otp_verify_attempts'] = $attempts + 1;
        forgotJsonResponse(false, 'Invalid OTP. Check the code and try again.', [
            'attemptsLeft' => max(0, RESET_MAX_VERIFY_ATTEMPTS - (int) $_SESSION['forgot_otp_verify_attempts']),
        ], 422);
    }

    $_SESSION['forgot_otp_verified'] = true;
    forgotJsonResponse(true, 'OTP verified successfully.');
}

function forgotHandleResetPassword(PDO $pdo, array $data): void
{
    forgotValidateCsrf($data);

    if (empty($_SESSION['forgot_otp_verified']) || empty($_SESSION['forgot_reset_user_id'])) {
        forgotJsonResponse(false, 'Verify your OTP before changing password.', [], 403);
    }

    if (time() > (int) ($_SESSION['forgot_otp_expires_at'] ?? 0)) {
        forgotClearSession();
        forgotJsonResponse(false, 'Reset session expired. Request a new OTP.', [], 410);
    }

    $password = (string) ($data['password'] ?? '');
    $confirmPassword = (string) ($data['confirm_password'] ?? '');

    if (!forgotPasswordIsStrong($password)) {
        forgotJsonResponse(false, 'Password must include uppercase, lowercase, number, special character, and 8+ characters.', [], 422);
    }

    if (!hash_equals($password, $confirmPassword)) {
        forgotJsonResponse(false, 'Passwords do not match.', [], 422);
    }

    $resetEmail = (string) ($_SESSION['forgot_reset_email'] ?? '');
    $resetUsername = (string) ($_SESSION['forgot_reset_username'] ?? 'QuizPro learner');
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$passwordHash, (int) $_SESSION['forgot_reset_user_id']]);

    $confirmationSent = false;
    if ($resetEmail !== '') {
        $confirmationResult = forgotSendPasswordChangedEmail($resetEmail, $resetUsername);
        $confirmationSent = (bool) ($confirmationResult['ok'] ?? false);
    }

    forgotClearSession();
    unset($_SESSION['forgot_csrf_token']);

    forgotJsonResponse(true, 'Password changed successfully. Redirecting to login...', [
        'redirect' => 'login.php',
        'confirmationEmailSent' => $confirmationSent,
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_GET['action'])) {
    $data = forgotRequestData();
    $action = (string) $_GET['action'];

    if ($action === 'send_otp') {
        forgotHandleSendOtp($pdo, $data);
    }

    if ($action === 'verify_otp') {
        forgotHandleVerifyOtp($data);
    }

    if ($action === 'reset_password') {
        forgotHandleResetPassword($pdo, $data);
    }

    forgotJsonResponse(false, 'Invalid reset action.', [], 400);
}

$csrfToken = $_SESSION['forgot_csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuizPro - Reset Password</title>
    <meta name="description" content="Reset your QuizPro account password with secure email OTP verification.">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/quizPro.png">
    <link rel="apple-touch-icon" href="assets/images/quizPro.png">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="register-page forgot-otp-page">
<div class="site-loader" aria-hidden="true">
    <span class="site-loader-mark"><img src="assets/images/quizPro.png" alt=""></span>
    <span class="site-loader-ring"></span>
</div>

<canvas id="particles-canvas"></canvas>
<div id="mouse-glow"></div>

<div class="auth-toast-stack" id="authToastStack" aria-live="polite" aria-atomic="true"></div>

<div class="auth-wrapper">
    <div class="auth-container auth-container-compact forgot-otp-container">
        <div class="auth-left">
            <div class="brand-area">
                <div class="brand-icon" style="width: 48px; height: 48px; border: 2px solid rgba(255, 255, 255, 0.15);">
                    <img src="assets/images/quizPro.png" alt="QuizPro Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: inherit; padding: 2px;">
                </div>
                <span class="brand-name">QuizPro</span>
            </div>

            <div class="illustration-area">
                <div class="central-graphic"></div>
                <div class="floating-icon"><i class="fas fa-key"></i></div>
                <div class="floating-icon"><i class="fas fa-shield-halved"></i></div>
                <div class="floating-icon"><i class="fas fa-envelope-open-text"></i></div>
                <div class="floating-icon"><i class="fas fa-lock"></i></div>
                <div class="floating-icon"><i class="fas fa-circle-check"></i></div>
            </div>

            <div class="quote-area">
                <p class="quote-text">
                    <i class="fas fa-quote-left"></i> Secure your account and continue your quiz progress. <i class="fas fa-quote-right"></i>
                </p>
            </div>

            <div class="forgot-security-card">
                <i class="fas fa-user-shield"></i>
                <div>
                    <strong>Protected reset flow</strong>
                    <span>Email validation, expiring OTP, attempt limits, and hashed password update.</span>
                </div>
            </div>
        </div>

        <div class="auth-right forgot-auth-right">
            <div class="form-header">
                <h2>Reset Password</h2>
                <p>Verify your registered email, enter the OTP, then create a stronger password.</p>
            </div>

            <div class="forgot-stepper" aria-label="Password reset progress">
                <div class="forgot-step active" data-step-indicator="email">
                    <span>1</span>
                    <strong>Email</strong>
                </div>
                <div class="forgot-step" data-step-indicator="otp">
                    <span>2</span>
                    <strong>OTP</strong>
                </div>
                <div class="forgot-step" data-step-indicator="password">
                    <span>3</span>
                    <strong>Password</strong>
                </div>
            </div>

            <section class="forgot-panel active" id="emailPanel" data-panel="email">
                <form class="auth-form" id="emailForm" novalidate>
                    <div class="form-group floating-label">
                        <input type="email" id="email" name="email" placeholder="Email address" required autocomplete="email">
                        <i class="fas fa-envelope input-icon"></i>
                        <label for="email">Registered email</label>
                        <div class="validation-msg" id="emailMsg"></div>
                    </div>

                    <button type="submit" class="btn-submit" id="sendOtpBtn" data-ripple>
                        <span class="btn-loader" aria-hidden="true"></span>
                        <i class="fas fa-paper-plane"></i>
                        <span class="btn-label">Send OTP</span>
                    </button>
                </form>
            </section>

            <section class="forgot-panel" id="otpPanel" data-panel="otp" aria-hidden="true">
                <div class="otp-copy">
                    <strong>Enter verification code</strong>
                    <span>We sent a 6-digit OTP to <b id="maskedEmail">your email</b>.</span>
                </div>

                <form class="auth-form" id="otpForm" novalidate>
                    <div class="otp-input-grid" role="group" aria-label="6 digit OTP">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" inputmode="numeric" maxlength="1" class="otp-box" aria-label="OTP digit <?php echo $i; ?>" autocomplete="one-time-code">
                        <?php endfor; ?>
                    </div>
                    <div class="forgot-inline-row">
                        <span class="otp-expiry" id="otpExpiry">Expires in 05:00</span>
                        <button type="button" class="forgot-link-button" id="resendOtpBtn" disabled>Resend in 60s</button>
                    </div>
                    <div class="validation-msg otp-error" id="otpMsg"></div>

                    <button type="submit" class="btn-submit" id="verifyOtpBtn" data-ripple>
                        <span class="btn-loader" aria-hidden="true"></span>
                        <i class="fas fa-shield-halved"></i>
                        <span class="btn-label">Verify OTP</span>
                    </button>
                </form>
            </section>

            <section class="forgot-panel" id="passwordPanel" data-panel="password" aria-hidden="true">
                <div class="otp-copy success-copy">
                    <strong><i class="fas fa-circle-check"></i> OTP verified</strong>
                    <span>Create a new password that meets all security requirements.</span>
                </div>

                <form class="auth-form" id="passwordForm" novalidate>
                    <div class="form-group floating-label">
                        <input type="password" id="newPassword" name="password" placeholder="New password" required autocomplete="new-password">
                        <i class="fas fa-lock input-icon"></i>
                        <label for="newPassword">New password</label>
                        <button type="button" class="password-toggle" data-toggle-password="newPassword" tabindex="-1" aria-label="Toggle new password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="validation-msg" id="passwordMsg"></div>
                    </div>

                    <div class="forgot-strength-meter" aria-hidden="true">
                        <span id="strengthBar"></span>
                    </div>
                    <div class="forgot-password-rules" id="passwordRules">
                        <span data-rule="length"><i class="fas fa-circle"></i> 8+ characters</span>
                        <span data-rule="upper"><i class="fas fa-circle"></i> Uppercase</span>
                        <span data-rule="lower"><i class="fas fa-circle"></i> Lowercase</span>
                        <span data-rule="number"><i class="fas fa-circle"></i> Number</span>
                        <span data-rule="special"><i class="fas fa-circle"></i> Special</span>
                    </div>

                    <div class="form-group floating-label">
                        <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm password" required autocomplete="new-password">
                        <i class="fas fa-shield-alt input-icon"></i>
                        <label for="confirmPassword">Confirm password</label>
                        <button type="button" class="password-toggle" data-toggle-password="confirmPassword" tabindex="-1" aria-label="Toggle confirm password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="validation-msg" id="confirmMsg"></div>
                    </div>

                    <button type="submit" class="btn-submit" id="resetPasswordBtn" data-ripple disabled>
                        <span class="btn-loader" aria-hidden="true"></span>
                        <i class="fas fa-rotate"></i>
                        <span class="btn-label">Change Password</span>
                    </button>
                </form>
            </section>

            <div class="auth-links forgot-bottom-link">
                <p>Remembered your password? <a href="login.php">Back to sign in</a></p>
            </div>

            <div class="auth-footer">
                &copy; <?php echo date('Y'); ?> QuizPro. <a href="#">Privacy</a> &middot; <a href="#">Terms</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/script.js"></script>
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const panels = {
        email: document.getElementById('emailPanel'),
        otp: document.getElementById('otpPanel'),
        password: document.getElementById('passwordPanel')
    };
    const indicators = document.querySelectorAll('[data-step-indicator]');
    const toastStack = document.getElementById('authToastStack');
    const emailForm = document.getElementById('emailForm');
    const otpForm = document.getElementById('otpForm');
    const passwordForm = document.getElementById('passwordForm');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    const otpBoxes = Array.from(document.querySelectorAll('.otp-box'));
    const otpExpiry = document.getElementById('otpExpiry');
    const emailInput = document.getElementById('email');
    const maskedEmail = document.getElementById('maskedEmail');
    const emailMsg = document.getElementById('emailMsg');
    const otpMsg = document.getElementById('otpMsg');
    const passwordInput = document.getElementById('newPassword');
    const confirmInput = document.getElementById('confirmPassword');
    const passwordMsg = document.getElementById('passwordMsg');
    const confirmMsg = document.getElementById('confirmMsg');
    const strengthBar = document.getElementById('strengthBar');
    const ruleEls = document.querySelectorAll('[data-rule]');

    let cooldownTimer = null;
    let expiryTimer = null;
    let currentEmail = '';

    const showToast = (message, type = 'success') => {
        const toast = document.createElement('div');
        toast.className = `auth-toast ${type}`;
        toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i><span>${message}</span>`;
        toastStack.appendChild(toast);
        window.setTimeout(() => toast.classList.add('show'), 20);
        window.setTimeout(() => {
            toast.classList.remove('show');
            window.setTimeout(() => toast.remove(), 300);
        }, 4200);
    };

    const setButtonLoading = (button, loading) => {
        button.disabled = loading;
        button.classList.toggle('loading', loading);
    };

    const apiRequest = async (action, payload) => {
        const response = await fetch(`forgot_password.php?action=${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        });
        const result = await response.json().catch(() => ({ ok: false, message: 'Unexpected server response.' }));

        if (!response.ok || !result.ok) {
            const error = new Error(result.message || 'Request failed.');
            error.payload = result;
            throw error;
        }

        return result;
    };

    const setStep = (step) => {
        Object.entries(panels).forEach(([name, panel]) => {
            const active = name === step;
            panel.classList.toggle('active', active);
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });

        const order = ['email', 'otp', 'password'];
        const currentIndex = order.indexOf(step);
        indicators.forEach((indicator) => {
            const stepName = indicator.dataset.stepIndicator;
            const index = order.indexOf(stepName);
            indicator.classList.toggle('active', index === currentIndex);
            indicator.classList.toggle('complete', index < currentIndex);
        });
    };

    const startCooldown = (seconds) => {
        clearInterval(cooldownTimer);
        let remaining = Number(seconds) || 60;
        resendOtpBtn.disabled = true;
        resendOtpBtn.textContent = `Resend in ${remaining}s`;

        cooldownTimer = window.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(cooldownTimer);
                resendOtpBtn.disabled = false;
                resendOtpBtn.textContent = 'Resend OTP';
                return;
            }
            resendOtpBtn.textContent = `Resend in ${remaining}s`;
        }, 1000);
    };

    const startExpiry = (seconds) => {
        clearInterval(expiryTimer);
        let remaining = Number(seconds) || 300;

        const render = () => {
            const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
            const secs = String(remaining % 60).padStart(2, '0');
            otpExpiry.textContent = remaining > 0 ? `Expires in ${minutes}:${secs}` : 'OTP expired';
            otpExpiry.classList.toggle('expired', remaining <= 0);
        };

        render();
        expiryTimer = window.setInterval(() => {
            remaining -= 1;
            render();
            if (remaining <= 0) {
                clearInterval(expiryTimer);
                showToast('OTP expired. Request a new code.', 'error');
            }
        }, 1000);
    };

    const sendOtp = async (email) => {
        setButtonLoading(sendOtpBtn, true);
        emailMsg.textContent = '';

        try {
            const result = await apiRequest('send_otp', { email });
            currentEmail = email;
            maskedEmail.textContent = result.maskedEmail || email;
            showToast(result.message, 'success');
            startCooldown(result.cooldown || 60);
            startExpiry(result.expiresIn || 300);
            setStep('otp');
            otpBoxes.forEach((box) => box.value = '');
            window.setTimeout(() => otpBoxes[0].focus(), 260);
        } catch (error) {
            emailMsg.textContent = error.message;
            showToast(error.message, 'error');
            if (error.payload && error.payload.cooldown) {
                startCooldown(error.payload.cooldown);
            }
        } finally {
            setButtonLoading(sendOtpBtn, false);
        }
    };

    emailForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const email = emailInput.value.trim().toLowerCase();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            emailMsg.textContent = 'Enter a valid registered email address.';
            showToast('Enter a valid registered email address.', 'error');
            return;
        }
        await sendOtp(email);
    });

    resendOtpBtn.addEventListener('click', async () => {
        if (!currentEmail) {
            setStep('email');
            return;
        }
        await sendOtp(currentEmail);
    });

    otpBoxes.forEach((box, index) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(0, 1);
            otpMsg.textContent = '';
            if (box.value && otpBoxes[index + 1]) {
                otpBoxes[index + 1].focus();
            }
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !box.value && otpBoxes[index - 1]) {
                otpBoxes[index - 1].focus();
            }
            if (event.key === 'ArrowLeft' && otpBoxes[index - 1]) {
                otpBoxes[index - 1].focus();
            }
            if (event.key === 'ArrowRight' && otpBoxes[index + 1]) {
                otpBoxes[index + 1].focus();
            }
        });

        box.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((digit, digitIndex) => {
                if (otpBoxes[digitIndex]) {
                    otpBoxes[digitIndex].value = digit;
                }
            });
            const next = otpBoxes[Math.min(pasted.length, 5)];
            if (next) {
                next.focus();
            }
        });
    });

    otpForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const otp = otpBoxes.map((box) => box.value).join('');

        if (otp.length !== 6) {
            otpMsg.textContent = 'Enter the complete 6-digit OTP.';
            showToast('Enter the complete 6-digit OTP.', 'error');
            return;
        }

        setButtonLoading(verifyOtpBtn, true);

        try {
            const result = await apiRequest('verify_otp', { otp });
            clearInterval(expiryTimer);
            showToast(result.message, 'success');
            otpForm.classList.add('verified-pulse');
            window.setTimeout(() => {
                setStep('password');
                passwordInput.focus();
            }, 550);
        } catch (error) {
            otpMsg.textContent = error.message;
            showToast(error.message, 'error');
        } finally {
            setButtonLoading(verifyOtpBtn, false);
        }
    });

    const passwordRules = (password) => ({
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /\d/.test(password),
        special: /[^A-Za-z0-9]/.test(password)
    });

    const validatePasswords = () => {
        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;
        const rules = passwordRules(password);
        const passed = Object.values(rules).filter(Boolean).length;

        ruleEls.forEach((ruleEl) => {
            const valid = rules[ruleEl.dataset.rule];
            ruleEl.classList.toggle('valid', valid);
            ruleEl.querySelector('i').className = `fas ${valid ? 'fa-circle-check' : 'fa-circle'}`;
        });

        strengthBar.style.width = `${(passed / 5) * 100}%`;
        strengthBar.dataset.level = passed < 3 ? 'weak' : passed < 5 ? 'medium' : 'strong';
        passwordMsg.textContent = password && passed < 5 ? 'Use a stronger password before continuing.' : '';
        confirmMsg.textContent = confirmPassword && password !== confirmPassword ? 'Passwords do not match.' : '';
        resetPasswordBtn.disabled = !(passed === 5 && password === confirmPassword && confirmPassword.length > 0);
    };

    passwordInput.addEventListener('input', validatePasswords);
    confirmInput.addEventListener('input', validatePasswords);

    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.querySelector('i').className = `fas ${show ? 'fa-eye-slash' : 'fa-eye'}`;
        });
    });

    passwordForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        validatePasswords();

        if (resetPasswordBtn.disabled) {
            showToast('Complete all password requirements first.', 'error');
            return;
        }

        setButtonLoading(resetPasswordBtn, true);

        try {
            const result = await apiRequest('reset_password', {
                password: passwordInput.value,
                confirm_password: confirmInput.value
            });
            showToast(result.message, 'success');
            passwordForm.classList.add('verified-pulse');
            window.setTimeout(() => {
                window.location.href = result.redirect || 'login.php';
            }, 2400);
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            setButtonLoading(resetPasswordBtn, false);
        }
    });
})();
</script>
</body>
</html>
