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

$stmt = $pdo->prepare("SELECT c.id, q.title, q.category, q.description, c.downloaded_at, c.certificate_path 
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



            <div class="app-grid" style="margin-top: 24px;">
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
                                    <p class="app-cert-desc"><?php 
                                         $desc = !empty($certificate['description']) ? $certificate['description'] : ($certificate['category'] . ' track');
                                         echo htmlspecialchars(strlen($desc) > 85 ? substr($desc, 0, 82) . '...' : $desc); 
                                     ?></p>
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


            </div>
<?php include 'includes/footer.php'; ?>
