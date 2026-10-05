/*
 * Solkeb Hotel UI helpers: confirmations, messages, double-click guard, chart tooltips.
 *
 * Popups use SweetAlert2 (public/vendor/sweetalert2/sweetalert2.all.min.js) when that file is present.
 * Without it, a built-in dialog in the same style is used, so the browser's plain popup never appears.
 *
 *   <form data-confirm="Body" data-confirm-title="Title?" data-confirm-button="Delete" data-confirm-danger>
 *   <form data-block="Why this can't be done" data-block-title="Can't delete">   -> explains, never submits
 *   <form data-confirm-payment>                       -> "Record 2,000.00 ETB into CBE · 1000… · Name?"
 *   <form data-confirm-if-unchecked="is_active" ...>  -> only asks when that checkbox is unticked
 *   <form data-confirm-input="reason" data-confirm-input-label="Reason" ...>
 *                                                     -> the popup asks for text (required) and sends it as "reason"
 *   <g data-tip="Text">                               -> hover tooltip (charts)
 */
(function () {
    'use strict';

    var BRAND = '#0284c7', DANGER = '#dc2626', MUTED = '#64748b';
    var swal = function () { return typeof window.Swal !== 'undefined' ? window.Swal : null; };

    function money(n) {
        return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ---- Built-in dialog (same look and behaviour as SweetAlert2) ----------------------------
    var ICONS = { warning: '!', question: '?', error: '×', success: '✓', info: 'i' };

    function builtInDialog(o) {
        return new Promise(function (resolve) {
            var last = document.activeElement;
            var wrap = document.createElement('div');
            wrap.className = 'sk-overlay';
            wrap.innerHTML =
                '<div class="sk-dialog" role="dialog" aria-modal="true" aria-labelledby="sk-title">' +
                '<div class="sk-icon sk-' + o.icon + '">' + (ICONS[o.icon] || '') + '</div>' +
                '<h2 id="sk-title" class="sk-title"></h2><p class="sk-text"></p>' +
                (o.input ? '<label class="sk-label"><span></span><input type="text" class="sk-input" maxlength="300" autocomplete="off"></label><div class="sk-error" role="alert"></div>' : '') +
                '<div class="sk-actions">' +
                (o.cancel ? '<button type="button" class="sk-btn sk-cancel"></button>' : '') +
                '<button type="button" class="sk-btn sk-ok"></button></div></div>';
            wrap.querySelector('.sk-title').textContent = o.title || '';
            var p = wrap.querySelector('.sk-text');
            if (o.text) { p.textContent = o.text; } else { p.remove(); }
            var ok = wrap.querySelector('.sk-ok');
            ok.textContent = o.confirm || 'OK';
            ok.style.background = o.danger ? DANGER : BRAND;
            var cancel = wrap.querySelector('.sk-cancel');
            if (cancel) { cancel.textContent = o.cancel; }
            var field = wrap.querySelector('.sk-input');
            if (field) {
                wrap.querySelector('.sk-label span').textContent = o.input;
                field.placeholder = o.placeholder || '';
                field.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); ok.click(); } });
            }

            function close(result) {
                document.removeEventListener('keydown', onKey, true);
                wrap.classList.add('sk-out');
                setTimeout(function () { wrap.remove(); if (last && last.focus) last.focus(); }, 150);
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                if (e.key === 'Tab') { // keep focus inside the dialog
                    var btns = wrap.querySelectorAll('button');
                    var i = Array.prototype.indexOf.call(btns, document.activeElement);
                    e.preventDefault();
                    btns[(i + (e.shiftKey ? -1 : 1) + btns.length) % btns.length].focus();
                }
            }
            ok.addEventListener('click', function () {
                if (field) {
                    if (!field.value.trim()) { wrap.querySelector('.sk-error').textContent = 'Please fill this in.'; field.focus(); return; }
                    return close(field.value.trim());
                }
                close(true);
            });
            if (cancel) cancel.addEventListener('click', function () { close(false); });
            wrap.addEventListener('mousedown', function (e) { if (e.target === wrap) close(false); });
            document.addEventListener('keydown', onKey, true);
            document.body.appendChild(wrap);
            (field || (o.danger && cancel ? cancel : ok)).focus();
        });
    }

    function builtInToast(icon, title) {
        var box = document.querySelector('.sk-toasts') || document.body.appendChild(Object.assign(document.createElement('div'), { className: 'sk-toasts' }));
        var t = document.createElement('div');
        t.className = 'sk-toast';
        t.setAttribute('role', 'status');
        t.innerHTML = '<span class="sk-icon sk-small sk-' + icon + '">' + (ICONS[icon] || '') + '</span><span></span><i></i>';
        t.children[1].textContent = title;
        box.appendChild(t);
        setTimeout(function () { t.classList.add('sk-out'); setTimeout(function () { t.remove(); }, 200); }, 3800);
    }

    // ---- One API for the rest of the app -------------------------------------------------------
    function ask(o) {
        var S = swal();
        var icon = o.danger ? 'warning' : 'question';
        if (S) {
            var cfg = {
                title: o.title || 'Are you sure?', text: o.text || '', icon: icon,
                showCancelButton: true, confirmButtonText: o.button || 'Yes, continue', cancelButtonText: 'Go back',
                confirmButtonColor: o.danger ? DANGER : BRAND, cancelButtonColor: MUTED,
                reverseButtons: true, focusCancel: !!o.danger && !o.input
            };
            if (o.input) {
                cfg.input = 'text';
                cfg.inputLabel = o.input;
                cfg.inputPlaceholder = o.placeholder || '';
                cfg.inputAttributes = { maxlength: 300 };
                cfg.inputValidator = function (v) { return (v || '').trim() ? null : 'Please fill this in.'; };
            }
            return S.fire(cfg).then(function (r) { return r.isConfirmed ? (o.input ? String(r.value).trim() : true) : false; });
        }
        return builtInDialog({ icon: icon, title: o.title || 'Are you sure?', text: o.text, confirm: o.button || 'Yes, continue',
            cancel: 'Go back', danger: o.danger, input: o.input, placeholder: o.placeholder });
    }

    function tell(icon, title, text) {
        var S = swal();
        if (S) return S.fire({ icon: icon, title: title, text: text || '', confirmButtonColor: BRAND, confirmButtonText: 'OK' });
        return builtInDialog({ icon: icon, title: title, text: text, confirm: 'OK' });
    }

    function toast(icon, title) {
        var S = swal();
        if (S) return S.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3800, timerProgressBar: true }).fire({ icon: icon, title: title });
        builtInToast(icon, title);
    }

    window.Solkeb = { ask: ask, tell: tell, toast: toast };

    function paymentQuestion(form) {
        var amount = form.querySelector('[name=amount]');
        var method = form.querySelector('[name=received_into]') || form.querySelector('[name=method]');
        var type = form.querySelector('[name=type]');
        var ref = form.querySelector('[name=reference]');
        var methodText = method ? method.options[method.selectedIndex].text : '';
        var kind = type ? type.value : 'payment';
        return {
            title: (kind === 'refund' ? 'Refund ' : 'Record ') + money(amount && amount.value) + ' ETB?',
            text: (kind === 'refund' ? 'Money going back to the guest' : (kind === 'deposit' ? 'Deposit' : 'Payment'))
                + (method && method.name === 'received_into' ? ' into ' : ' by ') + methodText + (ref && ref.value ? ' · ref ' + ref.value : '') + '.',
            button: kind === 'refund' ? 'Refund' : 'Record payment',
            danger: kind === 'refund'
        };
    }

    // ---- Confirm (or explain) before submitting ---------------------------------------------------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirmed === '1') return;

        if (form.hasAttribute('data-block')) {
            e.preventDefault();
            tell('info', form.getAttribute('data-block-title') || 'Not possible', form.getAttribute('data-block'));
            return;
        }

        var opts = null;
        if (form.hasAttribute('data-confirm-payment')) {
            opts = paymentQuestion(form);
        } else if (form.hasAttribute('data-confirm') || form.hasAttribute('data-confirm-title')) {
            var onlyIf = form.getAttribute('data-confirm-if-unchecked');
            if (onlyIf) {
                var box = form.querySelector('[name="' + onlyIf + '"]');
                if (box && box.checked) return;
            }
            opts = {
                title: form.getAttribute('data-confirm-title'),
                text: form.getAttribute('data-confirm'),
                button: form.getAttribute('data-confirm-button'),
                danger: form.hasAttribute('data-confirm-danger'),
                input: form.getAttribute('data-confirm-input-label'),
                placeholder: form.getAttribute('data-confirm-input-placeholder')
            };
            var inputName = form.getAttribute('data-confirm-input');
            if (inputName && !opts.input) opts.input = 'Reason';
            if (!inputName) opts.input = null;
        }
        if (!opts) return;

        e.preventDefault();
        var submitter = e.submitter;
        ask(opts).then(function (ok) {
            if (!ok) return;
            if (opts.input && typeof ok === 'string') {
                var name = form.getAttribute('data-confirm-input');
                var hidden = form.querySelector('input[type=hidden][name="' + name + '"]');
                if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = name; form.appendChild(hidden); }
                hidden.value = ok;
            }
            form.dataset.confirmed = '1';
            // Next tick: a submit requested while the first submit event is still being handled is ignored by the browser
            setTimeout(function () {
                // Call through the prototype so fields named "submit" can't get in the way
                var proto = HTMLFormElement.prototype;
                if (proto.requestSubmit) { proto.requestSubmit.call(form, submitter && submitter.form === form ? submitter : undefined); }
                else { proto.submit.call(form); }
            }, 0);
        });
    });

    // ---- Required fields: show the browser's check in our popup instead of its tooltip -----------
    document.addEventListener('invalid', function (e) {
        var field = e.target, form = field.form;
        if (!form || form.dataset.invalidShown === '1') { e.preventDefault(); return; }
        e.preventDefault();
        form.dataset.invalidShown = '1';
        var holder = field.closest('.field') || field.parentElement;
        var label = (field.id && document.querySelector('label[for="' + field.id + '"]')) || (holder && holder.querySelector('label'));
        var name = label ? label.textContent.replace('*', '').trim() : 'This field';
        tell('warning', 'Please check the form', name + ': ' + field.validationMessage).then(function () {
            field.focus();
        });
        setTimeout(function () { delete form.dataset.invalidShown; }, 0);
    }, true);

    // ---- Stop double clicks creating double payments/bookings ------------------------------------
    document.addEventListener('submit', function (e) {
        var form = e.target;
        // getAttribute, not form.method: a field named "method" (the payment form has one) hides that property
        if (!(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
        setTimeout(function () {
            if (e.defaultPrevented) return;
            form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) {
                b.disabled = true;
                b.dataset.label = b.textContent;
                b.textContent = 'Please wait…';
            });
        }, 0);
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('button[data-label]').forEach(function (b) {
            b.disabled = false; b.textContent = b.dataset.label; delete b.dataset.label;
        });
        document.querySelectorAll('form[data-confirmed]').forEach(function (f) { delete f.dataset.confirmed; });
    });

    // ---- Success / error messages from the server -------------------------------------------------
    function showFlash() {
        var ok = document.querySelector('[data-flash=success]');
        var err = document.querySelector('[data-flash=error]');
        var list = document.querySelector('[data-flash-errors]');
        if (err) { err.hidden = true; tell('error', 'Not done', err.textContent.trim()); }
        if (ok) { ok.hidden = true; if (!err) toast('success', ok.textContent.trim()); }
        if (list && !err) toast('warning', 'Please check the form'); // the list stays on the page next to the form
    }

    // ---- ID photos: shrink phone pictures before upload (about 300 KB instead of 3-5 MB) ----------
    function fileNote(input, text) {
        var note = input.parentNode.querySelector('.file-note');
        if (!note) { note = document.createElement('div'); note.className = 'file-note'; input.after(note); }
        note.textContent = text;
    }
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('input[type=file][data-compress]')) return;
        var f = input.files && input.files[0];
        if (!f) { fileNote(input, ''); return; }
        var kb = function (n) { return Math.round(n / 1024) + ' KB'; };
        if (!/^image\/(jpeg|png|webp)$/.test(f.type) || typeof DataTransfer === 'undefined') { fileNote(input, '✓ ' + f.name + ' (' + kb(f.size) + ')'); return; }
        fileNote(input, 'Preparing photo…');
        var url = URL.createObjectURL(f), img = new Image();
        img.onload = function () {
            var scale = Math.min(1, 1600 / Math.max(img.width, img.height));
            var c = document.createElement('canvas');
            c.width = Math.round(img.width * scale); c.height = Math.round(img.height * scale);
            var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob(function (blob) {
                if (!blob || blob.size >= f.size) { fileNote(input, '✓ Photo ready (' + kb(f.size) + ')'); return; }
                var dt = new DataTransfer();
                dt.items.add(new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                input.files = dt.files;
                fileNote(input, '✓ Photo ready (' + kb(blob.size) + ')');
            }, 'image/jpeg', 0.82);
        };
        img.onerror = function () { URL.revokeObjectURL(url); fileNote(input, '✓ ' + f.name); };
        img.src = url;
    });

    // ---- Chart tooltips -----------------------------------------------------------------------------
    function tooltips() {
        var tip = null;
        document.querySelectorAll('[data-tip]').forEach(function (el) {
            el.addEventListener('mouseenter', function () {
                tip = tip || Object.assign(document.createElement('div'), { className: 'tip' });
                tip.textContent = el.getAttribute('data-tip');
                document.body.appendChild(tip);
                el.classList.add('on');
            });
            el.addEventListener('mousemove', function (ev) {
                if (!tip) return;
                tip.style.left = Math.min(ev.clientX + 14, window.innerWidth - tip.offsetWidth - 8) + 'px';
                tip.style.top = (ev.clientY - tip.offsetHeight - 10) + 'px';
            });
            el.addEventListener('mouseleave', function () {
                el.classList.remove('on');
                if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
            });
        });
    }

    function init() { showFlash(); tooltips(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
