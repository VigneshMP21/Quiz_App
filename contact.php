<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db.php';

$isAdminView = isAdmin();
$homeLink = $isAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$logoutLink = 'logout.php';
$formData = [
    'name' => '',
    'email' => '',
    'mobile' => '',
    'message' => ''
];
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, mobile, message) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$formData['name'], $formData['email'], $formData['mobile'], $formData['message']])) {
            $success = "Thank you for your message. We'll get back to you soon.";
            $formData = [
                'name' => '',
                'email' => '',
                'mobile' => '',
                'message' => ''
            ];
        } else {
            $errors[] = 'Failed to send message. Please try again.';
        }
    }
}

$heroSummary = $isAdminView
    ? 'Use this workspace for platform feedback, escalation notes, and collaboration requests that need attention.'
    : 'Reach the QuizPro team for support, feedback, or help with anything in your learning flow.';

$pageTitle = 'QuizPro - Contact';
$pageKey = 'contact';
$pageBodyClass = 'page-contact';
$headerContext = $isAdminView ? 'Support control' : 'Support flow';
$pageFooterSummary = 'Fast support for access issues, quiz flow questions, certificates, and platform feedback.';

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
                            <button type="submit" class="app-button app-button-primary"><i class="fas fa-paper-plane"></i> Send Message</button>
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
                                    <p>mpvignesh06@gmail.com</p>
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
