/* rewards.js — Token Shop page interactions
 * Depends on: app.js, footer.js (already loaded by footer.php before this file)
 * Data hydration: window._rdData must be set inline before this file loads
 */
(function () {
    'use strict';

    /* ── Category icon / tone helpers ──────────────────────────────────── */

    function rwCategoryIconSvg(category) {
        var map = {
            voucher: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
            leave:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            merch:   '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
            perk:    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
            general: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>'
        };
        return map[category] || map.general;
    }

    function rwCategoryTone(category) {
        var map = {
            voucher: { bg: 'rgba(47,78,157,0.30)', border: 'rgba(123,159,245,0.52)', color: '#9db4f7' },
            leave:   { bg: 'rgba(81,142,92,0.30)', border: 'rgba(126,201,138,0.52)', color: '#8fdaa0' },
            merch:   { bg: 'rgba(98,48,122,0.32)', border: 'rgba(196,157,224,0.54)', color: '#d3ace8' },
            perk:    { bg: 'rgba(201,168,48,0.30)', border: 'rgba(248,231,105,0.52)', color: '#f8e769' },
            general: { bg: 'rgba(107,110,119,0.32)', border: 'rgba(165,169,181,0.52)', color: '#c9ccd4' }
        };
        return map[category] || map.general;
    }

    /* ── Redeem modal ──────────────────────────────────────────────────── */

    var _currentRewardId = 0;
    var _currentCost     = 0;
    var _redeemBusy      = false;
    window._redeemBusy   = false;

    window.filterCat = function (btn, cat) {
        document.querySelectorAll('.rw-cat-pill').forEach(function (p) {
            p.classList.remove('active');
        });
        btn.classList.add('active');
        document.querySelectorAll('.rw-reward-card').forEach(function (card) {
            var show = (cat === 'all' || card.dataset.category === cat);
            card.classList.toggle('rw-hidden', !show);
        });
    };

    window.openRedeem = function (id, title, cost) {
        _currentRewardId = id;
        _currentCost     = cost;

        var balEl = document.getElementById('hdr-balance');
        var balance = parseInt(
            balEl ? balEl.textContent.replace(/,/g, '') : '0', 10
        ) || parseInt((balEl && balEl.dataset.balance) || '0', 10);

        document.getElementById('modal-body-text').innerHTML =
            'แลกรางวัล <strong class="rw-modal-strong">' + title + '</strong> ' +
            'ใช้ <strong class="rw-modal-token">' + cost.toLocaleString() + ' Token</strong> ใช่หรือไม่?<br>' +
            '<span class="rw-modal-sub">ยอดคงเหลือ ' + (balance - cost).toLocaleString() + ' Token</span>';

        document.getElementById('modal-error').style.display = 'none';

        var confirmBtn = document.getElementById('modal-confirm-btn');
        confirmBtn.disabled    = false;
        confirmBtn.textContent = 'ยืนยันแลกรางวัล';

        document.getElementById('redeem-modal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeRedeem = function () {
        if (_redeemBusy) return;
        document.getElementById('redeem-modal').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.submitRedeem = function () {
        if (_redeemBusy) return;
        _redeemBusy = true;
        window._redeemBusy = true;

        var btn = document.getElementById('modal-confirm-btn');
        btn.disabled    = true;
        btn.textContent = 'กำลังดำเนินการ…';

        document.getElementById('modal-error').style.display  = 'none';
        document.getElementById('modal-cancel-btn').disabled  = true;

        var csrf = document.querySelector('meta[name="csrf-token"]')
                       ? document.querySelector('meta[name="csrf-token"]').content : '';

        fetch(window.location.href, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                action    : 'redeem',
                reward_id : _currentRewardId,
                csrf_token: csrf,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            _redeemBusy = false;
            window._redeemBusy = false;

            if (data.success) {
                var newBal = data.new_balance;
                document.getElementById('hdr-balance').textContent = newBal.toLocaleString('th-TH');
                var navBal = document.getElementById('nav-balance');
                if (navBal) navBal.textContent = newBal.toLocaleString('th-TH');

                document.querySelectorAll('.rw-reward-card').forEach(function (card) {
                    var anyBtn = card.querySelector('button[data-action="open-redeem"][data-reward-id="' + _currentRewardId + '"]');
                    if (anyBtn) { anyBtn.disabled = true; anyBtn.textContent = 'แลกแล้ว'; }
                });

                window.closeRedeem();
                showRedeemToast('แลกรางวัลสำเร็จ — รอ HR ดำเนินการมอบรางวัล');
            } else {
                var errEl = document.getElementById('modal-error');
                errEl.textContent   = data.message || 'เกิดข้อผิดพลาด';
                errEl.style.display = 'block';
                btn.disabled    = false;
                btn.textContent = 'ยืนยันแลกรางวัล';
                document.getElementById('modal-cancel-btn').disabled = false;
            }
        })
        .catch(function () {
            _redeemBusy = false;
            window._redeemBusy = false;
            var errEl = document.getElementById('modal-error');
            errEl.textContent   = 'การเชื่อมต่อขัดข้อง กรุณาลองใหม่';
            errEl.style.display = 'block';
            var btn2 = document.getElementById('modal-confirm-btn');
            btn2.disabled    = false;
            btn2.textContent = 'ยืนยันแลกรางวัล';
            document.getElementById('modal-cancel-btn').disabled = false;
        });
    };

    function showRedeemToast(msg) {
        var t = document.getElementById('app-toast');
        if (!t) return;
        t.className = 'toast-success';
        t.innerHTML = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="rw-flex-shrink-0">'
            + '<polyline points="20 6 9 17 4 12" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            + '<span>' + msg + '</span>';
        t.style.opacity   = '';
        t.style.transform = '';
        t.style.transition = '';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { t.classList.add('show'); });
        });
        setTimeout(function () {
            t.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            t.style.opacity    = '0';
            t.style.transform  = 'translate(-50%,-50%) scale(0.9)';
        }, 3200);
    }
    window.showRedeemToast = showRedeemToast;

    /* ── Coupon helpers (inline cards) ─────────────────────────────────── */

    window.rwToggleCoupon = function (id, btn) {
        var box   = document.getElementById('coupon-box-' + id);
        var label = document.getElementById('coupon-btn-label-' + id);
        var eye   = document.getElementById('coupon-eye-' + id);
        if (!box) return;
        var visible = box.style.display === 'flex';
        if (visible) {
            box.style.display = 'none';
            label.textContent = 'แสดงรหัสคูปอง';
            eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        } else {
            box.style.display = 'flex';
            label.textContent = 'ซ่อนรหัสคูปอง';
            eye.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
        }
    };

    window.rwCancelRedemption = function (rdId, title, cost) {
        if (!confirm('ยกเลิกการแลก "' + title + '"?\nToken ' + cost + ' จะถูกคืนให้คุณทันที')) return;

        var csrf = document.querySelector('meta[name="csrf-token"]')
                       ? document.querySelector('meta[name="csrf-token"]').content : '';

        fetch(window.location.href, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({
                action        : 'cancel_redemption',
                redemption_id : rdId,
                csrf_token    : csrf,
            }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var newBal = data.new_balance;
                var balEl  = document.getElementById('hdr-balance');
                if (balEl) balEl.textContent = newBal.toLocaleString('th-TH');
                var navBal = document.getElementById('nav-balance');
                if (navBal) navBal.textContent = newBal.toLocaleString('th-TH');
                location.reload();
            } else {
                alert(data.message || 'เกิดข้อผิดพลาด');
            }
        })
        .catch(function () { alert('การเชื่อมต่อขัดข้อง กรุณาลองใหม่'); });
    };

    window.rwCopyCoupon = function (code, id) {
        navigator.clipboard.writeText(code).then(function () {
            var btn = document.getElementById('coupon-copy-' + id);
            if (!btn) return;
            var orig = btn.innerHTML;
            btn.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="11" height="11"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> คัดลอกแล้ว';
            btn.style.color        = '#7ec98a';
            btn.style.borderColor  = 'rgba(81,142,92,0.40)';
            setTimeout(function () {
                btn.innerHTML      = orig;
                btn.style.color    = '#dab937';
                btn.style.borderColor = 'rgba(218,185,55,0.22)';
            }, 2000);
        }).catch(function () {
            var el = document.getElementById('coupon-code-' + id);
            if (el) {
                var r = document.createRange();
                r.selectNode(el);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(r);
            }
        });
    };

    /* ── Redeem modal event handlers ────────────────────────────────────── */

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            window.closeRedeem();
            closeRdDetail();
            closePendingList();
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.matches('[data-action-overlay="close-redeem"]')) {
            if (!window._redeemBusy) window.closeRedeem();
            return;
        }

        var filterBtn = e.target.closest('[data-action="filter-cat"]');
        if (filterBtn) {
            e.preventDefault();
            window.filterCat(filterBtn, filterBtn.dataset.cat || 'all');
            return;
        }

        var openRedeemBtn = e.target.closest('[data-action="open-redeem"]');
        if (openRedeemBtn) {
            e.preventDefault();
            window.openRedeem(
                parseInt(openRedeemBtn.dataset.rewardId, 10) || 0,
                openRedeemBtn.dataset.rewardTitle || '',
                parseInt(openRedeemBtn.dataset.rewardCost, 10) || 0
            );
            return;
        }

        var cancelBtn = e.target.closest('[data-action="cancel-redemption"]');
        if (cancelBtn) {
            e.preventDefault();
            e.stopPropagation();
            window.rwCancelRedemption(
                parseInt(cancelBtn.dataset.redemptionId, 10) || 0,
                cancelBtn.dataset.rewardTitle || '',
                parseInt(cancelBtn.dataset.cost, 10) || 0
            );
            return;
        }

        var toggleInlineCouponBtn = e.target.closest('[data-action="toggle-coupon-inline"]');
        if (toggleInlineCouponBtn) {
            e.preventDefault();
            e.stopPropagation();
            window.rwToggleCoupon(
                parseInt(toggleInlineCouponBtn.dataset.redemptionId, 10) || 0,
                toggleInlineCouponBtn
            );
            return;
        }

        var copyInlineCouponBtn = e.target.closest('[data-action="copy-coupon-inline"]');
        if (copyInlineCouponBtn) {
            e.preventDefault();
            e.stopPropagation();
            window.rwCopyCoupon(
                copyInlineCouponBtn.dataset.code || '',
                parseInt(copyInlineCouponBtn.dataset.redemptionId, 10) || 0
            );
            return;
        }

        var closeRedeemBtn = e.target.closest('[data-action="close-redeem"]');
        if (closeRedeemBtn) {
            e.preventDefault();
            window.closeRedeem();
            return;
        }

        var submitRedeemBtn = e.target.closest('[data-action="submit-redeem"]');
        if (submitRedeemBtn) {
            e.preventDefault();
            window.submitRedeem();
            return;
        }

        var pendingBtn = e.target.closest('[data-action="open-pending-list"]');
        if (pendingBtn) {
            e.preventDefault();
            openPendingList();
            return;
        }

        var row = e.target.closest('.rw-hist-detail-trigger');
        if (row) {
            e.preventDefault();
            openRdDetail(parseInt(row.dataset.redemptionId, 10) || 0);
        }

        if (e.target.matches('[data-action-overlay="close-rd-detail"]')) {
            closeRdDetail();
            return;
        }
        if (e.target.matches('[data-action-overlay="close-pending-list"]')) {
            closePendingList();
            return;
        }

        var closeDetailBtn = e.target.closest('[data-action="close-rd-detail"]');
        if (closeDetailBtn) {
            e.preventDefault();
            closeRdDetail();
            return;
        }

        var rdToggleCouponBtn = e.target.closest('[data-action="rd-toggle-coupon"]');
        if (rdToggleCouponBtn) {
            e.preventDefault();
            rdToggleCoupon();
            return;
        }

        var rdCopyCouponBtn = e.target.closest('[data-action="rd-copy-coupon"]');
        if (rdCopyCouponBtn) {
            e.preventDefault();
            rdCopyCoupon();
            return;
        }

        var rdDoCancelBtn = e.target.closest('[data-action="rd-do-cancel"]');
        if (rdDoCancelBtn) {
            e.preventDefault();
            rdDoCancel();
            return;
        }

        var closePendingBtn = e.target.closest('[data-action="close-pending-list"]');
        if (closePendingBtn) {
            e.preventDefault();
            closePendingList();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var row = e.target.closest('.rw-hist-detail-trigger');
        if (!row) return;
        e.preventDefault();
        openRdDetail(parseInt(row.dataset.redemptionId, 10) || 0);
    });

    /* ── Redemption detail modal ────────────────────────────────────────── */

    var _rdCurrentId     = 0;
    var _rdCurrentTokens = 0;

    function rdCategoryIconSvg(category, size) {
        var iconSize = size || 22;
        var map = {
            voucher: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4V7z" stroke-width="1.9"/><path d="M12 7v12" stroke-width="1.9" stroke-dasharray="2 2"/></svg>',
            leave:   '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="1.9"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="1.9" stroke-linecap="round"/><path d="m9 15 2 2 4-4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            merch:   '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 3v18" stroke-width="1.9"/><path d="M3 8h18" stroke-width="1.9"/><rect x="3" y="8" width="18" height="13" rx="2" stroke-width="1.9"/><path d="M7 3h10v2a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3V3z" stroke-width="1.9"/></svg>',
            perk:    '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.48 6.05.88-4.38 4.26 1.03 6.02L12 16.8l-5.4 2.84 1.03-6.02-4.38-4.26 6.05-.88L12 3z" stroke-width="1.9" stroke-linejoin="round"/></svg>',
            general: '<svg width="' + iconSize + '" height="' + iconSize + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 8h18" stroke-width="1.9"/><path d="M4 8l8 5 8-5" stroke-width="1.9"/><rect x="3" y="8" width="18" height="12" rx="2" stroke-width="1.9"/></svg>'
        };
        return map[category] || map.general;
    }

    function rdCategoryTone(category) {
        var map = {
            voucher: { bg: 'rgba(47,78,157,0.30)', border: 'rgba(123,159,245,0.52)', color: '#9db4f7' },
            leave:   { bg: 'rgba(81,142,92,0.30)', border: 'rgba(126,201,138,0.52)', color: '#8fdaa0' },
            merch:   { bg: 'rgba(98,48,122,0.32)', border: 'rgba(196,157,224,0.54)', color: '#d3ace8' },
            perk:    { bg: 'rgba(201,168,48,0.30)', border: 'rgba(248,231,105,0.52)', color: '#f8e769' },
            general: { bg: 'rgba(107,110,119,0.32)', border: 'rgba(165,169,181,0.52)', color: '#c9ccd4' }
        };
        return map[category] || map.general;
    }

    function openRdDetail(rdId) {
        var _rdData = window._rdData || {};
        var d = _rdData[rdId];
        if (!d) return;
        _rdCurrentId     = rdId;
        _rdCurrentTokens = d.tokens;

        var tone    = rdCategoryTone(d.category || 'general');
        var rddIcon = document.getElementById('rdd-emoji');
        rddIcon.innerHTML             = rdCategoryIconSvg(d.category || 'general', 26);
        rddIcon.style.color           = tone.color;
        rddIcon.style.background      = tone.bg;
        rddIcon.style.border          = '1px solid ' + tone.border;
        rddIcon.style.borderRadius    = '999px';
        rddIcon.style.width           = '44px';
        rddIcon.style.height          = '44px';
        rddIcon.style.display         = 'inline-flex';
        rddIcon.style.alignItems      = 'center';
        rddIcon.style.justifyContent  = 'center';

        document.getElementById('rdd-title').textContent  = d.title;
        document.getElementById('rdd-tokens').textContent = d.tokens.toLocaleString() + ' token';
        document.getElementById('rdd-req-at').textContent = d.reqAt;

        var statusMap = {
            pending:   { label: 'รอดำเนินการ', bg: 'rgba(245,158,11,0.12)', color: '#fbbf24', border: 'rgba(245,158,11,0.32)' },
            fulfilled: { label: 'มอบแล้ว',    bg: 'rgba(81,142,92,0.12)',  color: '#6fcf80', border: 'rgba(81,142,92,0.32)'  },
            cancelled: { label: 'ยกเลิก',     bg: 'rgba(210,89,42,0.12)',  color: '#e8805a', border: 'rgba(210,89,42,0.32)'  },
        };
        var sm    = statusMap[d.status] || statusMap.pending;
        var badge = document.getElementById('rdd-status-badge');
        badge.textContent      = sm.label;
        badge.style.background = sm.bg;
        badge.style.color      = sm.color;
        badge.style.border     = '1px solid ' + sm.border;

        var procRow = document.getElementById('rdd-proc-row');
        if (d.procAt) {
            document.getElementById('rdd-proc-at').textContent = d.procAt;
            var procByEl = document.getElementById('rdd-proc-by');
            if (d.procBy) {
                procByEl.textContent    = 'โดย ' + d.procBy;
                procByEl.style.display  = 'block';
            } else {
                procByEl.style.display  = 'none';
            }
            procRow.style.display = 'block';
        } else {
            procRow.style.display = 'none';
        }

        var noteWrap = document.getElementById('rdd-note-wrap');
        if (d.note) {
            document.getElementById('rdd-note').textContent = d.note;
            noteWrap.style.display = 'block';
        } else {
            noteWrap.style.display = 'none';
        }

        var couponSec = document.getElementById('rdd-coupon-section');
        if (d.coupon) {
            document.getElementById('rdd-coupon-code').textContent  = d.coupon;
            document.getElementById('rdd-coupon-box').style.display = 'none';
            document.getElementById('rdd-coupon-label').textContent = 'แสดงรหัสคูปอง';
            couponSec.style.display = 'block';
        } else {
            couponSec.style.display = 'none';
        }

        var cancelSec = document.getElementById('rdd-cancel-section');
        if (d.status === 'pending') {
            var cb = document.getElementById('rdd-cancel-btn');
            cb.disabled  = false;
            cb.innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg> ยกเลิกการแลก (คืน ' + d.tokens.toLocaleString() + ' Token)';
            cancelSec.style.display = 'block';
        } else {
            cancelSec.style.display = 'none';
        }

        var overlay = document.getElementById('rd-detail-modal');
        var card    = document.getElementById('rd-detail-card');
        overlay.classList.remove('rd-ov-out');  card.classList.remove('rd-card-out');
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void card.offsetWidth;
        overlay.classList.add('rd-ov-in');
        card.classList.add('rd-card-in');
    }
    window.openRdDetail = openRdDetail;

    function closeRdDetail() {
        var overlay = document.getElementById('rd-detail-modal');
        if (!overlay || overlay.style.display === 'none') return;
        var card = document.getElementById('rd-detail-card');
        overlay.classList.remove('rd-ov-in');  card.classList.remove('rd-card-in');
        overlay.classList.add('rd-ov-out');    card.classList.add('rd-card-out');
        setTimeout(function () {
            overlay.style.display = 'none';
            overlay.classList.remove('rd-ov-out'); card.classList.remove('rd-card-out');
            document.body.style.overflow = '';
        }, 160);
    }
    window.closeRdDetail = closeRdDetail;

    function rdToggleCoupon() {
        var box = document.getElementById('rdd-coupon-box');
        var lbl = document.getElementById('rdd-coupon-label');
        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'flex';
            lbl.textContent   = 'ซ่อนรหัสคูปอง';
        } else {
            box.style.display = 'none';
            lbl.textContent   = 'แสดงรหัสคูปอง';
        }
    }

    function rdCopyCoupon() {
        var code = document.getElementById('rdd-coupon-code').textContent.trim();
        var btn  = document.getElementById('rdd-coupon-copy');
        navigator.clipboard.writeText(code).then(function () {
            var orig = btn.textContent;
            btn.textContent   = 'คัดลอกแล้ว';
            btn.style.color   = '#7ec98a';
            setTimeout(function () { btn.textContent = orig; btn.style.color = '#dab937'; }, 1800);
        });
    }

    function rdDoCancel() {
        var cb = document.getElementById('rdd-cancel-btn');
        cb.disabled    = true;
        cb.textContent = 'กำลังดำเนินการ…';

        var csrf = document.querySelector('meta[name="csrf-token"]')
                       ? document.querySelector('meta[name="csrf-token"]').content : '';

        fetch(window.location.href, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : new URLSearchParams({ action: 'cancel_redemption', redemption_id: _rdCurrentId, csrf_token: csrf }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var balEl  = document.getElementById('hdr-balance');
                if (balEl)  balEl.textContent = data.new_balance.toLocaleString('th-TH');
                var navBal = document.getElementById('nav-balance');
                if (navBal) navBal.textContent = data.new_balance.toLocaleString('th-TH');
                closeRdDetail();
                setTimeout(function () { location.reload(); }, 165);
            } else {
                cb.disabled  = false;
                cb.innerHTML = data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่';
            }
        })
        .catch(function () {
            cb.disabled  = false;
            cb.innerHTML = 'การเชื่อมต่อขัดข้อง กรุณาลองใหม่';
        });
    }

    /* ── Pending list modal ─────────────────────────────────────────────── */

    function openPendingList() {
        var _rdData = window._rdData || {};
        var pending = Object.entries(_rdData).filter(function (e) { return e[1].status === 'pending'; });
        var body    = document.getElementById('pending-list-body');
        document.getElementById('pending-count-badge').textContent = pending.length;
        body.innerHTML = '';
        if (pending.length === 0) {
            body.innerHTML = '<p class="rw-pending-empty">ไม่มีรายการรอดำเนินการ</p>';
        } else {
            pending.forEach(function (entry, idx) {
                var rdId      = entry[0];
                var d         = entry[1];
                var toneClass = 'rw-tone-' + (d.category || 'general');
                var row = document.createElement('div');
                row.style.cssText = [
                    'display:flex; align-items:center; gap:0.85rem;',
                    'padding:0.75rem 1.25rem; cursor:pointer;',
                    'border-bottom:1px solid rgba(255,255,255,' + (idx < pending.length - 1 ? '0.05' : '0') + ');',
                    'transition:background 0.14s;',
                ].join('');
                row.onmouseover = function () { this.style.background = 'rgba(245,158,11,0.06)'; };
                row.onmouseout  = function () { this.style.background = ''; };
                row.onclick = function () {
                    closePendingList();
                    setTimeout(function () { openRdDetail(parseInt(rdId)); }, 140);
                };
                row.innerHTML = [
                    '<span class="rw-pending-icon ' + toneClass + '">' +
                    rdCategoryIconSvg(d.category || 'general', 20) +
                    '</span>',
                    '<div class="rw-pending-main">',
                    '  <p class="rw-pending-title"',
                    '     style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + d.title + '</p>',
                    '  <p class="rw-pending-date">ขอวันที่ ' + d.reqAt + '</p>',
                    '</div>',
                    '<div class="rw-pending-token-wrap">',
                    '  <span class="rw-pending-token-value">' + d.tokens.toLocaleString() + '</span>',
                    '  <span class="rw-pending-token-label">token</span>',
                    '</div>',
                    '<svg fill="none" stroke="#6b6e77" viewBox="0 0 24 24" width="14" height="14" class="rw-pending-arrow">',
                    '  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>',
                    '</svg>',
                ].join('');
                body.appendChild(row);
            });
        }

        var overlay = document.getElementById('rd-pending-modal');
        var card    = document.getElementById('rd-pending-card');
        overlay.classList.remove('rd-ov-out'); card.classList.remove('rd-card-out');
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        void card.offsetWidth;
        overlay.classList.add('rd-ov-in');
        card.classList.add('rd-card-in');
    }
    window.openPendingList = openPendingList;

    function closePendingList() {
        var overlay = document.getElementById('rd-pending-modal');
        if (!overlay || overlay.style.display === 'none') return;
        var card = document.getElementById('rd-pending-card');
        overlay.classList.remove('rd-ov-in');  card.classList.remove('rd-card-in');
        overlay.classList.add('rd-ov-out');    card.classList.add('rd-card-out');
        setTimeout(function () {
            overlay.style.display = 'none';
            overlay.classList.remove('rd-ov-out'); card.classList.remove('rd-card-out');
            document.body.style.overflow = '';
        }, 160);
    }
    window.closePendingList = closePendingList;

}());
