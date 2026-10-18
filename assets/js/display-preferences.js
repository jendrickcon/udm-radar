/**
 * assets/js/display-preferences.js
 * UDM-RADAR Display & Accessibility Preferences Manager (WP1)
 *
 * Implements:
 * 1. Schema versioning and strict allowlist parsing
 *    - colorStyle: 'ccs' | 'classic' (default: 'ccs')
 *    - theme: 'system' | 'light' | 'dark' (default: 'system')
 *    - textSize: 'small' | 'default' | 'large' | 'extra-large' (default: 'default')
 *    - motion: 'system' | 'reduce' | 'allow' (default: 'system')
 *    - underlineLinks: boolean (default: false)
 * 2. Legacy 'theme' key migration to udmRadarDisplayPreferencesV1
 * 3. System theme and motion resolution via matchMedia
 * 4. DOM attribute updates (data-color-style, data-theme, data-font-size, data-motion, data-underline-links)
 * 5. Device-theme change event listening when theme is 'system'
 * 6. Custom event dispatching ('udm-radar:display-preferences-changed')
 * 7. Expandable settings disclosure (accordion) toggling with keyboard & aria support
 * 8. Settings form two-way binding, reset action, and screen-reader status announcements
 * 9. Chart.js theme and motion synchronization helper
 */

(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.UDMDisplayPreferences = factory();
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const STORAGE_KEY = 'udmRadarDisplayPreferencesV1';

  const ALLOWED_COLOR_STYLES = ['ccs', 'classic'];
  const ALLOWED_THEMES = ['system', 'light', 'dark'];
  const ALLOWED_TEXT_SIZES = ['small', 'default', 'large', 'extra-large'];
  const ALLOWED_MOTIONS = ['system', 'reduce', 'allow'];

  const DEFAULT_PREFERENCES = Object.freeze({
    version: 1,
    colorStyle: 'ccs',
    theme: 'system',
    textSize: 'default',
    motion: 'system',
    underlineLinks: false
  });

  let deviceThemeListener = null;
  let deviceMotionListener = null;

  /**
   * Safe localStorage wrapper with try/catch fallback
   */
  function safeStorageGet(key) {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        return window.localStorage.getItem(key);
      }
    } catch (e) {
      // Storage unavailable or disabled
    }
    return null;
  }

  function safeStorageSet(key, value) {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.setItem(key, value);
        return true;
      }
    } catch (e) {
      // Storage quota exceeded or disabled
    }
    return false;
  }

  /**
   * Validates and parses a raw preference object against allowlists
   */
  function validatePreferences(raw) {
    if (!raw || typeof raw !== 'object') {
      return { ...DEFAULT_PREFERENCES };
    }

    const colorStyle = ALLOWED_COLOR_STYLES.includes(raw.colorStyle) ? raw.colorStyle : DEFAULT_PREFERENCES.colorStyle;
    const theme = ALLOWED_THEMES.includes(raw.theme) ? raw.theme : DEFAULT_PREFERENCES.theme;
    const textSize = ALLOWED_TEXT_SIZES.includes(raw.textSize) ? raw.textSize : DEFAULT_PREFERENCES.textSize;
    const motion = ALLOWED_MOTIONS.includes(raw.motion) ? raw.motion : DEFAULT_PREFERENCES.motion;
    const underlineLinks = typeof raw.underlineLinks === 'boolean' ? raw.underlineLinks : DEFAULT_PREFERENCES.underlineLinks;

    return {
      version: 1,
      colorStyle,
      theme,
      textSize,
      motion,
      underlineLinks
    };
  }

  /**
   * Migrates legacy 'theme' localStorage key if present
   */
  function migrateLegacyTheme() {
    const legacy = safeStorageGet('theme');
    if (legacy === 'light' || legacy === 'dark') {
      return legacy;
    }
    return null;
  }

  /**
   * Retrieves current preferences, validating against storage
   */
  function getPreferences() {
    const stored = safeStorageGet(STORAGE_KEY);
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        return validatePreferences(parsed);
      } catch (e) {
        // Malformed JSON fallback
      }
    }

    // Attempt migration from legacy key
    const legacyTheme = migrateLegacyTheme();
    if (legacyTheme) {
      const migrated = {
        ...DEFAULT_PREFERENCES,
        theme: legacyTheme
      };
      safeStorageSet(STORAGE_KEY, JSON.stringify(migrated));
      return migrated;
    }

    return { ...DEFAULT_PREFERENCES };
  }

  /**
   * Resolves system color scheme using matchMedia
   */
  function resolveActiveTheme(themePref) {
    if (themePref === 'system') {
      if (typeof window !== 'undefined' && window.matchMedia) {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
      }
      return 'light';
    }
    return themePref;
  }

  /**
   * Resolves system motion preference using matchMedia
   */
  function resolveActiveMotion(motionPref) {
    if (motionPref === 'system') {
      if (typeof window !== 'undefined' && window.matchMedia) {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'reduce' : 'allow';
      }
      return 'allow';
    }
    return motionPref;
  }

  /**
   * Applies preferences to root documentElement and manages media query listeners
   */
  function applyPreferences(prefs) {
    const validPrefs = validatePreferences(prefs);
    const activeTheme = resolveActiveTheme(validPrefs.theme);
    const activeMotion = resolveActiveMotion(validPrefs.motion);

    if (typeof document !== 'undefined' && document.documentElement) {
      const root = document.documentElement;

      // Apply root attributes
      root.setAttribute('data-color-style', validPrefs.colorStyle);
      root.setAttribute('data-theme', activeTheme);
      root.setAttribute('data-font-size', validPrefs.textSize);
      root.setAttribute('data-motion', activeMotion);
      root.setAttribute('data-underline-links', validPrefs.underlineLinks ? 'true' : 'false');
      root.removeAttribute('data-contrast'); // Contrast mode removed in revised WP1

      // Sync compatibility cookie (strictly allowlisted active theme)
      try {
        document.cookie = 'theme=' + activeTheme + ';path=/;max-age=31536000;SameSite=Lax';
      } catch (e) {}

      // Sync legacy localStorage theme for backward compatibility
      safeStorageSet('theme', activeTheme);
    }

    // Attach or detach OS theme listener
    setupDeviceListeners(validPrefs.theme, validPrefs.motion);

    // Dispatch custom event for charts and page observers
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      try {
        window.dispatchEvent(new CustomEvent('udm-radar:display-preferences-changed', {
          detail: {
            preferences: validPrefs,
            activeTheme,
            activeMotion
          }
        }));
      } catch (e) {}
    }

    return {
      preferences: validPrefs,
      activeTheme,
      activeMotion
    };
  }

  /**
   * Manages media query listeners for OS preference shifts
   */
  function setupDeviceListeners(themePref, motionPref) {
    if (typeof window === 'undefined' || !window.matchMedia) return;

    // Theme listener
    if (themePref === 'system') {
      if (!deviceThemeListener) {
        const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
        deviceThemeListener = (e) => {
          const currentPrefs = getPreferences();
          if (currentPrefs.theme === 'system') {
            applyPreferences(currentPrefs);
          }
        };
        if (themeMedia.addEventListener) {
          themeMedia.addEventListener('change', deviceThemeListener);
        } else if (themeMedia.addListener) {
          themeMedia.addListener(deviceThemeListener);
        }
      }
    } else if (deviceThemeListener) {
      const themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
      if (themeMedia.removeEventListener) {
        themeMedia.removeEventListener('change', deviceThemeListener);
      } else if (themeMedia.removeListener) {
        themeMedia.removeListener(deviceThemeListener);
      }
      deviceThemeListener = null;
    }

    // Motion listener
    if (motionPref === 'system') {
      if (!deviceMotionListener) {
        const motionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
        deviceMotionListener = (e) => {
          const currentPrefs = getPreferences();
          if (currentPrefs.motion === 'system') {
            applyPreferences(currentPrefs);
          }
        };
        if (motionMedia.addEventListener) {
          motionMedia.addEventListener('change', deviceMotionListener);
        } else if (motionMedia.addListener) {
          motionMedia.addListener(deviceMotionListener);
        }
      }
    } else if (deviceMotionListener) {
      const motionMedia = window.matchMedia('(prefers-reduced-motion: reduce)');
      if (motionMedia.removeEventListener) {
        motionMedia.removeEventListener('change', deviceMotionListener);
      } else if (motionMedia.removeListener) {
        motionMedia.removeListener(deviceMotionListener);
      }
      deviceMotionListener = null;
    }
  }

  /**
   * Saves preferences and applies them immediately
   */
  function savePreferences(partial) {
    const current = getPreferences();
    const updated = validatePreferences({
      ...current,
      ...partial
    });

    safeStorageSet(STORAGE_KEY, JSON.stringify(updated));
    return applyPreferences(updated);
  }

  /**
   * Resets preferences to defaults
   */
  function resetPreferences() {
    safeStorageSet(STORAGE_KEY, JSON.stringify(DEFAULT_PREFERENCES));
    return applyPreferences(DEFAULT_PREFERENCES);
  }

  /**
   * Announces status message to screen readers via aria-live
   */
  function announceStatus(message) {
    if (typeof document === 'undefined') return;
    const statusEl = document.getElementById('display-pref-status');
    const bannerEl = document.getElementById('display-pref-banner');

    if (statusEl) {
      statusEl.textContent = '';
      setTimeout(() => {
        statusEl.textContent = message;
      }, 50);
    }

    if (bannerEl) {
      bannerEl.textContent = message;
      bannerEl.style.display = 'block';
      setTimeout(() => {
        bannerEl.style.display = 'none';
      }, 3500);
    }
  }

  /**
   * Updates current-state summary card indicators
   */
  function updateSummaryCard(prefs) {
    if (typeof document === 'undefined') return;
    const colorEl = document.getElementById('summary-color-val');
    const themeEl = document.getElementById('summary-theme-val');
    const textEl = document.getElementById('summary-text-val');
    const motionEl = document.getElementById('summary-motion-val');

    if (colorEl) {
      colorEl.textContent = prefs.colorStyle === 'classic' ? 'Classic UDM-RADAR' : 'CCS Colors';
    }
    if (themeEl) {
      if (prefs.theme === 'light') themeEl.textContent = 'Light';
      else if (prefs.theme === 'dark') themeEl.textContent = 'Dark';
      else themeEl.textContent = 'Device setting';
    }
    if (textEl) {
      if (prefs.textSize === 'small') textEl.textContent = 'Small (90%)';
      else if (prefs.textSize === 'large') textEl.textContent = 'Large (112.5%)';
      else if (prefs.textSize === 'extra-large') textEl.textContent = 'Extra Large (125%)';
      else textEl.textContent = 'Default (100%)';
    }
    if (motionEl) {
      if (prefs.motion === 'reduce') motionEl.textContent = 'Reduce motion';
      else if (prefs.motion === 'allow') motionEl.textContent = 'Allow motion';
      else motionEl.textContent = 'Device setting';
    }
  }

  /**
   * Binds display preferences form controls and disclosure accordion if present
   */
  function bindFormControls() {
    if (typeof document === 'undefined') return;

    // 1. Disclosure Accordion Trigger & Launcher Button
    const triggerBtn = document.getElementById('displayPrefToggleBtn');
    const panel = document.getElementById('displayPreferencesPanel');
    const toggleText = document.getElementById('displayPrefToggleText');

    if (triggerBtn && panel) {
      triggerBtn.addEventListener('click', () => {
        const isExpanded = triggerBtn.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !isExpanded;
        triggerBtn.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
        if (toggleText) {
          toggleText.textContent = nextExpanded ? 'Close preferences' : 'Open preferences';
        }
        if (nextExpanded) {
          panel.removeAttribute('hidden');
          panel.style.display = 'block';
        } else {
          panel.setAttribute('hidden', '');
          panel.style.display = 'none';
        }
      });

      // Keyboard: Escape closes panel and returns focus to launcher
      panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          triggerBtn.setAttribute('aria-expanded', 'false');
          if (toggleText) toggleText.textContent = 'Open preferences';
          panel.setAttribute('hidden', '');
          panel.style.display = 'none';
          triggerBtn.focus();
        }
      });
    }

    // 2. Settings Form Controls
    const form = document.getElementById('displayPreferencesForm');
    const current = getPreferences();

    // Initialize summary card with active preferences
    updateSummaryCard(current);

    if (!form) return;

    // Set radio buttons
    const setRadio = (name, val) => {
      const el = form.querySelector(`input[name="${name}"][value="${val}"]`);
      if (el) el.checked = true;
    };

    setRadio('display_color_style', current.colorStyle);
    setRadio('display_theme', current.theme);
    setRadio('display_text_size', current.textSize);
    setRadio('display_motion', current.motion);

    // Set checkbox
    const underlineEl = form.querySelector('input[name="display_underline_links"]');
    if (underlineEl) {
      underlineEl.checked = !!current.underlineLinks;
    }

    // Event listeners
    form.addEventListener('change', (e) => {
      const target = e.target;
      if (!target) return;

      const newPrefs = {
        colorStyle: form.querySelector('input[name="display_color_style"]:checked')?.value || current.colorStyle,
        theme: form.querySelector('input[name="display_theme"]:checked')?.value || current.theme,
        textSize: form.querySelector('input[name="display_text_size"]:checked')?.value || current.textSize,
        motion: form.querySelector('input[name="display_motion"]:checked')?.value || current.motion,
        underlineLinks: !!form.querySelector('input[name="display_underline_links"]')?.checked
      };

      savePreferences(newPrefs);
      updateSummaryCard(newPrefs);
      announceStatus('Display preferences saved.');
    });

    // Reset button (Section 8, 9, 10)
    const resetBtn = document.getElementById('resetDisplayPreferencesBtn');
    const resetMsg = document.getElementById('display-pref-reset-msg');

    if (resetBtn) {
      resetBtn.addEventListener('click', (e) => {
        e.preventDefault();
        resetPreferences();

        // Refresh form UI
        setRadio('display_color_style', DEFAULT_PREFERENCES.colorStyle);
        setRadio('display_theme', DEFAULT_PREFERENCES.theme);
        setRadio('display_text_size', DEFAULT_PREFERENCES.textSize);
        setRadio('display_motion', DEFAULT_PREFERENCES.motion);
        if (underlineEl) underlineEl.checked = DEFAULT_PREFERENCES.underlineLinks;

        // Refresh summary card badges
        updateSummaryCard(DEFAULT_PREFERENCES);

        // Confirmation status (Section 10)
        announceStatus('Display preferences reset to defaults.');
        if (resetMsg) {
          resetMsg.textContent = 'Display preferences reset to defaults.';
          resetMsg.classList.add('is-visible');
          setTimeout(() => {
            resetMsg.classList.remove('is-visible');
          }, 3500);
        }
      });
    }
  }

  /**
   * Synchronizes Chart.js instances on preference change
   */
  function bindChartListener() {
    if (typeof window === 'undefined') return;

    window.addEventListener('udm-radar:display-preferences-changed', (event) => {
      const detail = event.detail || {};
      const isMotionReduced = detail.activeMotion === 'reduce';

      // If Chart.js is present and charts are registered
      if (typeof window.Chart !== 'undefined' && window.Chart.instances) {
        try {
          Object.values(window.Chart.instances).forEach((chart) => {
            if (chart && chart.options) {
              if (isMotionReduced) {
                chart.options.animation = false;
              }
              if (typeof chart.update === 'function') {
                chart.update('none');
              }
            }
          });
        } catch (e) {
          // Graceful fallback for non-standard chart registries
        }
      }
    });
  }

  /**
   * Initialize module on DOM ready
   */
  function init() {
    const prefs = getPreferences();
    applyPreferences(prefs);

    if (typeof document !== 'undefined') {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
          bindFormControls();
          bindChartListener();
        });
      } else {
        bindFormControls();
        bindChartListener();
      }
    }
  }

  // Auto-initialize in browser
  if (typeof window !== 'undefined') {
    init();
  }

  return {
    STORAGE_KEY,
    DEFAULT_PREFERENCES,
    ALLOWED_COLOR_STYLES,
    ALLOWED_THEMES,
    ALLOWED_TEXT_SIZES,
    ALLOWED_MOTIONS,
    validatePreferences,
    getPreferences,
    savePreferences,
    resetPreferences,
    applyPreferences,
    resolveActiveTheme,
    resolveActiveMotion,
    init
  };
});
