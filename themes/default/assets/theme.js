(function () {
    'use strict';

    var root = document.documentElement;
    var themeButton = document.querySelector('[data-theme-toggle]');

    function currentTheme() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        if (theme === 'dark') {
            root.setAttribute('data-theme', 'dark');
        } else {
            root.removeAttribute('data-theme');
        }
        if (themeButton) {
            themeButton.setAttribute('aria-label', theme === 'dark' ? '切换到浅色模式' : '切换到深色模式');
        }
    }

    if (themeButton) {
        applyTheme(currentTheme());
        themeButton.addEventListener('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try { localStorage.setItem('wenblog-theme', next); } catch (error) {}
        });
    }

    var navButton = document.querySelector('[data-nav-toggle]');
    var mobileMenu = document.querySelector('[data-mobile-menu]');
    if (navButton && mobileMenu) {
        navButton.addEventListener('click', function () {
            var open = mobileMenu.hasAttribute('hidden');
            if (open) {
                mobileMenu.removeAttribute('hidden');
            } else {
                mobileMenu.setAttribute('hidden', '');
            }
            navButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !mobileMenu.hasAttribute('hidden')) {
                mobileMenu.setAttribute('hidden', '');
                navButton.setAttribute('aria-expanded', 'false');
                navButton.focus();
            }
        });
        window.addEventListener('resize', function () {
            if (window.innerWidth > 780) {
                mobileMenu.setAttribute('hidden', '');
                navButton.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-flash]').forEach(function (flash) {
        var close = flash.querySelector('[data-flash-close]');
        var timer = window.setTimeout(function () {
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-4px)';
            window.setTimeout(function () { flash.remove(); }, 180);
        }, 6000);
        if (close) {
            close.addEventListener('click', function () {
                window.clearTimeout(timer);
                flash.remove();
            });
        }
    });

    var parentInput = document.querySelector('[data-parent-id]');
    var replyNotice = document.querySelector('[data-reply-notice]');
    var replyName = document.querySelector('[data-reply-name]');
    var commentForm = document.getElementById('comment-form');
    var textarea = commentForm ? commentForm.querySelector('textarea[name="content"]') : null;

    function resetReply() {
        if (parentInput) parentInput.value = '';
        if (replyNotice) replyNotice.setAttribute('hidden', '');
        if (replyName) replyName.textContent = '';
    }

    document.querySelectorAll('[data-reply-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!parentInput || !replyNotice || !textarea) return;
            parentInput.value = button.getAttribute('data-reply-toggle') || '';
            if (replyName) replyName.textContent = button.getAttribute('data-reply-name') || '';
            replyNotice.removeAttribute('hidden');
            commentForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.setTimeout(function () { textarea.focus(); }, 250);
        });
    });

    document.querySelectorAll('[data-reply-cancel]').forEach(function (button) {
        button.addEventListener('click', resetReply);
    });
})();