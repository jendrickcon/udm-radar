(() => {
 'use strict';
 const root=document.getElementById('academic-analytics');if(!root)return;
 const role=root.dataset.role, form=document.getElementById('analytics-filters'), results=document.getElementById('analytics-results'), state=document.getElementById('analytics-state');
 const labels={school_year:'School year',semester:'Semester',year_level:'Curriculum year',subject:'Subject',section:'Section',faculty:'Faculty',case_status:'Case status',decision:'Referral decision'};
 const dimensions={semester:'Semester',school_year:'School year',year_level:'Subject curriculum year',subject:'Subject',section:'Current section',faculty_subject:'Faculty–subject assignment'};
 let report=null,controller=null,generation=0;
 const el=(tag,text,attrs={})=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=text;for(const[k,v]of Object.entries(attrs))n.setAttribute(k,v);return n;};
 const num=v=>v===null||v===undefined?'N/A':Number(v).toFixed(2);
 const change=(v,suffix='')=>v===null||v===undefined?'Unavailable':(Number(v)>0?'+':'')+Number(v).toFixed(2)+suffix;
 const pct=v=>v===null?'Unavailable':num(v)+'%';
 function metrics(parent,items){const dl=el('dl',undefined,{class:'analytics-metrics'});for(const[label,value,key]of items){const d=el('div');d.append(el('dt',label),el('dd',value,{'data-metric':key}));dl.append(d);}parent.append(dl);}
 function table(target,headers,rows,empty,caption){target.replaceChildren();if(caption)target.append(el('caption',caption));const head=el('thead'),tr=el('tr');headers.forEach(h=>tr.append(el('th',h,{scope:'col'})));head.append(tr);const body=el('tbody');if(!rows.length){const row=el('tr');row.append(el('td',empty,{colspan:headers.length}));body.append(row);}for(const cells of rows){const row=el('tr');for(const cell of cells){const td=el('td');td.append(cell instanceof Node?cell:document.createTextNode(String(cell)));row.append(td);}body.append(row);}target.append(head,body);}
 const svgEl=(tag,attrs={},text)=>{const n=document.createElementNS('http://www.w3.org/2000/svg',tag);Object.entries(attrs).forEach(([k,v])=>n.setAttribute(k,String(v)));if(text!==undefined)n.textContent=text;return n;};
 function chart(target,series,kind,title){
  target.replaceChildren();if(!series.length||!series.some(x=>x.gwa!==null)){target.append(el('p','No numeric GWA is available for this view.'));return;}
  const data=kind==='bar'?series.slice(0,12):series;const height=kind==='bar'?Math.max(100,data.length*34+25):240;
  const svg=svgEl('svg',{viewBox:`0 0 640 ${height}`,role:'img','aria-label':title,'aria-describedby':'active-filters'});svg.append(svgEl('title',{},title),svgEl('desc',{},'Values, Student counts and coverage appear in the adjacent data table.'));
  if(kind==='bar')data.forEach((p,i)=>{const y=i*34+10;const group=svgEl('g',{tabindex:0});group.append(svgEl('title',{},`${p.label}: ${num(p.gwa)}, n=${p.student_count}, numeric coverage ${pct(p.numeric_coverage_pct)}`));group.append(svgEl('text',{x:0,y:y+15},`${i+1}. ${p.label.slice(0,23)}`));if(p.gwa!==null)group.append(svgEl('rect',{x:210,y,width:Math.max(0,Math.min(4,p.gwa))/4*340,height:20,class:'analytics-bar'}));group.append(svgEl('text',{x:560,y:y+15},num(p.gwa)));svg.append(group);});
  else{
   svg.append(svgEl('line',{x1:40,x2:620,y1:200,y2:200,class:'analytics-axis'}));let segment=[];
   const flush=()=>{if(segment.length>1)svg.append(svgEl('polyline',{points:segment.join(' '),class:'analytics-line'}));segment=[];};
   data.forEach((p,i)=>{const x=50+(data.length===1?0:i/(data.length-1)*550);svg.append(svgEl('text',{x,y:225,'text-anchor':'middle'},String(i+1)));if(p.gwa===null){flush();return;}const y=195-(p.gwa-1)/3*160;segment.push(`${x},${y}`);const dot=svgEl('circle',{cx:x,cy:y,r:5,class:'analytics-dot',tabindex:0});dot.append(svgEl('title',{},`${p.label}: ${num(p.gwa)}${p.provisional?' (provisional)':''}, n=${p.student_count}`));svg.append(dot,svgEl('text',{x,y:y-12,'text-anchor':'middle'},num(p.gwa)));});flush();
  }
  target.append(svg);if(kind==='bar'&&series.length>12)target.append(el('p','First 12 groups shown. The table contains all matching groups.'));
 }
 function renderPrediction(p){
  const box=document.getElementById('analytics-prediction');box.replaceChildren();if(!p)return;
  box.append(el('h2','Latest stored estimate'),el('p',p.explanation));
  metrics(box,[['Estimated GWA',num(p.estimated_gwa),'estimate'],['Lower estimate',p.lower===null?'Unavailable':num(p.lower),'lower'],['Upper estimate',p.upper===null?'Unavailable':num(p.upper),'upper'],['Advisory indicator',p.advisory_indicator||'Unavailable','advisory']]);
  box.append(el('p',p.source_label),el('p',`${p.is_provisional?'Provisional · ':''}Completeness: ${p.completeness.replaceAll('_',' ')} · Generated: ${p.generated_at||'Unavailable'}`),el('p',p.range_reason));
 }
 function renderComparisons(){
  if(role==='student')return;const selector=document.getElementById('comparison-group'),dimension=selector.value,groups=report.groups[dimension]||[];
  const description=`${dimensions[dimension]} · ${groups.length} groups · n=${report.summary.student_count}. Numeric coverage: ${pct(report.summary.numeric_coverage_pct)}. Pooled unit-weighted recorded grades; this is not an arithmetic mean of Student GWAs. `+(['section','faculty_subject'].includes(dimension)&&!report.filters.subject?'Select one subject for a same-subject comparison. ':'')+'Group order is identifier/period order, not a best/worst ranking.';
  document.getElementById('comparison-description').textContent=description;
  chart(document.getElementById('comparison-chart'),groups,'bar',dimensions[dimension]+' average GWA');
  table(document.getElementById('comparison-table'),['Group','Average GWA','Students (n)','Eligible / recorded grades','Numeric coverage','Final-outcome coverage'],groups.map(g=>[g.label,num(g.gwa)+(g.provisional?' (provisional)':''),g.student_count,`${g.eligible_grade_count} / ${g.expected_grade_count}`,pct(g.numeric_coverage_pct),pct(g.final_coverage_pct)]),'No records match these filters.',description);
 }
 function renderSupport(){
  if(!report.support)return;const s=report.support;
  const names={eligible_cases:'Recorded eligible cases',referrals_created:'Referrals created',referrals_reviewed:'Referrals with recorded review status',notices_issued:'Notices issued',notices_deferred:'Notices deferred',student_acknowledgments:'Recorded Student receipt acknowledgments (count)',open_cases:'Open cases',closed_cases:'Closed cases',mean_first_action_hours:'Mean referral time to first evidenced notice (MTR-20)',median_closure_hours:'Median recorded parent-case closed duration (limited indicator)'};
  const rows=Object.entries(names).map(([key,label])=>{let value=s[key]===null?'Unavailable':String(s[key]);if(key==='mean_first_action_hours'&&s[key]!==null)value=num(s[key])+` hours (n=${s.first_action_sample})`;if(key==='median_closure_hours'&&s[key]!==null)value=num(s[key])+` hours (n=${s.closure_sample})`;return[label,el('span',value,{'data-support':key})];});
  table(document.getElementById('support-table'),['Measure','Recorded result'],rows,'No cases match these filters.',`Cases n=${s.eligible_cases}; referrals n=${s.referrals_created}. Counts describe separate record types.`);
  document.getElementById('support-limits').textContent=s.deferral_reason+' MTR-20 is the arithmetic mean from each distinct referral creation to its first recorded notice; earlier unrecorded actions cannot be inferred. Missing, invalid or reversed times are excluded. Receipt acknowledgments are recorded counts, including valid legacy receipt timestamps without linked issuance evidence, not MTR-19 rates. The parent-case closed-duration median is a limited recorded indicator, not canonical MTR-21: referral closure and reopening equivalence remain blocked. No teaching-quality or causal claim follows from these durations.';
  table(document.getElementById('drilldown-table'),['Authorized record','Status'],report.drilldowns.map(d=>[el('a',d.label,{href:d.href}),d.status.replaceAll('_',' ')]),'No authorized records match these filters.');
 }
 function render(data){
  report=data;
  for(const select of form.querySelectorAll('select')){const key=select.name;select.replaceChildren(el('option','All recorded values',{value:''}));for(const o of data.options[key]||[])select.append(el('option',o.label,{value:o.value}));if(data.filters[key]&&!Array.from(select.options).some(o=>o.value===data.filters[key]))select.append(el('option',data.filters[key]+' (no matching data)',{value:data.filters[key]}));select.value=data.filters[key]||'';select.disabled=role==='faculty'&&key==='faculty';}
  form.querySelector('fieldset').disabled=false;
  const active=Object.entries(data.filters).filter(([,v])=>v!=='').map(([k,v])=>`${labels[k]}: ${(data.options[k]||[]).find(o=>o.value===v)?.label||v}`);
  document.getElementById('active-filters').textContent=active.length?'Active filters · '+active.join(' · '):'Active filters · All authorized recorded values';
  const box=document.getElementById('analytics-summary');box.replaceChildren();const t=data.trend;
  if(role==='student'){
   metrics(box,[['Current selected term GWA',num(t.current?.gwa),'current-gwa'],['Previous comparable GWA',num(t.previous?.gwa),'previous-gwa'],['Point change',change(t.change.point_change),'point-change']]);
   const relative=el('p','Relative change (supporting context): ',{class:'analytics-note'});relative.append(el('span',change(t.change.relative_change_pct,'%'),{'data-metric':'relative-change'}));box.append(relative);
   box.append(el('p',`Current period: ${t.current?.label||'Unavailable'} · Previous recorded comparable period: ${t.previous?.label||'Unavailable'} · Direction: ${t.change.direction}.`));
  }else{
   metrics(box,[['Weighted recorded-grade mean',num(data.summary.gwa),'average-gwa'],['Students (n)',String(data.summary.student_count),'student-count'],['Eligible grades',`${data.summary.eligible_grade_count} / ${data.summary.expected_grade_count}`,'eligible-grades'],['Numeric GWA coverage',pct(data.summary.numeric_coverage_pct),'numeric-coverage']]);
   box.append(el('p',`Comparison period: ${t.current?.label||'Unavailable'} versus ${t.previous?.label||'Unavailable'} · Point change: ${change(t.change.point_change)} · Relative change: ${change(t.change.relative_change_pct,'%')}.`));
   if(role==='faculty'){metrics(box,[['Students with recorded numeric review indicators',String(data.students_requiring_review),'review-count']]);box.append(el('p',data.faculty_comparison.reason));}
  }
  box.append(el('p',`${data.summary.provisional?'Provisional: some final outcomes or units are incomplete. ':''}GWA uses ${data.summary.eligible_units} eligible credit units. Final-outcome coverage: ${pct(data.summary.final_coverage_pct)}. Changes are unavailable when the compared term is incomplete or has no numeric GWA.`));
  renderPrediction(data.prediction);
  document.getElementById('trend-description').textContent=`${t.series.length} recorded terms · n=${data.summary.student_count}. Points follow term order; gaps mean unavailable values. Refer to the table for periods and coverage.`;
  chart(document.getElementById('trend-chart'),t.series,'line','Historical semester GWA');
  table(document.getElementById('trend-table'),['# / Recorded term','Recorded-point mean','Status','Students (n)','Eligible / recorded grades','Numeric coverage'],t.series.map((p,i)=>[`${i+1}. ${p.label}`,num(p.gwa),p.provisional?'Provisional partial records; term GWA pending':'Recorded',p.student_count,`${p.eligible_grade_count} / ${p.expected_grade_count}`,pct(p.numeric_coverage_pct)]),'No recorded terms match these filters.');
  if(role!=='student'){
   const selector=document.getElementById('comparison-group'),previous=selector.value;selector.replaceChildren();Object.keys(data.groups).forEach(key=>selector.append(el('option',dimensions[key],{value:key})));if(data.groups[previous])selector.value=previous;renderComparisons();renderSupport();
  }
  const methods=document.getElementById('analytics-method');methods.replaceChildren();methods.append(el('p','Average GWA is the sum of eligible final points × credit units divided by eligible credit units. It is not a sum of Student GWAs. Relative change is (current − previous) / previous × 100; it is not the prediction.'));
  (role==='student'?[data.warnings[0],data.warnings[2]]:data.warnings).forEach(w=>methods.append(el('p',w)));
  if(role!=='student')methods.append(el('p',`Latest stored prediction coverage: ${data.prediction_coverage.available_count} of ${data.prediction_coverage.student_count} represented Students; model ${data.prediction_coverage.model_count}, calculation ${data.prediction_coverage.calculation_count}, provisional/unknown-completeness ${data.prediction_coverage.provisional_count}. Provisional status overlaps the model/calculation counts; these are not additive categories. Complete versus provisional fallback cross-tabs are not provided. These snapshots are separate from historical comparison periods and do not measure prediction accuracy or full active-population coverage.`));
 }
 async function load(params){
  const ticket=++generation;if(controller)controller.abort();controller=new AbortController();results.hidden=true;results.setAttribute('aria-busy','true');state.setAttribute('role','status');state.textContent='Loading academic review…';
  try{const url=new URL(root.dataset.endpoint,location.origin);url.search=params.toString();const response=await fetch(url,{credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Unavailable');const data=await response.json();if(ticket!==generation)return;render(data);results.hidden=false;results.setAttribute('aria-busy','false');state.textContent='Academic review updated.';}
  catch(error){if(error.name==='AbortError'||ticket!==generation)return;state.setAttribute('role','alert');state.textContent='Academic review could not be loaded. Try applying the filters again.';results.hidden=true;results.setAttribute('aria-busy','false');}
 }
 form.addEventListener('submit',event=>{event.preventDefault();load(new URLSearchParams(new FormData(form)));});
 document.getElementById('analytics-clear').addEventListener('click',()=>load(new URLSearchParams()));
 document.getElementById('comparison-group')?.addEventListener('change',renderComparisons);
 load(new URLSearchParams(location.search));
})();
