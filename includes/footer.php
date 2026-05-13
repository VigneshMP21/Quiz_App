<?php
$footerQuickLinks = array_values(array_filter($navItems ?? [], static function ($navItem) {
    return !empty($navItem['show']) && (!array_key_exists('footer', $navItem) || !empty($navItem['footer']));
}));
?>
        </main>

        <footer class="app-site-footer">
            <div class="app-site-footer-grid">
                <section class="app-site-footer-brand">
                    <div class="app-site-footer-logo">
                        <span class="app-topbar-brand-mark"><i class="fas fa-bolt"></i></span>
                        <div>
                            <strong>Quiz Pro</strong>
                            <p><?php echo htmlspecialchars($pageFooterSummary ?? 'A sharper interface for quizzes, certificates, collaboration, and platform flow.'); ?></p>
                        </div>
                    </div>
                </section>

                <section class="app-site-footer-column">
                    <h3>Quick Links</h3>
                    <div class="app-site-footer-links">
                        <?php foreach ($footerQuickLinks as $navItem): ?>
                            <a href="<?php echo htmlspecialchars($resolveAppPath($navItem['href'])); ?>">
                                <i class="<?php echo htmlspecialchars($navItem['icon']); ?>"></i>
                                <span><?php echo htmlspecialchars($navItem['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="app-site-footer-column">
                    <h3>Workspace</h3>
                    <div class="app-site-footer-meta">
                        <span><i class="fas fa-user-shield"></i> <?php echo $isAdminView ? 'Admin access enabled' : 'Learner access enabled'; ?></span>
                        <span><i class="fas fa-bell"></i> <?php echo (int) ($notificationCount ?? 0); ?> active notification<?php echo ((int) ($notificationCount ?? 0) === 1) ? '' : 's'; ?></span>
                        <span><i class="fas fa-ranking-star"></i> Rank #<?php echo (int) ($headerRank ?? 1); ?></span>
                    </div>
                    <a href="<?php echo htmlspecialchars($resolvedLogoutLink); ?>" class="app-site-footer-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </section>
            </div>

            <div class="app-site-footer-bottom">
                <span>&copy; <?php echo date('Y'); ?> Quiz Pro. Professional quiz management and learning flow.</span>
                <a href="<?php echo htmlspecialchars($resolvedHomeLink); ?>">Back to dashboard</a>
            </div>
        </footer>
    </div>

    <script src="<?php echo htmlspecialchars($resolveAppPath('assets/js/script.js')); ?>"></script>
</body>
</html>
