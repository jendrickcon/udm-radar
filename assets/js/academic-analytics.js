(() => {
 'use strict';
 const root=document.getElementById('academic-analytics');if(!root)return;
 const role=root.dataset.role, form=document.getElementById('analytics-filters'), results=document.getElementById('analytics-results'), state=document.getElementById('analytics-state');
 const labels={school_year:'School year',semester:'Semester',year_level:'Curriculum year',subject:'Subject',section:'Section',faculty:'Faculty',case_status:'Case status',decision:'Referral status'};
 const dimensions={semester:'Semester',school_year:'School year',year_level:'Curriculum year',subject:'Subject',section:'Section',faculty_subject:'Faculty assignment'};
 const dimensionMetrics={
  semester:[
   {value:'gwa',label:'Recorded class average'},
   {value:'student_count',label:'Student count'},
   {value:'numeric_coverage_pct',label:'Numeric grade coverage'},
   {value:'final_coverage_pct',label:'Final-outcome coverage'},
   {value:'prediction_coverage_pct',label:'Prediction coverage'},
   {value:'risk_distribution',label:'Advisory-risk distribution'}
  ],
  school_year:[
   {value:'gwa',label:'Recorded class average'},
   {value:'student_count',label:'Student count'},
   {value:'numeric_coverage_pct',label:'Numeric grade coverage'},
   {value:'final_coverage_pct',label:'Final-outcome coverage'},
   {value:'prediction_coverage_pct',label:'Prediction coverage'},
   {value:'risk_distribution',label:'Advisory-risk distribution'}
  ],
  year_level:[
   {value:'gwa',label:'Recorded class average'},
   {value:'student_count',label:'Student count'},
   {value:'numeric_coverage_pct',label:'Numeric grade coverage'},
   {value:'final_coverage_pct',label:'Final-outcome coverage'}
  ],
  subject:[
   {value:'gwa',label:'Recorded class average'},
   {value:'student_count',label:'Student count'},
   {value:'numeric_coverage_pct',label:'Numeric grade coverage'},
   {value:'final_coverage_pct',label:'Final-outcome coverage'}
  ],
  section:[
   {value:'gwa',label:'Recorded class average'},
   {value:'student_count',label:'Student count'},
   {value:'numeric_coverage_pct',label:'Numeric grade coverage'},
   {value:'final_coverage_pct',label:'Final-outcome coverage'},
   {value:'prediction_coverage_pct',label:'Prediction coverage'},
   {value:'risk_distribution',label:'Advisory-risk distribution'}
  ],
  faculty_subject:[
   {value:'gwa',label:'Recorded class average'},
   {value:'student_count',label:'Student count'},
   {value:'numeric_coverage_pct',label:'Numeric grade coverage'},
   {value:'final_coverage_pct',label:'Final-outcome coverage'}
  ]
 };
 let report=null,controller=null,generation=0,activeTab='overview';
 const el=(tag,text,attrs={})=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;for(const[k,v]of Object.entries(attrs))n.setAttribute(k,v);return n;};
 const num=v=>v===null||v===undefined?'N/A':Number(v).toFixed(2);
 const change=(v,suffix='')=>v===null||v===undefined?'Unavailable':(Number(v)>0?'+':'')+Number(v).toFixed(2)+suffix;
 const pct=v=>v===null?'Unavailable':num(v)+'%';

 const formatDurationHours=hours=>{
  if(hours===null||hours===undefined||hours<0)return 'Unavailable';
  if(hours<24){
   const rounded=Math.round(hours*10)/10;
   return (rounded===Math.floor(hours)?Math.floor(hours):rounded.toFixed(1))+' hours';
  }
  let days=Math.floor(hours/24);
  let remHours=Math.round(hours%24);
  if(remHours>=24){days++;remHours=0;}
  if(remHours===0)return days+(days===1?' day':' days');
  return days+(days===1?' day ':' days ')+remHours+(remHours===1?' hour':' hours');
 };

 const formatSampleSize=n=>{
  if(n===null||n===undefined||n<=0)return 'No referrals have a recorded first notice for this scope.';
  if(n===1)return 'Based on 1 referral with a recorded notice';
  return `Based on ${n} referrals with recorded notices`;
 };

 function metrics(parent,items){
  const dl=el('dl',undefined,{class:'analytics-metrics'});
  for(const[label,value,key]of items){
   const d=el('div');
   d.append(el('dt',label),el('dd',value,{'data-metric':key}));
   dl.append(d);
  }
  parent.append(dl);
 }

 function table(target,headers,rows,empty,caption){
  target.replaceChildren();
  if(caption)target.append(el('caption',caption));
  const head=el('thead'),tr=el('tr');
  headers.forEach(h=>tr.append(el('th',h,{scope:'col'})));
  head.append(tr);
  const body=el('tbody');
  if(!rows.length){
   const row=el('tr');
   row.append(el('td',empty,{colspan:headers.length}));
   body.append(row);
  }
  for(const cells of rows){
   const row=el('tr');
   for(const cell of cells){
    const td=el('td');
    td.append(cell instanceof Node?cell:document.createTextNode(String(cell)));
    row.append(td);
   }
   body.append(row);
  }
  target.append(head,body);
 }

 const svgEl=(tag,attrs={},text)=>{
  const n=document.createElementNS('http://www.w3.org/2000/svg',tag);
  Object.entries(attrs).forEach(([k,v])=>n.setAttribute(k,String(v)));
  if(text!==undefined)n.textContent=text;
  return n;
 };

 function chart(target,series,kind,title){
  target.replaceChildren();
  if(!series.length||!series.some(x=>x.gwa!==null)){
   target.append(el('p','No numeric GWA is available for this view.'));
   return;
  }
  const data=kind==='bar'?series.slice(0,12):series;
  const studentHistory=role==='student'&&target.id==='trend-chart'&&kind==='line';
  const chartWidth=studentHistory?900:640;
  const lineBase=studentHistory?225:195, lineRange=studentHistory?190:160;
  const plotSpan=studentHistory?800:550, gridRight=studentHistory?865:615;
  const height=kind==='bar'?Math.max(100,data.length*34+25):(studentHistory?280:240);
  const svg=svgEl('svg',{viewBox:`0 0 ${chartWidth} ${height}`,role:'img','aria-label':title,'aria-describedby':'active-filters'});
  if(studentHistory)svg.classList.add('student-history-chart');
  svg.append(svgEl('title',{},title),svgEl('desc',{},'Values, Student counts and coverage appear in the adjacent data table.'));
  if(kind==='bar'){
   data.forEach((p,i)=>{
    const y=i*34+10;
    const group=svgEl('g',{tabindex:0});
    group.append(svgEl('title',{},`${p.label}: ${num(p.gwa)}, n=${p.student_count}, numeric coverage ${pct(p.numeric_coverage_pct)}`));
    group.append(svgEl('text',{x:0,y:y+15},`${i+1}. ${p.label.slice(0,23)}`));
    if(p.gwa!==null)group.append(svgEl('rect',{x:210,y,width:Math.max(0,Math.min(4,p.gwa))/4*340,height:20,class:'analytics-bar'}));
    group.append(svgEl('text',{x:560,y:y+15},num(p.gwa)));
    svg.append(group);
   });
  }else{
   // Major reference grid lines (1.00 to 4.00)
   [1.0, 2.0, 3.0, 4.0].forEach(val=>{
    const y=lineBase-(val-1.0)/3.0*lineRange;
    svg.append(svgEl('line',{x1:45,x2:gridRight,y1:y,y2:y,class:'chart-grid-line chart-grid-major',stroke:'var(--chart-grid-stroke, rgba(0,0,0,0.12))','stroke-dasharray':'3 3','stroke-width':1}));
    svg.append(svgEl('text',{x:38,y:y+4,'text-anchor':'end','font-size':'12px','font-weight':'600',opacity:'0.88',fill:'currentColor',class:'chart-axis-label chart-label-major'},val.toFixed(2)));
   });

   // Minor half-step reference grid lines (1.50, 2.50, 3.50) with subordinate styling
   [1.5, 2.5, 3.5].forEach(val=>{
    const y=lineBase-(val-1.0)/3.0*lineRange;
    svg.append(svgEl('line',{x1:45,x2:gridRight,y1:y,y2:y,class:'chart-grid-line-minor',stroke:'var(--chart-grid-stroke, rgba(0,0,0,0.06))','stroke-dasharray':'1 3','stroke-width':0.75,opacity:'0.38'}));
    svg.append(svgEl('text',{x:38,y:y+3,'text-anchor':'end','font-size':'10px','font-weight':'400',opacity:'0.52',fill:'currentColor',class:'chart-axis-label chart-label-minor'},val.toFixed(2)));
   });

   // Subtle vertical guides for term alignment
   data.forEach((p,i)=>{
    const x=50+(data.length===1?0:i/(data.length-1)*plotSpan);
    svg.append(svgEl('line',{x1:x,x2:x,y1:35,y2:lineBase,class:'chart-grid-vertical',stroke:'var(--chart-grid-stroke, rgba(0,0,0,0.06))','stroke-dasharray':'2 4','stroke-width':0.75,opacity:0.5}));
   });

   // Primary axis line
   svg.append(svgEl('line',{x1:45,x2:gridRight,y1:lineBase,y2:lineBase,class:'analytics-axis',stroke:'var(--chart-axis-stroke, currentColor)'}));

   // Compact period labels and primary points/lines
   let segment=[];
   const flush=()=>{if(segment.length>1)svg.append(svgEl('polyline',{points:segment.join(' '),class:'analytics-line'}));segment=[];};
   data.forEach((p,i)=>{
    const x=50+(data.length===1?0:i/(data.length-1)*plotSpan);
    let sy='', sem='';
    if(p.label){
     const match=p.label.match(/^(\d{4}-\d{4})\s*[\u00B7\u00C2·]?\s*Sem(?:ester)?\s*([1-2])/i);
     if(match){
      sy=studentHistory?match[1].replace(/^(\d{4})-\d{2}(\d{2})$/,'$1–$2'):match[1];
      sem=(studentHistory?'Sem ':'S')+match[2];
     }else{
      sy=p.label.replace(/\s*[\u00B7\u00C2·]?\s*Semester\s*/i,' S').replace(/\s*Sem\s*/i,' S');
     }
    }else{
     sy=String(i+1);
    }
    const termY=studentHistory?249:212;
    const termFontSize=studentHistory?'9px':'12px';
    const termText=svgEl('text',{x,y:termY,'text-anchor':'middle','font-size':termFontSize,'font-weight':'600',fill:'currentColor',class:'chart-period-label'});
    termText.append(svgEl('tspan',{x,dy:0,class:'chart-period-year'},sy));
    if(sem)termText.append(svgEl('tspan',{x,dy:studentHistory?16:13,'font-size':studentHistory?'9px':'11px','font-weight':'500',fill:'var(--text-gray)',class:'chart-period-sem'},' '+sem));
    svg.append(termText);

    if(p.gwa===null){flush();return;}
    const y=lineBase-(p.gwa-1)/3*lineRange;
    segment.push(`${x},${y}`);
    const dot=svgEl('circle',{cx:x,cy:y,r:4.5,class:'analytics-dot',tabindex:0});
    const fullLabel=p.label?p.label.replace(/\s*[\u00B7\u00C2·]?\s*Semester\s*/i,', Semester '):'';
    dot.append(svgEl('title',{},`School Year ${fullLabel}: ${num(p.gwa)}${p.provisional?' (provisional)':''}, n=${p.student_count}`));
    const pointValY=y-(studentHistory?15:10);
    svg.append(dot,svgEl('text',{x,y:pointValY,'text-anchor':'middle',fill:'currentColor','font-size':'12px','font-weight':'600',class:'chart-point-val'},num(p.gwa)));
   });
   flush();
  }
  target.append(svg);
  if(kind==='bar'&&series.length>12)target.append(el('p','First 12 groups shown. The table contains all matching groups.'));
 }

 function renderPrediction(p){
  const box=document.getElementById('analytics-prediction');
  if(!box)return;
  box.replaceChildren();
  if(!p)return;
  if(role==='student'){
      const card = el('div', undefined, {class: 'card', style: 'padding: 16px 20px; border-radius: 8px; margin-bottom: 20px;'});
      const heading = el('h3', 'Advisory Classification & Suggested Action', {style: 'font-size: 1rem; font-weight: 700; margin: 0 0 10px 0; color: var(--text-dark);'});
      const statusRow = el('div', undefined, {style: 'display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin-bottom: 10px; font-size: 0.88rem;'});
      const riskPill = el('span', p.advisory_indicator || 'Advisory available', {
          class: 'status-pill',
          style: 'font-weight: 700; font-size: 0.8rem; padding: 3px 10px;'
      });
      const compSpan = el('span', `Completeness: ${p.completeness ? p.completeness.replaceAll('_', ' ') : 'Complete records'}`, {style: 'color: var(--text-gray); font-size: 0.82rem;'});
      statusRow.append(riskPill, compSpan);

      const actionP = el('p', 'Suggested next step: Review your current subjects and consider discussing remaining terms with your Faculty adviser during consultation hours.', {
          style: 'margin: 0; font-size: 0.88rem; color: var(--text-dark); line-height: 1.45;'
      });

      card.append(heading, statusRow, actionP);
      box.append(card);
      return;
  }
  box.append(el('h2','Latest stored estimate'),el('p',p.explanation));
  metrics(box,[['Estimated GWA',num(p.estimated_gwa),'estimate'],['Lower estimate',p.lower===null?'Unavailable':num(p.lower),'lower'],['Upper estimate',p.upper===null?'Unavailable':num(p.upper),'upper'],['Advisory indicator',p.advisory_indicator||'Unavailable','advisory']]);
  box.append(el('p',p.source_label),el('p',`${p.is_provisional?'Provisional · ':''}Completeness: ${p.completeness.replaceAll('_',' ')} · Generated: ${p.generated_at||'Unavailable'}`),el('p',p.range_reason));
 }

 let comparisonMode='overlay';
 let comparisonCards=[
  {id:'card-a',dimension:role==='faculty'?'subject':'faculty_subject',key:''},
  {id:'card-b',dimension:role==='faculty'?'subject':'faculty_subject',key:''}
 ];

 function getVerifiedFaculty(groups){
  return (groups['faculty_subject']||[]).filter(g=>
   !g.key.startsWith('unknown|')&&!g.label.includes('Assignment unavailable')&&!g.label.includes('Historical')
  );
 }

 function setComparisonMode(mode){
  comparisonMode=mode;
  const btnOverlay=document.getElementById('btn-mode-overlay');
  const btnCards=document.getElementById('btn-mode-cards');
  const wrapper=document.getElementById('comparisons-content-wrapper');
  const helper=document.getElementById('comparison-mode-helper');
  if(btnOverlay&&btnCards){
   const checkOverlay=btnOverlay.querySelector('.mode-check');
   const checkCards=btnCards.querySelector('.mode-check');
   if(mode==='overlay'){
    btnOverlay.classList.add('active');
    btnOverlay.setAttribute('aria-pressed','true');
    btnCards.classList.remove('active');
    btnCards.setAttribute('aria-pressed','false');
    if(checkOverlay)checkOverlay.style.display='inline';
    if(checkCards)checkCards.style.display='none';
    if(helper)helper.textContent='Show selected groups together on one shared scale.';
    if(wrapper){
     wrapper.classList.add('mode-overlay');
     wrapper.classList.remove('mode-cards');
    }
   }else{
    btnCards.classList.add('active');
    btnCards.setAttribute('aria-pressed','true');
    btnOverlay.classList.remove('active');
    btnOverlay.setAttribute('aria-pressed','false');
    if(checkCards)checkCards.style.display='inline';
    if(checkOverlay)checkOverlay.style.display='none';
    if(helper)helper.textContent='Show complete details for each selected group.';
    if(wrapper){
     wrapper.classList.add('mode-cards');
     wrapper.classList.remove('mode-overlay');
    }
   }
  }
  renderComparisons();
 }

 function addComparisonCard(){
  if(comparisonCards.length>=3)return;
  const currentDim=comparisonCards[0]?.dimension||document.getElementById('comparison-group')?.value||'faculty_subject';
  const availableGroups=currentDim==='faculty_subject'?getVerifiedFaculty(report?.groups||{}):(report?.groups[currentDim]||[]);
  const usedKeys=new Set(comparisonCards.map(c=>c.key));
  const candidate=availableGroups.find(g=>!usedKeys.has(g.key))||availableGroups[0];
  comparisonCards.push({
   id:'card-c',
   dimension:currentDim,
   key:candidate?candidate.key:''
  });
  renderComparisons();
  const removeBtn=document.getElementById('remove-card-c');
  if(removeBtn)removeBtn.focus();
 }

 function removeComparisonCard(id){
  comparisonCards=comparisonCards.filter(c=>c.id!==id);
  renderComparisons();
  const addBtn=document.getElementById('add-comparison-card-btn');
  if(addBtn)addBtn.focus();
 }

 function renderComparisonChart(target, activeItemsOrGroupA, maybeGroupBOrMetric, maybeMetricOrTitle, maybeTitle){
  target.replaceChildren();
  let activeItems=[];
  let metric='gwa';
  let title='Visual Comparison';

  if(Array.isArray(activeItemsOrGroupA)){
   activeItems=activeItemsOrGroupA;
   metric=maybeGroupBOrMetric||'gwa';
   title=maybeMetricOrTitle||'Visual Comparison';
  }else{
   const gA=activeItemsOrGroupA;
   const gB=maybeGroupBOrMetric;
   if(gA)activeItems.push({card:{dimension:''},group:gA,tag:'Compare A'});
   if(gB)activeItems.push({card:{dimension:''},group:gB,tag:'Compare B'});
   metric=maybeMetricOrTitle||'gwa';
   title=maybeTitle||'Visual Comparison';
  }

  if(!activeItems.length||activeItems.every(it=>!it.group)){
   target.append(el('p','No comparison records match these filters.'));
   return;
  }

  const svg=svgEl('svg',{viewBox:'0 0 600 200',role:'img','aria-label':title,'aria-describedby':'active-filters',style:'width:100%;max-width:100%;display:block;'});
  svg.append(svgEl('title',{},title),svgEl('desc',{},'Side-by-side visual comparison with matched axis and scale. Exact values appear in the tables below.'));

  const yBottom=145, hPlot=95;
  svg.append(svgEl('line',{x1:55,x2:565,y1:yBottom,y2:yBottom,class:'analytics-axis',stroke:'var(--chart-axis-stroke)'}));

  if(metric==='gwa'){
   [1.0, 2.0, 3.0, 4.0].forEach(val=>{
    const y=yBottom-(val-1.0)/3.0*hPlot;
    svg.append(svgEl('line',{x1:55,x2:565,y1:y,y2:y,class:'chart-grid-line',stroke:'var(--chart-grid-stroke)','stroke-dasharray':'3 3','stroke-width':1}));
    svg.append(svgEl('text',{x:48,y:y+4,'text-anchor':'end','font-size':'11px',fill:'currentColor'},val.toFixed(2)));
   });
  }else if(metric==='numeric_coverage_pct'||metric==='final_coverage_pct'||metric==='prediction_coverage_pct'){
   [0, 25, 50, 75, 100].forEach(val=>{
    const y=yBottom-val/100*hPlot;
    svg.append(svgEl('line',{x1:55,x2:565,y1:y,y2:y,class:'chart-grid-line',stroke:'var(--chart-grid-stroke)','stroke-dasharray':'3 3','stroke-width':1}));
    svg.append(svgEl('text',{x:48,y:y+4,'text-anchor':'end','font-size':'11px',fill:'currentColor'},val+'%'));
   });
  }else{
   const maxCount=Math.max(10,...activeItems.map(item=>item.group?.student_count||0));
   const step=Math.ceil(maxCount/4);
   for(let val=0;val<=step*4;val+=step){
    const y=yBottom-(val/(step*4))*hPlot;
    svg.append(svgEl('line',{x1:55,x2:565,y1:y,y2:y,class:'chart-grid-line',stroke:'var(--chart-grid-stroke)','stroke-dasharray':'3 3','stroke-width':1}));
    svg.append(svgEl('text',{x:48,y:y+4,'text-anchor':'end','font-size':'11px',fill:'currentColor'},String(val)));
   }
  }

  let centers=[300];
  if(activeItems.length===2)centers=[200, 420];
  else if(activeItems.length===3)centers=[150, 310, 470];

  const colWidth=activeItems.length===3?70:95;
  const colStyles=[
   {col:'var(--teal, #008080)',shape:'circle',dash:''},
   {col:'var(--accent-blue, #1e4db7)',shape:'square',dash:'4 2'},
   {col:'var(--gold, #e8b923)',shape:'triangle',dash:'2 2'}
  ];

  activeItems.forEach((item, i)=>{
   const group=item.group;
   const xCenter=centers[i];
   const x=xCenter-colWidth/2;
   const styleInfo=colStyles[i]||colStyles[0];
   const color=styleInfo.col;
   const colName=item.tag||('Compare '+['A','B','C'][i]);
   const g=svgEl('g',{tabindex:0});

   let rawVal=null, valText='Unavailable', isProv=false;
   if(group){
    isProv=group.provisional;
    if(metric==='gwa')rawVal=group.gwa;
    else if(metric==='student_count')rawVal=group.student_count;
    else if(metric==='numeric_coverage_pct')rawVal=group.numeric_coverage_pct;
    else if(metric==='final_coverage_pct')rawVal=group.final_coverage_pct;
   }

   if(rawVal!==null&&rawVal!==undefined){
    let barH=0;
    if(metric==='gwa')barH=Math.max(0,(rawVal-1.0)/3.0*hPlot);
    else if(metric==='student_count'){
     const maxCount=Math.max(10,...activeItems.map(it=>it.group?.student_count||0));
     const step=Math.ceil(maxCount/4);
     barH=Math.max(0,(rawVal/(step*4))*hPlot);
    }else{
     barH=Math.max(0,rawVal/100*hPlot);
    }
    const barY=yBottom-barH;
    valText=(metric==='gwa'?num(rawVal):(metric==='student_count'?rawVal:pct(rawVal)))+(isProv?' (provisional)':'');

    // Horizontal dashed reference guideline across to Y axis for overlay comparison
    svg.append(svgEl('line',{x1:55,x2:xCenter,y1:barY,y2:barY,stroke:color,'stroke-dasharray':'2 2','stroke-width':1,opacity:'0.5'}));

    const barAttrs={x,y:barY,width:colWidth,height:barH,fill:color,rx:4};
    if(styleInfo.dash)barAttrs['stroke-dasharray']=styleInfo.dash;
    g.append(svgEl('rect',barAttrs));

    if(styleInfo.shape==='square'){
     g.append(svgEl('rect',{x:xCenter-5,y:barY-14,width:10,height:10,fill:isProv?'none':color,stroke:color,'stroke-width':isProv?1.5:0}));
    }else if(styleInfo.shape==='triangle'){
     g.append(svgEl('polygon',{points:`${xCenter},${barY-15} ${xCenter+5},${barY-6} ${xCenter-5},${barY-6}`,fill:isProv?'none':color,stroke:color,'stroke-width':isProv?1.5:0}));
    }else{
     g.append(svgEl('circle',{cx:xCenter,cy:barY-10,r:5,fill:isProv?'none':color,stroke:color,'stroke-width':isProv?1.5:0}));
    }
    g.append(svgEl('text',{x:xCenter,y:barY-20,'text-anchor':'middle','font-weight':'700','font-size':'12px',fill:'currentColor'},metric==='gwa'?num(rawVal):(metric==='student_count'?String(rawVal):pct(rawVal))));
   }else{
    const barH=hPlot*0.25;
    const barY=yBottom-barH;
    g.append(svgEl('rect',{x,y:barY,width:colWidth,height:barH,fill:'none',stroke:color,'stroke-dasharray':'4 4','stroke-width':1.5,rx:4}));
    g.append(svgEl('circle',{cx:xCenter,cy:barY-10,r:5,fill:'none',stroke:color,'stroke-width':1.5}));
    g.append(svgEl('text',{x:xCenter,y:barY-18,'text-anchor':'middle','font-size':'11px',fill:'var(--text-gray)'},'Unavailable'));
   }

   const tagLetter=['A','B','C'][i]||String(i+1);
   let line1=`${tagLetter} · `;
   let line2='';
   const fullLabel=group?group.label:colName;
   if(fullLabel.includes(' · ')){
    const parts=fullLabel.split(' · ');
    line1+=parts[0].slice(0,14);
    line2=parts[1].slice(0,18);
   }else if(fullLabel.includes(' (')){
    const parts=fullLabel.split(' (');
    line1+=parts[0].slice(0,14);
    line2='('+parts[1].replace(')','').slice(0,16)+')';
   }else if(fullLabel.includes(' - ')){
    const parts=fullLabel.split(' - ');
    line1+=parts[0].slice(0,12);
    line2=parts[1].slice(0,18);
   }else if(fullLabel.length>16){
    line1+=fullLabel.slice(0,14);
    line2=fullLabel.slice(14,30);
   }else{
    line1+=fullLabel;
   }

   g.append(svgEl('text',{x:xCenter,y:yBottom+16,'text-anchor':'middle','font-weight':'700','font-size':'11px',fill:'currentColor'},line1));
   if(line2){
    g.append(svgEl('text',{x:xCenter,y:yBottom+28,'text-anchor':'middle','font-weight':'500','font-size':'10px',fill:'var(--text-gray)'},line2));
   }
   g.append(svgEl('title',{},`${colName}: ${group?.label||'None'} - ${valText}`));
   svg.append(g);
  });

  target.append(svg);
 }

 function formatDeltaChipHtml(diff, unit='points'){
  if(diff===null||diff===undefined||isNaN(diff)){
   return '<span class="delta-chip delta-neutral" aria-label="Change unavailable"><span aria-hidden="true">N/A</span> Change unavailable</span>';
  }
  if(Math.abs(diff)<0.0001){
   return '<span class="delta-chip delta-neutral" aria-label="No recorded change"><span aria-hidden="true">—</span> No change</span>';
  }
  const sign=diff>0?'+':'';
  const arrow=diff>0?'↑':'↓';
  const cls=diff>0?'delta-higher':'delta-lower';
  const valStr=(typeof diff==='number'&&!Number.isInteger(diff))?diff.toFixed(2):String(diff);
  const dirLabel=diff>0?'Higher than the selected comparison':'Lower than the selected comparison';
  return `<span class="delta-chip ${cls}" aria-label="${dirLabel}"><span aria-hidden="true">${arrow}</span> ${sign}${valStr} ${unit}</span>`;
 }

 function renderTrendDetails(t){
  const container=document.getElementById('trend-details-content');
  if(!container||!t||!t.series)return;
  container.replaceChildren();

  const validSeries=t.series.filter(s=>s.gwa!==null);
  const earliest=validSeries[0];
  const latest=validSeries[validSeries.length-1];
  const count=validSeries.length;

  let maxPt=null, minPt=null;
  if(validSeries.length>0){
   maxPt=validSeries.reduce((max,p)=>p.gwa>max.gwa?p:max,validSeries[0]);
   minPt=validSeries.reduce((min,p)=>p.gwa<min.gwa?p:min,validSeries[0]);
  }

  const pointChange=t.change?.point_change;
  const relChange=t.change?.relative_change_pct;

  // Block 1: Recorded periods
  const itemPeriods=el('div',undefined,{class:'trend-detail-item'});
  itemPeriods.append(
   el('h5','Recorded periods'),
   el('p',`${count} recorded semesters`,{class:'detail-val'}),
   el('p',`${t.series.length} period positions (terms recorded)`,{class:'detail-sub'})
  );

  // Block 2: Observed range
  const itemSpan=el('div',undefined,{class:'trend-detail-item'});
  const earliestLabel = earliest ? earliest.label.replace(' · Semester ',' · S').replace(' · Sem ',' · S') : 'None';
  const latestLabel = latest ? latest.label.replace(' · Semester ',' · S').replace(' · Sem ',' · S') : 'None';
  itemSpan.append(
   el('h5','Observed range'),
   el('p',`${earliestLabel} to ${latestLabel}`,{class:'detail-val','style':'font-size:0.88rem;'}),
   el('p','Ordered historical sequence',{class:'detail-sub'})
  );

  // Block 3: Current change (consolidated single card)
  const itemChange=el('div',undefined,{class:'trend-detail-item'});
  if(pointChange === null || pointChange === undefined || isNaN(pointChange)){
   itemChange.append(
    el('h5','Current change'),
    el('p','Unavailable',{class:'detail-val delta-chip delta-neutral'}),
    el('p','Current or previous term is incomplete',{class:'detail-sub'})
   );
  } else {
   const chipSpan=document.createElement('div');
   chipSpan.innerHTML=formatDeltaChipHtml(pointChange,'grade points');
   const relText = relChange !== null && relChange !== undefined ? `${relChange > 0 ? '+' : ''}${relChange.toFixed(1)}%` : 'Unavailable';
   itemChange.append(
    el('h5','Current change'),
    chipSpan,
    el('p',`Relative: ${relText}`,{class:'detail-sub'})
   );
  }

  // Block 4: Highest and lowest
  const itemHighLow=el('div',undefined,{class:'trend-detail-item'});
  const hlText=maxPt&&minPt?`High: ${num(maxPt.gwa)} · Low: ${num(minPt.gwa)}`:'Unavailable';
  itemHighLow.append(
   el('h5','Highest & Lowest recorded'),
   el('p',hlText,{class:'detail-val','style':'font-size:0.92rem;'}),
   el('p','On active prototype 4.00–0.00 scale',{class:'detail-sub'})
  );

  container.append(itemPeriods,itemSpan,itemChange,itemHighLow);

  if(t.series.some(s=>s.provisional)){
   const provWarn=el('div',undefined,{
    class:'trend-warning-badge',
    role:'status',
    style:'grid-column: 1 / -1; margin-top: 6px; padding: 6px 12px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 6px; font-size: 0.8rem; color: var(--risk-mod); display: flex; align-items: center; gap: 6px;'
   });
   provWarn.innerHTML = `<span style="font-weight:700;" aria-hidden="true">ℹ</span> Some periods contain incomplete outcomes.`;
   container.append(provWarn);
  }
 }

 function renderComparisonDetails(activeItems, selectedMetric, dimension){
  const detailsContent=document.getElementById('comparison-details-content');
  if(!detailsContent)return;
  detailsContent.replaceChildren();

  const groupA=activeItems[0]?.group;
  const groupB=activeItems[1]?.group;
  const groupC=activeItems[2]?.group;

  if(!groupA&&!groupB){
   detailsContent.append(el('p','Select comparison groups to view details.'));
   return;
  }

  // Section 1: Selected groups
  const secGroups=el('div',undefined,{class:'comparison-details-section'});
  secGroups.append(el('h5','Selected groups'));
  activeItems.forEach((it,i)=>{
   if(it.group){
    const marker=['●','■','▲'][i];
    const col=['var(--teal,#008080)','var(--accent-blue,#1e4db7)','var(--gold,#e8b923)'][i];
    const p=el('p');
    p.innerHTML=`<span style="color:${col};font-weight:700;">${marker} ${it.tag}:</span> ${it.group.label}`;
    secGroups.append(p);
   }
  });

  // Section 2: Current values
  const secValues=el('div',undefined,{class:'comparison-details-section'});
  secValues.append(el('h5','Current values'));
  activeItems.forEach(it=>{
   if(it.group){
    let raw=it.group[selectedMetric];
    let valStr='Unavailable';
    if(raw!==null&&raw!==undefined){
     valStr=selectedMetric==='gwa'?num(raw):(selectedMetric==='student_count'?String(raw):pct(raw));
    }
    const p=el('p');
    p.innerHTML=`<strong>${it.tag}:</strong> ${valStr}${it.group.provisional?' <span class="card-status-badge badge-provisional">Provisional</span>':''}`;
    secValues.append(p);
   }
  });

  // Section 3: Change or difference
  const secChange=el('div',undefined,{class:'comparison-details-section'});
  secChange.append(el('h5','Change or difference'));

  const valA=activeItems[0]?.card?.key;
  const valB=activeItems[1]?.card?.key;
  const dimA=activeItems[0]?.card?.dimension||dimension;
  const dimB=activeItems[1]?.card?.dimension||dimension;

  const sameFacultySubject=dimA!=='faculty_subject'||valA?.split('|')[1]===valB?.split('|')[1];
  const isComparable=(dimA===dimB)&&sameFacultySubject&&(valA!==valB)&&groupA&&groupB;

  if(isComparable){
   let diff=null;
   let unit='points';
   let relChange=null;
   let rawA=groupA[selectedMetric];
   let rawB=groupB[selectedMetric];

   if(rawA!==null&&rawB!==null&&rawA!==undefined&&rawB!==undefined){
    diff=rawA-rawB;
    if(selectedMetric==='gwa'){
     unit='grade points';
     if(rawB>0) relChange=((rawA-rawB)/rawB)*100;
    }else if(selectedMetric==='student_count'){
     unit='students';
     if(rawB>0) relChange=((rawA-rawB)/rawB)*100;
    }else{
     unit='percentage points';
    }
   }

   const chipDiv=document.createElement('div');
   chipDiv.innerHTML=formatDeltaChipHtml(diff, unit);
   secChange.append(chipDiv);

   if(diff!==null){
    const dirText=diff>0?'Higher than the selected comparison':(diff<0?'Lower than the selected comparison':'No recorded change');
    const descP=el('p',`Difference: ${diff>0?'+':''}${selectedMetric==='gwa'?num(diff):(selectedMetric==='student_count'?diff:pct(diff))} (${dirText}).`,{style:'margin-top:6px;'});
    if(relChange!==null){
     descP.textContent+=` Relative change: ${relChange>0?'+':''}${relChange.toFixed(1)}%.`;
    }
    secChange.append(descP);
    secChange.append(el('p',`Calculated from ${groupB.label} to ${groupA.label}.`,{style:'font-size:0.75rem;color:var(--text-gray);margin-top:4px;'}));
   }else{
    secChange.append(el('p','Direct difference unavailable: one or both values are unrecorded.',{style:'margin-top:6px;color:var(--text-gray);'}));
   }
  }else{
   const chipDiv=document.createElement('div');
   chipDiv.innerHTML=formatDeltaChipHtml(null);
   secChange.append(chipDiv);

   let reason='Context views use different scopes. A direct difference is not calculated.';
   if(valA===valB&&groupA&&groupB){
    reason='Select two different groups to compare.';
   }else if(dimA==='faculty_subject'&&dimB==='faculty_subject'&&valA.split('|')[1]!==valB.split('|')[1]){
    reason='Faculty comparisons must use the same subject, school year, and semester.';
   }else if(dimension==='subject'){
    reason='Note: Comparing different subjects provides curricular context.';
   }
   secChange.append(el('p',reason,{style:'margin-top:6px;color:var(--text-gray);'}));
  }

  // Section 4: Coverage and sample
  const secSample=el('div',undefined,{class:'comparison-details-section'});
  secSample.append(el('h5','Coverage and sample'));
  if(groupA&&groupB){
   const nDiff=groupA.student_count-groupB.student_count;
   const covDiff=groupA.numeric_coverage_pct!==null&&groupB.numeric_coverage_pct!==null?(groupA.numeric_coverage_pct-groupB.numeric_coverage_pct).toFixed(1):null;
   secSample.append(
    el('p',`Students: Compare A has ${groupA.student_count}, Compare B has ${groupB.student_count} (${nDiff>0?'+':''}${nDiff} difference).`),
    el('p',`Coverage: Compare A has ${pct(groupA.numeric_coverage_pct)}, Compare B has ${pct(groupB.numeric_coverage_pct)}${covDiff!==null?` (${covDiff>0?'+':''}${covDiff} pp)`:''}.`,{style:'margin-top:4px;'})
   );
  }

  // Section 5: How to interpret
  const secInterpret=el('div',undefined,{class:'comparison-details-section'});
  secInterpret.append(
   el('h5','How to interpret'),
   el('p','Different sample sizes, prior achievement, subject difficulty, and semester conditions affect these results. This comparison provides academic context and does not independently measure teaching performance.',{style:'font-size:0.78rem;color:var(--text-gray);line-height:1.4;'})
  );

  detailsContent.append(secGroups,secValues,secChange,secSample,secInterpret);
 }

 function renderComparisons(){
  if(role==='student')return;
  const selector=document.getElementById('comparison-group');
  if(!selector)return;
  const dimension=selector.value;
  const groups=report.groups[dimension]||[];

  const metricSelector=document.getElementById('comparison-metric');
  const selectA=document.getElementById('compare-a');
  const selectB=document.getElementById('compare-b');
  const noticeEl=document.getElementById('comparison-validation-notice');
  const panelA=document.getElementById('panel-compare-a');
  const panelB=document.getElementById('panel-compare-b');
  const panelC=document.getElementById('panel-compare-c');
  const summaryCard=document.getElementById('comparison-summary-card');
  const chartContainer=document.getElementById('comparison-chart');
  const legendContainer=document.getElementById('comparison-legend');
  const sideBySideTable=document.getElementById('side-by-side-table');
  const allGroupsTable=document.getElementById('comparison-table');
  const allGroupsCount=document.getElementById('all-groups-count');
  const descEl=document.getElementById('comparison-description');
  const addBtn=document.getElementById('add-comparison-card-btn');
  const limitNotice=document.getElementById('add-comparison-limit-notice');

  const description=`${dimensions[dimension]} · ${groups.length} groups · n=${report.summary.student_count}. Numeric coverage: ${pct(report.summary.numeric_coverage_pct)}. Pooled unit-weighted recorded grades; this is not an arithmetic mean of Student GWAs. `+(['section','faculty_subject'].includes(dimension)&&!report.filters.subject?'Select one subject for a same-subject comparison. ':'')+'Group order is identifier/period order, not a best/worst ranking.';
  if(descEl)descEl.textContent=description;
  if(allGroupsCount)allGroupsCount.textContent=String(groups.length);

  if(metricSelector){
   const currentMetric=metricSelector.value;
   const allowedMetrics=dimensionMetrics[dimension]||dimensionMetrics.semester;
   metricSelector.replaceChildren();
   allowedMetrics.forEach(m=>{
    metricSelector.append(el('option',m.label,{value:m.value}));
   });
   if(allowedMetrics.some(m=>m.value===currentMetric)){
    metricSelector.value=currentMetric;
   }else{
    metricSelector.value='gwa';
   }
  }
  const selectedMetric=metricSelector?.value||'gwa';

  if(addBtn){
   if(comparisonCards.length>=3){
    addBtn.disabled=true;
    addBtn.setAttribute('aria-disabled','true');
    addBtn.style.opacity='0.55';
    addBtn.style.cursor='not-allowed';
    addBtn.title='Maximum of 3 comparisons reached';
    if(limitNotice)limitNotice.style.display='inline';
   }else{
    addBtn.disabled=false;
    addBtn.removeAttribute('aria-disabled');
    addBtn.style.opacity='1';
    addBtn.style.cursor='pointer';
    addBtn.title='Add a third comparison card';
    if(limitNotice)limitNotice.style.display='none';
   }
  }

  // Resolve groups for each card
  const activeItems=comparisonCards.map((card, idx)=>{
   const cardDim=card.dimension||dimension;
   const candidateGroups=cardDim==='faculty_subject'?getVerifiedFaculty(report.groups):(report.groups[cardDim]||[]);
   let g=candidateGroups.find(x=>x.key===card.key);
   if(!g&&candidateGroups.length>0){
    const pickIdx=Math.min(idx,candidateGroups.length-1);
    g=candidateGroups[pickIdx];
    card.key=g.key;
   }
   return {
    card,
    group:g,
    tag:'Compare '+['A','B','C'][idx],
    colClass:['panel-a','panel-b','panel-c'][idx]
   };
  });

  // Sync with test compatibility selectA and selectB
  if(selectA&&selectB){
   const curA=comparisonCards[0]?.key||selectA.value;
   const curB=comparisonCards[1]?.key||selectB.value;
   selectA.replaceChildren();
   selectB.replaceChildren();
   groups.forEach(g=>{
    selectA.append(el('option',g.label,{value:g.key}));
    selectB.append(el('option',g.label,{value:g.key}));
   });
   if(groups.length>0){
    if(groups.some(g=>g.key===curA))selectA.value=curA;
    else selectA.value=groups[0].key;

    if(groups.some(g=>g.key===curB))selectB.value=curB;
    else selectB.value=(groups.length>1?groups[1].key:groups[0].key);
   }
  }

  const valA=comparisonCards[0]?.key||'';
  const valB=comparisonCards[1]?.key||'';

  // Notice Handling
  if(noticeEl){
   noticeEl.style.display='none';
   noticeEl.className='comparison-notice';
   if(valA===valB&&activeItems.length>1){
    noticeEl.textContent='Select two different groups to compare.';
    noticeEl.style.display='block';
    noticeEl.classList.add('notice-warning');
   }else if(comparisonCards[0]?.dimension==='faculty_subject'&&comparisonCards[1]?.dimension==='faculty_subject'){
    const subA=valA.split('|')[1];
    const subB=valB.split('|')[1];
    if(subA&&subB&&subA!==subB){
     noticeEl.textContent='Faculty comparisons must use the same subject, school year, and semester to ensure fair and meaningful comparison. Please select faculty assigned to the same subject.';
     noticeEl.style.display='block';
     noticeEl.classList.add('notice-info');
    }
   }else if(comparisonCards[0]?.dimension!==comparisonCards[1]?.dimension){
    noticeEl.textContent='Context views use different scopes. A direct difference is not calculated.';
    noticeEl.style.display='block';
    noticeEl.classList.add('notice-neutral');
   }else if(dimension==='subject'){
    noticeEl.textContent='Note: Comparing different subjects provides curricular context. Subject difficulty, credit units, and prior preparation may vary.';
    noticeEl.style.display='block';
    noticeEl.classList.add('notice-neutral');
   }
  }

  const metricLabels={
   gwa:'Recorded class average',
   student_count:'Student count',
   numeric_coverage_pct:'Numeric grade coverage',
   final_coverage_pct:'Final-outcome coverage',
   prediction_coverage_pct:'Prediction coverage',
   risk_distribution:'Advisory-risk distribution'
  };

  const renderPanel=(panel, card, group, tag, colClass, index)=>{
   if(!panel)return;
   panel.replaceChildren();
   const header=el('div',undefined,{class:'panel-header'});
   const topRow=el('div',undefined,{style:'display:flex;justify-content:space-between;align-items:center;gap:8px;'});
   const titleBox=el('div');
   const tagColor=index===0?'var(--teal, #008080)':(index===1?'var(--accent-blue, #1e4db7)':'var(--gold, #e8b923)');
   titleBox.append(
    el('span',tag,{style:`font-size:0.75rem;font-weight:700;text-transform:uppercase;color:${tagColor};letter-spacing:0.5px;`})
   );

   const actionsBox=el('div',undefined,{style:'display:flex;align-items:center;gap:6px;'});
   if(index===2){
    const removeBtn=el('button','Remove',{type:'button',id:'remove-card-c',class:'btn btn--secondary btn-remove-card',style:'font-size:0.75rem;padding:2px 8px;min-height:28px;line-height:1;','aria-label':'Remove comparison card C'});
    removeBtn.addEventListener('click',()=>removeComparisonCard('card-c'));
    actionsBox.append(removeBtn);
   }
   const statusBadge=el('span',!group?'Unavailable':group.provisional?'Provisional data':role==='admin'?'Recorded outcomes resolved':'Verified complete',{
    class:`card-status-badge ${!group?'':group.provisional?'badge-provisional':'badge-complete'}`
   });
   actionsBox.append(statusBadge);
   topRow.append(titleBox,actionsBox);

   let cardTitleText=group?.label||'No matching records';
   if(group&&card.dimension==='faculty_subject'&&group.label.includes(' · ')){
    const [fname,scode]=group.label.split(' · ');
    cardTitleText=`${fname}'s verified ${scode} assignment`;
   }else if(group&&card.dimension==='section'){
    cardTitleText=`Section ${group.label}`;
   }else if(group&&card.dimension==='year_level'){
    cardTitleText=`${group.label} cohort`;
   }
   const titleEl=el('h4',cardTitleText,{class:'panel-title',style:'margin:6px 0 2px 0;'});
   const periodContext=dimensions[card.dimension||dimension]+(report.filters.school_year?' · S.Y. '+report.filters.school_year:'')+(report.filters.semester?' · Sem '+report.filters.semester:'');
   header.append(topRow,titleEl,el('p',periodContext,{class:'panel-sub'}));

   // Card scope selectors
   const controlsRow=el('div',undefined,{style:'display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;'});
   const dimSelect=el('select',undefined,{class:'filter-select card-dimension-select','aria-label':`${tag} Group by`,style:'font-size:0.8rem;min-height:36px;padding:4px 6px;'});
   Object.keys(report.groups).forEach(k=>{
    const lbl=dimensions[k];
    const opt=el('option',lbl,{value:k});
    if(k===(card.dimension||dimension))opt.selected=true;
    dimSelect.append(opt);
   });
   dimSelect.addEventListener('change',e=>{
    card.dimension=e.target.value;
    card.key='';
    renderComparisons();
    panel.querySelector('.card-dimension-select')?.focus({preventScroll:true});
   });

   const valSelect=el('select',undefined,{class:'filter-select card-group-select','aria-label':`${tag} Select value`,style:'font-size:0.8rem;min-height:36px;padding:4px 6px;'});
   const availableGroups=card.dimension==='faculty_subject'?getVerifiedFaculty(report.groups):(report.groups[card.dimension]||[]);
   availableGroups.forEach(g=>{
    const opt=el('option',g.label,{value:g.key});
    if(g.key===group?.key)opt.selected=true;
    valSelect.append(opt);
   });
   valSelect.addEventListener('change',e=>{
    card.key=e.target.value;
    renderComparisons();
    panel.querySelector('.card-group-select')?.focus({preventScroll:true});
   });
   controlsRow.append(dimSelect,valSelect);

   if(!group){
    valSelect.replaceChildren(el('option','No matching records',{value:''}));
    valSelect.disabled=true;
    panel.append(header,controlsRow,el('p','No verified records are available for this grouping. Choose another grouping or clear filters.'));
    return;
   }

   const metricBox=el('div',undefined,{class:'panel-metric-box card-metric-box'});
   const metricName=metricLabels[selectedMetric]||'Metric';
   let valStr='Unavailable', subStr='';
   if(selectedMetric==='gwa'){
    valStr=group.gwa!==null?num(group.gwa):'Unavailable';
    subStr='Pooled unit-weighted recorded-grade mean';
   }else if(selectedMetric==='student_count'){
    valStr=String(group.student_count);
    subStr=`Contributing: ${group.contributing_student_count} students`;
   }else if(selectedMetric==='numeric_coverage_pct'){
    valStr=pct(group.numeric_coverage_pct);
    subStr=`${group.eligible_grade_count} of ${group.expected_grade_count} grades`;
   }else if(selectedMetric==='final_coverage_pct'){
    valStr=pct(group.final_coverage_pct);
    subStr=`${group.resolved_final_count} of ${group.expected_grade_count} resolved grades`;
   }else if(selectedMetric==='prediction_coverage_pct'){
    valStr='Unavailable';
    subStr='Group-level prediction coverage requires student-level prediction join in the analytics group contract.';
   }else if(selectedMetric==='risk_distribution'){
    valStr='Unavailable';
    subStr='Advisory-risk distribution is not available in the current group dataset.';
   }
   metricBox.append(
    el('div',metricName,{class:'panel-metric-label card-metric-label'}),
    el('div',valStr,{class:'panel-metric-value card-metric-value'}),
    el('div',subStr,{class:'panel-metric-sub card-metric-sub'})
   );

   const statsDl=el('dl',undefined,{class:'panel-stats card-stats'});
   const d1=el('div');d1.append(el('dt','Students (n)'),el('dd',String(group.student_count)));
   const d2=el('div');d2.append(el('dt','Numeric coverage'),el('dd',pct(group.numeric_coverage_pct)));
   const d3=el('div');d3.append(el('dt','Final coverage'),el('dd',pct(group.final_coverage_pct)));
   statsDl.append(d1,d2,d3);

   const note=el('p',`Eligible credit units: ${group.eligible_units}. Recorded outcomes: ${group.recorded_outcome_count}.`,{style:'font-size:0.78rem;color:var(--text-gray);margin:0;'});
   panel.append(header,controlsRow,metricBox,statsDl,note);
  };

  const panelsGrid=document.getElementById('comparison-panels-grid');
  renderPanel(panelA, activeItems[0]?.card, activeItems[0]?.group, 'Compare A', 'panel-a', 0);
  renderPanel(panelB, activeItems[1]?.card, activeItems[1]?.group, 'Compare B', 'panel-b', 1);

  if(activeItems.length>=3&&panelC){
   panelC.style.display='block';
   if(panelsGrid)panelsGrid.classList.add('has-3-cards');
   renderPanel(panelC, activeItems[2]?.card, activeItems[2]?.group, 'Compare C', 'panel-c', 2);
  }else if(panelC){
   panelC.style.display='none';
   if(panelsGrid)panelsGrid.classList.remove('has-3-cards');
  }

  if(!groups.length){
   if(summaryCard)summaryCard.replaceChildren(el('p','No comparison records match these filters.'));
   if(chartContainer)chartContainer.replaceChildren(el('p','No comparison records match these filters.'));
   if(legendContainer)legendContainer.replaceChildren();
   if(sideBySideTable)table(sideBySideTable,['Comparison subject','Recorded class average','Students (n)','Eligible / recorded grades','Numeric coverage','Final-outcome coverage','Data status'],[],'No records match these filters.','Direct metrics for the selected comparison cards.');
   if(allGroupsTable)table(allGroupsTable,['Group','Recorded class average','Students (n)','Eligible / recorded grades','Numeric coverage','Final-outcome coverage'],[],'No records match these filters.',description);
   const detailsContent=document.getElementById('comparison-details-content');
   if(detailsContent)detailsContent.replaceChildren(el('p','No comparison records match these filters.'));
   if(noticeEl)noticeEl.style.display='none';
   return;
  }

  // Summary Card - removed duplicate lower block when comparison-details-pane is present
  if(summaryCard){
   summaryCard.replaceChildren();
   summaryCard.style.display='none';
  }

  // Chart Rendering
  renderComparisonChart(chartContainer, activeItems, selectedMetric, `${dimensions[dimension]} side-by-side comparison`);

  // Accessible Compact Legend / Key
  if(legendContainer){
   legendContainer.replaceChildren();
   const legA=el('div',undefined,{class:'comparison-legend-item'});
   const svgA=svgEl('svg',{width:12,height:12,viewBox:'0 0 12 12','aria-hidden':'true'});
   svgA.append(svgEl('circle',{cx:6,cy:6,r:5,fill:'var(--teal, #008080)'}));
   legA.append(svgA,el('span',`● Compare A: ${activeItems[0]?.group?.label||'None'}`));

   const legB=el('div',undefined,{class:'comparison-legend-item'});
   const svgB=svgEl('svg',{width:12,height:12,viewBox:'0 0 12 12','aria-hidden':'true'});
   svgB.append(svgEl('rect',{x:1,y:1,width:10,height:10,fill:'var(--accent-blue, #1e4db7)'}));
   legB.append(svgB,el('span',`■ Compare B: ${activeItems[1]?.group?.label||'None'}`));

   legendContainer.append(legA,legB);

   if(activeItems.length>=3){
    const legC=el('div',undefined,{class:'comparison-legend-item'});
    const svgC=svgEl('svg',{width:12,height:12,viewBox:'0 0 12 12','aria-hidden':'true'});
    svgC.append(svgEl('polygon',{points:'6,1 11,11 1,11',fill:'var(--gold, #e8b923)'}));
    legC.append(svgC,el('span',`▲ Compare C: ${activeItems[2]?.group?.label||'None'}`));
    legendContainer.append(legC);
   }

   const legInc=el('div',undefined,{class:'comparison-legend-item'});
   const svgInc=svgEl('svg',{width:12,height:12,viewBox:'0 0 12 12','aria-hidden':'true'});
   svgInc.append(svgEl('circle',{cx:6,cy:6,r:4.5,fill:'none',stroke:'var(--text-gray)','stroke-dasharray':'2 2','stroke-width':1.5}));
   legInc.append(svgInc,el('span','○ Incomplete or unavailable period'));
   legendContainer.append(legInc);
  }

  // Exact side-by-side evidence table
  if(sideBySideTable){
   const sbsHeaders=['Comparison subject','Recorded class average','Students (n)','Eligible / recorded grades','Numeric coverage','Final-outcome coverage','Data status'];
   const sbsRows=activeItems.filter(it=>it.group).map(it=>[
    it.group.label,
    num(it.group.gwa)+(it.group.provisional?' (provisional)':''),
    it.group.student_count,
    `${it.group.eligible_grade_count} / ${it.group.expected_grade_count}`,
    pct(it.group.numeric_coverage_pct),
    pct(it.group.final_coverage_pct),
    it.group.provisional?'Provisional':'Verified complete'
   ]);
   table(sideBySideTable,sbsHeaders,sbsRows,'No records match these filters.','Direct metrics for the selected comparison cards.');
  }

  // All groups disclosure table
  if(allGroupsTable){
   table(allGroupsTable,['Group','Recorded class average','Students (n)','Eligible / recorded grades','Numeric coverage','Final-outcome coverage'],groups.map(g=>[g.label,num(g.gwa)+(g.provisional?' (provisional)':''),g.student_count,`${g.eligible_grade_count} / ${g.expected_grade_count}`,pct(g.numeric_coverage_pct),pct(g.final_coverage_pct)]),'No records match these filters.',description);
  }

  renderComparisonDetails(activeItems, selectedMetric, dimension);
 }

 function renderSupport(){
  if(!report.support)return;
  const s=report.support;

  // Chart 1: Case & Referral Status
  const statusContainer=document.getElementById('chart-support-status');
  if(statusContainer){
   statusContainer.replaceChildren();
   const totalCases=(s.open_cases||0)+(s.closed_cases||0);
   const totalRefs=s.referrals_created||0;
   const reviewed=s.referrals_reviewed||0;
   const awaiting=Math.max(0,totalRefs-reviewed);
   if(totalCases===0&&totalRefs===0){
    statusContainer.append(el('p','No recorded support cases or referrals match these filters.',{style:'font-size:0.82rem;color:var(--text-gray);padding:10px 0;'}));
   }else{
    const svg=svgEl('svg',{viewBox:'0 0 380 150',role:'img','aria-label':'Case and referral status chart',style:'width:100%;height:auto;max-height:160px;'});
    svg.append(svgEl('title',{},'Support Case and Referral Status'),svgEl('desc',{},'Horizontal comparison showing cases and referrals as separate record units.'));
    const barW=250;
    const casesOpenW=totalCases>0?Math.round((s.open_cases/totalCases)*barW):0;
    const casesClosedW=totalCases>0?barW-casesOpenW:0;
    svg.append(svgEl('text',{x:10,y:20,'font-size':'12px','font-weight':'600'},`Parent Cases (n=${totalCases})`));
    if(totalCases>0){
     svg.append(svgEl('rect',{x:10,y:28,width:barW,height:18,fill:'var(--border-color)',opacity:'0.25',rx:4}));
     if(casesOpenW>0)svg.append(svgEl('rect',{x:10,y:28,width:casesOpenW,height:18,fill:'var(--teal, #008080)',rx:casesClosedW===0?4:0}));
     if(casesClosedW>0)svg.append(svgEl('rect',{x:10+casesOpenW,y:28,width:casesClosedW,height:18,fill:'var(--accent-blue, #1e4db7)',rx:casesOpenW===0?4:0}));
     svg.append(svgEl('text',{x:270,y:41,'font-size':'11px','font-weight':'600'},`${s.open_cases} Open · ${s.closed_cases} Closed`));
    }else{
     svg.append(svgEl('text',{x:10,y:40,'font-size':'11px',fill:'var(--text-gray)'},'No recorded parent cases in this scope'));
    }
    const refsRevW=totalRefs>0?Math.round((reviewed/totalRefs)*barW):0;
    const refsAwtW=totalRefs>0?barW-refsRevW:0;
    svg.append(svgEl('text',{x:10,y:80,'font-size':'12px','font-weight':'600'},`Referrals (n=${totalRefs})`));
    if(totalRefs>0){
     svg.append(svgEl('rect',{x:10,y:88,width:barW,height:18,fill:'var(--border-color)',opacity:'0.25',rx:4}));
     if(refsRevW>0)svg.append(svgEl('rect',{x:10,y:88,width:refsRevW,height:18,fill:'var(--teal, #008080)',rx:refsAwtW===0?4:0}));
     if(refsAwtW>0)svg.append(svgEl('rect',{x:10+refsRevW,y:88,width:refsAwtW,height:18,fill:'#f59e0b',rx:refsRevW===0?4:0}));
     svg.append(svgEl('text',{x:270,y:101,'font-size':'11px','font-weight':'600'},`${reviewed} Reviewed · ${awaiting} Awaiting`));
    }else{
     svg.append(svgEl('text',{x:10,y:100,'font-size':'11px',fill:'var(--text-gray)'},'No recorded referrals in this scope'));
    }
    svg.append(
     svgEl('circle',{cx:14,cy:132,r:4,fill:'var(--teal, #008080)'}),
     svgEl('text',{x:22,y:135,'font-size':'10px','font-weight':'500'},'Open'),
     svgEl('circle',{cx:74,cy:132,r:4,fill:'var(--accent-blue, #1e4db7)'}),
     svgEl('text',{x:82,y:135,'font-size':'10px','font-weight':'500'},'Closed'),
     svgEl('circle',{cx:138,cy:132,r:4,fill:'var(--teal, #008080)'}),
     svgEl('text',{x:146,y:135,'font-size':'10px','font-weight':'500'},'Reviewed'),
     svgEl('circle',{cx:214,cy:132,r:4,fill:'#f59e0b'}),
     svgEl('text',{x:222,y:135,'font-size':'10px','font-weight':'500'},'Awaiting review')
    );
    statusContainer.append(svg);
   }
  }

  // Chart 2: Notice & Acknowledgment Flow
  const flowContainer=document.getElementById('chart-notice-flow');
  if(flowContainer){
   flowContainer.replaceChildren();
   const svg=svgEl('svg',{viewBox:'0 0 460 115',role:'img','aria-label':'Notice and receipt acknowledgment flow',style:'width:100%;height:auto;max-height:125px;'});
   svg.append(svgEl('title',{},'Notice and Receipt Flow'),svgEl('desc',{},'Progression from created referrals to issued notices and recorded receipt acknowledgments.'));
   const nodeW=115, nodeH=64;

   // Node 1: Referrals
   svg.append(svgEl('rect',{x:12,y:14,width:nodeW,height:nodeH,fill:'var(--bg-hover, #f8fafc)',stroke:'var(--border-color)',rx:8}));
   svg.append(svgEl('text',{x:12+nodeW/2,y:32,'text-anchor':'middle','font-size':'11px','font-weight':'700',fill:'var(--text-gray)'},'REFERRALS'));
   svg.append(svgEl('text',{x:12+nodeW/2,y:52,'text-anchor':'middle','font-size':'18px','font-weight':'700'},String(s.referrals_created||0)));
   svg.append(svgEl('text',{x:12+nodeW/2,y:67,'text-anchor':'middle','font-size':'9.5px',fill:'var(--text-gray)'},'Created'));

   // Arrow 1
   svg.append(svgEl('path',{d:'M137 46 L163 46 M157 41 L164 46 L157 51',stroke:'var(--accent-blue, #1e4db7)','stroke-width':2,fill:'none'}));

   // Node 2: Notices Issued
   svg.append(svgEl('rect',{x:173,y:14,width:nodeW,height:nodeH,fill:'var(--bg-hover, #f8fafc)',stroke:'var(--border-color)',rx:8}));
   svg.append(svgEl('text',{x:173+nodeW/2,y:32,'text-anchor':'middle','font-size':'11px','font-weight':'700',fill:'var(--text-gray)'},'NOTICES'));
   svg.append(svgEl('text',{x:173+nodeW/2,y:52,'text-anchor':'middle','font-size':'18px','font-weight':'700'},String(s.notices_issued||0)));
   svg.append(svgEl('text',{x:173+nodeW/2,y:67,'text-anchor':'middle','font-size':'9.5px',fill:'var(--text-gray)'},'Issued'));

   // Arrow 2
   svg.append(svgEl('path',{d:'M298 46 L324 46 M318 41 L325 46 L318 51',stroke:'var(--accent-blue, #1e4db7)','stroke-width':2,fill:'none'}));

   // Node 3: Receipt Acknowledged
   svg.append(svgEl('rect',{x:334,y:14,width:nodeW,height:nodeH,fill:'var(--bg-hover, #f8fafc)',stroke:'var(--teal, #008080)',rx:8}));
   svg.append(svgEl('text',{x:334+nodeW/2,y:32,'text-anchor':'middle','font-size':'10px','font-weight':'700',fill:'var(--teal, #008080)'},'ACKNOWLEDGED'));
   svg.append(svgEl('text',{x:334+nodeW/2,y:52,'text-anchor':'middle','font-size':'18px','font-weight':'700',fill:'var(--teal, #008080)'},String(s.student_acknowledgments||0)));
   svg.append(svgEl('text',{x:334+nodeW/2,y:67,'text-anchor':'middle','font-size':'9.5px',fill:'var(--teal, #008080)'},'Receipt confirmed'));

   // Concise 1-line text
   svg.append(svgEl('text',{x:12,y:98,'font-size':'10px',fill:'var(--text-gray)'},'MTR-19 bounded count · Action progression records receipt only, not agreement or fault.'));
   flowContainer.append(svg);

   const flowHelp=el('details',undefined,{class:'support-inline-disclosure'});
   const flowSummary=el('summary',undefined,{class:'support-inline-summary'});
   flowSummary.append(
    el('span','?',{class:'circular-help-icon','aria-hidden':'true'}),
    el('span','How this flow is counted')
   );
   const flowBody=el('div',undefined,{class:'support-inline-help-body'});
   flowBody.innerHTML=`
    <ul style="margin:6px 0 0 0;padding-left:18px;font-size:0.78rem;color:var(--text-gray);line-height:1.5;">
     <li><strong>Referrals created:</strong> Initial support referral logged for review.</li>
     <li><strong>Notices issued:</strong> Formal academic advisory notices issued following review.</li>
     <li><strong>Receipt confirmed:</strong> Confirmed student receipt acknowledgments (bounded count, not rate). Confirms receipt only, never agreement, fault, or admission.</li>
    </ul>
   `;
   flowHelp.append(flowSummary,flowBody);
   flowContainer.append(flowHelp);
  }

  // Workload Summary (5 Metrics)
  const pendingReviews=Math.max(0,(s.referrals_created||0)-(s.referrals_reviewed||0));
  const setSupportWorkload=(key,val)=>{
   const el=document.querySelector(`[data-support-workload="${key}"]`);
   if(el)el.textContent=String(val);
  };
  setSupportWorkload('pending-reviews',pendingReviews);
  setSupportWorkload('open-cases',s.open_cases??0);
  setSupportWorkload('referrals',s.referrals_created??0);
  setSupportWorkload('notices',s.notices_issued??0);
  setSupportWorkload('acknowledgments',s.student_acknowledgments??0);

  // Empty State Guidance
  const emptyGuidance=document.getElementById('support-empty-guidance');
  const isSupportEmpty=(s.eligible_cases===0&&s.referrals_created===0);
  if(emptyGuidance){
   if(isSupportEmpty){
    emptyGuidance.style.display='block';
    emptyGuidance.replaceChildren();
    const p=el('p','No support or intervention records are available for the selected scope.');
    const actions=el('div',undefined,{class:'support-empty-actions'});
    const clearBtn=el('button','Clear filters',{type:'button',class:'btn btn--secondary'});
    clearBtn.addEventListener('click',()=>{
     form.reset();
     updateAdvancedFiltersBadge();
     load(new URLSearchParams());
    });
    actions.append(clearBtn);
    emptyGuidance.append(p,actions);
   }else{
    emptyGuidance.style.display='none';
    emptyGuidance.replaceChildren();
   }
  }

  // Chart 3: First-Notice Timing
  const timingContainer=document.getElementById('chart-first-notice');
  if(timingContainer){
   timingContainer.replaceChildren();
   const timingBox=el('div');
   const titleMtr20=el('div','AVERAGE TIME BEFORE FIRST NOTICE (MTR-20)',{style:'font-size:0.75rem;font-weight:700;color:var(--text-gray);letter-spacing:0.4px;margin-bottom:4px;'});
   let valStr='Unavailable', sampleStr=formatSampleSize(s.first_action_sample);
   if(s.mean_first_action_hours!==null){
    valStr=formatDurationHours(s.mean_first_action_hours);
   }
   const valEl=el('div',valStr,{style:'font-size:1.6rem;font-weight:700;color:var(--text-dark);line-height:1.2;'});
   const sampleEl=el('div',sampleStr,{style:'font-size:0.78rem;color:var(--text-gray);margin-top:2px;'});
   timingBox.append(titleMtr20,valEl,sampleEl);
   if(s.first_action_sample>0&&s.first_action_sample<3){
    timingBox.append(el('div',`Very limited sample (n=${s.first_action_sample}). Interpret cautiously.`,{
     role:'status',
     class:'support-sample-warning'
    }));
   }
   const divider=el('hr',undefined,{style:'margin:10px 0;border:0;border-top:1px dashed var(--border-color);'});
   const titleParent=el('div','MEDIAN PARENT-CASE DURATION',{style:'font-size:0.75rem;font-weight:700;color:var(--text-gray);letter-spacing:0.4px;margin-bottom:4px;'});
   const valParentStr=s.median_closure_hours!==null?`${num(s.median_closure_hours)} hours (n=${s.closure_sample})`:'Unavailable';
   const valParentEl=el('div',valParentStr,{style:'font-size:1.15rem;font-weight:700;color:var(--text-dark);'});

   const mtr21Details=el('details',undefined,{class:'support-inline-disclosure',style:'margin-top:8px;'});
   const mtr21Summary=el('summary',undefined,{class:'support-inline-summary'});
   mtr21Summary.append(
    el('span','?',{class:'circular-help-icon','aria-hidden':'true'}),
    el('span','Why referral closure remains unavailable')
   );
   const mtr21Body=el('div',undefined,{class:'support-inline-help-body'});
   mtr21Body.innerHTML=`
    <p style="margin:4px 0;font-size:0.75rem;color:var(--text-gray);line-height:1.45;">
     Limited indicator for currently closed parent support cases. This is not the canonical referral-closure metric.
    </p>
    <p style="margin:4px 0;font-size:0.75rem;color:var(--text-gray);line-height:1.45;font-style:italic;">
     Canonical referral closure or reopening duration (MTR-21) remains unavailable until required referral-lifecycle data contracts exist.
    </p>
   `;
   mtr21Details.append(mtr21Summary,mtr21Body);

   timingBox.append(divider,titleParent,valParentEl,mtr21Details);
   timingContainer.append(timingBox);
  }

  const names={eligible_cases:'Recorded eligible cases',referrals_created:'Referrals created',referrals_reviewed:'Referrals with recorded review status',notices_issued:'Notices issued',notices_deferred:'Notices deferred',student_acknowledgments:'Recorded Student receipt acknowledgments (count)',open_cases:'Open cases',closed_cases:'Closed cases',mean_first_action_hours:'Average time before the first notice',median_closure_hours:'Median recorded parent-case closed duration (limited indicator)'};
  const rows=Object.entries(names).map(([key,label])=>{
   let value=s[key]===null?'Unavailable':String(s[key]);
   if(key==='mean_first_action_hours'){
    if(s[key]===null){
     value='Unavailable';
    }else{
     const dur=formatDurationHours(s[key]);
     const sampleText=formatSampleSize(s.first_action_sample);
     const caution=(s.first_action_sample>0&&s.first_action_sample<3)?' · Very limited sample. Interpret cautiously.':'';
     value=`${dur} (${sampleText}${caution})`;
    }
   }
   if(key==='median_closure_hours'&&s[key]!==null)value=num(s[key])+` hours (n=${s.closure_sample})`;
   return[el('span',label,{class:'support-metric-card-label'}),el('span',value,{'data-support':key,class:'support-metric-card-val'})];
  });
  const supTable=document.getElementById('support-table');
  if(supTable){
   supTable.className='support-metrics-table';
   table(supTable,['Measure','Recorded result'],rows,'No cases match these filters.',`Cases n=${s.eligible_cases}; referrals n=${s.referrals_created}. Counts describe separate record types.`);
  }

  // Definition disclosure
  const defContainer=document.getElementById('support-definitions-container');
  if(defContainer){
   defContainer.replaceChildren();
   const defDetails=el('details',undefined,{class:'support-inline-disclosure card',style:'padding:12px 16px;'});
   const defSummary=el('summary',undefined,{class:'support-inline-summary'});
   defSummary.append(
    el('span','?',{class:'circular-help-icon','aria-hidden':'true'}),
    el('span','How support metrics are defined and calculated')
   );
   const defBody=el('div',undefined,{class:'support-inline-help-body',style:'margin-top:10px;'});
   defBody.innerHTML=`
    <ul style="margin:0;padding-left:18px;font-size:0.8rem;color:var(--text-gray);line-height:1.55;">
     <li><strong>Recorded eligible cases:</strong> Count of distinct student cases within the filtered scope.</li>
     <li><strong>Referrals created vs. reviewed:</strong> Separate record units representing initial referrals and faculty-reviewed decisions.</li>
     <li><strong>Notices issued vs. deferred:</strong> Formal notices generated versus advisory notices intentionally postponed pending review.</li>
     <li><strong>Receipt acknowledgments:</strong> Raw count of student receipt confirmations; does not constitute fault or agreement.</li>
     <li><strong>First-notice duration (MTR-20):</strong> Mean elapsed hours from referral creation to the first recorded notice.</li>
     <li><strong>Median parent-case duration:</strong> Limited indicator for closed cases; canonical MTR-21 referral closure remains unavailable.</li>
    </ul>
   `;
   defDetails.append(defSummary,defBody);
   defContainer.append(defDetails);
  }

  const supLimits=document.getElementById('support-limits');
  if(supLimits)supLimits.textContent=s.deferral_reason+' MTR-20 is the arithmetic mean from each distinct referral creation to its first recorded notice; earlier unrecorded actions cannot be inferred. Missing, invalid or reversed times are excluded. Receipt acknowledgments are recorded counts, including valid legacy receipt timestamps without linked issuance evidence, not MTR-19 rates. The parent-case closed-duration median is a limited recorded indicator, not canonical MTR-21: referral closure and reopening equivalence remain blocked. No teaching-quality or causal claim follows from these durations.';

  const drillTable=document.getElementById('drilldown-table');
  if(drillTable){
   drillTable.className='support-task-table';
   drillTable.replaceChildren();
   if(!report.drilldowns||report.drilldowns.length===0){
    const tbody=el('tbody');
    const tr=el('tr');
    tr.append(el('td','No authorized records match these filters.',{colspan:3,class:'support-task-empty',style:'padding:16px;color:var(--text-gray);text-align:center;'}));
    tbody.append(tr);
    drillTable.append(tbody);
   }else{
    const tbody=el('tbody');
    const total=report.drilldowns.length;
    report.drilldowns.forEach((d,idx)=>{
     const tr=el('tr',undefined,{class:idx>=5?'support-task-row support-task-row--extra':'support-task-row'});
     if(idx>=5)tr.style.display='none';

     const tdName=el('td',undefined,{class:'support-task-col-name'});
     tdName.append(el('span',d.label,{class:'support-task-name'}));

     const rawStatus=String(d.status||'').replaceAll('_',' ');
     const sentenceStatus=rawStatus.charAt(0).toUpperCase()+rawStatus.slice(1).toLowerCase();
     const tdBadge=el('td',undefined,{class:'support-task-col-status'});
     const badge=el('span',sentenceStatus,{class:`support-status-badge support-status-badge--${d.status}`});
     tdBadge.append(badge);

     const tdAction=el('td',undefined,{class:'support-task-col-action',style:'text-align:right;'});
     const actionVerb=(d.status==='open'||d.status==='pending')?'Open case':'View case';
     const link=el('a',`${actionVerb} →`,{
      href:d.href,
      class:'btn btn--secondary btn--sm support-action-btn',
      'aria-label':d.label
     });
     tdAction.append(link);

     tr.append(tdName,tdBadge,tdAction);
     tbody.append(tr);
    });

    if(total>5){
     const toggleTr=el('tr',undefined,{class:'support-task-toggle-row'});
     const toggleTd=el('td',undefined,{colspan:3,style:'text-align:center;padding:10px;'});
     const toggleBtn=el('button',`View all ${total} cases ↓`,{
      type:'button',
      class:'btn btn--subtle btn--sm support-task-toggle-btn',
      'aria-expanded':'false'
     });
     let expanded=false;
     toggleBtn.addEventListener('click',()=>{
      expanded=!expanded;
      toggleBtn.setAttribute('aria-expanded',String(expanded));
      toggleBtn.textContent=expanded?`Show fewer cases ↑`:`View all ${total} cases ↓`;
      tbody.querySelectorAll('.support-task-row--extra').forEach(r=>{
       r.style.display=expanded?'':'none';
      });
     });
     toggleTd.append(toggleBtn);
     toggleTr.append(toggleTd);
     tbody.append(toggleTr);
    }
    drillTable.append(tbody);
   }
  }
 }

 function updateAdvancedFiltersBadge(){
  const badge=document.getElementById('analytics-advanced-badge');
  if(!badge)return;
  const yearSelect=document.getElementById('analytics-year_level');
  const facSelect=document.getElementById('analytics-faculty');
  let activeCount=0;
  if(yearSelect&&yearSelect.value!=='')activeCount++;
  if(facSelect&&facSelect.value!=='')activeCount++;
  if(activeCount>0){
   badge.textContent=String(activeCount);
   badge.style.display='inline-block';
   const details=document.getElementById('analytics-advanced-details');
   if(details)details.open=true;
  }else{
   badge.style.display='none';
  }
 }

 let lastOverviewScopeStr = 'Current scope: All authorized recorded values';
 let lastSupportScopeStr = 'Current support scope: All authorized recorded values';

 function switchTab(tabId){
  activeTab=tabId;
  const tabButtons=root.querySelectorAll('.analytics-tablist .tab-btn');
  tabButtons.forEach(btn=>{
   const match=btn.dataset.tab===tabId;
   btn.classList.toggle('active',match);
   btn.setAttribute('aria-selected',match?'true':'false');
   btn.setAttribute('tabindex',match?'0':'-1');
  });

  const panels=root.querySelectorAll('.analytics-tab-panel');
  panels.forEach(p=>{
   const match=p.id==='tab-panel-'+tabId;
   p.hidden=!match;
  });

  const overviewSlot=document.getElementById('overview-filter-slot');
  const supportSlot=document.getElementById('support-filter-slot');
  const wsTitle=document.getElementById('filter-workspace-title');
  const wsIntro=document.getElementById('filter-workspace-intro');
  const overviewFields=form.querySelectorAll('.filter-field--overview');
  const supportFields=form.querySelectorAll('.filter-field--support');
  const activeFilters=document.getElementById('active-filters');

  if(tabId==='overview'){
   if(overviewSlot&&!overviewSlot.contains(form)){
    overviewSlot.appendChild(form);
   }
   if(activeFilters&&overviewSlot&&!overviewSlot.contains(activeFilters)){
    overviewSlot.parentElement.insertBefore(activeFilters, overviewSlot.nextSibling);
   }
   form.hidden=false;
   if(wsTitle)wsTitle.textContent='Filter this view';
   if(wsIntro)wsIntro.textContent='Choose the academic scope and cohorts to analyze.';
   overviewFields.forEach(el=>el.style.display='');
   supportFields.forEach(el=>el.style.display='none');
   if(activeFilters){
    activeFilters.style.display='';
    activeFilters.textContent=lastOverviewScopeStr;
   }
  }else if(tabId==='support'){
   if(supportSlot&&!supportSlot.contains(form)){
    supportSlot.appendChild(form);
   }
   if(activeFilters&&supportSlot&&!supportSlot.contains(activeFilters)){
    supportSlot.parentElement.insertBefore(activeFilters, supportSlot.nextSibling);
   }
   form.hidden=false;
   if(wsTitle)wsTitle.textContent='Filter this view';
   if(wsIntro)wsIntro.textContent='Filter support workload and recorded intervention activity by period and status.';
   overviewFields.forEach(el=>el.style.display='none');
   supportFields.forEach(el=>el.style.display='');
   if(activeFilters){
    activeFilters.style.display='';
    activeFilters.textContent=lastSupportScopeStr;
   }
  }else if(tabId==='comparisons'||tabId==='trends'){
   form.hidden=true;
   if(activeFilters)activeFilters.style.display='none';
  }

  // Update URL without page reload
  try{
   const url=new URL(location.href);
   url.searchParams.set('tab',tabId);
   history.replaceState(null,'',url);
  }catch{}
 }

 function setupTabs(){
  const tablist=root.querySelector('.analytics-tablist');
  if(!tablist)return;
  const buttons=Array.from(tablist.querySelectorAll('.tab-btn'));
  buttons.forEach(btn=>{
   btn.addEventListener('click',()=>switchTab(btn.dataset.tab));
   btn.addEventListener('keydown',e=>{
    const idx=buttons.indexOf(btn);
    let nextIdx=null;
    if(e.key==='ArrowRight')nextIdx=(idx+1)%buttons.length;
    else if(e.key==='ArrowLeft')nextIdx=(idx-1+buttons.length)%buttons.length;
    else if(e.key==='Home')nextIdx=0;
    else if(e.key==='End')nextIdx=buttons.length-1;
    if(nextIdx!==null){
     e.preventDefault();
     buttons[nextIdx].focus();
     switchTab(buttons[nextIdx].dataset.tab);
    }
   });
  });

  // Check initial tab from URL
  const initialTab=new URLSearchParams(location.search).get('tab');
  if(initialTab&&buttons.some(b=>b.dataset.tab===initialTab)){
   switchTab(initialTab);
  }
 }

 function render(data){
  report=data;
  for(const select of form.querySelectorAll('select')){
   const key=select.name;
   select.replaceChildren(el('option','All recorded values',{value:''}));
   for(const o of data.options[key]||[])select.append(el('option',o.label,{value:o.value}));
   if(data.filters[key]&&!Array.from(select.options).some(o=>o.value===data.filters[key])){
    select.append(el('option',data.filters[key]+' (no matching data)',{value:data.filters[key]}));
   }
   select.value=data.filters[key]||'';
   select.disabled=role==='faculty'&&key==='faculty';
  }
  form.querySelector('fieldset').disabled=false;
  updateAdvancedFiltersBadge();

  const formatScopeItem = (key, defaultText) => {
   const val = data.filters[key];
   if (!val) return defaultText;
   const opt = (data.options[key] || []).find(o => o.value === val);
   return opt ? opt.label : val;
  };

  const syText = formatScopeItem('school_year', 'All school years');
  const semText = data.filters.semester ? (data.filters.semester === '1' ? 'Semester 1' : (data.filters.semester === '2' ? 'Semester 2' : (data.filters.semester === '3' ? 'Summer' : `Semester ${data.filters.semester}`))) : 'All semesters';
  const yrText = data.filters.year_level ? `Year ${data.filters.year_level}` : 'All curriculum years';
  const secText = data.filters.section ? `Section ${data.filters.section}` : 'All sections';
  const caseText = formatScopeItem('case_status', 'All case statuses');
  const decText = formatScopeItem('decision', 'All referral statuses');

  lastOverviewScopeStr = `Current scope: ${syText} · ${semText} · ${yrText} · ${secText}`;
  lastSupportScopeStr = `Current support scope: ${syText} · ${semText} · ${caseText} · ${decText} · ${secText}`;

  const activeScopeText = activeTab === 'support' ? lastSupportScopeStr : lastOverviewScopeStr;
  const activeFiltersEl = document.getElementById('active-filters');
  if (activeFiltersEl) {
   activeFiltersEl.textContent = activeScopeText;
  }

  const box=document.getElementById('analytics-summary');
  box.replaceChildren();
  const t=data.trend;

  if(role==='student'){
   const growthPoints = t.change?.point_change !== null ? (t.change.point_change > 0 ? '+' : '') + t.change.point_change.toFixed(2) + ' GWA points' : 'Unavailable';
   const growthRel = t.change?.relative_change_pct !== null ? (t.change.relative_change_pct > 0 ? '+' : '') + t.change.relative_change_pct.toFixed(2) + '%' : '';
   const growthDisplay = growthRel ? `${growthPoints} (${growthRel})` : growthPoints;

   metrics(box,[
       ['Current selected term GWA', num(t.current?.gwa), 'current-gwa'],
       ['Previous comparable GWA', num(t.previous?.gwa), 'previous-gwa'],
       ['Growth', growthDisplay, 'point-change'],
       ['Latest estimate', num(data.prediction?.estimated_gwa) + (data.prediction?.is_provisional ? ' (Provisional)' : ''), 'estimate']
   ]);

   const noteP = el('p', `Current period: ${t.current?.label || 'Unavailable'} · Previous recorded comparable period: ${t.previous?.label || 'Unavailable'} · Direction: ${t.change?.direction || 'Unavailable'}.`, {style: 'font-size: 0.85rem; color: var(--text-gray); margin-top: 12px;'});
   box.append(noteP);
  }else{
   // Admin & Faculty Overview primary metrics (Level 1 Full Width)
   metrics(box,[['Weighted recorded-grade mean',num(data.summary.gwa),'average-gwa'],['Students (n)',String(data.summary.student_count),'student-count'],['Eligible grades',`${data.summary.eligible_grade_count} / ${data.summary.expected_grade_count}`,'eligible-grades'],['Numeric GWA coverage',pct(data.summary.numeric_coverage_pct),'numeric-coverage']]);

   // Level 2: Scannable 4-Block Full-Width Details Workspace
   const detailsWorkspace = el('div', undefined, { class: 'analytics-details-workspace' });
   const detailsGrid = el('div', undefined, { class: 'analytics-details-grid' });

   // Block 1: Comparison period
   const bPeriod = el('div', undefined, { class: 'analytics-detail-block' });
   bPeriod.append(
    el('h5', 'Comparison period'),
    el('div', `${t.current?.label || 'Unavailable'} versus ${t.previous?.label || 'Unavailable'}`, { class: 'detail-field-value detail-value-highlight' }),
    el('div', 'Selected term versus prior comparable term', { class: 'detail-sub' })
   );

   // Block 2: Term-over-term change
   const bChange = el('div', undefined, { class: 'analytics-detail-block' });
   bChange.append(
    el('h5', 'Point change & Relative change'),
    el('div', `Point change: ${change(t.change.point_change)} · Relative change: ${change(t.change.relative_change_pct, '%')}`, { class: 'detail-field-value detail-value-highlight' }),
    el('div', `Direction: ${t.change.direction || 'Unavailable'}`, { class: 'detail-sub' })
   );

   // Block 3: Evidence used
   const bEvidence = el('div', undefined, { class: 'analytics-detail-block' });
   bEvidence.append(
    el('h5', 'Evidence used'),
    el('div', `GWA uses ${data.summary.eligible_units} eligible credit units`, { class: 'detail-field-value detail-value-highlight' }),
    el('div', `Final-outcome coverage: ${pct(data.summary.final_coverage_pct)}`, { class: 'detail-sub' })
   );

   // Block 4: Data status
   const bStatus = el('div', undefined, { class: 'analytics-detail-block' });
   const statusVal = el('div', undefined, { class: 'detail-field-value' });
   statusVal.append(el('span', data.summary.provisional ? 'Provisional' : 'Recorded outcomes resolved', {
    class: 'card-status-badge ' + (data.summary.provisional ? 'badge-provisional' : 'badge-complete')
   }));
   bStatus.append(
    el('h5', 'Data status'),
    statusVal,
    el('div', data.summary.provisional ? 'Some final outcomes or units are incomplete' : 'All expected outcomes recorded', { class: 'detail-sub' })
   );

   detailsGrid.append(bPeriod, bChange, bEvidence, bStatus);

   const ruleExpl = el('div', 'Changes are unavailable when the compared term is incomplete or has no numeric GWA.', { class: 'analytics-rule-explanation' });
   const textSummary = el('p', `Comparison period: ${t.current?.label || 'Unavailable'} versus ${t.previous?.label || 'Unavailable'} · Point change: ${change(t.change.point_change)} · Relative change: ${change(t.change.relative_change_pct, '%')}. ${data.summary.provisional ? 'Provisional: some final outcomes or units are incomplete. ' : ''}GWA uses ${data.summary.eligible_units} eligible credit units. Final-outcome coverage: ${pct(data.summary.final_coverage_pct)}. Changes are unavailable when the compared term is incomplete or has no numeric GWA.`, { class: 'sr-only' });

   detailsWorkspace.append(detailsGrid, ruleExpl, textSummary);
   box.append(detailsWorkspace);

   // Secondary Summary Row (Overview Tab)
   const secondary=document.getElementById('analytics-secondary-summary');
   if(secondary){
    secondary.replaceChildren();
    const items=[
     {title:'Previous Comparable GWA',val:num(t.previous?.gwa),sub:t.previous?.label||'No prior term',key:'previous-gwa'},
     {title:'Point Change',val:change(t.change.point_change),sub:'vs comparable term',key:'point-change'},
     {title:'Relative Change',val:change(t.change.relative_change_pct,'%'),sub:t.change.direction||'direction',key:'relative-change'},
     {title:'Prediction Coverage',val:pct(data.prediction_coverage.coverage_pct),sub:`${data.prediction_coverage.available_count} of ${data.prediction_coverage.student_count} students`,key:'prediction-coverage'},
     {title:'Open Support Cases',val:String(data.support?.open_cases??0),sub:`${data.support?.eligible_cases??0} total eligible`,key:'open-cases'},
    ];
    for(const item of items){
     const card=el('div',undefined,{class:'analytics-secondary-card'});
     card.append(el('h4',item.title),el('p',item.val,{class:'metric-val','data-metric':item.key}),el('div',item.sub,{class:'metric-sub'}));
     secondary.append(card);
    }
   }

   if(role==='faculty'){
    metrics(box,[['Students with recorded numeric review indicators',String(data.students_requiring_review),'review-count']]);
    box.append(el('p',data.faculty_comparison.reason));
   }

   // Section Summary Table in Admin Overview
   const secTable=document.getElementById('section-summary-table');
   if(secTable&&data.groups?.section){
    const secDesc=document.getElementById('section-summary-description');
    if(secDesc)secDesc.textContent=`${data.groups.section.length} sections analyzed · n=${data.summary.student_count} total students.`;
    table(secTable,['Section','Average GWA','Students (n)','Eligible / recorded grades','Numeric coverage'],data.groups.section.map(s=>[s.label,num(s.gwa)+(s.provisional?' (provisional)':''),s.student_count,`${s.eligible_grade_count} / ${s.expected_grade_count}`,pct(s.numeric_coverage_pct)]),'No sections match these filters.');
   }
  }

  if(role!=='student'){
   box.append(el('p',`${data.summary.provisional?'Provisional: some final outcomes or units are incomplete. ':''}GWA uses ${data.summary.eligible_units} eligible credit units. Final-outcome coverage: ${pct(data.summary.final_coverage_pct)}. Changes are unavailable when the compared term is incomplete or has no numeric GWA.`));
  }
  renderPrediction(data.prediction);

  const trendChart=document.getElementById('trend-chart');
  const trendTable=document.getElementById('trend-table');
  const trendDesc=document.getElementById('trend-description');
  if(trendChart){
   if(trendDesc){
    const validCount = t.series.filter(s=>s.gwa!==null).length;
    trendDesc.textContent=role==='student'
      ? 'Your semester results in order. Missing results appear as gaps.'
      : `${validCount} recorded semesters across ${t.series.length} period positions · ${data.summary.student_count} Students. Gaps represent unavailable values.`;
   }
   chart(trendChart,t.series,'line','Historical semester GWA');
   renderTrendDetails(t);
  }
  if(trendTable){
   table(trendTable,['# / Recorded term','Recorded-point mean','Status','Students (n)','Eligible / recorded grades','Numeric coverage'],t.series.map((p,i)=>[`${i+1}. ${p.label}`,num(p.gwa),p.provisional?'Provisional partial records; term GWA pending':'Recorded',p.student_count,`${p.eligible_grade_count} / ${p.expected_grade_count}`,pct(p.numeric_coverage_pct)]),'No recorded terms match these filters.');
  }

  if(role!=='student'){
   if(role==='admin')window.UDMAdminComparisons?.update(data,params=>load(params));
   const selector=document.getElementById('comparison-group');
   if(selector){
    const previous=selector.value;
    selector.replaceChildren();
    Object.keys(data.groups).forEach(key=>selector.append(el('option',dimensions[key],{value:key})));
    if(data.groups[previous])selector.value=previous;
    renderComparisons();
   }
   renderSupport();
  }

  const methods=document.getElementById('analytics-method');
  if(methods){
   methods.replaceChildren();
   if(role==='student'){
    const d=el('details',undefined,{class:'analytics-method-disclosure portal-info-disclosure'});
    const s=el('summary',undefined,{class:'trend-disclosure-summary'});
    s.innerHTML = '<svg class="trend-disclosure-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg><span class="trend-disclosure-title">How these results are calculated</span>';
    const list=el('ul',undefined,{style:'margin-top:10px;padding-left:20px;font-size:0.85rem;color:var(--text-gray);line-height:1.5;'});
    list.append(
        el('li','Average GWA is calculated from eligible final grades weighted by credit units. Incomplete (INC), dropped (DRP), and zero entries are excluded from GWA.'),
        el('li','Growth reflects the point change and relative percentage between consecutive comparable completed terms.'),
        el('li','Estimates provide provisional decision support and do not replace official registrar evaluations or academic standing.'),
        el('li','Summer terms are compared only with prior Summer terms.')
    );
    d.append(s,list);
    methods.append(d);
   }else{
    const introP=el('p','Recorded academic metrics provide decision-support context. Use the disclosures below to inspect specific calculation formulas, pooled-mean weighting, and analytical limitations.',{style:'font-size:0.85rem;color:var(--text-gray);margin-bottom:12px;line-height:1.45;'});

    const d1=el('details',undefined,{class:'analytics-method-disclosure'});
    const s1=el('summary','How this is calculated');
    const p1a=el('p','Average GWA is the sum of eligible final points × credit units divided by eligible credit units. It is not an arithmetic sum of Student GWAs.');
    const p1b=el('p','Relative change is (current − previous) / previous × 100; it indicates observed percentage change and is not a predictive forecast.');
    const p1c=el('p','Unit-weighted calculations exclude incomplete (INC), dropped (DRP), and missing grades from grade sums and unit totals.');
    d1.append(s1,p1a,p1b,p1c);

    const d2=el('details',undefined,{class:'analytics-method-disclosure'});
    const s2=el('summary','Data limitations & caveats');
    d2.append(s2);
    (role==='student'?[data.warnings[0],data.warnings[2]]:data.warnings).forEach(w=>d2.append(el('p',w)));
    if(role!=='student'){
     d2.append(el('p',`Latest stored prediction coverage: ${data.prediction_coverage.available_count} of ${data.prediction_coverage.student_count} represented Students; model ${data.prediction_coverage.model_count}, calculation ${data.prediction_coverage.calculation_count}, provisional/unknown-completeness ${data.prediction_coverage.provisional_count}. Provisional status overlaps the model/calculation counts; these are not additive categories. Complete versus provisional fallback cross-tabs are not provided. These snapshots are separate from historical comparison periods and do not measure prediction accuracy or full active-population coverage.`));
    }
    d2.append(el('p','Prototype performance on synthetic academic records. Predictions and triage categories provide non-punitive decision support and do not constitute official registrar evaluations.'));

    methods.append(introP,d1,d2);
   }
  }
 }

 async function load(params){
  const ticket=++generation;
  if(controller)controller.abort();
  controller=new AbortController();
  results.hidden=!(role==='admin'&&activeTab==='comparisons');
  if(role==='admin')window.UDMAdminComparisons?.loading();
  results.setAttribute('aria-busy','true');
  state.setAttribute('role','status');
  state.textContent='Loading academic review…';
  try{
   const apiParams=new URLSearchParams(params);
   apiParams.delete('tab');
   const url=new URL(root.dataset.endpoint,location.origin);
   url.search=apiParams.toString();
   const response=await fetch(url,{credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});
   if(!response.ok)throw new Error('Unavailable');
   const data=await response.json();
   if(ticket!==generation)return;
   render(data);
   results.hidden=false;
   results.setAttribute('aria-busy','false');
   state.textContent=document.getElementById('active-filters').textContent.replace('Current scope: ','Showing ').replace('Current support scope: ','Showing ').replace('Active filters · ','Showing ')+'.';
   // Only publish a successfully rendered scope; failed/stale requests keep it intact.
   try{
    const appliedUrl=new URL(location.href);
    appliedUrl.search='';
    apiParams.forEach((value,key)=>{if(value!=='')appliedUrl.searchParams.set(key,value);});
    if(root.querySelector('.analytics-tablist'))appliedUrl.searchParams.set('tab',activeTab);
    history.replaceState(null,'',appliedUrl);
   }catch{}
  }catch(error){
   if(error.name==='AbortError'||ticket!==generation)return;
   state.setAttribute('role','alert');
   state.textContent='Academic review could not be loaded. Try applying the filters again.';
   results.hidden=!(role==='admin'&&activeTab==='comparisons');
   if(role==='admin')window.UDMAdminComparisons?.failure();
   results.setAttribute('aria-busy','false');
  }
 }

 form.addEventListener('submit',event=>{
  event.preventDefault();
  load(new URLSearchParams(new FormData(form)));
 });

 document.getElementById('analytics-clear').addEventListener('click',()=>{
  form.reset();
  updateAdvancedFiltersBadge();
  load(new URLSearchParams());
 });

 form.addEventListener('change',()=>updateAdvancedFiltersBadge());
 document.getElementById('btn-mode-overlay')?.addEventListener('click',()=>setComparisonMode('overlay'));
 document.getElementById('btn-mode-cards')?.addEventListener('click',()=>setComparisonMode('cards'));
 document.getElementById('add-comparison-card-btn')?.addEventListener('click',addComparisonCard);
 document.getElementById('comparison-group')?.addEventListener('change',e=>{
  const newDim=e.target.value;
  comparisonCards.forEach(c=>{c.dimension=newDim;c.key='';});
  renderComparisons();
 });
 document.getElementById('comparison-metric')?.addEventListener('change',renderComparisons);
 document.getElementById('compare-a')?.addEventListener('change',e=>{
  if(comparisonCards[0]){
   comparisonCards[0].key=e.target.value;
   comparisonCards[0].dimension=document.getElementById('comparison-group')?.value||'faculty_subject';
  }
  renderComparisons();
 });
 document.getElementById('compare-b')?.addEventListener('change',e=>{
  if(comparisonCards[1]){
   comparisonCards[1].key=e.target.value;
   comparisonCards[1].dimension=document.getElementById('comparison-group')?.value||'faculty_subject';
  }
  renderComparisons();
 });

 setupTabs();
 load(new URLSearchParams(location.search));
})();
