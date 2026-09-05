</main>
<footer class="app-footer text-center text-muted py-3">
    <div class="container">
        <div class="row"><div class="col-12"><div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
            <span class="fw-bold" style="color: var(--navy, #1b4d8f);"><span class="org-flag" aria-hidden="true"></span> <?php echo e(t('common.organization_name')); ?></span><span class="text-muted">|</span>
            <a href="https://wa.me/249900008247" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1"><i class="fab fa-whatsapp" style="color:#25D366"></i><span class="text-muted small"><?php echo e(t('common.whatsapp')); ?></span></a><span class="text-muted">|</span>
            <a href="https://www.tiktok.com/@ahlalkheir1" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1"><i class="fab fa-tiktok"></i><span class="text-muted small"><?php echo e(t('common.tiktok')); ?></span></a><span class="text-muted">|</span>
            <a href="https://www.instagram.com/ahlalkhair9/" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1"><i class="fab fa-instagram" style="color:#E4405F"></i><span class="text-muted small"><?php echo e(t('common.instagram')); ?></span></a><span class="text-muted">|</span>
            <a href="https://www.facebook.com/61576384876429" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1"><i class="fab fa-facebook" style="color:#1877F2"></i><span class="text-muted small"><?php echo e(t('common.facebook')); ?></span></a>
        </div></div></div>
        <div class="row mt-3"><div class="col-12"><small><?php echo e(t('common.copyright')); ?> &copy; <?php echo date('Y'); ?></small></div></div>
    </div>
</footer>
</div>
</div>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<?php include __DIR__ . '/age_alert.php'; ?>
<?php include __DIR__ . '/messaging_widget.php'; ?>

<style>
/* Real CSS Sudan flag with a subtle fabric-wave animation. */
.org-header-banner .org-flag,
.app-footer .org-flag {
    display: inline-block !important;
    position: relative;
    width: 34px !important;
    height: 23px !important;
    min-width: 34px !important;
    margin-inline: 7px;
    vertical-align: middle;
    overflow: hidden;
    border-radius: 2px;
    font-size: 0 !important;
    line-height: 0;
    background: linear-gradient(
        to bottom,
        #d71920 0 33.333%,
        #ffffff 33.333% 66.666%,
        #000000 66.666% 100%
    ) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.28);
    transform-origin: left center;
    animation: ak-sudan-flag-wave 2.8s ease-in-out infinite;
}

.org-header-banner .org-flag::before,
.app-footer .org-flag::before {
    content: "";
    display: block !important;
    position: absolute;
    inset: 0 auto 0 0;
    width: 43%;
    height: 100%;
    background: #087a3b !important;
    clip-path: polygon(0 0, 100% 50%, 0 100%);
    z-index: 2;
}

.org-header-banner .org-flag::after,
.app-footer .org-flag::after {
    content: "";
    display: block !important;
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255,255,255,.28) 18%,
        transparent 34%,
        rgba(0,0,0,.16) 48%,
        transparent 63%,
        rgba(255,255,255,.22) 78%,
        transparent 100%
    );
    z-index: 3;
    pointer-events: none;
    animation: ak-sudan-flag-folds 2.8s ease-in-out infinite;
}

@keyframes ak-sudan-flag-wave {
    0%, 100% {
        transform: perspective(120px) rotateY(0deg) skewY(0deg) scaleX(1);
    }
    25% {
        transform: perspective(120px) rotateY(-12deg) skewY(1.5deg) scaleX(.96);
    }
    50% {
        transform: perspective(120px) rotateY(10deg) skewY(-1deg) scaleX(.98);
    }
    75% {
        transform: perspective(120px) rotateY(-7deg) skewY(.8deg) scaleX(.97);
    }
}

@keyframes ak-sudan-flag-folds {
    0%, 100% { transform: translateX(-12%); }
    50% { transform: translateX(12%); }
}

@media (prefers-reduced-motion: reduce) {
    .org-header-banner .org-flag,
    .app-footer .org-flag,
    .org-header-banner .org-flag::after,
    .app-footer .org-flag::after {
        animation: none;
    }
}

/* The personal leave action is rendered inside the user menu, not the top action strip. */
.ak-leave-request-moving {
    visibility: hidden;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.AK_LANG = <?php echo json_encode(AK_LANG, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.AK_TRANSLATIONS = <?php echo json_encode(ak_dict(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo asset('js/language.js'); ?>"></script>
<script src="<?php echo asset('js/app.js'); ?>"></script>
<script src="<?php echo asset('js/notifications.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_reply_tools.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_ui_fixes.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_ui_cleanup.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_attachments.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_message_delete.js'); ?>"></script>
<?php if (($active ?? '') === 'fm_dashboard'): ?>
<script src="<?php echo asset('js/fm_dashboard_layout.js'); ?>"></script>
<?php endif; ?>
<script>
(function(){
    var leaveLink = document.querySelector('a[href*="modules/hr/leaves.php?action=request"]');
    var userMenu = document.querySelector('#userDropdown .ak-dd-menu');

    if (leaveLink && userMenu) {
        leaveLink.classList.remove('qa-btn');
        leaveLink.classList.add('ak-dd-item', 'ak-leave-request-moving');
        leaveLink.removeAttribute('style');
        leaveLink.setAttribute('title', 'طلب إجازة');

        var icon = leaveLink.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-calendar-plus me-2';
        }

        var divider = document.createElement('div');
        divider.className = 'dropdown-divider';
        userMenu.insertBefore(divider, userMenu.firstChild);
        userMenu.insertBefore(leaveLink, divider.nextSibling);

        requestAnimationFrame(function(){
            leaveLink.classList.remove('ak-leave-request-moving');
        });
    }
})();

(function(){var deferred=null;var btn=document.getElementById('akInstallBtn');window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferred=e;if(btn)btn.classList.remove('d-none')});if(btn)btn.addEventListener('click',function(){if(!deferred)return;deferred.prompt();deferred.userChoice.then(function(){deferred=null;btn.classList.add('d-none')})});window.addEventListener('appinstalled',function(){if(btn)btn.classList.add('d-none')})})();
if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){})});}
</script>
</body>
</html>
