/* REFERENCE ONLY. NOT LOADED BY PRODUCTION PAGES.
Future implementers must adapt this file to the active UDM-RADAR layout and tests. */
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const loadReference = async name => import('data:text/javascript;base64,' + Buffer.from(await readFile(new URL(name, import.meta.url), 'utf8')).toString('base64'));
const chart = await loadReference('gwa-trend-chart.reference.js');
const sidebar = await loadReference('collapsible-sidebar.reference.js');
const point = (year, semester, value, state = 'recorded') => ({ schoolYear: year, semester, value, state });
const input = points => ({ schemaVersion: 'own-recorded-gwa-reference-v1', scale: { min: 0, max: 4 }, points, cohort: { status: 'BLOCKED' } });
const pause = () => new Promise(resolve => setTimeout(resolve, 15));

test('recorded values and accessible table share the same precision and meaning', () => {
  const result = chart.buildGwaChartReference(input([point('2025-2026', 1, 3.125), point('2025-2026', 2, 3.5)]));
  assert.deepEqual(result.chartConfig.data.datasets[0].data, [3.125, 3.5]);
  assert.deepEqual(result.tableRows.map(row => row.ownValue), ['3.13', '3.50']);
  assert.equal(result.legend[0].label, 'Your recorded GWA');
  assert.equal(result.emptyMessage, null);
});
test('blocked legend remains visible and optional comparison has no numeric points', () => {
  const result = chart.buildGwaChartReference(input([point('2025-2026', 1, 3)]), { includeBlockedSeries: true });
  assert.equal(result.legend[1].label, 'Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.');
  assert.equal(result.legend[1].state, 'BLOCKED');
  assert.equal(result.legend[1].interactive, false);
  assert.deepEqual(result.chartConfig.data.datasets[1].data, []);
  assert.equal(result.chartConfig.data.datasets[1].hidden, true);
  assert.equal(result.tableRows[0].cohort, result.legend[1].label);
});
test('numeric cohort inputs and enable flags cannot turn on the unavailable contract', () => {
  for (const cohort of [{ status: 'APPROVED' }, { status: 'BLOCKED', value: 3 }, { status: 'BLOCKED', enabled: true }, { status: 'BLOCKED', values: [3] }]) {
    assert.throws(() => chart.validateRecordedGwaModel({ ...input([]), cohort }), /BLOCKED/);
  }
});
test('incomplete and unavailable terms stay null with explicit table states', () => {
  const result = chart.buildGwaChartReference(input([point('2025-2026', 1, null, 'incomplete'), point('2025-2026', 2, null, 'unavailable')]));
  assert.deepEqual(result.chartConfig.data.datasets[0].data, [null, null]);
  assert.deepEqual(result.tableRows.map(row => row.ownValue), ['Pending', 'Unavailable']);
  assert.equal(result.emptyMessage, chart.EMPTY_GWA_MESSAGE);
  assert.throws(() => chart.validateRecordedGwaModel(input([point('2025-2026', 1, 3, 'incomplete')])));
});
test('no records produce clear empty state without generating values or periods', () => {
  const result = chart.buildGwaChartReference(input([]));
  assert.equal(result.emptyMessage, 'No complete recorded GWA is available for the selected terms.');
  assert.deepEqual(result.tableRows, []);
  assert.deepEqual(result.chartConfig.data.labels, []);
  assert.deepEqual(result.chartConfig.data.datasets[0].data, []);
});
test('duplicate, reversed and malformed terms are rejected; Summer is explicit', () => {
  assert.throws(() => chart.validateRecordedGwaModel(input([point('2025-2026', 1, 3), point('2025-2026', 1, 3)])));
  assert.throws(() => chart.validateRecordedGwaModel(input([point('2025-2026', 2, 3), point('2025-2026', 1, 3)])));
  assert.throws(() => chart.validateRecordedGwaModel(input([point('2025-2027', 1, 3)])));
  assert.match(chart.validateRecordedGwaModel(input([point('2025-2026', 3, 3)])).points[0].label, /Summer/);
});
test('scale, finite numbers and strict input prevent coercion and peer metadata', () => {
  for (const value of [NaN, Infinity, -1, 0, 0.99, 4.1, '3.00', null]) assert.throws(() => chart.validateRecordedGwaModel(input([point('2025-2026', 1, value)])));
  assert.throws(() => chart.validateRecordedGwaModel({ ...input([]), scale: { min: 4, max: 0 } }));
  assert.throws(() => chart.validateRecordedGwaModel({ ...input([]), studentId: 999 }));
  const result = chart.buildGwaChartReference(input([point('2025-2026', 1, 1)]));
  assert.equal(result.tableRows[0].ownValue, '1.00');
  assert.equal(result.chartConfig.options.scales.y.min, 0); // Axis floor is distinct from eligible GWA.
});
test('missing interval breaks stroke; no smoothing, fill or gap interpolation', () => {
  const result = chart.buildGwaChartReference(input([point('2023-2024', 1, 3), point('2025-2026', 1, 3.5)]));
  const dataset = result.chartConfig.data.datasets[0];
  assert.equal(dataset.spanGaps, false);
  assert.equal(dataset.tension, 0);
  assert.equal(dataset.fill, false);
  assert.equal(dataset.segment.borderColor({ p1DataIndex: 1 }), 'transparent');
  assert.equal(result.model.points.length, 2);
});
test('validated model is detached and frozen', () => {
  const source = input([point('2025-2026', 1, 3)]);
  const model = chart.validateRecordedGwaModel(source);
  source.points[0].value = 1;
  assert.equal(model.points[0].value, 3);
  assert.throws(() => { model.points[0].value = 2; });
});

class DetailEvent extends Event { constructor(name, options = {}) { super(name, options); this.detail = options.detail; } }
function fakeHost(storage) {
  const desktop = new EventTarget(); desktop.matches = true;
  const reduced = new EventTarget(); reduced.matches = true;
  const win = new EventTarget();
  Object.assign(win, { CustomEvent: DetailEvent, localStorage: storage, matchMedia: query => query.includes('901px') ? desktop : reduced, getComputedStyle: () => ({ getPropertyValue: () => '0ms' }) });
  const toggle = new EventTarget(); const attrs = new Map();
  Object.assign(toggle, { getAttribute: key => attrs.get(key) ?? null, setAttribute: (key, value) => attrs.set(key, value), removeAttribute: key => attrs.delete(key) });
  const root = new EventTarget(); const classes = new Set();
  Object.assign(root, { ownerDocument: { defaultView: win }, closest: () => null,
    querySelector: selector => selector.includes('toggle') ? toggle : { id: 'reference-navigation' },
    classList: { contains: key => classes.has(key), toggle: (key, flag) => flag ? classes.add(key) : classes.delete(key) } });
  return { root, win, toggle, desktop, attrs, classes };
}
test('sidebar idempotent init, preference fallback and desktop state work without DOM writes elsewhere', async () => {
  const denied = { getItem() { throw new Error('Denied'); }, setItem() { throw new Error('Denied'); } };
  const host = fakeHost(denied); const events = [];
  host.root.addEventListener(sidebar.REFERENCE_LAYOUT_EVENT, event => events.push(event.detail));
  const controller = sidebar.initReferenceSidebar(host.root);
  assert.equal(sidebar.initReferenceSidebar(host.root), controller);
  controller.setCollapsed(true); await pause();
  assert.equal(host.attrs.get('aria-expanded'), 'false');
  assert.equal(host.attrs.get('aria-controls'), 'reference-navigation');
  assert.deepEqual(events.at(-1), { collapsed: true, phase: 'settled' });
  host.desktop.matches = false; host.desktop.dispatchEvent(new Event('change')); await pause();
  assert.equal(host.attrs.get('aria-expanded'), 'true');
  host.desktop.matches = true; host.desktop.dispatchEvent(new Event('change')); await pause();
  assert.equal(host.attrs.get('aria-expanded'), 'false');
  controller.destroy(); controller.destroy();
  assert.equal(host.attrs.get('aria-expanded'), undefined);
  assert.equal(host.classes.has('ui-ref-sidebar-collapsed'), false);
  const before = events.length; host.toggle.dispatchEvent(new Event('click')); await pause();
  assert.equal(events.length, before);
});
test('resize notifications coalesce, duplicate owner rejected and cleanup disconnects', async () => {
  const host = fakeHost(); let observed = 0; let disconnected = 0; let resized = 0;
  host.win.ResizeObserver = class { observe() { observed++; } disconnect() { disconnected++; } };
  const container = { isConnected: true, hidden: false }; const resize = () => resized++;
  const controller = chart.attachReferenceChartResize(host.root, container, resize, { debounceMs: 0 });
  assert.equal(chart.attachReferenceChartResize(host.root, container, resize), controller);
  assert.throws(() => chart.attachReferenceChartResize(host.root, container, () => {}));
  host.root.dispatchEvent(new DetailEvent(sidebar.REFERENCE_LAYOUT_EVENT, { detail: { phase: 'settled' } }));
  controller.refresh(); controller.refresh(); await pause();
  assert.equal(resized, 1); assert.equal(observed, 1);
  controller.destroy(); controller.destroy();
  assert.equal(disconnected, 1);
  host.root.dispatchEvent(new DetailEvent(sidebar.REFERENCE_LAYOUT_EVENT, { detail: { phase: 'settled' } })); await pause();
  assert.equal(resized, 1);
});
test('sidebar caps an excessive configured transition at the governance limit', async () => {
  const host = fakeHost();
  host.win.matchMedia = query => query.includes('901px') ? host.desktop : { matches: false, addEventListener() {}, removeEventListener() {} };
  host.win.getComputedStyle = () => ({ getPropertyValue: () => '500ms' });
  let settled = 0;
  host.root.addEventListener(sidebar.REFERENCE_LAYOUT_EVENT, () => settled++);
  const controller = sidebar.initReferenceSidebar(host.root);
  await new Promise(resolve => setTimeout(resolve, 275)); // 200ms maximum + 30ms settle allowance.
  controller.destroy();
  assert.equal(settled, 1);
});
test('chart constructor is injected once; pagehide destroys chart and resize lifecycle', () => {
  const host = fakeHost(); const canvas = {}; const container = { isConnected: true };
  let created = 0; let destroyed = 0;
  class HostChart { constructor() { created++; } resize() {} destroy() { destroyed++; } }
  const args = { Chart: HostChart, canvas, root: host.root, container, model: input([point('2025-2026', 1, 3)]) };
  const controller = chart.mountReferenceChart(args);
  assert.equal(chart.mountReferenceChart(args), controller);
  assert.equal(created, 1);
  host.win.dispatchEvent(new Event('pagehide'));
  assert.equal(destroyed, 1);
  controller.destroy(); assert.equal(destroyed, 1);
});
test('no library or no numeric records preserves table without constructing a chart', () => {
  const own = input([point('2025-2026', 1, 3)]);
  assert.equal(chart.mountReferenceChart({ model: own }).chart, null);
  let called = false;
  const empty = chart.mountReferenceChart({ Chart: class { constructor() { called = true; } }, model: input([]) });
  assert.equal(empty.chart, null); assert.equal(called, false);
  assert.equal(empty.reference.emptyMessage, chart.EMPTY_GWA_MESSAGE);
});
