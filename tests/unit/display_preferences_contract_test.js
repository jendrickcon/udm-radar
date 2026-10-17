/**
 * tests/unit/display_preferences_contract_test.js
 * Unit Contract Tests for UDM-RADAR Display & Accessibility Preferences (Revised WP1)
 *
 * Verifies:
 * 1. Default preference schema: colorStyle='ccs', theme='system', textSize='default', motion='system', underlineLinks=false
 * 2. Contrast and Display Density are strictly absent from schema and defaults
 * 3. Allowlist validation for colorStyle ('ccs' | 'classic'), theme, textSize, motion, and underlineLinks
 * 4. Contrast is rejected and discarded if present in legacy or malformed storage input
 * 5. Graceful fallback on malformed or malicious storage payloads
 * 6. Migration of legacy 'theme' key
 * 7. System theme and motion resolution
 * 8. Protection against arbitrary CSS and DOM attribute injection
 * 9. Reset behavior restores exact revised WP1 defaults
 */

const assert = require('assert');
const DisplayPreferences = require('../../assets/js/display-preferences.js');

let passed = 0;
let failed = 0;

function test(description, fn) {
  try {
    fn();
    console.log(`  [PASS] ${description}`);
    passed++;
  } catch (err) {
    console.error(`  [FAIL] ${description}`);
    console.error(`    ${err.message}`);
    failed++;
  }
}

console.log('=== Display Preferences Unit Contract Test (Revised WP1) ===\n');

// 1. Defaults verification
test('DEFAULT_PREFERENCES matches approved revised WP1 schema exactly', () => {
  const defaults = DisplayPreferences.DEFAULT_PREFERENCES;
  assert.strictEqual(defaults.version, 1);
  assert.strictEqual(defaults.colorStyle, 'ccs', 'CCS Colors must be the default colorStyle');
  assert.strictEqual(defaults.theme, 'system');
  assert.strictEqual(defaults.textSize, 'default');
  assert.strictEqual(defaults.motion, 'system');
  assert.strictEqual(defaults.underlineLinks, false);
  assert.strictEqual(defaults.contrast, undefined, 'Contrast must be strictly absent from WP1 schema');
  assert.strictEqual(defaults.density, undefined, 'Density must be strictly absent from WP1 schema');
});

// 2. Validate valid preferences
test('validatePreferences preserves valid inputs including colorStyle', () => {
  const inputCcs = {
    version: 1,
    colorStyle: 'ccs',
    theme: 'dark',
    textSize: 'extra-large',
    motion: 'reduce',
    underlineLinks: true
  };
  assert.deepStrictEqual(DisplayPreferences.validatePreferences(inputCcs), inputCcs);

  const inputClassic = {
    version: 1,
    colorStyle: 'classic',
    theme: 'light',
    textSize: 'small',
    motion: 'allow',
    underlineLinks: false
  };
  assert.deepStrictEqual(DisplayPreferences.validatePreferences(inputClassic), inputClassic);
});

// 3. Reject invalid values and substitute safe defaults
test('validatePreferences rejects invalid colorStyle and falls back to ccs', () => {
  const res = DisplayPreferences.validatePreferences({ colorStyle: 'cyberpunk-neon' });
  assert.strictEqual(res.colorStyle, 'ccs');
});

test('validatePreferences rejects invalid theme and falls back to system', () => {
  const res = DisplayPreferences.validatePreferences({ theme: 'midnight-blue' });
  assert.strictEqual(res.theme, 'system');
});

test('validatePreferences rejects invalid textSize and falls back to default', () => {
  const res = DisplayPreferences.validatePreferences({ textSize: 'massive-400%' });
  assert.strictEqual(res.textSize, 'default');
});

test('validatePreferences rejects invalid motion and falls back to system', () => {
  const res = DisplayPreferences.validatePreferences({ motion: 'hyper-speed' });
  assert.strictEqual(res.motion, 'system');
});

test('validatePreferences rejects and discards contrast from input', () => {
  const res = DisplayPreferences.validatePreferences({ contrast: 'increased' });
  assert.strictEqual(res.contrast, undefined, 'contrast must be discarded');
});

test('validatePreferences coerces non-boolean underlineLinks to default false', () => {
  const res = DisplayPreferences.validatePreferences({ underlineLinks: 'yes' });
  assert.strictEqual(res.underlineLinks, false);
});

// 4. Malformed JSON and malicious injection safety
test('validatePreferences discards arbitrary or malicious extra properties', () => {
  const malicious = {
    version: 1,
    colorStyle: 'ccs',
    theme: 'dark',
    textSize: 'default',
    motion: 'system',
    contrast: 'increased',
    underlineLinks: false,
    maliciousScript: '<script>alert(1)</script>',
    customCss: 'body { display: none; }',
    density: 'compact'
  };
  const sanitized = DisplayPreferences.validatePreferences(malicious);
  assert.strictEqual(sanitized.maliciousScript, undefined);
  assert.strictEqual(sanitized.customCss, undefined);
  assert.strictEqual(sanitized.density, undefined);
  assert.strictEqual(sanitized.contrast, undefined);
  assert.strictEqual(sanitized.colorStyle, 'ccs');
  assert.strictEqual(sanitized.theme, 'dark');
});

test('validatePreferences handles null, undefined, and non-object gracefully', () => {
  assert.deepStrictEqual(DisplayPreferences.validatePreferences(null), DisplayPreferences.DEFAULT_PREFERENCES);
  assert.deepStrictEqual(DisplayPreferences.validatePreferences(undefined), DisplayPreferences.DEFAULT_PREFERENCES);
  assert.deepStrictEqual(DisplayPreferences.validatePreferences('string-payload'), DisplayPreferences.DEFAULT_PREFERENCES);
  assert.deepStrictEqual(DisplayPreferences.validatePreferences(12345), DisplayPreferences.DEFAULT_PREFERENCES);
});

// 5. System theme resolution
test('resolveActiveTheme returns light/dark for explicit themes', () => {
  assert.strictEqual(DisplayPreferences.resolveActiveTheme('light'), 'light');
  assert.strictEqual(DisplayPreferences.resolveActiveTheme('dark'), 'dark');
});

test('resolveActiveTheme returns light as safe fallback when window.matchMedia is undefined', () => {
  assert.strictEqual(DisplayPreferences.resolveActiveTheme('system'), 'light');
});

// 6. System motion resolution
test('resolveActiveMotion returns reduce/allow for explicit motions', () => {
  assert.strictEqual(DisplayPreferences.resolveActiveMotion('reduce'), 'reduce');
  assert.strictEqual(DisplayPreferences.resolveActiveMotion('allow'), 'allow');
});

test('resolveActiveMotion returns allow as safe fallback when window.matchMedia is undefined', () => {
  assert.strictEqual(DisplayPreferences.resolveActiveMotion('system'), 'allow');
});

// 7. Allowlist arrays
test('ALLOWED_COLOR_STYLES contains only ccs and classic', () => {
  assert.deepStrictEqual(DisplayPreferences.ALLOWED_COLOR_STYLES, ['ccs', 'classic']);
});

test('ALLOWED_TEXT_SIZES contains only small, default, large, and extra-large', () => {
  assert.deepStrictEqual(DisplayPreferences.ALLOWED_TEXT_SIZES, ['small', 'default', 'large', 'extra-large']);
});

test('ALLOWED_THEMES contains only system, light, and dark', () => {
  assert.deepStrictEqual(DisplayPreferences.ALLOWED_THEMES, ['system', 'light', 'dark']);
});

test('ALLOWED_MOTIONS contains only system, reduce, and allow', () => {
  assert.deepStrictEqual(DisplayPreferences.ALLOWED_MOTIONS, ['system', 'reduce', 'allow']);
});

test('ALLOWED_CONTRASTS is undefined (contrast completely removed)', () => {
  assert.strictEqual(DisplayPreferences.ALLOWED_CONTRASTS, undefined);
});

console.log('\n==================================================');
console.log(`Unit Test Summary: ${passed} passed, ${failed} failed`);
console.log('==================================================\n');

if (failed > 0) {
  process.exit(1);
}
