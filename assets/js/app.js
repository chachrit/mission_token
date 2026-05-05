/**
 * assets/js/app.js
 * Mission Token — Client-side JS
 * Handles: counter animations, confetti, quiz interactions, form UX
 */

// ============================================================
// Counter Animation (count-up effect)
// ============================================================

/**
 * Animate a number element counting up to target.
 * @param {HTMLElement} el  — element to update
 * @param {number}      target
 * @param {number}      duration ms
 */
function animateCounter(el, target, duration = 1200) {
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

    setTimeout(() => container.remove(), 4000);
}

// ============================================================
// Token Reward Popup (show earned tokens)
// ============================================================

function showTokenReward(amount, message = '') {
    const el = document.createElement('div');
    el.style.cssText = [
        'position:fixed', 'top:50%', 'left:50%',
        'transform:translate(-50%,-50%) scale(0.6)',
        'background:#091113', 'border:2px solid #dab937',
        'border-radius:20px', 'padding:2rem 3rem',
        'text-align:center', 'z-index:10000',
        'box-shadow:0 20px 60px rgba(0,0,0,0.4)',
        'transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s',
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
        setTimeout(() => el.remove(), 300);
    }, 2500);

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

        // Client-side size check (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('ไฟล์ต้องมีขนาดไม่เกิน 5MB');
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

function setButtonLoading(btn, loading = true) {
    if (loading) {
        btn.dataset.origText = btn.innerHTML;
        btn.innerHTML        = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-dashoffset="12"/></svg> กำลังดำเนินการ...';
        btn.disabled         = true;
    } else {
        btn.innerHTML = btn.dataset.origText || btn.innerHTML;
        btn.disabled  = false;
    }
}

// ============================================================
// Home Page — Morphing Slide Transition (clip-path expand)
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    var scrollBtn  = document.getElementById('hero-scroll-btn');
    var aboutSlide = document.getElementById('about');
    var backBtn    = document.getElementById('about-back-btn');

    if (!scrollBtn || !aboutSlide) return;

    var isOpen = false;
    var wheelAccum = 0;
    var wheelResetTimer = null;
    var WHEEL_THRESHOLD = 300; // accumulated px before triggering

    function openAbout() {
        if (isOpen) return;
        isOpen = true;
        wheelAccum = 0;
        aboutSlide.classList.add('slide-open');
        document.body.classList.add('about-open');
        if (backBtn) backBtn.classList.add('btn-visible');
    }

    function closeAbout() {
        if (!isOpen) return;
        isOpen = false;
        wheelAccum = 0;
        aboutSlide.classList.remove('slide-open');
        document.body.classList.remove('about-open');
        if (backBtn) backBtn.classList.remove('btn-visible');
    }

    // Click on scroll indicator
    scrollBtn.addEventListener('click', openAbout);

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
            loginBtn.disabled    = true;
            loginBtn.textContent = 'กำลังเข้าสู่ระบบ...';
        });
    }
});

// ============================================================
// Admin Redemptions — action modal (fulfill / cancel)
// ============================================================
function ardOpenAction(id, action, empName, rewardTitle) {
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

    document.getElementById('ard-action-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function ardCloseAction() {
    document.getElementById('ard-action-modal').classList.remove('open');
    document.body.style.overflow = '';
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
    var newPw  = document.getElementById('new_password');
    var confPw = document.getElementById('confirm_password');
    var hint   = document.getElementById('pw-match-hint');
    if (!newPw || !confPw || !hint) return;

    function checkMatch() {
        if (!confPw.value) { hint.textContent = ''; return; }
        if (newPw.value === confPw.value) {
            hint.textContent   = '✓ รหัสผ่านตรงกัน';
            hint.style.color   = '#82b295';
        } else {
            hint.textContent   = '✗ รหัสผ่านไม่ตรงกัน';
            hint.style.color   = '#d2592a';
        }
    }
    newPw.addEventListener('input',  checkMatch);
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

