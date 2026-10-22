(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory;
    else {
        root.GoalCalculator = factory(JSON.parse(document.getElementById('goal-calculator-config').textContent));
    }
})(typeof window === 'undefined' ? globalThis : window, function createGoalCalculator(config) {
    'use strict';
    const epsilon = 0.000001;
    const weights = Object.freeze([...config.weights]);
    const targets = Object.freeze(config.targets.map(target => Object.freeze({...target})));
    const maximum = config.maximumPercentage;
    if (weights.length !== 3 || weights.some(w => !Number.isFinite(w) || w <= 0) || Math.abs(weights.reduce((a,b) => a+b, 0)-1) > epsilon || maximum !== 100) throw new Error('Calculator contract unavailable.');
    function number(value) {
        if (typeof value === 'number') return Number.isFinite(value) ? value : NaN;
        if (typeof value !== 'string' || !/^\d+(?:\.\d{1,2})?$/.test(value.trim())) return NaN;
        return Number(value);
    }
    function toPoint(percentage) {
        return targets.find(target => percentage >= target.minimumPercentage)?.point ?? 0;
    }
    function evaluate(periods, target) {
        const result = {isFeasible:false, reason:'insufficient_data', remainingWeight:0, completedContribution:0, bestReachablePercentage:null, bestReachablePoint:null, requiredRemainingGrade:null, suggestedRemainingGrade:null};
        if (!Array.isArray(periods) || periods.length !== weights.length) return result;
        for (let i=0;i<weights.length;i++) {
            if (periods[i] === null || periods[i] === '') result.remainingWeight += weights[i];
            else {
                const value = number(periods[i]);
                if (!Number.isFinite(value) || value < 0 || value > maximum) return result;
                result.completedContribution += value * weights[i];
            }
        }
        result.bestReachablePercentage = result.completedContribution + maximum * result.remainingWeight;
        result.bestReachablePoint = toPoint(result.bestReachablePercentage + epsilon);
        const goal = targets.find(candidate => candidate.point === number(target));
        if (!goal) return {...result, reason:target === null || target === '' ? 'ready' : 'invalid_target'};
        if (goal.minimumPercentage > result.bestReachablePercentage + epsilon) return {...result, reason:'impossible'};
        result.isFeasible = true;
        if (result.remainingWeight < epsilon) return {...result, remainingWeight:0, reason:'completed'};
        const needed = goal.minimumPercentage - result.completedContribution;
        if (needed <= epsilon) return {...result, reason:'already_achieved', requiredRemainingGrade:0, suggestedRemainingGrade:0};
        const required = Math.min(maximum, Math.max(0, needed / result.remainingWeight));
        return {...result, reason:'reachable', requiredRemainingGrade:required, suggestedRemainingGrade:Math.min(maximum, Math.ceil((required - 1e-9)*100)/100)};
    }
    function weightedGwa(rows) {
        let units=0, weightedQuarters=0;
        for (const row of rows) {
            const grade=number(row.grade), count=Math.trunc(number(row.units));
            if (!config.gwaPoints.includes(grade) || !Number.isFinite(count) || count <= 0) continue;
            units += count; weightedQuarters += Math.round(grade*4) * count;
        }
        // Canonical grades are quarter points and units are whole credits.
        // Scale before division so PHP's positive half-up rounding is preserved.
        return units > 0 ? Math.round(25*weightedQuarters/units)/100 : null;
    }
    function scenarioPoint(periods) {
        const state=evaluate(periods,null);
        return state.bestReachablePoint!==null&&state.remainingWeight===0 ? toPoint(state.completedContribution + epsilon) : null;
    }
    return Object.freeze({epsilon, targets, toPoint, evaluate, weightedGwa, scenarioPoint});
});
