(function (window, document, $) {
    'use strict';

    if (!window || !document) {
        return;
    }

    var config = window.fpSeoPerformanceMetabox || null;

    if (!config) {
        return;
    }

    var container = document.querySelector('[data-fp-seo-metabox]');

    if (!container) {
        return;
    }

    var scoreWrapper = container.querySelector('[data-fp-seo-score]');
    var scoreValue = container.querySelector('[data-fp-seo-score-value]');
    var indicatorList = container.querySelector('[data-fp-seo-indicators]');
    var recommendationList = container.querySelector('[data-fp-seo-recommendations]');
    var messageEl = container.querySelector('[data-fp-seo-message]');
    var excludeToggle = container.querySelector('[data-fp-seo-exclude]');

    var labels = config.labels || {};
    var legend = config.legend || {};
    var ajaxUrl = config.ajaxUrl || window.ajaxurl || '';

    var state = {
        enabled: !!config.enabled,
        excluded: !!config.excluded,
        lastPayload: null,
        timer: null,
        busy: false
    };

    function setMessage(text) {
        if (!messageEl) {
            return;
        }

        messageEl.textContent = text || '';
        messageEl.style.display = text ? 'block' : 'none';
    }

    function clearList(list) {
        if (!list) {
            return;
        }

        while (list.firstChild) {
            list.removeChild(list.firstChild);
        }
    }

    function renderIndicators(checks) {
        clearList(indicatorList);

        if (!indicatorList || !checks || !checks.length) {
            return;
        }

        checks.forEach(function (check) {
            var status = check.status || 'pending';
            var label = check.label || '';
            var hint = check.hint || '';
            var statusLabel = legend[status] || status;
            var item = document.createElement('li');
            item.className = 'fp-seo-performance-indicator fp-seo-performance-indicator--' + status;
            item.setAttribute('role', 'listitem');
            item.setAttribute('tabindex', '0');
            item.setAttribute('aria-label', statusLabel + ': ' + label);

            if (hint) {
                item.title = hint;
            }

            var statusBadge = document.createElement('span');
            statusBadge.className = 'fp-seo-performance-indicator__status';
            statusBadge.textContent = statusLabel;
            item.appendChild(statusBadge);

            var text = document.createElement('span');
            text.className = 'fp-seo-performance-indicator__label';
            text.textContent = label;
            item.appendChild(text);

            indicatorList.appendChild(item);
        });
    }

    function renderRecommendations(items) {
        clearList(recommendationList);

        if (!recommendationList || !items) {
            return;
        }

        if (!items.length) {
            return;
        }

        items.forEach(function (itemText) {
            var item = document.createElement('li');
            item.textContent = itemText;
            recommendationList.appendChild(item);
        });
    }

    function updateScore(data) {
        if (!scoreWrapper || !scoreValue) {
            return;
        }

        var score = data && data.score && typeof data.score.score === 'number' ? data.score.score : 0;
        var status = data && data.score && data.score.status ? data.score.status : 'pending';

        scoreValue.textContent = String(score);
        scoreWrapper.setAttribute('data-status', status);
    }

    function applyAnalysis(result) {
        if (!result) {
            return;
        }

        updateScore(result);
        renderIndicators(result.checks || []);
        renderRecommendations(result.score && result.score.recommendations ? result.score.recommendations : []);

        if (state.excluded) {
            setMessage(labels.excluded || '');
            return;
        }

        if (!state.enabled) {
            setMessage(labels.disabled || '');
            return;
        }

        var recommendations = result.score && result.score.recommendations ? result.score.recommendations.length : 0;
        var status = result.score && result.score.status ? result.score.status : 'pending';

        if (recommendations === 0 && status === 'green') {
            setMessage(labels.none || '');
        } else {
            setMessage('');
        }
    }

    function handleError(message) {
        setMessage(message || labels.error || '');
        state.busy = false;
    }

    function gatherPayload() {
        var payload = {
            title: '',
            content: '',
            excerpt: '',
            metaDescription: '',
            canonical: '',
            robots: ''
        };

        if (window.wp && window.wp.data && typeof window.wp.data.select === 'function') {
            var select = window.wp.data.select('core/editor');

            if (select) {
                payload.title = select.getEditedPostAttribute('title') || '';
                payload.content = select.getEditedPostAttribute('content') || '';
                payload.excerpt = select.getEditedPostAttribute('excerpt') || '';
                payload.metaDescription = (select.getEditedPostAttribute('meta') || {}).fp_seo_meta_description || '';
                payload.canonical = (select.getEditedPostAttribute('meta') || {}).fp_seo_meta_canonical || '';
                payload.robots = (select.getEditedPostAttribute('meta') || {}).fp_seo_meta_robots || '';
            }
        } else {
            var titleField = document.getElementById('title');
            var contentField = document.getElementById('content');
            var excerptField = document.getElementById('excerpt');

            if (titleField) {
                payload.title = titleField.value || '';
            }

            if (contentField) {
                payload.content = contentField.value || '';
            }

            if (excerptField) {
                payload.excerpt = excerptField.value || '';
            }
        }

        if (!payload.metaDescription && payload.excerpt) {
            payload.metaDescription = payload.excerpt;
        }

        return payload;
    }

    function sendAnalysis(force) {
        if (!state.enabled || state.excluded || !ajaxUrl) {
            return;
        }

        var payload = gatherPayload();
        var serialized = JSON.stringify(payload);

        if (!force && serialized === state.lastPayload) {
            return;
        }

        state.lastPayload = serialized;
        state.busy = true;
        state.timer = null;
        setMessage(labels.loading || '');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: $.extend({}, payload, {
                action: 'fp_seo_performance_analyze',
                nonce: config.nonce,
                postId: config.postId
            })
        })
            .done(function (response) {
                state.busy = false;

                if (!response || !response.success) {
                    handleError(response && response.data && response.data.message ? response.data.message : null);
                    return;
                }

                if (response.data && response.data.excluded) {
                    state.excluded = true;
                    setMessage(labels.excluded || '');
                    return;
                }

                applyAnalysis(response.data || {});
            })
            .fail(function () {
                handleError();
            });
    }

    function scheduleAnalysis() {
        if (!state.enabled || state.excluded) {
            return;
        }

        if (state.timer) {
            window.clearTimeout(state.timer);
        }

        state.timer = window.setTimeout(function () {
            sendAnalysis(false);
        }, 700);
    }

    function bindClassicEditor() {
        var fields = ['title', 'content', 'excerpt'];

        fields.forEach(function (id) {
            var field = document.getElementById(id);

            if (!field) {
                return;
            }

            field.addEventListener('input', scheduleAnalysis);
            field.addEventListener('change', scheduleAnalysis);
        });
    }

    function bindBlockEditor() {
        if (!window.wp || !window.wp.data || typeof window.wp.data.subscribe !== 'function') {
            return;
        }

        var select = window.wp.data.select('core/editor');

        if (!select) {
            return;
        }

        window.wp.data.subscribe(function () {
            if (!state.enabled || state.excluded) {
                return;
            }

            scheduleAnalysis();
        });
    }

    function bindExcludeToggle() {
        if (!excludeToggle) {
            return;
        }

        excludeToggle.addEventListener('change', function () {
            state.excluded = excludeToggle.checked;

            if (state.excluded) {
                setMessage(labels.excluded || '');
                clearList(indicatorList);
                clearList(recommendationList);
            } else {
                sendAnalysis(true);
            }
        });
    }

    if (!state.enabled) {
        setMessage(labels.disabled || '');
    } else if (state.excluded) {
        setMessage(labels.excluded || '');
    } else if (config.initial) {
        applyAnalysis(config.initial);
    } else {
        sendAnalysis(true);
    }

    bindClassicEditor();
    bindBlockEditor();
    bindExcludeToggle();
})(window, window.document, window.jQuery);
