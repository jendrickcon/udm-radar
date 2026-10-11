(() => {
    'use strict';
    const form = document.getElementById('coverage-filters');
    if (!form) return;
    const region = document.getElementById('coverage-results');
    const status = document.getElementById('coverage-status');
    const loading = document.getElementById('coverage-loading');
    const error = document.getElementById('coverage-filter-error');
    const rows = [...region.querySelectorAll('tr[data-coverage-record]')];
    const table = region.querySelector('table'), head = table.querySelector('thead');
    let revision = 0, currentPage = 1;

    // URL state affects presentation only, never the read-only server query.
    if (['http:', 'https:'].includes(location.protocol)) {
        const params = new URL(location.href).searchParams;
        for (const key of ['period', 'section', 'source', 'triage', 'page_size']) {
            const control = form.elements[key], value = params.get(key);
            if (control && [...control.options].some(option => option.value === value)) control.value = value;
        }
        const requestedPage = Number(params.get('page'));
        if (Number.isSafeInteger(requestedPage) && requestedPage > 0) currentPage = requestedPage;
    }

    function positionHeader() {
        // Follow normal content scrolling despite the horizontal scroll ancestor.
        // No nested vertical viewport or fixed table height is introduced.
        const bounds = table.getBoundingClientRect();
        const top = Math.max(0, document.getElementById('coverage-page').getBoundingClientRect().top);
        const naturalTop = head.getBoundingClientRect().top - Number(head.dataset.stickyOffset || 0);
        const offset = Math.min(Math.max(0, top - naturalTop), Math.max(0, bounds.bottom - naturalTop - head.offsetHeight));
        head.dataset.stickyOffset = offset;
        head.style.transform = 'translateY(' + offset + 'px)';
    }

    function apply({ resetPage = true, focusResults = false } = {}) {
        if (resetPage) currentPage = 1;
        const attempt = ++revision;
        region.setAttribute('aria-busy', 'true'); loading.hidden = false; error.hidden = true;
        requestAnimationFrame(() => {
            if (attempt !== revision) return;
            try {
                const period = form.elements.period.value;
                const section = form.elements.section.value;
                const source = form.elements.source.value;
                const triage = form.elements.triage.value;
                const size = Number(form.elements.page_size.value);
                const periodNames = { prelim: 'Preliminary', midterm: 'Midterm', prefinal: 'Pre-Final' };
                const periodName = periodNames[period] || 'Preliminary';

                const records = rows.map(row => ({ row, record: JSON.parse(row.dataset.coverageRecord) }));
                let expected = 0, available = 0, complete = 0, partial = 0, unavailable = 0;
                const sections = new Set();
                const matching = records.filter(({ record }) => {
                    const missing = field => record.missing_counts[field] > 0;
                    const categories = {
                        missing_prelim: missing('prelim'),
                        missing_midterm: missing('midterm'),
                        missing_prefinal: missing('prefinal'),
                        no_numeric: !record.has_numeric,
                        partial_inputs: record.inputs === 'partial',
                        insufficient: record.inputs === 'insufficient',
                        provisional: record.prediction_state === 'provisional',
                        no_records: record.expected === 0
                    };
                    return (!section || section === record.section) && (!source || source === record.source) && (!triage || categories[triage]);
                });

                const count = matching.length, pages = Math.ceil(count / size);
                currentPage = pages ? Math.min(Math.max(1, currentPage), pages) : 1;
                const start = (currentPage - 1) * size;
                const visible = new Set(matching.slice(start, start + size).map(item => item.row));

                records.forEach(({ row, record }) => {
                    const isVisible = visible.has(row);
                    row.hidden = !isVisible;
                    const detailRow = document.getElementById('coverage-detail-' + record.id);
                    if (detailRow) detailRow.hidden = true;
                    const toggle = row.querySelector('.coverage-detail-toggle');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                        toggle.textContent = 'View details';
                        toggle.setAttribute('aria-label', 'View details for ' + record.name);
                    }
                    row.classList.remove('is-expanded');

                    const n = record.expected, valid = record.counts[period];
                    const cell = row.querySelector('[data-period-coverage]');
                    if (n === 0) {
                        cell.innerHTML = '<span class="coverage-count">No current subjects</span>';
                    } else if (valid === n) {
                        cell.innerHTML = '<span class="coverage-count">' + valid + ' of ' + n + ' ' + periodName + '</span>';
                    } else if (valid > 0) {
                        cell.innerHTML = '<span class="coverage-count">' + valid + ' of ' + n + ' ' + periodName + '</span><br><small class="coverage-subtext">' + (n - valid) + ' missing</small>';
                    } else {
                        cell.innerHTML = '<span class="coverage-count">0 of ' + n + ' ' + periodName + '</span><br><small class="coverage-subtext">No results recorded</small>';
                    }

                    if (n > 0 && valid === n) complete++;
                    else if (valid > 0) partial++;
                    else unavailable++;
                });

                matching.forEach(({ record }) => {
                    expected += record.expected; available += record.counts[period];
                    if (record.counts[period] < record.expected) sections.add(record.section);
                });

                const contextHeading = document.getElementById('coverage-kpi-context');
                if (contextHeading) {
                    contextHeading.setAttribute('aria-label', 'Current-term result summary for the selected ' + periodName + ' grading period.');
                }
                const periodLabel = document.getElementById('coverage-kpi-period-label');
                if (periodLabel) {
                    periodLabel.textContent = periodName;
                }

                const cardReviewed = document.getElementById('coverage-card-reviewed');
                if (cardReviewed) cardReviewed.setAttribute('aria-label', count + ' students reviewed');

                const completeTitle = document.getElementById('coverage-complete-title');
                if (completeTitle) completeTitle.textContent = 'Complete';
                const cardComplete = document.getElementById('coverage-card-complete');
                if (cardComplete) {
                    cardComplete.setAttribute('aria-label', complete + ' students have results recorded for every current subject row in the selected ' + periodName + ' period.');
                }

                const partialTitle = document.getElementById('coverage-partial-title');
                if (partialTitle) partialTitle.textContent = 'Some missing';
                const cardPartial = document.getElementById('coverage-card-partial');
                if (cardPartial) {
                    cardPartial.setAttribute('aria-label', partial + ' students have at least one recorded ' + periodName + ' result and at least one missing ' + periodName + ' result.');
                }

                const cardNeedsReview = document.getElementById('coverage-card-needs-review');
                if (cardNeedsReview) {
                    cardNeedsReview.setAttribute('aria-label', unavailable + ' students have no ' + periodName + ' results recorded or no current subjects found.');
                }
                const unavailableSubtext = document.getElementById('coverage-unavailable-subtext');
                if (unavailableSubtext) unavailableSubtext.textContent = 'no ' + periodName + ' results recorded or no current subjects found';

                document.getElementById('coverage-complete-count').textContent = complete;
                document.getElementById('coverage-partial-count').textContent = partial;
                document.getElementById('coverage-unavailable-count').textContent = unavailable;

                const first = count ? start + 1 : 0, last = Math.min(start + size, count);
                status.textContent = count > 0 ? ('Showing ' + first + '–' + last + ' of ' + count + ' students') : 'No students match the selected filters.';
                const emptyRow = document.getElementById('coverage-empty');
                if (emptyRow) emptyRow.hidden = count !== 0;

                const periodSummary = document.getElementById('coverage-period-summary');
                if (periodSummary) {
                    periodSummary.textContent = count > 0
                        ? available + ' of ' + expected + ' ' + periodName + ' results are available across the filtered records.'
                        : '';
                }

                const restrictions = ['period', 'section', 'triage', 'source'].filter(key => form.elements[key].value !== (key === 'period' ? 'prelim' : ''));
                const filterSummary = document.getElementById('coverage-active-filters');
                if (filterSummary) {
                    filterSummary.hidden = restrictions.length === 0;
                    filterSummary.textContent = restrictions.length ? 'Active filters: ' + restrictions.map(key => form.elements[key].selectedOptions[0].textContent).join(' · ') : '';
                }

                document.querySelectorAll('[data-source-filter]').forEach(button => {
                    const selected = button.dataset.sourceFilter === source;
                    button.setAttribute('aria-pressed', String(selected));
                    const cue = button.querySelector('[data-source-cue]');
                    const countEl = button.querySelector('.coverage-source-count');
                    const sCount = Number(countEl?.textContent.split(' ')[0] || 0);
                    if (cue) {
                        if (sCount === 0) {
                            cue.textContent = selected ? 'Selected · No matching students' : 'No matching students';
                        } else {
                            cue.textContent = selected ? 'Selected · View students' : 'View students';
                        }
                    }
                });

                region.querySelectorAll('[data-pagination]').forEach(nav => nav.hidden = count === 0);
                if (count > 0) {
                    region.querySelectorAll('[data-page-label]').forEach(label => label.textContent = 'Page ' + currentPage + ' of ' + pages);
                    region.querySelectorAll('[data-page-action]').forEach(button => {
                        button.disabled = !pages || (button.dataset.pageAction === 'previous' ? currentPage <= 1 : currentPage >= pages);
                    });
                }

                if (['http:', 'https:'].includes(location.protocol)) {
                    const url = new URL(location.href);
                    for (const key of ['period', 'section', 'triage', 'source', 'page_size']) {
                        const value = form.elements[key].value;
                        if (value) url.searchParams.set(key, value); else url.searchParams.delete(key);
                    }
                    url.searchParams.set('page', currentPage);
                    history.replaceState(null, '', url);
                }

                positionHeader();
                const enhancementNote = document.getElementById('coverage-enhancement-note');
                if (enhancementNote) enhancementNote.hidden = true;
                if (focusResults) status.focus();
            } catch (_) {
                error.hidden = false; status.textContent = 'Filters could not be applied. Use Retry filters.';
            } finally { loading.hidden = true; region.setAttribute('aria-busy', 'false'); }
        });
    }

    form.addEventListener('submit', event => { event.preventDefault(); apply(); });
    form.addEventListener('reset', () => { setTimeout(() => apply({ resetPage: true, focusResults: true }), 0); });
    const emptyClearBtn = document.getElementById('coverage-empty-clear');
    if (emptyClearBtn) {
        emptyClearBtn.addEventListener('click', () => {
            form.reset();
            setTimeout(() => apply({ resetPage: true, focusResults: true }), 0);
        });
    }
    document.querySelectorAll('[data-source-filter]').forEach(button => button.addEventListener('click', () => {
        form.elements.source.value = button.dataset.sourceFilter; apply(); document.getElementById('coverage-source').focus();
    }));
    region.querySelectorAll('[data-page-action]').forEach(button => button.addEventListener('click', () => {
        currentPage += button.dataset.pageAction === 'previous' ? -1 : 1;
        apply({ resetPage: false, focusResults: true });
    }));
    function getVerticalScrollOwner(element) {
        let parent = element ? element.parentElement : null;
        while (parent && parent !== document.body && parent !== document.documentElement) {
            const style = window.getComputedStyle(parent);
            const overflowY = style.overflowY;
            if ((overflowY === 'auto' || overflowY === 'scroll') && parent.scrollHeight > parent.clientHeight) {
                return parent;
            }
            parent = parent.parentElement;
        }
        const docEl = document.scrollingElement || document.documentElement;
        if (docEl && docEl.scrollHeight > docEl.clientHeight) {
            return window;
        }
        const main = document.querySelector('.main-content');
        if (main && main.scrollHeight > main.clientHeight) {
            return main;
        }
        return window;
    }

    function scrollDetailIntoViewIfNeeded(summaryRow, detailRow) {
        if (!detailRow || detailRow.hidden) return;
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const scrollBehavior = prefersReducedMotion ? 'auto' : 'smooth';

        const scrollOwner = getVerticalScrollOwner(detailRow);
        const isWindow = (scrollOwner === window);

        const ownerRect = isWindow ? { top: 0, bottom: window.innerHeight, height: window.innerHeight } : scrollOwner.getBoundingClientRect();
        const viewportTop = ownerRect.top;
        const viewportBottom = ownerRect.bottom;

        const headRect = head ? head.getBoundingClientRect() : null;
        let stickyHeight = 0;
        if (headRect) {
            if (isWindow && headRect.top <= 10) stickyHeight = head.offsetHeight;
            else if (!isWindow && headRect.top <= ownerRect.top + 10) stickyHeight = head.offsetHeight;
        }

        const safeTopMargin = viewportTop + stickyHeight + 12;
        const safeBottomMargin = viewportBottom - 16;

        const summaryRect = summaryRow ? summaryRow.getBoundingClientRect() : null;
        const detailRect = detailRow.getBoundingClientRect();

        let scrollDelta = 0;

        if (detailRect.bottom > safeBottomMargin) {
            const overflowBottom = detailRect.bottom - safeBottomMargin;
            if (summaryRect) {
                // Keep the student summary row visible below sticky header where practical
                const maxScrollDown = Math.max(0, summaryRect.top - safeTopMargin);
                if (maxScrollDown > 0) {
                    scrollDelta = Math.min(overflowBottom, maxScrollDown);
                }
            } else {
                scrollDelta = overflowBottom;
            }
        } else if (summaryRect && summaryRect.top < safeTopMargin) {
            scrollDelta = summaryRect.top - safeTopMargin;
        }

        if (Math.abs(scrollDelta) > 4) {
            if (isWindow) {
                window.scrollBy({ top: scrollDelta, behavior: scrollBehavior });
            } else {
                scrollOwner.scrollBy({ top: scrollDelta, behavior: scrollBehavior });
            }
        }
    }

    region.querySelectorAll('.coverage-detail-toggle').forEach(btn => btn.addEventListener('click', () => {
        const detailId = btn.getAttribute('aria-controls');
        const detailRow = document.getElementById(detailId);
        if (!detailRow) return;
        const summaryRow = btn.closest('tr');
        const record = summaryRow && summaryRow.dataset.coverageRecord ? JSON.parse(summaryRow.dataset.coverageRecord) : null;
        const studentName = record?.name || 'student';
        const isExpanded = btn.getAttribute('aria-expanded') === 'true';
        if (!isExpanded) {
            region.querySelectorAll('.coverage-detail-toggle').forEach(otherBtn => {
                if (otherBtn !== btn && otherBtn.getAttribute('aria-expanded') === 'true') {
                    otherBtn.setAttribute('aria-expanded', 'false');
                    otherBtn.textContent = 'View details';
                    const otherRow = otherBtn.closest('tr');
                    const otherRecord = otherRow && otherRow.dataset.coverageRecord ? JSON.parse(otherRow.dataset.coverageRecord) : null;
                    otherBtn.setAttribute('aria-label', 'View details for ' + (otherRecord?.name || 'student'));
                    const otherDetail = document.getElementById(otherBtn.getAttribute('aria-controls'));
                    if (otherDetail) otherDetail.hidden = true;
                    if (otherRow) otherRow.classList.remove('is-expanded');
                }
            });
            btn.setAttribute('aria-expanded', 'true');
            btn.textContent = 'Hide details';
            btn.setAttribute('aria-label', 'Hide details for ' + studentName);
            detailRow.hidden = false;
            if (summaryRow) summaryRow.classList.add('is-expanded');
            positionHeader();

            // Allow layout geometry calculation across animation frames before measuring and scrolling
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    scrollDetailIntoViewIfNeeded(summaryRow, detailRow);
                });
            });
        } else {
            btn.setAttribute('aria-expanded', 'false');
            btn.textContent = 'View details';
            btn.setAttribute('aria-label', 'View details for ' + studentName);
            detailRow.hidden = true;
            if (summaryRow) summaryRow.classList.remove('is-expanded');
            positionHeader();
        }
    }));
    document.addEventListener('scroll', positionHeader, true);
    window.addEventListener('resize', positionHeader);
    const retryBtn = document.getElementById('coverage-retry');
    if (retryBtn) retryBtn.addEventListener('click', () => apply());
    document.querySelectorAll('.table-header-help').forEach(btn => {
        btn.addEventListener('click', event => {
            event.stopPropagation();
            const wasOpen = btn.classList.contains('is-open');
            document.querySelectorAll('.table-header-help.is-open').forEach(b => b.classList.remove('is-open'));
            if (!wasOpen) {
                btn.classList.add('is-open');
                btn.focus();
            } else {
                btn.blur();
            }
        });
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.table-header-help')) {
            document.querySelectorAll('.table-header-help.is-open').forEach(b => b.classList.remove('is-open'));
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            const active = document.activeElement;
            if (active && active.classList.contains('table-header-help')) {
                active.blur();
            }
            document.querySelectorAll('.table-header-help.is-open').forEach(b => b.classList.remove('is-open'));
        }
    });
    apply({ resetPage: false });
})();
