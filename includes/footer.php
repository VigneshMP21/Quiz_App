<?php
if (!isset($resolveAppPath) || !is_callable($resolveAppPath)) {
    $resolveAppPath = static function (string $path): string {
        return $path;
    };
}

$footerQuickLinks = array_values(array_filter($navItems ?? [], static function ($navItem) {
    return is_array($navItem) && !empty($navItem['show']);
}));
?>
</main>

<style>
    .app-site-footer-enhanced {
        padding: 40px 24px 30px !important;
        background: #fbfcfd !important; /* Matches the user's exact uploaded image color! */
        border-radius: 28px !important;
        border: 1px solid rgba(15, 23, 42, 0.06) !important;
        margin-top: 40px !important;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05) !important;
        color: #1e293b !important;
    }
    .app-footer-enhanced-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 40px;
        margin-bottom: 30px;
        text-align: left;
    }
    @media (max-width: 768px) {
        .app-footer-enhanced-grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }
    }
    .app-footer-details {
        display: flex;
        flex-direction: column;
        gap: 14px;
        font-size: 14px;
        color: #334155 !important;
    }
    .app-footer-details a {
        color: inherit;
        text-decoration: none;
        transition: color 0.28s ease;
    }
    .app-footer-details a:hover {
        color: #0f172a;
    }
    .app-footer-socials {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 15px;
    }
    .app-footer-social-btn {
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(15, 23, 42, 0.05);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: #334155;
        transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
    }
    .app-footer-social-btn:hover {
        color: #fff;
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }
    .app-footer-social-btn.insta:hover {
        background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
    }
    .app-footer-social-btn.whatsapp:hover {
        background: #25D366;
    }
    .app-footer-social-btn.youtube:hover {
        background: #FF0000;
    }
    .app-footer-social-btn.github:hover {
        background: #24292e;
        border-color: #404448;
    }
    .app-footer-social-btn.linkedin:hover {
        background: #0077b5;
    }
    .app-footer-contact-form {
        display: grid;
        gap: 14px;
    }
    .app-footer-input-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 480px) {
        .app-footer-input-row {
            grid-template-columns: 1fr;
        }
    }
    .app-footer-input,
    .app-footer-textarea {
        width: 100%;
        padding: 12px 16px;
        background: #ffffff !important; /* Crisp pure white inputs to stand out against the premium cool off-white background */
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px;
        color: #0f172a !important;
        font-size: 13.5px;
        font-family: inherit;
        transition: all 0.28s ease;
    }
    .app-footer-input::placeholder,
    .app-footer-textarea::placeholder {
        color: #94a3b8 !important;
    }
    .app-footer-input:focus,
    .app-footer-textarea:focus {
        border-color: var(--app-accent) !important;
        background: #ffffff !important; /* Transition to clean white on focus */
        outline: none;
        box-shadow: 0 0 12px rgba(20, 184, 166, 0.12);
    }
    .app-footer-textarea {
        resize: none;
    }
    .app-footer-submit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        font-size: 13.5px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, var(--app-accent), var(--app-accent-2));
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.28s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 20px var(--app-accent-soft);
        width: max-content;
        justify-self: end;
    }
    .app-footer-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 28px var(--app-accent-soft);
    }
    .app-footer-nav-row {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 24px;
        padding: 24px 0;
        border-top: 1px solid #e2e8f0 !important;
        border-bottom: 1px solid #e2e8f0 !important;
        margin-bottom: 24px;
    }
    .app-footer-nav-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        color: #334155 !important;
        transition: all 0.28s ease;
    }
    .app-footer-nav-link i {
        color: var(--app-accent);
        font-size: 14px;
        transition: transform 0.28s ease;
    }
    .app-footer-nav-link:hover {
        color: #0f172a !important;
    }
    .app-footer-nav-link:hover i {
        transform: scale(1.15);
    }
    .app-footer-bottom-centered {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 13px;
        color: #64748b !important;
    }
    .page-public-home .app-site-footer-enhanced {
        background: rgba(255, 255, 255, 0.82) !important;
        border-color: rgba(148, 163, 184, 0.2) !important;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.86) !important;
        color: #334155 !important;
        backdrop-filter: blur(24px) saturate(1.2);
        -webkit-backdrop-filter: blur(24px) saturate(1.2);
    }
    .page-public-home .app-site-footer-enhanced strong,
    .page-public-home .app-site-footer-enhanced h3 {
        color: #0f172a !important;
    }
    .page-public-home .app-site-footer-enhanced p,
    .page-public-home .app-footer-details,
    .page-public-home .app-footer-details a,
    .page-public-home .app-footer-nav-link,
    .page-public-home .app-footer-bottom-centered {
        color: #475569 !important;
    }
    .page-public-home .app-footer-social-btn,
    .page-public-home .app-footer-input,
    .page-public-home .app-footer-textarea {
        background: rgba(255, 255, 255, 0.9) !important;
        border-color: rgba(148, 163, 184, 0.18) !important;
        color: #0f172a !important;
    }
    .page-public-home .app-footer-input::placeholder,
    .page-public-home .app-footer-textarea::placeholder {
        color: #94a3b8 !important;
    }
</style>

<footer class="app-site-footer app-site-footer-enhanced">
    <div class="app-footer-enhanced-grid">
        
        <!-- Left Column: Logo & Details -->
        <section style="display: flex; flex-direction: column; gap: 24px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <span class="app-topbar-brand-mark" style="width: 52px; height: 52px; border: 2px solid rgba(255, 255, 255, 0.15);">
                    <img src="<?php echo htmlspecialchars($resolveAppPath('assets/images/quizPro.png')); ?>" alt="Quiz Pro Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: inherit; padding: 2px;">
                </span>
                <div>
                    <strong style="font-size: 24px; font-family: 'Poppins', sans-serif; color: #0f172a !important; font-weight: 800; letter-spacing: -0.03em; line-height: 1;">Quiz Pro</strong>
                    <p style="font-size: 12px; color: #64748b !important; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700;"><?php echo htmlspecialchars($headerContext ?? 'Learning cockpit'); ?></p>
                </div>
            </div>
            
            <div class="app-footer-details">
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-user" style="color: var(--app-accent); margin-top: 4px; width: 16px; text-align: center; font-size: 15px;"></i>
                    <span><strong>Vignesh MP</strong></span>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-envelope" style="color: var(--app-accent); margin-top: 4px; width: 16px; text-align: center; font-size: 15px;"></i>
                    <a href="mailto:mpvignesh2107@gmail.com">mpvignesh2107@gmail.com</a>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-phone" style="color: var(--app-accent); margin-top: 4px; width: 16px; text-align: center; font-size: 15px;"></i>
                    <a href="tel:+919393211095">+91 9393211095</a>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 12px;">
                    <i class="fas fa-location-dot" style="color: var(--app-accent); margin-top: 4px; width: 16px; text-align: center; font-size: 15px;"></i>
                    <span style="line-height: 1.5;">4-50, Bazar Street, Chinthala Pattadai, Nagari, Chittoor, Andhra Pradesh - 517590.</span>
                </div>
            </div>

            <!-- Social Icons -->
            <div class="app-footer-socials">
                <a href="https://instagram.com" class="app-footer-social-btn insta" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://wa.me/919393211095" class="app-footer-social-btn whatsapp" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="https://youtube.com" class="app-footer-social-btn youtube" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                    <i class="fab fa-youtube"></i>
                </a>
                <a href="https://github.com/VigneshMP21" class="app-footer-social-btn github" target="_blank" rel="noopener noreferrer" aria-label="GitHub">
                    <i class="fab fa-github"></i>
                </a>
                <a href="https://linkedin.com" class="app-footer-social-btn linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                    <i class="fab fa-linkedin-in"></i>
                </a>
            </div>
        </section>

        <!-- Right Column: Contact Form -->
        <section style="display: flex; flex-direction: column; gap: 20px;">
            <h3 style="font-size: 15px; font-family: 'Poppins', sans-serif; color: #0f172a !important; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; margin: 0; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-envelope-open-text" style="color: var(--app-accent);"></i> Send us a message
            </h3>
            
            <form action="<?php echo htmlspecialchars($resolveAppPath('contact.php')); ?>" method="POST" class="app-footer-contact-form">
                <div class="app-footer-input-row">
                    <input type="text" name="name" class="app-footer-input" placeholder="Your Name" required>
                    <input type="email" name="email" class="app-footer-input" placeholder="Your Email" required>
                </div>
                <textarea name="message" rows="4" class="app-footer-textarea" placeholder="Your Message..." required></textarea>
                
                <button type="submit" class="app-footer-submit-btn" data-loading-submit>
                    <span class="submit-loader" aria-hidden="true"></span>
                    <i class="fas fa-paper-plane"></i>
                    <span>Send Message</span>
                </button>
            </form>
        </section>

    </div>

    <!-- Navigation links in a single row -->
    <div class="app-footer-nav-row">
        <?php foreach ($footerQuickLinks as $navItem): ?>
            <?php
            $footerLinkHref = (string) ($navItem['href'] ?? '#');
            $footerLinkIcon = (string) ($navItem['icon'] ?? 'fas fa-circle');
            $footerLinkLabel = (string) ($navItem['label'] ?? 'Link');
            ?>
            <a href="<?php echo htmlspecialchars($resolveAppPath($footerLinkHref)); ?>" class="app-footer-nav-link">
                <i class="<?php echo htmlspecialchars($footerLinkIcon); ?>"></i>
                <span><?php echo htmlspecialchars($footerLinkLabel); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Copyright -->
    <div class="app-footer-bottom-centered">
        <span>&copy; <?php echo date('Y'); ?> Quiz Pro. Professional quiz management and learning flow. Created by Vignesh MP.</span>
    </div>
</footer>
</div>

<script src="<?php echo htmlspecialchars($resolveAppPath('assets/js/script.js')); ?>"></script>
</body>

</html>
