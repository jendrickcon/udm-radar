(() => {
    'use strict';
    const allPoints = JSON.parse(document.getElementById('student-trend-data').textContent);
    // Exclude pending history placeholder for current term and filter out interim snapshots from GWA axis
    const points = allPoints.map((point,sourceIndex)=>({...point,sourceIndex})).filter(point => {
        if (point.zone === 'history' && point.state === 'Pending') return false;
        if (point.zone === 'current') return false;
        return true;
    });
    const pointLabel = point => point.type.replace(' snapshot',' progress');
    const status = document.getElementById('trend-chart-status');
    if (!points.some(point => point.value !== null)) {
        document.querySelector('.trend-chart').hidden = true;
        status.textContent = 'No chart is available because there are no dated completed results, current progress values, or stored estimates to plot. Available record details remain in the exact values table below.';
        return;
    }
    if (typeof Chart === 'undefined') { status.textContent = 'Chart unavailable. All values and point details are in the exact values table below.'; return; }
    const root = document.documentElement;
    const palette = () => {
        const css = getComputedStyle(root);
        const guideStyle = getComputedStyle(document.querySelector('.student-performance-trend'));
        const isDark = root.dataset.theme === 'dark';
        return {
            text: isDark ? '#f8fafc' : '#0f172a',
            background: css.getPropertyValue('--card-bg').trim(),
            divider: isDark ? '#94a3b8' : '#64748b',
            grid: css.getPropertyValue('--border-color').trim(),
            history: css.getPropertyValue('--accent-blue').trim(),
            estimate: isDark ? '#fbbf24' : '#d97706',
            bridge: isDark ? 'rgba(251, 191, 36, 0.45)' : 'rgba(217, 119, 6, 0.45)',
            guides: [0,1,2,3].map(index => guideStyle.getPropertyValue('--trend-guide-' + index).trim())
        };
    };
    let colors = palette();
    const zones = ['history','estimate'];
    const zoneNames = {history:'Completed semesters',estimate:'Estimated result'};

    const historyPoints = points.filter(p => p.zone === 'history');
    const estimatePoint = points.find(p => p.zone === 'estimate');
    const hasEstimate = estimatePoint && estimatePoint.value !== null;
    const numHistory = historyPoints.length;

    // Completed-semester region: 84% to 88% (85%); estimated-result region: 12% to 16% (15%)
    const histMaxX = hasEstimate ? 85 : 100;
    historyPoints.forEach((p, i) => {
        p.x = numHistory > 1 ? (i / (numHistory - 1)) * histMaxX : (hasEstimate ? 42.5 : 50);
    });
    if (estimatePoint) {
        estimatePoint.x = 100;
    }

    const lastHistoryIndex = points.reduce((last, p, i) => p.zone === 'history' && p.value !== null ? i : last, -1);
    const estimateIndex = points.findIndex(p => p.zone === 'estimate' && p.value !== null);
    const hasBridge = lastHistoryIndex >= 0 && estimateIndex >= 0;
    const bridgeData = hasBridge ? [
        { x: points[lastHistoryIndex].x, y: points[lastHistoryIndex].value },
        { x: estimatePoint.x, y: estimatePoint.value }
    ] : [];

    // Never crop a genuine low/zero result merely to tighten the usual GWA view.
    const lowestValue = Math.min(2.5, ...points.filter(p=>p.value!==null).map(p=>p.value));
    const axisMinimum = Math.max(0, Math.floor((lowestValue < 2.5 ? lowestValue - .1 : 2.5) * 4) / 4);
    const axisLabel = point => {
        if (point.zone !== 'history') return pointLabel(point).replace('Current ','').replace('Estimated semester GWA','Estimated GWA');
        const [year,semester] = point.key.split('|');
        const shortYear = year.replace(/^(\d{4})-(\d{2})(\d{2})$/, '$1–$3');
        return [shortYear, semester==='3'?'Summer':`Sem ${semester}`];
    };
    const guides = JSON.parse(document.getElementById('student-trend-guides').textContent);

    const contextPlugin = {
        id:'studentEvidenceContext',
        beforeDatasetDraw(chart, args) {
            if (args.index >= 3) { chart.ctx.save(); chart.ctx.globalAlpha = .45; }
        },
        afterDatasetDraw(chart, args) {
            if (args.index >= 3) chart.ctx.restore();
        },
        afterDatasetsDraw(chart) {
            const {ctx,chartArea,scales} = chart;
            ctx.save(); ctx.fillStyle = colors.text; ctx.font = '600 13px sans-serif'; ctx.textAlign = 'center';

            const paintValue = (label,x,y) => {
                ctx.save(); ctx.textBaseline='middle'; ctx.lineJoin='round';
                ctx.strokeStyle=colors.background; ctx.lineWidth=2.5;
                ctx.strokeText(label,x,y);
                ctx.fillStyle=colors.text; ctx.fillText(label,x,y); ctx.restore();
            };

            // Plot heading to first horizontal grid line: exactly 16px (in 14px to 20px range)
            const labelY = chartArea.top - 16;
            const histLeft = scales.x.getPixelForValue(0);
            const histRight = scales.x.getPixelForValue(hasEstimate ? 85 : 100);
            const histCenter = (histLeft + histRight) / 2;
            ctx.fillText('Completed semesters', histCenter, labelY);

            if (hasEstimate) {
                const estCenter = (scales.x.getPixelForValue(87.5) + scales.x.getPixelForValue(100)) / 2;
                ctx.fillText('Estimated result', estCenter, labelY);

                // Thin subtle vertical divider between completed-semester region and estimated-result region
                const divX = scales.x.getPixelForValue(87.5);
                ctx.strokeStyle = colors.divider;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(divX, chartArea.top);
                ctx.lineTo(divX, chartArea.bottom);
                ctx.stroke();
            }

            // Numeric values placed directly above markers
            ctx.font = '600 13px sans-serif';
            for (const p of points) {
                if (p.value === null) continue;
                const px = scales.x.getPixelForValue(p.x);
                const py = scales.y.getPixelForValue(p.value);
                paintValue(p.value.toFixed(2), px, py - 12);
            }

            // Guide values on the right
            ctx.font = '600 12px sans-serif'; ctx.textAlign = 'left';
            guides.forEach((guide,index)=>{
                if (!chart.isDatasetVisible(index+3)) return;
                const label = guide.value.toFixed(2);
                paintValue(label, chartArea.right + 12, scales.y.getPixelForValue(guide.value));
            });
            ctx.restore();
        }
    };

    const chart = new Chart(document.getElementById('semGwaChart'), {
        type:'line',
        data:{
            datasets:[
                {
                    label:zoneNames.history,
                    data:historyPoints.map(p=>({ x: p.x, y: p.value })),
                    borderColor:colors.history,pointBackgroundColor:colors.history,pointBorderColor:colors.history,
                    pointBorderWidth:1,pointStyle:'circle',pointRadius:6,pointHoverRadius:8,
                    showLine:historyPoints.filter(p=>p.value!==null).length>=2,
                    spanGaps:false,tension:0,fill:false,borderWidth:3,
                    segment:{borderWidth:context=>historyPoints[context.p1DataIndex]?.connect ? 3 : 0}
                },
                {
                    label:zoneNames.estimate,
                    data:estimatePoint && estimatePoint.value !== null ? [{ x: estimatePoint.x, y: estimatePoint.value }] : [],
                    borderColor:colors.estimate,pointBackgroundColor:colors.estimate,pointBorderColor:colors.estimate,
                    pointBorderWidth:1,pointStyle:'rectRot',pointRadius:7,pointHoverRadius:9,
                    showLine:false,spanGaps:false,fill:false,borderWidth:2
                },
                {
                    label:'Projection bridge',
                    data:bridgeData,
                    borderColor:colors.bridge,borderWidth:1.5,borderDash:[6,4],
                    pointRadius:0,pointHoverRadius:0,showLine:hasBridge,spanGaps:true,fill:false
                },
                ...guides.map(guide=>({
                    label:`${guide.names.join(" / ")} (${guide.value.toFixed(2)}); full criteria apply`,
                    data:[{ x: 0, y: guide.value }, { x: 100, y: guide.value }],
                    pointRadius:0,hidden:false,
                    borderColor:colors.guides[guide.style],borderWidth:1.25,borderDash:guide.style===1?[3,3]:[8,4],fill:false
                }))
            ]
        },
        plugins:[contextPlugin],
        options:{
            responsive:true,maintainAspectRatio:false,animation:root.dataset.motion==='reduce'?false:undefined,
            layout:{padding:{top:26,right:48,bottom:6,left:6}},
            onResize(chart,size) {
                chart.options.layout.padding.top = size.width < 640 ? 32 : 26;
                chart.options.scales.x.ticks.maxRotation = size.width < 640 ? 60 : 0;
                chart.options.scales.x.ticks.minRotation = size.width < 640 ? 45 : 0;
            },
            interaction:{mode:'nearest',intersect:true},
            plugins:{
                legend:{display:false},
                tooltip:{
                    filter:item=>item.datasetIndex<2,
                    callbacks:{
                        title:items=>{
                            if (!items.length) return '';
                            const it = items[0];
                            const p = it.datasetIndex === 1 ? estimatePoint : historyPoints[it.dataIndex];
                            return p ? (p.zone === 'estimate' ? 'Estimated GWA' : p.period) : '';
                        },
                        label:item=>{
                            const p = item.datasetIndex === 1 ? estimatePoint : historyPoints[item.dataIndex];
                            if (!p) return '';
                            if(p.zone==='estimate'){
                                return `Estimated GWA: ${p.value.toFixed(2)} (Provisional advisory estimate)`;
                            }
                            return `${pointLabel(points[item.dataIndex])}: ${p.value.toFixed(2)}`;
                        },
                        afterLabel:item=>{
                            const p = item.datasetIndex === 1 ? estimatePoint : historyPoints[item.dataIndex];
                            return p ? [p.detail, p.comparison].flatMap(text=>text?text.match(/.{1,62}(?:\s|$)|\S+/g)||[text]:[]) : [];
                        }
                    }
                }
            },
            scales:{
                x:{
                    type:'linear',min:0,max:100,
                    grid:{display:false},
                    afterBuildTicks:axis=>{
                        axis.ticks = points.map(p=>({ value: p.x }));
                    },
                    ticks:{
                        color:colors.text,font:{size:13},maxRotation:0,autoSkip:false,
                        callback:function(value){
                            const pt = points.find(p=>Math.abs(p.x - value) < 0.5);
                            return pt ? axisLabel(pt) : '';
                        }
                    }
                },
                y:{
                    min:axisMinimum,max:4,
                    ticks:{color:colors.text,font:{size:13},stepSize:.25,autoSkip:axisMinimum<2.5,callback:value=>Number(value).toFixed(2)},
                    grid:{color:colors.grid},
                    title:{display:true,text:'GWA · Higher is better',font:{size:13},color:colors.text}
                }
            }
        }
    });

    if (hasBridge) {
        const lastVal = points[lastHistoryIndex].value.toFixed(2);
        const estVal = points[estimateIndex].value.toFixed(2);
        const canvas = document.getElementById('semGwaChart');
        if (canvas) {
            canvas.setAttribute('aria-label', `GWA evidence chart showing completed semesters up to ${lastVal}, with a projection bridge to the provisional estimated GWA of ${estVal}. Higher GWA is better.`);
        }
    }
    status.textContent = 'Open the exact values table to explore each period.';
    const honorsToggle = document.getElementById('trend-honors-toggle');
    const honorsLegend = document.getElementById('trend-honors-legend');
    if (honorsToggle) {
        honorsToggle.disabled = false;
        honorsToggle.checked = true;
        honorsToggle.addEventListener('change', () => {
            guides.forEach((guide,index) => chart.setDatasetVisibility(index+3,honorsToggle.checked));
            if (honorsLegend) honorsLegend.hidden = !honorsToggle.checked;
            chart.update('none');
        });
    }
    const clearSelection = () => { chart.setActiveElements([]);chart.tooltip.setActiveElements([],{x:0,y:0});chart.update('none'); };
    document.querySelectorAll('[data-trend-point]').forEach(control=>{
        const select = () => {
            const index=points.findIndex(point=>point.sourceIndex===Number(control.dataset.trendPoint));
            if(index<0){clearSelection();return;}
            const point=points[index];
            if (point.value===null) {clearSelection();return;}
            const dsIndex = point.zone === 'estimate' ? 1 : 0;
            const ptIndex = point.zone === 'estimate' ? 0 : historyPoints.indexOf(point);
            const active=[{datasetIndex:dsIndex,index:ptIndex}];
            chart.setActiveElements(active);chart.tooltip.setActiveElements(active,{x:chart.chartArea.left,y:chart.chartArea.top});chart.update('none');
        };
        control.addEventListener('focus',select);control.addEventListener('mouseenter',select);
        control.addEventListener('blur',clearSelection);control.addEventListener('mouseleave',clearSelection);
        control.addEventListener('keydown',event=>{if(event.key==='Escape'){control.closest('details').open=false;clearSelection();}});
    });
    const observer = new MutationObserver(()=>{
        colors=palette();chart.options.animation=root.dataset.motion==='reduce'?false:undefined;
        for (const axis of Object.values(chart.options.scales)) {axis.ticks.color=colors.text;axis.grid.color=colors.grid;if(axis.title)axis.title.color=colors.text;}
        Object.assign(chart.data.datasets[0],{borderColor:colors.history,pointBackgroundColor:colors.history,pointBorderColor:colors.history});
        Object.assign(chart.data.datasets[1],{borderColor:colors.estimate,pointBackgroundColor:colors.estimate,pointBorderColor:colors.estimate});
        if(chart.data.datasets[2]){
            Object.assign(chart.data.datasets[2],{borderColor:colors.bridge});
        }
        chart.data.datasets.slice(3).forEach((dataset,index)=>dataset.borderColor=colors.guides[guides[index].style]);chart.update('none');
    });
    observer.observe(root,{attributes:true,attributeFilter:['data-theme','data-color-style','data-motion']});
    window.addEventListener('pagehide',event=>{if(!event.persisted){observer.disconnect();chart.destroy();}});
})();
