(function () {
    'use strict';
    const math=window.GoalCalculator;
    const rows=()=>[...document.querySelectorAll('#calculator-section .calc-row')];
    const inputs=row=>[...row.querySelectorAll('.calc-term-input')];
    function evidence(row) {
        return inputs(row).map(input=>input.dataset.periodState==='unavailable' ? 'unresolved' : input.dataset.suggested==='true' ? null : input.value);
    }
    function clearSuggestions(row) {
        for (const input of inputs(row)) if (input.dataset.suggested==='true') { input.value='';delete input.dataset.suggested; }
    }
    function say(element,text) { if (element.textContent!==text) element.textContent=text; }
    function forward(row) {
        const values=inputs(row).map(input=>input.value);
        const state=math.evaluate(evidence(row),null);
        const point=state.bestReachablePercentage===null?null:math.scenarioPoint(values);
        row.dataset.scenarioPoint=point===null?'':String(point);
        return point;
    }
    function update(row,{calculate=false,changed=false}={}) {
        const select=row.querySelector('.calc-final-input'), oldGoal=select.value;
        const state=math.evaluate(evidence(row),null);
        const unavailable=state.bestReachablePoint===null;
        let feasible=0;
        for (const option of select.options) if (option.value!=='') {
            const allowed=math.evaluate(evidence(row),option.value).isFeasible;
            option.disabled=!allowed;option.textContent=option.value;
            option.title=allowed?'':'Not mathematically reachable with the available grades and remaining period weights.';
            if (allowed) feasible++;
        }
        select.disabled=unavailable||feasible===0;
        let goal=math.evaluate(evidence(row),oldGoal), message='';
        if (oldGoal!==''&&!goal.isFeasible) {
            select.value='';clearSuggestions(row);
            message=unavailable?'Calculation unavailable: a period grade is unresolved or invalid.':feasible===0?'The available grades and remaining weights do not permit another selectable goal.':changed?'The previous goal is no longer reachable. Choose another goal.':'This target is not reachable with the remaining terms, because it would need more than 100%. Try a lower target.';
        } else if (unavailable) message='Calculation unavailable: a period grade is unresolved or invalid.';
        else if (feasible===0) message='The available grades and remaining weights do not permit another selectable goal.';
        else if (oldGoal!==''&&calculate) {
            // Guard every calculation independently of the native disabled option state.
            goal=math.evaluate(evidence(row),select.value);
            if (goal.reason==='completed') message='Goal already achieved. All periods are complete; no remaining grade is needed.';
            else if (goal.isFeasible) {
                for (const input of inputs(row)) if (!input.disabled&&(input.value===''||input.dataset.suggested==='true')) {
                    input.value=String(goal.suggestedRemainingGrade);input.dataset.suggested='true';
                }
                message=goal.reason==='already_achieved'?'Goal already achieved from the completed contribution.':`At least ${goal.suggestedRemainingGrade.toFixed(2)}% average is needed across the remaining periods. Temporary scenario only.`;
            }
        }
        const point=forward(row);
        if (!message&&state.remainingWeight===0) message='All periods are complete; no remaining weight. Choose a met goal to review it.';
        if (!message&&point!==null) message='Temporary scenario only. Your recorded grades are unchanged.';
        if (!message) message='Choose a reachable target or enter a temporary scenario.';
        const limit=unavailable?'Highest reachable final point grade: unavailable.':`Highest reachable final point grade: ${state.bestReachablePoint.toFixed(2)}.`;
        say(row.querySelector('.calc-goal-limit'),limit);
        say(row.querySelector('.calc-row-result'),(point===null?'':`Scenario final grade: ${point.toFixed(2)}. `)+message);
        calculateOverallGwa();
    }
    function computeRowFinal(input) {
        const row=input.closest('.calc-row');delete input.dataset.suggested;clearSuggestions(row);
        update(row,{calculate:row.querySelector('.calc-final-input').value!=='',changed:true});
    }
    function seekGrades(select) {
        const row=select.closest('.calc-row');clearSuggestions(row);update(row,{calculate:true});
    }
    function calculateOverallGwa() {
        const value=math.weightedGwa(rows().map(row=>({grade:row.dataset.scenarioPoint,units:row.querySelector('.calc-units').textContent.trim()})));
        const output=document.getElementById('projected-gwa');output.textContent=value===null?'N/A':value.toFixed(2);
        output.style.color=value===null?'var(--accent-blue)':value>=3.25?'var(--risk-low)':value>=2.50?'var(--risk-mod)':'var(--risk-high)';
    }
    function toggleCalculator() {
        const section=document.getElementById('calculator-section');
        if (section.style.display==='none') {
            section.style.display='block';rows().forEach(row=>update(row,{calculate:row.querySelector('.calc-final-input').value!==''}));
            section.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});
        } else section.style.display='none';
    }
    Object.assign(window,{toggleCalculator,computeRowFinal,seekGrades,calculateOverallGwa});
    rows().forEach(row=>update(row));
})();
