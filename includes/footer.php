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

        // ── Auto-dismiss Flash (5s) ──────────────────────────────
        setTimeout(function() {
            const flash = document.getElementById('flash-msg');
            if (flash) {
                flash.style.transition = 'opacity 0.6s';
                flash.style.opacity    = '0';
                setTimeout(function() {
                    const wrap = document.getElementById('flash-wrap');
                    if (wrap) wrap.remove();
                }, 600);
            }
        }, 5000);

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
