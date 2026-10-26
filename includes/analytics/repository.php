<?php
declare(strict_types=1);
require_once __DIR__.'/calculations.php';

/** SELECT-only repository; scope is applied before data leaves the database. */
function analyticsReadData(PDO $db,array $user,?array $currentTerm=null):array {
    $id=(int)($user['id']??0);$role=$user['role']??'';
    if($id<1||!in_array($role,['student','faculty','admin'],true))throw new DomainException('Analytics access denied');
    $account=$db->prepare('SELECT role,is_active FROM users WHERE id=?');$account->execute([$id]);$actual=$account->fetch();
    if(!$actual||(int)$actual['is_active']!==1||$actual['role']!==$role)throw new DomainException('Analytics account scope changed');
    $term=$currentTerm??getCurrentTerm();
    $where="u.role='student' AND u.is_active=1";$params=[];
    if($role==='student'){$where.=' AND g.student_id=?';$params[]=$id;}
    else{$where.=" AND sp.record_status='Active'";}
    if($role==='faculty'){
        $where.=' AND g.is_current=1 AND g.school_year=? AND g.semester=? AND EXISTS (SELECT 1 FROM faculty_class_loads own WHERE own.faculty_user_id=? AND own.subject_id=g.subject_id AND own.section=sp.section)';
        array_push($params,$term['school_year'],(int)$term['semester'],$id);
    }
    $stmt=$db->prepare("SELECT g.id,g.student_id,g.subject_id,g.school_year,g.semester,g.final_grade,g.prelim,g.midterm,g.prefinal,g.is_current,
        s.code,s.title,s.units,s.year_level AS curriculum_year,sp.section AS current_section,
        (SELECT GROUP_CONCAT(DISTINCT f.faculty_user_id) FROM faculty_class_loads f WHERE f.subject_id=g.subject_id AND f.section=sp.section) AS current_faculty_ids
        FROM grades g JOIN subjects s ON s.id=g.subject_id JOIN student_profiles sp ON sp.user_id=g.student_id JOIN users u ON u.id=g.student_id
        WHERE $where ORDER BY g.school_year,g.semester,g.id");
    $stmt->execute($params);$grades=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $faculty=[];
    if($role!=='student'){
        $q=$db->prepare("SELECT id,first_name,last_name FROM users WHERE role='faculty'".($role==='faculty'?' AND id=?':'').' ORDER BY id');
        $q->execute($role==='faculty'?[$id]:[]);
        foreach($q->fetchAll() as $f)$faculty[(int)$f['id']]=trim($f['first_name'].' '.$f['last_name']);
    }
    foreach($grades as &$g){
        $current=(int)$g['is_current']===1&&$g['school_year']===$term['school_year']&&(int)$g['semester']===(int)$term['semester'];
        $g['section']=$current?$g['current_section']:null;
        $g['faculty_ids']=$current&&$g['current_faculty_ids']?array_map('intval',explode(',',$g['current_faculty_ids'])):[];
        $g['faculty_id']=$role==='faculty'?$id:(count($g['faculty_ids'])===1?$g['faculty_ids'][0]:null);
        $g['faculty_name']=$g['faculty_id']!==null?($faculty[$g['faculty_id']]??'Recorded assignment'):'Assignment unavailable';
    }unset($g);
    $predictions=[];$students=array_values(array_unique(array_column($grades,'student_id')));
    if($students){
        $in=implode(',',array_fill(0,count($students),'?'));
        $q=$db->prepare("SELECT p.* FROM predictions p WHERE p.student_id IN ($in) AND p.id=(SELECT newer.id FROM predictions newer WHERE newer.student_id=p.student_id ORDER BY newer.generated_at DESC,newer.id DESC LIMIT 1)");
        $q->execute($students);foreach($q->fetchAll() as $p)$predictions[(int)$p['student_id']]=$p;
    }
    // An own-account estimate can exist before any grade row is available.
    if($role==='student'&&!isset($predictions[$id])){
        $q=$db->prepare('SELECT * FROM predictions WHERE student_id=? ORDER BY generated_at DESC,id DESC LIMIT 1');$q->execute([$id]);$p=$q->fetch();if($p)$predictions[$id]=$p;
    }
    $cases=[];$referrals=[];$actions=[];
    if($role!=='student'){
        $caseWhere="sp.record_status='Active' AND u.is_active=1 AND u.role='student'";$caseParams=[];
        if($role==='faculty'){$caseWhere.=' AND EXISTS (SELECT 1 FROM support_case_referrals own WHERE own.case_id=c.id AND own.faculty_id=?)';$caseParams[]=$id;}
        $q=$db->prepare("SELECT c.id,c.student_id,c.school_year,c.semester,c.status,c.created_at,c.closed_at
            FROM academic_support_cases c JOIN student_profiles sp ON sp.user_id=c.student_id JOIN users u ON u.id=c.student_id WHERE $caseWhere ORDER BY c.id");
        $q->execute($caseParams);$cases=$q->fetchAll();$ids=array_column($cases,'id');
        if($ids){
            $in=implode(',',array_fill(0,count($ids),'?'));
            $q=$db->prepare("SELECT r.id,r.case_id,r.faculty_id,r.subject_id,r.section,r.status,r.created_at,r.notice_issued_at,r.student_acknowledged_at,s.code,s.title,s.year_level AS curriculum_year
                FROM support_case_referrals r JOIN subjects s ON s.id=r.subject_id JOIN users f ON f.id=r.faculty_id AND f.role='faculty'
                WHERE r.case_id IN ($in)".($role==='faculty'?' AND r.faculty_id=?':'').' ORDER BY r.id');
            $q->execute($role==='faculty'?[...$ids,$id]:$ids);$referrals=$q->fetchAll();
            foreach([['support_actions','actor_id'],['support_status_history','changed_by']] as [$table,$actor]){
                $q=$db->prepare("SELECT a.case_id,a.created_at,u.role AS actor_role FROM $table a JOIN users u ON u.id=a.$actor AND u.role='faculty' WHERE a.case_id IN ($in)".($role==='faculty'?" AND a.$actor=?":'').' ORDER BY a.created_at');
                $q->execute($role==='faculty'?[...$ids,$id]:$ids);$actions=array_merge($actions,$q->fetchAll());
            }
        }
    }
    return ['grades'=>$grades,'predictions'=>$predictions,'faculty'=>$faculty,'cases'=>$cases,'referrals'=>$referrals,'actions'=>$actions,'current_term'=>$term];
}
