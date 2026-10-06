/**
 * UDM-RADAR — Shared UI Interaction Helpers
 * UI-WP1A: Critical Display and Interaction Foundation
 */
(function(window) {
    'use strict';

    window.UDM = window.UDM || {};

    /**
     * Scroll an in-page destination into view safely and manage focus.
     * Respects user's prefers-reduced-motion setting.
     * Works with both window and .main-content scroll containers.
     *
     * @param {string|HTMLElement} target - Element or selector/id to scroll to
     * @param {Object} [options]
     * @param {string|HTMLElement} [options.focusTarget] - Element to focus after scroll (defaults to target)
     * @param {boolean} [options.focus=true] - Whether to shift focus to the target
     * @param {ScrollLogicalPosition} [options.block='start'] - Scroll alignment
     * @param {ScrollBehavior} [options.behavior='smooth'] - Requested scroll behavior
     */
    window.UDM.scrollToTarget = function(target, options) {
        options = options || {};
        var el = typeof target === 'string'
            ? (document.getElementById(target) || document.querySelector(target))
            : target;

        if (!el) return;

        var prefersReduced = false;
        try {
            prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (e) {
            prefersReduced = false;
        }

        var behavior = prefersReduced ? 'auto' : (options.behavior || 'smooth');

        el.scrollIntoView({
            behavior: behavior,
            block: options.block || 'start',
            inline: options.inline || 'nearest'
        });

        if (options.focus !== false) {
            var focusEl = options.focusTarget
                ? (typeof options.focusTarget === 'string'
                    ? (document.getElementById(options.focusTarget) || document.querySelector(options.focusTarget))
                    : options.focusTarget)
                : el;

            if (focusEl) {
                var isInteractive = /^(BUTTON|A|INPUT|SELECT|TEXTAREA)$/.test(focusEl.tagName) || focusEl.hasAttribute('tabindex');
                if (!isInteractive) {
                    focusEl.setAttribute('tabindex', '-1');
                }
                try {
                    focusEl.focus({ preventScroll: true });
                } catch (err) {
                    focusEl.focus();
                }
            }
        }
    };

    /**
     * Announce a message to assistive technology using an accessible live region.
     * Respects priority ('polite' or 'assertive').
     *
     * @param {string} message - Message text to announce
     * @param {'polite'|'assertive'} [priority='polite'] - Priority level
     */
    window.UDM.announce = function(message, priority) {
        if (!message) return;
        var p = priority === 'assertive' ? 'assertive' : 'polite';
        var regionId = 'udm-live-region-' + p;
        var region = document.getElementById(regionId);
        if (!region) {
            region = document.createElement('div');
            region.id = regionId;
            region.className = 'sr-only';
            region.setAttribute('role', p === 'assertive' ? 'alert' : 'status');
            region.setAttribute('aria-live', p);
            region.setAttribute('aria-atomic', 'true');
            if (document.body) {
                document.body.appendChild(region);
            }
        }
        if (region) {
            region.textContent = '';
            window.setTimeout(function() {
                region.textContent = message;
            }, 50);
        }
    };
})(window);

