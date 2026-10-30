/* Admin presentation only. Academic aggregates and ownership remain in the report API. */
(() => {
    'use strict';
    const root = document.getElementById('admin-comparisons');
    if (!root) return;
    const dimensions = { school_year: 'School year', semester: 'Semester', year_level: 'Curriculum year', subject: 'Subject', section: 'Section', faculty_subject: 'Faculty assignment' };
    const metrics = { gwa: 'Recorded-grade average', student_count: 'Student count', numeric_coverage_pct: 'Numeric grade coverage', final_coverage_pct: 'Final-outcome coverage', prediction_coverage_pct: 'Prediction coverage', risk_distribution: 'Advisory-risk distribution' };
    const helpers = { school_year: 'Compare recorded school-year groups.', semester: 'Compare recorded terms in chronological order.', year_level: 'Compare subject curriculum years, not Student year-level history.', subject: 'Compare subject records for context; units and difficulty may differ.', section: 'Compare verified current section records.', faculty_subject: 'Choose a common school year, semester and subject before selecting verified Faculty assignments.' };
    const make = (tag, text, attrs = {}) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value); return node; };
    const controls = document.getElementById('ac-controls'), notice = document.getElementById('ac-notice'), result = document.getElementById('ac-result');
    let report, request, dimension = 'semester', metric = 'gwa', mode = 'overlay', keys = ['', ''], busy = false, failed = false;
    const field = (id, label) => { const box = make('div', undefined, { class: 'ac-field' }); const select = make('select', undefined, { id }); box.append(make('label', label, { for: id }), select); return { box, select }; };
    const scope = make('div', undefined, { role: 'group', 'aria-label': 'Compare by', class: 'ac-dimensions' });
    for (const [key, label] of Object.entries(dimensions)) {
        const button = make('button', label, { type: 'button', class: 'btn btn--secondary', 'data-dimension': key, 'aria-pressed': String(key === dimension) });
        button.addEventListener('click', () => {
            dimension = key; keys = ['', ''];
            const conflicting = key === 'faculty_subject' ? 'faculty' : key;
            const params = parameters(); params.delete(conflicting);
            if (key === 'subject') common.subject.select.value = '';
            if (key === 'school_year') common.school_year.select.value = '';
            if (key === 'semester') common.semester.select.value = '';
            if (report?.filters[conflicting]) request?.(params); else draw();
        });
        scope.append(button);
    }
    const helper = make('p', helpers[dimension], { class: 'ac-helper', id: 'ac-dimension-help', 'aria-live': 'polite' });
    controls.append(make('h3', '1. Compare by'), scope, helper);
    const commonRow = make('div', undefined, { class: 'ac-control-grid' }), common = {};
    for (const [key, label] of Object.entries({ school_year: 'Common school year', semester: 'Common semester', subject: 'Common subject' })) {
        common[key] = field('ac-' + key, label); commonRow.append(common[key].box);
        common[key].select.addEventListener('change', () => { keys = ['', '']; request?.(parameters()); });
    }
    const clear = make('button', 'Clear scope', { type: 'button', class: 'btn btn--quiet' });
    clear.addEventListener('click', () => { keys = ['', '']; request?.(new URLSearchParams()); });
    controls.append(make('h3', '2. Common scope and period'), commonRow, clear);
    const groups = make('div', undefined, { class: 'ac-control-grid', id: 'ac-groups' });
    const groupFields = [];
    for (const letter of ['A', 'B', 'C']) {
        const item = field('ac-group-' + letter.toLowerCase(), 'Group ' + letter);
        item.box.hidden = letter === 'C'; groups.append(item.box); groupFields.push(item);
        item.select.addEventListener('change', () => { keys[['A', 'B', 'C'].indexOf(letter)] = item.select.value; drawResult(); });
    }
    const groupActions = make('div', undefined, { class: 'ac-group-actions' });
    const add = make('button', '+ Add Group C', { type: 'button', class: 'btn btn--secondary', 'aria-describedby': 'ac-group-limit' });
    const remove = make('button', 'Remove Group C', { type: 'button', class: 'btn btn--quiet', hidden: '' });
    const limit = make('span', '', { id: 'ac-group-limit', class: 'ac-helper', role: 'status' });
    add.addEventListener('click', () => { if (keys.length === 3) return; keys.push(''); draw(); groupFields[2].select.focus(); });
    remove.addEventListener('click', () => { keys = keys.slice(0, 2); draw(); add.focus(); });
    groupActions.append(add, remove, limit); controls.append(make('h3', '3. Select groups'), groups, groupActions);
    const finalRow = make('div', undefined, { class: 'ac-final-controls' });
    const metricField = field('ac-metric', 'Metric');
    for (const [value, label] of Object.entries(metrics)) metricField.select.append(make('option', label, { value }));
    metricField.select.addEventListener('change', () => { metric = metricField.select.value; drawResult(); });
    const modes = make('div', undefined, { role: 'group', 'aria-label': 'Comparison display mode', class: 'ac-modes' });
    for (const [value, label] of [['overlay', 'Overlay chart'], ['cards', 'Separate cards']]) {
        const button = make('button', label, { type: 'button', class: 'btn btn--secondary', 'data-mode': value, 'aria-pressed': String(mode === value) });
        button.addEventListener('click', () => { mode = value; drawResult(); }); modes.append(button);
    }
    const modeBox = make('div'); modeBox.append(make('span', 'View', { class: 'ac-label' }), modes);
    const compare = make('button', 'Compare groups', { type: 'button', class: 'btn btn--primary' });
    compare.addEventListener('click', () => { if (failed) request?.(parameters()); else drawResult(true); });
    finalRow.append(metricField.box, modeBox, compare); controls.append(finalRow);
    const modeHelp = make('p', '', { class: 'ac-helper' }); controls.append(modeHelp);
    document.getElementById('ac-view-history').addEventListener('click', () => {
        document.getElementById('tab-btn-overview').click();
        const heading = document.getElementById('analytics-trend-heading'); heading.tabIndex = -1; heading.focus();
    });

    function parameters() {
        const params = new URLSearchParams(report?.filters || {});
        for (const [name, item] of Object.entries(common)) { if (!item.select.disabled) params.set(name, item.select.value); }
        params.delete(dimension === 'faculty_subject' ? 'faculty' : dimension);
        params.delete('case_status'); params.delete('decision');
        return params;
    }
    function candidates() {
        const list = report?.groups[dimension] || [];
        if (dimension !== 'faculty_subject') return list;
        if (!report || !report.filters.school_year || !report.filters.semester || !report.filters.subject) return [];
        return list.filter(group => /^\d+\|\d+$/.test(group.key) && group.key.split('|')[1] === report.filters.subject && !/Historical|Assignment unavailable/.test(group.label));
    }
    function options(select, list, current, prompt) {
        select.replaceChildren(make('option', prompt, { value: '' }));
        list.forEach(item => select.append(make('option', item.label, { value: item.value ?? item.key })));
        select.value = current || '';
    }
    function draw() {
        scope.querySelectorAll('button').forEach(button => {
            const active = button.dataset.dimension === dimension;
            button.setAttribute('aria-pressed', String(active));
            button.textContent = (active ? '✓ ' : '') + dimensions[button.dataset.dimension];
        });
        helper.textContent = helpers[dimension];
        for (const [name, item] of Object.entries(common)) {
            item.select.disabled = dimension === name;
            options(item.select, report?.options[name] || [], report?.filters[name] || '', dimension === 'faculty_subject' ? 'Choose ' + name.replace('_', ' ') : 'All recorded values');
        }
        const choices = candidates();
        keys.forEach((key, index) => {
            if (!choices.some(group => group.key === key)) keys[index] = choices.find(group => !keys.includes(group.key))?.key || '';
        });
        groupFields.forEach((item, index) => {
            item.box.hidden = index >= keys.length; options(item.select, choices, keys[index], 'Choose a group'); item.select.disabled = busy || !choices.length;
        });
        add.disabled = keys.length === 3 || choices.length <= keys.length; remove.hidden = keys.length !== 3;
        limit.textContent = keys.length === 3 ? 'Maximum three groups.' : choices.length <= keys.length ? 'No additional recorded group is available.' : 'Optional; up to three groups.';
        metricField.select.querySelectorAll('option').forEach(option => { option.disabled = ['prediction_coverage_pct', 'risk_distribution'].includes(option.value) && !['school_year', 'semester', 'section'].includes(dimension); });
        if (metricField.select.selectedOptions[0]?.disabled) { metric = 'gwa'; metricField.select.value = metric; }
        drawResult();
    }
    function validation() {
        if (failed) return ['Comparison records could not be loaded. Apply the scope again to retry.', compare];
        if (busy || !report) return ['Loading comparison records…', null];
        if (dimension === 'faculty_subject') {
            for (const [name, item] of Object.entries(common)) {
                if (!item.select.value || item.select.value !== report.filters[name]) return ['Choose the same school year, semester and subject for every Faculty assignment.', item.select];
            }
        }
        const choices = candidates();
        if (dimension === 'faculty_subject' && choices.length < 2) return ['No common verified subject assignment is available for the selected Faculty members and period.', common.subject.select];
        for (let index = 0; index < keys.length; index++) {
            if (!keys[index] || !choices.some(group => group.key === keys[index]) || groupFields[index].select.value !== keys[index]) return ['Choose an available recorded group for each comparison.', groupFields[index].select];
        }
        if (new Set(keys).size !== keys.length) return ['Choose different groups for this comparison.', groupFields[1].select];
        return null;
    }
    const rawValue = group => Number.isFinite(group?.[metric]) ? group[metric] : null;
    const valueText = group => rawValue(group) === null ? 'Unavailable' : metric === 'student_count' ? String(rawValue(group)) : rawValue(group).toFixed(2) + (metric.endsWith('_pct') ? '%' : '');
    const coverage = group => Number.isFinite(group.numeric_coverage_pct) ? group.numeric_coverage_pct.toFixed(2) + '%' : 'Unavailable';
    const status = group => group.provisional ? 'Provisional' : 'Recorded outcomes resolved';
    function drawResult(focusInvalid = false) {
        modes.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mode === mode)));
        modeHelp.textContent = mode === 'overlay' ? 'Show selected groups together on one scale.' : 'Show complete details for each group.';
        controls.querySelectorAll('[aria-invalid]').forEach(control => { control.removeAttribute('aria-invalid'); control.removeAttribute('aria-describedby'); });
        result.replaceChildren(); const error = validation();
        notice.hidden = !error; notice.textContent = error?.[0] || ''; notice.setAttribute('role', focusInvalid ? 'alert' : 'status');
        if (error) {
            if (focusInvalid && error[1]) { error[1].setAttribute('aria-invalid', 'true'); error[1].setAttribute('aria-describedby', 'ac-notice'); error[1].focus(); }
            return;
        }
        const selected = keys.map(key => candidates().find(group => group.key === key));
        const valuesAvailable = selected.some(group => rawValue(group) !== null);
        if (!valuesAvailable) { notice.hidden = false; notice.textContent = 'No recorded values are available for this metric and scope. Review coverage or choose another metric.'; }
        const layout = make('div', undefined, { class: mode === 'overlay' ? 'card visual-comparison-card visual-comparison-layout ac-result-layout' : 'ac-result-layout', 'data-view': mode });
        if (valuesAvailable && mode === 'overlay') {
            const plot = make('div', undefined, { class: 'ac-plot visual-comparison-chart-pane', 'data-comparison-chart': '' }); plot.append(make('h3', 'Visual comparison'));
            const max = metric === 'gwa' ? 4 : metric.endsWith('_pct') ? 100 : Math.max(1, ...selected.map(group => rawValue(group) || 0));
            plot.append(make('p', metrics[metric] + ' · shared scale 0–' + max, { class: 'ac-helper' }));
            const bars = make('div', undefined, { role: 'img', 'aria-label': metrics[metric] + ' for selected groups; exact evidence follows', class: 'ac-bars' });
            selected.forEach((group, index) => {
                const row = make('div', undefined, { class: 'ac-bar-row' }), raw = rawValue(group);
                row.append(make('span', 'Group ' + 'ABC'[index], { class: 'ac-group-tag' }), make('span', valueText(group), { class: 'ac-bar-value' }));
                if (raw !== null) {
                    const track = make('div', undefined, { class: 'ac-track' });
                    track.append(make('span', '', { class: 'ac-bar ac-color-' + index, style: 'width:' + Math.max(0, Math.min(100, raw / max * 100)) + '%', 'data-value': raw, title: group.label + ': ' + valueText(group) })); row.append(track);
                }
                row.append(make('span', group.label, { class: 'ac-helper ac-bar-label' })); bars.append(row);
            });
            plot.append(bars); layout.append(plot);
        }
        const summary = make('div', undefined, { class: mode === 'cards' ? 'ac-separate-cards comparison-cards-grid' + (selected.length === 3 ? ' has-3-cards' : '') : 'ac-summary visual-comparison-details-pane', 'data-comparison-summary': '' });
        if (mode === 'overlay') summary.append(make('h3', 'Comparison summary'));
        selected.forEach((group, index) => {
            if (mode === 'cards') {
                const letter = 'abc'[index], card = make('div', undefined, { class: 'comparison-panel comparison-card panel-' + letter + ' card-' + letter + ' ac-group-summary' });
                const header = make('div', undefined, { class: 'panel-header' });
                const top = make('div', undefined, { class: 'ac-card-top' });
                top.append(make('span', 'Group ' + letter.toUpperCase(), { class: 'ac-group-tag' }), make('span', status(group), { class: 'card-status-badge ' + (group.provisional ? 'badge-provisional' : 'badge-complete') }));
                header.append(top, make('h4', group.label, { class: 'panel-title' }), make('p', dimensions[dimension] + ' · ' + (report.filters.school_year || 'All recorded years') + ' · ' + (report.filters.semester ? 'Semester ' + report.filters.semester : 'All recorded semesters'), { class: 'panel-sub' }));
                const metricBox = make('div', undefined, { class: 'panel-metric-box card-metric-box' });
                metricBox.append(make('div', metrics[metric], { class: 'panel-metric-label card-metric-label' }), make('div', valueText(group), { class: 'panel-metric-value card-metric-value', 'data-comparison-value': group.key }), make('div', metric === 'gwa' ? 'Pooled unit-weighted recorded-grade mean' : 'Recorded values for the selected scope', { class: 'panel-metric-sub card-metric-sub' }));
                const stats = make('dl', undefined, { class: 'panel-stats card-stats' });
                for (const [label, value] of [['Students (n)', String(group.student_count)], ['Numeric coverage', coverage(group)], ['Final coverage', Number.isFinite(group.final_coverage_pct) ? group.final_coverage_pct.toFixed(2) + '%' : 'Unavailable']]) {
                    const pair = make('div'); pair.append(make('dt', label), make('dd', value)); stats.append(pair);
                }
                card.append(header, metricBox, stats, make('p', 'Eligible credit units: ' + group.eligible_units + '. Recorded outcomes: ' + group.recorded_outcome_count + '.', { class: 'ac-helper' }));
                summary.append(card); return;
            }
            const item = make('div', undefined, { class: mode === 'cards' ? 'card ac-group-summary' : 'ac-group-summary' });
            item.append(make('h4', 'Group ' + 'ABC'[index] + ' · ' + group.label), make('strong', valueText(group), { 'data-comparison-value': group.key }), make('span', status(group), { class: 'ac-status' }));
            const dl = make('dl');
            for (const [label, text] of [['Sample', String(group.student_count)], ['Numeric coverage', coverage(group)]]) { const pair = make('div'); pair.append(make('dt', label), make('dd', text)); dl.append(pair); }
            item.append(dl); summary.append(item);
        });
        const a = rawValue(selected[0]), b = rawValue(selected[1]);
        const unit = metric === 'gwa' ? 'recorded-grade points' : metric === 'student_count' ? 'Students' : 'percentage points';
        const difference = a !== null && b !== null ? (a - b > 0 ? '+' : '') + (a - b).toFixed(2) + ' ' + unit : 'Unavailable';
        const differenceLine = make('p', 'Difference A − B: ' + difference, { class: 'ac-difference' });
        if (mode === 'overlay') summary.append(differenceLine);
        layout.append(summary); result.append(layout);
        if (mode === 'cards') result.append(differenceLine);
        result.append(make('p', 'Recorded group results provide academic context; differences do not independently measure teaching performance.', { class: 'ac-limitation' }));
        const evidence = make('details', undefined, { class: 'card ac-evidence' }); evidence.append(make('summary', 'View exact evidence'));
        const scroll = make('div', undefined, { class: 'data-table-scroll', role: 'region', 'aria-label': 'Exact comparison evidence', tabindex: '0' });
        const table = make('table'), head = make('thead'), header = make('tr'), body = make('tbody');
        table.append(make('caption', 'Exact values for selected academic groups'));
        for (const label of ['Group', 'Scope', 'Period', 'Metric', 'Value', 'Sample', 'Coverage', 'Status']) header.append(make('th', label, { scope: 'col' }));
        head.append(header); table.append(head);
        const period = [report.filters.school_year || 'All recorded school years', report.filters.semester ? 'Semester ' + report.filters.semester : 'All recorded semesters'].join(' · ');
        selected.forEach((group, index) => {
            const row = make('tr'); row.append(make('th', 'Group ' + 'ABC'[index] + ' · ' + group.label, { scope: 'row' }));
            [dimensions[dimension], period, metrics[metric], valueText(group), String(group.student_count), coverage(group), status(group)].forEach(text => row.append(make('td', text))); body.append(row);
        });
        table.append(body); scroll.append(table); evidence.append(scroll); result.append(evidence);
        const help = make('details', undefined, { class: 'ac-explanation' }); help.append(make('summary', 'How to interpret and calculate these results'));
        help.append(make('p', 'Values use the existing pooled eligible final points × credit units / eligible credit units. Difference is Group A minus Group B. Missing values remain unavailable; provisional records may change.'), make('p', report.fairness)); result.append(help);
    }
    window.UDMAdminComparisons = {
        update(data, submit) {
            report = data; request = submit; busy = false; failed = false;
            const series = data.trend.series;
            document.getElementById('ac-history-summary').textContent = 'Historical context · ' + series.length + ' recorded terms' + (series.length ? ' · ' + series[0].label + ' to ' + series.at(-1).label : '');
            draw();
        },
        loading() { busy = true; failed = false; groupFields.forEach(item => { item.select.disabled = true; }); drawResult(); },
        failure() { busy = false; failed = true; drawResult(); }
    };
})();
