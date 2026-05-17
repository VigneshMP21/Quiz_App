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
$headAssets = '<link rel="stylesheet" href="assets/css/mobile_view.css">';

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



            <div class="app-grid" style="margin-top: 24px;">
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


            </div>
<?php require __DIR__ . '/footer.php'; ?>
