<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$isAdminView = isAdmin();
$homeLink = $isAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$logoutLink = 'logout.php';

$stmt = $pdo->prepare("SELECT c.id, q.title, q.category, c.downloaded_at, c.certificate_path 
                      FROM certificates c
                      JOIN user_attempts ua ON c.attempt_id = ua.id
                      JOIN quizzes q ON ua.quiz_id = q.id
                      WHERE ua.user_id = ?
                      ORDER BY c.downloaded_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll();

$totalCertificates = count($certificates);
$latestEarnedDate = $certificates[0]['downloaded_at'] ?? null;
$latestEarnedLabel = $latestEarnedDate ? date('M j, Y', strtotime($latestEarnedDate)) : 'No certificate yet';
$categoryCoverage = count(array_unique(array_filter(array_map(static function ($certificate) {
    return $certificate['category'] ?? null;
}, $certificates))));
$earnedThisMonth = 0;
$currentMonth = date('Y-m');

foreach ($certificates as $certificate) {
    if (strpos((string) $certificate['downloaded_at'], $currentMonth) === 0) {
        $earnedThisMonth++;
    }
}

$heroSummary = $totalCertificates > 0
    ? 'Your certificates capture the strongest quiz finishes in one polished archive. Download, revisit, and keep the momentum visible.'
    : 'Complete quizzes with a score of 70% or higher to start building your verified achievement archive.';
 
$pageTitle = 'QuizPro - Certificates';
$pageKey = 'certificates';
$pageBodyClass = 'page-certificates';
$headerContext = $isAdminView ? 'Control suite' : 'Achievement flow';
$pageFooterSummary = 'A curated archive of earned certificates and verified progress milestones.';

include 'includes/header.php';
?>
            <?php displayMessage(); ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Achievement archive</span>
                    <h1 class="app-title">Certificates that validate real progress</h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                    <div class="app-actions">
                        <a href="quiz.php" class="app-button app-button-primary"><i class="fas fa-play"></i> Explore Quizzes</a>
                        <a href="contact.php" class="app-button app-button-ghost"><i class="fas fa-headset"></i> Need Help</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Certificate pulse</span>
                        <span class="app-status-pill"><i class="fas fa-award"></i> Verified</span>
                    </div>
                    <div class="app-hero-panel-copy">
                        <strong>Latest milestone</strong>
                        <p><?php echo $latestEarnedLabel; ?></p>
                    </div>
                    <div class="app-hero-stack">
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">This month</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $earnedThisMonth; ?>">0</span>
                        </div>
                        <div class="app-hero-mini-card">
                            <span class="app-hero-mini-label">Categories covered</span>
                            <span class="app-hero-mini-value app-metric-value" data-count="<?php echo $categoryCoverage; ?>">0</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Total certificates</span>
                    <strong class="app-metric-value" data-count="<?php echo $totalCertificates; ?>">0</strong>
                    <p>All certificates issued to your account.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Latest earned</span>
                    <strong class="app-metric-static"><?php echo htmlspecialchars($latestEarnedLabel); ?></strong>
                    <p>Your most recent recorded certificate date.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Categories won</span>
                    <strong class="app-metric-value" data-count="<?php echo $categoryCoverage; ?>">0</strong>
                    <p>Unique learning tracks where you reached the threshold.</p>
                </article>
            </section>

            <div class="app-grid app-grid-certificates">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Collection</span>
                            <h2 class="app-panel-title">My certificate library</h2>
                        </div>
                        <span class="app-status-pill"><?php echo $totalCertificates; ?> total</span>
                    </div>

                    <p class="app-panel-text">Each card below represents a completed quiz where your score met the certificate threshold. Download copies any time.</p>

                    <?php if (empty($certificates)): ?>
                        <div class="app-empty-state">
                            <div class="app-empty-icon"><i class="fas fa-certificate"></i></div>
                            <h3>No certificates yet</h3>
                            <p>Finish quizzes at 70% or above to unlock your first achievement card.</p>
                            <a href="quiz.php" class="app-button app-button-primary"><i class="fas fa-bolt"></i> Take a Quiz</a>
                        </div>
                    <?php else: ?>
                        <div class="app-cert-grid">
                            <?php foreach ($certificates as $certificate): ?>
                                <article class="app-cert-card">
                                    <div class="app-cert-card-top">
                                        <span class="app-cert-badge"><i class="fas fa-award"></i> Achievement</span>
                                        <span class="app-cert-date"><?php echo date('M j, Y', strtotime($certificate['downloaded_at'])); ?></span>
                                    </div>
                                    <div class="app-cert-icon"><i class="fas fa-scroll"></i></div>
                                    <h3><?php echo htmlspecialchars($certificate['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($certificate['category']); ?> track</p>
                                    <div class="app-cert-actions">
                                        <a href="<?php echo htmlspecialchars($certificate['certificate_path']); ?>" class="app-button app-button-primary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <a href="quiz.php" class="app-button app-button-ghost">
                                            <i class="fas fa-layer-group"></i> More Quizzes
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Guideline</span>
                                <h2 class="app-panel-title">How certificates unlock</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Score at least 70% on a completed quiz.</li>
                            <li><i class="fas fa-check-circle"></i> Open the certificate action from the result or dashboard.</li>
                            <li><i class="fas fa-check-circle"></i> Download and keep a local copy whenever you need it.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Next move</span>
                                <h2 class="app-panel-title">Keep the streak alive</h2>
                            </div>
                        </div>
                        <p class="app-panel-text">Push into a new category, improve your best score, or repeat a skill area to build a stronger certificate timeline.</p>
                        <div class="app-sidebar-actions">
                            <a href="quiz.php" class="app-button app-button-primary"><i class="fas fa-compass"></i> Browse Quizzes</a>
                            <?php if (!$isAdminView): ?>
                                <a href="join_quiz.php" class="app-button app-button-ghost"><i class="fas fa-link"></i> Join with Code</a>
                            <?php endif; ?>
                        </div>
                    </section>
                </aside>
            </div>
<?php include 'includes/footer.php'; ?>
