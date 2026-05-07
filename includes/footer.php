    </main><!-- end .max-w-7xl main -->

    <!-- ===================================================
         FOOTER
         =================================================== -->
    <footer class="site-footer">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="site-footer-brand">JOURNAL</span>
                    <span class="site-footer-divider w-px h-4 inline-block"></span>
                    <span class="site-footer-sub">Mission Token v<?php echo APP_VERSION; ?></span>
                </div>
                <p class="site-footer-sub">
                    © <?php echo date('Y'); ?> JOURNAL. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <!-- ===================================================
         GLOBAL TOAST
         =================================================== -->
    <?php if (!empty($flash)): ?>
    <?php
        $toastIcon = $flash['type'] === 'success'
            ? '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'
            : ($flash['type'] === 'warning'
                ? '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>'
                : '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>');
    ?>
    <div id="app-toast" class="toast-<?php echo e($flash['type']); ?>">
        <?php echo $toastIcon; ?>
        <span><?php echo e($flash['message']); ?></span>
    </div>
    <?php endif; ?>

    <!-- ===================================================
         JAVASCRIPT
         =================================================== -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>

    <script>
        // ── User Dropdown ────────────────────────────────────────
        function toggleUserMenu() {
            document.getElementById('user-dropdown').classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const btn  = document.getElementById('user-menu-btn');
            const menu = document.getElementById('user-dropdown');
            if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // ── Mobile Menu ──────────────────────────────────────────
        function toggleMobileMenu() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        }

        // ── Global toast (driven by $flash set in header.php) ──
        (function(){
            var t = document.getElementById('app-toast');
            if (!t) return;
            requestAnimationFrame(function(){
                requestAnimationFrame(function(){ t.classList.add('show'); });
            });
            setTimeout(function(){
                t.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                t.style.opacity = '0';
                t.style.transform = 'translate(-50%,-50%) scale(0.9)';
            }, 3000);
        })();

        // ── CSRF header for all fetch() requests ─────────────────
        const _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const _fetchJSON = (url, data) => fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': _csrfToken
            },
            body: JSON.stringify(data)
        }).then(r => r.json());
    </script>
</body>
</html>
