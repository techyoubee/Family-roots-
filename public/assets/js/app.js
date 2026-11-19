/**
 * Family Roots - core UI behaviour.
 *
 * Progressive enhancement only: every feature here degrades to a working
 * server-rendered page when JavaScript is unavailable.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------- mobile nav */
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ------------------------------------------------ flash messages */
    document.querySelectorAll('.alert__close').forEach(function (button) {
        button.addEventListener('click', function () {
            var alert = button.closest('.alert');
            if (alert) {
                alert.remove();
            }
        });
    });

    document.querySelectorAll('.alert[data-autoclose]').forEach(function (alert) {
        window.setTimeout(function () {
            alert.style.transition = 'opacity .4s ease';
            alert.style.opacity = '0';
            window.setTimeout(function () { alert.remove(); }, 400);
        }, 6000);
    });

    /* -------------------------------------------------- form states */
    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[type="submit"]');
            if (!submit) {
                return;
            }
            var spinner = document.createElement('span');
            spinner.className = 'spinner';
            submit.classList.add('is-loading');
            submit.insertBefore(spinner, submit.firstChild);
            // Prevent double submissions without blocking the form submit.
            window.setTimeout(function () { submit.disabled = true; }, 0);
        });
    });

    /* ------------------------------------------- destructive actions */
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        var message = element.getAttribute('data-confirm');
        if (element.tagName === 'FORM') {
            // Fires for button clicks and Enter-key submissions alike.
            element.addEventListener('submit', function (event) {
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
            return;
        }
        element.addEventListener('click', function (event) {
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    /* ------------------------------------------------ password reveal */
    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!target) {
                return;
            }
            var reveal = target.type === 'password';
            target.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        });
    });

    /* --------------------------------------------------- copy button */
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-copy');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(value).then(function () {
                    var original = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function () { button.textContent = original; }, 1500);
                });
            }
        });
    });

    /* --------------------------------------------- auto-submit filters */
    document.querySelectorAll('[data-auto-submit]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.form) {
                input.form.submit();
            }
        });
    });
})();
