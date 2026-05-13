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

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Support desk</span>
                    <h1 class="app-title">Contact the team behind your quiz workspace</h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                    <div class="app-actions">
                        <a href="#contact-form" class="app-button app-button-primary"><i class="fas fa-paper-plane"></i> Send Message</a>
                        <a href="quiz.php" class="app-button app-button-ghost"><i class="fas fa-layer-group"></i> Return to Quizzes</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Response promise</span>
                        <span class="app-status-pill"><i class="fas fa-sparkles"></i> Active</span>
                    </div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Expected reply</span>
                            <span class="app-hero-mini-static">Within 24 hours</span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Primary channel</span>
                            <span class="app-hero-mini-static">Email support</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Response window</span>
                    <strong class="app-metric-static">24h</strong>
                    <p>Standard turnaround for routine support queries.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Office channel</span>
                    <strong class="app-metric-static">Email + Phone</strong>
                    <p>Use the method that best matches the urgency of the issue.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Coverage</span>
                    <strong class="app-metric-static">Product + Access</strong>
                    <p>Account, quiz flow, certificates, and platform feedback.</p>
                </article>
            </section>

            <div class="app-grid app-contact-grid">
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

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Best practice</span>
                                <h2 class="app-panel-title">What to include</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Mention the quiz title or certificate involved.</li>
                            <li><i class="fas fa-check-circle"></i> Describe the issue and what you already tried.</li>
                            <li><i class="fas fa-check-circle"></i> Add contact details if a follow-up call helps.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">FAQ</span>
                                <h2 class="app-panel-title">Quick guidance</h2>
                            </div>
                        </div>
                        <div class="app-faq-list">
                            <article class="app-faq-item">
                                <strong>Missing certificate?</strong>
                                <p>Certificates unlock only after a 70%+ score and generation from the result flow.</p>
                            </article>
                            <article class="app-faq-item">
                                <strong>Cannot join a quiz?</strong>
                                <p>Double-check the code and make sure it matches an active published quiz.</p>
                            </article>
                            <article class="app-faq-item">
                                <strong>Need admin help?</strong>
                                <p>Use this form for platform feedback, content issues, and operational escalations.</p>
                            </article>
                        </div>
                    </section>
                </aside>
            </div>
<?php include 'includes/footer.php'; ?>
