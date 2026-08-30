</main>
    <footer class="app-footer text-center text-muted py-3">
        <div class="container">
            <!-- Organization Info & Social Media -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
                        <span class="fw-bold" style="color: var(--navy, #1b4d8f);">
                            🇸🇩 منظمة أهل الخير النسوية لكفالة الأيتام
                        </span>
                        <span class="text-muted">|</span>
                        <a href="https://wa.me/249900008247" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                            <span class="text-muted small">واتساب</span>
                        </a>
                        <span class="text-muted">|</span>
                        <a href="https://www.tiktok.com/@ahlalkheir1" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <i class="fab fa-tiktok"></i>
                            <span class="text-muted small">تيك توك</span>
                        </a>
                        <span class="text-muted">|</span>
                        <a href="https://www.instagram.com/ahlalkhair9/" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <i class="fab fa-instagram" style="color: #E4405F;"></i>
                            <span class="text-muted small">انستغرام</span>
                        </a>
                        <span class="text-muted">|</span>
                        <a href="https://www.facebook.com/61576384876429" target="_blank" rel="noopener" class="text-decoration-none d-inline-flex align-items-center gap-1">
                            <i class="fab fa-facebook" style="color: #1877F2;"></i>
                            <span class="text-muted small">فيسبوك</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Copyright -->
            <div class="row mt-3">
                <div class="col-12">
                    <small>
                        Ahl El Kheir Charity Management System &copy; <?php echo date('Y'); ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
</div>
</div>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<?php include __DIR__ . '/age_alert.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo asset('js/app.js'); ?>"></script>
<script>
/* ========== Session-4: PWA install button ========== */
(function(){
    var deferred = null;
    var btn = document.getElementById('akInstallBtn');
    window.addEventListener('beforeinstallprompt', function(e){
        e.preventDefault();
        deferred = e;
        if (btn) btn.classList.remove('d-none');
    });
    if (btn) btn.addEventListener('click', function(){
        if (!deferred) return;
        deferred.prompt();
        deferred.userChoice.then(function(){ deferred = null; btn.classList.add('d-none'); });
    });
    window.addEventListener('appinstalled', function(){ if (btn) btn.classList.add('d-none'); });
})();
/* ========== Session-4: register service worker ========== */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
        navigator.serviceWorker.register('<?php echo APP_URL; ?>sw.js').catch(function(){});
    });
}
</script>
</body>
</html>