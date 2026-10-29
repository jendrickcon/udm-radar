<?php
/**
 * includes/display_preferences_control.php
 * Reusable Display & Accessibility Settings Component
 *
 * Included by:
 * - student/settings.php
 * - faculty/settings.php
 * - admin/settings.php
 *
 * Implements a calm, accessible 2-part structure:
 * Part 1: Compact Summary / Launcher Card (Row 1, Right column)
 * Part 2: Full-Width Expanded Preferences Panel (Row 2, Spanning full workspace)
 */
?>
<!-- Part 1: Display & Accessibility Summary Card (Launcher) -->
<section class="card settings-card display-pref-summary-card" id="display-accessibility-card" aria-labelledby="displayPrefSummaryTitle">
    <div class="display-pref-summary-header">
        <div class="table-title" id="displayPrefSummaryTitle" style="margin-bottom: 4px;">Display &amp; Accessibility</div>
        <p class="display-pref-summary-desc" style="color: var(--text-gray); font-size: 0.85rem; margin: 0 0 16px 0; line-height: 1.4;">
            Theme, text size, motion, link display, and navigation
        </p>
    </div>

    <!-- Compact current-state summary grid -->
    <div class="display-pref-state-grid" aria-label="Current display settings summary">
        <div class="display-pref-state-cell">
            <span class="display-pref-state-label">Color Style</span>
            <span class="display-pref-state-val" id="summary-color-val">CCS Colors</span>
        </div>
        <div class="display-pref-state-cell">
            <span class="display-pref-state-label">Appearance</span>
            <span class="display-pref-state-val" id="summary-theme-val">Device setting</span>
        </div>
        <div class="display-pref-state-cell">
            <span class="display-pref-state-label">Text Size</span>
            <span class="display-pref-state-val" id="summary-text-val">Default (100%)</span>
        </div>
        <div class="display-pref-state-cell">
            <span class="display-pref-state-label">Motion</span>
            <span class="display-pref-state-val" id="summary-motion-val">Device setting</span>
        </div>
    </div>

    <div class="display-pref-summary-action" style="margin-top: 18px;">
        <button type="button"
                class="btn display-pref-launcher-btn"
                id="displayPrefToggleBtn"
                aria-expanded="false"
                aria-controls="displayPreferencesPanel"
                aria-label="Toggle Display & Accessibility preferences panel">
            <span id="displayPrefToggleText">Open preferences</span>
            <svg id="displayPrefToggleIcon" class="display-pref-toggle-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
    </div>
</section>

<!-- Part 2: Expanded Full-Width Display Preferences Panel -->
<section class="card settings-card display-preferences-expanded"
         id="displayPreferencesPanel"
         hidden
         style="display: none;"
         aria-labelledby="displayPrefPanelHeading">

    <div class="display-pref-panel-header">
        <div class="table-title" id="displayPrefPanelHeading" style="margin-bottom: 4px; font-size: 1.15rem;">
            Display &amp; Accessibility Preferences
        </div>
        <p style="color: var(--text-gray); font-size: 0.875rem; margin: 0 0 20px 0; line-height: 1.5;">
            Customize colors, appearance, text size, motion, link visibility, and sidebar behavior. Changes apply immediately and are saved in this browser.
        </p>
    </div>

    <!-- Accessible status announcer for screen readers and live banner -->
    <div id="display-pref-status" role="status" aria-live="polite" class="sr-only"></div>
    <div id="display-pref-banner" class="display-pref-status-banner" role="alert" aria-live="polite"></div>

    <form id="displayPreferencesForm" onsubmit="return false;" style="margin-top: 4px;">
        <div class="display-pref-groups-grid">

            <!-- 1. Appearance Group -->
            <fieldset class="display-pref-group">
                <legend class="display-pref-group-title">Appearance</legend>

                <!-- Color Style -->
                <div class="display-pref-section">
                    <div class="display-pref-item-label">Color Style</div>
                    <div class="display-pref-item-desc">Visual identity and accent colors</div>
                    <div class="display-pref-options-stack">
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
                </div>

                <!-- Theme -->
                <div class="display-pref-section" style="margin-top: 16px;">
                    <div class="display-pref-item-label">Theme</div>
                    <div class="display-pref-item-desc">Light, dark, or system canvas appearance</div>
                    <div class="display-pref-options-stack">
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
                </div>
            </fieldset>

            <!-- 2. Reading Group -->
            <fieldset class="display-pref-group">
                <legend class="display-pref-group-title">Reading</legend>

                <!-- Text Size -->
                <div class="display-pref-section">
                    <div class="display-pref-item-label">Text Size</div>
                    <div class="display-pref-item-desc">Scales typography while maintaining layout</div>
                    <div class="display-pref-options-stack">
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
                                <span class="display-pref-option-sub">Standard institutional scale</span>
                            </div>
                        </label>
                        <label class="display-pref-option">
                            <input type="radio" name="display_text_size" value="large">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Large (112.5%)</span>
                                <span class="display-pref-option-sub">Enhanced reading comfort</span>
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
                </div>

                <!-- Link Visibility -->
                <div class="display-pref-section" style="margin-top: 16px;">
                    <div class="display-pref-item-label">Underline Content Links</div>
                    <div class="display-pref-item-desc">Distinct visual indicator for hyperlinks</div>
                    <div class="display-pref-options-stack">
                        <label class="display-pref-option">
                            <input type="checkbox" name="display_underline_links" value="1">
                            <div class="display-pref-option-text">
                                <span class="display-pref-option-label">Underline content links</span>
                                <span class="display-pref-option-sub">Persistent underlines on content hyperlinks</span>
                            </div>
                        </label>
                    </div>
                </div>
            </fieldset>

            <!-- 3. Motion Group -->
            <fieldset class="display-pref-group">
                <legend class="display-pref-group-title">Motion</legend>

                <!-- Animation Preference -->
                <div class="display-pref-section">
                    <div class="display-pref-item-label">Animation Preference</div>
                    <div class="display-pref-item-desc">Transitions, chart motion, and indicators</div>
                    <div class="display-pref-options-stack">
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
                </div>
            </fieldset>

            <fieldset class="display-pref-group">
                <legend class="display-pref-group-title">Navigation</legend>
                <div class="display-pref-section">
                    <label class="display-pref-option">
                        <input type="checkbox" name="display_automatic_sidebar_expansion" value="1" aria-labelledby="automatic-sidebar-label" aria-describedby="automatic-sidebar-description">
                        <div class="display-pref-option-text">
                            <span class="display-pref-option-label" id="automatic-sidebar-label">Automatic sidebar expansion</span>
                            <span class="display-pref-option-sub" id="automatic-sidebar-description">Off by default. When enabled, hovering opens a collapsed sidebar and leaving closes it. Use the navigation button to expand or collapse manually. Touch and keyboard users can focus an item or hold its icon to read its label.</span>
                        </div>
                    </label>
                </div>
            </fieldset>
        </div>

        <!-- 4. Reset Button & Footer (Sections 8, 9, 10) -->
        <div class="display-pref-footer">
            <div class="display-pref-footer-left">
                <button type="button"
                        id="resetDisplayPreferencesBtn"
                        class="btn display-pref-reset-btn"
                        aria-label="Reset display preferences to defaults">
                    <svg class="display-pref-reset-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    <span>Reset to defaults</span>
                </button>
                <span id="display-pref-reset-msg" class="display-pref-reset-msg" role="status" aria-live="polite"></span>
            </div>
            <div class="display-pref-footer-right">
                <span class="display-pref-auto-save">Saved automatically in this browser</span>
            </div>
        </div>

    </form>
</section>
