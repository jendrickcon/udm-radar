# UDM-RADAR Setup Report

## Latest database setup update — 2026-10-29

**Owner-dataset setup and compatibility gate: PASS.** Following explicit replacement authorization and approval to exclude 182 predictions with blank provenance, the supplied owner backup was reviewed, reconciled and imported into local `udm_radar`. Normal localhost now uses that database. Final totals: 18 canonical tables, 291 Student profiles, 12,552 grades and 2,358 valid Student predictions. Original supplied synthetic identities and password hashes are preserved. Both previous local datasets were backed up; recovery of the original `udm_radar` backup was rehearsed successfully. All 182 excluded Student predictions remain in the source review database. No Migration 011 or new feature implementation was performed in this setup task.

See [the current owner-dataset setup and compatibility report](OWNER_DATASET_SETUP_REPORT.md) and [verification evidence](OWNER_DATASET_VERIFICATION.json) for exact counts, other exclusions, backup checksums, schema checks and testing limits. The remaining sections below record the historical 2026-10-27 setup and are not the current database inventory.

Date: 2026-10-27 (Asia/Manila)

**Setup gate: PASS. Feature implementation has not started.**

## Scope and authority

All three supplied documents were read: BASIC_MEMBER_CLONE_AND_DATABASE_IMPORT_GUIDE.md, JOSHUA_STUDENT_PORTAL_MASTER_WORK_PACKAGE_V2.md, and prompt for codex to automate import.txt. Their future feature instructions were distinguished from the user's setup-only request. Repository AGENTS.md, CONTRIBUTING.md, database/README.md, SETUP.md, and relevant UI startup/ownership/stop guidance were inspected. Implementation-specific UI and analytics reading remains required before future feature work.

The user confirmed the approved main SHA and explicitly authorized private backup and replacement of the existing database. At setup completion, no commit, push, PR, merge, deployment, or feature implementation had been performed. The user subsequently authorized committing and pushing this report on the assigned branch for review.

## Repository

- Path: `C:\xampp\htdocs\udm-radar`
- Remote: `https://github.com/jendrickcon/udm-radar.git`
- Approved main, fetched origin/main, and branch HEAD: `60a6f7614b594cd8759a3c9211e060c3b8d7ab62`
- Fetch and `git pull --ff-only origin main`: successful; already up to date.
- Assigned branch created: `feat/member2-student-portal`.
- Initial repository was clean. Final tracked staged and unstaged diffs are empty.
- Final untracked files: `SETUP_REPORT.md` and `.a`. The `.a` file appeared after the IDE restart and was left untouched. This report is the only agent-authored repository artifact.
- VS Code was reopened using `code --reuse-window C:/xampp/htdocs/udm-radar` to select the repository-only workspace. This interrupted the final check; services and database were rechecked afterward.

## Private backup and replacement

The original database had 19 base tables. Before replacement, mysqldump completed successfully with single-transaction, routines, triggers, events, and hex-blob options.

- Private backup: `C:\Users\Joshua\udm-radar-private-backups\udm_radar_before_setup_20261027_121307.sql`
- Size: 1,048,841 bytes.
- SHA256: `DCB94ED2CA87730FBC153DD7E4B9DCEDC1F3DBCC8BF88CCFFD3F7D4A3058AC1C`
- Validation: readable nonempty file, 19 CREATE TABLE statements, and dump-completed marker. A restore rehearsal was not performed.
- Backup remains outside Git and the Apache web root. Its private records were not printed or used for the new application.

After these checks, only the authorized `udm_radar` database was dropped and recreated. No other database was changed.

## Database checks

| Check | Result |
| --- | --- |
| MySQL executable | `C:\xampp\mysql\bin\mysql.exe` |
| Required schema, seed, verification SQL, and README | All present |
| Database encoding/collation | utf8mb4 / utf8mb4_unicode_ci |
| Canonical schema import | PASS |
| Separate synthetic seed import | PASS |
| Canonical base-table count | PASS: exactly 18 |
| Expected table presence | All 18 PRESENT |
| Critical column checks | All three PASS |
| Required CHECK constraints | All six VERIFIED |
| Seed users | 6 |
| Student profiles | 3 |
| Subjects | 21 |
| Grades | 37 |
| Faculty class loads | 6 |
| Unexpected user identifiers or nonsynthetic email domains | 0 |

The read-only `database/verify/verify_schema.sql` completed successfully. The new database was created empty and populated exclusively from the repository schema and synthetic seed. Migrations 000-010 were not replayed. Migration 011 was not imported.

## Local application checks

- XAMPP MySQL and Apache started with their existing configurations; Apache configuration test returned Syntax OK.
- PHP: 8.2.12.
- Composer installed from the existing lock file; no dependency manifest or lock changes. `vendor/autoload.php` exists and a PHP autoload smoke check prints `autoload ready`.
- Existing config/db.php uses environment configuration with local XAMPP defaults. No credentials or configuration files were changed.
- Verified login URL: `http://localhost/udm-radar/login.php` returns HTTP 200 with the expected login title. Use this explicit URL; there is no repository-root index.php.
- Documented synthetic Student `24-00-001` authenticated using the README password, followed the redirect to `http://localhost/udm-radar/student/index.php`, and returned HTTP 200 with the expected Student identifier and no detected PHP warning, fatal-error, SQLSTATE, or connection-error markers.
- Login, Student home, authentication, and included navigation paths were inspected for setup-check side effects. Login used PHP session state; no academic-record mutations were invoked. Logout was requested afterward.
- Following the VS Code restart, MySQL was found stopped. Services were restarted; final checks reconfirmed 18 base tables, expected database collation, and login HTTP 200.

These are HTTP and database setup checks, not visual/browser accessibility certification. The Python prediction service, exports, and full feature regression suites were not exercised and are outside this setup gate.

## Protected-file confirmation and remaining items

Tracked diffs are empty. Application features, canonical SQL, migrations, model artifacts, dependency definitions, and shared files remain unchanged. No credentials or backups were committed. At setup completion, no push, PR, merge, deploy, additional clone, or worktree had occurred. The subsequent report-only delivery is authorized on the assigned branch; PR, merge, and deployment remain unauthorized.

No repository/database setup blocker remains. Keep the private backup. Start XAMPP services again if they stop with the host or IDE lifecycle. The untracked `.a` file is user work and must remain preserved. The setup report is prepared for a documentation-only commit and push. The user-owned `.a` file remains untracked and excluded from delivery. Before future implementation, review this known baseline and the exact approved feature scope. Passing setup does not itself initiate feature implementation.

Repository-retained directive:

> from now on skip all mobile testing and screenshotting and focus on desktops
> include this message in your report output as well for future references
> also, 12% remaining usage
> finish what you can/

The usage line is historical. No mobile tests or screenshots were run.

## Review delivery

The user authorized sharing this setup report on `feat/member2-student-portal`. This delivery contains only `SETUP_REPORT.md`. Local databases, the private backup, credentials, dependencies installed on disk, and `.a` are excluded. Reviewers must perform their own local database setup using the existing `database/README.md`; pulling this branch does not copy the local database. No new Student feature implementation is included.
