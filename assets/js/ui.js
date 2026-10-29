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

    /** Shared manual navigation with optional, temporary hover expansion. */
    window.UDM.initDesktopNav = function() {
        var sidebar = document.querySelector('.sidebar--collapsible');
        var toggle = document.getElementById('sidebar-collapse-toggle');
        if (!sidebar || !toggle || sidebar._udmDesktopNavBound) return;
        sidebar._udmDesktopNavBound = true;

        var key = 'udmRadarDisplayPreferencesV1';
        var desktop = window.matchMedia('(min-width: 901px)');
        var hover = window.matchMedia('(hover: hover)');
        function readPreferences(value) {
            if (!value) {
                try {
                    value = window.UDMDisplayPreferences
                        ? window.UDMDisplayPreferences.getPreferences()
                        : JSON.parse(window.localStorage.getItem(key));
                } catch (e) { value = null; }
            }
            return {
                automatic: Boolean(value && value.automaticSidebarExpansion === true),
                collapsed: Boolean(value && value.sidebarCollapsed === true)
            };
        }
        var initial = readPreferences();
        var automatic = initial.automatic;
        var manualCollapsed = initial.collapsed;
        var expanded = !manualCollapsed;
        var rendered = null;
        var pointerInside = false;
        var deferredLeave = false;
        var resizeTimer;

        function saveManual() {
            var update = { sidebarCollapsed: manualCollapsed, automaticSidebarExpansion: automatic };
            if (window.UDMDisplayPreferences) {
                window.UDMDisplayPreferences.savePreferences(update);
            } else {
                try {
                    var prefs = JSON.parse(window.localStorage.getItem(key)) || {};
                    if (typeof prefs !== 'object' || Array.isArray(prefs)) prefs = {};
                    prefs.sidebarCollapsed = manualCollapsed;
                    prefs.automaticSidebarExpansion = automatic;
                    window.localStorage.setItem(key, JSON.stringify(prefs));
                } catch (e) {}
            }
        }
        function apply() {
            var next = desktop.matches && expanded;
            document.documentElement.setAttribute('data-sidebar-collapsed', expanded ? 'false' : 'true');
            sidebar.classList.toggle('sidebar--desktop-expanded', next);
            toggle.setAttribute('aria-expanded', String(next));
            toggle.setAttribute('aria-label', next ? 'Collapse navigation' : 'Expand navigation');
            toggle.querySelector('.sidebar-label').textContent = next ? 'Collapse navigation' : 'Expand navigation';
            if (next === rendered) return;
            rendered = next;
            window.clearTimeout(resizeTimer);
            var reduced = document.documentElement.getAttribute('data-motion') === 'reduce'
                || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            resizeTimer = window.setTimeout(function() { window.dispatchEvent(new Event('resize')); }, reduced ? 0 : 210);
        }
        function preferenceChanged(event) {
            var next = readPreferences(event && event.detail && event.detail.preferences);
            var enabled = next.automatic && !automatic;
            var changed = next.automatic !== automatic || next.collapsed !== manualCollapsed;
            automatic = next.automatic;
            manualCollapsed = enabled ? false : next.collapsed;
            if (changed) {
                expanded = !manualCollapsed;
                deferredLeave = false;
                apply();
            }
            if (enabled && next.collapsed) saveManual();
        }
        function resetForViewport() {
            pointerInside = desktop.matches && hover.matches && sidebar.matches(':hover');
            deferredLeave = false;
            expanded = !manualCollapsed || (automatic && pointerInside);
            hideTooltip();
            apply();
        }
        sidebar.addEventListener('pointerenter', function(event) {
            if (event.pointerType === 'touch') return;
            dismissed = null;
            pointerInside = true;
            deferredLeave = false;
            if (automatic && desktop.matches && hover.matches) { expanded = true; apply(); }
        });
        sidebar.addEventListener('pointerleave', function(event) {
            if (event.pointerType === 'touch') return;
            pointerInside = false;
            if (!automatic || !desktop.matches || !hover.matches) return;
            if (sidebar.contains(document.activeElement) && document.activeElement.matches(':focus-visible')) {
                deferredLeave = true;
                return;
            }
            expanded = false;
            apply();
        });
        sidebar.addEventListener('focusout', function() {
            window.setTimeout(function() {
                if (automatic && deferredLeave && !pointerInside && !sidebar.contains(document.activeElement)) {
                    deferredLeave = false;
                    expanded = false;
                    apply();
                }
            }, 0);
        });
        toggle.addEventListener('click', function() {
            expanded = !expanded;
            manualCollapsed = !expanded;
            deferredLeave = false;
            apply();
            saveManual();
        });
        window.addEventListener('udm-radar:display-preferences-changed', preferenceChanged);
        window.addEventListener('storage', function(event) { if (event.key === key) preferenceChanged(); });
        desktop.addEventListener('change', resetForViewport);
        hover.addEventListener('change', resetForViewport);
        window.addEventListener('pageshow', function() {
            var current = readPreferences();
            automatic = current.automatic;
            manualCollapsed = current.collapsed;
            expanded = !manualCollapsed;
            apply();
        });

        // One supplemental tooltip lives outside the rail's scrolling/clipping box.
        var tooltip = document.createElement('div');
        tooltip.id = 'sidebar-navigation-tooltip';
        tooltip.className = 'sidebar-navigation-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.hidden = true;
        document.body.appendChild(tooltip);
        var anchor = null;
        var dismissed = null;
        var lastPointer = null;
        var hideTimer;
        var holdTimer;
        var clickTimer;
        var touch = null;
        var suppressedClick = null;
        var actionSelector = '.sidebar-link, .theme-toggle-btn, .logout-btn';
        function actionFor(target) {
            var action = target && target.closest && target.closest(actionSelector);
            return action && sidebar.contains(action) ? action : null;
        }
        function iconRect(action) {
            var icon = action.querySelector('.theme-icon-wrapper') || action.querySelector('.sidebar-icon');
            return icon && icon.getBoundingClientRect();
        }
        function onIcon(event, action) {
            var rect = iconRect(action);
            return rect && event.clientX >= rect.left && event.clientX <= rect.right
                && event.clientY >= rect.top && event.clientY <= rect.bottom;
        }
        function removeDescription(action) {
            if (!action) return;
            var ids = (action.getAttribute('aria-describedby') || '').split(/\s+/).filter(function(id) { return id && id !== tooltip.id; });
            if (ids.length) action.setAttribute('aria-describedby', ids.join(' '));
            else action.removeAttribute('aria-describedby');
        }
        function positionTooltip() {
            if (!anchor || tooltip.hidden) return;
            var rect = iconRect(anchor);
            if (!rect) return;
            var clip = (anchor.closest('.sidebar-nav') || sidebar).getBoundingClientRect();
            if (rect.bottom <= clip.top || rect.top >= clip.bottom) { hideTooltip(); return; }
            var rail = sidebar.getBoundingClientRect();
            var finalWidth = desktop.matches && expanded
                ? parseFloat(getComputedStyle(sidebar).getPropertyValue('--sidebar-expanded-width')) || 280
                : rail.width;
            var x = rail.left + Math.max(rail.width, finalWidth) + 10;
            var y = rect.top + rect.height / 2 - tooltip.offsetHeight / 2;
            if (x + tooltip.offsetWidth > window.innerWidth - 8) {
                // A narrow drawer uses the non-interactive brand area, leaving links clear.
                var brand = sidebar.querySelector('.sidebar-brand').getBoundingClientRect();
                x = Math.min(rail.left + (rail.width - tooltip.offsetWidth) / 2, window.innerWidth - tooltip.offsetWidth - 8);
                y = brand.bottom - tooltip.offsetHeight - 8;
            }
            y = Math.max(8, Math.min(y, window.innerHeight - tooltip.offsetHeight - 8));
            tooltip.style.left = Math.max(8, x) + 'px';
            tooltip.style.top = y + 'px';
        }
        function hideTooltip() {
            window.clearTimeout(hideTimer);
            hideTimer = null;
            if (!tooltip) return;
            tooltip.hidden = true;
            removeDescription(anchor);
            anchor = null;
        }
        function showTooltip(action) {
            if (!action || dismissed === action) return;
            window.clearTimeout(hideTimer);
            hideTimer = null;
            if (anchor !== action) removeDescription(anchor);
            anchor = action;
            var label = action.querySelector('.sidebar-label');
            tooltip.textContent = label ? label.textContent.trim() : action.getAttribute('aria-label');
            var ids = (action.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf(tooltip.id) === -1) ids.push(tooltip.id);
            action.setAttribute('aria-describedby', ids.join(' '));
            tooltip.hidden = false;
            positionTooltip();
        }
        function queueHide() {
            if (hideTimer) return;
            hideTimer = window.setTimeout(function() {
                hideTimer = null;
                if (anchor && anchor === document.activeElement && anchor.matches(':focus-visible')) return;
                hideTooltip();
            }, 160);
        }
        sidebar.addEventListener('pointermove', function(event) {
            if (event.pointerType === 'touch') {
                if (touch && Math.hypot(event.clientX - touch.x, event.clientY - touch.y) > 10) {
                    window.clearTimeout(holdTimer);
                    if (touch.held) hideTooltip();
                    touch = null;
                }
                return;
            }
            lastPointer = { clientX: event.clientX, clientY: event.clientY };
            var action = actionFor(event.target);
            if (action && onIcon(event, action)) showTooltip(action);
            else {
                dismissed = null;
                if (action === anchor && !tooltip.hidden) {
                    window.clearTimeout(hideTimer);
                    hideTimer = null;
                } else queueHide();
            }
        });
        sidebar.addEventListener('pointerout', function(event) {
            if (event.pointerType === 'touch') return;
            if (!sidebar.contains(event.relatedTarget)) { dismissed = null; queueHide(); }
        });
        sidebar.addEventListener('focusin', function(event) {
            var action = actionFor(event.target);
            if (action && action.matches(':focus-visible')) { dismissed = null; showTooltip(action); }
        });
        sidebar.addEventListener('focusout', function(event) {
            if (anchor === actionFor(event.target)) { dismissed = null; hideTooltip(); }
        });
        tooltip.addEventListener('pointerenter', function() { window.clearTimeout(hideTimer); hideTimer = null; });
        tooltip.addEventListener('pointerleave', queueHide);
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && !tooltip.hidden) { dismissed = anchor; hideTooltip(); }
        });
        document.addEventListener('pointerdown', function() {
            dismissed = null;
            suppressedClick = null;
            window.clearTimeout(clickTimer);
            window.clearTimeout(holdTimer);
            hideTooltip();
        }, true);
        sidebar.addEventListener('pointerdown', function(event) {
            if (event.pointerType !== 'touch') return;
            var action = actionFor(event.target);
            if (!action || !onIcon(event, action)) return;
            touch = { action: action, x: event.clientX, y: event.clientY, held: false };
            holdTimer = window.setTimeout(function() {
                if (!touch) return;
                touch.held = true;
                showTooltip(touch.action);
            }, 450);
        });
        sidebar.addEventListener('pointerup', function() {
            window.clearTimeout(holdTimer);
            if (touch && touch.held) {
                suppressedClick = touch.action;
                clickTimer = window.setTimeout(function() { suppressedClick = null; }, 700);
                hideTimer = window.setTimeout(hideTooltip, 2500);
            }
            touch = null;
        });
        sidebar.addEventListener('pointercancel', function() {
            window.clearTimeout(holdTimer);
            touch = null;
            hideTooltip();
        });
        sidebar.addEventListener('contextmenu', function(event) {
            if (touch && actionFor(event.target) === touch.action) event.preventDefault();
        });
        sidebar.addEventListener('click', function(event) {
            if (suppressedClick && actionFor(event.target) === suppressedClick) {
                event.preventDefault();
                event.stopPropagation();
                suppressedClick = null;
            }
        }, true);
        sidebar.addEventListener('click', function(event) {
            var action = actionFor(event.target);
            if (action && action === anchor && !tooltip.hidden) showTooltip(action);
        });
        window.addEventListener('resize', function() {
            if (!tooltip.matches(':hover')) positionTooltip();
        });
        sidebar.addEventListener('scroll', function() {
            if (!anchor) return;
            if (anchor === document.activeElement && anchor.matches(':focus-visible')) { positionTooltip(); return; }
            var action = lastPointer && actionFor(document.elementFromPoint(lastPointer.clientX, lastPointer.clientY));
            if (action && onIcon(lastPointer, action)) showTooltip(action);
            else hideTooltip();
        }, true);
        window.addEventListener('pagehide', function() {
            window.clearTimeout(resizeTimer);
            window.clearTimeout(holdTimer);
            window.clearTimeout(clickTimer);
            hideTooltip();
        });
        apply();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.UDM.initMobileNav);
        document.addEventListener('DOMContentLoaded', window.UDM.initDesktopNav);
    } else {
        window.UDM.initMobileNav();
        window.UDM.initDesktopNav();
    }
})(window);

