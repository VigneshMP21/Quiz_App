<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($accountPageConfig) || !is_array($accountPageConfig)) {
    http_response_code(500);
    exit('Account password configuration is missing.');
}

$changePasswordIsAdminView = !empty($accountPageConfig['is_admin']);

if (!isLoggedIn()) {
    redirect('login.php');
}

if (isAdmin() !== $changePasswordIsAdminView) {
    redirect(getChangePasswordPagePath(isAdmin()));
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = getUserById($pdo, $userId);

if (!$user) {
    redirect($changePasswordIsAdminView ? 'dashboard_admin.php' : 'dashboard_user.php', 'Account could not be loaded.', 'error');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'All password fields are required.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirm password do not match.';
    }

    if (strlen($newPassword) < 6) {
        $errors[] = 'New password must be at least 6 characters long.';
    }

    if ($currentPassword !== '' && !password_verify($currentPassword, (string) ($user['password'] ?? ''))) {
        $errors[] = 'Current password is incorrect.';
    }

    if ($currentPassword !== '' && $newPassword !== '' && $currentPassword === $newPassword) {
        $errors[] = 'New password must be different from the current password.';
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");

        if ($updateStmt->execute([$hashedPassword, $userId])) {
            redirect(getChangePasswordPagePath($changePasswordIsAdminView), 'Password changed successfully.');
        }

        $errors[] = 'Password change failed. Please try again.';
    }
}

$displayName = trim((string) ($user['username'] ?? 'User'));
$displayEmail = trim((string) ($user['email'] ?? ''));
$displayProfileImage = trim((string) ($user['profile_image'] ?? ''));
$profileInitial = strtoupper(substr($displayName, 0, 1));

if ($changePasswordIsAdminView) {
    $heroSummary = 'Use a stronger password for the admin account whenever you need to refresh access security or rotate credentials.';
    $statusLabel = 'Admin account';
} else {
    $heroSummary = 'Refresh your learner password when you need stronger account security or want to rotate old credentials.';
    $statusLabel = 'Learner account';
}

$pageTitle = $accountPageConfig['page_title'] ?? ($changePasswordIsAdminView ? 'QuizPro - Admin Change Password' : 'QuizPro - User Change Password');
$pageKey = 'change_password';
$pageBodyClass = $accountPageConfig['page_body_class'] ?? ($changePasswordIsAdminView ? 'page-admin-change-password' : 'page-user-change-password');
$headerContext = $accountPageConfig['header_context'] ?? ($changePasswordIsAdminView ? 'Admin security' : 'Learner security');
$pageFooterSummary = $accountPageConfig['footer_summary'] ?? 'Secure password management for protected account access and safer platform usage.';
$homeLink = $changePasswordIsAdminView ? 'dashboard_admin.php' : 'dashboard_user.php';
$logoutLink = 'logout.php';
$changePasswordLink = getChangePasswordPagePath($changePasswordIsAdminView);
$profileLink = getProfilePagePath($changePasswordIsAdminView);
$isAdminView = $changePasswordIsAdminView;

require __DIR__ . '/header.php';
?>
            <?php displayMessage(); ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="app-hero">
                <div class="app-hero-copy">
                    <span class="app-kicker">Credential security</span>
                    <h1 class="app-title">Change your password</h1>
                    <p class="app-subtitle"><?php echo htmlspecialchars($heroSummary); ?></p>
                    <div class="app-actions">
                        <a href="<?php echo htmlspecialchars($profileLink); ?>" class="app-button app-button-ghost"><i class="fas fa-id-badge"></i> Back to Profile</a>
                        <a href="<?php echo htmlspecialchars($homeLink); ?>" class="app-button app-button-primary"><i class="fas fa-house"></i> Dashboard</a>
                    </div>
                </div>

                <div class="app-hero-panel">
                    <div class="app-hero-panel-head">
                        <span>Password policy</span>
                        <span class="app-status-pill"><i class="fas fa-lock"></i> Protected</span>
                    </div>
                    <div class="app-profile-hero-card">
                        <?php if ($displayProfileImage !== ''): ?>
                            <img src="<?php echo htmlspecialchars($displayProfileImage); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" class="app-account-avatar app-account-avatar-image">
                        <?php else: ?>
                            <span class="app-account-avatar app-account-avatar-fallback"><?php echo htmlspecialchars($profileInitial); ?></span>
                        <?php endif; ?>
                        <div class="app-profile-hero-meta">
                            <strong><?php echo htmlspecialchars($displayName); ?></strong>
                            <p><?php echo htmlspecialchars($displayEmail !== '' ? $displayEmail : $statusLabel); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-metric-grid">
                <article class="app-metric-card">
                    <span class="app-metric-label">Minimum length</span>
                    <strong class="app-metric-static">6+ chars</strong>
                    <p>Use a password long enough to avoid weak default combinations.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Current account</span>
                    <strong class="app-metric-static"><?php echo htmlspecialchars($statusLabel); ?></strong>
                    <p>The password update applies immediately after the form is saved.</p>
                </article>
                <article class="app-metric-card">
                    <span class="app-metric-label">Header access</span>
                    <strong class="app-metric-static">Sidebar ready</strong>
                    <p>You can return to this page any time from the shared sidebar.</p>
                </article>
            </section>

            <div class="app-grid app-account-layout">
                <section class="app-panel">
                    <div class="app-panel-head">
                        <div>
                            <span class="app-panel-kicker">Password form</span>
                            <h2 class="app-panel-title">Rotate your credentials safely</h2>
                        </div>
                    </div>
                    <p class="app-panel-text">Enter your current password first, then set a new password and confirm it before saving the change.</p>

                    <form action="<?php echo htmlspecialchars($changePasswordLink); ?>" method="POST" class="app-form-grid">
                        <div class="app-form-section">
                            <h3 class="app-section-title">Password details</h3>
                            <div class="app-field">
                                <label for="current_password" class="app-label">Current Password*</label>
                                <input type="password" id="current_password" name="current_password" class="app-input" required autocomplete="current-password">
                            </div>
                            <div class="app-field-row-compact">
                                <div class="app-field">
                                    <label for="new_password" class="app-label">New Password*</label>
                                    <input type="password" id="new_password" name="new_password" class="app-input" required autocomplete="new-password">
                                </div>
                                <div class="app-field">
                                    <label for="confirm_password" class="app-label">Confirm Password*</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="app-input" required autocomplete="new-password">
                                </div>
                            </div>
                            <p class="app-helper">Use a password you are not reusing elsewhere. A longer password with mixed characters is safer than a short predictable one.</p>
                        </div>

                        <div class="app-actions">
                            <button type="submit" class="app-button app-button-primary"><i class="fas fa-key"></i> Change Password</button>
                            <a href="<?php echo htmlspecialchars($profileLink); ?>" class="app-button app-button-ghost"><i class="fas fa-arrow-left"></i> Back to Profile</a>
                        </div>
                    </form>
                </section>

                <aside class="app-sidebar">
                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Security notes</span>
                                <h2 class="app-panel-title">Use better password habits</h2>
                            </div>
                        </div>
                        <ul class="app-note-list">
                            <li><i class="fas fa-check-circle"></i> Avoid reusing the same password across personal and platform accounts.</li>
                            <li><i class="fas fa-check-circle"></i> Change the password immediately if you suspect the account has been exposed.</li>
                            <li><i class="fas fa-check-circle"></i> Keep the current password private and do not share it for support requests.</li>
                        </ul>
                    </section>

                    <section class="app-panel app-panel-compact">
                        <div class="app-panel-head">
                            <div>
                                <span class="app-panel-kicker">Quick route</span>
                                <h2 class="app-panel-title">Return to account tools</h2>
                            </div>
                        </div>
                        <div class="app-sidebar-actions">
                            <a href="<?php echo htmlspecialchars($profileLink); ?>" class="app-button app-button-primary"><i class="fas fa-user-pen"></i> Open Profile</a>
                            <a href="<?php echo htmlspecialchars($homeLink); ?>" class="app-button app-button-ghost"><i class="fas fa-chart-line"></i> Dashboard</a>
                        </div>
                    </section>
                </aside>
            </div>
<?php require __DIR__ . '/footer.php'; ?>
