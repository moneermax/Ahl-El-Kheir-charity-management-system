<?php /* Unified PRINT footer (invisible on screen). Styled in assets/css/print.css. */ ?>
<div class="ak-print-footer" aria-hidden="true">
    <span><?php echo e(t('common.copyright')); ?> &copy; <?php echo date('Y'); ?></span>
    <span><?php echo e(t('common.organization_name')); ?></span>
</div>
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

<style>
/* Top Back button — mirrors the existing bottom contextual Back button. */
.ak-top-back-wrap {
    display: flex;
    justify-content: flex-start;
    direction: ltr;
    width: 100%;
    margin: 0 0 1rem;
}
.ak-bottom-back-wrap {
    display: flex;
    justify-content: flex-start;
    direction: ltr;
    width: 100%;
    margin: 1.5rem 0 1.5rem;
}
.ak-top-back-wrap .ak-top-back-btn,
.ak-bottom-back-wrap .ak-bottom-back-btn {
    direction: rtl;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    white-space: nowrap;
}
@media (max-width: 575.98px) {
    .ak-top-back-wrap {
        margin-bottom: .75rem;
    }
    .ak-bottom-back-wrap {
        margin-top: 1.25rem;
    }
}</style>
<style>
/* Real CSS Sudan flag with a subtle fabric-wave animation. */
.org-header-banner .org-flag,
.app-footer .org-flag { display:inline-block !important;position:relative;width:34px !important;height:23px !important;min-width:34px !important;margin-inline:7px;vertical-align:middle;overflow:hidden;border-radius:2px;font-size:0 !important;line-height:0;background:linear-gradient(to bottom,#d71920 0 33.333%,#fff 33.333% 66.666%,#000 66.666% 100%) !important;box-shadow:0 1px 3px rgba(0,0,0,.28);transform-origin:left center;animation:ak-sudan-flag-wave 2.8s ease-in-out infinite }
.org-header-banner .org-flag::before,.app-footer .org-flag::before {content:"";display:block !important;position:absolute;inset:0 auto 0 0;width:43%;height:100%;background:#087a3b !important;clip-path:polygon(0 0,100% 50%,0 100%);z-index:2}
.org-header-banner .org-flag::after,.app-footer .org-flag::after {content:"";display:block !important;position:absolute;inset:0;background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.28) 18%,transparent 34%,rgba(0,0,0,.16) 48%,transparent 63%,rgba(255,255,255,.22) 78%,transparent 100%);z-index:3;pointer-events:none;animation:ak-sudan-flag-folds 2.8s ease-in-out infinite}
@keyframes ak-sudan-flag-wave{0%,100%{transform:perspective(120px) rotateY(0deg) skewY(0deg) scaleX(1)}25%{transform:perspective(120px) rotateY(-12deg) skewY(1.5deg) scaleX(.96)}50%{transform:perspective(120px) rotateY(10deg) skewY(-1deg) scaleX(.98)}75%{transform:perspective(120px) rotateY(-7deg) skewY(.8deg) scaleX(.97)}}
@keyframes ak-sudan-flag-folds{0%,100%{transform:translateX(-12%)}50%{transform:translateX(12%)}}
@media (prefers-reduced-motion:reduce){.org-header-banner .org-flag,.org-header-banner .org-flag::after,.app-footer .org-flag,.app-footer .org-flag::after{animation:none}}
.ak-leave-request-moving{visibility:hidden}
</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.AK_LANG=<?php echo json_encode(AK_LANG,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
window.AK_TRANSLATIONS=<?php echo json_encode(ak_dict(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
window.AK_BACK_FALLBACK=<?php echo json_encode(APP_URL . dashboard_for_role(current_user_role()), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo asset('js/language.js'); ?>"></script>
<script src="<?php echo asset('js/app.js'); ?>"></script>
<script src="<?php echo asset('js/notifications.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_reply_tools.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_ui_fixes.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_ui_cleanup.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_attachments.js'); ?>"></script>
<script src="<?php echo asset('js/messaging_message_delete.js'); ?>"></script>
<script src="<?php echo asset('js/searchable_sponsorship_select.js'); ?>"></script>
<script src="<?php echo asset('js/notification_unread_indicator.js'); ?>"></script>
<script src="<?php echo asset('js/transaction_details_localization.js'); ?>"></script>
<?php if (($active ?? '') === 'fm_dashboard'): ?><script src="<?php echo asset('js/fm_dashboard_layout.js'); ?>"></script><?php endif; ?>
<?php if (($active ?? '') === 'fm_dashboard'): ?><script src="<?php echo asset('js/fina_dashboard_widget.js'); ?>"></script><?php endif; ?>
<script>
(function(){
    var btn = document.getElementById('akSidebarToggle');
    var overlay = document.getElementById('sidebarOverlay');
    var mobileQuery = window.matchMedia('(max-width: 991.98px)');

    function setSidebar(open) {
        document.body.classList.toggle('sidebar-open', open);

        if (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('title', open
                ? (window.AK_LANG === 'ar' ? 'إخفاء القائمة' : 'Hide menu')
                : (window.AK_LANG === 'ar' ? 'إظهار القائمة' : 'Show menu'));

            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = 'fas ' + (open
                    ? (document.documentElement.dir === 'rtl' ? 'fa-chevron-right' : 'fa-chevron-left')
                    : 'fa-bars');
            }
        }

        document.body.style.overflow = mobileQuery.matches && open ? 'hidden' : '';
    }

    /* Keep the sidebar closed by default on every page; opening it remains user-controlled. */
    setSidebar(false);

    if (btn) {
        btn.addEventListener('click', function () {
            setSidebar(!document.body.classList.contains('sidebar-open'));
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            if (mobileQuery.matches) setSidebar(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            setSidebar(false);
            if (btn) btn.focus();
        }
    });

    document.querySelectorAll('#sidebar a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (mobileQuery.matches) setSidebar(false);
        });
    });

    window.addEventListener('resize', function () {
        if (mobileQuery.matches) {
            if (document.body.classList.contains('sidebar-open')) setSidebar(false);
        }
    });
})();



(function(){var deferred=null;var btn=document.getElementById('akInstallBtn');window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferred=e;if(btn)btn.classList.remove('d-none')});if(btn)btn.addEventListener('click',function(){if(!deferred)return;deferred.prompt();deferred.userChoice.then(function(){deferred=null;btn.classList.add('d-none')})});window.addEventListener('appinstalled',function(){if(btn)btn.classList.add('d-none')})})();
if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){})});}
function akCreateBackButton(fallback, extraClass, label){
    var button = document.createElement('a');
    button.href = fallback || '#';
    button.className = extraClass;
    button.setAttribute('onclick', 'return akGoBack(this.href);');
    button.setAttribute('aria-label', label);
    button.innerHTML = '<i class="fa-solid fa-arrow-right" aria-hidden="true"></i><span>' + label + '</span>';
    return button;
}
function akInstallBackButtons(){
    var content = document.querySelector('.content');
    if (!content) return;

    var path = window.location.pathname.replace(/\\/g, '/');
    var isDashboard = /(^|\/)dashboard\//i.test(path) || /(^|\/)modules\/accounting\/fm_dashboard\.php$/i.test(path);
    if (isDashboard) return;

    var existing = content.querySelector('a[onclick*="akGoBack("]');
    var fallback = window.AK_BACK_FALLBACK || window.location.origin + '/';
    var label = window.AK_LANG === 'ar' ? 'العودة' : 'Back';

    /*
     * Every audited HTML page must have two shared Back controls:
     * one at the top-left and one at the bottom-left.
     *
     * If the page already supplies a contextual Back control, keep it in
     * place.  It is used as the source for the top clone, and only create
     * a shared bottom control when the existing control is not already
     * positioned in the lower part of the content.
     */
    /*
     * The shared footer sits inside .content (the "</main>" that opens this
     * file closes nothing), so appending to .content would put the bottom
     * Back control AFTER the footer.  Find the footer's direct-child block so
     * the bottom control can be inserted before it instead.
     */
    var footer = document.querySelector('.app-footer');
    var footerBlock = null;
    if (footer && content.contains(footer)) {
        footerBlock = footer;
        while (footerBlock.parentElement && footerBlock.parentElement !== content) {
            footerBlock = footerBlock.parentElement;
        }
    }

    var existingIsNearBottom = false;
    if (existing) {
        var existingRect = existing.getBoundingClientRect();
        if (footer) {
            /* Existing Back control sits directly above the footer. */
            var footerRect = footer.getBoundingClientRect();
            existingIsNearBottom = existingRect.top < footerRect.top &&
                (footerRect.top - existingRect.bottom) <= 200;
        } else {
            var contentRect = content.getBoundingClientRect();
            existingIsNearBottom = existingRect.top >= contentRect.top + (contentRect.height * 0.60);
        }
    }

    if (!content.querySelector('.ak-top-back-wrap')) {
        var topWrap = document.createElement('div');
        topWrap.className = 'ak-top-back-wrap';
        var topButton = existing
            ? existing.cloneNode(true)
            : akCreateBackButton(fallback, 'btn btn-outline-secondary ak-top-back-btn', label);
        topButton.classList.add('ak-top-back-btn');
        topButton.removeAttribute('id');
        topButton.setAttribute('aria-label', label);
        topWrap.appendChild(topButton);
        content.insertBefore(topWrap, content.firstChild);
    }

    if (!existingIsNearBottom && !content.querySelector('.ak-bottom-back-wrap')) {
        var bottomWrap = document.createElement('div');
        bottomWrap.className = 'ak-bottom-back-wrap';
        bottomWrap.appendChild(existing
            ? existing.cloneNode(true)
            : akCreateBackButton(fallback, 'btn btn-outline-secondary ak-bottom-back-btn', label));
        var bottomButton = bottomWrap.querySelector('a');
        if (bottomButton) {
            bottomButton.classList.add('ak-bottom-back-btn');
            bottomButton.removeAttribute('id');
            bottomButton.setAttribute('aria-label', label);
        }
        if (footerBlock) {
            content.insertBefore(bottomWrap, footerBlock);
        } else {
            content.appendChild(bottomWrap);
        }
    }
}
function akGoBack(fallback){try{var ref=document.referrer;if(ref&&ref.indexOf(window.location.origin)===0&&window.history.length>1){window.history.back();return false;}}catch(e){} if(fallback){window.location.href=fallback;} return false;}
document.addEventListener('DOMContentLoaded', akInstallBackButtons);

</script>
</body>
</html>