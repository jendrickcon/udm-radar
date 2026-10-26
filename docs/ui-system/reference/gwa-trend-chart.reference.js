/* REFERENCE ONLY. NOT LOADED BY PRODUCTION PAGES.
Future implementers must adapt this file to the active UDM-RADAR layout and tests. */
export const OWN_SERIES_LABEL = 'Your recorded GWA';
export const BLOCKED_COHORT_LABEL = 'Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.';
export const EMPTY_GWA_MESSAGE = 'No complete recorded GWA is available for the selected terms.';
const SCHEMA = 'own-recorded-gwa-reference-v1';
const layoutEvent = 'udmradar:reference-layout-change';
const resizeControllers = new WeakMap();
const chartControllers = new WeakMap();
const exactKeys = (value, names) => value && typeof value === 'object' && !Array.isArray(value)
  && Object.keys(value).length === names.length && names.every(name => Object.hasOwn(value, name));

export function validateRecordedGwaModel(input) {
  if (!exactKeys(input, ['schemaVersion', 'scale', 'points', 'cohort']) || input.schemaVersion !== SCHEMA) {
    throw new TypeError('Unsupported recorded GWA reference contract');
  }
  if (!exactKeys(input.scale, ['min', 'max']) || input.scale.min !== 0 || input.scale.max !== 4) {
    throw new TypeError('Configured prototype point scale must be 0 to 4');
  }
  if (!exactKeys(input.cohort, ['status']) || input.cohort.status !== 'BLOCKED') {
    throw new TypeError('Cohort contract BLOCKED; numeric values or enable flags are not accepted');
  }
  if (!Array.isArray(input.points)) throw new TypeError('Recorded term points required');
  let previous = null;
  const points = input.points.map(point => {
    if (!exactKeys(point, ['schoolYear', 'semester', 'state', 'value'])) throw new TypeError('Unexpected point fields');
    const match = typeof point.schoolYear === 'string' && point.schoolYear.match(/^(\d{4})-(\d{4})$/);
    if (!match || Number(match[2]) !== Number(match[1]) + 1 || ![1, 2, 3].includes(point.semester)) {
      throw new TypeError('Invalid recorded academic period');
    }
    if (!['recorded', 'incomplete', 'unavailable'].includes(point.state)) throw new TypeError('Invalid availability');
    const recorded = point.state === 'recorded';
    if (recorded ? typeof point.value !== 'number' || !Number.isFinite(point.value) || point.value < 1 || point.value > 4 : point.value !== null) {
      throw new TypeError('Use finite recorded points or null for incomplete/unavailable terms');
    }
    const year = Number(match[1]);
    const key = year * 10 + point.semester;
    if (previous && key <= previous.key) throw new TypeError('Terms must be distinct and chronological');
    const adjacent = !previous || (year === previous.year && point.semester === previous.semester + 1)
      || (year === previous.year + 1 && point.semester === 1 && [2, 3].includes(previous.semester));
    const result = Object.freeze({ ...point, label: point.schoolYear + ' · ' + (point.semester === 3 ? 'Summer' : 'Semester ' + point.semester), breakBefore: !adjacent });
    previous = { key, year, semester: point.semester };
    return result;
  });
  return Object.freeze({ schemaVersion: SCHEMA, scale: Object.freeze({ min: 0, max: 4 }), points: Object.freeze(points), cohort: Object.freeze({ status: 'BLOCKED' }) });
}

export function buildGwaChartReference(input, options = {}) {
  const model = validateRecordedGwaModel(input);
  const color = options.currentColor || '#1769aa';
  const hasValues = model.points.some(point => point.state === 'recorded');
  const datasets = [{ label: OWN_SERIES_LABEL, data: model.points.map(point => point.value),
    borderColor: color, backgroundColor: color, borderDash: [], pointRadius: 4, tension: 0, spanGaps: false, fill: false,
    segment: { borderColor: context => model.points[context.p1DataIndex]?.breakBefore ? 'transparent' : color }
  }];
  if (options.includeBlockedSeries === true) datasets.push({ label: BLOCKED_COHORT_LABEL, data: [], hidden: true, borderDash: [6, 4], spanGaps: false, tension: 0 });
  const legend = Object.freeze([
    Object.freeze({ label: OWN_SERIES_LABEL, state: hasValues ? 'active' : 'unavailable', pattern: 'solid', interactive: false }),
    Object.freeze({ label: BLOCKED_COHORT_LABEL, state: 'BLOCKED', pattern: 'disabled-dashed', interactive: false })
  ]);
  const tableRows = Object.freeze(model.points.map(point => Object.freeze({ term: point.label,
    ownValue: point.value === null ? (point.state === 'incomplete' ? 'Pending' : 'Unavailable') : point.value.toFixed(2),
    state: point.state, cohort: BLOCKED_COHORT_LABEL
  })));
  return { model, legend, tableRows, emptyMessage: hasValues ? null : EMPTY_GWA_MESSAGE,
    interpretation: 'Higher point values indicate stronger recorded performance under the configured prototype scale. Comparisons provide context, not rank or official standing. Incomplete terms may be unavailable. Prototype performance on synthetic academic records',
    chartConfig: { type: 'line', data: { labels: model.points.map(point => point.label), datasets }, options: {
      responsive: true, maintainAspectRatio: false, animation: false,
      scales: { y: { min: model.scale.min, max: model.scale.max, title: { display: true, text: 'Recorded GWA (points)' } } },
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => OWN_SERIES_LABEL + ': ' + (typeof context.parsed.y === 'number' ? context.parsed.y.toFixed(2) + ' points' : 'Unavailable') } } }
    } }
  };
}

export function attachReferenceChartResize(root, container, resize, options = {}) {
  const existing = resizeControllers.get(container);
  if (existing) {
    if (existing.root !== root || existing.resize !== resize) throw new TypeError('Container already has a different resize owner');
    return existing.controller;
  }
  const win = root.ownerDocument.defaultView;
  let timer = null;
  let disposed = false;
  let previousSize = '';
  const schedule = () => {
    if (disposed) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      if (!disposed && container.isConnected !== false && !container.hidden) resize();
    }, options.debounceMs ?? 40);
  };
  const observer = typeof win.ResizeObserver === 'function' ? new win.ResizeObserver(entries => {
    const rect = entries[0]?.contentRect;
    const key = rect ? rect.width + ':' + rect.height : '';
    if (key !== previousSize) { previousSize = key; schedule(); }
  }) : null;
  const destroy = () => {
    if (disposed) return;
    disposed = true;
    clearTimeout(timer);
    observer?.disconnect();
    root.removeEventListener(layoutEvent, onLayout);
    win.removeEventListener('pagehide', destroy);
    resizeControllers.delete(container);
  };
  const onLayout = event => { if (event.detail?.phase === 'settled') schedule(); };
  const controller = { refresh: schedule, destroy };
  resizeControllers.set(container, { root, resize, controller });
  observer?.observe(container);
  root.addEventListener(layoutEvent, onLayout);
  win.addEventListener('pagehide', destroy);
  return controller;
}

export function mountReferenceChart({ Chart, canvas, model, root, container, ...options }) {
  if (chartControllers.has(canvas)) return chartControllers.get(canvas);
  const reference = buildGwaChartReference(model, options);
  // Host must render reference.legend and reference.tableRows as safe text before calling this.
  if (typeof Chart !== 'function' || reference.emptyMessage) return { reference, chart: null, destroy() {} };
  const chart = new Chart(canvas, reference.chartConfig);
  const resize = attachReferenceChartResize(root, container, () => chart.resize());
  const win = root.ownerDocument.defaultView;
  let disposed = false;
  const destroy = () => {
    if (disposed) return;
    disposed = true;
    resize.destroy();
    chart.destroy();
    win.removeEventListener('pagehide', destroy);
    chartControllers.delete(canvas);
  };
  const controller = { reference, chart, destroy };
  chartControllers.set(canvas, controller);
  win.addEventListener('pagehide', destroy);
  return controller;
}
