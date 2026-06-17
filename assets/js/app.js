/**
 * assets/js/app.js
 * Mission Token — Client-side JS
 * Handles: counter animations, confetti, quiz interactions, form UX
 */

// ============================================================
// Counter Animation (count-up effect)
// ============================================================

function prefersReducedMotion() {
    return typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function getMotionDuration(ms) {
    return prefersReducedMotion() ? 0 : ms;
}

function getMotionBehavior() {
    return prefersReducedMotion() ? 'auto' : 'smooth';
}

window.mtMotion = window.mtMotion || {
    reduced: prefersReducedMotion,
    delay: getMotionDuration,
    scrollBehavior: getMotionBehavior
};

/**
 * Animate a number element counting up to target.
 * @param {HTMLElement} el  — element to update
 * @param {number}      target
 * @param {number}      duration ms
 */
function animateCounter(el, target, duration = 1200) {
    if (prefersReducedMotion()) {
        el.textContent = target.toLocaleString('th-TH');
        return;
    }

    const start     = parseInt(el.textContent.replace(/[^0-9]/g, '')) || 0;
    const range     = target - start;
    const startTime = performance.now();

    function step(now) {
        const elapsed  = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Ease-out cubic
        const eased    = 1 - Math.pow(1 - progress, 3);
        const value    = Math.round(start + range * eased);
        el.textContent = value.toLocaleString('th-TH');
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

// Auto-init: any element with data-counter="<number>"
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-counter]').forEach(function (el) {
        const target = parseInt(el.dataset.counter, 10);
        if (!isNaN(target)) animateCounter(el, target);
    });
});

// ============================================================
// Confetti Effect (token reward celebration)
// ============================================================

const CONFETTI_COLORS = ['#dab937', '#f8e769', '#518e5c', '#4f8b98', '#d2592a', '#2f4e9d', '#eeebe1'];

function showConfetti(count = 90) {
    if (prefersReducedMotion()) return;

    // Inject keyframe once
    if (!document.getElementById('confetti-kf')) {
        const style = document.createElement('style');
        style.id = 'confetti-kf';
        style.textContent = `
            @keyframes confetti-fall {
                0%   { transform: translateY(-20px) rotate(0deg);   opacity: 1; }
                100% { transform: translateY(110vh)  rotate(720deg); opacity: 0; }
            }`;
        document.head.appendChild(style);
    }

    const container = document.createElement('div');
    container.style.cssText =
        'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;overflow:hidden;';
    document.body.appendChild(container);

    for (let i = 0; i < count; i++) {
        const piece    = document.createElement('div');
        const color    = CONFETTI_COLORS[Math.floor(Math.random() * CONFETTI_COLORS.length)];
        const size     = Math.random() * 9 + 4;
        const x        = Math.random() * 100;
        const delay    = Math.random() * 0.6;
        const duration = Math.random() * 2 + 2;
        const round    = Math.random() > 0.5 ? '50%' : '2px';

        piece.style.cssText = [
            `position:absolute`,
            `width:${size}px`, `height:${size}px`,
            `background:${color}`,
            `left:${x}%`, `top:-12px`,
            `border-radius:${round}`,
            `animation:confetti-fall ${duration}s ${delay}s ease-in forwards`
        ].join(';');

        container.appendChild(piece);
    }

    setTimeout(() => container.remove(), getMotionDuration(4000) || 300);
}

// ============================================================
// Token Reward Popup (show earned tokens)
// ============================================================

function showTokenReward(amount, message = '') {
    var reduced = prefersReducedMotion();
    const el = document.createElement('div');
    el.style.cssText = [
        'position:fixed', 'top:50%', 'left:50%',
        'transform:translate(-50%,-50%) scale(0.6)',
        'background:#091113', 'border:2px solid #dab937',
        'border-radius:20px', 'padding:2rem 3rem',
        'text-align:center', 'z-index:10000',
        'box-shadow:0 20px 60px rgba(0,0,0,0.4)',
        'transition:transform ' + (reduced ? '0ms' : '0.3s cubic-bezier(0.22,1,0.36,1)') + ', opacity ' + (reduced ? '0ms' : '0.3s'),
        'opacity:0'
    ].join(';');

    el.innerHTML = `
        <div style="font-size:2.5rem;margin-bottom:0.5rem;">🪙</div>
        <div style="color:#dab937;font-size:2rem;font-weight:700;font-family:'Prompt',sans-serif;">
            +${amount.toLocaleString('th-TH')}
        </div>
        <div style="color:#f8e769;font-size:0.9rem;font-weight:600;margin-top:0.25rem;font-family:'Prompt',sans-serif;">TOKEN</div>
        ${message ? `<div style="color:#cecdcd;font-size:0.8rem;margin-top:0.75rem;font-family:'Prompt',sans-serif;">${message}</div>` : ''}
    `;

    document.body.appendChild(el);

    // Animate in
    requestAnimationFrame(() => {
        el.style.transform = 'translate(-50%,-50%) scale(1)';
        el.style.opacity   = '1';
    });

    // Auto-dismiss after 2.5s
    setTimeout(() => {
        el.style.transform = 'translate(-50%,-50%) scale(0.8)';
        el.style.opacity   = '0';
        setTimeout(() => el.remove(), getMotionDuration(300) || 120);
    }, getMotionDuration(2500) || 900);

    showConfetti(70);
}

// ============================================================
// Quiz UI Helpers
// ============================================================

/**
 * Highlight selected MCQ option.
 * options: NodeList of .quiz-option elements
 */
function selectQuizOption(selectedEl, options) {
    options.forEach(function (opt) {
        opt.classList.remove('quiz-selected');
        opt.style.borderColor = '#cecdcd';
        opt.style.background  = '#fff';
    });
    selectedEl.classList.add('quiz-selected');
    selectedEl.style.borderColor = '#dab937';
    selectedEl.style.background  = '#fffde7';
}

/**
 * Show quiz result feedback on each option.
 * @param {string} selectedValue   — user's answer ('A'/'B'/'C'/'D')
 * @param {string} correctValue    — correct answer
 * @param {NodeList} options       — .quiz-option elements with data-option attribute
 */
function showQuizResult(selectedValue, correctValue, options) {
    options.forEach(function (opt) {
        const val = opt.dataset.option;
        if (val === correctValue) {
            opt.style.borderColor = '#518e5c';
            opt.style.background  = '#e8f4ec';
            opt.style.color       = '#518e5c';
        } else if (val === selectedValue && val !== correctValue) {
            opt.style.borderColor = '#d2592a';
            opt.style.background  = '#fdf0ea';
            opt.style.color       = '#d2592a';
        }
        opt.style.pointerEvents = 'none';
    });
}

// ============================================================
// File Upload Preview
// ============================================================

/**
 * Bind a file input to a preview <img> element.
 * @param {string} inputId   — id of <input type="file">
 * @param {string} previewId — id of <img> or container
 */
function bindFilePreview(inputId, previewId) {
    const input   = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (!input || !preview) return;

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        // Client-side size check (20MB)
        if (file.size > 20 * 1024 * 1024) {
            alert('ไฟล์ต้องมีขนาดไม่เกิน 20MB');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src              = e.target.result;
            preview.style.display    = 'block';
            preview.style.maxHeight  = '240px';
            preview.style.objectFit  = 'contain';
            preview.style.borderRadius = '8px';
        };
        reader.readAsDataURL(file);
    });
}

// ============================================================
// Nav Balance Updater (call after token award via AJAX)
// ============================================================

function updateNavBalance(newBalance) {
    const el = document.getElementById('nav-balance');
    if (el) animateCounter(el, newBalance, 800);
}

// ============================================================
// Loading Spinner (for form submit buttons)
// ============================================================

function setButtonLoading(btn, loading = true, loadingText = 'กำลังดำเนินการ...') {
    if (!btn) return;
    if (loading) {
        if (!btn.dataset.origHtml) btn.dataset.origHtml = btn.innerHTML;
        if (!btn.dataset.origAriaLabel && btn.getAttribute('aria-label')) {
            btn.dataset.origAriaLabel = btn.getAttribute('aria-label');
        }
        btn.innerHTML = '<span class="btn-loading"><svg class="btn-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="12"/></svg><span>' + loadingText + '</span></span>';
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
    } else {
        btn.innerHTML = btn.dataset.origHtml || btn.innerHTML;
        if (btn.dataset.origAriaLabel) {
            btn.setAttribute('aria-label', btn.dataset.origAriaLabel);
        }
        btn.disabled = false;
        btn.removeAttribute('aria-busy');
    }
}

// ============================================================
// Modal Focus Trap Utility
// ============================================================

(function () {
    function getFocusable(container) {
        if (!container) return [];
        var selector = [
            'a[href]:not([tabindex="-1"])',
            'button:not([disabled]):not([tabindex="-1"])',
            'textarea:not([disabled]):not([tabindex="-1"])',
            'input:not([disabled]):not([type="hidden"]):not([tabindex="-1"])',
            'select:not([disabled]):not([tabindex="-1"])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(',');

        return Array.prototype.filter.call(container.querySelectorAll(selector), function (el) {
            return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
        });
    }

    function createTrap(overlayEl, containerEl) {
        if (!overlayEl || !containerEl) {
            return { release: function () {} };
        }

        function onKeyDown(e) {
            if (e.key !== 'Tab') return;

            var focusables = getFocusable(containerEl);
            if (!focusables.length) {
                e.preventDefault();
                if (typeof containerEl.focus === 'function') containerEl.focus();
                return;
            }

            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            var active = document.activeElement;

            if (e.shiftKey) {
                if (active === first || !containerEl.contains(active)) {
                    e.preventDefault();
                    last.focus();
                }
            } else if (active === last || !containerEl.contains(active)) {
                e.preventDefault();
                first.focus();
            }
        }

        overlayEl.addEventListener('keydown', onKeyDown);
        return {
            release: function () {
                overlayEl.removeEventListener('keydown', onKeyDown);
            }
        };
    }

    window.mtModalFocusTrap = {
        activate: function (overlayEl, containerEl) {
            return createTrap(overlayEl, containerEl);
        }
    };
})();

// ============================================================
// Home Page — Morphing Slide Transition (clip-path expand)
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    var scrollBtn  = document.getElementById('hero-scroll-btn');
    var aboutSlide = document.getElementById('about');
    var aboutTitle = document.getElementById('about-title');
    var backBtn    = document.getElementById('about-back-btn');
    var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var intelCards = document.querySelectorAll('.home-intel-card-reveal');

    if (!scrollBtn || !aboutSlide) return;

    var isOpen = false;
    var wheelAccum = 0;
    var wheelResetTimer = null;
    var WHEEL_THRESHOLD = 300; // accumulated px before triggering

    function settleHeroScrollIndicator() {
        scrollBtn.style.animation = 'none';
        scrollBtn.style.opacity = '0.72';
    }

    function openAbout() {
        if (isOpen) return;
        isOpen = true;
        wheelAccum = 0;
        aboutSlide.classList.add('slide-open');
        aboutSlide.setAttribute('aria-hidden', 'false');
        scrollBtn.setAttribute('aria-expanded', 'true');
        settleHeroScrollIndicator();
        document.body.classList.add('about-open');
        if (backBtn) backBtn.classList.add('btn-visible');
        if (aboutTitle) {
            setTimeout(function () {
                aboutTitle.focus();
            }, 320);
        }
    }

    function closeAbout() {
        if (!isOpen) return;
        isOpen = false;
        wheelAccum = 0;
        aboutSlide.classList.remove('slide-open');
        aboutSlide.setAttribute('aria-hidden', 'true');
        scrollBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('about-open');
        if (backBtn) backBtn.classList.remove('btn-visible');
        // Reset scroll so next open always starts clip-path from the button origin
        aboutSlide.scrollTop = 0;
        scrollBtn.focus();
    }

    // Click on scroll indicator
    scrollBtn.addEventListener('click', function () {
        settleHeroScrollIndicator();
        openAbout();
    });

    // Mouse wheel: accumulate delta — only trigger after sustained scrolling
    window.addEventListener('wheel', function (e) {
        if (isOpen) {
            // Close: only when already scrolled to top of about panel
            if (e.deltaY < 0 && aboutSlide.scrollTop <= 0) {
                wheelAccum += e.deltaY; // negative
                if (wheelAccum < -WHEEL_THRESHOLD) closeAbout();
            } else {
                wheelAccum = 0;
            }
            return;
        }

        // Open: accumulate downward scroll
        if (e.deltaY > 0) {
            settleHeroScrollIndicator();
            wheelAccum += e.deltaY;
            // Reset accumulation if user pauses
            clearTimeout(wheelResetTimer);
            wheelResetTimer = setTimeout(function () { wheelAccum = 0; }, 250);
            if (wheelAccum >= WHEEL_THRESHOLD) openAbout();
        }
    }, { passive: true });

    // Touch swipe: swipe up → open, swipe down (at top) → close
    var touchStartY = 0;
    window.addEventListener('touchstart', function (e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    window.addEventListener('touchend', function (e) {
        var delta = touchStartY - e.changedTouches[0].clientY;
        if (!isOpen && delta > 50) {
            openAbout();
        } else if (isOpen && delta < -50 && aboutSlide.scrollTop <= 0) {
            closeAbout();
        }
    }, { passive: true });

    // Back button click
    if (backBtn) backBtn.addEventListener('click', closeAbout);

    // ESC key closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closeAbout();
    });

    if (intelCards.length) {
        if (prefersReducedMotion) {
            intelCards.forEach(function (card) {
                card.classList.add('in-view');
            });
        } else {
            var intelObserver = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('in-view');
                    obs.unobserve(entry.target);
                });
            }, { threshold: 0.18 });

            intelCards.forEach(function (card) {
                intelObserver.observe(card);
            });
        }
    }

});

// ============================================================
// Login Page — Password Toggle & Form Submit
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    // Password show/hide toggle
    var passInput = document.getElementById('password');
    var eyeShow   = document.getElementById('eye-show');
    var eyeHide   = document.getElementById('eye-hide');
    var toggleBtn = document.querySelector('.pass-toggle');

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function () {
            if (passInput.type === 'password') {
                passInput.type = 'text';
                if (eyeShow) eyeShow.classList.add('hidden');
                if (eyeHide) eyeHide.classList.remove('hidden');
            } else {
                passInput.type = 'password';
                if (eyeShow) eyeShow.classList.remove('hidden');
                if (eyeHide) eyeHide.classList.add('hidden');
            }
        });
    }

    // Login form: show loading state on submit
    var loginForm = document.getElementById('login-form');
    var loginBtn  = document.getElementById('login-btn');
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function () {
            setButtonLoading(loginBtn, true, 'กำลังเข้าสู่ระบบ...');
        });
    }

    var errorAlert = document.getElementById('error-alert');
    if (errorAlert) {
        errorAlert.setAttribute('tabindex', '-1');
        errorAlert.focus({ preventScroll: true });
    }

    document.querySelectorAll('[data-avatar-pick]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inputId = btn.getAttribute('data-avatar-pick');
            var input = inputId ? document.getElementById(inputId) : null;
            if (input) input.click();
        });
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btn.click();
            }
        });
    });
});

// ============================================================
// Admin Redemptions — action modal (fulfill / cancel)
// ============================================================
var _ardLastFocus = null;
var _ardTrap = null;

function ardOpenAction(id, action, empName, rewardTitle) {
    _ardLastFocus = document.activeElement;
    document.getElementById('ard-form-action').value        = action;
    document.getElementById('ard-form-redemption-id').value = id;
    document.getElementById('ard-form-note').value          = '';

    var isFulfill = (action === 'fulfill');
    document.getElementById('ard-modal-title').textContent =
        isFulfill ? '✓ ยืนยันการมอบรางวัล' : '✕ ยืนยันการยกเลิก';
    document.getElementById('ard-modal-desc').textContent =
        isFulfill
            ? empName + ' แลกรางวัล "' + rewardTitle + '" — ยืนยันว่าได้มอบรางวัลให้พนักงานเรียบร้อยแล้ว'
            : empName + ' แลกรางวัล "' + rewardTitle + '" — ยืนยันการยกเลิก Token จะถูกคืนให้พนักงานทันที';

    var btn = document.getElementById('ard-modal-submit-btn');
    if (isFulfill) {
        btn.textContent      = '✓ ยืนยันมอบรางวัล';
        btn.style.background = '#518e5c';
    } else {
        btn.textContent      = '✕ ยืนยันยกเลิก';
        btn.style.background = '#d2592a';
    }
    var modal = document.getElementById('ard-action-modal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (_ardTrap && typeof _ardTrap.release === 'function') {
        _ardTrap.release();
    }
    if (window.mtModalFocusTrap) {
        _ardTrap = window.mtModalFocusTrap.activate(modal, modal.querySelector('.ard-modal-box'));
    }
    setTimeout(function () {
        var submitBtn = document.getElementById('ard-modal-submit-btn');
        if (submitBtn) submitBtn.focus();
    }, 0);
}

function ardCloseAction() {
    var modal = document.getElementById('ard-action-modal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (_ardTrap && typeof _ardTrap.release === 'function') {
        _ardTrap.release();
        _ardTrap = null;
    }
    if (_ardLastFocus && typeof _ardLastFocus.focus === 'function') {
        _ardLastFocus.focus();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('ard-action-modal');
    if (!modal) return;
    modal.addEventListener('click', function (e) {
        if (e.target === modal) ardCloseAction();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) ardCloseAction();
    });
});

// ============================================================
// Profile Page — password toggle + confirm match hint
// ============================================================
function profileTogglePw(fieldId) {
    var el = document.getElementById(fieldId);
    if (el) el.type = el.type === 'password' ? 'text' : 'password';
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-submit-on-change]').forEach(function (input) {
        input.addEventListener('change', function () {
            var formId = input.getAttribute('data-submit-on-change');
            var form = formId ? document.getElementById(formId) : null;
            if (form) form.submit();
        });
    });

    document.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('[data-toggle-pw]');
        if (!toggleBtn) return;
        e.preventDefault();
        profileTogglePw(toggleBtn.getAttribute('data-toggle-pw'));
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.getAttribute('data-confirm');
            if (msg && !confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    var newPw  = document.getElementById('new_password');
    var confPw = document.getElementById('confirm_password');
    var hint   = document.getElementById('pw-match-hint');
    if (!newPw || !confPw || !hint) return;

    function checkMatch() {
        if (!confPw.value) { hint.textContent = ''; return; }
        if (newPw.value === confPw.value) {
            hint.textContent = '✓ รหัสผ่านตรงกัน';
            hint.style.color = '#82b295';
        } else {
            hint.textContent = '✗ รหัสผ่านไม่ตรงกัน';
            hint.style.color = '#d2592a';
        }
    }
    newPw.addEventListener('input', checkMatch);
    confPw.addEventListener('input', checkMatch);
});

/* ── Admin Submissions (asb-) ───────────────────────────────── */
function toggleNote(id) {
    var area = document.getElementById('note-area-' + id);
    if (!area) return;
    var hidden = area.classList.contains('hidden');
    area.classList.toggle('hidden', !hidden);
    if (hidden) document.getElementById('note-input-' + id).focus();
}

function syncNote(id, targetId) {
    var inp = document.getElementById('note-input-' + id);
    var tgt = document.getElementById(targetId);
    if (inp && tgt) tgt.value = inp.value;
}

function confirmReject(id) {
    var inp  = document.getElementById('note-input-' + id);
    var note = document.getElementById('reject-note-' + id);
    if (inp && note) note.value = inp.value;
    return confirm('ยืนยันปฏิเสธการส่งงานนี้?\nพนักงานจะไม่ได้รับ Token และสามารถเห็นหมายเหตุที่คุณใส่ไว้');
}


/* -- Admin Employees (emp-) ---------------------------------- */
var _empAdjustMode = 'add'; // 'add' | 'deduct'
var _empAdjustLastFocus = null;
var _empPwLastFocus = null;
var _empAdjustTrap = null;
var _empPwTrap = null;

function empSetMode(mode) {
    _empAdjustMode = mode;
    var btnAdd    = document.getElementById('adj-btn-add');
    var btnDeduct = document.getElementById('adj-btn-deduct');
    var submitBtn = document.getElementById('emp-adjust-submit-btn');
    var label     = document.getElementById('adj-amount-label');
    if (mode === 'add') {
        btnAdd.style.background    = 'rgba(81,142,92,0.30)';
        btnAdd.style.borderColor   = 'rgba(81,142,92,0.60)';
        btnAdd.style.color         = '#7ec98a';
        btnDeduct.style.background = 'rgba(255,255,255,0.05)';
        btnDeduct.style.borderColor= 'rgba(255,255,255,0.10)';
        btnDeduct.style.color      = '#6b6e77';
        submitBtn.style.background = 'rgba(81,142,92,0.25)';
        submitBtn.style.borderColor= 'rgba(81,142,92,0.50)';
        submitBtn.style.color      = '#7ec98a';
        submitBtn.textContent      = '+ เพิ่ม Token';
        label.innerHTML            = 'จำนวน Token ที่จะเพิ่ม <span style="color:#d2592a;">*</span>';
    } else {
        btnDeduct.style.background = 'rgba(210,89,42,0.25)';
        btnDeduct.style.borderColor= 'rgba(210,89,42,0.50)';
        btnDeduct.style.color      = '#e07a55';
        btnAdd.style.background    = 'rgba(255,255,255,0.05)';
        btnAdd.style.borderColor   = 'rgba(255,255,255,0.10)';
        btnAdd.style.color         = '#6b6e77';
        submitBtn.style.background = 'rgba(210,89,42,0.20)';
        submitBtn.style.borderColor= 'rgba(210,89,42,0.50)';
        submitBtn.style.color      = '#e07a55';
        submitBtn.textContent      = '− หัก Token';
        label.innerHTML            = 'จำนวน Token ที่จะหัก <span style="color:#d2592a;">*</span>';
    }
}

function empOpenAdjust(empId, name, balance, qs) {
    _empAdjustLastFocus = document.activeElement;
    document.getElementById('emp-adjust-emp-id').value = empId;
    document.getElementById('emp-adjust-qs').value     = qs;
    document.getElementById('emp-adjust-title').textContent = 'จัดการ Token: ' + name;
    document.getElementById('emp-adjust-balance').textContent = balance.toLocaleString() + ' Token';
    document.getElementById('emp-adjust-amount').value = '';
    empSetMode('add'); // always default to add
    var modal = document.getElementById('emp-adjust-modal');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (_empAdjustTrap && typeof _empAdjustTrap.release === 'function') {
        _empAdjustTrap.release();
    }
    if (window.mtModalFocusTrap) {
        _empAdjustTrap = window.mtModalFocusTrap.activate(modal, modal.querySelector('.jp-modal-content'));
    }
    setTimeout(function() { document.getElementById('emp-adjust-amount').focus(); }, 80);
}
function empCloseAdjust() {
    var modal = document.getElementById('emp-adjust-modal');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (_empAdjustTrap && typeof _empAdjustTrap.release === 'function') {
        _empAdjustTrap.release();
        _empAdjustTrap = null;
    }
    if (_empAdjustLastFocus && typeof _empAdjustLastFocus.focus === 'function') {
        _empAdjustLastFocus.focus();
    }
}

function empOpenPw(empId, name, qs) {
    _empPwLastFocus = document.activeElement;
    document.getElementById('emp-pw-emp-id').value = empId;
    document.getElementById('emp-pw-qs').value     = qs;
    document.getElementById('emp-pw-title').textContent = 'Reset รหัสผ่าน: ' + name;
    document.getElementById('emp-pw-new').value     = '';
    document.getElementById('emp-pw-confirm').value = '';
    document.getElementById('emp-pw-match-hint').textContent = '';
    var modal = document.getElementById('emp-pw-modal');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (_empPwTrap && typeof _empPwTrap.release === 'function') {
        _empPwTrap.release();
    }
    if (window.mtModalFocusTrap) {
        _empPwTrap = window.mtModalFocusTrap.activate(modal, modal.querySelector('.jp-modal-content'));
    }
    setTimeout(function() { document.getElementById('emp-pw-new').focus(); }, 80);
}
function empClosePw() {
    var modal = document.getElementById('emp-pw-modal');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (_empPwTrap && typeof _empPwTrap.release === 'function') {
        _empPwTrap.release();
        _empPwTrap = null;
    }
    if (_empPwLastFocus && typeof _empPwLastFocus.focus === 'function') {
        _empPwLastFocus.focus();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // ESC + click-outside for adjust modal
    var adjModal = document.getElementById('emp-adjust-modal');
    if (adjModal) {
        adjModal.addEventListener('click', function(e) { if (e.target === adjModal) empCloseAdjust(); });
    }

    // Adjust form: compute signed amount before submit
    var adjForm = document.getElementById('emp-adjust-form');
    if (adjForm) {
        adjForm.addEventListener('submit', function(e) {
            var raw = parseInt(document.getElementById('emp-adjust-amount').value, 10);
            if (!raw || raw <= 0) { e.preventDefault(); return; }
            var signed = _empAdjustMode === 'deduct' ? -raw : raw;
            document.getElementById('emp-adjust-amount-final').value = signed;
        });
    }
    // ESC + click-outside for pw modal
    var pwModal = document.getElementById('emp-pw-modal');
    if (pwModal) {
        pwModal.addEventListener('click', function(e) { if (e.target === pwModal) empClosePw(); });
        // live password match hint
        var pwNew  = document.getElementById('emp-pw-new');
        var pwConf = document.getElementById('emp-pw-confirm');
        var pwHint = document.getElementById('emp-pw-match-hint');
        if (pwNew && pwConf && pwHint) {
            function checkPwMatch() {
                if (!pwConf.value) { pwHint.textContent = ''; return; }
                if (pwNew.value === pwConf.value) {
                    pwHint.textContent = '\u2713 \u0e23\u0e2b\u0e31\u0e2a\u0e1c\u0e48\u0e32\u0e19\u0e15\u0e23\u0e07\u0e01\u0e31\u0e19';
                    pwHint.style.color = '#82b295';
                } else {
                    pwHint.textContent = '\u2717 \u0e23\u0e2b\u0e31\u0e2a\u0e1c\u0e48\u0e32\u0e19\u0e44\u0e21\u0e48\u0e15\u0e23\u0e07\u0e01\u0e31\u0e19';
                    pwHint.style.color = '#d2592a';
                }
            }
            pwNew.addEventListener('input',  checkPwMatch);
            pwConf.addEventListener('input', checkPwMatch);
        }
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            empCloseAdjust();
            empClosePw();
        }
    });
});

