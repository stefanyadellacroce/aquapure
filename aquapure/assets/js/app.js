(() => {
    'use strict';

    const pendingForms = new Map();
    const initialized = new WeakSet();
    let fieldSequence = 0;
    const find = (selector) => {
        try { return selector ? document.querySelector(selector) : null; }
        catch { return null; }
    };
    const once = (selector, initialize) => document.querySelectorAll(selector).forEach((element) => {
        if (initialized.has(element)) return;
        initialized.add(element);
        initialize(element);
    });

    function resetSubmission(form) {
        const state = pendingForms.get(form);
        if (!state) return;
        state.buttons.forEach(({button, html, ariaDisabled}) => {
            if (button instanceof HTMLButtonElement) button.innerHTML = html;
            if (ariaDisabled === null) button.removeAttribute('aria-disabled');
            else button.setAttribute('aria-disabled', ariaDisabled);
        });
        if (state.ariaBusy === null) form.removeAttribute('aria-busy');
        else form.setAttribute('aria-busy', state.ariaBusy);
        delete form.dataset.submitting;
        pendingForms.delete(form);
    }

    function initializeConfirmations() {
        const element = document.getElementById('confirmModal');
        const form = document.getElementById('confirmForm');
        if (!element || !form || !window.bootstrap?.Modal) return;
        const modal = window.bootstrap.Modal.getOrCreateInstance(element);
        let trigger = null;
        once('[data-confirm]', (button) => button.addEventListener('click', () => {
            trigger = button;
            resetSubmission(form);
            form.action = button.dataset.action || window.location.href;
            document.getElementById('confirmMessage').textContent = button.dataset.confirm || 'Continue with this action?';
            document.getElementById('confirmTitle').textContent = button.dataset.confirmTitle || 'Confirm action';
            form.querySelector('button[type="submit"]').textContent = button.dataset.confirmLabel || 'Confirm';
            form.elements.namedItem('action').value = button.dataset.formAction || '';
            form.elements.namedItem('id').value = button.dataset.id || '';
            modal.show(button);
        }));
        element.addEventListener('shown.bs.modal', () => {
            element.querySelector('[data-bs-dismiss="modal"].btn-light')?.focus();
        });
        element.addEventListener('hidden.bs.modal', () => {
            trigger?.focus();
        });
    }

    function initializeTableSearch() {
        once('[data-table-search]', (input) => {
            const table = find(input.dataset.tableSearch);
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter(
                (row) => !row.classList.contains('table-empty-row') && !row.querySelector('[colspan]'),
            );
            const panel = table.closest('.panel-card') || table.parentElement;
            const empty = document.createElement('div');
            empty.className = 'search-empty';
            empty.hidden = true;
            const message = document.createElement('p');
            message.textContent = 'No displayed records match your search.';
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'btn btn-light btn-sm';
            clear.textContent = 'Clear search';
            empty.append(message, clear);
            panel.append(empty);
            const count = document.createElement('span');
            count.className = 'search-results-count';
            count.setAttribute('role', 'status');
            input.closest('.search-box')?.append(count);
            input.disabled = rows.length === 0;
            const update = () => {
                const term = input.value.toLocaleLowerCase().trim();
                let visible = 0;
                rows.forEach((row) => {
                    const matches = !term || row.textContent.toLocaleLowerCase().includes(term);
                    row.hidden = !matches;
                    if (matches) visible += 1;
                });
                empty.hidden = !term || visible > 0;
                count.textContent = term ? `${visible} of ${rows.length} displayed records` : '';
            };
            input.addEventListener('input', update);
            clear.addEventListener('click', () => { input.value = ''; update(); input.focus(); });
            update();
        });
    }

    function initializeConditionalFields() {
        once('[data-order-type]', (select) => {
            const target = find(select.dataset.orderType);
            const update = () => {
                const delivery = select.value === 'Delivery';
                target?.classList.toggle('d-none', !delivery);
                target?.querySelectorAll('input, textarea, select').forEach((field) => {
                    if (['recipient_name', 'contact_number', 'delivery_address', 'preferred_date'].includes(field.name)) {
                        field.required = delivery;
                    }
                });
            };
            select.addEventListener('change', update);
            update();
        });
        once('[data-payment-method]', (select) => {
            const field = select.closest('form')?.querySelector('[data-reference-field]');
            const input = field?.querySelector('[name="reference_number"]');
            const update = () => {
                const required = ['GCash', 'Maya'].includes(select.value);
                field?.classList.toggle('d-none', !required);
                if (input) input.required = required;
            };
            select.addEventListener('change', update);
            update();
        });
        once('[data-date-range]', (select) => {
            const fields = select.closest('form')?.querySelector('[data-custom-range-fields]');
            const update = () => {
                const custom = select.value === 'custom';
                fields?.classList.toggle('d-none', !custom);
                fields?.querySelectorAll('input').forEach((input) => {
                    input.disabled = !custom;
                    input.required = custom;
                });
            };
            select.addEventListener('change', update);
            update();
        });
        once('[data-cash-calculator]', (container) => {
            const due = Number(container.dataset.amountDue || 0);
            const received = container.querySelector('[name="amount_paid"]');
            const method = container.querySelector('[data-payment-method]');
            const output = container.querySelector('[data-change-output]');
            const update = () => {
                const amount = Number(received?.value || 0);
                const change = (!method || method.value === 'Cash') ? Math.max(0, amount - due) : 0;
                if (output) output.textContent = new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(change);
            };
            received?.addEventListener('input', update);
            method?.addEventListener('change', update);
            update();
        });
    }

    function initializePasswordsAndInvitations() {
        const input = document.querySelector('[data-invitation-code]');
        if (input instanceof HTMLInputElement && window.location.hash.startsWith('#invite=')) {
            try {
                const token = decodeURIComponent(window.location.hash.slice(8));
                if (/^AQ-(?:AD|ST)-[A-F0-9]{48}$/i.test(token)) input.value = token.toUpperCase();
            } catch { /* A malformed fragment must not fill the invitation field. */ }
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
        }
        once('[data-password-toggle]', (button) => {
            button.setAttribute('aria-pressed', 'false');
            button.addEventListener('click', () => {
                const input = find(button.dataset.passwordToggle);
                if (!(input instanceof HTMLInputElement)) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.querySelector('i')?.classList.toggle('bi-eye', !show);
                button.querySelector('i')?.classList.toggle('bi-eye-slash', show);
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', String(show));
            });
        });
        once('[data-copy-target]', (button) => button.addEventListener('click', async () => {
            const input = find(button.dataset.copyTarget);
            if (!(input instanceof HTMLInputElement)) return;
            let copied = false;
            try {
                if (navigator.clipboard) { await navigator.clipboard.writeText(input.value); copied = true; }
                else { input.select(); copied = document.execCommand('copy'); }
            } catch { /* The status below supplies a manual recovery path. */ }
            let status = button.parentElement.querySelector('[data-copy-status]');
            if (!status) {
                status = document.createElement('span');
                status.dataset.copyStatus = '';
                status.className = 'form-text w-100';
                status.setAttribute('role', 'status');
                button.parentElement.append(status);
            }
            status.textContent = copied ? 'Copied.' : 'Select the text and copy it manually.';
            if (!copied) { input.focus(); input.select(); }
        }));
    }

    function initializeCartReview() {
        const quantities = document.querySelector('[data-cart-quantities]');
        const checkout = document.querySelector('[data-cart-checkout]');
        if (!quantities || !checkout) return;
        const button = checkout.querySelector('button[type="submit"]');
        const notice = checkout.querySelector('[data-cart-dirty]');
        const serverBlocked = button.disabled;
        const update = () => {
            const dirty = Array.from(quantities.querySelectorAll('input[type="number"]')).some((input) => input.value !== input.defaultValue);
            button.disabled = serverBlocked || dirty;
            notice.hidden = !dirty;
            return dirty;
        };
        quantities.addEventListener('input', update);
        window.addEventListener('pageshow', update);
        checkout.addEventListener('submit', (event) => {
            if (update() || serverBlocked) {
                event.preventDefault();
                document.getElementById('updateCartButton')?.focus();
            }
        });
        update();
    }

    function initializeProductImages() {
        once('.product-art img, img.product-thumb', (img) => {
            const fallback = () => {
                if (img.hidden) return;
                img.hidden = true;
                const label = document.createElement('span');
                label.className = img.classList.contains('product-thumb') ? 'product-thumb product-thumb-placeholder' : 'product-image-fallback';
                label.setAttribute('role', 'img');
                label.setAttribute('aria-label', `${img.alt || 'Product'} image unavailable`);
                const icon = document.createElement('i');
                icon.className = 'bi bi-droplet'; icon.setAttribute('aria-hidden', 'true');
                label.append(icon);
                img.insertAdjacentElement('afterend', label);
            };
            img.addEventListener('error', fallback);
            if (img.complete && img.naturalWidth === 0) fallback();
        });
    }

    function clearFieldError(field) {
        if (!field.matches?.('.is-invalid') || (field.willValidate && !field.validity.valid)) return;
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const errorId = `${field.id}-error`;
        document.getElementById(errorId)?.remove();
        const described = (field.getAttribute('aria-describedby') || '').split(' ').filter((id) => id !== errorId).join(' ');
        if (described) field.setAttribute('aria-describedby', described);
        else field.removeAttribute('aria-describedby');
    }

    function showValidation(form) {
        Array.from(form.elements).forEach(clearFieldError);
        const fields = Array.from(form.elements).filter((field) => field.willValidate && !field.validity.valid);
        let summary = form.querySelector('[data-validation-summary]');
        if (!fields.length) { summary?.remove(); return true; }
        if (!summary) {
            summary = document.createElement('section');
            summary.dataset.validationSummary = '';
            summary.className = 'alert alert-danger validation-summary w-100';
            summary.tabIndex = -1;
            summary.setAttribute('role', 'alert');
            form.prepend(summary);
        }
        summary.replaceChildren();
        const heading = document.createElement('h2');
        heading.className = 'h6';
        heading.textContent = `Check ${fields.length === 1 ? 'this field' : 'these fields'} before continuing`;
        const list = document.createElement('ul');
        list.className = 'mb-0';
        fields.forEach((field) => {
            if (!field.id) {
                do { field.id = `aq-field-${++fieldSequence}`; } while (document.querySelectorAll(`#${field.id}`).length > 1);
            }
            const errorId = `${field.id}-error`;
            let error = document.getElementById(errorId);
            if (!error) {
                error = document.createElement('div');
                error.id = errorId;
                error.className = 'invalid-feedback d-block';
                (field.closest('.input-group') || field).insertAdjacentElement('afterend', error);
            }
            error.textContent = field.validationMessage;
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
            const described = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
            described.add(errorId);
            field.setAttribute('aria-describedby', Array.from(described).join(' '));
            const label = field.labels?.[0]?.textContent.trim() || field.getAttribute('aria-label') || 'Field';
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = `#${field.id}`;
            link.textContent = `${label.replace(/\s*\*\s*$/, '')}: ${field.validationMessage}`;
            link.addEventListener('click', (event) => { event.preventDefault(); field.focus(); });
            item.append(link); list.append(item);
        });
        summary.append(heading, list);
        summary.focus();
        return false;
    }

    function initializeForms() {
        const labels = {
            checkout: 'Placing order…', create_walkin_order: 'Creating order…', record_payment: 'Recording payment…',
            save_customer: 'Saving customer…', save_product: 'Saving product…', save_inventory: 'Saving inventory…',
            save_settings: 'Saving settings…', update_profile: 'Saving changes…', create_invitation: 'Creating invitation…',
        };
        document.querySelectorAll('form').forEach((form) => {
            // Restore native validation automatically when JavaScript is absent.
            form.noValidate = true;
            form.addEventListener('input', (event) => clearFieldError(event.target));
            form.addEventListener('change', () => Array.from(form.elements).forEach(clearFieldError));
        });
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
            if (pendingForms.has(form)) { event.preventDefault(); return; }
            const submitter = event.submitter;
            if (!submitter?.formNoValidate && !showValidation(form)) { event.preventDefault(); return; }
            const confirmation = submitter?.dataset.inlineConfirm || form.querySelector('[data-inline-confirm]')?.dataset.inlineConfirm;
            if (confirmation && !window.confirm(confirmation)) { event.preventDefault(); return; }
            const method = submitter?.getAttribute('formmethod') || form.method;
            if (method.toLowerCase() !== 'post') return;
            const buttons = Array.from(form.elements).filter((control) => ['submit', 'image'].includes(control.type));
            const state = {ariaBusy: form.getAttribute('aria-busy'), buttons: buttons.map((button) => ({button, html: button.innerHTML, ariaDisabled: button.getAttribute('aria-disabled')}))};
            pendingForms.set(form, state);
            // A later handler may cancel the submission; let it finish first.
            queueMicrotask(() => {
                if (event.defaultPrevented) { resetSubmission(form); return; }
                form.dataset.submitting = 'true'; form.setAttribute('aria-busy', 'true');
                buttons.forEach((button) => button.setAttribute('aria-disabled', 'true'));
                const button = submitter || buttons[0];
                if (button instanceof HTMLButtonElement) {
                    const action = form.elements.namedItem('action')?.value || '';
                    const spinner = document.createElement('span');
                    spinner.className = 'spinner-border spinner-border-sm'; spinner.setAttribute('aria-hidden', 'true');
                    const label = document.createElement('span');
                    label.textContent = button.dataset.processingLabel || labels[action] || 'Processing…';
                    button.replaceChildren(spinner, label);
                }
                // Never disable controls or alter input values: named submitters remain successful controls.
            });
        });
        window.addEventListener('pageshow', () => Array.from(pendingForms.keys()).forEach(resetSubmission));
        document.addEventListener('aquapure:submit-reset', (event) => {
            if (event.target instanceof HTMLFormElement) resetSubmission(event.target);
        });
    }

    function start() {
        if (document.documentElement.dataset.aqInitialized) return;
        document.documentElement.dataset.aqInitialized = 'true';
        initializeConfirmations();
        initializeTableSearch();
        initializeConditionalFields();
        initializePasswordsAndInvitations();
        initializeCartReview();
        initializeProductImages();
        initializeForms();
        once('[data-print-page]', (button) => button.addEventListener('click', () => window.print()));
        const summary = document.querySelector('[data-error-summary]');
        if (summary) summary.focus();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
})();
