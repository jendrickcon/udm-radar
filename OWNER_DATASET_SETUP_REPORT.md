# Owner dataset setup and compatibility report

Date: 2026-10-29 (Asia/Manila). **Setup and compatibility gate: PASS.** The authorized local replacement is complete. No feature implementation was performed during this work.

Normal login URL: http://localhost/udm-radar/login.php

Normal application connection now selects **`udm_radar`**, containing the compatible owner-supplied dataset. Original synthetic account identifiers, names and password hashes are preserved; they were not changed to the earlier `24-00-NNN` demo namespace. Log out of existing sessions before signing in again to clear cached identity fields.

## Source and exclusions

Source: `C:/Users/Joshua/Downloads/udm_radar_full_synthetic_backup_sql.sql` (1,359,230 bytes).

Source SHA-256: `b799de553f034d63bae8d4263d846e061cceaa5ca2d51c2c74a4f827bc102119`

Imported the supplied file into `udm_radar_owner_dataset_review` before compatibility preparation. Its actual inventory is **19 tables: all 18 required application tables plus one legacy table**. The legacy table `_backup_student_profiles_course_wp6` contains 291 historical course snapshots and is excluded. The requested second legacy table does not exist in this supplied file: the older singular `export_audit_log` table is absent. Application PHP reference checks found no requirement for the legacy table. No table was invented or silently misclassified.

The owner explicitly approved excluding the 182 Student predictions with blank provenance after the initial staging failure. They have no support-case references and remain unchanged in the review database and original backup. Post-replacement query confirmed all 182 still exist in the review database.

Also excluded from the compatible import: one unreferenced Administrator-owned prediction and two rejected correction rows targeting nonexistent Student ID 0. Those originals also remain in the review database/source file. No Student profiles or grades were excluded.

## Schema reconciliation

- `export_audit_logs.format` is already `VARCHAR(20)` in this supplied source, so no ENUM conversion was necessary. The canonical schema retains this contract and all 3 export-log rows.
- `predictions.prediction_source` changes from the old `ENUM('heuristic','decision_tree')` storage to the canonical `VARCHAR(30)` with valid-source CHECK. The 182 blank sources are excluded rather than assigned fabricated provenance. Retained source values and generation metadata are unchanged.
- `grades.final_grade` changes from `DECIMAL(4,2)` storage to canonical `VARCHAR(10)` using exact two-decimal strings. All values fit the current allowed domain; missing values stay NULL. No grade was rescored, GWA recalculated or model retrained.
- All 291 courses are already valid Software Development values. All current columns exist in the source. Canonical schema supplies current column/table collations; source identifier comparisons were performed byte-for-byte to avoid legacy mixed-collation errors.
- Source has its payload JSON CHECK but lacks all ten current named CHECK constraints. The unchanged `database/udm_radar.sql` supplies them; all ten were verified after staging and final replacement. No constraint bypass is included in the compatibility data export.
- Used a private data-only INSERT export in ignored logs. The original owner-supplied source and earlier public `database/seeds/002_synthetic_full_dataset.sql` remain unchanged. The private export contains 168 INSERT statements, zero non-INSERT statements, and zero legacy-table statements.
- No Migration 011 or other migration script was executed; all current structure comes directly from the canonical bootstrap.

Full column/type/default/collation differences and exact automated checks are recorded in `OWNER_DATASET_VERIFICATION.json`.

Compatible data-only export SHA-256: `b912bb2fb176d045f7b2d7097dcde09bddda04312162705c8e0c7b724f094e1f`

## Exact source and final counts

| Table | Source review | Final udm_radar |
|---|---:|---:|
| `_backup_student_profiles_course_wp6` | 291 | Excluded legacy table |
| `academic_support_cases` | 11 | 11 |
| `admin_change_log` | 22 | 22 |
| `export_audit_logs` | 3 | 3 |
| `faculty_class_loads` | 10 | 10 |
| `feedback_messages` | 0 | 0 |
| `feedback_reports` | 16 | 16 |
| `feedback_status_history` | 11 | 11 |
| `grades` | 12,552 | 12,552 |
| `otp_tokens` | 0 | 0 |
| `pending_corrections` | 5 | 3 |
| `pending_grade_batches` | 4 | 4 |
| `predictions` | 2,541 | 2,358 |
| `student_profiles` | 291 | 291 |
| `subjects` | 58 | 58 |
| `support_actions` | 3 | 3 |
| `support_case_referrals` | 11 | 11 |
| `support_status_history` | 4 | 4 |
| `users` | 296 | 296 |

Final accounts: **291 Students, 4 Faculty, 1 Administrator** (296 total). Sections are free text, not a table: 10 distinct Student profile sections and 8 faculty-load sections. Final prediction total: **2,358** (2,541 source rows minus 182 owner-approved exclusions minus 1 non-Student prediction). All **12,552 grades** remain.

## Backup and recovery proof

Before replacement, backed up both the original `udm_radar` and currently active `udm_radar_large_seed_demo`. Both completed with stable schema/data fingerprints before and after the dump. Latest verified copies:

- `udm_radar`: `C:\xampp\htdocs\udm-radar\logs\owner-dataset-review/backups/udm_radar_before_owner_dataset_20261029_095809.sql` (44,528 bytes), SHA-256 `5f06214e1ca766c7fdc2b83f3f6023f7fe3f998685f26b1507db23d99e11e845`.
- `udm_radar_large_seed_demo`: `C:\xampp\htdocs\udm-radar\logs\owner-dataset-review/backups/udm_radar_large_seed_demo_before_owner_dataset_20261029_095809.sql` (1,140,892 bytes), SHA-256 `45a50324e4b31baf2418d5372a52b0c52c3d07ab62604a60f55e6f6dab3932ee`.

Restored the `udm_radar` backup into fresh `udm_radar_owner_rollback_check`; every table's schema and data fingerprint matched the original backup snapshot. Dropped that recovery-check database and verified its absence. Immediately before replacement, rechecked that `udm_radar` still matched the verified backup. Recovery was available if import failed; no rollback was needed.

## Replacement and verification results

1. Canonical schema and compatible data passed isolated import in `udm_radar_owner_dataset_ready`. Every exported field round-tripped unchanged, including all retained names, account identifiers and password hashes.
2. Replaced `udm_radar` application tables using unchanged `database/udm_radar.sql`, then imported the verified compatible data-only export.
3. Ran `database/verify/verify_schema.sql` on final `udm_radar`: table count PASS, all 18 tables PRESENT, all 3 critical-column checks PASS, all 6 verifier named checks VERIFIED. Separately verified all 10 manifest-required named CHECK constraints.
4. Final full-table schema/data fingerprints match verified staging exactly. All 32 declared foreign-key relationships have zero orphans. Ten additional retained-record identity/role/audit/correction/case-owner checks have zero violations; all 23 batch payload Student/feedback references verify.
5. Reconnected normal localhost to `udm_radar`; PHP syntax check passed, configuration query confirms 291 profiles in that exact database, and normal HTTP login page returns 200.
6. Seven Student GET routes render successfully under an isolated injected Student test session with PDO SQL sessions verified read-only: Home, Dashboard, Trend, Grades, Academic Review, Enrollment Planning and Feedback. Each returns render status 200 with no caught exception or SQL/fatal marker. Test sessions were destroyed. This is rendering evidence, not actual password authentication or full workflow testing.

The specifically requested original account `23-22-040` remains under its original supplied identity with 57 grade rows. Original password hashes are preserved byte-for-byte. **Actual old-password login was not tested**, because no confirmed plaintext credential was supplied; no password was guessed or reset. PDF generation and write workflows were not certified by these setup checks.

## Retained databases and delivery state

- `udm_radar`: now contains the verified compatible owner dataset and serves normal localhost.
- `udm_radar_owner_dataset_review`: retained with original source rows, including all 182 excluded Student predictions.
- `udm_radar_owner_dataset_ready`: retained with the verified canonical compatible staging dataset.
- `udm_radar_large_seed_demo`: previous large-seed test dataset retained unchanged; its separate test URL remains distinct.
- `udm_radar_owner_rollback_check`: removed after successful recovery rehearsal.

No commit, push, PR, merge, deployment or feature implementation was performed. Existing UI work was preserved. Full evidence is in `OWNER_DATASET_VERIFICATION.json`; private SQL exports, backups and raw diagnostics are retained in ignored `logs/owner-dataset-review/`.

## Subsequent owner-authorized profile correction — 29 October 2026

After the verified import, the user confirmed CCS and authorized filling the missing college field on mock account 23-22-016 only. Updated college NULL to CCS; all other profile fields and every other table fingerprint remained unchanged, including grades and password hashes. The original profile was backed up privately under ignored logs/owner-dataset-review/23-22-016-college-before.json. The retained review/source databases were not modified. This intentional post-import metadata correction supersedes exact staging/data parity for this one field; all original import/recovery checks remain historical evidence of that checkpoint. The account now passes the planning view-model checks in provisional mode, with a one-subject Fourth Year/Second Semester plan. PDF generation remains unverified and blocked by private assets.
