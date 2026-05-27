    </main><!-- end .max-w-7xl main -->

    <!-- ===================================================
         BOTTOM NAVIGATION BAR  (mobile only, logged-in users)
         =================================================== -->
    <?php if (!empty($_SESSION['employee_id'])):
        $_bnavIsHr    = isset($isAdminOrHr) && $isAdminOrHr && isset($isAdminPage) && $isAdminPage;
        $_bnavActive  = $activePage ?? '';
        $_bnavMorePgs = ['admin_challenges', 'admin_rewards', 'admin_employees'];
        $_bnavMoreOn  = in_array($_bnavActive, $_bnavMorePgs, true);
        $_bnavPendSub = $pendingCount ?? 0;
        $_bnavPendRed = $pendingRedemptionCount ?? 0;
    ?>

    <?php if ($_bnavIsHr): ?>
    <!-- HR More — overlay + bottom sheet -->
    <div class="bnav-overlay" id="bnav-overlay" aria-hidden="true"></div>
    <div class="bnav-sheet" id="bnav-sheet" role="dialog" aria-modal="true" aria-label="เมนูเพิ่มเติม" aria-hidden="true">
        <div class="bnav-sheet-handle"></div>
        <p class="bnav-sheet-title">เมนูเพิ่มเติม</p>
        <div class="bnav-sheet-links">
            <a href="<?= BASE_URL ?>/hr/challenges/index.php"
               class="bnav-sheet-link <?= $_bnavActive === 'admin_challenges' ? 'active' : '' ?>">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
                </svg>
                จัดการภารกิจ
            </a>
            <a href="<?= BASE_URL ?>/hr/rewards/index.php"
               class="bnav-sheet-link <?= $_bnavActive === 'admin_rewards' ? 'active' : '' ?>">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><path d="M12 22V7"/>
                </svg>
                จัดการรางวัล
            </a>
            <a href="<?= BASE_URL ?>/hr/employees.php"
               class="bnav-sheet-link <?= $_bnavActive === 'admin_employees' ? 'active' : '' ?>">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                </svg>
                จัดการพนักงาน
            </a>
        </div>
    </div>
    <?php endif; ?>

    <nav class="bnav-bar" id="bnav-bar" role="navigation" aria-label="เมนูหลัก">

        <?php if ($_bnavIsHr): ?>
        <!-- ── HR: 5 tabs ─────────────────────────────── -->
        <a href="<?= BASE_URL ?>/hr/dashboard.php"
           class="bnav-item <?= $_bnavActive === 'admin_dashboard' ? 'active' : '' ?>"
           aria-label="ภาพรวม" aria-current="<?= $_bnavActive === 'admin_dashboard' ? 'page' : 'false' ?>">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9,22 9,12 15,12 15,22"/>
            </svg>
            <span>ภาพรวม</span>
        </a>

        <a href="<?= BASE_URL ?>/hr/submissions.php"
           class="bnav-item <?= $_bnavActive === 'admin_submissions' ? 'active' : '' ?>"
           aria-label="อนุมัติงาน" aria-current="<?= $_bnavActive === 'admin_submissions' ? 'page' : 'false' ?>">
            <?php if ($_bnavPendSub > 0): ?>
            <span class="bnav-badge"><?= $_bnavPendSub ?></span>
            <?php endif; ?>
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <span>อนุมัติงาน</span>
        </a>

        <a href="<?= BASE_URL ?>/hr/qrcodes.php"
           class="bnav-item <?= $_bnavActive === 'admin_qrcodes' ? 'active' : '' ?>"
           aria-label="QR Token" aria-current="<?= $_bnavActive === 'admin_qrcodes' ? 'page' : 'false' ?>">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
                <path d="M14 14h3v3h-3zM17 17h3v3M14 20h3"/>
            </svg>
            <span>QR Token</span>
        </a>

        <a href="<?= BASE_URL ?>/hr/rewards/redemptions.php"
           class="bnav-item <?= $_bnavActive === 'admin_redemptions' ? 'active' : '' ?>"
           aria-label="คำขอแลกรางวัล" aria-current="<?= $_bnavActive === 'admin_redemptions' ? 'page' : 'false' ?>">
            <?php if ($_bnavPendRed > 0): ?>
            <span class="bnav-badge"><?= $_bnavPendRed ?></span>
            <?php endif; ?>
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 12 20 22 4 22 4 12"/>
                <rect x="2" y="7" width="20" height="5"/>
                <path d="M12 22V7"/>
            </svg>
            <span>รางวัล</span>
        </a>

        <button class="bnav-item <?= $_bnavMoreOn ? 'active' : '' ?>"
                id="bnav-more-btn" aria-expanded="false" aria-label="เมนูเพิ่มเติม">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                <circle cx="5" cy="12" r="1.75"/>
                <circle cx="12" cy="12" r="1.75"/>
                <circle cx="19" cy="12" r="1.75"/>
            </svg>
            <span>เพิ่มเติม</span>
        </button>

        <?php else: ?>
        <!-- ── Employee: 4 tabs ───────────────────────── -->
        <a href="<?= BASE_URL ?>/pages/dashboard.php"
           class="bnav-item <?= $_bnavActive === 'dashboard' ? 'active' : '' ?>"
           aria-label="หน้าแรก" aria-current="<?= $_bnavActive === 'dashboard' ? 'page' : 'false' ?>">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                <polyline points="9,22 9,12 15,12 15,22"/>
            </svg>
            <span>หน้าแรก</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/challenges.php"
           class="bnav-item <?= $_bnavActive === 'challenges' ? 'active' : '' ?>"
           aria-label="ภารกิจ" aria-current="<?= $_bnavActive === 'challenges' ? 'page' : 'false' ?>">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
            </svg>
            <span>ภารกิจ</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/rewards.php"
           class="bnav-item <?= $_bnavActive === 'rewards' ? 'active' : '' ?>"
           aria-label="ร้านรางวัล" aria-current="<?= $_bnavActive === 'rewards' ? 'page' : 'false' ?>">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 12 20 22 4 22 4 12"/>
                <rect x="2" y="7" width="20" height="5"/>
                <path d="M12 22V7"/>
            </svg>
            <span>ร้านรางวัล</span>
        </a>

        <a href="<?= BASE_URL ?>/pages/history.php"
           class="bnav-item <?= $_bnavActive === 'history' ? 'active' : '' ?>"
           aria-label="ประวัติ" aria-current="<?= $_bnavActive === 'history' ? 'page' : 'false' ?>">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <span>ประวัติ</span>
        </a>
        <?php endif; ?>

    </nav>

    <script>
    (function () {
        var btn     = document.getElementById('bnav-more-btn');
        var sheet   = document.getElementById('bnav-sheet');
        var overlay = document.getElementById('bnav-overlay');
        if (!btn || !sheet || !overlay) return;
        function open()  {
            sheet.classList.add('open');
            overlay.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
            sheet.setAttribute('aria-hidden', 'false');
        }
        function close() {
            sheet.classList.remove('open');
            overlay.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            sheet.setAttribute('aria-hidden', 'true');
        }
        btn.addEventListener('click', function () {
            sheet.classList.contains('open') ? close() : open();
        });
        overlay.addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    }());
    </script>

    <?php endif; ?>

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
    <div id="app-toast" class="<?php echo $toastClass; ?>" role="status" aria-live="polite" aria-atomic="true" <?php echo empty($toastMsg) ? 'aria-hidden="true"' : ''; ?>>
        <?php echo $toastIcon; ?>
        <?php if ($toastMsg): ?><span><?php echo $toastMsg; ?></span><?php endif; ?>
    </div>

    <!-- ===================================================
         JAVASCRIPT
         =================================================== -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/footer.js"></script>
</body>
</html>
