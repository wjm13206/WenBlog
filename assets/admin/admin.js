/* ==========================================================================
   WenBlog admin interactions
   No dependencies. Every behavior is opt-in through data attributes.
   ========================================================================== */

(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;

    function ready(fn) {
        if (doc.readyState === 'loading') {
            doc.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    function formatBytes(bytes) {
        var value = Number(bytes) || 0;
        if (value < 1024) return value + ' B';
        if (value < 1024 * 1024) return (value / 1024).toFixed(1) + ' KB';
        return (value / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function effectiveTheme() {
        var explicit = root.getAttribute('data-theme');
        if (explicit === 'dark' || explicit === 'light') return explicit;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /* ---------------------------------------------------------------- theme */

    function initTheme() {
        var buttons = doc.querySelectorAll('[data-theme-toggle]');
        if (!buttons.length) return;

        function sync() {
            var dark = effectiveTheme() === 'dark';
            buttons.forEach(function (button) {
                var label = dark ? '切换到浅色模式' : '切换到深色模式';
                button.setAttribute('aria-label', label);
                button.setAttribute('title', label);
                button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            });
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var next = effectiveTheme() === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('wenblog-theme', next);
                } catch (error) {
                    /* Storage can be unavailable in private contexts. */
                }
                sync();
            });
        });

        sync();
    }

    /* -------------------------------------------------------------- sidebar */

    function initSidebar() {
        var shell = doc.querySelector('.admin-shell');
        if (!shell) return;

        var toggle = doc.querySelector('[data-sidebar-toggle]');
        var overlay = doc.querySelector('[data-sidebar-close]');
        var mobile = window.matchMedia('(max-width: 900px)');

        function setOpen(open) {
            shell.classList.toggle('sidebar-open', open);
            doc.body.classList.toggle('nav-open', open && mobile.matches);
            if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function close() {
            setOpen(false);
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                setOpen(!shell.classList.contains('sidebar-open'));
            });
        }
        if (overlay) overlay.addEventListener('click', close);

        shell.querySelectorAll('.side-nav a').forEach(function (link) {
            link.addEventListener('click', function () {
                if (mobile.matches) close();
            });
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && shell.classList.contains('sidebar-open')) {
                close();
                if (toggle) toggle.focus();
            }
        });

        var onViewport = function () {
            if (!mobile.matches) {
                shell.classList.remove('sidebar-open');
                doc.body.classList.remove('nav-open');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            }
        };
        if (mobile.addEventListener) mobile.addEventListener('change', onViewport);
        else if (mobile.addListener) mobile.addListener(onViewport);

        setOpen(false);
    }

    /* --------------------------------------------------------- flash alerts */

    function initFlashAlerts() {
        doc.querySelectorAll('[data-auto-hide]').forEach(function (alert) {
            var delay = parseInt(alert.getAttribute('data-auto-hide'), 10);
            if (!Number.isFinite(delay) || delay <= 0) delay = 5000;
            var timer;

            function dismiss() {
                alert.style.transition = 'opacity .3s ease, transform .3s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-4px)';
                window.setTimeout(function () { alert.remove(); }, 320);
            }

            function schedule(wait) {
                window.clearTimeout(timer);
                timer = window.setTimeout(dismiss, wait);
            }

            schedule(delay);
            alert.addEventListener('mouseenter', function () { window.clearTimeout(timer); });
            alert.addEventListener('mouseleave', function () { schedule(1800); });
        });
    }

    /* ------------------------------------------------------------- confirms */

    function initConfirms() {
        doc.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;

            var message = form.getAttribute('data-confirm') || '确定继续吗？';
            if (!window.confirm(message)) {
                event.preventDefault();
                return;
            }

            // Validation has already passed at this point. Submit natively so
            // this handler cannot ask for confirmation a second time.
            event.preventDefault();
            HTMLFormElement.prototype.submit.call(form);
        });

        doc.addEventListener('click', function (event) {
            var target = event.target.closest('[data-confirm]');
            if (!target || target.closest('form')) return;
            if (!window.confirm(target.getAttribute('data-confirm') || '确定继续吗？')) {
                event.preventDefault();
            }
        });
    }

    /* --------------------------------------------------------- panel toggles */

    function initPanelToggles() {
        doc.querySelectorAll('[data-toggle-panel]').forEach(function (button) {
            var id = button.getAttribute('data-toggle-panel');
            if (!id) return;
            var panel = doc.getElementById(id);
            if (!panel) return;

            button.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            button.addEventListener('click', function () {
                var show = panel.hidden;
                panel.hidden = !show;
                button.setAttribute('aria-expanded', show ? 'true' : 'false');
                if (show) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
    }

    /* ----------------------------------------------------------- copy button */

    function fallbackCopy(text) {
        var area = doc.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        area.style.opacity = '0';
        doc.body.appendChild(area);
        area.select();
        var ok = false;
        try {
            ok = doc.execCommand('copy');
        } catch (error) {
            ok = false;
        }
        area.remove();
        return ok;
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () {
                return true;
            }).catch(function () {
                return fallbackCopy(text);
            });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function initCopyButtons() {
        doc.querySelectorAll('[data-copy]').forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-copy') || '';
                if (!value) return;
                var original = button.innerHTML;

                copyText(value).then(function (ok) {
                    button.textContent = ok ? '已复制' : '复制失败';
                    button.style.width = 'auto';
                    button.style.padding = '0 8px';
                    window.setTimeout(function () {
                        button.innerHTML = original;
                        button.style.width = '';
                        button.style.padding = '';
                    }, 1400);
                });
            });
        });
    }

    /* ------------------------------------------------------------ replies */

    function initReplyToggles() {
        doc.querySelectorAll('[data-reply-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.getAttribute('data-reply-toggle');
                var form = id ? doc.getElementById(id) : null;
                if (!form) return;

                form.hidden = !form.hidden;
                button.setAttribute('aria-expanded', form.hidden ? 'false' : 'true');
                if (!form.hidden) {
                    var field = form.querySelector('textarea');
                    if (field) field.focus();
                }
            });
        });
    }

    /* --------------------------------------------------------------- uploads */

    function initUploads() {
        doc.querySelectorAll('[data-auto-submit]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.files && input.files.length > 0 && input.form) {
                    input.form.requestSubmit();
                }
            });
        });

        doc.querySelectorAll('[data-file-input]').forEach(function (input) {
            var zone = input.closest('[data-dropzone]');
            var label = zone ? zone.querySelector('[data-file-label]') : null;

            function updateLabel() {
                if (!label) return;
                var file = input.files && input.files[0];
                label.textContent = file
                    ? file.name + ' · ' + formatBytes(file.size)
                    : '点击选择文件，或将文件拖到这里';
            }

            input.addEventListener('change', updateLabel);

            if (!zone) return;

            ['dragenter', 'dragover'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    zone.classList.add('is-dragging');
                });
            });

            ['dragleave', 'drop'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (name === 'dragleave' && zone.contains(event.relatedTarget)) return;
                    zone.classList.remove('is-dragging');
                });
            });

            zone.addEventListener('drop', function (event) {
                var files = event.dataTransfer && event.dataTransfer.files;
                if (!files || !files.length) return;
                try {
                    var transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    input.files = transfer.files;
                } catch (error) {
                    // Older browsers can still submit through the hidden input
                    // after the user picks the file manually.
                    return;
                }
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    /* --------------------------------------------------------- settings tabs */

    function initSettingsTabs() {
        var tabs = Array.from(doc.querySelectorAll('[data-settings-tab]'));
        var panels = Array.from(doc.querySelectorAll('[data-settings-panel]'));
        if (!tabs.length || !panels.length) return;

        function activate(key) {
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-settings-tab') === key;
                tab.classList.toggle('active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-settings-panel') !== key;
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-settings-tab');
                if (key) activate(key);
            });
        });
    }

    /* --------------------------------------------------------- theme config */

    function initThemeConfig() {
        var form = doc.querySelector('[data-theme-config]');
        if (!form) return;

        var status = form.querySelector('[data-config-status]');
        var submit = form.querySelector('[data-config-submit]');
        var reset = form.querySelector('[data-config-reset]');
        var submitLabel = submit ? submit.innerHTML : '';
        var hideTimer = 0;

        function setStatus(text, kind) {
            if (!status) return;
            window.clearTimeout(hideTimer);
            status.textContent = text || '';
            status.classList.remove('is-success', 'is-error');
            if (kind) status.classList.add(kind);
        }

        function snapshot() {
            var pairs = [];
            new FormData(form).forEach(function (value, key) {
                if (key === '_token' || key === 'slug') return;
                pairs.push(key + '=' + value);
            });
            return pairs.sort().join('&');
        }

        var initial = snapshot();
        var saved = initial;

        function markDirty() {
            if (snapshot() !== saved) {
                setStatus('有未保存的修改。');
            } else {
                setStatus('');
            }
        }

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        form.addEventListener('submit', function (event) {
            // Without fetch the form still submits normally and keeps working.
            if (!window.fetch || !window.FormData) return;
            event.preventDefault();
            if (form.dataset.saving === '1') return;

            form.dataset.saving = '1';
            if (submit) {
                submit.disabled = true;
                submit.textContent = '保存中…';
            }
            setStatus('正在保存…');

            window.fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().catch(function () {
                    return null;
                }).then(function (data) {
                    if (!response.ok || !data || data.ok !== true) {
                        throw new Error((data && data.message) || ('保存失败（HTTP ' + response.status + '）。'));
                    }
                    return data;
                });
            }).then(function (data) {
                saved = snapshot();
                setStatus(data.message || '主题配置已保存。', 'is-success');
                hideTimer = window.setTimeout(function () {
                    setStatus('');
                }, 4000);
            }).catch(function (error) {
                setStatus((error && error.message) || '网络错误，请稍后重试。', 'is-error');
            }).then(function () {
                form.dataset.saving = '0';
                if (submit) {
                    submit.disabled = false;
                    submit.innerHTML = submitLabel;
                }
            });
        });

        if (reset) {
            reset.addEventListener('click', function () {
                form.reset();
                saved = initial;
                setStatus('已恢复到页面载入时的设置。');
            });
        }
    }

    /* ------------------------------------------------------------ Markdown */

    function initEditors() {
        var form = doc.querySelector('#post-form, #page-form');
        if (!form) return;

        var textarea = form.querySelector('textarea[name="content"]');
        if (!textarea) return;

        var labels = doc.querySelectorAll('[data-word-count]');
        var tabs = Array.from(form.querySelectorAll('[data-editor-tab]'));
        var panes = Array.from(form.querySelectorAll('[data-editor-pane]'));
        var paneWrap = form.querySelector('[data-editor-panes]');
        var previewBody = form.querySelector('[data-preview-body]');
        var previewUrl = form.getAttribute('data-preview-url') || '';
        var uploadUrl = form.getAttribute('data-upload-url') || '';
        var draftKey = form.getAttribute('data-draft-key') || '';
        var statusEl = form.querySelector('[data-autosave-status]');
        var restoreBar = form.querySelector('[data-draft-restore]');
        var tokenField = form.querySelector('input[name="_token"]');
        var previewDirty = true;
        var previewController = null;
        var previewTimer = null;
        var draftTimer = null;
        var mode = 'write';
        var uploading = 0;
        var draftFields = ['title', 'slug', 'content', 'excerpt', 'tags', 'cover'];

        function countUnits(text) {
            var value = String(text || '').trim();
            if (!value) return 0;
            var cjk = (value.match(/[\u3400-\u9fff\uf900-\ufaff]/g) || []).length;
            var rest = value.replace(/[\u3400-\u9fff\uf900-\ufaff]/g, ' ');
            var words = (rest.match(/[A-Za-z0-9_]+/g) || []).length;
            return cjk + words;
        }

        function updateWordCount() {
            var total = countUnits(textarea.value);
            labels.forEach(function (label) {
                label.textContent = total + ' 字';
            });
        }

        function previewVisible() {
            return mode === 'preview' || mode === 'split';
        }

        function setMode(next) {
            mode = next;
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-editor-tab') === next;
                tab.classList.toggle('active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            var split = next === 'split';
            if (paneWrap) paneWrap.classList.toggle('is-split', split);
            panes.forEach(function (pane) {
                pane.hidden = !split && pane.getAttribute('data-editor-pane') !== next;
            });

            if (previewVisible()) {
                if (previewDirty) loadPreview(split);
            } else {
                textarea.focus();
            }
        }

        function renderPreview(html) {
            if (!previewBody) return;
            previewBody.innerHTML = html || '<p class="muted">还没有可预览的内容。</p>';
            previewBody.setAttribute('aria-busy', 'false');
        }

        function loadPreview(quiet) {
            if (!previewBody) return;

            var content = textarea.value;
            if (!content.trim()) {
                previewDirty = false;
                renderPreview('<p class="muted">还没有可预览的内容。</p>');
                return;
            }

            if (!previewUrl) {
                previewDirty = false;
                renderPreview('<p class="muted">当前页面不支持预览。</p>');
                return;
            }

            if (previewController) previewController.abort();
            previewController = new AbortController();
            var signal = previewController.signal;

            previewBody.setAttribute('aria-busy', 'true');
            if (!quiet) {
                previewBody.innerHTML = '<p class="muted">正在渲染预览…</p>';
            }

            var params = new URLSearchParams();
            params.set('content', content);
            if (tokenField) params.set('_token', tokenField.value);

            fetch(previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params.toString(),
                signal: signal
            }).then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            }).then(function (payload) {
                if (signal.aborted) return;
                previewDirty = false;
                renderPreview(payload && payload.html ? payload.html : '');
            }).catch(function (error) {
                if (error && error.name === 'AbortError') return;
                previewBody.setAttribute('aria-busy', 'false');
                previewBody.innerHTML = '<p class="error-text">预览加载失败，请刷新页面或重新登录后重试。</p>';
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                setMode(tab.getAttribute('data-editor-tab') || 'write');
            });
        });

        textarea.addEventListener('input', function () {
            previewDirty = true;
            updateWordCount();
            schedulePreview(600);
            scheduleDraft();
        });

        form.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || target === textarea || !target.name) return;
            if (draftFields.indexOf(target.name) !== -1) scheduleDraft();
        });

        updateWordCount();

        /* ---- toolbar ---- */

        function focusAndNotify() {
            textarea.focus();
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function wrap(before, after, placeholder) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var selected = textarea.value.slice(start, end);
            var text = selected || placeholder || '';
            var replacement = before + text + after;

            textarea.setRangeText(replacement, start, end, 'end');
            if (!selected && text) {
                var inner = start + before.length;
                textarea.setSelectionRange(inner, inner + text.length);
            }
            focusAndNotify();
        }

        function prefixLines(prefix, numbered) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var value = textarea.value;
            var blockStart = value.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
            var blockEnd = value.indexOf('\n', end);
            if (blockEnd === -1) blockEnd = value.length;

            var block = value.slice(blockStart, blockEnd);
            var counter = 0;
            var output = block.split('\n').map(function (line) {
                if (line.trim() === '') return line;
                counter += 1;
                return (numbered ? counter + '. ' : prefix) + line;
            }).join('\n');

            textarea.setRangeText(output, blockStart, blockEnd, 'select');
            focusAndNotify();
        }

        function insertLink(image) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var selected = textarea.value.slice(start, end);
            var label = selected || (image ? '图片描述' : '链接文字');
            var mark = image ? '!' : '';
            var replacement = mark + '[' + label + '](https://)';

            textarea.setRangeText(replacement, start, end, 'end');
            var urlStart = start + mark.length + 1 + label.length + 2;
            textarea.setSelectionRange(urlStart, urlStart + 8);
            focusAndNotify();
        }

        function insertCode() {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            var selected = textarea.value.slice(start, end);

            if (selected.indexOf('\n') !== -1) {
                var fenced = '```\n' + selected + '\n```';
                textarea.setRangeText(fenced, start, end, 'end');
            } else {
                var inline = '`' + (selected || 'code') + '`';
                textarea.setRangeText(inline, start, end, 'end');
                if (!selected) {
                    textarea.setSelectionRange(start + 1, start + 5);
                }
            }
            focusAndNotify();
        }

        form.querySelectorAll('[data-md]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-md');
                switch (action) {
                    case 'bold':
                        wrap('**', '**', '加粗文本');
                        break;
                    case 'italic':
                        wrap('*', '*', '斜体文本');
                        break;
                    case 'heading':
                        prefixLines('## ', false);
                        break;
                    case 'quote':
                        prefixLines('> ', false);
                        break;
                    case 'ul':
                        prefixLines('- ', false);
                        break;
                    case 'ol':
                        prefixLines('', true);
                        break;
                    case 'link':
                        insertLink(false);
                        break;
                    case 'image':
                        if (filePicker) {
                            filePicker.click();
                        } else {
                            insertLink(true);
                        }
                        break;
                    case 'code':
                        insertCode();
                        break;
                }
            });
        });

        /* ---- local draft autosave ---- */

        function timeLabel() {
            var now = new Date();
            function pad(value) {
                return value < 10 ? '0' + value : String(value);
            }
            return pad(now.getHours()) + ':' + pad(now.getMinutes());
        }

        function setStatus(text, saving) {
            if (!statusEl) return;
            statusEl.textContent = text || '';
            statusEl.classList.toggle('is-saving', !!saving);
        }

        function draftPayload() {
            var data = { savedAt: Date.now() };
            draftFields.forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) data[name] = field.value;
            });
            return data;
        }

        function saveDraftNow() {
            if (!draftKey) return;
            try {
                window.localStorage.setItem(draftKey, JSON.stringify(draftPayload()));
                setStatus('已自动保存 ' + timeLabel());
            } catch (error) {
                setStatus('本地存储不可用');
            }
        }

        function scheduleDraft() {
            if (!draftKey) return;
            window.clearTimeout(draftTimer);
            setStatus('正在保存…', true);
            draftTimer = window.setTimeout(saveDraftNow, 1200);
        }

        function discardDraft() {
            if (!draftKey) return;
            try {
                window.localStorage.removeItem(draftKey);
            } catch (error) {
                /* Storage can be unavailable in private contexts. */
            }
        }

        function applyDraft(data) {
            draftFields.forEach(function (name) {
                if (typeof data[name] !== 'string') return;
                var field = form.querySelector('[name="' + name + '"]');
                if (field) field.value = data[name];
            });
            updateWordCount();
            previewDirty = true;
            if (previewVisible()) loadPreview(true);
            setStatus('已恢复本地草稿');
        }

        function initDraft() {
            if (!draftKey) return;
            var raw = null;
            try {
                raw = window.localStorage.getItem(draftKey);
            } catch (error) {
                return;
            }
            if (!raw) return;

            var data = null;
            try {
                data = JSON.parse(raw);
            } catch (error) {
                discardDraft();
                return;
            }
            if (!data || !data.savedAt) return;

            var current = draftPayload();
            var differs = draftFields.some(function (name) {
                return typeof data[name] === 'string' && data[name] !== (current[name] || '');
            });
            if (!differs) {
                discardDraft();
                return;
            }

            if (!restoreBar) {
                setStatus('本地草稿可用');
                return;
            }
            var when = new Date(data.savedAt);
            var textEl = restoreBar.querySelector('[data-draft-restore-text]');
            if (textEl) {
                textEl.textContent = '检测到 ' + when.toLocaleString() + ' 的本地自动保存内容。';
            }
            restoreBar.hidden = false;

            var accept = restoreBar.querySelector('[data-draft-accept]');
            if (accept) {
                accept.addEventListener('click', function () {
                    applyDraft(data);
                    restoreBar.hidden = true;
                });
            }
            var discard = restoreBar.querySelector('[data-draft-discard]');
            if (discard) {
                discard.addEventListener('click', function () {
                    discardDraft();
                    restoreBar.hidden = true;
                    setStatus('');
                });
            }
        }

        /* ---- live preview ---- */

        function schedulePreview(delay) {
            if (!previewUrl || !previewVisible()) return;
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(function () {
                loadPreview(true);
            }, typeof delay === 'number' ? delay : 600);
        }

        /* ---- image uploads ---- */

        function insertAtCursor(text) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;
            textarea.setRangeText(text, start, end, 'end');
            focusAndNotify();
        }

        function uploadImage(file) {
            if (!uploadUrl || !file) return;
            if (uploading >= 3) {
                setStatus('同时上传的图片过多，请稍候');
                return;
            }
            uploading += 1;

            var mark = '![uploading-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8) + ']()';
            var prefix = textarea.value === '' || textarea.value.slice(-1) === '\n' ? '' : '\n\n';
            insertAtCursor(prefix + mark + '\n\n');

            var body = new FormData();
            body.append('file', file);
            if (tokenField) body.append('_token', tokenField.value);

            setStatus('正在上传图片…', true);

            fetch(uploadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (payload) {
                    if (!response.ok) {
                        throw new Error(payload && payload.error ? payload.error : 'HTTP ' + response.status);
                    }
                    return payload;
                });
            }).then(function (payload) {
                if (!payload || !payload.url) throw new Error('上传失败');
                var alt = String(payload.alt || file.name || 'image').replace(/\.[^.]+$/, '');
                textarea.value = textarea.value.replace(mark, '![' + alt + '](' + payload.url + ')');
                focusAndNotify();
                setStatus('图片已插入');
            }).catch(function (error) {
                textarea.value = textarea.value.replace(mark, '');
                focusAndNotify();
                setStatus('图片上传失败：' + (error && error.message ? error.message : '未知错误'));
            }).then(function () {
                uploading = Math.max(0, uploading - 1);
            });
        }

        function imageFilesFromTransfer(transfer) {
            var files = [];
            if (!transfer) return files;
            Array.prototype.forEach.call(transfer.files || [], function (file) {
                if (file && /^image\//i.test(file.type)) files.push(file);
            });
            return files;
        }

        function transferHasImages(transfer) {
            if (!transfer) return false;
            if (transfer.items) {
                for (var index = 0; index < transfer.items.length; index += 1) {
                    var item = transfer.items[index];
                    if (item && item.kind === 'file' && /^image\//i.test(item.type)) return true;
                }
            }
            return imageFilesFromTransfer(transfer).length > 0;
        }

        var filePicker = null;
        if (uploadUrl) {
            filePicker = doc.createElement('input');
            filePicker.type = 'file';
            filePicker.accept = 'image/*';
            filePicker.multiple = true;
            filePicker.hidden = true;
            form.appendChild(filePicker);
            filePicker.addEventListener('change', function () {
                Array.prototype.forEach.call(filePicker.files || [], uploadImage);
                filePicker.value = '';
            });
        }

        textarea.addEventListener('paste', function (event) {
            var clipboard = event.clipboardData;
            if (!clipboard || !uploadUrl) return;
            var images = [];
            Array.prototype.forEach.call(clipboard.items || [], function (item) {
                if (item && item.kind === 'file' && /^image\//i.test(item.type)) {
                    var file = item.getAsFile();
                    if (file) images.push(file);
                }
            });
            if (!images.length) return;
            event.preventDefault();
            images.forEach(uploadImage);
        });

        ['dragenter', 'dragover'].forEach(function (name) {
            textarea.addEventListener(name, function (event) {
                if (!uploadUrl || !transferHasImages(event.dataTransfer)) return;
                event.preventDefault();
                textarea.classList.add('is-dragover');
            });
        });

        textarea.addEventListener('dragleave', function () {
            textarea.classList.remove('is-dragover');
        });

        textarea.addEventListener('drop', function (event) {
            var images = imageFilesFromTransfer(event.dataTransfer);
            if (!uploadUrl || !images.length) return;
            event.preventDefault();
            textarea.classList.remove('is-dragover');
            images.forEach(uploadImage);
        });

        form.addEventListener('submit', function () {
            discardDraft();
        });

        setMode(form.getAttribute('data-editor-mode') || 'write');
        initDraft();
    }

    /* -------------------------------------------------------------- tags */

    function initTagChips() {
        var input = doc.querySelector('input[name="tags"]');
        if (!input) return;

        function split(value) {
            return value.split(/[,，]/).map(function (part) {
                return part.trim();
            }).filter(Boolean);
        }

        doc.querySelectorAll('[data-tag]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var tag = (chip.getAttribute('data-tag') || '').trim();
                if (!tag) return;

                var tags = split(input.value);
                var exists = tags.some(function (item) {
                    return item.toLocaleLowerCase() === tag.toLocaleLowerCase();
                });
                if (!exists) tags.push(tag);
                input.value = tags.join(', ');
                input.focus();
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    }

    /* ------------------------------------------------------- publish schedule */

    function initScheduleField() {
        var select = doc.querySelector('[data-status-select]');
        var field = doc.querySelector('[data-schedule-field]');
        if (!select || !field) return;

        var input = field.querySelector('input[name="published_at"]');

        function localDateTime(date) {
            function pad(value) { return String(value).padStart(2, '0'); }
            return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
                + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        function sync() {
            var scheduled = select.value === 'scheduled';
            field.hidden = !scheduled;
            if (scheduled && input && !input.value) {
                input.value = localDateTime(new Date(Date.now() + 60 * 60 * 1000));
            }
        }

        select.addEventListener('change', sync);
        sync();
    }

    /* --------------------------------------------------------------- boot */

    ready(function () {
        initTheme();
        initSidebar();
        initFlashAlerts();
        initConfirms();
        initPanelToggles();
        initCopyButtons();
        initReplyToggles();
        initUploads();
        initSettingsTabs();
        initThemeConfig();
        initEditors();
        initTagChips();
        initScheduleField();
    });
})();