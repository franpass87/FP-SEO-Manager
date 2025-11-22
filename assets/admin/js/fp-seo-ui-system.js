/**
 * FP SEO Performance - Unified UI System JavaScript
 * 
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

(function($) {
    'use strict';

    /**
     * FP SEO UI System
     */
    window.FPSeoUI = {
        
        /**
         * Initialize the UI system
         */
        init: function() {
            this.initTabs();
            this.initTooltips();
            this.initLoadingStates();
            this.initFormValidation();
            this.initCharacterCounters();
            this.initImageSelectors();
            this.initAjaxHandlers();
            this.initResponsiveHandlers();
        },

        /**
         * Initialize tab functionality
         */
        initTabs: function() {
            $(document).on('click', '.fp-seo-tab', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const tabId = $tab.data('tab');
                const $container = $tab.closest('.fp-seo-tabs').parent();
                
                // Update tab states
                $container.find('.fp-seo-tab').removeClass('fp-seo-tab-active');
                $tab.addClass('fp-seo-tab-active');
                
                // Update content states
                $container.find('.fp-seo-tab-content').removeClass('fp-seo-tab-content-active');
                $container.find('#' + tabId).addClass('fp-seo-tab-content-active');
                
                // Trigger custom event
                $tab.trigger('fp-seo-tab-changed', [tabId]);
            });
        },

        /**
         * Initialize tooltip functionality
         */
        initTooltips: function() {
            $(document).on('mouseenter', '.fp-seo-tooltip-trigger', function() {
                const $trigger = $(this);
                const $tooltip = $trigger.find('.fp-seo-tooltip-content');
                
                if ($tooltip.length) {
                    $tooltip.addClass('fp-seo-fade-in');
                }
            });

            $(document).on('mouseleave', '.fp-seo-tooltip-trigger', function() {
                const $trigger = $(this);
                const $tooltip = $trigger.find('.fp-seo-tooltip-content');
                
                if ($tooltip.length) {
                    $tooltip.removeClass('fp-seo-fade-in');
                }
            });
        },

        /**
         * Initialize loading states
         */
        initLoadingStates: function() {
            $(document).on('click', '.fp-seo-btn[data-loading]', function() {
                const $btn = $(this);
                const originalText = $btn.text();
                const loadingText = $btn.data('loading-text') || 'Loading...';
                
                // XSS safe: create spinner separately, then append text
                const $spinner = $('<span class="fp-seo-loading"></span>');
                $btn.prop('disabled', true)
                    .data('original-text', originalText)
                    .empty()
                    .append($spinner)
                    .append(' ' + $('<span>').text(loadingText));
            });
        },

        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            $(document).on('blur', '.fp-seo-form-control[required]', function() {
                const $field = $(this);
                const value = $field.val().trim();
                const $group = $field.closest('.fp-seo-form-group');
                
                if (value === '') {
                    $group.addClass('fp-seo-form-group-error');
                    $field.addClass('fp-seo-form-control-error');
                } else {
                    $group.removeClass('fp-seo-form-group-error');
                    $field.removeClass('fp-seo-form-control-error');
                }
            });

            $(document).on('input', '.fp-seo-form-control[required]', function() {
                const $field = $(this);
                const $group = $field.closest('.fp-seo-form-group');
                
                if ($field.val().trim() !== '') {
                    $group.removeClass('fp-seo-form-group-error');
                    $field.removeClass('fp-seo-form-control-error');
                }
            });
        },

        /**
         * Initialize character counters
         */
        initCharacterCounters: function() {
            $(document).on('input', '.fp-seo-character-counter', function() {
                const $field = $(this);
                const $counter = $field.siblings('.fp-seo-character-count').find('span');
                const maxLength = parseInt($field.attr('maxlength')) || 0;
                const currentLength = $field.val().length;
                
                if ($counter.length) {
                    $counter.text(currentLength);
                    
                    // Update counter color based on usage
                    const percentage = (currentLength / maxLength) * 100;
                    if (percentage > 90) {
                        $counter.addClass('fp-seo-text-danger');
                    } else if (percentage > 75) {
                        $counter.addClass('fp-seo-text-warning');
                    } else {
                        $counter.removeClass('fp-seo-text-danger fp-seo-text-warning');
                    }
                }
            });

            // Initialize existing counters
            $('.fp-seo-character-counter').each(function() {
                $(this).trigger('input');
            });
        },

        /**
         * Initialize image selectors
         */
        initImageSelectors: function() {
            $(document).on('click', '.fp-seo-image-select', function(e) {
                e.preventDefault();
                
                const $button = $(this);
                const $input = $button.siblings('input[type="url"]');
                const $preview = $button.data('preview-target');
                
                if (typeof wp !== 'undefined' && wp.media) {
                    const frame = wp.media({
                        title: 'Select Image',
                        button: {
                            text: 'Use Image'
                        },
                        multiple: false
                    });
                    
                    frame.on('select', function() {
                        const attachment = frame.state().get('selection').first().toJSON();
                        $input.val(attachment.url);
                        
                        if ($preview) {
                            $(`#${$preview}`).attr('src', attachment.url);
                        }
                    });
                    
                    frame.open();
                }
            });
        },

        /**
         * Initialize AJAX handlers
         */
        initAjaxHandlers: function() {
            // Specific AJAX error handler - only for FP SEO plugin requests
            $(document).ajaxError(function(event, xhr, settings, thrownError) {
                // Skip if it's a WordPress core request (heartbeat, autosave, etc.)
                if (settings.data) {
                    const dataStr = typeof settings.data === 'string' ? settings.data : '';
                    if (dataStr.indexOf('action=heartbeat') !== -1 ||
                        dataStr.indexOf('action=autosave') !== -1 ||
                        dataStr.indexOf('action=wp-remove-post-lock') !== -1) {
                        return; // Skip WordPress core requests
                    }
                }
                
                // Only handle errors for FP SEO plugin AJAX requests
                let isFpSeoRequest = false;
                
                if (settings.url && settings.url.indexOf('admin-ajax.php') !== -1) {
                    // Check if it's a FormData request - we need to check the URL pattern
                    if (settings.data instanceof FormData) {
                        // For FormData, we can't inspect directly, but we check if URL contains admin-ajax.php
                        // and rely on the action being set correctly in the request
                        // We'll be more conservative and only show errors for confirmed FP SEO actions
                        return; // Skip FormData requests to avoid false positives
                    } else if (settings.data) {
                        const dataStr = typeof settings.data === 'string' ? settings.data : '';
                        isFpSeoRequest = dataStr.indexOf('action=fp_seo_performance') !== -1 ||
                                       dataStr.indexOf('action=fp-seo') !== -1;
                    }
                }
                
                // Only show notification for confirmed FP SEO requests with real errors
                if (isFpSeoRequest && (xhr.status >= 500 || (xhr.status === 0 && thrownError))) {
                    FPSeoUI.showNotification('An error occurred. Please try again.', 'error');
                }
            });

            // Specific AJAX success handler - only for FP SEO plugin requests
            $(document).ajaxSuccess(function(event, xhr, settings) {
                // Only handle success for confirmed FP SEO plugin AJAX requests
                if (settings.url && settings.url.indexOf('admin-ajax.php') !== -1 && settings.data) {
                    const dataStr = typeof settings.data === 'string' ? settings.data : '';
                    const isFpSeoRequest = dataStr.indexOf('action=fp_seo_performance') !== -1 ||
                                         dataStr.indexOf('action=fp-seo') !== -1;
                    
                    if (isFpSeoRequest && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        FPSeoUI.showNotification(xhr.responseJSON.data.message, 'success');
                    }
                }
            });
        },

        /**
         * Initialize responsive handlers
         */
        initResponsiveHandlers: function() {
            let resizeTimer;
            
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    FPSeoUI.handleResponsiveChanges();
                }, 250);
            });
            
            // Initial call
            this.handleResponsiveChanges();
        },

        /**
         * Handle responsive changes
         */
        handleResponsiveChanges: function() {
            const windowWidth = $(window).width();
            
            if (windowWidth < 768) {
                $('.fp-seo-tabs').addClass('fp-seo-tabs-mobile');
                $('.fp-seo-grid').addClass('fp-seo-grid-mobile');
            } else {
                $('.fp-seo-tabs').removeClass('fp-seo-tabs-mobile');
                $('.fp-seo-grid').removeClass('fp-seo-grid-mobile');
            }
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info', duration = 5000) {
            const $notification = $(`
                <div class="fp-seo-notification fp-seo-notification-${type} fp-seo-fade-in">
                    <div class="fp-seo-notification-content">
                        <span class="fp-seo-notification-message">${message}</span>
                        <button class="fp-seo-notification-close" type="button">&times;</button>
                    </div>
                </div>
            `);
            
            // Add to body if container doesn't exist
            let $container = $('.fp-seo-notifications');
            if ($container.length === 0) {
                $container = $('<div class="fp-seo-notifications"></div>').appendTo('body');
            }
            
            $container.append($notification);
            
            // Auto remove after duration
            setTimeout(function() {
                $notification.removeClass('fp-seo-fade-in').addClass('fp-seo-fade-out');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, duration);
            
            // Manual close
            $notification.find('.fp-seo-notification-close').on('click', function() {
                $notification.removeClass('fp-seo-fade-in').addClass('fp-seo-fade-out');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            });
        },

        /**
         * Show loading overlay
         */
        showLoading: function($element, text = 'Loading...') {
            const $overlay = $(`
                <div class="fp-seo-loading-overlay">
                    <div class="fp-seo-loading-content">
                        <div class="fp-seo-loading fp-seo-loading-lg"></div>
                        <div class="fp-seo-loading-text">${text}</div>
                    </div>
                </div>
            `);
            
            $element.css('position', 'relative').append($overlay);
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function($element) {
            $element.find('.fp-seo-loading-overlay').remove();
        },

        /**
         * Update progress bar
         */
        updateProgress: function($progressBar, percentage) {
            $progressBar.find('.fp-seo-progress-bar').css('width', percentage + '%');
        },

        /**
         * Animate element
         */
        animate: function($element, animation, callback) {
            $element.addClass(animation);
            
            setTimeout(function() {
                $element.removeClass(animation);
                if (callback) callback();
            }, 300);
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle function
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        FPSeoUI.init();
    });

    /**
     * Expose to global scope
     */
    window.FPSeoUI = FPSeoUI;

})(jQuery);
