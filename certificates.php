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

$headAssets = '
<style>
    .app-cert-category-badge {
        font-size: 0.75rem;
        background: rgba(14, 165, 233, 0.1);
        color: var(--app-primary, #0ea5e9);
        padding: 0.25rem 0.75rem;
        border-radius: 2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    .app-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(12px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 2rem;
        animation: certFadeIn 0.3s ease;
    }
    .app-modal-overlay.active {
        display: flex;
    }
    .app-modal-content {
        background: var(--app-bg-panel, #ffffff);
        padding: 1.5rem;
        border-radius: 1.5rem;
        max-width: 1200px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: scale(0.95);
        animation: certScaleIn 0.3s ease forwards;
    }
    .app-modal-body {
        overflow: auto;
        display: flex;
        justify-content: center;
        background: #f8fafc;
        border-radius: 1rem;
        padding: 1rem;
    }
    .app-modal-body img {
        max-width: 100%;
        height: auto;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }
    .app-modal-footer {
        display: flex;
        justify-content: center;
        gap: 1rem;
        padding-top: 0.5rem;
    }
    @keyframes certFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes certScaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
</style>
<link rel="stylesheet" href="assets/css/mobile_view.css">
';

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
                                        <span class="app-cert-category-badge"><i class="fas fa-tags"></i> <?php echo htmlspecialchars($certificate['category']); ?></span>
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
                                        <button type="button" class="app-button app-button-outline" onclick="openCertView('<?php echo htmlspecialchars($certificate['certificate_path']); ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>


            </div>

            <div id="certModal" class="app-modal-overlay">
                <div class="app-modal-content">
                    <div class="app-modal-body">
                        <img id="certImage" src="" alt="Certificate View">
                    </div>
                    <div class="app-modal-footer">
                        <a id="certDownloadLink" href="" class="app-button app-button-primary" download>
                            <i class="fas fa-download"></i> Download
                        </a>
                        <button type="button" class="app-button app-button-ghost" onclick="closeCertView()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <script>
                function openCertView(path) {
                    const modal = document.getElementById('certModal');
                    const img = document.getElementById('certImage');
                    const link = document.getElementById('certDownloadLink');
                    
                    img.src = path;
                    link.href = path;
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }

                function closeCertView() {
                    const modal = document.getElementById('certModal');
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }

                // Close on ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') closeCertView();
                });

                // Close on overlay click
                document.getElementById('certModal').addEventListener('click', function(e) {
                    if (e.target === this) closeCertView();
                });
            </script>
<?php include 'includes/footer.php'; ?>
