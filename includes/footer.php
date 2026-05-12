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
    <?php if (!empty($flash) && $flash['type'] !== 'pending' && empty($noToast)): ?>
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
            const menu  = document.getElementById('user-dropdown');
            const notif = document.getElementById('notif-dropdown');
            if (notif) notif.classList.add('hidden');
            if (menu) menu.classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const btn  = document.getElementById('user-menu-btn');
            const menu = document.getElementById('user-dropdown');
            if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // ── Notification Bell ────────────────────────────────────
        function toggleNotifDropdown() {
            const notif = document.getElementById('notif-dropdown');
            const menu  = document.getElementById('user-dropdown');
            if (menu) menu.classList.add('hidden');
            if (notif) notif.classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            const btn   = document.getElementById('notif-bell-btn');
            const notif = document.getElementById('notif-dropdown');
            if (notif && btn && !btn.contains(e.target) && !notif.contains(e.target)) {
                notif.classList.add('hidden');
            }
        });

        // ── Notification Dismissal (localStorage) ────────────────
        (function () {
            const STORAGE_KEY = 'mt_notif_dismissed';

            function getDismissed() {
                try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
                catch (e) { return []; }
            }
            function markDismissed(cid, sid) {
                const list = getDismissed();
                const key  = cid + '_' + sid;
                if (!list.includes(key)) { list.push(key); localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); }
            }
            function rebuildBell() {
                const dismissed = getDismissed();
                const allItems  = document.querySelectorAll('.nav-notif-item[data-cid]');
                let visible = 0;
                allItems.forEach(function (item) {
                    if (!dismissed.includes(item.dataset.cid + '_' + item.dataset.sid)) visible++;
                });
                const badge      = document.getElementById('nav-notif-badge');
                const hCount     = document.getElementById('notif-header-count');
                const emptyEl    = document.getElementById('notif-empty-state');
                const list       = document.querySelector('.nav-notif-list');
                const footerLink = document.getElementById('notif-footer-link');
                if (badge) { badge.textContent = visible; badge.style.display = visible > 0 ? '' : 'none'; }
                if (hCount) { hCount.textContent = visible + ' รายการ'; hCount.style.display = visible > 0 ? '' : 'none'; }
                if (emptyEl && list) {
                    if (visible === 0) { list.style.display = 'none'; emptyEl.style.display = 'flex'; if (footerLink) footerLink.style.display = 'none'; }
                    else               { list.style.display = '';     emptyEl.style.display = 'none'; if (footerLink) footerLink.style.display = ''; }
                }
            }
            function dismissItem(cid, sid) {
                markDismissed(cid, sid);
                // Fade out notification item
                const item = document.querySelector('.nav-notif-item[data-cid="' + cid + '"][data-sid="' + sid + '"]');
                if (item) {
                    item.style.transition = 'opacity 0.2s, max-height 0.3s';
                    item.style.opacity    = '0';
                    item.style.overflow   = 'hidden';
                    setTimeout(function () { item.style.maxHeight = '0'; item.style.padding = '0'; }, 200);
                    setTimeout(function () { item.style.display = 'none'; rebuildBell(); }, 400);
                } else {
                    rebuildBell();
                }
                // Fade out card badge (only if sid matches current card's rejected submission)
                const scene = document.querySelector('.ch-quest-flip-scene[data-cid="' + cid + '"]');
                if (scene && scene.dataset.sid === String(sid)) {
                    const badge = scene.querySelector('.ch-rejected-front-badge');
                    if (badge) { badge.style.transition = 'opacity 0.35s'; badge.style.opacity = '0'; setTimeout(function() { badge.style.display = 'none'; }, 350); }
                }
            }

            // Apply dismissals already stored → hide on page load
            (function applyStored() {
                const dismissed = getDismissed();
                dismissed.forEach(function (key) {
                    // key format: "cid_sid"
                    const parts = key.split('_');
                    const cid   = parts[0];
                    const sid   = parts[1];
                    const item  = document.querySelector('.nav-notif-item[data-cid="' + cid + '"][data-sid="' + sid + '"]');
                    if (item) { item.style.display = 'none'; }
                    const scene = document.querySelector('.ch-quest-flip-scene[data-cid="' + cid + '"]');
                    if (scene && scene.dataset.sid === sid) {
                        const badge = scene.querySelector('.ch-rejected-front-badge');
                        if (badge) badge.style.display = 'none';
                    }
                });
                rebuildBell();
            })();

            // Click on notification item → dismiss + navigate
            document.addEventListener('click', function (e) {
                const item = e.target.closest('.nav-notif-item[data-cid]');
                if (!item) return;
                const cid = item.dataset.cid;
                const sid = item.dataset.sid;
                markDismissed(cid, sid);
                // Fade badge on card immediately (only if sid matches)
                const scene = document.querySelector('.ch-quest-flip-scene[data-cid="' + cid + '"]');
                if (scene && scene.dataset.sid === String(sid)) {
                    const badge = scene.querySelector('.ch-rejected-front-badge');
                    if (badge) { badge.style.transition = 'opacity 0.25s'; badge.style.opacity = '0'; setTimeout(function() { badge.style.display = 'none'; }, 250); }
                }
                // Update bell count immediately then navigate
                rebuildBell();
                // Navigation happens naturally via <a href>
            });

            // Hover on rejected card → dismiss
            document.querySelectorAll('.ch-quest-flip-scene[data-rejected]').forEach(function (scene) {
                scene.addEventListener('mouseenter', function () {
                    dismissItem(scene.dataset.cid, scene.dataset.sid);
                }, { once: true }); // fire only once per session
            });
        })();

        // ── Mobile Menu ──────────────────────────────────────────
        function toggleMobileMenu() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        }

        // ── Challenge highlight: unseen new / rejected ─────────────
        (function () {
            const SEEN_KEY = 'mt_seen_challenges';
            function getSeen() {
                try { return JSON.parse(localStorage.getItem(SEEN_KEY) || '[]'); }
                catch (e) { return []; }
            }
            function markSeen(cid) {
                var list = getSeen(), key = String(cid);
                if (!list.includes(key)) { list.push(key); localStorage.setItem(SEEN_KEY, JSON.stringify(list)); }
            }
            var seen = getSeen();
            document.querySelectorAll('.ch-quest-flip-scene[data-cid]').forEach(function (scene) {
                var cid = scene.dataset.cid;
                if (seen.includes(cid)) return;
                scene.dataset.highlight = scene.hasAttribute('data-rejected') ? 'rejected' : 'new';
                function dismiss() {
                    scene.removeAttribute('data-highlight');
                    markSeen(cid);
                }
                scene.addEventListener('mouseenter', dismiss, { once: true });
                scene.addEventListener('click',      dismiss, { once: true });
            });
        })();

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
