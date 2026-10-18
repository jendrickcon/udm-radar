<?php
/**
 * includes/display_preferences_control.php
 * Reusable Display & Accessibility Settings Component (WP1)
 *
 * Included by:
 * - student/settings.php
 * - faculty/settings.php
 * - admin/settings.php
 *
 * Implements a calm, accessible expandable disclosure card (accordion) containing:
 * 1. Color Style (CCS Colors [Default], Classic UDM-RADAR)
 * 2. Appearance (Use Device Setting, Light, Dark)
 * 3. Text Size (Small, Default, Large, Extra Large)
 * 4. Motion (Use Device Setting, Reduce Motion, Allow Motion)
 * 5. Underline Content Links (checkbox)
 * 6. Reset Display Settings
 */
?>
<div class="card settings-card display-pref-card" id="display-accessibility-card" style="margin-top: 24px; padding: 0; overflow: hidden;">
    <!-- Compact Disclosure Trigger Button -->
    <button type="button"
            class="display-pref-disclosure-trigger"
            id="displayPrefToggleBtn"
            aria-expanded="false"
            aria-controls="displayPreferencesPanel"
            aria-label="Toggle Display & Accessibility settings">
        <div class="display-pref-trigger-text">
            <span class="display-pref-trigger-title">Display &amp; Accessibility</span>
            <span class="display-pref-trigger-desc">Theme, text size, motion, and link display</span>
        </div>
        <div class="display-pref-trigger-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </div>
    </button>

    <!-- Expanded Settings Panel -->
    <div id="displayPreferencesPanel" class="display-pref-panel" hidden style="display: none; padding: 20px 24px 24px; border-top: 1px solid var(--border-color);">
        <!-- Accessible status announcer for screen readers and live banner -->
        <div id="display-pref-status" role="status" aria-live="polite" class="sr-only"></div>
        <div id="display-pref-banner" class="display-pref-status-banner" role="alert" aria-live="polite"></div>

        <form id="displayPreferencesForm" onsubmit="return false;" style="margin-top: 4px;">
            <div class="display-pref-container">

                <!-- 1. Color Style Fieldset -->
                <fieldset class="display-pref-fieldset">
                    <legend class="display-pref-legend">Color Style</legend>
                    <div class="display-pref-desc">Choose between CCS Colors and Classic UDM-RADAR visual identity.</div>
                    <div class="display-pref-options-grid">
                        <label class="display-pref-option">
                            <input type="radio" name="display_color_style" value="ccs">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">CCS Colors (Default)</span>
                                <span class="display-pref-option-sub">Aqua, royal blue, deep green &amp; gold</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_color_style" value="classic">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Classic UDM-RADAR</span>
                                <span class="display-pref-option-sub">Established navy &amp; gold appearance</span>
                            </div>
                        </label>
                    </div>
                </fieldset>

                <!-- 2. Appearance Fieldset -->
                <fieldset class="display-pref-fieldset">
                    <legend class="display-pref-legend">Appearance</legend>
                    <div class="display-pref-desc">Choose between light, dark, or automatic system appearance.</div>
                    <div class="display-pref-options-grid">
                        <label class="display-pref-option">
                            <input type="radio" name="display_theme" value="system">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Use Device Setting</span>
                                <span class="display-pref-option-sub">Follows operating system appearance</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_theme" value="light">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Light</span>
                                <span class="display-pref-option-sub">Dark text on light canvas</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_theme" value="dark">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Dark</span>
                                <span class="display-pref-option-sub">Light text on dark canvas</span>
                            </div>
                        </label>
                    </div>
                </fieldset>

                <!-- 3. Text Size Fieldset -->
                <fieldset class="display-pref-fieldset">
                    <legend class="display-pref-legend">Text Size</legend>
                    <div class="display-pref-desc">Scales page typography and controls while preserving layout proportions.</div>
                    <div class="display-pref-options-grid">
                        <label class="display-pref-option">
                            <input type="radio" name="display_text_size" value="small">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Small (90%)</span>
                                <span class="display-pref-option-sub">Compact typography</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_text_size" value="default">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Default (100%)</span>
                                <span class="display-pref-option-sub">Standard scale</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_text_size" value="large">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Large (112.5%)</span>
                                <span class="display-pref-option-sub">Enhanced readability</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_text_size" value="extra-large">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Extra Large (125%)</span>
                                <span class="display-pref-option-sub">Maximum readability</span>
                            </div>
                        </label>
                    </div>
                </fieldset>

                <!-- 4. Motion Fieldset -->
                <fieldset class="display-pref-fieldset">
                    <legend class="display-pref-legend">Motion Preference</legend>
                    <div class="display-pref-desc">Controls animations, transitions, chart movement, and spinning indicators.</div>
                    <div class="display-pref-options-grid">
                        <label class="display-pref-option">
                            <input type="radio" name="display_motion" value="system">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Use Device Setting</span>
                                <span class="display-pref-option-sub">Follows system motion setting</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_motion" value="reduce">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Reduce Motion</span>
                                <span class="display-pref-option-sub">Suppresses decorative animations</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_motion" value="allow">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Allow Motion</span>
                                <span class="display-pref-option-sub">Standard interface animations</span>
                            </div>
                        </label>
                    </div>
                </fieldset>

                <!-- 5. Link Visibility Fieldset -->
                <fieldset class="display-pref-fieldset">
                    <legend class="display-pref-legend">Link Visibility</legend>
                    <div class="display-pref-desc">Ensures links are distinguishable without relying on color or hover states alone.</div>
                    <div class="display-pref-options-grid">
                        <label class="display-pref-option" style="grid-column: 1 / -1;">
                            <input type="checkbox" name="display_underline_links" value="1">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Underline content links</span>
                                <span class="display-pref-option-sub">Always display persistent underlines on content hyperlinks</span>
                            </div>
                        </label>
                    </div>
                </fieldset>

                <!-- 6. Reset & Info Banner -->
                <div class="display-pref-actions">
                    <button type="button" id="resetDisplayPreferencesBtn" class="btn btn-secondary" style="min-height: 44px; padding: 10px 20px; font-weight: 600; cursor: pointer; border-radius: 6px;">
                        Reset display settings
                    </button>
                    <span class="display-pref-info-note">Preferences are saved in this browser.</span>
                </div>

            </div>
        </form>
    </div>
</div>
