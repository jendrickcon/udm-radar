<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/constants.php';
require_once __DIR__ . '/pef_cc_view_model.php';

/**
 * Student-owned, SELECT-only source for planning exports.
 * The caller supplies the authenticated users.id, never a request field.
 * A read-only transaction is owned by the route so one model sees one snapshot.
 */
function pefCcReadContext(PDO $db, int $studentId): array
{
    if ($studentId <= 0) {
        throw new InvalidArgumentException('An authenticated Student is required.');
    }

    $identityQuery = $db->prepare(
        'SELECT u.id, u.user_id AS account_student_number, u.first_name, u.middle_name, u.last_name, u.role, u.is_active,
                sp.id AS profile_id, sp.student_number, sp.college, sp.course AS program,
                sp.year_level, sp.section, sp.status, sp.record_status
           FROM users u
           LEFT JOIN student_profiles sp ON sp.user_id = u.id
          WHERE u.id = ?
          ORDER BY sp.id'
    );
    $identityQuery->execute([$studentId]);
    $identities = $identityQuery->fetchAll(PDO::FETCH_ASSOC);
    if (count($identities) !== 1 || $identities[0]['profile_id'] === null) {
        throw new RuntimeException('Requires record review: Student profile is missing or ambiguous.');
    }
    $identity = $identities[0];
    if ($identity['role'] !== 'student' || (string) $identity['is_active'] !== '1') {
        throw new RuntimeException('An active Student account is required.');
    }
    if ($identity['student_number'] !== $identity['account_student_number']) {
        throw new RuntimeException('Requires record review: Student identity records disagree.');
    }
    $identity['name'] = formatNameLastFirst($identity['first_name'], $identity['middle_name'], $identity['last_name']);
    unset($identity['profile_id'], $identity['account_student_number'], $identity['first_name'], $identity['middle_name'], $identity['last_name']);
    $identity['id'] = (int) $identity['id'];
    $identity['is_active'] = (int) $identity['is_active'];

    // No final-grade/current flag filter, INNER JOIN, pagination, or truncation:
    // missing subject references and every member of mixed terms stay visible.
    $recordsQuery = $db->prepare(
        'SELECT g.id, g.subject_id, g.school_year, g.semester, g.is_current,
                s.code, s.title, s.units, g.final_grade AS grade
           FROM grades g
           JOIN users u ON u.id = g.student_id
           LEFT JOIN subjects s ON s.id = g.subject_id
          WHERE u.id = ?
          ORDER BY g.school_year, g.semester, s.code, g.subject_id, g.id'
    );
    $recordsQuery->execute([$studentId]);
    $records = $recordsQuery->fetchAll(PDO::FETCH_ASSOC);

    // This is the non-personal subject catalogue, not another Student's history.
    $catalogQuery = $db->query("SELECT id, code, title, units, year_level, semester FROM subjects WHERE code NOT REGEXP '^(CY|DS)' ORDER BY code, id");
    $catalog = $catalogQuery->fetchAll(PDO::FETCH_ASSOC);

    // Nonpersonal active SD section evidence. A unique coherent current term
    // corroborates the profile year; the workbook vocabulary alone cannot.
    $sourceRows = $db->query(
        "SELECT sp.user_id AS context_key, sp.section, sp.year_level, sp.course AS program,
                sp.record_status, u.role, u.is_active,
                g.id, g.subject_id, g.school_year, g.semester, g.is_current,
                s.code, s.title, s.units, NULL AS grade
           FROM student_profiles sp
           JOIN users u ON u.id=sp.user_id
           LEFT JOIN grades g ON g.student_id=u.id AND (g.is_current<>0 OR g.is_current IS NULL)
           LEFT JOIN subjects s ON s.id=g.subject_id
          WHERE u.role='student' AND u.is_active=1 AND sp.record_status='Active'
            AND sp.course='BSIT - Software Development'
            AND sp.section REGEXP '^IT-[1-4][1-9]$'
            AND (SELECT COUNT(*) FROM student_profiles ap WHERE ap.user_id=u.id)=1
          ORDER BY sp.user_id, g.id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $sourceContexts = [];
    foreach ($sourceRows as $row) {
        $key = $row['context_key'];
        $sourceContexts[$key]['identity'] = array_intersect_key($row, array_flip([
            'section', 'year_level', 'program', 'record_status', 'role', 'is_active',
        ]));
        if ($row['id'] !== null) {
            $sourceContexts[$key]['records'][] = array_intersect_key($row, array_flip([
                'id', 'subject_id', 'school_year', 'semester', 'is_current', 'code', 'title', 'units', 'grade',
            ]));
        }
    }
    // Shared PHP validation handles NULLs, duplicates, orphan subjects and
    // curriculum mismatches; SQL MIN/COUNT must not manufacture a term tuple.
    $sections = pefCcVerifiedSectionEvidence($sourceContexts, $catalog, getCurrentTerm());

    return ['identity' => $identity, 'records' => $records, 'catalog' => $catalog, 'sections' => $sections];
}
