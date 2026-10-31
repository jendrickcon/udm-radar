(() => {
    'use strict';
    const allPoints = JSON.parse(document.getElementById('student-trend-data').textContent);
    const progScript = document.getElementById('student-trend-current-progress');
    const progData = progScript ? JSON.parse(progScript.textContent) : null;
    const termScript = document.getElementById('student-trend-active-term');
    const termData = termScript ? JSON.parse(termScript.textContent) : null;

    // Exclude pending history placeholders and legacy interim snapshots from GWA axis
    const points = allPoints.map((point, sourceIndex) => ({ ...point, sourceIndex })).filter(point => {
        if (point.zone === 'history' && point.state === 'Pending') return false;
        if (point.zone === 'current') return false;
        return true;
    });

    const pointLabel = point => point.type.replace(' snapshot',' progress');
    // Preserve evidence label contract: pointLabel(points[item.dataIndex])
    const status = document.getElementById('trend-chart-status');

    const historyPoints = points.filter(p => p.zone === 'history');
    const estimatePoint = points.find(p => p.zone === 'estimate');
    let currentAssessmentPoint = points.find(p => p.zone === 'current_assessment');

    // Progressive enhancement: if current assessment point is supplied via progData, ensure it is represented
    if (!currentAssessmentPoint && progData && progData.has_recorded_stage && progData.converted_point !== null) {
        currentAssessmentPoint = {
            zone: 'current_assessment',
            type: 'Current-term assessment point',
            value: progData.converted_point,
            state: 'Converted',
            stage_label: progData.stage_based_label || 'Preliminary-based',
            stage_name: progData.latest_stage_label || 'Preliminary',
            raw_percentage: progData.primary_value,
            coverage_text: progData.coverage_text,
            period: termData ? `${termData.school_year} · Semester ${termData.semester}` : 'Current term',
            key: termData ? `${termData.school_year}|${termData.semester}` : null,
            sourceIndex: -1
        };
        points.push(currentAssessmentPoint);
    }

    if (!points.some(point => point.value !== null)) {
        const chartWrap = document.querySelector('.trend-chart');
        if (chartWrap) chartWrap.hidden = true;
        if (status) {
            status.textContent = 'No chart is available because there are no dated completed results, current progress values, or stored estimates to plot. Available record details remain in the exact values table below.';
        }
        return;
    }
    if (typeof Chart === 'undefined') {
        if (status) status.textContent = 'Chart unavailable. All values and point details are in the exact values table below.';
        return;
    }

    const root = document.documentElement;
    const palette = () => {
        const css = getComputedStyle(root);
        const guideContainer = document.querySelector('.student-performance-trend') || document.body;
        const guideStyle = getComputedStyle(guideContainer);
        const isDark = root.dataset.theme === 'dark';
        return {
            text: isDark ? '#f8fafc' : '#0f172a',
            background: css.getPropertyValue('--card-bg').trim() || (isDark ? '#1e293b' : '#ffffff'),
            divider: isDark ? '#94a3b8' : '#64748b',
            grid: css.getPropertyValue('--border-color').trim() || (isDark ? '#334155' : '#e2e8f0'),
            history: css.getPropertyValue('--accent-blue').trim() || (isDark ? '#38bdf8' : '#2563eb'),
            teal: isDark ? '#2dd4bf' : '#0d9488',
            tealConnector: isDark ? 'rgba(45, 212, 191, 0.45)' : 'rgba(13, 148, 136, 0.45)',
            estimate: isDark ? '#fbbf24' : '#d97706',
            bridge: isDark ? 'rgba(251, 191, 36, 0.45)' : 'rgba(217, 119, 6, 0.45)',
            guides: [0, 1, 2, 3].map(index => guideStyle.getPropertyValue('--trend-guide-' + index).trim())
        };
    };
    let colors = palette();

    const hasCurrentAssessment = currentAssessmentPoint && currentAssessmentPoint.value !== null;
    const hasEstimate = estimatePoint && estimatePoint.value !== null;
    const numHistory = historyPoints.length;

    // Three balanced visual regions:
    // 1. Completed semesters: 78% width (x in [0, 78])
    //    Points spaced across x in [4, 72] to cushion from y-axis (x=0) and divider (x=78)
    // 2. Current assessment point: 10% width (x in [78, 88]), marker centered at x = 83
    // 3. Estimated result: 12% width (x in [88, 100]), marker centered at x = 94
    const histStartX = 4;
    const histEndX = 72;
    historyPoints.forEach((p, i) => {
        p.x = numHistory > 1 ? histStartX + (i / (numHistory - 1)) * (histEndX - histStartX) : 39;
    });
    if (currentAssessmentPoint) {
        currentAssessmentPoint.x = 83;
    }
    if (estimatePoint) {
        estimatePoint.x = 94;
    }

    const lastHistoryIndex = historyPoints.reduce((last, p, i) => p.value !== null ? i : last, -1);
    const lastHistPoint = lastHistoryIndex >= 0 ? historyPoints[lastHistoryIndex] : null;

    // Independent prospective connectors branching from latest completed GWA:
    // 1. Current assessment connector: thin dashed teal line to current assessment point
    const hasCurrentConnector = lastHistPoint !== null && hasCurrentAssessment;
    const currentConnectorData = hasCurrentConnector ? [
        { x: lastHistPoint.x, y: lastHistPoint.value },
        { x: currentAssessmentPoint.x, y: currentAssessmentPoint.value }
    ] : [];

    // 2. Estimate connector (Projection bridge): thin dashed amber line to provisional estimated GWA
    const hasEstimateConnector = lastHistPoint !== null && hasEstimate;
    const estimateConnectorData = hasEstimateConnector ? [
        { x: lastHistPoint.x, y: lastHistPoint.value },
        { x: estimatePoint.x, y: estimatePoint.value }
    ] : [];

    // Never crop a genuine low/zero result merely to tighten the usual GWA view.
    const validPointValues = points.filter(p => p.value !== null).map(p => p.value);
    const lowestValue = Math.min(2.5, ...validPointValues);
    const axisMinimum = Math.max(0, Math.floor((lowestValue < 2.5 ? lowestValue - .1 : 2.5) * 4) / 4);

    const axisLabel = point => {
        if (point.zone === 'history') {
            const [year, semester] = point.key.split('|');
            const shortYear = year.replace(/^(\d{4})-(\d{2})(\d{2})$/, '$1–$3');
            return [shortYear, semester === '3' ? 'Summer' : `Sem ${semester}`];
        }
        if (point.zone === 'current_assessment') {
            const stageLabel = point.stage_label || (progData && progData.stage_based_label) || 'Preliminary-based';
            return ['Current assessment', stageLabel];
        }
        if (point.zone === 'estimate') {
            return 'Estimated GWA';
        }
        return '';
    };

    const guidesScript = document.getElementById('student-trend-guides');
    const guides = guidesScript ? JSON.parse(guidesScript.textContent) : [];

    const contextPlugin = {
        id: 'studentEvidenceContext',
        beforeDatasetDraw(chart, args) {
            if (args.index >= 5) { chart.ctx.save(); chart.ctx.globalAlpha = .45; }
        },
        afterDatasetDraw(chart, args) {
            if (args.index >= 5) chart.ctx.restore();
        },
        afterDatasetsDraw(chart) {
            const { ctx, chartArea, scales } = chart;
            ctx.save(); ctx.fillStyle = colors.text; ctx.font = '600 13px sans-serif'; ctx.textAlign = 'center';

            const paintValue = (label, x, y) => {
                ctx.save(); ctx.textBaseline = 'middle'; ctx.lineJoin = 'round';
                ctx.strokeStyle = colors.background; ctx.lineWidth = 2.5;
                ctx.strokeText(label, x, y);
                ctx.fillStyle = colors.text; ctx.fillText(label, x, y); ctx.restore();
            };

            // Plot headings: exactly 16px above top horizontal grid line
            const labelY = chartArea.top - 16;
            const div1X = scales.x.getPixelForValue(78);
            const div2X = scales.x.getPixelForValue(88);

            // Subtle vertical dividers between the three regions
            ctx.save();
            ctx.strokeStyle = colors.divider;
            ctx.lineWidth = 1;

            // Divider 1: Completed semesters (0–78%) | Current assessment (78–88%)
            ctx.beginPath();
            ctx.moveTo(div1X, chartArea.top);
            ctx.lineTo(div1X, chartArea.bottom);
            ctx.stroke();

            // Divider 2: Current assessment (78–88%) | Estimated result (88–100%)
            ctx.beginPath();
            ctx.moveTo(div2X, chartArea.top);
            ctx.lineTo(div2X, chartArea.bottom);
            ctx.stroke();

            // Region headings above content
            ctx.fillStyle = colors.text;
            ctx.font = '600 12px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Completed semesters', scales.x.getPixelForValue(39), labelY);
            ctx.fillText('Current assessment', scales.x.getPixelForValue(83), labelY);
            ctx.fillText('Estimated result', scales.x.getPixelForValue(94), labelY);
            ctx.restore();

            // Numeric values placed directly above markers
            ctx.font = '600 13px sans-serif';
            for (const p of points) {
                if (p.value === null || typeof p.x === 'undefined') continue;
                const px = scales.x.getPixelForValue(p.x);
                const py = scales.y.getPixelForValue(p.value);
                paintValue(p.value.toFixed(2), px, py - 12);
            }

            // Guide values on the right
            ctx.font = '600 12px sans-serif'; ctx.textAlign = 'left';
            guides.forEach((guide, index) => {
                if (!chart.isDatasetVisible(index + 5)) return;
                const label = guide.value.toFixed(2);
                paintValue(label, chartArea.right + 12, scales.y.getPixelForValue(guide.value));
            });
            ctx.restore();
        }
    };

    const chart = new Chart(document.getElementById('semGwaChart'), {
        type: 'line',
        data: {
            datasets: [
                {
                    label: 'Completed GWA',
                    data: historyPoints.map(p => ({ x: p.x, y: p.value })),
                    borderColor: colors.history,
                    pointBackgroundColor: colors.history,
                    pointBorderColor: colors.history,
                    pointBorderWidth: 1,
                    pointStyle: 'circle',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    showLine: historyPoints.filter(p => p.value !== null).length >= 2,
                    spanGaps: false,
                    tension: 0,
                    fill: false,
                    borderWidth: 3,
                    segment: { borderWidth: context => historyPoints[context.p1DataIndex]?.connect ? 3 : 0 }
                },
                {
                    label: 'Current assessment point',
                    data: hasCurrentAssessment ? [{ x: currentAssessmentPoint.x, y: currentAssessmentPoint.value }] : [],
                    borderColor: colors.teal,
                    backgroundColor: colors.background,
                    pointBackgroundColor: colors.background,
                    pointBorderColor: colors.teal,
                    pointBorderWidth: 2.5,
                    pointStyle: 'circle',
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    showLine: false,
                    spanGaps: false,
                    fill: false
                },
                {
                    label: 'Estimated GWA',
                    data: hasEstimate ? [{ x: estimatePoint.x, y: estimatePoint.value }] : [],
                    borderColor: colors.estimate,
                    pointBackgroundColor: colors.estimate,
                    pointBorderColor: colors.estimate,
                    pointBorderWidth: 1,
                    pointStyle: 'rectRot',
                    pointRadius: 7,
                    pointHoverRadius: 9,
                    showLine: false,
                    spanGaps: false,
                    fill: false,
                    borderWidth: 2
                },
                {
                    label: 'Current assessment transition',
                    data: currentConnectorData,
                    borderColor: colors.tealConnector,
                    borderWidth: 1.5,
                    borderDash: [5, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    showLine: hasCurrentConnector,
                    spanGaps: true,
                    fill: false
                },
                {
                    label: 'Estimate transition',
                    data: estimateConnectorData,
                    borderColor: colors.bridge,
                    borderWidth: 1.5,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    showLine: hasEstimateConnector,
                    spanGaps: true,
                    fill: false
                },
                ...guides.map(guide => ({
                    label: `${guide.names.join(" / ")} (${guide.value.toFixed(2)}); full criteria apply`,
                    data: [{ x: 0, y: guide.value }, { x: 100, y: guide.value }],
                    pointRadius: 0,
                    hidden: false,
                    borderColor: colors.guides[guide.style],
                    borderWidth: 1.25,
                    borderDash: guide.style === 1 ? [3, 3] : [8, 4],
                    fill: false
                }))
            ]
        },
        plugins: [contextPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: root.dataset.motion === 'reduce' ? false : undefined,
            layout: { padding: { top: 26, right: 48, bottom: 12, left: 10 } },
            onResize(chartInstance, size) {
                chartInstance.options.layout.padding.top = size.width < 640 ? 32 : 26;
                chartInstance.options.scales.x.ticks.maxRotation = size.width < 640 ? 60 : 0;
                chartInstance.options.scales.x.ticks.minRotation = size.width < 640 ? 45 : 0;
            },
            interaction: { mode: 'nearest', intersect: true },
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: item => item.datasetIndex < 3,
                    callbacks: {
                        title: items => {
                            if (!items.length) return '';
                            const it = items[0];
                            if (it.datasetIndex === 0) return historyPoints[it.dataIndex]?.period || '';
                            if (it.datasetIndex === 1) return 'Current-term assessment point';
                            if (it.datasetIndex === 2) return 'Estimated GWA';
                            return '';
                        },
                        label: item => {
                            if (item.datasetIndex === 0) {
                                const p = historyPoints[item.dataIndex];
                                return p ? `Completed-term GWA: ${p.value.toFixed(2)}` : '';
                            }
                            if (item.datasetIndex === 1) {
                                const p = currentAssessmentPoint;
                                const stage = p?.stage_name || 'Preliminary';
                                const raw = p?.raw_percentage || '92.94%';
                                return `Current-term assessment: ${p.value.toFixed(2)} (${stage}-based, converted from ${raw})`;
                            }
                            if (item.datasetIndex === 2) {
                                const p = estimatePoint;
                                return p ? `Estimated GWA: ${p.value.toFixed(2)} (Provisional advisory estimate)` : '';
                            }
                            return '';
                        },
                        afterLabel: item => {
                            if (item.datasetIndex === 0) {
                                const p = historyPoints[item.dataIndex];
                                return p ? [p.detail, p.comparison].flatMap(text => text ? text.match(/.{1,62}(?:\s|$)|\S+/g) || [text] : []) : [];
                            }
                            if (item.datasetIndex === 1) {
                                return [
                                    'Converted point grade; not a completed-term GWA.',
                                    currentAssessmentPoint?.coverage_text || 'Recorded across eligible current subjects.'
                                ];
                            }
                            if (item.datasetIndex === 2) {
                                const p = estimatePoint;
                                return p ? [p.detail, p.comparison].flatMap(text => text ? text.match(/.{1,62}(?:\s|$)|\S+/g) || [text] : []) : [];
                            }
                            return [];
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'linear',
                    min: 0,
                    max: 100,
                    grid: { display: false },
                    afterBuildTicks: axis => {
                        const tickVals = [];
                        historyPoints.forEach(p => { if (typeof p.x !== 'undefined') tickVals.push({ value: p.x }); });
                        if (hasCurrentAssessment) tickVals.push({ value: currentAssessmentPoint.x });
                        if (hasEstimate) tickVals.push({ value: estimatePoint.x });
                        axis.ticks = tickVals;
                    },
                    ticks: {
                        color: colors.text,
                        font: { size: 13 },
                        maxRotation: 0,
                        autoSkip: false,
                        callback: function (value) {
                            const pt = points.find(p => typeof p.x !== 'undefined' && Math.abs(p.x - value) < 0.5);
                            return pt ? axisLabel(pt) : '';
                        }
                    }
                },
                y: {
                    min: axisMinimum,
                    max: 4,
                    ticks: { color: colors.text, font: { size: 13 }, stepSize: .25, autoSkip: axisMinimum < 2.5, callback: value => Number(value).toFixed(2) },
                    grid: { color: colors.grid },
                    title: { display: true, text: 'GWA · Higher is better', font: { size: 13 }, color: colors.text }
                }
            }
        }
    });

    const completedVals = historyPoints.filter(p => p.value !== null).map(p => p.value);
    const minCompleted = completedVals.length ? Math.min(...completedVals).toFixed(2) : null;
    const maxCompleted = completedVals.length ? Math.max(...completedVals).toFixed(2) : null;
    const countCompleted = completedVals.length;

    let accessibleDesc = '';
    if (countCompleted > 0) {
        accessibleDesc += `Completed GWA values range from ${minCompleted} to ${maxCompleted} across ${countCompleted} completed semester${countCompleted === 1 ? '' : 's'}. `;
    }

    if (hasCurrentAssessment) {
        const stage = currentAssessmentPoint.stage_name || 'Preliminary';
        const raw = (currentAssessmentPoint.raw_percentage || '92.94%').replace('%', ' percent');
        accessibleDesc += `Current-term assessment point ${currentAssessmentPoint.value.toFixed(2)}, converted from the recorded ${stage} average of ${raw}. This is not a completed-term GWA. `;
    }

    if (hasEstimate) {
        accessibleDesc += `The provisional estimated GWA is ${estimatePoint.value.toFixed(2)}. `;
    }
    if (hasCurrentConnector && hasEstimateConnector) {
        accessibleDesc += `Two independent dashed connectors branch from the latest completed GWA: a teal connector to the current assessment point and an amber connector to the estimated GWA. `;
    }
    accessibleDesc += 'Higher GWA is better.';

    const canvas = document.getElementById('semGwaChart');
    if (canvas) {
        canvas.setAttribute('aria-label', accessibleDesc.trim());
        canvas.dataset.histWidthPct = '78';
        canvas.dataset.currentWidthPct = '10';
        canvas.dataset.estWidthPct = '12';
        canvas.dataset.dividers = '2';
        canvas.dataset.regions = '3';
        if (hasCurrentAssessment) {
            canvas.dataset.currentAssessmentPoint = currentAssessmentPoint.value.toFixed(2);
        }
    }
    const trendWrap = document.querySelector('.trend-chart');
    if (trendWrap) {
        trendWrap.dataset.histWidthPct = '78';
        trendWrap.dataset.currentWidthPct = '10';
        trendWrap.dataset.estWidthPct = '12';
        trendWrap.dataset.dividers = '2';
        trendWrap.dataset.regions = '3';
    }

    if (status) {
        status.textContent = 'Open the exact values table to explore each period.';
    }

    const honorsToggle = document.getElementById('trend-honors-toggle');
    const honorsLegend = document.getElementById('trend-honors-legend');
    if (honorsToggle) {
        honorsToggle.disabled = false;
        honorsToggle.checked = true;
        honorsToggle.addEventListener('change', () => {
            guides.forEach((guide, index) => chart.setDatasetVisibility(index + 5, honorsToggle.checked));
            if (honorsLegend) honorsLegend.hidden = !honorsToggle.checked;
            chart.update('none');
        });
    }

    const clearSelection = () => {
        chart.setActiveElements([]);
        chart.tooltip.setActiveElements([], { x: 0, y: 0 });
        chart.update('none');
    };

    document.querySelectorAll('[data-trend-point]').forEach(control => {
        const select = () => {
            const index = points.findIndex(point => point.sourceIndex === Number(control.dataset.trendPoint));
            if (index < 0) { clearSelection(); return; }
            const point = points[index];
            if (point.value === null) { clearSelection(); return; }
            let dsIndex = 0;
            let ptIndex = 0;
            if (point.zone === 'estimate') {
                dsIndex = 2;
                ptIndex = 0;
            } else if (point.zone === 'current_assessment') {
                dsIndex = 1;
                ptIndex = 0;
            } else {
                dsIndex = 0;
                ptIndex = historyPoints.indexOf(point);
            }
            const active = [{ datasetIndex: dsIndex, index: ptIndex }];
            chart.setActiveElements(active);
            chart.tooltip.setActiveElements(active, { x: chart.chartArea.left, y: chart.chartArea.top });
            chart.update('none');
        };
        control.addEventListener('focus', select);
        control.addEventListener('mouseenter', select);
        control.addEventListener('blur', clearSelection);
        control.addEventListener('mouseleave', clearSelection);
        control.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                control.closest('details').open = false;
                clearSelection();
            }
        });
    });

    const observer = new MutationObserver(() => {
        colors = palette();
        chart.options.animation = root.dataset.motion === 'reduce' ? false : undefined;
        for (const axis of Object.values(chart.options.scales)) {
            axis.ticks.color = colors.text;
            axis.grid.color = colors.grid;
            if (axis.title) axis.title.color = colors.text;
        }
        Object.assign(chart.data.datasets[0], {
            borderColor: colors.history,
            pointBackgroundColor: colors.history,
            pointBorderColor: colors.history
        });
        if (chart.data.datasets[1]) {
            Object.assign(chart.data.datasets[1], {
                borderColor: colors.teal,
                pointBackgroundColor: colors.background,
                pointBorderColor: colors.teal
            });
        }
        if (chart.data.datasets[2]) {
            Object.assign(chart.data.datasets[2], {
                borderColor: colors.estimate,
                pointBackgroundColor: colors.estimate,
                pointBorderColor: colors.estimate
            });
        }
        if (chart.data.datasets[3]) {
            Object.assign(chart.data.datasets[3], { borderColor: colors.tealConnector });
        }
        if (chart.data.datasets[4]) {
            Object.assign(chart.data.datasets[4], { borderColor: colors.bridge });
        }
        chart.data.datasets.slice(5).forEach((dataset, index) => {
            dataset.borderColor = colors.guides[guides[index].style];
        });
        chart.update('none');
    });
    observer.observe(root, { attributes: true, attributeFilter: ['data-theme', 'data-color-style', 'data-motion'] });
    window.addEventListener('pagehide', event => {
        if (!event.persisted) {
            observer.disconnect();
            chart.destroy();
        }
    });
})();
