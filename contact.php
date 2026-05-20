<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'vendor/autoload.php';
require_once 'includes/env.php';
session_start();
require_once 'includes/functions.php';

require_once 'includes/db.php';

const CONTACT_RECIPIENT_EMAIL = 'mpvignesh2107@gmail.com';

$formData = [
    'name' => '',
    'email' => '',
    'mobile' => '',
    'message' => ''
];
$errors = [];
$success = null;

function sendContactDetailsEmail(array $formData): array
{
    $host = env('SMTP_HOST', '');
    $smtpUser = env('SMTP_USER', '');
    $smtpPass = env('SMTP_PASS', '');
    $port = (int) env('SMTP_PORT', '587');
    $fromEmail = env('SMTP_FROM_EMAIL', '') ?: $smtpUser;
    $fromName = env('SMTP_FROM_NAME', 'QuizPro');

    if ($host === '' || $smtpUser === '' || $smtpPass === '' || $port <= 0 || $fromEmail === '') {
        return ['ok' => false, 'message' => 'SMTP mail is not configured.'];
    }

    $safeName = htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8');
    $safeMobile = htmlspecialchars($formData['mobile'] ?: 'Not provided', ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($formData['message'], ENT_QUOTES, 'UTF-8'));
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
        $mail->addAddress(CONTACT_RECIPIENT_EMAIL, 'QuizPro Support');
        $mail->addReplyTo($formData['email'], $formData['name']);
        $mail->isHTML(true);
        $mail->Subject = 'New QuizPro Contact Message';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; padding: 24px; color: #111827; background: #f8fafc;">
                <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 28px;">
                    <h2 style="margin: 0 0 18px; color: #111827;">New QuizPro Contact Message</h2>
                    <p><strong>Name:</strong> ' . $safeName . '</p>
                    <p><strong>Email:</strong> ' . $safeEmail . '</p>
                    <p><strong>Mobile:</strong> ' . $safeMobile . '</p>
                    <div style="margin-top: 20px; padding: 16px; border-radius: 14px; background: #eef2ff;">
                        <strong>Message:</strong><br>
                        <div style="margin-top: 10px; line-height: 1.6;">' . $safeMessage . '</div>
                    </div>
                </div>
            </div>';
        $mail->AltBody = "New QuizPro Contact Message\n\nName: {$formData['name']}\nEmail: {$formData['email']}\nMobile: " . ($formData['mobile'] ?: 'Not provided') . "\n\nMessage:\n{$formData['message']}";
        $mail->send();

        return ['ok' => true, 'message' => 'Message sent successfully.'];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => 'SMTP Mail Error: ' . ($mail instanceof PHPMailer ? $mail->ErrorInfo : $e->getMessage())];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['mobile'] = trim($_POST['mobile'] ?? '');
    $formData['message'] = trim($_POST['message'] ?? '');
    
    if ($formData['name'] === '' || $formData['email'] === '' || $formData['message'] === '') {
        $errors[] = 'Please fill all required fields.';
    }
    
    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($errors)) {
        $mailResult = sendContactDetailsEmail($formData);
        
        if ($mailResult['ok']) {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, mobile, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$formData['name'], $formData['email'], $formData['mobile'], $formData['message']]);
            $success = "Thank you for your message. We'll get back to you soon.";
            // Trigger Admin Notification
            addNotification($pdo, null, "New Support Message", "Support message from " . $formData['name'] . " (" . $formData['email'] . "): " . (strlen($formData['message']) > 80 ? substr($formData['message'], 0, 80) . "..." : $formData['message']), "message_sent", "contact.php");
            $formData = [
                'name' => '',
                'email' => '',
                'mobile' => '',
                'message' => ''
            ];
        } else {
            $errors[] = $mailResult['message'];
        }
    }

    if (!isLoggedIn()) {
        if (empty($errors)) {
            redirect('index.php', 'Your message has been sent successfully.');
        }

        redirect('index.php', implode(' ', $errors), 'error');
    }
}

if (!isLoggedIn()) {
    redirect('login.php');
}

$isAdminView = isAdmin();
$homeLink = $isAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$logoutLink = 'logout.php';

$heroSummary = $isAdminView
    ? 'Use this workspace for platform feedback, escalation notes, and collaboration requests that need attention.'
    : 'Reach the QuizPro team for support, feedback, or help with anything in your learning flow.';

$pageTitle = 'QuizPro - Contact';
$pageKey = 'contact';
$pageBodyClass = 'page-contact';
$headerContext = $isAdminView ? 'Support control' : 'Support flow';
$pageFooterSummary = 'Fast support for access issues, quiz flow questions, certificates, and platform feedback.';
$headAssets = '<link rel="stylesheet" href="assets/css/mobile_view.css">';

include 'includes/header.php';
?>
            <?php displayMessage(); ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>



            <div class="app-grid app-contact-grid" style="margin-top: 24px;">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Write to us</span>
                            <h2 class="app-panel-title">Send a focused message</h2>
                        </div>
                    </div>
                    <p class="app-panel-text">Clear details help us solve access issues, certificate requests, or content questions faster.</p>

                    <form action="contact.php" method="POST" class="app-form-grid" id="contact-form">
                        <div class="app-field-row">
                            <div class="app-field">
                                <label for="name" class="app-label">Name*</label>
                                <input type="text" id="name" name="name" class="app-input" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                            </div>
                            <div class="app-field">
                                <label for="email" class="app-label">Email*</label>
                                <input type="email" id="email" name="email" class="app-input" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                        </div>

                        <div class="app-field">
                            <label for="mobile" class="app-label">Mobile Number</label>
                            <input type="tel" id="mobile" name="mobile" class="app-input" value="<?php echo htmlspecialchars($formData['mobile']); ?>">
                        </div>

                        <div class="app-field">
                            <label for="message" class="app-label">Message*</label>
                            <textarea id="message" name="message" rows="7" class="app-textarea" required><?php echo htmlspecialchars($formData['message']); ?></textarea>
                        </div>

                        <div class="app-actions">
                            <button type="submit" class="app-button app-button-primary" data-loading-submit>
                                <span class="submit-loader" aria-hidden="true"></span>
                                <i class="fas fa-paper-plane"></i>
                                <span>Send Message</span>
                            </button>
                            <a href="<?php echo $homeLink; ?>" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Return</a>
                        </div>
                    </form>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Direct channels</span>
                                <h2 class="app-panel-title">Contact information</h2>
                            </div>
                        </div>
                        <div class="app-info-stack">
                            <article class="app-info-card">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <strong>Email</strong>
                                    <p>mpvignesh2107@gmail.com</p>
                                </div>
                            </article>
                            <article class="app-info-card">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <strong>Phone</strong>
                                    <p>+91 9393211095</p>
                                </div>
                            </article>
                            <article class="app-info-card">
                                <i class="fas fa-location-dot"></i>
                                <div>
                                    <strong>Address</strong>
                                    <p>4-50, Bazar Street, Chinthala Pattadai, Nagari, Chittoor, Andhra Pradesh.</p>
                                </div>
                            </article>
                        </div>
                    </section>
                </aside>
            </div>
<?php include 'includes/footer.php'; ?>
