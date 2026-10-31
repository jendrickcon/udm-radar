/* Admin presentation only. Academic aggregates and ownership remain in the report API. */
(() => {
    'use strict';
    const root = document.getElementById('admin-comparisons');
    if (!root) return;
    const dimensions = { school_year: 'School year', semester: 'Semester', year_level: 'Curriculum year', subject: 'Subject', section: 'Section', faculty_subject: 'Faculty assignment' };
    const metrics = {
        student_count: 'Student count',
        prelim_avg: 'Preliminary score average',
        prelim_coverage_pct: 'Preliminary score coverage',
        midterm_avg: 'Midterm score average',
        midterm_coverage_pct: 'Midterm score coverage',
        gwa: 'Recorded-grade average',
        numeric_coverage_pct: 'Final numeric-grade coverage',
        final_coverage_pct: 'Final-outcome coverage',
        prediction_coverage_pct: 'Prediction coverage',
        risk_distribution: 'Advisory-risk distribution'
    };
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
        common[key].select.addEventListener('change', () => {
            if (dimension === 'faculty_subject') {
                if (key === 'school_year') {
                    common.subject.select.value = ''; keys = ['', '']; highlightedGroup = 'all';
                    announceFilter('Common school year updated; dependent subject and Faculty selections cleared.');
                } else if (key === 'semester') {
                    common.subject.select.value = ''; keys = ['', '']; highlightedGroup = 'all';
                    announceFilter('Common semester updated; dependent subject and Faculty selections cleared.');
                } else if (key === 'subject') {
                    keys = ['', '']; highlightedGroup = 'all';
                    const compSubjs = report?.comparable_subjects || [];
                    const sel = compSubjs.find(s => String(s.subject_id) === String(common.subject.select.value));
                    if (sel) {
                        announceFilter(`${sel.code} has ${sel.distinct_faculty_count} comparable Faculty groups across ${sel.eligible_section_count} sections for ${sel.school_year} Semester ${sel.semester}.`);
                    }
                }
            } else {
                keys = ['', '']; highlightedGroup = 'all';
            }
            request?.(parameters());
        });
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
    const metricGroups = [
        {
            label: 'Current-term evidence',
            items: [
                { value: 'student_count', label: 'Student count' },
                { value: 'prelim_avg', label: 'Preliminary score average' },
                { value: 'prelim_coverage_pct', label: 'Preliminary score coverage' },
                { value: 'midterm_avg', label: 'Midterm score average' },
                { value: 'midterm_coverage_pct', label: 'Midterm score coverage' }
            ]
        },
        {
            label: 'Finalized outcomes',
            items: [
                { value: 'gwa', label: 'Recorded-grade average' },
                { value: 'numeric_coverage_pct', label: 'Final numeric-grade coverage' },
                { value: 'final_coverage_pct', label: 'Final-outcome coverage' },
                { value: 'prediction_coverage_pct', label: 'Prediction coverage' },
                { value: 'risk_distribution', label: 'Advisory-risk distribution' }
            ]
        }
    ];
    for (const grp of metricGroups) {
        const og = make('optgroup', undefined, { label: grp.label });
        for (const item of grp.items) {
            og.append(make('option', item.label, { value: item.value }));
        }
        metricField.select.append(og);
    }
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
            return list.filter(group => /^\d+\|\d+$/.test(group.key) && String(group.key.split('|')[1]) === String(report.filters.subject) && !/Historical|Assignment unavailable/.test(group.label) && (group.student_count > 0 || group.recorded_outcome_count > 0));
        }
        return list.filter(group => !/Historical|Assignment unavailable/.test(group.label) && (group.student_count > 0 || group.recorded_outcome_count > 0));
    }
    function options(select, list, current, prompt) {
        select.replaceChildren(make('option', prompt, { value: '' }));
        list.forEach(item => select.append(make('option', item.label, { value: String(item.value ?? item.key ?? '') })));
        select.value = String(current || '');
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
            if (dimension === 'faculty_subject' && name === 'subject') {
                const compSubjects = report?.comparable_subjects || [];
                if (compSubjects.length > 0) {
                    options(item.select, compSubjects, report?.filters[name] || '', 'Choose a comparable subject');
                    item.select.disabled = false;
                    const hasPeriod = Boolean(report?.filters?.school_year && report?.filters?.semester);
                    if (hasPeriod && compSubjects.length === 1 && !report?.filters?.subject && !item.select.value) {
                        const singleSubject = compSubjects[0];
                        const singleSubjectId = String(singleSubject.subject_id);
                        item.select.value = singleSubjectId;
                        if (report?.filters) report.filters.subject = singleSubjectId;
                        announceFilter(`${singleSubject.code} was selected automatically because it is the only comparable subject for this period.`);
                        busy = true;
                        notice.hidden = false;
                        notice.textContent = `Loading Faculty groups for ${singleSubject.code}…`;
                        notice.setAttribute('role', 'status');
                        request?.(parameters());
                        return;
                    }
                } else {
                    item.select.replaceChildren(make('option', 'No comparable subject for this period', { value: '' }));
                    item.select.disabled = true;
                }
            } else {
                options(item.select, report?.options[name] || [], report?.filters[name] || '', dimension === 'faculty_subject' ? 'Choose ' + name.replace('_', ' ') : 'All recorded values');
            }
        }
        const choices = candidates();

        if (dimension === 'faculty_subject' && choices.length === 2) {
            keys[0] = choices[0].key;
            keys[1] = choices[1].key;
            if (keys.length > 2) keys = keys.slice(0, 2);
        } else {
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
        }

        groupFields.forEach((item, index) => {
            item.box.hidden = index >= keys.length;
            let groupChoices = choices;
            if (index === 0 && keys[1]) {
                groupChoices = choices.filter(group => group.key !== keys[1] || group.key === keys[0]);
            } else if (index === 1) {
                groupChoices = choices.filter(group => group.key !== keys[0] || group.key === keys[1]);
            } else if (index === 2) {
                groupChoices = choices.filter(group => (group.key !== keys[0] && group.key !== keys[1]) || group.key === keys[2]);
            }
            if (dimension === 'faculty_subject') {
                const mapped = groupChoices.map(g => ({
                    value: g.key,
                    label: g.faculty_label || g.label
                }));
                options(item.select, mapped, keys[index], 'Choose a group');
            } else {
                options(item.select, groupChoices, keys[index], 'Choose a group');
            }
            item.select.disabled = busy || !groupChoices.length;
        });

        add.disabled = keys.length === 3 || choices.length <= keys.length; remove.hidden = keys.length !== 3;
        limit.textContent = keys.length === 3 ? 'Maximum three groups.' : choices.length <= keys.length ? (dimension === 'faculty_subject' && choices.length === 2 ? 'Two comparable Faculty groups are available.' : 'No additional recorded group is available.') : 'Optional; up to three groups.';
        metricField.select.querySelectorAll('option').forEach(option => { option.disabled = ['prediction_coverage_pct', 'risk_distribution'].includes(option.value) && !['school_year', 'semester', 'section'].includes(dimension); });

        const optMidtermAvg = metricField.select.querySelector('option[value="midterm_avg"]');
        const optMidtermCov = metricField.select.querySelector('option[value="midterm_coverage_pct"]');
        const hasMidterm = choices.length >= 2 && choices.some(g => Number.isFinite(g.midterm_avg));
        if (optMidtermAvg) {
            optMidtermAvg.disabled = !hasMidterm;
            optMidtermAvg.textContent = hasMidterm ? 'Midterm score average' : 'Midterm score average, no eligible scores for this scope';
        }
        if (optMidtermCov) {
            optMidtermCov.disabled = !hasMidterm;
            optMidtermCov.textContent = hasMidterm ? 'Midterm score coverage' : 'Midterm score coverage, no eligible scores for this scope';
        }

        const optPrelimAvg = metricField.select.querySelector('option[value="prelim_avg"]');
        const optPrelimCov = metricField.select.querySelector('option[value="prelim_coverage_pct"]');
        const hasPrelim = choices.length >= 2 && choices.every(g => Number.isFinite(g.prelim_avg));
        if (optPrelimAvg) {
            optPrelimAvg.disabled = !hasPrelim;
            optPrelimAvg.textContent = hasPrelim ? 'Preliminary score average' : 'Preliminary score average, no eligible scores for this scope';
        }
        if (optPrelimCov) {
            optPrelimCov.disabled = !hasPrelim;
            optPrelimCov.textContent = hasPrelim ? 'Preliminary score coverage' : 'Preliminary score coverage, no eligible scores for this scope';
        }

        const gwaOption = metricField.select.querySelector('option[value="gwa"]');
        const gwaUnavailable = choices.length >= 2 && choices.every(g => !Number.isFinite(g.gwa));
        if (gwaOption) {
            gwaOption.textContent = gwaUnavailable ? 'Recorded-grade average (Unavailable - outcomes pending)' : 'Recorded-grade average';
        }

        if (dimension === 'faculty_subject' && metric === 'gwa' && gwaUnavailable) {
            const compSubjCode = (report?.comparable_subjects || []).find(s => String(s.subject_id) === String(report?.filters?.subject))?.code || 'ITE331';
            if (hasPrelim) {
                metric = 'prelim_avg';
                metricField.select.value = 'prelim_avg';
                announceFilter(`${compSubjCode} was selected automatically because it is the only comparable subject for this period. Recorded-grade average is unavailable for this ongoing term. Preliminary score average was selected.`);
            } else {
                metric = 'student_count';
                metricField.select.value = 'student_count';
                announceFilter(`${compSubjCode} was selected automatically because it is the only comparable subject for this period. Recorded-grade average is unavailable for this ongoing term. Student count was selected as an available comparison metric.`);
            }
        } else if (metricField.select.selectedOptions[0]?.disabled) {
            metric = 'gwa';
            metricField.select.value = metric;
        }
        drawResult();
    }
    function validation() {
        if (failed) {
            if (dimension === 'faculty_subject') return ['Faculty comparison options could not be loaded. Try again.', compare];
            return ['Comparison records could not be loaded. Apply the scope again to retry.', compare];
        }
        if (busy || !report) {
            if (dimension === 'faculty_subject') {
                const selectedSubjId = common.subject?.select?.value || report?.filters?.subject;
                const subObj = (report?.comparable_subjects || []).find(s => String(s.subject_id) === String(selectedSubjId));
                const code = subObj?.code || (selectedSubjId === '39' ? 'ITE331' : '');
                if (code) return [`Loading Faculty groups for ${code}…`, null];
            }
            return ['Loading comparison records…', null];
        }
        if (dimension === 'faculty_subject') {
            if (!report.filters.school_year || !report.filters.semester) return ['Choose a school year and semester.', common.school_year.select];
            const compList = report.comparable_subjects || [];
            if (compList.length === 0) {
                return ['No common subject is available for Faculty comparison in this period. Choose another school year or semester. Subjects are shown only when at least two verified Faculty assignments have eligible comparison data.', common.school_year.select];
            }
            if (!report.filters.subject && !common.subject.select.value) return ['Choose a comparable subject.', common.subject.select];
            const currentSubj = report.filters.subject || common.subject.select.value;
            if (currentSubj && !compList.some(s => String(s.subject_id) === String(currentSubj))) {
                return ['No comparable assignment is available. No common verified subject assignment is available for the selected Faculty members and period.', common.subject.select];
            }
            for (const [name, item] of Object.entries(common)) {
                if (!item.select.value || String(item.select.value) !== String(report.filters[name])) {
                    if (name === 'subject' && !report.filters[name] && item.select.value) {
                        const subObj = compList.find(s => String(s.subject_id) === String(item.select.value));
                        return [`Loading Faculty groups for ${subObj?.code || 'ITE331'}…`, null];
                    }
                    return ['Choose the same school year, semester and subject for every Faculty assignment.', item.select];
                }
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
    const valueText = group => {
        const raw = rawValue(group);
        if (raw === null) return 'Unavailable';
        if (metric === 'student_count') return String(raw);
        if (metric.endsWith('_pct') || metric.endsWith('_avg')) return raw.toFixed(2) + '%';
        return raw.toFixed(2);
    };
    const coverage = group => {
        if (metric.startsWith('prelim_')) {
            return Number.isFinite(group?.prelim_coverage_pct) ? group.prelim_coverage_pct.toFixed(2) + '%' : (Number.isFinite(group?.numeric_coverage_pct) ? group.numeric_coverage_pct.toFixed(2) + '%' : 'Unavailable');
        }
        if (metric.startsWith('midterm_')) {
            return Number.isFinite(group?.midterm_coverage_pct) ? group.midterm_coverage_pct.toFixed(2) + '%' : 'Unavailable';
        }
        return Number.isFinite(group?.numeric_coverage_pct) ? group.numeric_coverage_pct.toFixed(2) + '%' : 'Unavailable';
    };
    const status = group => {
        if (metric.startsWith('prelim_') || metric.startsWith('midterm_')) return 'Interim';
        return group.provisional ? 'Provisional' : 'Recorded outcomes resolved';
    };
    const statusBadgeClass = group => {
        const s = status(group);
        if (s === 'Interim') return 'card-status-badge badge-interim';
        return 'card-status-badge ' + (group.provisional ? 'badge-provisional' : 'badge-complete');
    };

    function drawResult(focusInvalid = false) {
        modes.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mode === mode)));
        modeHelp.textContent = mode === 'overlay' ? 'Show selected groups together on one scale.' : 'Show complete details for each group.';
        controls.querySelectorAll('[aria-invalid]').forEach(control => { control.removeAttribute('aria-invalid'); control.removeAttribute('aria-describedby'); });
        result.replaceChildren(); const error = validation();
        notice.hidden = !error; notice.textContent = error?.[0] || ''; notice.setAttribute('role', focusInvalid ? 'alert' : 'status');
        if (error) {
            if (focusInvalid && error[1]) { error[1].setAttribute('aria-invalid', 'true'); error[1].setAttribute('aria-describedby', 'ac-notice'); error[1].focus(); }
            if (error[0]?.includes('No common subject is available for Faculty comparison in this period')) {
                const emptyCard = make('div', undefined, { class: 'card ac-empty-intersection', role: 'region', 'aria-label': 'No common subject available' });
                const emptyHead = make('div', undefined, { class: 'ac-empty-header' });
                emptyHead.append(
                    make('h4', 'No common subject is available for Faculty comparison in this period.'),
                    make('p', 'Choose another school year or semester. Subjects are shown only when at least two verified Faculty assignments have eligible comparison data.', { class: 'ac-helper' })
                );
                const emptyActions = make('div', undefined, { class: 'ac-empty-actions' });
                const bYear = make('button', 'Change school year or semester', { type: 'button', class: 'btn btn--secondary' });
                bYear.addEventListener('click', () => { common.school_year.select.focus(); });
                const bDim = make('button', 'Change comparison dimension', { type: 'button', class: 'btn btn--quiet' });
                bDim.addEventListener('click', () => { (scope.querySelector('[data-dimension="semester"]') || scope.querySelector('button'))?.focus(); });
                emptyActions.append(bYear, bDim);
                emptyCard.append(emptyHead, emptyActions);
                result.append(emptyCard);
                return;
            }
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
        if (!valuesAvailable) {
            notice.hidden = false;
            notice.textContent = metric === 'gwa'
                ? 'Recorded-grade average is unavailable for the selected ongoing term. Choose an available metric.'
                : 'No recorded values are available for this metric and scope. Review coverage or choose another metric.';
        } else if (dimension === 'faculty_subject' && selected.length === 2) {
            const totalSections = selected.reduce((acc, g) => {
                const m = (g?.faculty_label || g?.label || '').match(/Sections\s+([^|]+)/i);
                return acc + (m ? m[1].split(',').length : 0);
            }, 0);
            const secText = totalSections === 5 ? 'five sections' : `${totalSections} sections`;
            notice.hidden = false;
            notice.textContent = `Two comparable Faculty groups are available across ${secText}.`;
            notice.setAttribute('role', 'status');
        } else {
            notice.hidden = true;
            notice.textContent = '';
        }
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
            const stripItems = layout.querySelectorAll('.ac-value-strip-item, .ac-overlay-pill');
            stripItems.forEach(btn => {
                const active = btn.dataset.groupSelect === letter;
                btn.setAttribute('aria-pressed', String(active));
                btn.classList.toggle('is-selected', active);
                const ind = btn.querySelector('.ac-pill-indicator');
                if (ind) ind.textContent = active ? '✓' : '';
            });
            // Update column z-index for physical stacking in overlay mode
            const cols = layout.querySelectorAll('.ac-bar-column');
            cols.forEach(col => {
                const grp = col.dataset.group;
                if (letter === 'all') {
                    col.style.zIndex = grp === 'A' ? '1' : grp === 'B' ? '2' : '3';
                } else if (grp === letter) {
                    col.style.zIndex = '10';
                } else {
                    col.style.zIndex = '1';
                }
            });
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
                const grid = make('div', undefined, { class: 'ac-summary-grid' + (selected.length === 3 ? ' has-3-cols' : ' has-2-cols') });
                selected.slice(0, 2).forEach((group, index) => {
                    const letter = 'ABC'[index];
                    const cell = make('div', undefined, { class: 'ac-summary-card ac-card-' + letter.toLowerCase() });
                    const cellHeader = make('div', undefined, { class: 'ac-summary-cell-header' });
                    cellHeader.append(
                        make('span', glyphs[letter], { class: 'ac-marker-glyph ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }),
                        make('span', 'Group ' + letter, { class: 'ac-summary-group-title' })
                    );
                    const valBox = make('div', undefined, { class: 'ac-summary-val-box' });
                    valBox.append(
                        make('strong', valueText(group), { 'data-comparison-value': group.key, class: 'ac-summary-metric-val' }),
                        make('span', status(group), { class: statusBadgeClass(group) })
                    );
                    const periodLabel = make('p', group.label, { class: 'ac-summary-period-label', title: group.label });
                    const dl = make('dl', undefined, { class: 'ac-summary-stats' });
                    for (const [l, text] of [
                        ['Sample', String(group.student_count)],
                        ['Numeric coverage', coverage(group)],
                        ['Final coverage', Number.isFinite(group.final_coverage_pct) ? group.final_coverage_pct.toFixed(2) + '%' : 'Unavailable']
                    ]) {
                        const pair = make('div'); pair.append(make('dt', l), make('dd', text)); dl.append(pair);
                    }
                    cell.append(cellHeader, valBox, periodLabel, dl);
                    grid.append(cell);
                });
                summary.append(grid);

                if (selected.length === 3) {
                    const groupC = selected[2];
                    const cellC = make('div', undefined, { class: 'ac-summary-card ac-card-c ac-summary-contextual-card' });
                    const cellHeaderC = make('div', undefined, { class: 'ac-summary-cell-header' });
                    cellHeaderC.append(
                        make('span', glyphs.C, { class: 'ac-marker-glyph ac-glyph-c', 'aria-hidden': 'true' }),
                        make('span', 'Group C (Context only) · ' + groupC.label, { class: 'ac-summary-group-title' })
                    );
                    const valBoxC = make('div', undefined, { class: 'ac-summary-val-box' });
                    valBoxC.append(
                        make('strong', valueText(groupC), { 'data-comparison-value': groupC.key, class: 'ac-summary-metric-val' }),
                        make('span', status(groupC), { class: statusBadgeClass(groupC) })
                    );
                    const dlC = make('dl', undefined, { class: 'ac-summary-stats' });
                    for (const [l, text] of [
                        ['Sample', String(groupC.student_count)],
                        ['Numeric coverage', coverage(groupC)],
                        ['Final coverage', Number.isFinite(groupC.final_coverage_pct) ? groupC.final_coverage_pct.toFixed(2) + '%' : 'Unavailable']
                    ]) {
                        const pair = make('div'); pair.append(make('dt', l), make('dd', text)); dlC.append(pair);
                    }
                    cellC.append(cellHeaderC, valBoxC, dlC);
                    summary.append(cellC);
                }
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
                    make('span', status(targetGroup), { class: statusBadgeClass(targetGroup) })
                );
                item.append(metricBox);
                let metricSub = metrics[metric] + ' for selected group';
                if (metric === 'gwa') metricSub = 'Pooled unit-weighted recorded-grade mean';
                else if (metric === 'prelim_avg' || metric === 'midterm_avg') metricSub = 'Interim percentage result, not a finalized GWA.';
                item.append(make('p', metricSub, { class: 'ac-helper' }));

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

                const counterpart = make('div', undefined, { class: 'ac-counterpart-card' });
                counterpart.append(
                    make('span', 'Compared with Group ' + otherLetter + ': ', { class: 'ac-helper' }),
                    make('strong', valueText(otherGroup), { class: 'ac-counterpart-val' }),
                    make('span', ' (' + otherGroup.label + ')', { class: 'ac-helper' })
                );
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
                    class: 'btn btn--secondary ac-toolbar-btn ac-highlight-btn ac-highlight-btn-' + letter.toLowerCase(),
                    'data-group-select': letter,
                    'aria-pressed': String(highlightedGroup === letter)
                });
                btn.append(make('span', glyphs[letter], { class: 'ac-marker-glyph ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }), ' Group ' + letter);
                btn.addEventListener('click', () => updateHighlight(letter));
                highlightGroup.append(btn);
            });
            const showBothBtn = make('button', selected.length === 3 ? 'Show all' : 'Show both', {
                type: 'button',
                class: 'btn btn--secondary ac-toolbar-btn ac-highlight-btn ac-highlight-btn-all',
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

            const formatScopeLabel = (group, dim, isCompact = false) => {
                if (!group) return '';
                if (dim === 'faculty_subject') {
                    const raw = group.faculty_label || group.label || '';
                    const parts = raw.split(' · ');
                    const fac = parts[0] || raw;
                    if (isCompact && fac.length > 15) {
                        const nameParts = fac.split(' ');
                        if (nameParts.length > 1) {
                            return nameParts.slice(0, -1).join(' ') + ' ' + nameParts[nameParts.length - 1][0] + '.';
                        }
                    }
                    return fac;
                }
                if (dim === 'semester') {
                    let lbl = group.label || '';
                    if (isCompact) {
                        lbl = lbl.replace('Semester 1', 'S1').replace('Semester 2', 'S2');
                    }
                    return lbl;
                }
                if (dim === 'subject') {
                    const raw = group.label || '';
                    return raw.split(' · ')[0].trim();
                }
                if (dim === 'section') {
                    const raw = group.label || '';
                    return raw.replace(/^Section\s+/i, '').trim();
                }
                if (dim === 'year_level') {
                    return group.label || '';
                }
                return group.label || '';
            };

            const adjustPillPlacement = (pills, stageEl, colEls) => {
                if (!pills || !stageEl || !colEls.length) return;
                pills.classList.remove('pos-upper-left', 'pos-top-center');
                const pillRect = pills.getBoundingClientRect();
                if (!pillRect.width || !pillRect.height) return;

                let collides = false;
                for (const col of colEls) {
                    const bar = col.querySelector('.ac-vertical-bar');
                    if (!bar) continue;
                    const bRect = bar.getBoundingClientRect();
                    if (bRect.top < pillRect.bottom + 6 && bRect.bottom > pillRect.top - 6 &&
                        bRect.left < pillRect.right + 6 && bRect.right > pillRect.left - 6) {
                        collides = true;
                        break;
                    }
                }
                if (!collides) return;

                // Attempt upper-left
                pills.classList.add('pos-upper-left');
                const leftRect = pills.getBoundingClientRect();
                let leftCollides = false;
                for (const col of colEls) {
                    const bar = col.querySelector('.ac-vertical-bar');
                    if (!bar) continue;
                    const bRect = bar.getBoundingClientRect();
                    if (bRect.top < leftRect.bottom + 6 && bRect.bottom > leftRect.top - 6 &&
                        bRect.left < leftRect.right + 6 && bRect.right > leftRect.left - 6) {
                        leftCollides = true;
                        break;
                    }
                }
                if (!leftCollides) return;

                // Fallback: top center row
                pills.classList.remove('pos-upper-left');
                pills.classList.add('pos-top-center');
            };

            // Recognizable Vertical Bar Comparison Chart Card
            const chartCard = make('div', undefined, { class: 'ac-vertical-chart-card', role: 'region', 'aria-label': 'Vertical bar comparison chart' });

            const chartStage = make('div', undefined, { class: 'ac-chart-stage' + (chartDisplayMode === 'overlay' ? ' ac-stage-overlay' : '') });

            // If overlay mode, insert the compact group-colored pill cluster inside the chart stage
            let pillGroup = null;
            if (chartDisplayMode === 'overlay') {
                pillGroup = make('div', undefined, {
                    class: 'ac-overlay-pill-group ac-overlay-value-strip' + (selected.length === 3 ? ' has-3-pills' : ''),
                    role: 'group',
                    'aria-label': 'Current group values'
                });
                selected.forEach((group, index) => {
                    const letter = 'ABC'[index];
                    const isSelected = highlightedGroup === letter;
                    const scopeName = formatScopeLabel(group, dimension, selected.length === 3);
                    const pillBtn = make('button', undefined, {
                        type: 'button',
                        class: 'ac-overlay-pill ac-value-strip-item ac-item-' + letter.toLowerCase() + ' ac-pill-' + letter.toLowerCase() + (isSelected ? ' is-selected' : ''),
                        'data-group-select': letter,
                        'aria-pressed': String(isSelected),
                        'aria-label': 'Group ' + letter + ', ' + formatScopeLabel(group, dimension) + ', value ' + valueText(group),
                        title: group.label
                    });
                    const indicator = make('span', isSelected ? '✓' : '', { class: 'ac-pill-indicator', 'aria-hidden': 'true' });
                    pillBtn.append(
                        make('span', glyphs[letter], { class: 'ac-marker-glyph ac-glyph-' + letter.toLowerCase(), 'aria-hidden': 'true' }),
                        make('span', scopeName, { class: 'ac-pill-key ac-strip-key' }),
                        make('strong', valueText(group), { class: 'ac-pill-val ac-strip-val' }),
                        indicator
                    );
                    pillBtn.addEventListener('click', () => updateHighlight(letter));
                    pillGroup.append(pillBtn);
                });
                chartStage.append(pillGroup);
            }

            // Y-Axis and Ticks (Major Scale Only)
            const yAxis = make('div', undefined, { class: 'ac-chart-y-axis', 'aria-hidden': 'true' });
            let yTicks = [4, 3, 2, 1, 0];
            if (metric.endsWith('_pct') || metric.endsWith('_avg')) yTicks = [100, 75, 50, 25, 0];
            else if (metric === 'student_count') yTicks = [max, Math.round(max * 0.75), Math.round(max * 0.5), Math.round(max * 0.25), 0];

            yTicks.forEach(tickVal => {
                const tickPct = (tickVal / max) * 100;
                const tickNode = make('div', undefined, { class: 'ac-y-tick', style: 'top: ' + (100 - tickPct) + '%;' });
                let tickLabel = metric === 'gwa' ? tickVal.toFixed(2) : (metric.endsWith('_pct') || metric.endsWith('_avg')) ? tickVal + '%' : String(tickVal);
                if (metric === 'gwa' && tickVal === 1) tickLabel = '1.00';
                tickNode.append(make('span', tickLabel, { class: 'ac-y-tick-label' }));
                yAxis.append(tickNode);

                if (tickPct > 0) {
                    chartStage.append(make('div', undefined, { class: 'ac-chart-gridline', style: 'top: ' + (100 - tickPct) + '%;' }));
                }
            });

            // Half-step minor horizontal lines (0.50, 1.50, 2.50, 3.50)
            if (metric === 'gwa') {
                [3.5, 2.5, 1.5, 0.5].forEach(minorVal => {
                    const minorPct = (minorVal / max) * 100;
                    chartStage.append(make('div', undefined, {
                        class: 'ac-chart-gridline ac-gridline-minor',
                        style: 'top: ' + (100 - minorPct) + '%;'
                    }));
                });
            }

            chartStage.append(make('div', undefined, { class: 'ac-chart-baseline' }));
            chartStage.append(yAxis);

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

                    if (raw > 0) {
                        // The Vertical Bar (terminates at 0.00 baseline)
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

                        // Value badge pinned inside top of the bar
                        const valBadge = make('span', valueText(group), {
                            class: 'ac-bar-inner-val-badge ac-bar-val-badge',
                            'aria-hidden': 'true'
                        });
                        bar.append(valBadge);

                        // Circular A, B, C badge placed INSIDE the bar, 8-12px above 0.00 baseline
                        const marker = make('div', letter, {
                            class: 'ac-bar-inner-marker ac-marker-' + letter.toLowerCase() + (highlightedGroup === letter ? ' is-selected' : ''),
                            'aria-hidden': 'true'
                        });
                        bar.append(marker);
                        col.append(bar);
                    } else {
                        // Zero value: 4px baseline marker, 0.00% immediately above marker, circular A/B/C marker visible at baseline
                        const bar = make('div', undefined, {
                            class: 'ac-vertical-bar ac-bar ac-bar-color-' + index + ' ac-bar-zero',
                            'data-value': 0,
                            role: 'button',
                            tabindex: '0',
                            'aria-label': 'Group ' + letter + ': ' + group.label + ', ' + valueText(group)
                        });
                        bar.addEventListener('click', () => updateHighlight(letter));
                        bar.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); updateHighlight(letter); } });

                        const zeroValBadge = make('span', valueText(group), {
                            class: 'ac-bar-zero-val-badge ac-bar-val-badge',
                            'aria-hidden': 'true'
                        });
                        col.append(zeroValBadge);

                        const marker = make('div', letter, {
                            class: 'ac-bar-inner-marker ac-marker-zero ac-marker-' + letter.toLowerCase() + (highlightedGroup === letter ? ' is-selected' : ''),
                            'aria-hidden': 'true'
                        });
                        col.append(marker);
                        col.append(bar);
                    }
                } else {
                    if (chartDisplayMode !== 'overlay') {
                        const topInfo = make('div', undefined, {
                            class: 'ac-bar-top-info',
                            style: 'bottom: 20px;'
                        });
                        topInfo.append(make('span', 'Unavailable', { class: 'ac-bar-val-badge' }));
                        col.append(topInfo);
                    }

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

                // In Side-by-side mode: Sub-labels beneath baseline (NO "Group A" / "Group B")
                // For faculty_subject, faculty and section details belong in the Comparison summary, avoiding chart clutter
                if (chartDisplayMode !== 'overlay') {
                    if (dimension !== 'faculty_subject') {
                        const bottomLabel = make('div', undefined, { class: 'ac-bar-bottom-label' });
                        let periodLabel = formatScopeLabel(group, dimension, selected.length === 3);
                        bottomLabel.append(
                            make('span', periodLabel, { class: 'ac-bar-sub-label', title: group.label })
                        );
                        col.append(bottomLabel);
                    }
                }

                plotArea.append(col);
            });
            chartStage.append(plotArea);
            chartCard.append(chartStage);

            if (pillGroup) {
                adjustPillPlacement(pillGroup, chartStage, plotArea.querySelectorAll('.ac-bar-column'));
            }

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
                top.append(make('span', 'Group ' + letter.toUpperCase(), { class: 'ac-group-tag' }), make('span', status(group), { class: statusBadgeClass(group) }));
                header.append(top, make('h4', group.label, { class: 'panel-title' }), make('p', dimensions[dimension] + ' · ' + (report.filters.school_year || 'All recorded years') + ' · ' + (report.filters.semester ? 'Semester ' + report.filters.semester : 'All recorded semesters'), { class: 'panel-sub' }));
                const metricBox = make('div', undefined, { class: 'panel-metric-box card-metric-box' });
                let cardMetricSub = 'Recorded values for the selected scope';
                if (metric === 'gwa') cardMetricSub = 'Pooled unit-weighted recorded-grade mean';
                else if (metric === 'prelim_avg' || metric === 'midterm_avg') cardMetricSub = 'Interim percentage result, not a finalized GWA.';
                metricBox.append(make('div', metrics[metric], { class: 'panel-metric-label card-metric-label' }), make('div', valueText(group), { class: 'panel-metric-value card-metric-value', 'data-comparison-value': group.key }), make('div', cardMetricSub, { class: 'panel-metric-sub card-metric-sub' }));
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
        const evidence = make('details', undefined, { class: 'card ac-evidence analytics-evidence-disclosure ac-disclosure-card' });
        const evSum = make('summary', undefined, { class: 'ac-disclosure-summary analytics-evidence-summary', 'aria-label': 'View exact values for comparison groups' });
        evSum.innerHTML = '<svg class="ac-disclosure-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/></svg><span class="ac-disclosure-label">View exact values</span>';
        evidence.append(evSum);
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
        const help = make('details', undefined, { class: 'card ac-explanation analytics-calc-disclosure ac-disclosure-card' });
        const helpSum = make('summary', undefined, { class: 'ac-disclosure-summary analytics-calc-summary', 'aria-label': 'How to interpret these results' });
        helpSum.innerHTML = '<svg class="ac-disclosure-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span class="ac-disclosure-label">How to interpret these results</span>';
        const helpContent = make('div', undefined, { class: 'analytics-calc-content' });
        const helpBullets = make('ul', undefined, { class: 'analytics-calc-bullets' });
        helpBullets.append(
            make('li', 'Recorded-grade averages use eligible finalized numeric outcomes.'),
            make('li', 'Point and relative changes require comparable complete periods.'),
            make('li', 'Gaps represent periods without an eligible numeric value.'),
            make('li', 'Provisional or incomplete periods are not used for a definitive change.')
        );
        const subCalc = make('details', undefined, { class: 'analytics-calc-formulas' });
        subCalc.append(
            make('summary', 'View calculation details', { class: 'analytics-calc-formulas-summary' }),
            make('p', 'Values use the existing pooled eligible final points × credit units / eligible credit units. Difference is Group A minus Group B. Missing values remain unavailable; provisional records may change.'),
            make('p', report.fairness)
        );
        helpContent.append(helpBullets, subCalc);
        help.append(helpSum, helpContent);
        result.append(help);
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
