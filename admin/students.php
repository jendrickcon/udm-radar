<?php
require_once '../includes/auth.php';
require_once '../config/constants.php';
require_once '../config/db.php';
require_once '../includes/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

requireRole('admin');
$user = currentUser();
$db = getDB();

const LOCKED_COURSE   = 'Bachelor of Science in Information Technology';
const LOCKED_PASSWORD = 'default1!';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error   = '';
$success = '';
$previewPayload = null;
$resolutionPayload = null;
$matches = [];

// ---------------------------------------------------------
// POST HANDLERS
// ---------------------------------------------------------

// 1. EDIT STUDENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $uid = (int) ($_POST['edit_id'] ?? 0);
        $newFirst   = trim($_POST['edit_first_name'] ?? '');
        $newMiddle  = trim($_POST['edit_middle_name'] ?? '');
        $newLast    = trim($_POST['edit_last_name'] ?? '');
        $newStatus  = trim($_POST['edit_status'] ?? 'Regular');
        $propSection = trim($_POST['propose_section'] ?? '');
        $propYear    = trim($_POST['propose_year_level'] ?? '');
        $reason      = trim($_POST['propose_reason'] ?? '');

        $stmt = $db->prepare("SELECT u.first_name, u.middle_name, u.last_name, sp.section, sp.year_level, sp.status FROM users u JOIN student_profiles sp ON sp.user_id = u.id WHERE u.id = ?");
        $stmt->execute([$uid]);
        $old = $stmt->fetch();

        if (!$old) {
            $error = 'Student not found.';
        } else {
            $fieldsToCheck = [
                'first_name' => [$old['first_name'], $newFirst],
                'middle_name'=> [$old['middle_name'], $newMiddle !== '' ? $newMiddle : null],
                'last_name'  => [$old['last_name'], $newLast],
                'status'     => [$old['status'], $newStatus],
            ];

            $wantsSectionChange = ($propSection !== '' && $propSection !== $old['section']);
            $wantsYearChange    = ($propYear !== '' && (int) $propYear !== (int) $old['year_level']);

            if (($wantsSectionChange || $wantsYearChange) && $reason === '') {
                $error = 'A reason is required to propose a section or year level correction.';
            }

            if (!$error) {
                $effectiveSection = $wantsSectionChange ? $propSection : $old['section'];
                $effectiveYear    = $wantsYearChange ? (int) $propYear : (int) $old['year_level'];
                if ($effectiveSection !== '' && $effectiveYear > 0 && preg_match('/^IT-(\d)\d*$/i', $effectiveSection, $m)) {
                    $sectionYear = (int) $m[1];
                    if ($sectionYear !== $effectiveYear) {
                        $error = "Section \"$effectiveSection\" looks like a Year $sectionYear section, but the resulting Year Level would be $effectiveYear. Propose both changes together so they agree.";
                    }
                }
            }

            if (!$error) {
                try {
                    $db->beginTransaction();
                    $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id=?")->execute([$newFirst, $newMiddle !== '' ? $newMiddle : null, $newLast, $uid]);
                    $db->prepare("UPDATE student_profiles SET status=? WHERE user_id=?")->execute([$newStatus, $uid]);

                    $logStmt = $db->prepare("INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value) VALUES (?, 'student', ?, ?, ?, ?)");
                    $changedCount = 0;
                    foreach ($fieldsToCheck as $field => [$oldVal, $newVal]) {
                        if ((string) $oldVal !== (string) $newVal) {
                            $logStmt->execute([$user['id'], $uid, $field, $oldVal, $newVal]);
                            $changedCount++;
                        }
                    }

                    $proposedCount = 0;
                    $propStmt = $db->prepare("INSERT INTO pending_corrections (proposed_by, target_type, target_id, field_changed, old_value, new_value, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($wantsSectionChange) {
                        $propStmt->execute([$user['id'], 'student_section', $uid, 'section', $old['section'], $propSection, $reason]);
                        $proposedCount++;
                    }
                    if ($wantsYearChange) {
                        $propStmt->execute([$user['id'], 'student_year_level', $uid, 'year_level', $old['year_level'], $propYear, $reason]);
                        $proposedCount++;
                    }

                    $db->commit();
                    $parts = [];
                    if ($changedCount > 0)  $parts[] = "{$changedCount} field(s) updated";
                    if ($proposedCount > 0) $parts[] = "{$proposedCount} correction(s) proposed — pending confirmation";
                    $success = $parts ? implode(', ', $parts) . '.' : 'No changes were made.';
                } catch (PDOException $e) {
                    $db->rollBack();
                    $error = 'Could not update student: ' . $e->getMessage();
                }
            }
        }
    }
}

// 2. DELETE STUDENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!checkCsrf()) {
        $error = 'Session expired.';
    } else {
        $delId = (int) ($_POST['delete_id'] ?? 0);
        $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$delId]);
        $success = 'Student removed.';
    }
}

// 3. CONFIRM/REJECT CORRECTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['confirm_correction', 'reject_correction'])) {
    if (!checkCsrf()) {
        $error = 'Session expired — please refresh the page and try again.';
    } else {
        $corrId = (int) ($_POST['correction_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM pending_corrections WHERE id = ? AND status = 'pending'");
        $stmt->execute([$corrId]);
        $corr = $stmt->fetch();

        if (!$corr) {
            $error = 'Correction not found or already resolved.';
        } elseif (($_POST['action'] ?? '') === 'reject_correction') {
            $db->prepare("UPDATE pending_corrections SET status='rejected', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $corrId]);
            $success = 'Correction rejected — no change applied.';
        } else {
            try {
                $db->beginTransaction();
                $column = $corr['field_changed']; 
                $db->prepare("UPDATE student_profiles SET `$column` = ? WHERE user_id = ?")->execute([$corr['new_value'], $corr['target_id']]);
                $db->prepare("UPDATE pending_corrections SET status='confirmed', resolved_by=?, resolved_at=NOW() WHERE id=?")->execute([$user['id'], $corrId]);
                $db->prepare("INSERT INTO admin_change_log (admin_id, target_type, target_id, field_changed, old_value, new_value) VALUES (?, 'student', ?, ?, ?, ?)")->execute([$user['id'], $corr['target_id'], $column, $corr['old_value'], $corr['new_value']]);
                $db->commit();
                $success = 'Correction confirmed and officially reflected.';
            } catch (PDOException $e) {
                $db->rollBack();
                $error = 'Could not confirm correction: ' . $e->getMessage();
            }
        }
    }
}

// 4. PREVIEW IMPORT (Parse Excel & Auto-Calculate School Years)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview_import') {
    if (!checkCsrf()) {
        $error = 'Session expired.';
    } elseif (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        if ($xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name'])) {
            $pefIndex = -1; $ccIndex = -1;
            foreach ($xlsx->sheetNames() as $idx => $name) {
                if (trim(strtoupper($name)) === 'PEF') $pefIndex = $idx;
                if (trim(strtoupper($name)) === 'CC') $ccIndex = $idx;
            }

            if ($pefIndex === -1 && $ccIndex === -1) {
                $error = "Upload Rejected: Could not locate a valid 'CC' sheet. Format unrecognized.";
            } else {
                $requiresResolution = false;
                $studentNo = '';
                $rawName = '';
                
                $enrolledSubjects = [];
                $currentSy = '';
                $currentSem = 1;
                $currentSection = '';

                // Step A: Determine Data Strategy & Extract PEF Active Enrollments
                if ($pefIndex !== -1) {
                    $pefRows = $xlsx->rows($pefIndex);
                    $rawName   = isset($pefRows[9][0]) ? trim($pefRows[9][0]) : '';
                    $studentNo = isset($pefRows[11][0]) ? trim($pefRows[11][0]) : '';
                    
                    if (!$studentNo || !$rawName) {
                        $error = "Upload Rejected: Could not locate Student Anchor Data in PEF sheet.";
                    } else {
                        // Extract "SUBJECT(S) TO BE ENROLLED"
                        $enrollStartIndex = -1;
                        foreach ($pefRows as $idx => $row) {
                            if (isset($row[0]) && strpos(strtoupper(trim((string)$row[0])), 'SUBJECT(S) TO BE ENROLLED') !== false) {
                                $enrollStartIndex = $idx;
                                break;
                            }
                        }
                        
                        if ($enrollStartIndex !== -1) {
                            $semStr = isset($pefRows[$enrollStartIndex + 1][0]) ? strtolower(trim((string)$pefRows[$enrollStartIndex + 1][0])) : '';
                            $currentSem = (strpos($semStr, 'second') !== false) ? 2 : 1;
                            $currentSy = isset($pefRows[$enrollStartIndex + 1][2]) ? trim((string)$pefRows[$enrollStartIndex + 1][2]) : '';
                            $currentSection = isset($pefRows[$enrollStartIndex + 3][0]) ? trim((string)$pefRows[$enrollStartIndex + 3][0]) : '';

                            $rowIdx = $enrollStartIndex + 6;
                            while (isset($pefRows[$rowIdx][0]) && trim((string)$pefRows[$rowIdx][0]) !== '') {
                                $rawCode  = trim((string)$pefRows[$rowIdx][0]);
                                $rawDesc  = isset($pefRows[$rowIdx][1]) ? trim((string)$pefRows[$rowIdx][1]) : '';
                                $cleanTitle = cleanSubjectTitle($rawDesc);
                                $altCode   = extractTrackCode($rawDesc);

                                $enrolledSubjects[] = [
                                    'code'    => $rawCode,
                                    'altCode' => $altCode,
                                    'title'   => $cleanTitle ?: $rawDesc
                                ];
                                $rowIdx++;
                            }
                        }
                    }
                } else {
                    // SECT 4: MISSING ID RESOLUTION TRIGGER (PEF missing)
                    $requiresResolution = true;
                    $ccRows = $xlsx->rows($ccIndex);
                    $rawName = isset($ccRows[0][1]) ? trim((string)$ccRows[0][1]) : '';
                    
                    if (!$rawName) {
                        $error = "Upload Rejected: PEF sheet missing and no Name anchor found in CC!B1.";
                    }
                }

                if (!$error) {
                    $nameParts = explode(',', $rawName);
                    $lastName = trim($nameParts[0]);
                    $firstAndMiddle = isset($nameParts[1]) ? trim($nameParts[1]) : '';
                    $firstName = $firstAndMiddle; $middleName = null;
                    if (preg_match('/^(.*?)\s+([A-Za-z]\.?)$/i', $firstAndMiddle, $m)) {
                        $firstName = trim($m[1]); $middleName = trim($m[2]);
                    }

                    // Step B: Loop CC sheet and calculate chronological states
                    $ccRows = $xlsx->rows($ccIndex);
                    $extractedGrades = [];
                    $currentYearLevel = 1;
                    $maxYearLevel = 1;

                    foreach ($ccRows as $row) {
                        $cellA = isset($row[0]) ? trim(strtoupper((string)$row[0])) : '';
                        $cellI = isset($row[8]) ? trim(strtoupper((string)$row[8])) : '';
                        
                        if (strpos($cellA, 'FIRST YEAR') !== false || strpos($cellI, 'FIRST YEAR') !== false) $currentYearLevel = 1;
                        elseif (strpos($cellA, 'SECOND YEAR') !== false || strpos($cellI, 'SECOND YEAR') !== false) $currentYearLevel = 2;
                        elseif (strpos($cellA, 'THIRD YEAR') !== false || strpos($cellI, 'THIRD YEAR') !== false) $currentYearLevel = 3;
                        elseif (strpos($cellA, 'FOURTH YEAR') !== false || strpos($cellI, 'FOURTH YEAR') !== false) $currentYearLevel = 4;
                        
                        if ($currentYearLevel > $maxYearLevel) $maxYearLevel = $currentYearLevel;

                        $code1  = isset($row[0]) ? trim((string)$row[0]) : '';
                        $desc1  = isset($row[1]) ? trim((string)$row[1]) : '';
                        $grade1 = isset($row[4]) ? trim((string)$row[4]) : '';

                        $code2  = isset($row[8]) ? trim((string)$row[8]) : '';
                        $desc2  = isset($row[9]) ? trim((string)$row[9]) : '';
                        $grade2 = isset($row[12]) ? trim((string)$row[12]) : '';

                        if ($code1 && isValidGrade($grade1)) {
                            $cleanTitle1 = cleanSubjectTitle($desc1);
                            $altCode1    = extractTrackCode($desc1);
                            $extractedGrades[] = [
                                'code'       => $code1,
                                'altCode'    => $altCode1,
                                'title'      => $cleanTitle1 ?: $desc1,
                                'grade'      => $grade1,
                                'year_level' => $currentYearLevel,
                                'semester'   => 1
                            ];
                        }
                        if ($code2 && isValidGrade($grade2)) {
                            $cleanTitle2 = cleanSubjectTitle($desc2);
                            $altCode2    = extractTrackCode($desc2);
                            $extractedGrades[] = [
                                'code'       => $code2,
                                'altCode'    => $altCode2,
                                'title'      => $cleanTitle2 ?: $desc2,
                                'grade'      => $grade2,
                                'year_level' => $currentYearLevel,
                                'semester'   => 2
                            ];
                        }
                    }

                    // Sort grades descending (Highest Year & Sem first)
                    usort($extractedGrades, function($a, $b) {
                        if ($a['year_level'] === $b['year_level']) {
                            return $b['semester'] <=> $a['semester'];
                        }
                        return $b['year_level'] <=> $a['year_level'];
                    });

                    // Step C: Route to Resolution Modal or Preview Modal
                    if ($requiresResolution) {
                        $stmt = $db->prepare("SELECT u.id, sp.student_number, u.first_name, u.last_name FROM student_profiles sp JOIN users u ON sp.user_id = u.id WHERE u.last_name LIKE ?");
                        $stmt->execute([$lastName . '%']);
                        $matches = $stmt->fetchAll();

                        $resolutionPayload = [
                            'rawName' => $rawName,
                            'firstName' => $firstName,
                            'middleName' => $middleName,
                            'lastName' => $lastName,
                            'grades' => $extractedGrades,
                            'maxYearLevel' => $maxYearLevel,
                            'enrolled_subjects' => $enrolledSubjects,
                            'current_sy' => $currentSy,
                            'current_sem' => $currentSem,
                            'current_section' => $currentSection
                        ];
                    } else {
                        $previewPayload = [
                            'studentNo'  => $studentNo,
                            'firstName'  => $firstName,
                            'middleName' => $middleName,
                            'lastName'   => $lastName,
                            'grades'     => $extractedGrades,
                            'enrolled_subjects' => $enrolledSubjects,
                            'current_sy' => $currentSy,
                            'current_sem' => $currentSem,
                            'current_section' => $currentSection,
                            'maxYearLevel' => $maxYearLevel
                        ];
                    }
                }
            }
        } else {
            $error = "Error parsing Excel file: " . SimpleXLSX::parseError();
        }
    }
}

// 4.5 RESOLVE IMPORT (Bridge between Missing ID and Preview)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_import') {
    if (!checkCsrf()) {
        $error = 'Session expired.';
    } else {
        $resPayload = json_decode($_POST['resolution_payload'], true);
        $studentNo = trim($_POST['resolved_student_no'] ?? '');
        if (!$studentNo) {
            $studentNo = trim($_POST['resolved_student_no_manual'] ?? '');
        }

        if (!$studentNo) {
            $error = "Student Number is required to resolve the import.";
        } else {
            $confirmedYearLevel = (int) ($_POST['confirmed_year_level'] ?? $resPayload['maxYearLevel'] ?? 0);
            $confirmedSemester  = (int) ($_POST['confirmed_semester'] ?? 1);

            $previewPayload = [
                'studentNo'  => $studentNo,
                'firstName'  => $resPayload['firstName'],
                'middleName' => $resPayload['middleName'],
                'lastName'   => $resPayload['lastName'],
                'grades'     => $resPayload['grades'], // Already sorted from step 4
                'maxYearLevel' => $confirmedYearLevel ?: $resPayload['maxYearLevel'],
                'enrolled_subjects' => $resPayload['enrolled_subjects'] ?? [],
                'current_sy' => $resPayload['current_sy'] ?? '',
                'current_sem' => $confirmedSemester,
                'current_section' => $resPayload['current_section'] ?? ''
            ];
        }
    }
}

// 5. CONFIRM IMPORT (DB Upsert with Dual-Code Elective Resolution)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_import') {
    if (!checkCsrf()) {
        $error = 'Session expired.';
    } else {
        $payload = json_decode($_POST['import_payload'], true);
        if (!$payload) {
            $error = "Error: Payload corrupted.";
        } else {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("SELECT id FROM users WHERE user_id = ?");
                $stmt->execute([$payload['studentNo']]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $uid = $existing['id'];
                    $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=? WHERE id=?")->execute([$payload['firstName'], $payload['middleName'], $payload['lastName'], $uid]);
                    $db->prepare("UPDATE student_profiles SET course=? WHERE user_id=?")->execute([LOCKED_COURSE, $uid]);
                } else {
                    $db->prepare("INSERT INTO users (user_id, password_hash, role, first_name, middle_name, last_name) VALUES (?, ?, 'student', ?, ?, ?)")
                       ->execute([$payload['studentNo'], password_hash(LOCKED_PASSWORD, PASSWORD_DEFAULT), $payload['firstName'], $payload['middleName'], $payload['lastName']]);
                    $uid = $db->lastInsertId();
                    
                    $db->prepare("INSERT INTO student_profiles (user_id, student_number, course, year_level, status) VALUES (?, ?, ?, ?, 'Regular')")
                       ->execute([$uid, $payload['studentNo'], LOCKED_COURSE, $payload['maxYearLevel']]);
                }

                // Query that finds subject by Primary Code or Alt Track Code
                $stmtFindSubj = $db->prepare("SELECT id FROM subjects WHERE code = ? OR (code IS NOT NULL AND code = ?) LIMIT 1");
                
                $stmtCheckGrade = $db->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND is_current = 0");
                $stmtUpdateGrade = $db->prepare("UPDATE grades SET final_grade = ?, risk_level = ?, school_year = ?, semester = ?, encoded_by = ? WHERE id = ?");
                $stmtInsertGrade = $db->prepare("INSERT INTO grades (student_id, subject_id, final_grade, school_year, semester, is_current, risk_level, encoded_by) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");

                $stmtCheckCurrentGrade  = $db->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND is_current = 1");
                $stmtUpdateCurrentGrade = $db->prepare("UPDATE grades SET final_grade = ?, risk_level = ?, encoded_by = ? WHERE id = ?");
                $stmtInsertCurrentGrade = $db->prepare("INSERT INTO grades (student_id, subject_id, final_grade, school_year, semester, is_current, risk_level, encoded_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");

                $baseYear = 2000 + (int)substr($payload['studentNo'], 0, 2);

                if (!empty($payload['maxYearLevel']) && !empty($payload['current_sem'])) {
                    $confirmedYearLevel = (int) $payload['maxYearLevel'];
                    $confirmedSemester  = (int) $payload['current_sem'];
                    $confirmedSyStart   = $baseYear + ($confirmedYearLevel - 1);
                    $confirmedSy        = !empty($payload['current_sy']) ? $payload['current_sy'] : ($confirmedSyStart . '-' . ($confirmedSyStart + 1));

                    $db->prepare("UPDATE grades SET is_current = 0 WHERE student_id = ? AND is_current = 1 AND (school_year != ? OR semester != ?)")
                       ->execute([$uid, $confirmedSy, $confirmedSemester]);
                }

                foreach ($payload['grades'] as $item) {
                    $schoolYearStart = $baseYear + ($item['year_level'] - 1);
                    $schoolYearString = $schoolYearStart . '-' . ($schoolYearStart + 1);

                    $altCode = $item['altCode'] ?? null;
                    $stmtFindSubj->execute([$item['code'], $altCode]);
                    if ($subj = $stmtFindSubj->fetch()) {
                        $isNum = is_numeric($item['grade']);
                        $finalGrade = $isNum ? (float)$item['grade'] : null;
                        
                        $riskLevel = ($isNum && function_exists('computeRiskFromAvg')) ? computeRiskFromAvg($finalGrade) : 'LOW';

                        $stmtCheckCurrentGrade->execute([$uid, $subj['id']]);
                        if ($currentGrade = $stmtCheckCurrentGrade->fetch()) {
                            $stmtUpdateCurrentGrade->execute([$finalGrade, $riskLevel, $user['id'], $currentGrade['id']]);
                            continue;
                        }

                        $isCurrentTerm = !empty($payload['current_sem']) && !empty($payload['maxYearLevel'])
                            && (int) $item['year_level'] === (int) $payload['maxYearLevel']
                            && (int) $item['semester'] === (int) $payload['current_sem'];

                        if ($isCurrentTerm) {
                            $currentSyToStore = !empty($payload['current_sy']) ? $payload['current_sy'] : $schoolYearString;
                            $stmtInsertCurrentGrade->execute([$uid, $subj['id'], $finalGrade, $currentSyToStore, $item['semester'], $riskLevel, $user['id']]);
                            continue;
                        }

                        $stmtCheckGrade->execute([$uid, $subj['id']]);
                        if ($existingGrade = $stmtCheckGrade->fetch()) {
                            $stmtUpdateGrade->execute([$finalGrade, $riskLevel, $schoolYearString, $item['semester'], $user['id'], $existingGrade['id']]);
                        } else {
                            $stmtInsertGrade->execute([$uid, $subj['id'], $finalGrade, $schoolYearString, $item['semester'], $riskLevel, $user['id']]);
                        }
                    }
                }
                
                // -------------------------------------------------------------
                // CURRENT ENROLLED SUBJECTS UPSERT (is_current = 1)
                // -------------------------------------------------------------
                if (!empty($payload['enrolled_subjects'])) {
                    if (!empty($payload['current_sy']) && !empty($payload['current_sem'])) {
                        $db->prepare("DELETE FROM grades WHERE student_id = ? AND is_current = 1 AND (school_year != ? OR semester != ?)")
                           ->execute([$uid, $payload['current_sy'], $payload['current_sem']]);
                    }

                    $stmtCheckCurrent = $db->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND is_current = 1");
                    $stmtInsertCurrent = $db->prepare("INSERT INTO grades (student_id, subject_id, school_year, semester, is_current, risk_level, encoded_by) VALUES (?, ?, ?, ?, 1, 'LOW', ?)");

                    foreach ($payload['enrolled_subjects'] as $subj) {
                        $altCode = $subj['altCode'] ?? null;
                        $stmtFindSubj->execute([$subj['code'], $altCode]);
                        if ($dbSubj = $stmtFindSubj->fetch()) {
                            $stmtCheckCurrent->execute([$uid, $dbSubj['id']]);
                            if (!$stmtCheckCurrent->fetch()) {
                                $stmtInsertCurrent->execute([$uid, $dbSubj['id'], $payload['current_sy'], $payload['current_sem'], $user['id']]);
                            }
                        }
                    }
                    
                    if (!empty($payload['current_section'])) {
                        $sec = trim($payload['current_section']);
                        if (preg_match('/^IT\s*(\d+)$/i', $sec, $m)) {
                            $sec = 'IT-' . $m[1]; // Normalizes "IT43" to "IT-43"
                        }
                        $db->prepare("UPDATE student_profiles SET section=? WHERE user_id=?")->execute([$sec, $uid]);
                    }
                }

                $db->commit();
                $success = "Successfully synced historical data for " . formatNameLastFirst($payload['firstName'], $payload['middleName'], $payload['lastName']) . ".";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// ---------------------------------------------------------
// DATA FETCHING FOR UI
// ---------------------------------------------------------
$students = $db->query("SELECT sp.user_id, sp.student_number, sp.section, sp.year_level, sp.status, sp.current_gwa, u.first_name, u.middle_name, u.last_name, u.email FROM student_profiles sp JOIN users u ON u.id = sp.user_id ORDER BY sp.section, u.last_name, u.first_name")->fetchAll();

// Fetch Title instead of Code for Student Profile Modal
$gradeStmt = $db->query("SELECT g.student_id, s.code, s.title, g.prelim, g.final_grade, g.risk_level FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.is_current = 1 ORDER BY g.student_id, s.code");
$gradesByStudent = [];
foreach ($gradeStmt->fetchAll() as $g) {
    $gradesByStudent[$g['student_id']][] = [
        'code'       => $g['code'],
        'title'      => cleanSubjectTitle($g['title']),
        'prelim'     => $g['prelim'] !== null ? (float) $g['prelim'] : null,
        'finalGrade' => $g['final_grade'] !== null ? (float) $g['final_grade'] : null,
        'risk'       => $g['risk_level']
    ];
}

$predStmt = $db->query("SELECT p.student_id, p.predicted_gwa, p.risk_level, p.latin_honor FROM predictions p JOIN (SELECT student_id, MAX(generated_at) mx FROM predictions GROUP BY student_id) latest ON latest.student_id = p.student_id AND latest.mx = p.generated_at");
$predByStudent = [];
foreach ($predStmt->fetchAll() as $p) {
    $predByStudent[$p['student_id']] = $p;
}

$histStmt = $db->query("
    SELECT g.student_id, g.school_year, g.semester, s.code, s.title, g.final_grade
    FROM grades g
    JOIN subjects s ON s.id = g.subject_id
    WHERE g.is_current = 0 AND g.final_grade IS NOT NULL
    ORDER BY g.student_id, g.school_year ASC, g.semester ASC, s.code
");
$tempHistory = [];
foreach ($histStmt->fetchAll() as $h) {
    $sid = $h['student_id'];
    $sy = $h['school_year'] ?? 'Unknown Year';
    $sem = $h['semester'];
    $tempHistory[$sid][$sy][$sem][] = [
        'code'  => $h['code'],
        'title' => cleanSubjectTitle($h['title']),
        'grade' => (float) $h['final_grade']
    ];
}

$historyByStudent = [];
foreach ($tempHistory as $sid => $years) {
    $yearLevel = 1;
    foreach ($years as $sy => $sems) {
        $ordinalYear = ($yearLevel === 1) ? "1st" : (($yearLevel === 2) ? "2nd" : (($yearLevel === 3) ? "3rd" : "4th"));
        foreach ($sems as $sem => $subjects) {
            $ordinalSem = ($sem == 1) ? "1st Sem" : ($sem == 2 ? "2nd Sem" : "Unknown Sem");
            $termKey = "$sy | {$ordinalYear} Year, $ordinalSem";
            $historyByStudent[$sid][$termKey] = $subjects;
        }
        $yearLevel++;
    }
    $historyByStudent[$sid] = array_reverse($historyByStudent[$sid], true);
}

$modalData = [];
foreach ($students as $s) {
    $uid = $s['user_id'];
    $modalData[$uid] = [
        'name'       => formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']),
        'firstName'  => $s['first_name'],
        'middleName' => $s['middle_name'],
        'lastName'   => $s['last_name'],
        'studentNo'  => $s['student_number'],
        'email'      => $s['email'],
        'section'    => $s['section'],
        'yearLevel'  => $s['year_level'],
        'status'     => $s['status'],
        'currentGwa' => $s['current_gwa'] !== null ? (float) $s['current_gwa'] : null,
        'predicted'  => $predByStudent[$uid]['predicted_gwa'] ?? null,
        'risk'       => $predByStudent[$uid]['risk_level'] ?? null,
        'grades'     => $gradesByStudent[$uid] ?? [],
        'history'    => $historyByStudent[$uid] ?? [],
    ];
}

$pending = $db->query("
    SELECT pc.*, u.first_name, u.middle_name, u.last_name, sp.student_number,
           au.first_name AS a_first, au.middle_name AS a_middle, au.last_name AS a_last
    FROM pending_corrections pc
    JOIN users u ON u.id = pc.target_id
    JOIN student_profiles sp ON sp.user_id = pc.target_id
    JOIN users au ON au.id = pc.proposed_by
    WHERE pc.status = 'pending' AND pc.target_type IN ('student_section','student_year_level')
    ORDER BY pc.proposed_at DESC
")->fetchAll();

$pageTitle = 'Students';
$navItems = [
    ['Dashboard',          'index.php',     '🏠'],
    ['Students',           'students.php',  '👥'],
    ['Faculty',            'faculty.php',   '👨‍🏫'],
    ['Grades',             'grades.php',    '📝'],
    ['Program Analytics',  'analytics.php', '📊'],
    ['Activity & Inbox',   'activity.php',  '💬'],
    ['Settings',           'settings.php',  '⚙️'],
];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<style>
.locked-field { background: var(--bg-color) !important; color: var(--text-gray) !important; border-color: var(--border-color) !important; cursor: not-allowed; }
.table-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.search-box { padding: 9px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 0.9rem; width: 280px; max-width: 100%; background-color: var(--bg-color); color: var(--text-dark); }
.search-box:focus { border-color: var(--accent-blue); outline: none; background-color: var(--card-bg); }
.pagination-bar { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
.page-btn { padding: 6px 12px; border: 1px solid var(--border-color); background: var(--card-bg); border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); font-family: inherit; transition: all 0.2s; }
.page-btn:hover { background: var(--bg-color); }
.page-btn.active { background: var(--accent-blue); color: white; border-color: var(--accent-blue); }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.row-clickable { cursor: pointer; }
.row-clickable:hover { background: var(--bg-color); }
.row-clickable td:first-child + td { color: var(--accent-blue); font-weight: 600; }
.modal-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0; }
.modal-stat { background: var(--bg-color); border-radius: 8px; padding: 10px 12px; text-align: center; border: 1px solid var(--border-color); }
.modal-stat span { display: block; font-size: 0.72rem; color: var(--text-gray); margin-bottom: 4px; }
.modal-stat strong { font-size: 1.15rem; color: var(--text-dark); }

/* Updated Modal Centering, Width, & Smooth Animations */
.modal-overlay { 
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0, 0, 0, 0.5); z-index: 1000; 
    display: flex; align-items: flex-start; justify-content: center; 
    overflow-y: auto; padding: 40px 20px; 
    opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; 
}
.modal-overlay.open { opacity: 1; visibility: visible; }

.modal-box { 
    background: var(--card-bg); padding: 24px; border-radius: 12px; width: 100%; 
    box-shadow: 0 10px 25px rgba(0,0,0,0.1); position: relative; margin: auto; 
    transform: scale(0.95) translateY(15px); transition: transform 0.3s ease; 
}
.modal-overlay.open .modal-box { transform: scale(1) translateY(0); }

.modal-close { position: absolute; top: 20px; right: 20px; background: none; border: 1px solid var(--border-color); color: var(--text-dark); cursor: pointer; font-size: 0.85rem; padding: 6px 12px; border-radius: 6px; font-weight: 600; transition: background 0.2s; }
.modal-close:hover { background: var(--bg-color); }

/* Drag & Drop UI */
.drop-zone { border: 2px dashed var(--border-color); border-radius: 12px; padding: 40px 20px; text-align: center; background-color: var(--bg-color); cursor: pointer; transition: all 0.3s ease; position: relative; }
.drop-zone:hover, .drop-zone.dragover { border-color: var(--accent-blue); background-color: rgba(108, 142, 239, 0.05); }
.upload-icon { width: 48px; height: 48px; color: var(--text-gray); margin-bottom: 16px; transition: color 0.3s ease; }
.drop-zone:hover .upload-icon, .drop-zone.dragover .upload-icon { color: var(--accent-blue); }
.file-preview { display: none; align-items: center; background-color: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 16px; margin-top: 16px; }
.file-preview.active { display: flex; }
.btn-primary { background-color: var(--accent-blue); color: #FFFFFF; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: opacity 0.2s; }
.btn-primary:disabled { background-color: var(--text-gray); opacity: 0.5; cursor: not-allowed; }
.btn-primary:not(:disabled):hover { opacity: 0.9; }
</style>

<div class="main-content">
    <div class="header">
        <div><h1>Students</h1><p style="color: var(--text-gray);">Add or remove student accounts, and view individual profiles.</p></div>
    </div>

    <?php if ($error): ?>
        <p style="background:rgba(220, 38, 38, 0.1); color:var(--risk-high); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-high);"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p style="background:rgba(5, 150, 105, 0.1); color:var(--risk-low); padding:12px; border-radius:6px; margin-bottom:16px; border-left:4px solid var(--risk-low);"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <div class="card upload-card">
        <div class="table-title">Import Student Data</div>
        <p style="color: var(--text-gray); font-size: 0.9rem; margin-bottom: 24px;">Upload the official Curriculum Checklist Excel file (.xlsx) to automatically register the student and sync their complete academic history.</p>

        <form id="uploadForm" action="students.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="preview_import">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="drop-zone" id="dropZone">
                <input type="file" name="excel_file" id="fileInput" accept=".xlsx, .xls" hidden required>
                <div class="drop-zone-content">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="upload-icon"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px;">Drag & drop the Excel file here</h3>
                    <p style="font-size: 0.9rem; color: var(--text-gray);">or <span style="color: var(--accent-blue); font-weight: 600; text-decoration: underline;">browse your computer</span></p>
                </div>
            </div>

            <div id="filePreview" class="file-preview">
                <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent-blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:24px; height:24px; margin-right:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                <span id="fileName" style="flex-grow:1; font-size:0.95rem; color:var(--text-dark);">No file selected</span>
                <button type="button" id="removeFile" style="background:none; border:none; color:var(--risk-high); cursor:pointer;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            </div>
            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-primary" id="uploadBtn" disabled>Process Import</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-toolbar">
            <div class="table-title" style="margin:0;">All Students</div>
            <input type="text" id="student-search" class="search-box" placeholder="Search by name, student no., or section…">
        </div>
        <table id="students-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Student No.</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Name</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Section</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Year</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">GWA</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-dark);">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="students-tbody">
                <?php foreach ($students as $s):
                    $uid = $s['user_id'];
                    $searchBlob = strtolower($s['student_number'] . ' ' . formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name']) . ' ' . ($s['section'] ?? ''));
                ?>
                <tr class="row-clickable" data-search="<?= htmlspecialchars($searchBlob) ?>" onclick="openStudentModal(<?= $uid ?>)" style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['student_number']) ?></td>
                    <td style="padding: 12px; font-weight:600; color:var(--accent-blue);"><?= htmlspecialchars(formatNameLastFirst($s['first_name'], $s['middle_name'], $s['last_name'])) ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['section'] ?? '—') ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['year_level'] ?? '—') ?></td>
                    <td style="padding: 12px; color: var(--text-dark); font-weight: 600;"><?= $s['current_gwa'] !== null ? number_format($s['current_gwa'], 2) : '—' ?></td>
                    <td style="padding: 12px; color: var(--text-dark);"><?= htmlspecialchars($s['status'] ?? 'Regular') ?></td>
                    <td onclick="event.stopPropagation();" style="padding: 12px; text-align: right; white-space: nowrap;">
                        <button type="button" onclick="openEditModal(<?= $uid ?>)" style="background:none; border:none; color:var(--accent-blue); font-weight:600; cursor:pointer; margin-right: 12px;">Edit</button>
                        <form method="POST" action="students.php" onsubmit="return confirm('Remove this student account and ALL their history? This cannot be undone.');" style="display:inline; margin:0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_id" value="<?= $uid ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <button type="submit" style="background:none; border:none; color:var(--risk-high); font-weight:600; cursor:pointer;">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination-bar" id="pagination-bar"></div>
    </div>
</div>

<!-- MISSING ID RESOLUTION MODAL -->
<?php if ($resolutionPayload): ?>
<div class="modal-overlay open">
    <div class="modal-box" style="max-width: 500px;">
        <h2 style="color:var(--text-dark); margin-bottom:4px;">⚠️ Incomplete File Detected</h2>
        <p style="color:var(--text-gray); font-size:0.88rem; margin-bottom:16px;">We found grades for <strong><?= htmlspecialchars($resolutionPayload['rawName']) ?></strong>, but the Student ID is missing from the file.</p>
        
        <form method="POST" action="students.php">
            <input type="hidden" name="action" value="resolve_import">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="resolution_payload" value="<?= htmlspecialchars(json_encode($resolutionPayload)) ?>">
            
            <?php if (!empty($matches)): ?>
                <h4 style="margin-bottom:8px;">Is this one of these students?</h4>
                <div style="background:var(--bg-color); border:1px solid var(--border-color); border-radius:8px; padding:12px; margin-bottom:16px;">
                    <?php foreach ($matches as $match): ?>
                        <label style="display:block; margin-bottom:8px; cursor:pointer;">
                            <input type="radio" name="resolved_student_no" value="<?= htmlspecialchars($match['student_number']) ?>" required>
                            <strong style="color:var(--text-dark);"><?= htmlspecialchars($match['last_name'] . ', ' . $match['first_name']) ?></strong> (<?= htmlspecialchars($match['student_number']) ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h4 style="margin-bottom:8px;"><?= empty($matches) ? 'Create New Profile' : 'Or enter a new Student No:' ?></h4>
            <input type="text" name="resolved_student_no_manual" placeholder="Enter Student No (e.g. 23-22-123)" class="form-input" style="width:100%; margin-bottom:20px;" <?= empty($matches) ? 'required' : '' ?> oninput="if(this.value) { document.querySelectorAll('input[type=radio]').forEach(r => r.required = false); } else { document.querySelectorAll('input[type=radio]').forEach(r => r.required = true); }">

            <h4 style="margin-bottom:4px;">Confirm Current Term</h4>
            <p style="color:var(--text-gray); font-size:0.8rem; margin-bottom:8px;">This file has no PEF sheet, so the current term can't be read from it automatically. Please confirm which term is the student's <em>ongoing</em> one — grades for it will update the current-semester record instead of being filed as history.</p>
            <div style="display:flex; gap:12px; margin-bottom:20px;">
                <select name="confirmed_year_level" class="form-input" style="flex:1;" required>
                    <?php for ($yr = 1; $yr <= 4; $yr++): ?>
                    <option value="<?= $yr ?>" <?= $yr === (int) $resolutionPayload['maxYearLevel'] ? 'selected' : '' ?>>Year <?= $yr ?></option>
                    <?php endfor; ?>
                </select>
                <select name="confirmed_semester" class="form-input" style="flex:1;" required>
                    <option value="1" <?= (int) $resolutionPayload['current_sem'] === 1 ? 'selected' : '' ?>>1st Semester</option>
                    <option value="2" <?= (int) $resolutionPayload['current_sem'] === 2 ? 'selected' : '' ?>>2nd Semester</option>
                </select>
            </div>

            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <a href="students.php" style="padding:10px 16px; background:var(--bg-color); color:var(--text-dark); text-decoration:none; border:1px solid var(--border-color); border-radius:8px; font-weight:600;">Cancel</a>
                <button type="submit" class="btn-primary">Link & Continue</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- IMPORT PREVIEW MODAL -->
<?php if ($previewPayload): ?>
<div class="modal-overlay open" id="import-preview-modal">
    <div class="modal-box" style="max-width: 850px;">
        <button class="modal-close" onclick="document.getElementById('import-preview-modal').classList.remove('open');">✕ Cancel</button>
        <h2 style="color:var(--text-dark); margin-bottom:4px;">Review Import Data</h2>
        <p style="color:var(--text-gray); font-size:0.88rem; margin-bottom:16px;">Verify the extracted Curriculum Checklist data below.</p>
        
        <div style="display:flex; justify-content:space-between; align-items:flex-end; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="color: var(--text-dark); margin:0 0 4px 0;"><?= htmlspecialchars($previewPayload['lastName'] . ', ' . $previewPayload['firstName'] . ' ' . $previewPayload['middleName']) ?></h3>
                <span style="color: var(--text-gray); font-weight:600;"><?= htmlspecialchars($previewPayload['studentNo']) ?> | <?= htmlspecialchars(LOCKED_COURSE) ?></span>
            </div>
            <div style="background: rgba(5, 150, 105, 0.1); color: var(--risk-low); padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                <?= count($previewPayload['grades']) ?> Grades | <?= count($previewPayload['enrolled_subjects'] ?? []) ?> Enrolled
            </div>
        </div>

        <div style="max-height: 350px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 20px; background: var(--card-bg);">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead style="position: sticky; top: 0; z-index: 10;">
                    <tr>
                        <th style="padding:12px 10px; text-align:left; color:var(--text-dark); background-color: var(--bg-color); border-bottom: 2px solid var(--border-color);">Subject Name</th>
                        <th style="padding:12px 10px; text-align:left; color:var(--text-dark); background-color: var(--bg-color); border-bottom: 2px solid var(--border-color);">Calculated Term</th>
                        <th style="padding:12px 10px; text-align:left; color:var(--text-dark); background-color: var(--bg-color); border-bottom: 2px solid var(--border-color);">Final Grade</th>
                        <th style="padding:12px 10px; text-align:left; color:var(--text-dark); background-color: var(--bg-color); border-bottom: 2px solid var(--border-color);">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Display Enrolled Subjects First -->
                    <?php if (!empty($previewPayload['enrolled_subjects'])): ?>
                        <tr><td colspan="4" style="background: rgba(108, 142, 239, 0.1); padding: 8px 10px; font-size:0.8rem; font-weight:700; color:var(--accent-blue); border-bottom: 1px solid var(--border-color);">CURRENTLY ENROLLED (<?= htmlspecialchars($previewPayload['current_sy'] . ' | Sem ' . $previewPayload['current_sem'] . ' | ' . $previewPayload['current_section']) ?>)</td></tr>
                        <?php foreach ($previewPayload['enrolled_subjects'] as $g): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); background: rgba(5, 150, 105, 0.03);">
                                <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                                <td style="padding: 10px; color: var(--text-gray); font-size: 0.85rem;"><?= htmlspecialchars($previewPayload['current_sy'] . ' | Sem ' . $previewPayload['current_sem']) ?></td>
                                <td style="padding: 10px; color: var(--accent-blue); font-weight:600;">Enrolled</td>
                                <td style="padding: 10px; color: var(--risk-low); font-weight:600;">Ready</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Display Historical Grades Below -->
                    <?php if (!empty($previewPayload['grades'])): ?>
                        <tr><td colspan="4" style="background: rgba(255,255,255,0.03); padding: 8px 10px; font-size:0.8rem; font-weight:700; color:var(--text-gray); border-bottom: 1px solid var(--border-color);">HISTORICAL GRADES</td></tr>
                        <?php 
                        $baseYear = 2000 + (int)substr($previewPayload['studentNo'], 0, 2);
                        foreach ($previewPayload['grades'] as $g): 
                            $schoolYearStart = $baseYear + ($g['year_level'] - 1);
                            $syDisplay = $schoolYearStart . '-' . ($schoolYearStart + 1);
                        ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 10px; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($g['title']) ?></td>
                                <td style="padding: 10px; color: var(--text-gray); font-size: 0.85rem;"><?= htmlspecialchars($syDisplay . ' | Sem ' . $g['semester']) ?></td>
                                <td style="padding: 10px; color: var(--text-dark); font-weight:600;"><?= htmlspecialchars($g['grade']) ?></td>
                                <td style="padding: 10px; color: var(--risk-low); font-weight:600;">Ready</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form method="POST" action="students.php" style="display: flex; gap: 12px; justify-content: flex-end;">
            <input type="hidden" name="action" value="confirm_import">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="import_payload" value="<?= htmlspecialchars(json_encode($previewPayload)) ?>">
            <button type="button" onclick="document.getElementById('import-preview-modal').classList.remove('open');" style="padding: 12px 24px; background: var(--bg-color); color: var(--text-dark); border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer;">Cancel</button>
            <button type="submit" class="btn-primary">Confirm & Save to Database</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- STUDENT PROFILE MODAL -->
<div class="modal-overlay" id="student-modal-overlay" onclick="if(event.target===this) closeStudentModal();">
    <div class="modal-box" style="max-width: 850px;">
        <button class="modal-close" onclick="closeStudentModal()">✕ Close</button>
        <h2 id="modal-name" style="color:var(--text-dark); margin-bottom:2px;"></h2>
        <p id="modal-subline" style="color:var(--text-gray); font-size:0.88rem; margin-bottom:12px;"></p>

        <div class="modal-stat-grid">
            <div class="modal-stat"><span>Current GWA</span><strong id="modal-gwa">—</strong></div>
            <div class="modal-stat"><span>Predicted GWA</span><strong id="modal-predicted">—</strong></div>
            <div class="modal-stat"><span>Risk Level</span><strong id="modal-risk">—</strong></div>
        </div>

        <h4 style="color:var(--text-dark); font-size:0.95rem; margin-bottom:8px;">Current Semester Grades</h4>
        <table style="width:100%; border-collapse: collapse;">
            <thead><tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);"><th style="padding: 8px; text-align: left; color: var(--text-dark);">Subject</th><th style="padding: 8px; text-align: left; color: var(--text-dark);">Prelim</th><th style="padding: 8px; text-align: left; color: var(--text-dark);">Final Grade</th><th style="padding: 8px; text-align: left; color: var(--text-dark);">Risk</th></tr></thead>
            <tbody id="modal-grades-body"></tbody>
        </table>

        <div style="margin-top:18px; border-top:1px solid var(--border-color); padding-top:14px;">
            <button type="button" id="modal-history-toggle" onclick="toggleHistory()" style="background:var(--bg-color); border:1px solid var(--border-color); color:var(--accent-blue); padding:8px 14px; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.85rem; transition:all 0.2s ease;">
                <span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s ease; margin-right:4px;">▶</span> View Grade History
            </button>
            <div id="modal-history-wrapper" class="history-wrapper" style="max-height: 0px; overflow: hidden; transition: max-height 0.4s ease;">
                <div id="modal-history-container" style="padding-top: 10px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT STUDENT MODAL -->
<div class="modal-overlay" id="edit-modal-overlay" onclick="if(event.target===this) closeEditModal();">
    <div class="modal-box" style="max-width: 500px;">
        <button class="modal-close" onclick="closeEditModal()">✕ Close</button>
        <h2 style="color:var(--text-dark); margin-bottom:4px;">Edit Student</h2>
        <p style="color:var(--text-gray); font-size:0.78rem; margin-bottom:16px;">Name and status save immediately. Grades are not editable here.</p>

        <form method="POST" action="students.php" id="edit-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" id="edit-id">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:18px;">
                <input type="text" name="edit_first_name" id="edit-first-name" placeholder="First Name" required class="form-input">
                <input type="text" name="edit_middle_name" id="edit-middle-name" placeholder="Middle Name" class="form-input">
                <input type="text" name="edit_last_name" id="edit-last-name" placeholder="Last Name" required class="form-input" style="grid-column:span 2;">
                <select name="edit_status" id="edit-status" class="form-input" style="grid-column:span 2;">
                    <option value="Regular">Regular</option>
                    <option value="Irregular">Irregular</option>
                </select>
            </div>

            <div style="background:rgba(217, 119, 6, 0.1); border:1px solid var(--risk-mod); border-radius:8px; padding:14px; margin-bottom:16px;">
                <p style="font-size:0.8rem; font-weight:700; color:var(--risk-mod); margin-bottom:2px;">Section &amp; Year Level</p>
                <p style="font-size:0.75rem; color:var(--risk-mod); margin-bottom:12px;">Changing these proposes a correction — it will not take effect until confirmed.</p>

                <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:10px;">
                    <input type="text" name="propose_section" id="propose-section" placeholder="Current section" class="form-input">
                    <select name="propose_year_level" id="propose-year-level" class="form-input">
                        <option value="">— No change —</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div id="propose-section-hint" style="font-size: 0.78rem; color: var(--risk-mod); display: none; margin-bottom: 10px;"></div>
                <input type="text" name="propose_reason" id="propose-reason" placeholder="Reason (required only if proposing a change)" class="form-input" style="width:100%;">
            </div>

            <button type="submit" style="width:100%; padding:10px; background:var(--accent-blue); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Save Changes</button>
        </form>
    </div>
</div>

<script>
const studentModalData = <?= json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function wireSectionYearGuard(sectionInputId, yearSelectId, hintId) {
    const sectionEl = document.getElementById(sectionInputId);
    const yearEl = document.getElementById(yearSelectId);
    const hintEl = document.getElementById(hintId);
    if (!sectionEl || !yearEl || !hintEl) return;

    function check() {
        const section = sectionEl.value.trim();
        const year = parseInt(yearEl.value, 10);
        const match = section.match(/^IT-(\d)\d*$/i);
        if (match && year && parseInt(match[1], 10) !== year) {
            hintEl.textContent = `⚠ "${section}" looks like a Year ${match[1]} section, but Year Level is set to ${year}.`;
            hintEl.style.display = 'block';
        } else {
            hintEl.style.display = 'none';
        }
    }
    sectionEl.addEventListener('input', check);
    yearEl.addEventListener('change', check);
}

wireSectionYearGuard('propose-section', 'propose-year-level', 'propose-section-hint');

// Pagination
const ROWS_PER_PAGE = 25;
let currentPage = 1;
function renderPage() {
    const term = document.getElementById('student-search')?.value.trim().toLowerCase() || '';
    const allRows = Array.from(document.querySelectorAll('#students-tbody tr'));
    const visible = allRows.filter(row => !term || row.dataset.search.includes(term));
    allRows.forEach(r => r.style.display = 'none');
    
    const totalPages = Math.max(1, Math.ceil(visible.length / ROWS_PER_PAGE));
    currentPage = Math.min(currentPage, totalPages);
    
    const start = (currentPage - 1) * ROWS_PER_PAGE;
    visible.slice(start, start + ROWS_PER_PAGE).forEach(r => r.style.display = '');

    const bar = document.getElementById('pagination-bar');
    if (!bar) return;
    
    let html = '';
    html += `<button class="page-btn" ${currentPage===1?'disabled':''} onclick="goToPage(${currentPage-1})">‹ Prev</button>`;
    
    // Smart page numbering for large datasets (matches index.php)
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button class="page-btn" onclick="goToPage(1)">1</button>`;
        if (startPage > 2) html += `<span style="color:var(--text-gray); margin: 0 4px;">...</span>`;
    }
    
    for (let p = startPage; p <= endPage; p++) {
        html += `<button class="page-btn ${p===currentPage?'active':''}" onclick="goToPage(${p})">${p}</button>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span style="color:var(--text-gray); margin: 0 4px;">...</span>`;
        html += `<button class="page-btn" onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }

    html += `<button class="page-btn" ${currentPage===totalPages?'disabled':''} onclick="goToPage(${currentPage+1})">Next ›</button>`;
    html += `<span style="color:var(--text-gray); font-size:0.8rem; margin-left:10px;">Showing ${visible.length} results</span>`;
    
    bar.innerHTML = html;
}
function goToPage(p) { currentPage = p; renderPage(); }
document.getElementById('student-search')?.addEventListener('input', () => { currentPage = 1; renderPage(); });
renderPage();

// Modals
let currentModalUid = null;
function openStudentModal(uid) {
    const d = studentModalData[uid];
    if (!d) return;
    currentModalUid = uid; 
    document.getElementById('modal-name').innerText = d.name;
    document.getElementById('modal-subline').innerText = `${d.studentNo} · ${d.section || '—'} · Year ${d.yearLevel || '—'} · ${d.status || 'Regular'}`;
    document.getElementById('modal-gwa').innerText = d.currentGwa !== null ? d.currentGwa.toFixed(2) : '—';
    document.getElementById('modal-predicted').innerText = d.predicted !== null ? parseFloat(d.predicted).toFixed(2) : 'N/A';
    
    // Convert nulls for display safely based on the array
    document.getElementById('modal-grades-body').innerHTML = d.grades.length ? d.grades.map(g => {
        let numericGrade = g.prelim !== null ? parseFloat(g.prelim).toFixed(2) : '—';
        let finalGradeDisplay = g.finalGrade !== null ? parseFloat(g.finalGrade).toFixed(2) : 'In Progress';
        return `<tr style="border-bottom: 1px solid var(--border-color);"><td style="color: var(--text-dark); padding: 8px;">${g.title}</td><td style="font-weight:600; color: var(--text-dark); padding: 8px;">${numericGrade}</td><td style="font-weight:600; color: var(--text-dark); padding: 8px;">${finalGradeDisplay}</td><td style="padding: 8px;"><span style="background:var(--risk-low); color:white; padding:4px 10px; border-radius:4px; font-size:0.72rem; font-weight:700;">${g.risk || '—'}</span></td></tr>`
    }).join('') : '<tr><td colspan="4" style="text-align:center; color:var(--text-gray); padding:16px;">No current grades.</td></tr>';
    
    // Explicitly reset max height to '0px'
    document.getElementById('modal-history-wrapper').style.maxHeight = '0px';
    document.getElementById('modal-history-container').innerHTML = '';
    document.getElementById('modal-history-toggle').innerHTML = '<span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s ease; margin-right:4px;">▶</span> View Grade History';
    document.getElementById('student-modal-overlay').classList.add('open');
}

function toggleHistory() {
    const wrapper = document.getElementById('modal-history-wrapper');
    const container = document.getElementById('modal-history-container');
    const btn = document.getElementById('modal-history-toggle');
    
    // Fix: Explicitly check against '0px' to ensure toggle works perfectly
    if (wrapper.style.maxHeight && wrapper.style.maxHeight !== '0px') {
        wrapper.style.maxHeight = '0px';
        btn.innerHTML = '<span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s ease; margin-right:4px;">▶</span> View Grade History';
    } else {
        if (!container.innerHTML) {
            const terms = Object.keys(studentModalData[currentModalUid].history || {});
            container.innerHTML = terms.length ? terms.map(term => `<div style="margin-bottom:14px;"><div style="font-weight:700; color:var(--text-dark); font-size:0.85rem; margin-bottom:6px;">${term}</div><table style="width:100%; border-collapse: collapse;"><thead><tr style="background: var(--table-header-bg); border-bottom: 1px solid var(--border-color);"><th style="text-align:left; padding: 8px; color:var(--text-dark);">Subject Name</th><th style="text-align:left; padding: 8px; color:var(--text-dark);">Final Grade</th></tr></thead><tbody>${studentModalData[currentModalUid].history[term].map(g => {
                let dispGrade = isNaN(g.grade) || g.grade === null ? (g.grade || '—') : parseFloat(g.grade).toFixed(2);
                return `<tr style="border-bottom: 1px solid var(--border-color);"><td style="color:var(--text-dark); padding: 8px;">${g.title}</td><td style="font-weight:600; color:var(--text-dark); padding: 8px;">${dispGrade}</td></tr>`
            }).join('')}</tbody></table></div>`).join('') : '<p style="color:var(--text-gray); font-size:0.85rem; text-align:center;">No historical grades on record.</p>';
        }
        wrapper.style.maxHeight = container.scrollHeight + "px";
        btn.innerHTML = '<span id="history-toggle-icon" style="display:inline-block; transition:transform 0.3s ease; margin-right:4px; transform: rotate(90deg);">▶</span> Hide Grade History';
    }
}
function closeStudentModal() { document.getElementById('student-modal-overlay').classList.remove('open'); }

function openEditModal(uid) {
    const d = studentModalData[uid];
    if (!d) return;
    document.getElementById('edit-id').value = uid;
    document.getElementById('edit-first-name').value = d.firstName || '';
    document.getElementById('edit-middle-name').value = d.middleName || '';
    document.getElementById('edit-last-name').value = d.lastName || '';
    document.getElementById('edit-status').value = d.status || 'Regular';
    document.getElementById('propose-section').value = d.section || '';
    document.getElementById('propose-year-level').value = '';
    document.getElementById('propose-reason').value = '';
    document.getElementById('edit-modal-overlay').classList.add('open');
}

function closeEditModal() { 
    document.getElementById('edit-modal-overlay').classList.remove('open'); 
}

// Drag & Drop
const dropZone = document.getElementById("dropZone"), fileInput = document.getElementById("fileInput"), filePreview = document.getElementById("filePreview"), fileNameDisplay = document.getElementById("fileName"), removeFileBtn = document.getElementById("removeFile"), uploadBtn = document.getElementById("uploadBtn");
if(dropZone && fileInput) {
    dropZone.addEventListener("click", () => fileInput.click());
    dropZone.addEventListener("dragover", e => { e.preventDefault(); dropZone.classList.add("dragover"); });
    dropZone.addEventListener("dragleave", () => dropZone.classList.remove("dragover"));
    dropZone.addEventListener("drop", e => { e.preventDefault(); dropZone.classList.remove("dragover"); if (e.dataTransfer.files.length > 0) { fileInput.files = e.dataTransfer.files; updateUI(); } });
    fileInput.addEventListener("change", updateUI);
    removeFileBtn.addEventListener("click", e => { e.stopPropagation(); fileInput.value = ""; updateUI(); });
    function updateUI() {
        if (fileInput.files.length > 0 && (fileInput.files[0].name.endsWith('.xlsx') || fileInput.files[0].name.endsWith('.xls'))) {
            fileNameDisplay.textContent = fileInput.files[0].name; filePreview.classList.add("active"); dropZone.style.display = "none"; uploadBtn.disabled = false;
        } else {
            if(fileInput.files.length > 0) alert("Please upload a valid Excel file (.xlsx or .xls)");
            fileNameDisplay.textContent = "No file selected"; filePreview.classList.remove("active"); dropZone.style.display = "block"; uploadBtn.disabled = true;
        }
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>