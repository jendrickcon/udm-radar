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
    const latest = zone => points.reduce((last,p,i) => p.zone === zone && p.value !== null ? i : last, -1);
    const keyLabels = new Set([...['estimate','history'].map(latest).filter(i => i >= 0), ...points.map((p,i)=>p.zone==='history'&&p.value!==null?i:-1).filter(i=>i>=0)]);
    const lastHistoryIndex = points.reduce((last, p, i) => p.zone === 'history' && p.value !== null ? i : last, -1);
    const estimateIndex = points.findIndex(p => p.zone === 'estimate' && p.value !== null);
    const hasBridge = lastHistoryIndex >= 0 && estimateIndex >= 0;
    const bridgeData = points.map((p, i) => (i === lastHistoryIndex || i === estimateIndex) ? p.value : null);

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
            ctx.save(); ctx.fillStyle = colors.text; ctx.font = '600 18px sans-serif'; ctx.textAlign = 'center';
            const compact = chart.width < 640;
            const paintValue = (label,x,y) => {
                // Canvas equivalent of paint-order: stroke fill; no guide-erasing boxes.
                ctx.save(); ctx.textBaseline='middle'; ctx.lineJoin='round';
                ctx.strokeStyle=colors.background; ctx.lineWidth=2.5;
                ctx.strokeText(label,x,y);
                ctx.fillStyle=colors.text; ctx.fillText(label,x,y); ctx.restore();
            };
            const headingBoxes = [];
            for (const zone of zones) {
                const indices = points.map((p,i) => p.zone === zone ? i : -1).filter(i => i >= 0);
                if (!indices.length) continue;
                const left = scales.x.getPixelForValue(indices[0]), right = scales.x.getPixelForValue(indices.at(-1));
                const label = zoneNames[zone];
                const lines = zone==='estimate' ? ['Estimated','result'] : compact ? ['Completed','semesters'] : [label];
                const width = Math.max(...lines.map(line=>ctx.measureText(line).width));
                const labelX = Math.max(chartArea.left+width/2,Math.min((left+right)/2,chart.width-8-width/2));
                let labelY = chartArea.top-(lines.length>1?40:24);
                const bottomOffset = (lines.length-1)*22+5;
                while (headingBoxes.some(box => labelX-width/2-12 < box.right && labelX+width/2+12 > box.left && labelY-30 < box.bottom && labelY+bottomOffset+12 > box.top)) labelY -= lines.length*22+16;
                headingBoxes.push({left:labelX-width/2,right:labelX+width/2,top:labelY-18,bottom:labelY+bottomOffset});
                lines.forEach((line,index)=>ctx.fillText(line,labelX,labelY+index*22));
                if (zone !== 'history') {
                    const edge = Math.max(chartArea.left,left - (scales.x.getPixelForValue(1)-scales.x.getPixelForValue(0))/2);
                    ctx.strokeStyle=colors.divider;ctx.lineWidth=1.5;ctx.beginPath();ctx.moveTo(edge,chartArea.top);ctx.lineTo(edge,chartArea.bottom);ctx.stroke();
                }
            }
            ctx.font = '600 15px sans-serif';
            const labelPlans = [];
            for (const index of keyLabels) {
                const datasetIndex = zones.indexOf(points[index].zone);
                const marker = chart.getDatasetMeta(datasetIndex).data[index];
                if (!marker) continue;
                const label = points[index].value.toFixed(2), metrics = ctx.measureText(label);
                const width = metrics.width+6;
                const height = (metrics.actualBoundingBoxAscent || 11)+(metrics.actualBoundingBoxDescent || 3);
                const x = Math.max(chartArea.left+width/2, Math.min(marker.x,chartArea.right));
                // Nine pixels of clear space from the marker edge to the text edge.
                const distance = 6+9+height/2;
                const offsets = points[index].zone==='estimate' ? [distance] : [-distance,distance];
                const candidates = [];
                for (const offset of offsets) {
                    const y=marker.y+offset;
                    for (const shift of points[index].zone==='estimate' ? [0] : [0,-12,12]) {
                        const labelX=Math.max(chartArea.left+width/2,Math.min(x+shift,chartArea.right));
                        const box={left:labelX-width/2,right:labelX+width/2,top:y-height/2-2,bottom:y+height/2+2};
                        if (box.top<chartArea.top-10 || box.bottom>chartArea.bottom) continue;
                        candidates.push({box,x:labelX,y});
                    }
                }
                labelPlans.push({label,candidates});
            }
            // Try nearby placements together so a greedy choice cannot hide a later value.
            const overlaps=(a,b)=>a.left<b.right&&a.right>b.left&&a.top<b.bottom&&a.bottom>b.top;
            const chosen=[];let attempts=0;
            const place=index=>{
                if(index===labelPlans.length)return true;
                if(++attempts>5000)return false;
                for(const candidate of labelPlans[index].candidates){
                    if(chosen.some(other=>overlaps(candidate.box,other.box)))continue;
                    chosen.push(candidate);if(place(index+1))return true;chosen.pop();
                }
                return false;
            };
            if(!place(0)){
                chosen.length=0;
                for(const plan of labelPlans)chosen.push(plan.candidates.find(candidate=>!chosen.some(other=>other&&overlaps(candidate.box,other.box)))||null);
            }
            chosen.forEach((candidate,index)=>{if(candidate)paintValue(labelPlans[index].label,candidate.x,candidate.y);});
            // Guide values stay fully readable even though the reference lines are subdued.
            ctx.font='600 13px sans-serif'; ctx.textAlign='left';
            guides.forEach((guide,index)=>{
                if (!chart.isDatasetVisible(index+3)) return;
                const label=guide.value.toFixed(2);
                paintValue(label,chartArea.right+54,scales.y.getPixelForValue(guide.value));
            });
            ctx.restore();
        }
    };
    const chart = new Chart(document.getElementById('semGwaChart'), {
        type:'line',
        data:{
            labels:points.map(axisLabel),
            datasets:[
                {
                    label:zoneNames.history,
                    data:points.map(p=>p.zone==='history'?p.value:null),
                    borderColor:colors.history,pointBackgroundColor:colors.history,pointBorderColor:colors.history,
                    pointBorderWidth:1,pointStyle:'circle',pointRadius:6,pointHoverRadius:8,
                    showLine:points.filter(p=>p.zone==='history'&&p.value!==null).length>=2,
                    spanGaps:false,tension:0,fill:false,borderWidth:3,
                    segment:{borderWidth:context=>points[context.p1DataIndex]?.connect && points[context.p1DataIndex]?.sourceIndex===points[context.p0DataIndex]?.sourceIndex+1?3:0}
                },
                {
                    label:zoneNames.estimate,
                    data:points.map(p=>p.zone==='estimate'?p.value:null),
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
                    data:points.map(()=>guide.value),pointRadius:0,hidden:false,
                    borderColor:colors.guides[guide.style],borderWidth:1.25,borderDash:guide.style===1?[3,3]:[8,4],fill:false
                }))
            ]
        },
        plugins:[contextPlugin],
        options:{
            responsive:true,maintainAspectRatio:false,animation:root.dataset.motion==='reduce'?false:undefined,
            layout:{padding:{top:100,right:104}},
            onResize(chart,size) {
                chart.options.layout.padding.top=size.width<640?184:100;
                chart.options.scales.x.ticks.maxRotation=size.width<640?60:0;
                chart.options.scales.x.ticks.minRotation=size.width<640?45:0;
            },
            interaction:{mode:'nearest',intersect:true},
            plugins:{
                legend:{display:false},
                tooltip:{
                    filter:item=>item.datasetIndex<2,
                    callbacks:{
                        title:items=>items.length?(points[items[0].dataIndex].zone==='estimate'?'Estimated GWA':points[items[0].dataIndex].period):'',
                        label:item=>{
                            const p=points[item.dataIndex];
                            if(p.zone==='estimate'){
                                return `Estimated GWA: ${p.value.toFixed(2)} (Provisional advisory estimate)`;
                            }
                            return `${pointLabel(points[item.dataIndex])}: ${p.value.toFixed(2)}`;
                        },
                        afterLabel:item=>[points[item.dataIndex].detail,points[item.dataIndex].comparison].flatMap(text=>text?text.match(/.{1,62}(?:\s|$)|\S+/g)||[text]:[])
                    }
                }
            },
            scales:{
                x:{ticks:{color:colors.text,font:{size:14},maxRotation:0,autoSkip:false},grid:{display:false}},
                y:{min:axisMinimum,max:4,ticks:{color:colors.text,font:{size:14},stepSize:.25,autoSkip:axisMinimum<2.5,callback:value=>Number(value).toFixed(2)},grid:{color:colors.grid},title:{display:true,text:'GWA · Higher is better',font:{size:14},color:colors.text}}
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
            const active=[{datasetIndex:zones.indexOf(point.zone),index}];
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
