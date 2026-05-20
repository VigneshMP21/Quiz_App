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

// Handle AJAX actions or query string actions
$action = $_REQUEST['action'] ?? null;
$notifId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : null;

// Helper to return JSON if AJAX
function sendAjaxResponse($success, $message = '', $data = []) {
    if (!empty($_SERVER['HTTP_X_REQUEST_WITH']) && strtolower($_SERVER['HTTP_X_REQUEST_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
        exit;
    }
}

if ($action) {
    if ($action === 'mark_read' && $notifId) {
        // Verify ownership/permission
        if ($isAdminView) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id IS NULL");
            $success = $stmt->execute([$notifId]);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $success = $stmt->execute([$notifId, $_SESSION['user_id']]);
        }
        sendAjaxResponse($success, $success ? 'Notification marked as read.' : 'Failed to update notification.');
        if (!$success) {
            setMessage('Failed to update notification.', 'error');
        }
        redirect('notifications.php');
    }

    if ($action === 'delete' && $notifId) {
        // Delete notification
        if ($isAdminView) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id IS NULL");
            $success = $stmt->execute([$notifId]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $success = $stmt->execute([$notifId, $_SESSION['user_id']]);
        }
        sendAjaxResponse($success, $success ? 'Notification deleted.' : 'Failed to delete notification.');
        if (!$success) {
            setMessage('Failed to delete notification.', 'error');
        }
        redirect('notifications.php');
    }

    if ($action === 'mark_all_read') {
        // Mark all as read
        if ($isAdminView) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id IS NULL");
            $success = $stmt->execute();
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $success = $stmt->execute([$_SESSION['user_id']]);
        }
        sendAjaxResponse($success, $success ? 'All notifications marked as read.' : 'Failed to update notifications.');
        if ($success) {
            setMessage('All notifications marked as read.', 'success');
        } else {
            setMessage('Failed to update notifications.', 'error');
        }
        redirect('notifications.php');
    }

    if ($action === 'clear_all') {
        // Clear all
        if ($isAdminView) {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id IS NULL");
            $success = $stmt->execute();
        } else {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
            $success = $stmt->execute([$_SESSION['user_id']]);
        }
        sendAjaxResponse($success, $success ? 'All notifications cleared.' : 'Failed to clear notifications.');
        if ($success) {
            setMessage('All notifications cleared.', 'success');
        } else {
            setMessage('Failed to clear notifications.', 'error');
        }
        redirect('notifications.php');
    }
}

// Fetch all notifications for display
if ($isAdminView) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
}
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page definitions
$pageTitle = 'Notifications - QuizPro';
$pageKey = 'notifications';
$pageBodyClass = 'page-notifications-center';
$headerContext = $isAdminView ? 'Admin alerts cockpit' : 'Notification center';
$pageFooterSummary = 'A complete record of your activity alerts, scores, certificate updates, and team notifications.';
$heroSummary = $isAdminView 
    ? 'A complete system log of user registrations, quiz attempts, and contact submissions needing administrative oversight.' 
    : 'A complete record of your activity alerts, scores, certificate updates, and custom platform notifications.';

// Select FontAwesome icons and color tags based on notification type
function getNotificationVisuals($type) {
    switch ($type) {
        case 'quiz_completed':
            return [
                'icon' => 'fas fa-clipboard-check',
                'bg' => 'linear-gradient(135deg, #10b981, #059669)',
                'class' => 'notif-tag-success',
                'label' => 'Quiz Done'
            ];
        case 'quiz_attempted':
            return [
                'icon' => 'fas fa-graduation-cap',
                'bg' => 'linear-gradient(135deg, #3b82f6, #2563eb)',
                'class' => 'notif-tag-primary',
                'label' => 'New Attempt'
            ];
        case 'certificate_issued':
            return [
                'icon' => 'fas fa-award',
                'bg' => 'linear-gradient(135deg, #f59e0b, #d97706)',
                'class' => 'notif-tag-warning',
                'label' => 'Certificate'
            ];
        case 'registration':
            return [
                'icon' => 'fas fa-user-plus',
                'bg' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
                'class' => 'notif-tag-purple',
                'label' => 'New User'
            ];
        case 'message_sent':
            return [
                'icon' => 'fas fa-envelope',
                'bg' => 'linear-gradient(135deg, #ec4899, #db2777)',
                'class' => 'notif-tag-danger',
                'label' => 'Support'
            ];
        default:
            return [
                'icon' => 'fas fa-info-circle',
                'bg' => 'linear-gradient(135deg, #6b7280, #4b5563)',
                'class' => 'notif-tag-gray',
                'label' => 'Info'
            ];
    }
}

// Inject premium glassmorphism custom styles in headAssets
$headAssets = <<<'HTML'
<style>
    .notif-wrapper {
        margin-top: 2rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .notif-actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        background: rgba(255, 255, 255, 0.72);
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 1rem 1.5rem;
        border-radius: 16px;
        backdrop-filter: blur(10px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }
    .notif-stats {
        font-size: 0.95rem;
        color: #475569;
    }
    .notif-stats strong {
        color: #0f172a;
    }
    .notif-btns {
        display: flex;
        gap: 0.75rem;
    }
    .notif-btn-action {
        font-size: 0.85rem;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
        cursor: pointer;
    }
    .notif-btn-mark-all {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(148, 163, 184, 0.22);
        color: #334155;
    }
    .notif-btn-mark-all:hover {
        background: #fff;
        color: #0f172a;
        transform: translateY(-1px);
        border-color: rgba(100, 116, 139, 0.24);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }
    .notif-btn-clear-all {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }
    .notif-btn-clear-all:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.1);
    }
    .notif-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .notif-card {
        display: flex;
        gap: 1.25rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.96));
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 1.25rem 1.5rem;
        border-radius: 16px;
        backdrop-filter: blur(15px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }
    .notif-card.unread {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(241, 245, 249, 0.98));
        border-color: rgba(99, 102, 241, 0.25);
    }
    .notif-card.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: linear-gradient(to bottom, #6366f1, #4f46e5);
    }
    .notif-card:hover {
        transform: translateX(4px);
        background: #fff;
        border-color: rgba(148, 163, 184, 0.28);
        box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
    }
    .notif-card.unread:hover {
        background: linear-gradient(180deg, #fff, rgba(241, 245, 249, 0.9));
        border-color: rgba(99, 102, 241, 0.35);
    }
    .notif-left {
        flex-shrink: 0;
    }
    .notif-icon-container {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.25rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    .notif-mid {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .notif-meta-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .notif-badge {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: 0.2rem 0.6rem;
        border-radius: 6px;
        letter-spacing: 0.05em;
    }
    .notif-tag-success { background: rgba(16, 185, 129, 0.08); color: #059669; border: 1px solid rgba(16, 185, 129, 0.15); }
    .notif-tag-primary { background: rgba(59, 130, 246, 0.08); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.15); }
    .notif-tag-warning { background: rgba(245, 158, 11, 0.08); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.15); }
    .notif-tag-purple { background: rgba(139, 92, 246, 0.08); color: #7c3aed; border: 1px solid rgba(139, 92, 246, 0.15); }
    .notif-tag-danger { background: rgba(239, 68, 68, 0.08); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.15); }
    .notif-tag-gray { background: rgba(107, 114, 128, 0.08); color: #4b5563; border: 1px solid rgba(107, 114, 128, 0.15); }

    .notif-time {
        font-size: 0.8rem;
        color: #64748b;
    }
    .notif-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
    }
    .notif-card.unread .notif-title {
        color: #0f172a;
    }
    .notif-msg {
        font-size: 0.9rem;
        color: #475569;
        line-height: 1.45;
        margin: 0;
    }
    .notif-link {
        font-size: 0.85rem;
        font-weight: 500;
        color: #4f46e5;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        margin-top: 0.25rem;
        width: fit-content;
        transition: color 0.2s;
    }
    .notif-link:hover {
        color: #3b82f6;
    }
    .notif-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .notif-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(148, 163, 184, 0.18);
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
    }
    .notif-action-btn:hover {
        background: #fff;
        color: #0f172a;
        border-color: rgba(148, 163, 184, 0.26);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.05);
    }
    .notif-action-btn.delete-btn:hover {
        background: rgba(239, 68, 68, 0.08);
        border-color: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.08);
    }
    
    .notif-empty-box {
        text-align: center;
        padding: 4rem 2rem;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.96));
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 16px;
        backdrop-filter: blur(10px);
        margin-top: 1rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }
    .notif-empty-icon {
        font-size: 3rem;
        color: rgba(148, 163, 184, 0.4);
        margin-bottom: 1rem;
    }
    .notif-empty-box h3 {
        font-size: 1.25rem;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }
    .notif-empty-box p {
        font-size: 0.9rem;
        color: #475569;
        margin: 0;
    }
    
    /* Animation classes */
    .notif-fade-out {
        opacity: 0;
        transform: translateY(-20px);
        transition: opacity 0.5s ease, transform 0.5s ease;
    }
</style>
HTML;

include 'includes/header.php';
?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker"><?php echo $isAdminView ? 'System Alerts Log' : 'Personal Activity'; ?></span>
                    <h1 class="app-title">Notifications</h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                </div>
            </section>

            <?php displayMessage(); ?>

            <div class="notif-wrapper">
                <?php if (!empty($notifications)): ?>
                    <?php
                    $unreadCount = 0;
                    foreach ($notifications as $n) {
                        if (!$n['is_read']) $unreadCount++;
                    }
                    ?>
                    <div class="notif-actions-bar">
                        <div class="notif-stats">
                            Showing <strong><?php echo count($notifications); ?></strong> notifications
                            <?php if ($unreadCount > 0): ?>
                                (&nbsp;<strong><?php echo $unreadCount; ?></strong> unread&nbsp;)
                            <?php endif; ?>
                        </div>
                        <div class="notif-btns">
                            <?php if ($unreadCount > 0): ?>
                                <button type="button" class="notif-btn-action notif-btn-mark-all" id="btnMarkAllRead" onclick="triggerNotificationAction('mark_all_read')">
                                    <i class="fas fa-check-double"></i> Mark all read
                                </button>
                            <?php endif; ?>
                            <button type="button" class="notif-btn-action notif-btn-clear-all" id="btnClearAll" onclick="triggerNotificationAction('clear_all')">
                                <i class="fas fa-trash-can"></i> Clear all
                            </button>
                        </div>
                    </div>

                    <div class="notif-grid" id="notificationsList">
                        <?php foreach ($notifications as $notif): ?>
                            <?php
                            $visuals = getNotificationVisuals($notif['type']);
                            $isUnread = !$notif['is_read'];
                            $timeStr = date('M j, Y - H:i', strtotime($notif['created_at']));
                            ?>
                            <article class="notif-card <?php echo $isUnread ? 'unread' : ''; ?>" id="notif-row-<?php echo $notif['id']; ?>">
                                <div class="notif-left">
                                    <div class="notif-icon-container" style="background: <?php echo $visuals['bg']; ?>;">
                                        <i class="<?php echo $visuals['icon']; ?>"></i>
                                    </div>
                                </div>
                                <div class="notif-mid">
                                    <div class="notif-meta-row">
                                        <span class="notif-badge <?php echo $visuals['class']; ?>">
                                            <?php echo htmlspecialchars($visuals['label']); ?>
                                        </span>
                                        <span class="notif-time">
                                            <i class="far fa-clock" style="margin-right: 4px;"></i><?php echo $timeStr; ?>
                                        </span>
                                    </div>
                                    <h3 class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></h3>
                                    <p class="notif-msg"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    
                                    <?php if (!empty($notif['link'])): ?>
                                        <?php
                                        // Prefix links appropriately if we are in admin but redirecting to root files
                                        $notifLink = htmlspecialchars($notif['link']);
                                        ?>
                                        <a href="<?php echo $notifLink; ?>" class="notif-link" onclick="markReadAndGo(event, <?php echo $notif['id']; ?>, '<?php echo $notifLink; ?>', <?php echo $isUnread ? 'true' : 'false'; ?>)">
                                            View details <i class="fas fa-chevron-right" style="font-size: 0.75rem;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="notif-right">
                                    <?php if ($isUnread): ?>
                                        <button type="button" class="notif-action-btn" title="Mark as read" onclick="triggerNotificationAction('mark_read', <?php echo $notif['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="notif-action-btn delete-btn" title="Delete notification" onclick="triggerNotificationAction('delete', <?php echo $notif['id']; ?>)">
                                        <i class="far fa-trash-can"></i>
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="notif-empty-box">
                        <div class="notif-empty-icon">
                            <i class="far fa-bell-slash"></i>
                        </div>
                        <h3>All caught up!</h3>
                        <p>You have no notifications at the moment. We will notify you when things require your attention.</p>
                    </div>
                <?php endif; ?>
            </div>

<script>
    // Performs notifications update via AJAX and triggers beautiful animations
    function triggerNotificationAction(action, id = null) {
        let url = 'notifications.php?action=' + action;
        if (id) {
            url += '&id=' + id;
        }

        // Use fetch with AJAX header so the PHP file knows to return JSON instead of redirecting
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (action === 'mark_read' && id) {
                    // Update layout for marked as read
                    const row = document.getElementById('notif-row-' + id);
                    if (row) {
                        row.classList.remove('unread');
                        // Remove unread action button
                        const checkBtn = row.querySelector('.notif-right button[title="Mark as read"]');
                        if (checkBtn) checkBtn.remove();
                    }
                    // Refresh unread stats on topbar notifications badge dynamically
                    updateTopbarCount();
                } else if (action === 'delete' && id) {
                    // Slide up and dismiss the row
                    const row = document.getElementById('notif-row-' + id);
                    if (row) {
                        row.classList.add('notif-fade-out');
                        setTimeout(() => {
                            row.remove();
                            // If empty now, reload to show empty state
                            if (document.querySelectorAll('.notif-card').length === 0) {
                                window.location.reload();
                            }
                        }, 500);
                    }
                    updateTopbarCount();
                } else {
                    // For mark_all_read or clear_all, reload the page seamlessly
                    window.location.reload();
                }
            } else {
                console.error('Action failed: ', data.message);
                // Standard fallback: redirect directly
                window.location.href = 'notifications.php?action=' + action + (id ? '&id=' + id : '');
            }
        })
        .catch(err => {
            console.warn('AJAX fetch failed, redirecting standard workflow.', err);
            window.location.href = 'notifications.php?action=' + action + (id ? '&id=' + id : '');
        });
    }

    // Handles clicking 'View details' link - marks read in background, then navigates
    function markReadAndGo(event, id, linkUrl, isUnread) {
        if (!isUnread) return; // Proceed standard navigation immediately if already read

        event.preventDefault();
        fetch('notifications.php?action=mark_read&id=' + id, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(() => {
            window.location.href = linkUrl;
        })
        .catch(() => {
            // Fallback navigate anyway
            window.location.href = linkUrl;
        });
    }

    // Dynamic topbar badge updates
    function updateTopbarCount() {
        const unreadCount = document.querySelectorAll('.notif-card.unread').length;
        const badges = document.querySelectorAll('.app-topbar-badge');
        
        badges.forEach(badge => {
            if (unreadCount > 0) {
                badge.textContent = Math.min(unreadCount, 99);
            } else {
                badge.remove();
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>
