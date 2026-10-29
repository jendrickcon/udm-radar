(() => {
    'use strict';
    const points = JSON.parse(document.getElementById('student-trend-data').textContent);
    const status = document.getElementById('trend-chart-status');
    if (typeof Chart === 'undefined') { status.textContent = 'Chart unavailable. All values and point details are in the table below.'; return; }
    const root = document.documentElement;
    const palette = () => {
        const css = getComputedStyle(root);
        return {text:css.getPropertyValue('--text-dark').trim(),grid:css.getPropertyValue('--border-color').trim(),
            history:css.getPropertyValue('--accent-blue').trim(),current:root.dataset.theme === 'dark' ? '#5eead4' : '#0f766e',
            estimate:root.dataset.theme === 'dark' ? '#fbbf24' : '#92400e'};
    };
    let colors = palette();
    const zones = ['history','current','estimate'];
    const zoneNames = {history:'Completed history',current:'In-progress snapshots',estimate:'Stored estimate'};
    const latest = zone => points.reduce((last,p,i) => p.zone === zone && p.value !== null ? i : last, -1);
    const keyLabels = new Set(zones.map(latest).filter(i => i >= 0));
    const guides = [3.25,3.50,3.75];
    const contextPlugin = {
        id:'studentEvidenceContext',
        afterDatasetsDraw(chart) {
            const {ctx,chartArea,scales} = chart;
            ctx.save(); ctx.fillStyle = colors.text; ctx.font = '600 12px sans-serif'; ctx.textAlign = 'center';
            const headingBoxes = [];
            for (const zone of zones) {
                const indices = points.map((p,i) => p.zone === zone ? i : -1).filter(i => i >= 0);
                if (!indices.length) continue;
                const left = scales.x.getPixelForValue(indices[0]), right = scales.x.getPixelForValue(indices.at(-1));
                const label = zoneNames[zone];
                const width = ctx.measureText(label).width;
                const labelX = Math.max(chartArea.left+width/2,Math.min((left+right)/2,chartArea.right-width/2));
                let labelY = chartArea.top-22;
                while (headingBoxes.some(box => labelX-width/2 < box.right && labelX+width/2 > box.left && labelY-12 < box.bottom && labelY+3 > box.top)) labelY -= 18;
                headingBoxes.push({left:labelX-width/2,right:labelX+width/2,top:labelY-12,bottom:labelY+3});
                ctx.fillText(label,labelX,labelY);
                if (zone !== 'history') {
                    const edge = Math.max(chartArea.left,left - (scales.x.getPixelForValue(1)-scales.x.getPixelForValue(0))/2);
                    ctx.strokeStyle=colors.grid;ctx.setLineDash([3,4]);ctx.beginPath();ctx.moveTo(edge,chartArea.top);ctx.lineTo(edge,chartArea.bottom);ctx.stroke();ctx.setLineDash([]);
                }
            }
            const occupied = [];
            for (const index of keyLabels) {
                const dataset = zones.indexOf(points[index].zone);
                const marker = chart.getDatasetMeta(dataset).data[index];
                if (!marker) continue;
                const label = points[index].value.toFixed(2), width = ctx.measureText(label).width+12;
                const x = Math.max(chartArea.left+width/2, Math.min(marker.x,chartArea.right-width/2));
                const y = marker.y < chartArea.top+30 ? marker.y+24 : marker.y-16;
                const box = {left:x-width/2,right:x+width/2,top:y-12,bottom:y+5};
                if (occupied.some(b => box.left<b.right && box.right>b.left && box.top<b.bottom && box.bottom>b.top)) continue;
                occupied.push(box);ctx.fillStyle=colors.text;ctx.fillText(label,x,y);
            }
            ctx.restore();
        }
    };
    const chart = new Chart(document.getElementById('semGwaChart'), {
        type:'line',
        data:{labels:points.map(p=>p.zone==='history'?p.period.replace('SY ','').replace(' — ','\n'):p.type.replace('Current ','').replace('Estimated semester GWA','Stored estimate')),
            datasets:[...zones.map(zone=>({label:zoneNames[zone],data:points.map(p=>p.zone===zone?p.value:null),
                borderColor:colors[zone],pointBackgroundColor:colors[zone],pointBorderColor:colors[zone],
                pointStyle:zone==='history'?'circle':zone==='current'?'rect':'rectRot',pointRadius:6,pointHoverRadius:8,
                showLine:zone==='history' && points.filter(p=>p.zone==='history'&&p.value!==null).length>=2,
                spanGaps:false,tension:0,fill:false,borderWidth:2,
                segment:{borderWidth:context=>points[context.p1DataIndex].connect?2:0}})),
                ...guides.map(value=>({label:`Distinction reference ${value.toFixed(2)}; full criteria apply`,data:points.map(()=>value),pointRadius:0,borderColor:colors.grid,borderWidth:1,borderDash:[4,5],fill:false}))]},
        plugins:[contextPlugin],
        options:{responsive:true,maintainAspectRatio:false,animation:root.dataset.motion==='reduce'?false:undefined,
            layout:{padding:{top:64,right:12}},interaction:{mode:'nearest',intersect:true},
            plugins:{legend:{display:false},tooltip:{filter:item=>item.datasetIndex<3,
                callbacks:{title:items=>items.length?points[items[0].dataIndex].period:'',
                    label:item=>`${points[item.dataIndex].type}: ${points[item.dataIndex].value.toFixed(2)}`,
                    afterLabel:item=>[points[item.dataIndex].detail,points[item.dataIndex].comparison].flatMap(text=>text.match(/.{1,62}(?:\s|$)|\S+/g)||[text])}}},
            scales:{x:{ticks:{color:colors.text,maxRotation:0,autoSkip:true},grid:{color:colors.grid}},
                y:{min:0,max:4,ticks:{color:colors.text,stepSize:.5},grid:{color:colors.grid},title:{display:true,text:'GWA / snapshot point scale (4.00 highest)',color:colors.text}}}}
    });
    status.textContent = 'Focus or expand a period in the exact evidence table for the same point details. Missing values remain gaps.';
    const clearSelection = () => { chart.setActiveElements([]);chart.tooltip.setActiveElements([],{x:0,y:0});chart.update('none'); };
    document.querySelectorAll('[data-trend-point]').forEach(control=>{
        const select = () => {
            const index=Number(control.dataset.trendPoint), point=points[index];
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
        zones.forEach((zone,index)=>{Object.assign(chart.data.datasets[index],{borderColor:colors[zone],pointBackgroundColor:colors[zone],pointBorderColor:colors[zone]});});
        chart.data.datasets.slice(3).forEach(dataset=>dataset.borderColor=colors.grid);chart.update('none');
    });
    observer.observe(root,{attributes:true,attributeFilter:['data-theme','data-color-style','data-motion']});
    window.addEventListener('pagehide',event=>{if(!event.persisted){observer.disconnect();chart.destroy();}});
})();