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
    <?php
        $toastIcon = '';
        $toastClass = '';
        $toastMsg   = '';
        if (!empty($flash) && $flash['type'] !== 'pending' && empty($noToast)) {
            $toastClass = 'toast-' . e($flash['type']);
            $toastMsg   = e($flash['message']);
            $toastIcon  = $flash['type'] === 'success'
                ? '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>'
                : ($flash['type'] === 'warning'
                    ? '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>'
                    : '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>');
        }
    ?>
    <!-- #app-toast is always present so JS can trigger it dynamically -->
    <div id="app-toast" class="<?php echo $toastClass; ?>">
        <?php echo $toastIcon; ?>
        <?php if ($toastMsg): ?><span><?php echo $toastMsg; ?></span><?php endif; ?>
    </div>

    <!-- ===================================================
         JAVASCRIPT
         =================================================== -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>

    <script>
        // ── User Dropdown ────────────────────────────────────────
        function toggleUserMenu() {
            const menu  = document.getElementById('user-dropdown');
            const btn   = document.getElementById('user-menu-btn');
            const notif = document.getElementById('notif-dropdown');
            const bell  = document.getElementById('notif-bell-btn');
            if (notif) notif.classList.add('hidden');
            if (bell)  bell.setAttribute('aria-expanded', 'false');
            if (menu) {
                const open = menu.classList.toggle('hidden');
                if (btn) btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            }
        }
        document.addEventListener('click', function(e) {
            const btn  = document.getElementById('user-menu-btn');
            const menu = document.getElementById('user-dropdown');
            if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        // ── Notification Bell ────────────────────────────────────
        function toggleNotifDropdown() {
            const notif = document.getElementById('notif-dropdown');
            const bell  = document.getElementById('notif-bell-btn');
            const menu  = document.getElementById('user-dropdown');
            const uBtn  = document.getElementById('user-menu-btn');
            if (menu)  menu.classList.add('hidden');
            if (uBtn)  uBtn.setAttribute('aria-expanded', 'false');
            if (notif) {
                const open = notif.classList.toggle('hidden');
                if (bell) bell.setAttribute('aria-expanded', open ? 'false' : 'true');
            }
        }
        document.addEventListener('click', function(e) {
            const btn   = document.getElementById('notif-bell-btn');
            const notif = document.getElementById('notif-dropdown');
            if (notif && btn && !btn.contains(e.target) && !notif.contains(e.target)) {
                notif.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        // ── Notification Dismissal (localStorage) ────────────────
        (function () {
            const STORAGE_KEY = 'mt_notif_dismissed';

            function getDismissed() {
                try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
                catch (e) { return []; }
            }
            function markDismissed(key) {
                var list = getDismissed();
                if (!list.includes(key)) { list.push(key); localStorage.setItem(STORAGE_KEY, JSON.stringify(list)); }
            }
            function rebuildBell() {
                var dismissed = getDismissed();
                var allItems  = document.querySelectorAll('.nav-notif-item[data-key]');
                var visible = 0;
                allItems.forEach(function (item) {
                    if (!dismissed.includes(item.dataset.key)) visible++;
                });
                var badge      = document.getElementById('nav-notif-badge');
                var hCount     = document.getElementById('notif-header-count');
                var emptyEl    = document.getElementById('notif-empty-state');
                var list       = document.querySelector('.nav-notif-list');
                var footerLink = document.getElementById('notif-footer-link');
                if (badge) { badge.textContent = visible; badge.style.display = visible > 0 ? '' : 'none'; }
                if (hCount) { hCount.textContent = visible + ' รายการ'; hCount.style.display = visible > 0 ? '' : 'none'; }
                if (emptyEl && list) {
                    if (visible === 0) { list.style.display = 'none'; emptyEl.style.display = 'flex'; if (footerLink) footerLink.style.display = 'none'; }
                    else               { list.style.display = '';     emptyEl.style.display = 'none'; if (footerLink) footerLink.style.display = ''; }
                }
            }
            function dismissItem(key, cid, sid) {
                markDismissed(key);
                // Fade out notification item
                var item = document.querySelector('.nav-notif-item[data-key="' + key + '"]');
                if (item) {
                    item.style.transition = 'opacity 0.2s, max-height 0.3s';
                    item.style.opacity    = '0';
                    item.style.overflow   = 'hidden';
                    setTimeout(function () { item.style.maxHeight = '0'; item.style.padding = '0'; }, 200);
                    setTimeout(function () { item.style.display = 'none'; rebuildBell(); }, 400);
                } else {
                    rebuildBell();
                }
                // Fade out card badge (rejected submissions only)
                if (cid && sid) {
                    var scene = document.querySelector('.ch-quest-flip-scene[data-cid="' + cid + '"]');
                    if (scene && scene.dataset.sid === String(sid)) {
                        var badge = scene.querySelector('.ch-rejected-front-badge');
                        if (badge) { badge.style.transition = 'opacity 0.35s'; badge.style.opacity = '0'; setTimeout(function() { badge.style.display = 'none'; }, 350); }
                    }
                }
            }

            // Apply dismissals already stored → hide on page load
            (function applyStored() {
                var dismissed = getDismissed();
                dismissed.forEach(function (key) {
                    var item = document.querySelector('.nav-notif-item[data-key="' + key + '"]');
                    if (item) { item.style.display = 'none'; }
                    // For rejected submissions: also hide card badge
                    if (key.indexOf('sub_rej_') === 0) {
                        var sid = key.replace('sub_rej_', '');
                        document.querySelectorAll('.ch-quest-flip-scene[data-sid="' + sid + '"]').forEach(function (s) {
                            var badge = s.querySelector('.ch-rejected-front-badge');
                            if (badge) badge.style.display = 'none';
                        });
                    }
                });
                rebuildBell();
            })();

            // Click on notification item → dismiss + navigate
            document.addEventListener('click', function (e) {
                if (!e.target || typeof e.target.closest !== 'function') return;
                var item = e.target.closest('.nav-notif-item[data-key]');
                if (!item) return;
                var key = item.dataset.key;
                var cid = item.dataset.cid || '';
                var sid = item.dataset.sid || '';
                markDismissed(key);
                if (cid && sid) {
                    var scene = document.querySelector('.ch-quest-flip-scene[data-cid="' + cid + '"]');
                    if (scene && scene.dataset.sid === String(sid)) {
                        var badge = scene.querySelector('.ch-rejected-front-badge');
                        if (badge) { badge.style.transition = 'opacity 0.25s'; badge.style.opacity = '0'; setTimeout(function() { badge.style.display = 'none'; }, 250); }
                    }
                }
                rebuildBell();
                // Navigation happens naturally via <a href>
            });

            // Hover on rejected card → dismiss
            document.querySelectorAll('.ch-quest-flip-scene[data-rejected]').forEach(function (scene) {
                scene.addEventListener('mouseenter', function () {
                    var key = 'sub_rej_' + scene.dataset.sid;
                    dismissItem(key, scene.dataset.cid, scene.dataset.sid);
                }, { once: true }); // fire only once per session
            });

            // Hover on any notification dropdown item → dismiss it
            document.addEventListener('mouseenter', function (e) {
                if (!e.target || typeof e.target.closest !== 'function') return;
                var item = e.target.closest('.nav-notif-item[data-key]');
                if (!item) return;
                var key = item.dataset.key;
                var cid = item.dataset.cid || '';
                var sid = item.dataset.sid || '';
                dismissItem(key, cid, sid);
            }, true); // use capture so it fires reliably inside dropdown
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
