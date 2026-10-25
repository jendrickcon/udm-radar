<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/constants.php';

/** Domain clipping only: this helper does not infer or calibrate a margin. */
function analyticsClipRange(float $lower,float $upper):?array {
    if(!is_finite($lower)||!is_finite($upper)||$lower>$upper)return null;
    return ['lower'=>max(1.0,min(4.0,$lower)),'upper'=>max(1.0,min(4.0,$upper))];
}

function analyticsPrediction(array $row):array {
    $value=$row['predicted_gwa']??null;
    $estimate=is_numeric($value)&&is_finite((float)$value)&&(float)$value>=1&&(float)$value<=4?(float)$value:null;
    $source=normalizePredictionSourceBoundary($row['prediction_source']??null);
    $family=getPredictionSourceFamily($source);
    $complete=$row['data_completeness']??'legacy_unknown';
    $provisional=!empty($row['is_provisional'])||$complete!=='complete';
    return ['estimated_gwa'=>$estimate,'advisory_indicator'=>$estimate!==null?computeRiskFromAvg($estimate):null,
        'source'=>$source,'source_family'=>$family,'source_label'=>$estimate===null?'Insufficient data':match($source){
            'decision_tree'=>'Decision Tree Regressor','heuristic'=>'Legacy calculation estimate','calculation_fallback'=>'Calculation fallback',default=>'Source unavailable'},
        'completeness'=>$estimate===null?'insufficient_data':$complete,'is_provisional'=>$estimate!==null&&$provisional,
        'generated_at'=>$row['generated_at']??null,'lower'=>null,'upper'=>null,'range_status'=>'unavailable',
        'range_reason'=>'Range unavailable: no accepted held-out residual set is configured. A numerical margin is not inferred from RMSE.',
        'explanation'=>'The regressor outputs a numerical estimated GWA. High, Moderate or Low is a separate rule-derived advisory indicator. Calculation fallbacks are not model outputs. Estimates are not guaranteed outcomes.'];
}
