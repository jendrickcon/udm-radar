<?php
declare(strict_types=1);
require_once __DIR__.'/../fixtures/analytics_mvp_fixture.php';
$passed=0;$failed=0;
function fixtureCheck(bool $ok,string $label):void{global $passed,$failed;if($ok)$passed++;else{$failed++;echo "FAIL: $label\n";}}
$sql="-- DDL-only probe, never executed\nCREATE TABLE `probe_a` (note VARCHAR(50) COMMENT 'literal; INSERT INTO');\nINSERT INTO probe_a VALUES ('must not import');\nCREATE TABLE `probe_b` (id INT);\nUPDATE probe_b SET id=1;";
$sections=[];$blocks=analyticsFixtureSchemaStatements($sql,$sections);
fixtureCheck(count($blocks)===2,'Only CREATE TABLE statements returned');
fixtureCheck(!str_contains(implode("\n",$blocks),'must not import')&&!str_contains(implode("\n",$blocks),'UPDATE probe_b'),'DML excluded, including application/example data');
fixtureCheck(count($sections)===2,'Exact DDL source sections recorded');
fixtureCheck(($sections[0]['table']??null)==='probe_a'&&($sections[0]['start_line']??null)===2&&($sections[0]['end_line']??null)===2,'First source section exact');
fixtureCheck(($sections[1]['table']??null)==='probe_b'&&($sections[1]['start_line']??null)===4,'Second source section exact');
fixtureCheck(($sections[0]['sha256']??null)===hash('sha256',$blocks[0]),'Executed DDL fingerprint recorded');
try{analyticsFixtureSchemaStatements("CREATE TABLE `bad` (note VARCHAR(5) COMMENT 'unterminated);");fixtureCheck(false,'Reject malformed input');}catch(RuntimeException){fixtureCheck(true,'Malformed input rejected');}
echo "RESULT $passed passed, $failed failed; no database or protected dump accessed\n";exit($failed?1:0);
