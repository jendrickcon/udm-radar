/* Admin presentation only. Academic aggregates and ownership remain in the report API. */
(() => {
    'use strict';
    const root = document.getElementById('admin-comparisons');
    if (!root) return;
    const dimensions = { school_year: 'School year', semester: 'Semester', year_level: 'Curriculum year', subject: 'Subject', section: 'Section', faculty_subject: 'Faculty assignment' };
    const metrics = { gwa: 'Recorded-grade average', student_count: 'Student count', numeric_coverage_pct: 'Numeric grade coverage', final_coverage_pct: 'Final-outcome coverage', prediction_coverage_pct: 'Prediction coverage', risk_distribution: 'Advisory-risk distribution' };
    const helpers = { school_year: 'Compare recorded school-year groups.', semester: 'Compare recorded terms in chronological order.', year_level: 'Compare subject curriculum years, not Student year-level history.', subject: 'Compare subject records for context; units and difficulty may differ.', section: 'Compare verified current section records.', faculty_subject: 'Choose a common school year, semester and subject before selecting verified Faculty assignments.' };
    const glyphs = { A: '●', B: '■', C: '◆' };
    const make = (tag, text, attrs = {}) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value); return node; };
    const controls = document.getElementById('ac-controls'), notice = document.getElementById('ac-notice'), result = document.getElementById('ac-result');
    let report, request, dimension = 'semester', metric = 'gwa', mode = 'overlay', keys = ['', ''], busy = false, failed = false;
    let chartDisplayMode = 'side-by-side', highlightedGroup = 'all';

    const filterAnnouncer = make('span', '', { class: 'sr-only', role: 'status', 'aria-live': 'polite', id: 'ac-filter-announcer' });
    controls.append(filterAnnouncer);
    const announceFilter = (msg) => { if (filterAnnouncer) filterAnnouncer.textContent = msg; };

    const field = (id, label) => { const box = make('div', undefined, { class: 'ac-field' }); const select = make('select', undefined, { id }); box.append(make('label', label, { for: id }), select); return { box, select }; };
    const scope = make('div', undefined, { role: 'group', 'aria-label': 'Compare by', class: 'ac-dimensions' });
    for (const [key, label] of Object.entries(dimensions)) {
        const button = make('button', label, { type: 'button', class: 'btn btn--secondary', 'data-dimension': key, 'aria-pressed': String(key === dimension) });
        button.addEventListener('click', () => {
            dimension = key; keys = ['', '']; highlightedGroup = 'all';
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
        common[key].select.addEventListener('change', () => { keys = ['', '']; highlightedGroup = 'all'; request?.(parameters()); });
    }
    const clear = make('button', 'Clear scope', { type: 'button', class: 'btn btn--quiet' });
    clear.addEventListener('click', () => { keys = ['', '']; highlightedGroup = 'all'; request?.(new URLSearchParams()); });
    controls.append(make('h3', '2. Common scope and period'), commonRow, clear);
    const groups = make('div', undefined, { class: 'ac-control-grid', id: 'ac-groups' });
    const groupFields = [];
    for (const letter of ['A', 'B', 'C']) {
        const item = field('ac-group-' + letter.toLowerCase(), 'Group ' + letter);
        item.box.hidden = letter === 'C'; groups.append(item.box); groupFields.push(item);
        item.select.addEventListener('change', () => {
            const idx = ['A', 'B', 'C'].indexOf(letter);
            keys[idx] = item.select.value;
            highlightedGroup = 'all';
            if (idx === 0) {
                draw();
            } else if (idx === 1 && keys.length === 3) {
                draw();
            } else {
                drawResult();
            }
        });
    }
    const groupActions = make('div', undefined, { class: 'ac-group-actions' });
    const add = make('button', '+ Add Group C', { type: 'button', class: 'btn btn--secondary', 'aria-describedby': 'ac-group-limit' });
    const remove = make('button', 'Remove Group C', { type: 'button', class: 'btn btn--quiet', hidden: '' });
    const limit = make('span', '', { id: 'ac-group-limit', class: 'ac-helper', role: 'status' });
    add.addEventListener('click', () => { if (keys.length === 3) return; keys.push(''); highlightedGroup = 'all'; draw(); groupFields[2].select.focus(); });
    remove.addEventListener('click', () => { keys = keys.slice(0, 2); highlightedGroup = 'all'; draw(); add.focus(); });
    groupActions.append(add, remove, limit); controls.append(make('h3', '3. Select groups'), groups, groupActions);
    const finalRow = make('div', undefined, { class: 'ac-final-controls' });
    const metricField = field('ac-metric', 'Metric');
    for (const [value, label] of Object.entries(metrics)) metricField.select.append(make('option', label, { value }));
    metricField.select.addEventListener('change', () => { metric = metricField.select.value; highlightedGroup = 'all'; drawResult(); });
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
        if (dimension === 'faculty_subject') {
            if (!report || !report.filters.school_year || !report.filters.semester || !report.filters.subject) return [];
            return list.filter(group => /^\d+\|\d+$/.test(group.key) && group.key.split('|')[1] === report.filters.subject && !/Historical|Assignment unavailable/.test(group.label) && (group.student_count > 0 || group.recorded_outcome_count > 0));
        }
        return list.filter(group => !/Historical|Assignment unavailable/.test(group.label) && (group.student_count > 0 || group.recorded_outcome_count > 0));
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

        // Progressive candidate filtering: Group A gets full choices
        if (!choices.some(group => group.key === keys[0])) {
            keys[0] = choices[0]?.key || '';
        }

        // Group B options exclude Group A's selected assignment
        const choicesB = choices.filter(group => group.key !== keys[0]);
        if (!choicesB.some(group => group.key === keys[1])) {
            const oldB = keys[1];
            keys[1] = choicesB[0]?.key || '';
            if (oldB && oldB !== keys[1]) {
                announceFilter('Group B was updated because Group A changed.');
            }
        }

        // Group C options exclude both Group A and Group B
        if (keys.length === 3) {
            const choicesC = choices.filter(group => group.key !== keys[0] && group.key !== keys[1]);
            if (!choicesC.some(group => group.key === keys[2])) {
                keys[2] = choicesC[0]?.key || '';
            }
        }

        groupFields.forEach((item, index) => {
            item.box.hidden = index >= keys.length;
            let groupChoices = choices;
            if (index === 1) {
                groupChoices = choices.filter(group => group.key !== keys[0]);
            } else if (index === 2) {
                groupChoices = choices.filter(group => group.key !== keys[0] && group.key !== keys[1]);
            }
            options(item.select, groupChoices, keys[index], 'Choose a group');
            item.select.disabled = busy || !groupChoices.length;
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
        if (dimension === 'faculty_subject' && choices.length < 2) return ['No comparable assignment is available. No common verified subject assignment is available for the selected Faculty members and period.', common.subject.select];
        if (choices.length < 2) return ['No comparable assignment is available. The selected groups do not share an eligible subject and academic period.', groupFields[0].select];
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

    // Label collision layout function
    function computeLabelLayout({ selected, max, stageWidth, mode }) {
        if (mode !== 'overlay') return { strategy: 'standard', items: [] };
        const items = selected.map((group, index) => {
            const letter = 'ABC'[index];
            const raw = rawValue(group);
            const heightPct = raw !== null ? Math.max(0, Math.min(100, (raw / max) * 100)) : 0;
            return { letter, group, raw, heightPct, index };
        });

        const isConstrained = stageWidth <= 480 || (typeof window !== 'undefined' && window.innerWidth <= 1040);
        const anyNearTop = items.some(it => it.heightPct > 82);

        let closeValues = false;
        for (let i = 0; i < items.length; i++) {
            for (let j = i + 1; j < items.length; j++) {
                if (items[i].raw !== null && items[j].raw !== null && Math.abs(items[i].raw - items[j].raw) <= 0.35) {
                    closeValues = true;
                }
            }
        }

        if (isConstrained || (anyNearTop && closeValues)) {
            return {
                strategy: 'compact-top-legend',
                items: items.map(it => ({ ...it, lane: 0, offsetY: 0 }))
            };
        }

        const laneOffsets2 = [32, 10];
        const laneOffsets3 = [40, 22, 6];
        const offsets = items.length === 3 ? laneOffsets3 : laneOffsets2;

        return {
            strategy: 'staggered-lanes',
            items: items.map((it, idx) => ({
                ...it,
                lane: idx,
                offsetY: offsets[idx] || 10
            }))
        };
    }

    function drawResult(focusInvalid = false) {
        modes.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mode === mode)));
        modeHelp.textContent = mode === 'overlay' ? 'Show selected groups together on one scale.' : 'Show complete details for each group.';
        controls.querySelectorAll('[aria-invalid]').forEach(control => { control.removeAttribute('aria-invalid'); control.removeAttribute('aria-describedby'); });
        result.replaceChildren(); const error = validation();
        notice.hidden = !error; notice.textContent = error?.[0] || ''; notice.setAttribute('role', focusInvalid ? 'alert' : 'status');
        if (error) {
            if (focusInvalid && error[1]) { error[1].setAttribute('aria-invalid', 'true'); error[1].setAttribute('aria-describedby', 'ac-notice'); error[1].focus(); }
            if (error[0]?.includes('No comparable assignment is available')) {
                const emptyCard = make('div', undefined, { class: 'card ac-empty-intersection', role: 'region', 'aria-label': 'No comparable assignment available' });
                const emptyHead = make('div', undefined, { class: 'ac-empty-header' });
                emptyHead.append(
                    make('h4', 'No comparable assignment is available.'),
                    make('p', 'The selected groups do not share an eligible subject and academic period.', { class: 'ac-helper' })
                );
                const emptyActions = make('div', undefined, { class: 'ac-empty-actions' });
                const bGroups = make('button', 'Change selected groups', { type: 'button', class: 'btn btn--secondary' });
                bGroups.addEventListener('click', () => { groupFields[0].select.focus(); });
                const bDim = make('button', 'Change comparison dimension', { type: 'button', class: 'btn btn--secondary' });
                bDim.addEventListener('click', () => { (scope.querySelector('[data-dimension="' + dimension + '"]') || scope.querySelector('button'))?.focus(); });
                const bClear = make('button', 'Clear comparison', { type: 'button', class: 'btn btn--quiet' });
                bClear.addEventListener('click', () => { clear.click(); });
                emptyActions.append(bGroups, bDim, bClear);
                emptyCard.append(emptyHead, emptyActions);
                result.append(emptyCard);
            }
            return;
        }
        const selected = keys.map(key => candidates().find(group => group.key === key));
        const valuesAvailable = selected.some(group => rawValue(group) !== null);
        if (!valuesAvailable) { notice.hidden = false; notice.textContent = 'No recorded values are available for this metric and scope. Review coverage or choose another metric.'; }
        if (highlightedGroup !== 'all' && !['A', 'B', ...(selected.length === 3 ? ['C'] : [])].includes(highlightedGroup)) {
            highlightedGroup = 'all';
        }

        const a = rawValue(selected[0]), b = rawValue(selected[1]);
        const unit = metric === 'gwa' ? 'recorded-grade points' : metric === 'student_count' ? 'Students' : 'percentage points';
        const difference = a !== null && b !== null ? (a - b > 0 ? '+' : '') + (a - b).toFixed(2) + ' ' + unit : 'Unavailable';
        const signedDiff = a !== null && b !== null ? a - b : null;

        const layout = make('div', undefined, {
            class: mode === 'overlay' ? 'card visual-comparison-card visual-comparison-layout ac-result-layout' : 'ac-result-layout',
            'data-view': mode,
            'data-highlighted': highlightedGroup,
            'data-chart-display': chartDisplayMode
        });

        const updateHighlight = (letter) => {
            highlightedGroup = letter;
            layout.setAttribute('data-highlighted', letter);
            const tb = layout.querySelector('[data-role="highlight-controls"]');
            if (tb) {
                tb.querySelectorAll('[data-group-select]').forEach(btn => {
                    btn.setAttribute('aria-pressed', String(btn.dataset.groupSelect === letter));
                });
                const live = tb.querySelector('#ac-highlight-announcement');
                if (live) {
                    live.textContent = letter === 'all'
                        ? (selected.length === 3 ? 'All groups displayed.' : 'Both groups displayed.')
                        : 'Group ' + letter + ' highlighted.';
                }
            }
            renderSummary();
        };

        const updateDisplayMode = (newMode) => {
            chartDisplayMode = newMode;
            drawResult();
        };

        const renderSummary = () => {
            const summary = layout.querySelector('[data-comparison-summary]');
            if (!summary) return;
            summary.replaceChildren();
            if (mode === 'overlay') summary.append(make('h3', 'Comparison summary'));

            if (highlightedGroup === 'all') {
                selected.forEach((group, index) => {
                    const letter = 'ABC'[index];
                    const item = make('div', undefined, { class: 'ac-group-summary ac-summary-item ac-item-' + letter.toLowerCase(), role: 'button', tabindex: '0' });
                    item.addEventListener('click', () => updateHighlight(letter));
                    item.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(letter); } });

                    const header = make('h4', undefined);
                    header.append(make('span', glyphs[letter], { class: 'ac-marker-glyph ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }), ' Group ' + letter + ' · ' + group.label);
                    item.append(header, make('strong', valueText(group), { 'data-comparison-value': group.key }), make('span', status(group), { class: 'ac-status' }));
                    const dl = make('dl');
                    for (const [l, text] of [['Sample', String(group.student_count)], ['Numeric coverage', coverage(group)]]) {
                        const pair = make('div'); pair.append(make('dt', l), make('dd', text)); dl.append(pair);
                    }
                    item.append(dl); summary.append(item);
                });
            } else {
                const targetIdx = ['A', 'B', 'C'].indexOf(highlightedGroup);
                const targetGroup = selected[targetIdx];
                const otherIdx = targetIdx === 0 ? 1 : 0;
                const otherGroup = selected[otherIdx];
                const otherLetter = targetIdx === 0 ? 'B' : 'A';

                const item = make('div', undefined, { class: 'ac-group-summary ac-highlighted-summary ac-summary-' + highlightedGroup.toLowerCase() });
                const pill = make('div', undefined, { class: 'ac-highlight-pill' });
                pill.append(make('span', glyphs[highlightedGroup], { class: 'ac-marker-glyph ac-glyph-' + highlightedGroup.toLowerCase(), 'aria-hidden': 'true' }), ' Group ' + highlightedGroup + ' highlighted');
                item.append(pill);
                item.append(make('h4', 'Group ' + highlightedGroup + ' · ' + targetGroup.label, { class: 'ac-highlight-title' }));

                const metricBox = make('div', undefined, { class: 'ac-highlight-metric-box' });
                metricBox.append(
                    make('strong', valueText(targetGroup), { 'data-comparison-value': targetGroup.key, class: 'ac-highlight-metric-value' }),
                    make('span', status(targetGroup), { class: 'card-status-badge ' + (targetGroup.provisional ? 'badge-provisional' : 'badge-complete') })
                );
                item.append(metricBox);
                item.append(make('p', metric === 'gwa' ? 'Pooled unit-weighted recorded-grade mean' : metrics[metric] + ' for selected group', { class: 'ac-helper' }));

                const dl = make('dl', undefined, { class: 'ac-highlight-stats' });
                for (const [l, text] of [
                    ['Sample (n)', String(targetGroup.student_count)],
                    ['Numeric coverage', coverage(targetGroup)],
                    ['Final coverage', Number.isFinite(targetGroup.final_coverage_pct) ? targetGroup.final_coverage_pct.toFixed(2) + '%' : 'Unavailable']
                ]) {
                    const pair = make('div'); pair.append(make('dt', l), make('dd', text)); dl.append(pair);
                }
                item.append(dl);

                if (highlightedGroup === 'A' || highlightedGroup === 'B') {
                    const rawTarget = rawValue(targetGroup), rawOther = rawValue(otherGroup);
                    if (rawTarget !== null && rawOther !== null) {
                        const delta = rawTarget - rawOther;
                        let relText = '';
                        if (delta > 0.0001) relText = delta.toFixed(2) + ' ' + unit + ' higher than Group ' + otherLetter;
                        else if (delta < -0.0001) relText = Math.abs(delta).toFixed(2) + ' ' + unit + ' lower than Group ' + otherLetter;
                        else relText = 'Identical to Group ' + otherLetter;
                        item.append(make('div', relText, { class: 'ac-relational-callout' }));
                    }
                } else if (highlightedGroup === 'C') {
                    item.append(make('div', 'Difference compares Group A and Group B. Group C is shown for context.', { class: 'ac-relational-callout ac-relational-context' }));
                }

                const counterpart = make('div', undefined, { class: 'ac-counterpart-card', role: 'button', tabindex: '0' });
                counterpart.append(
                    make('span', 'Compared with Group ' + otherLetter + ': ', { class: 'ac-helper' }),
                    make('strong', valueText(otherGroup), { class: 'ac-counterpart-val' }),
                    make('span', ' (' + otherGroup.label + ')', { class: 'ac-helper' })
                );
                counterpart.addEventListener('click', () => updateHighlight(otherLetter));
                counterpart.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(otherLetter); } });
                item.append(counterpart);
                summary.append(item);
            }

            const differenceLine = make('p', 'Difference A − B: ' + difference, { class: 'ac-difference' });
            if (selected.length === 3) {
                differenceLine.append(make('span', ' · Group C is shown for context.', { class: 'ac-helper ac-c-context' }));
            }
            summary.append(differenceLine);
        };

        if (valuesAvailable && mode === 'overlay') {
            const plot = make('div', undefined, { class: 'ac-plot visual-comparison-chart-pane', 'data-comparison-chart': '' });
            plot.append(make('h3', 'Visual comparison'));
            const max = metric === 'gwa' ? 4 : metric.endsWith('_pct') ? 100 : Math.max(1, ...selected.map(group => rawValue(group) || 0));
            plot.append(make('p', metrics[metric] + ' · shared scale 0–' + max, { class: 'ac-helper' }));

            // Dual Toolbars: Highlight on left, Display on right
            const toolbarsRow = make('div', undefined, { class: 'ac-chart-toolbar-row' });

            const highlightGroup = make('div', undefined, { class: 'ac-toolbar-group', 'data-role': 'highlight-controls', role: 'group', 'aria-label': 'Highlight comparison group' });
            highlightGroup.append(make('span', 'Highlight:', { class: 'ac-toolbar-label' }));
            const letters = ['A', 'B', ...(selected.length === 3 ? ['C'] : [])];
            letters.forEach(letter => {
                const btn = make('button', undefined, {
                    type: 'button',
                    class: 'btn btn--secondary ac-toolbar-btn',
                    'data-group-select': letter,
                    'aria-pressed': String(highlightedGroup === letter)
                });
                btn.append(make('span', glyphs[letter], { class: 'ac-marker-glyph ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }), ' Group ' + letter);
                btn.addEventListener('click', () => updateHighlight(letter));
                highlightGroup.append(btn);
            });
            const showBothBtn = make('button', selected.length === 3 ? 'Show all' : 'Show both', {
                type: 'button',
                class: 'btn btn--secondary ac-toolbar-btn',
                'data-group-select': 'all',
                'aria-pressed': String(highlightedGroup === 'all')
            });
            showBothBtn.addEventListener('click', () => updateHighlight('all'));
            highlightGroup.append(showBothBtn);

            const liveAnnouncement = make('span', highlightedGroup === 'all' ? (selected.length === 3 ? 'All groups displayed.' : 'Both groups displayed.') : `Group ${highlightedGroup} highlighted.`, {
                class: 'sr-only',
                role: 'status',
                'aria-live': 'polite',
                id: 'ac-highlight-announcement'
            });
            highlightGroup.append(liveAnnouncement);
            toolbarsRow.append(highlightGroup);

            const displayGroup = make('div', undefined, { class: 'ac-toolbar-group', 'data-role': 'display-controls', role: 'group', 'aria-label': 'Chart display style' });
            displayGroup.append(make('span', 'Display:', { class: 'ac-toolbar-label' }));
            [['side-by-side', 'Side by side'], ['overlay', 'Overlay']].forEach(([dMode, dLabel]) => {
                const dBtn = make('button', dLabel, {
                    type: 'button',
                    class: 'btn btn--secondary ac-toolbar-btn',
                    'data-display-select': dMode,
                    'aria-pressed': String(chartDisplayMode === dMode)
                });
                dBtn.addEventListener('click', () => updateDisplayMode(dMode));
                displayGroup.append(dBtn);
            });
            toolbarsRow.append(displayGroup);
            plot.append(toolbarsRow);

            // Recognizable Vertical Bar Comparison Chart Card
            const chartCard = make('div', undefined, { class: 'ac-vertical-chart-card', role: 'region', 'aria-label': 'Vertical bar comparison chart' });
            const chartStage = make('div', undefined, { class: 'ac-chart-stage' });

            // Y-Axis and Ticks
            const yAxis = make('div', undefined, { class: 'ac-chart-y-axis', 'aria-hidden': 'true' });
            let yTicks = [4, 3, 2, 1, 0];
            if (metric.endsWith('_pct')) yTicks = [100, 75, 50, 25, 0];
            else if (metric === 'student_count') yTicks = [max, Math.round(max * 0.75), Math.round(max * 0.5), Math.round(max * 0.25), 0];

            yTicks.forEach(tickVal => {
                const tickPct = (tickVal / max) * 100;
                const tickNode = make('div', undefined, { class: 'ac-y-tick', style: 'top: ' + (100 - tickPct) + '%;' });
                let tickLabel = metric === 'gwa' ? tickVal.toFixed(2) : metric.endsWith('_pct') ? tickVal + '%' : String(tickVal);
                if (metric === 'gwa' && tickVal === 1) tickLabel = '1.00';
                tickNode.append(make('span', tickLabel, { class: 'ac-y-tick-label' }));
                yAxis.append(tickNode);

                if (tickPct > 0) {
                    chartStage.append(make('div', undefined, { class: 'ac-chart-gridline', style: 'top: ' + (100 - tickPct) + '%;' }));
                }
            });
            chartStage.append(make('div', undefined, { class: 'ac-chart-baseline' }));
            chartStage.append(yAxis);

            const layoutConfig = computeLabelLayout({
                selected,
                max,
                stageWidth: chartStage.clientWidth || (typeof window !== 'undefined' ? window.innerWidth * 0.5 : 600),
                mode: chartDisplayMode
            });

            if (chartDisplayMode === 'overlay' && layoutConfig.strategy === 'compact-top-legend') {
                const compactLegend = make('div', undefined, { class: 'ac-compact-top-legend', role: 'group', 'aria-label': 'Current group values' });
                layoutConfig.items.forEach(it => {
                    const pill = make('div', undefined, {
                        class: 'ac-compact-val-pill ac-compact-' + it.letter.toLowerCase(),
                        'data-group': it.letter,
                        role: 'button',
                        tabindex: '0'
                    });
                    pill.append(
                        make('span', glyphs[it.letter], { class: 'ac-marker-glyph ac-glyph-' + it.letter.toLowerCase(), 'aria-hidden': 'true' }),
                        ' Group ' + it.letter + ': ',
                        make('strong', valueText(it.group), { class: 'ac-bar-val-badge' })
                    );
                    pill.addEventListener('click', () => updateHighlight(it.letter));
                    pill.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(it.letter); } });
                    compactLegend.append(pill);
                });
                chartStage.append(compactLegend);
            }

            // Plot Area with Vertical Bars
            const plotArea = make('div', undefined, {
                class: 'ac-chart-plot-area ac-plot-' + chartDisplayMode + (selected.length === 3 ? ' has-3-groups' : ' has-2-groups')
            });

            selected.forEach((group, index) => {
                const letter = 'ABC'[index];
                const raw = rawValue(group);
                const col = make('div', undefined, {
                    class: 'ac-bar-column ac-col-' + letter.toLowerCase(),
                    'data-group': letter
                });

                if (raw !== null) {
                    const heightPct = Math.max(0, Math.min(100, (raw / max) * 100));

                    // Dotted guideline to Y-axis
                    chartStage.append(make('div', undefined, {
                        class: 'ac-bar-guideline ac-guide-' + letter.toLowerCase(),
                        style: 'bottom: ' + heightPct + '%;'
                    }));

                    // Top label with value badge
                    if (chartDisplayMode === 'overlay' && layoutConfig.strategy === 'compact-top-legend') {
                        const topInfo = make('div', undefined, {
                            class: 'ac-bar-top-info ac-top-compact',
                            style: 'bottom: ' + heightPct + '%;'
                        });
                        topInfo.append(
                            make('span', glyphs[letter], { class: 'ac-bar-glyph-icon ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' })
                        );
                        col.append(topInfo);
                    } else if (chartDisplayMode === 'overlay' && layoutConfig.strategy === 'staggered-lanes') {
                        const itLayout = layoutConfig.items[index] || { lane: index, offsetY: 10 };
                        const topInfo = make('div', undefined, {
                            class: 'ac-bar-top-info ac-overlay-staggered ac-lane-' + itLayout.lane,
                            style: 'bottom: calc(' + heightPct + '% + ' + itLayout.offsetY + 'px);'
                        });
                        topInfo.append(
                            make('span', glyphs[letter], { class: 'ac-bar-glyph-icon ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }),
                            make('span', valueText(group), { class: 'ac-bar-val-badge' }),
                            make('span', undefined, { class: 'ac-label-connector', style: 'height: ' + itLayout.offsetY + 'px;' })
                        );
                        col.append(topInfo);
                    } else {
                        const topInfo = make('div', undefined, {
                            class: 'ac-bar-top-info',
                            style: 'bottom: ' + heightPct + '%;'
                        });
                        topInfo.append(
                            make('span', glyphs[letter], { class: 'ac-bar-glyph-icon ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }),
                            make('span', valueText(group), { class: 'ac-bar-val-badge' })
                        );
                        col.append(topInfo);
                    }

                    // The Vertical Bar
                    const bar = make('div', undefined, {
                        class: 'ac-vertical-bar ac-bar ac-bar-color-' + index,
                        style: 'height: ' + heightPct + '%;',
                        'data-value': raw,
                        role: 'button',
                        tabindex: '0',
                        'aria-label': 'Group ' + letter + ': ' + group.label + ', ' + valueText(group)
                    });
                    bar.addEventListener('click', () => updateHighlight(letter));
                    bar.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(letter); } });
                    col.append(bar);
                } else {
                    const topInfo = make('div', undefined, {
                        class: 'ac-bar-top-info',
                        style: 'bottom: 20px;'
                    });
                    topInfo.append(make('span', 'Unavailable', { class: 'ac-bar-val-badge' }));
                    col.append(topInfo);

                    const bar = make('div', undefined, {
                        class: 'ac-vertical-bar ac-bar ac-bar-unavailable',
                        style: 'height: 18px;',
                        'data-value': 0,
                        role: 'button',
                        tabindex: '0',
                        'aria-label': 'Group ' + letter + ': ' + group.label + ', Unavailable'
                    });
                    col.append(bar);
                }

                // Column Bottom Labels
                const bottomLabel = make('div', undefined, { class: 'ac-bar-bottom-label', role: 'button', tabindex: '0' });
                if (chartDisplayMode === 'overlay') {
                    bottomLabel.append(make('span', 'Group ' + letter));
                } else {
                    bottomLabel.append(
                        make('span', 'Group ' + letter),
                        make('span', group.label, { class: 'ac-bar-sub-label' })
                    );
                }
                bottomLabel.addEventListener('click', () => updateHighlight(letter));
                bottomLabel.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(letter); } });
                col.append(bottomLabel);

                plotArea.append(col);
            });
            chartStage.append(plotArea);
            chartCard.append(chartStage);

            // Chart Difference Annotation
            const diffAnnotation = make('div', undefined, { class: 'ac-chart-diff-annotation' });
            diffAnnotation.append(
                make('span', 'Difference A − B:'),
                make('span', difference, { class: 'ac-diff-pill' })
            );
            if (selected.length === 3) {
                diffAnnotation.append(make('span', '(Group C contextual)', { class: 'ac-helper' }));
            }
            chartCard.append(diffAnnotation);

            // Interactive Legend
            const legend = make('div', undefined, { class: 'ac-chart-legend', role: 'list', 'aria-label': 'Chart legend' });
            selected.forEach((group, index) => {
                const letter = 'ABC'[index];
                const legItem = make('div', undefined, {
                    class: 'ac-legend-item ac-legend-' + letter.toLowerCase(),
                    role: 'button',
                    tabindex: '0',
                    'data-group': letter,
                    'aria-label': 'Highlight Group ' + letter + ': ' + group.label
                });
                legItem.append(
                    make('span', glyphs[letter], { class: 'ac-marker-glyph ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }),
                    ' Group ' + letter + ': ' + group.label + ' (' + valueText(group) + ')' + (index === 2 ? ' · Context only' : '')
                );
                legItem.addEventListener('click', () => updateHighlight(letter));
                legItem.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(letter); } });
                legend.append(legItem);
            });
            chartCard.append(legend);
            plot.append(chartCard);
            layout.append(plot);
        }

        const summary = make('div', undefined, {
            class: mode === 'cards' ? 'ac-separate-cards comparison-cards-grid' + (selected.length === 3 ? ' has-3-cards' : '') : 'ac-summary visual-comparison-details-pane',
            'data-comparison-summary': ''
        });

        if (mode === 'cards') {
            selected.forEach((group, index) => {
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
                summary.append(card);
            });
            layout.append(summary);
            result.append(layout);
            const cardsDifferenceLine = make('p', 'Difference A − B: ' + difference, { class: 'ac-difference' });
            if (selected.length === 3) {
                cardsDifferenceLine.append(make('span', ' · Group C is shown for context.', { class: 'ac-helper ac-c-context' }));
            }
            result.append(cardsDifferenceLine);
        } else {
            layout.append(summary);
            renderSummary();
            result.append(layout);
        }

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
