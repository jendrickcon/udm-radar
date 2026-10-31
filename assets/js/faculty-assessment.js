(() => {
    'use strict';
    const payload = document.getElementById('faculty-assessment-data');
    if (!payload) return;
    const data = JSON.parse(payload.textContent);
    const status = document.getElementById('faculty-chart-status');
    if (typeof Chart === 'undefined') {
        status.textContent = 'Charts unavailable. Recorded averages, coverage and risk counts remain in the exact tables below.';
        return;
    }
    const stages = Object.values(data.stages);
    const labels = [...stages.map(s => s.count ? s.label : s.label + ' · Not recorded'), data.final.recorded ? 'Final · grade points' : 'Final · Not recorded'];
    const palette = () => {
        const s = getComputedStyle(document.documentElement), dark = document.documentElement.dataset.theme === 'dark';
        return {text:s.getPropertyValue('--text-dark').trim(),grid:s.getPropertyValue('--border-color').trim(),blue:s.getPropertyValue('--accent-blue').trim(),high:dark?'#f87171':'#b91c1c',moderate:dark?'#fbbf24':'#92400e',low:dark?'#34d399':'#047857',none:dark?'#94a3b8':'#64748b'};
    };
    let colors = palette();
    const reduced = () => document.documentElement.dataset.motion === 'reduce' || matchMedia('(prefers-reduced-motion: reduce)').matches;
    const line = new Chart(document.getElementById('progressionChart'), {
        type:'line', data:{labels,datasets:[{label:'Recorded assessment average (%)',data:[...stages.map(s=>s.mean),null],borderColor:colors.blue,pointBackgroundColor:colors.blue,borderWidth:2,pointRadius:5,spanGaps:false,tension:0,fill:false}]},
        options:{responsive:true,maintainAspectRatio:false,animation:!reduced(),plugins:{legend:{display:false},tooltip:{callbacks:{label:item=>item.parsed.y.toFixed(2)+'%'}}},scales:{x:{ticks:{color:colors.text},grid:{display:false}},y:{min:0,max:100,ticks:{color:colors.text,callback:v=>v+'%'},grid:{color:colors.grid},title:{display:true,text:'Recorded percentage · 0–100%',color:colors.text}}}}
    });
    const risks = [...stages.map(s=>s.risk),data.final.risk];
    const keys = ['HIGH','MODERATE','LOW','NONE'];
    const names = ['High Risk','Moderate Risk','Low Risk','No Data'];
    const risk = new Chart(document.getElementById('riskMovementChart'), {
        type:'bar',data:{labels,datasets:keys.map((key,i)=>({label:names[i],data:risks.map(s=>s===null?null:s[key]),backgroundColor:[colors.high,colors.moderate,colors.low,colors.none][i],stack:'risk'}))},
        options:{responsive:true,maintainAspectRatio:false,animation:!reduced(),plugins:{legend:{position:'bottom',labels:{color:colors.text,usePointStyle:true,boxWidth:8}}},scales:{x:{stacked:true,ticks:{color:colors.text},grid:{display:false}},y:{stacked:true,min:0,ticks:{stepSize:1,color:colors.text},grid:{color:colors.grid},title:{display:true,text:'Student records · advisory risk',color:colors.text}}}}
    });
    status.textContent = stages.some(s=>s.mean!==null) ? 'Percentage averages and exact recording counts are listed below. Final points are separate.' : 'No assessment percentages are recorded. Current record counts and finalized outcomes remain below.';
    const refresh = () => {
        colors = palette();
        for (const chart of [line,risk]) {
            chart.options.animation = !reduced();
            chart.options.scales.x.ticks.color = colors.text;
            chart.options.scales.y.ticks.color = colors.text;
            chart.options.scales.y.grid.color = colors.grid;
            chart.options.scales.y.title.color = colors.text;
        }
        line.data.datasets[0].borderColor = line.data.datasets[0].pointBackgroundColor = colors.blue;
        risk.data.datasets.forEach((s,i)=>s.backgroundColor=[colors.high,colors.moderate,colors.low,colors.none][i]);
        risk.options.plugins.legend.labels.color=colors.text;
        line.update('none');risk.update('none');
    };
    new MutationObserver(refresh).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme','data-color-style','data-motion']});
    matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change',refresh);
})();
