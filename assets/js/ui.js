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

    /**
     * Mobile Navigation Controller (UI-WP-Mobile)
     */
    var lastFocusedNavTrigger = null;

    window.UDM.isMobileNavOpen = function() {
        var sidebar = document.getElementById('sidebar');
        return Boolean(sidebar && sidebar.classList.contains('sidebar--open'));
    };

    window.UDM.openMobileNav = function() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var toggleBtn = document.getElementById('mobile-nav-toggle');
        if (!sidebar) return;

        lastFocusedNavTrigger = toggleBtn || document.activeElement;

        sidebar.classList.add('sidebar--open');
        if (overlay) overlay.classList.add('open');
        if (document.body) document.body.classList.add('body--nav-open');

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.setAttribute('aria-label', 'Close navigation menu');
        }

        // Shift initial focus to active link or first focusable element inside drawer
        var activeLink = sidebar.querySelector('.sidebar-link.active');
        var closeBtn = document.getElementById('sidebar-drawer-close-btn');
        var firstFocusable = activeLink || closeBtn || sidebar.querySelector('a, button');

        if (firstFocusable) {
            try {
                firstFocusable.focus({ preventScroll: true });
            } catch (e) {
                firstFocusable.focus();
            }
        }

        if (window.UDM.announce) {
            window.UDM.announce('Navigation menu opened');
        }
    };

    window.UDM.closeMobileNav = function(options) {
        options = options || {};
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var toggleBtn = document.getElementById('mobile-nav-toggle');
        if (!sidebar) return;

        sidebar.classList.remove('sidebar--open');
        if (overlay) overlay.classList.remove('open');
        if (document.body) document.body.classList.remove('body--nav-open');

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('aria-label', 'Open navigation menu');
        }

        if (options.restoreFocus !== false && lastFocusedNavTrigger) {
            try {
                lastFocusedNavTrigger.focus({ preventScroll: true });
            } catch (e) {
                lastFocusedNavTrigger.focus();
            }
        }
        lastFocusedNavTrigger = null;
    };

    window.UDM.initMobileNav = function() {
        var toggleBtn = document.getElementById('mobile-nav-toggle');
        var overlay = document.getElementById('sidebar-overlay');
        var closeBtn = document.getElementById('sidebar-drawer-close-btn');
        var sidebar = document.getElementById('sidebar');

        if (!toggleBtn && !sidebar) return;

        if (toggleBtn && !toggleBtn._udmNavBound) {
            toggleBtn._udmNavBound = true;
            toggleBtn.addEventListener('click', function(e) {
                if (e) e.preventDefault();
                if (window.UDM.isMobileNavOpen()) {
                    window.UDM.closeMobileNav();
                } else {
                    window.UDM.openMobileNav();
                }
            });
        }

        if (overlay && !overlay._udmNavBound) {
            overlay._udmNavBound = true;
            overlay.addEventListener('click', function() {
                window.UDM.closeMobileNav();
            });
        }

        if (closeBtn && !closeBtn._udmNavBound) {
            closeBtn._udmNavBound = true;
            closeBtn.addEventListener('click', function() {
                window.UDM.closeMobileNav();
            });
        }

        // Close on navigation link activation
        if (sidebar && !sidebar._udmNavLinksBound) {
            sidebar._udmNavLinksBound = true;
            sidebar.addEventListener('click', function(e) {
                var link = e.target.closest('a');
                if (link && window.UDM.isMobileNavOpen()) {
                    window.UDM.closeMobileNav({ restoreFocus: false });
                }
            });
        }

        // Keyboard handler for Escape and Focus containment
        if (!document._udmNavKeyBound) {
            document._udmNavKeyBound = true;
            document.addEventListener('keydown', function(e) {
                if (!window.UDM.isMobileNavOpen()) return;

                if (e.key === 'Escape') {
                    e.preventDefault();
                    window.UDM.closeMobileNav();
                    return;
                }

                if (e.key === 'Tab') {
                    var s = document.getElementById('sidebar');
                    if (!s) return;
                    var focusables = Array.from(s.querySelectorAll('button:not([disabled]), a[href]:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')).filter(function(el) {
                        return el.tabIndex >= 0 && el.getClientRects().length && window.getComputedStyle(el).visibility !== 'hidden';
                    });

                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last = focusables[focusables.length - 1];

                    if (e.shiftKey && (document.activeElement === first || !s.contains(document.activeElement))) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && (document.activeElement === last || !s.contains(document.activeElement))) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        }

        // Resize cleanup: close drawer when crossing into desktop breakpoint
        if (!window._udmNavResizeBound) {
            window._udmNavResizeBound = true;
            window.addEventListener('resize', function() {
                if (window.innerWidth > 900 && window.UDM.isMobileNavOpen()) {
                    window.UDM.closeMobileNav({ restoreFocus: false });
                }
            });
        }
    };

    /** Desktop hover rail; the existing small-screen drawer remains independent. */
    window.UDM.initDesktopNav = function() {
        var sidebar = document.querySelector('.sidebar--collapsible');
        var toggle = document.getElementById('sidebar-collapse-toggle');
        if (!sidebar || !toggle || sidebar._udmDesktopNavBound) return;
        sidebar._udmDesktopNavBound = true;

        var desktop = window.matchMedia('(min-width: 901px)');
        var hover = window.matchMedia('(hover: hover)');
        var pointerInside = false;
        var keyboardInside = false;
        var keyboardInput = false;
        var manualOpen = false;
        var dismissed = false;
        var expanded = false;
        var resizeTimer;

        function apply() {
            var next = desktop.matches && !dismissed && (pointerInside || keyboardInside || manualOpen);
            if (next === expanded) return;
            expanded = next;
            sidebar.classList.toggle('sidebar--desktop-expanded', expanded);
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', expanded ? 'Collapse navigation' : 'Expand navigation');
            toggle.querySelector('.sidebar-label').textContent = expanded ? 'Collapse navigation' : 'Expand navigation';
            window.clearTimeout(resizeTimer);
            var reduced = document.documentElement.getAttribute('data-motion') === 'reduce'
                || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            resizeTimer = window.setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
            }, reduced ? 0 : 210);
        }
        function reset(event) {
            pointerInside = Boolean(event && event.type === 'change' && desktop.matches
                && hover.matches && sidebar.matches(':hover'));
            keyboardInside = manualOpen = dismissed = false;
            apply();
        }
        function close() {
            pointerInside = keyboardInside = manualOpen = false;
            dismissed = true;
            apply();
        }
        document.addEventListener('keydown', function() { keyboardInput = true; });
        document.addEventListener('pointerdown', function(e) {
            keyboardInput = false;
            keyboardInside = false;
            if (!sidebar.contains(e.target)) manualOpen = false;
            apply();
        }, true);
        sidebar.addEventListener('mouseenter', function() {
            if (!desktop.matches || !hover.matches) return;
            pointerInside = true;
            dismissed = false;
            apply();
        });
        sidebar.addEventListener('mouseleave', function() {
            pointerInside = manualOpen = false;
            dismissed = false;
            apply();
        });
        sidebar.addEventListener('focusin', function() {
            if (!desktop.matches || !keyboardInput) return;
            keyboardInside = true;
            dismissed = false;
            apply();
        });
        sidebar.addEventListener('focusout', function() {
            window.setTimeout(function() {
                if (sidebar.contains(document.activeElement)) return;
                keyboardInside = manualOpen = false;
                apply();
            }, 0);
        });
        sidebar.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape' || !desktop.matches || !expanded) return;
            e.preventDefault();
            toggle.focus({ preventScroll: true });
            close();
        });
        toggle.addEventListener('click', function() {
            if (expanded) { close(); return; }
            dismissed = false;
            manualOpen = true;
            apply();
        });
        sidebar.addEventListener('click', function(e) {
            if (desktop.matches && e.target.closest('a[href]') && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) close();
        });
        desktop.addEventListener('change', reset);
        hover.addEventListener('change', reset);
        window.addEventListener('pageshow', reset);
        window.addEventListener('pagehide', function() { window.clearTimeout(resizeTimer); });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.UDM.initMobileNav);
        document.addEventListener('DOMContentLoaded', window.UDM.initDesktopNav);
    } else {
        window.UDM.initMobileNav();
        window.UDM.initDesktopNav();
    }
})(window);

