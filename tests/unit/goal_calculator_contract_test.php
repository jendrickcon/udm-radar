<?php
require_once dirname(__DIR__,2).'/includes/goal_calculator.php';
$checks=$failures=0;
function checkGoal(bool $ok,string $message):void { global $checks,$failures; $checks++;if(!$ok)$failures++;echo ($ok?'[PASS] ':'[FAIL] ').$message.PHP_EOL; }
$config=goalCalculatorConfig();
checkGoal($config['weights']===[0.3,0.3,0.4], 'Canonical 30/30/40 weights');
checkGoal($config['maximumPercentage']===100&&isValidTermPercentage(100)&&!isValidTermPercentage(100.01),'Validated 100 percent maximum');
checkGoal(count($config['targets'])===count(FINAL_GRADE_POINTS),'All existing target choices');
foreach($config['targets'] as $i=>$target){
 checkGoal(formatFinalGrade($target['point'])===FINAL_GRADE_POINTS[$i],'Canonical option order '.$i);
 checkGoal(convertPercentageToPoint($target['minimumPercentage'])===$target['point'],'Canonical threshold '.$i);
 checkGoal(convertPercentageToPoint($target['minimumPercentage']-.01)<$target['point'],'Threshold lower bound '.$i);
}
checkGoal(goalCalculatorPeriod(89.99)===['value'=>'89.99','state'=>'recorded'],'Recorded decimal precision retained');
checkGoal(goalCalculatorPeriod(0)===['value'=>'0','state'=>'recorded'],'Numeric zero remains recorded');
checkGoal(goalCalculatorPeriod(null)['state']==='remaining','Missing period remains available');
foreach(['INC','DRP','P','90x',-1,101,true] as $value) checkGoal(goalCalculatorPeriod($value)['state']==='unavailable','Invalid evidence is never zero: '.get_debug_type($value));
checkGoal(!in_array(0.0,$config['gwaPoints'],true),'Canonical GWA zero exclusion');
echo "SUMMARY: Ran $checks checks, $failures failures.\n";exit($failures?1:0);
