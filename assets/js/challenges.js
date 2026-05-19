/* challenges.js — Quest Board + Quiz page interactions
 * Depends on: app.js, footer.js (loaded by footer.php)
 * Data hydration: window._stravaModalData, window._stravaConnected,
 *                 window._quizModalData, window._photoModalData
 *                 must be set inline before this file loads.
 */
(function () {
    'use strict';

    var _photoModalLastFocus = null;
    var _stravaModalLastFocus = null;
    var _quizModalLastFocus = null;
    var _photoTrap = null;
    var _stravaTrap = null;
    var _quizTrap = null;

    function mtDelay(ms) {
        if (window.mtMotion && typeof window.mtMotion.delay === 'function') {
            return window.mtMotion.delay(ms);
        }
        return ms;
    }

    function mtScrollBehavior() {
        if (window.mtMotion && typeof window.mtMotion.scrollBehavior === 'function') {
            return window.mtMotion.scrollBehavior();
        }
        return 'smooth';
    }

    function setPhotoFileCount(input) {
        var countEl = document.getElementById('pm-file-count');
        if (!countEl || !input) return;
        var total = input.files ? input.files.length : 0;
        countEl.textContent = total > 0 ? 'เลือกแล้ว ' + total + ' ไฟล์' : '';
    }

    function bindPhotoDropzone() {
        var zone = document.getElementById('pm-dropzone');
        var input = document.getElementById('pm-photo-input');
        if (!zone || !input || zone.dataset.bound === 'true') return;
        zone.dataset.bound = 'true';

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, preventDefaults);
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            zone.addEventListener(name, function () {
                zone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            zone.addEventListener(name, function () {
                zone.classList.remove('is-dragover');
            });
        });

        zone.addEventListener('drop', function (e) {
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (!files || !files.length) return;
            var picked = Array.prototype.slice.call(files, 0, 5);
            if (typeof DataTransfer !== 'undefined') {
                var dt = new DataTransfer();
                picked.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            }
            setPhotoFileCount(input);
        });

        zone.addEventListener('click', function (e) {
            if (e.target === input) return;
            input.click();
        });

        zone.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            input.click();
        });

        input.addEventListener('change', function () {
            setPhotoFileCount(input);
        });
    }

    /* ── Progress bar hydration (runs in both quiz view and list view) ─── */
    var quizProgressEl = document.getElementById('quiz-progress');
    if (quizProgressEl) {
        var initPct = parseInt(quizProgressEl.getAttribute('data-progress-init') || '0', 10);
        quizProgressEl.style.width = Math.max(0, Math.min(100, initPct)) + '%';
    }

    document.querySelectorAll('.ch-board-progress-fill[data-progress-width]').forEach(function (el) {
        var pct = parseInt(el.getAttribute('data-progress-width') || '0', 10);
        el.style.width = Math.max(0, Math.min(100, pct)) + '%';
    });

    /* ── Strava form submit ─────────────────────────────────────────────── */
    function submitStravaForm(formId, btn) {
        var f = document.getElementById(formId);
        if (!f) return;
        if (btn) btn.disabled = true;
        var overlay = document.getElementById('strava-loading-overlay');
        var sub     = document.getElementById('strava-loading-sub');
        if (overlay) overlay.classList.add('is-active');
        var msgs = [
            [8000,  'กำลังค้นหากิจกรรมที่ตรงเงื่อนไข...'],
            [20000, 'ใช้เวลานานกว่าปกติ Strava API อาจช้า กรุณารอสักครู่...'],
            [40000, 'เกือบเสร็จแล้ว กรุณาอย่าปิดหน้านี้...'],
        ];
        msgs.forEach(function (m) {
            setTimeout(function () { if (sub) sub.textContent = m[1]; }, m[0]);
        });
        f.submit();
    }

    /* ── Quiz step navigation (only when quiz form is present) ─────────── */
    (function () {
        var quizForm = document.getElementById('quiz-form');
        if (!quizForm) return;

        var totalQ = parseInt(quizForm.dataset.totalQ || '0', 10);

        function quizGoStep(idx) {
            var currentStep = document.querySelector('.quiz-step.active');
            var currentIdx  = currentStep ? parseInt(currentStep.dataset.step, 10) : -1;
            var forward     = idx > currentIdx;
            if (currentStep) currentStep.classList.remove('active', 'step-enter-fwd', 'step-enter-back');
            var step = document.getElementById('step-' + idx);
            if (!step) return;
            step.classList.add('active', forward ? 'step-enter-fwd' : 'step-enter-back');
            setTimeout(function () { step.classList.remove('step-enter-fwd', 'step-enter-back'); }, mtDelay(340));
            document.getElementById('q-current').textContent = idx + 1;
            var pct = Math.round(((idx + 1) / totalQ) * 100);
            document.getElementById('quiz-progress').style.width = pct + '%';
            var checked = step.querySelector('input[type="radio"]:checked');
            if (checked) {
                var nb = document.getElementById('next-' + idx);
                if (nb) nb.disabled = false;
                var sb = document.getElementById('quiz-submit-btn');
                if (sb && idx === totalQ - 1) sb.disabled = false;
            }
            step.scrollIntoView({ behavior: mtScrollBehavior(), block: 'nearest' });
        }
        // expose for data-action="quiz-go-step" handled by event dispatcher below
        window._chQuizGoStep = quizGoStep;

        /* Option card click */
        document.querySelectorAll('.quiz-opt').forEach(function (label) {
            if (!label.hasAttribute('tabindex')) {
                label.setAttribute('tabindex', '0');
            }
            label.addEventListener('click', function () {
                var radio  = this.querySelector('input[type="radio"]');
                if (!radio) return;
                var stepEl  = this.closest('.quiz-step');
                var stepIdx = parseInt(stepEl.dataset.step, 10);
                stepEl.querySelectorAll('.quiz-opt').forEach(function (l) { l.classList.remove('selected'); });
                radio.checked = true;
                this.classList.add('selected');
                var nb = document.getElementById('next-' + stepIdx);
                if (nb) nb.disabled = false;
                var sb = document.getElementById('quiz-submit-btn');
                if (sb && stepIdx === totalQ - 1) sb.disabled = false;
            });
            label.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                this.click();
            });
        });

        /* Processing modal */
        function showProcessingModal() {
            var modal  = document.getElementById('quiz-processing-modal');
            modal.style.display = 'flex';
            var dotsEl = document.getElementById('qpm-dots');
            var base   = 'กรุณารอสักครู่';
            var tick   = 0;
            return setInterval(function () {
                tick = (tick + 1) % 4;
                dotsEl.textContent = base + '.'.repeat(tick);
            }, 280);
        }

        /* Submit handler */
        quizForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var form = this;

            var confirmed = window.confirm(
                'ยืนยันการส่งคำตอบ?\n\nคุณมีสิทธิ์ทำ Quiz นี้ได้เพียง 1 ครั้ง ไม่สามารถแก้ไขหรือลองใหม่ได้'
            );
            if (!confirmed) return;

            var dotsTimer = showProcessingModal();
            function doSubmit() { clearInterval(dotsTimer); form.submit(); }
            function fireAndSubmit() {
                if (typeof confetti !== 'undefined') {
                    confetti({ particleCount: 70, spread: 65, origin: { y: 0.72 },
                               colors: ['#dab937', '#f8e769', '#c9a830', '#fdfcdf', '#3a3e43'],
                               scalar: 0.85, ticks: 130, gravity: 1.3 });
                }
                setTimeout(doSubmit, mtDelay(480));
            }
            setTimeout(function () {
                if (typeof confetti !== 'undefined') {
                    fireAndSubmit();
                } else {
                    var s = document.createElement('script');
                    s.src    = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js';
                    s.onload = fireAndSubmit;
                    s.onerror = doSubmit;
                    document.head.appendChild(s);
                }
            }, 1050);
        });
    }());

    /* ── Photo modal ────────────────────────────────────────────────────── */
    function openPhotoModal(cid) {
        var _photoModalData = window._photoModalData || {};
        var d = _photoModalData[cid];
        if (!d) return;

        _photoModalLastFocus = document.activeElement;

        document.getElementById('pm-token').textContent = '+' + d.token.toLocaleString() + ' Token';
        document.getElementById('pm-title').textContent = d.title;

        var descEl = document.getElementById('pm-desc');
        descEl.textContent    = d.desc;
        descEl.style.display  = d.desc ? '' : 'none';

        var instrEl = document.getElementById('pm-instructions');
        if (d.instructions) {
            document.getElementById('pm-instructions-text').textContent = d.instructions;
            instrEl.classList.remove('ch-u-hidden');
        } else {
            instrEl.classList.add('ch-u-hidden');
        }

        var edEl = document.getElementById('pm-enddate');
        if (d.ed) {
            var txt = 'สิ้นสุด ' + d.ed;
            if (d.daysLeft !== null && d.daysLeft >= 0 && d.daysLeft <= 7) {
                txt += ' · เหลืออีก ' + (d.daysLeft === 0 ? 'วันนี้!' : d.daysLeft + ' วัน');
            }
            edEl.textContent   = txt;
            edEl.style.display = '';
        } else {
            edEl.style.display = 'none';
        }

        var rejEl = document.getElementById('pm-rejected-msg');
        if (d.rejected) {
            rejEl.textContent = 'งานก่อนหน้าถูกปฏิเสธ — สามารถส่งใหม่ได้';
            rejEl.classList.remove('ch-u-hidden');
        } else {
            rejEl.classList.add('ch-u-hidden');
        }

        document.getElementById('pm-cid-input').value = cid;
        document.getElementById('pm-csrf').innerHTML  = d.csrfField;
        var pmInput = document.getElementById('pm-photo-input');
        if (pmInput) {
            pmInput.value = '';
            setPhotoFileCount(pmInput);
        }

        var overlay = document.getElementById('photo-modal');
        var card    = document.getElementById('photo-modal-card');
        overlay.classList.remove('ch-u-hidden');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
        overlay.classList.remove('modal-overlay-out');
        card.classList.remove('modal-card-out');
        overlay.classList.add('modal-overlay-in');
        card.classList.add('modal-card-in');
        document.body.style.overflow = 'hidden';
        if (_photoTrap && typeof _photoTrap.release === 'function') _photoTrap.release();
        if (window.mtModalFocusTrap) {
            _photoTrap = window.mtModalFocusTrap.activate(overlay, card);
        }
        setTimeout(function () {
            var firstFocus = card.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocus) firstFocus.focus();
        }, 0);
    }

    function closePhotoModal() {
        var overlay = document.getElementById('photo-modal');
        if (!overlay) return;
        var card    = document.getElementById('photo-modal-card');
        overlay.classList.remove('modal-overlay-in');
        card.classList.remove('modal-card-in');
        overlay.classList.add('modal-overlay-out');
        card.classList.add('modal-card-out');
        setTimeout(function () {
            overlay.style.display = 'none';
            overlay.classList.add('ch-u-hidden');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (_photoTrap && typeof _photoTrap.release === 'function') {
                _photoTrap.release();
                _photoTrap = null;
            }
            if (_photoModalLastFocus && typeof _photoModalLastFocus.focus === 'function') {
                _photoModalLastFocus.focus();
            }
        }, mtDelay(180));
    }

    /* ── Strava modal ───────────────────────────────────────────────────── */
    function openStravaModal(cid) {
        var _stravaModalData = window._stravaModalData || {};
        var _stravaConnected = window._stravaConnected || false;
        var d = _stravaModalData[cid];
        if (!d) return;

        _stravaModalLastFocus = document.activeElement;

        document.getElementById('sm-token').textContent = '+' + d.token.toLocaleString() + ' Token';
        document.getElementById('sm-title').textContent = d.title;
        var descEl = document.getElementById('sm-desc');
        descEl.textContent   = d.desc;
        descEl.style.display = d.desc ? 'block' : 'none';

        var scDiv  = document.getElementById('sm-condition');
        var scText = document.getElementById('sm-condition-text');
        var sc = d.sc || {};
        if (sc.sport_type || sc.min_distance || sc.min_moving_time || sc.min_elevation) {
            var parts = [];
            if (sc.sport_type)     parts.push(sc.sport_type);
            if (sc.min_distance)   parts.push('\u2265' + (sc.min_distance / 1000).toFixed(1) + ' \u0e01\u0e21.');
            if (sc.min_moving_time) parts.push('\u2265' + Math.floor(sc.min_moving_time / 60) + ' \u0e19\u0e32\u0e17\u0e35');
            if (sc.min_elevation)  parts.push('\u0e04\u0e27\u0e32\u0e21\u0e2a\u0e39\u0e07 \u2265' + sc.min_elevation + ' \u0e21.');
            scText.textContent = parts.join(' \u00b7 ');
            scDiv.classList.remove('ch-u-hidden');
        } else {
            scDiv.classList.add('ch-u-hidden');
        }

        var edEl = document.getElementById('sm-enddate');
        if (d.ed) {
            var txt = '\u0e2a\u0e34\u0e49\u0e19\u0e2a\u0e38\u0e14 ' + d.ed;
            if (d.daysLeft !== null && d.daysLeft >= 0 && d.daysLeft <= 7) {
                txt += ' \u00b7 ' + (d.daysLeft === 0 ? '\u0e27\u0e31\u0e19\u0e19\u0e35\u0e49!' : '\u0e40\u0e2b\u0e25\u0e37\u0e2d\u0e2d\u0e35\u0e01 ' + d.daysLeft + ' \u0e27\u0e31\u0e19');
            }
            edEl.textContent   = txt;
            edEl.style.display = 'block';
        } else {
            edEl.style.display = 'none';
        }

        var rejMsg = document.getElementById('sm-rejected-msg');
        if (d.rejected) {
            rejMsg.textContent = '\u0e44\u0e21\u0e48\u0e1e\u0e1a\u0e01\u0e34\u0e08\u0e01\u0e23\u0e23\u0e21\u0e17\u0e35\u0e48\u0e15\u0e23\u0e07\u0e40\u0e07\u0e37\u0e48\u0e2d\u0e19\u0e44\u0e02 \u2022 \u0e25\u0e2d\u0e07\u0e43\u0e2b\u0e21\u0e48\u0e44\u0e14\u0e49';
            rejMsg.classList.remove('ch-u-hidden');
        } else {
            rejMsg.classList.add('ch-u-hidden');
        }

        var connectEl = document.getElementById('sm-connect-area');
        var formEl    = document.getElementById('sm-strava-form');
        var submitBtn = document.getElementById('sm-submit-btn');
        if (!_stravaConnected) {
            connectEl.classList.remove('ch-u-hidden');
            formEl.classList.add('ch-u-hidden');
        } else {
            connectEl.classList.add('ch-u-hidden');
            formEl.classList.remove('ch-u-hidden');
            document.getElementById('sm-cid-input').value = cid;
            submitBtn.innerHTML = d.rejected
                ? '\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e2d\u0e35\u0e01\u0e04\u0e23\u0e31\u0e49\u0e07'
                : '\u0e15\u0e23\u0e27\u0e08\u0e2a\u0e2d\u0e1a\u0e01\u0e34\u0e08\u0e01\u0e23\u0e23\u0e21 Strava';
            submitBtn.disabled = false;
        }

        var modal = document.getElementById('strava-modal');
        var card  = document.getElementById('strava-modal-card');
        modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
        modal.classList.remove('ch-u-hidden');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        void card.offsetWidth;
        modal.classList.add('modal-overlay-in');
        card.classList.add('modal-card-in');
        if (_stravaTrap && typeof _stravaTrap.release === 'function') _stravaTrap.release();
        if (window.mtModalFocusTrap) {
            _stravaTrap = window.mtModalFocusTrap.activate(modal, card);
        }
        setTimeout(function () {
            var firstFocus = card.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocus) firstFocus.focus();
        }, 0);
    }

    function closeStravaModal() {
        var modal = document.getElementById('strava-modal');
        if (!modal || modal.style.display === 'none') return;
        var card  = document.getElementById('strava-modal-card');
        modal.classList.remove('modal-overlay-in'); card.classList.remove('modal-card-in');
        modal.classList.add('modal-overlay-out');   card.classList.add('modal-card-out');
        setTimeout(function () {
            modal.style.display = 'none';
            modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
            modal.classList.add('ch-u-hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (_stravaTrap && typeof _stravaTrap.release === 'function') {
                _stravaTrap.release();
                _stravaTrap = null;
            }
            if (_stravaModalLastFocus && typeof _stravaModalLastFocus.focus === 'function') {
                _stravaModalLastFocus.focus();
            }
        }, mtDelay(180));
    }

    /* ── Quiz modal ─────────────────────────────────────────────────────── */
    function openQuizModal(cid) {
        var _quizModalData = window._quizModalData || {};
        var d = _quizModalData[cid];
        if (!d) {
            var baseUrl = (document.querySelector('meta[name="base-url"]') || {}).content || '';
            window.location.href = baseUrl + '/pages/challenges.php?id=' + cid;
            return;
        }
        _quizModalLastFocus = document.activeElement;
        document.getElementById('qm-token').textContent = '+' + d.token.toLocaleString() + ' Token';
        document.getElementById('qm-title').textContent = d.title;
        var descEl = document.getElementById('qm-desc');
        descEl.textContent   = d.desc;
        descEl.style.display = d.desc ? 'block' : 'none';
        var qcEl = document.getElementById('qm-qcount');
        qcEl.textContent = d.qcount > 0
            ? d.qcount + ' คำถาม • ต้องตอบถูกทุกข้อเพื่อรับ Token'
            : 'ไม่มีคำถาม';
        var edEl = document.getElementById('qm-enddate');
        if (d.ed) {
            var txt = 'สิ้นสุด ' + d.ed;
            if (d.daysLeft !== null && d.daysLeft >= 0 && d.daysLeft <= 7) {
                txt += ' · ' + (d.daysLeft === 0 ? 'วันนี้!' : 'เหลืออีก ' + d.daysLeft + ' วัน');
            }
            edEl.textContent   = txt;
            edEl.style.display = 'block';
        } else {
            edEl.style.display = 'none';
        }
        document.getElementById('qm-start-btn').href = d.url;

        var modal = document.getElementById('quiz-modal');
        var card  = document.getElementById('quiz-modal-card');
        modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
        modal.classList.remove('ch-u-hidden');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        void card.offsetWidth;
        modal.classList.add('modal-overlay-in');
        card.classList.add('modal-card-in');
        if (_quizTrap && typeof _quizTrap.release === 'function') _quizTrap.release();
        if (window.mtModalFocusTrap) {
            _quizTrap = window.mtModalFocusTrap.activate(modal, card);
        }
        setTimeout(function () {
            var firstFocus = card.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocus) firstFocus.focus();
        }, 0);
    }

    function closeQuizModal() {
        var modal = document.getElementById('quiz-modal');
        if (!modal || modal.style.display === 'none') return;
        var card  = document.getElementById('quiz-modal-card');
        modal.classList.remove('modal-overlay-in'); card.classList.remove('modal-card-in');
        modal.classList.add('modal-overlay-out');   card.classList.add('modal-card-out');
        setTimeout(function () {
            modal.style.display = 'none';
            modal.classList.remove('modal-overlay-out'); card.classList.remove('modal-card-out');
            modal.classList.add('ch-u-hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (_quizTrap && typeof _quizTrap.release === 'function') {
                _quizTrap.release();
                _quizTrap = null;
            }
            if (_quizModalLastFocus && typeof _quizModalLastFocus.focus === 'function') {
                _quizModalLastFocus.focus();
            }
        }, mtDelay(180));
    }

    /* ── Done section toggle ────────────────────────────────────────────── */
    function toggleDoneSection() {
        var grid    = document.getElementById('done-section-grid');
        var chevron = document.getElementById('done-chevron');
        var isOpen  = grid && !grid.classList.contains('ch-u-hidden');
        if (grid) {
            if (isOpen) {
                grid.classList.add('ch-u-hidden');
            } else {
                grid.classList.remove('ch-u-hidden');
            }
        }
        if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    }

    /* ── Keyboard handlers ──────────────────────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePhotoModal();
            closeStravaModal();
            closeQuizModal();
        }
    });

    /* ── Main click dispatcher ──────────────────────────────────────────── */
    (function () {
        bindPhotoDropzone();

        function triggerCardAction(el) {
            if (!el) return;
            var cid    = parseInt(el.getAttribute('data-cid') || '0', 10);
            var action = el.getAttribute('data-action');
            if (!cid || !action) return;
            if (action === 'open-quiz-modal')   openQuizModal(cid);
            if (action === 'open-photo-modal')  openPhotoModal(cid);
            if (action === 'open-strava-modal') openStravaModal(cid);
        }

        document.addEventListener('click', function (e) {
            var overlay = e.target.closest('[data-overlay-close]');
            if (overlay && e.target === overlay) {
                var overlayAction = overlay.getAttribute('data-overlay-close');
                if (overlayAction === 'self-hide')     overlay.style.display = 'none';
                if (overlayAction === 'photo-modal')   closePhotoModal();
                if (overlayAction === 'strava-modal')  closeStravaModal();
                if (overlayAction === 'quiz-modal')    closeQuizModal();
                return;
            }

            var act = e.target.closest('[data-action]');
            if (!act) return;
            var action = act.getAttribute('data-action');

            if (action === 'close-error-overlay') {
                e.preventDefault();
                var err = document.getElementById('ch-error-flash-overlay');
                if (err) err.style.display = 'none';
                return;
            }

            if (action === 'quiz-go-step') {
                e.preventDefault();
                var stepIdx = parseInt(act.getAttribute('data-step') || '0', 10);
                if (window._chQuizGoStep) window._chQuizGoStep(stepIdx);
                return;
            }

            if (action === 'open-quiz-modal' || action === 'open-photo-modal' || action === 'open-strava-modal') {
                e.preventDefault();
                triggerCardAction(act);
                return;
            }

            if (action === 'close-photo-modal') {
                e.preventDefault();
                closePhotoModal();
                return;
            }

            if (action === 'close-strava-modal') {
                e.preventDefault();
                closeStravaModal();
                return;
            }

            if (action === 'close-quiz-modal') {
                e.preventDefault();
                closeQuizModal();
                return;
            }

            if (action === 'submit-strava-form') {
                e.preventDefault();
                submitStravaForm(act.getAttribute('data-form-id'), act);
                return;
            }

            if (action === 'toggle-done-section') {
                e.preventDefault();
                toggleDoneSection();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var scene = e.target.closest('.ch-quest-flip-scene');
            if (scene && !e.target.closest('a,button,input,label,select,textarea')) {
                var type = scene.getAttribute('data-type');
                var cid  = parseInt(scene.getAttribute('data-cid') || '0', 10);
                if (!cid) return;
                e.preventDefault();
                if (type === 'quiz')   openQuizModal(cid);
                if (type === 'photo')  openPhotoModal(cid);
                if (type === 'strava') openStravaModal(cid);
                return;
            }
            var cardTrigger = e.target.closest('[data-action="open-quiz-modal"], [data-action="open-photo-modal"], [data-action="open-strava-modal"]');
            if (!cardTrigger) return;
            e.preventDefault();
            triggerCardAction(cardTrigger);
        });
    }());

    /* ── Touch flip ─────────────────────────────────────────────────────── */
    (function () {
        var isTouch = window.matchMedia('(pointer: coarse)').matches;
        if (!isTouch) return;

        document.querySelectorAll('.ch-quest-flip-scene').forEach(function (scene) {
            var front = scene.querySelector('.ch-flip-front');
            if (front) {
                front.addEventListener('click', function (e) {
                    if (e.target.closest('a,button,input,label,select,textarea')) return;
                    scene.classList.add('is-flipped');
                });
            }

            var closeBtn = scene.querySelector('.ch-flip-back-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    scene.classList.remove('is-flipped');
                });
            }
        });
    }());

}());
