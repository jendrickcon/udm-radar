# UDM-RADAR Setup Guide

This guide installs and runs the PHP application, MySQL or MariaDB database, Composer dependencies, and Python prediction service on a Windows development machine using XAMPP.

## 1. Prerequisites

Install:

- XAMPP with Apache, MySQL or MariaDB, PHP, and phpMyAdmin
- Git
- Visual Studio Code or another editor
- Composer
- Python 3 with `pip` and `venv`

Recommended VS Code extensions:

- PHP Intelephense
- Python by Microsoft
- SQLTools
- SQLTools MySQL/MariaDB Driver
- GitLens

Use LF line endings where possible to avoid noisy cross-platform diffs.

## 2. Clone the Repository

Open PowerShell or the VS Code terminal:

```powershell
cd C:\xampp\htdocs
git clone https://github.com/jendrickcon/udm-radar.git
cd udm-radar
```

If Apache uses a non-default document root, clone the repository into that configured web directory instead.

## 3. Configure PHP

Confirm the PHP version:

```powershell
C:\xampp\php\php.exe -v
```

PHP 8.2 is the expected local environment.

Ensure required extensions are enabled in `C:\xampp\php\php.ini`, particularly those required by the repository and installed dependencies. Common requirements include:

```text
pdo_mysql
mysqli
mbstring
fileinfo
openssl
zip
gd
```

Restart Apache after changing `php.ini`.

## 4. Configure Local Application Files

Copy local configuration templates:

```powershell
copy config\db.example.php config\db.php
copy config\mail.example.php config\mail.php
```

Update `config\db.php` with the local database values. A typical XAMPP development configuration uses:

```text
Host: localhost
Database: udm_radar
Username: root
Password: empty unless changed locally
```

Do not commit real configuration files containing credentials.

## 5. Install PHP Dependencies

If the repository does not already contain a usable `vendor/` directory, install dependencies from the project root:

```powershell
composer install
```

The reporting layer uses Dompdf. Installation should be driven by the repository's `composer.json` and `composer.lock`, not by manually copying package folders.

Verify that this file exists afterward:

```text
vendor/autoload.php
```

## 6. Create and Import the Database

Start Apache and MySQL in XAMPP Control Panel.

Open:

```text
http://localhost/phpmyadmin
```

Create the database expected by `config/db.php`, normally:

```text
udm_radar
```

### 6.1 Fresh Installation (New Database Setup)

The committed baseline file `database/udm_radar.sql` is a **reconciled schema-only dump** containing all 18 official application tables incorporating migrations `000` through `010`.

```powershell
# 1. Create fresh database
mysql -u root -e "CREATE DATABASE IF NOT EXISTS udm_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 2. Import canonical 18-table baseline schema (post-010)
mysql -u root udm_radar < database\udm_radar.sql

# 3. Seed starter synthetic demonstration data
mysql -u root udm_radar < database\seeds\001_synthetic_demo.sql
```

> [!NOTE]
> **Baseline vs. Migrations Distinction**:
> - `database/udm_radar.sql` already incorporates all structural changes from migrations `000` through `010`.
> - Re-running migrations `000` to `010` after importing `database/udm_radar.sql` is **not required** on a fresh install (though doing so is harmless because all migrations are strictly idempotent).
> - The starter seed (`database/seeds/001_synthetic_demo.sql`) populates initial curriculum subjects and synthetic accounts (`admin`, `faculty01`, `faculty02`, `24-00-001`, `24-00-002`, `24-00-003`).

#### Temporary Starter Credentials
- **Administrator:** `admin` / `AdminPassword2026!`
- **Faculty:** `faculty01` / `FacultyPassword2026!` (Class load: ITE321, ITE322, ITE331)
- **Faculty:** `faculty02` / `FacultyPassword2026!` (Class load: ITE323, ITE341, SD351)
- **Student (Low Risk):** `24-00-001` / `StudentPassword2026!`
- **Student (Moderate Risk):** `24-00-002` / `StudentPassword2026!`
- **Student (High Risk / Case Trigger):** `24-00-003` / `StudentPassword2026!`

*Change temporary passwords in shared or team development environments.*

### 6.2 Upgrading an Existing Local Installation

If you already have a working local installation and need to update after pulling new code:

> [!CAUTION]
> **DO NOT RE-IMPORT `database/udm_radar.sql` OVER AN EXISTING DATABASE.**
> Re-importing the baseline dump will drop or conflict with your active development records.
> **DO NOT IMPORT DEMO SEED DATA OVER ACTIVE DEV DATA.**
> The starter seed will refuse execution if user records already exist. Use migrations to update existing schemas.

```powershell
# 1. Always back up your database before updating
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
mysqldump -u root udm_radar > "capstone_backup_$timestamp.sql"

# 2. Pull latest code (safe fast-forward only)
git switch main
git fetch origin
git pull --ff-only origin main

# 3. Apply ONLY pending migrations in numerical order (example: if upgrading from 008)
mysql -u root udm_radar < database\migrations\009_add_support_reason_code.sql
mysql -u root udm_radar < database\migrations\010_add_notice_issued_at.sql

# 4. Verify schema health
mysql -u root udm_radar < database\verify\verify_schema.sql
```

### 6.3 Testing on Scratch Database
Before executing schema changes, migrations, or data-mutating tests, run them on an isolated scratch database:

```powershell
# Rebuild clean scratch database using the automated rebuild tool
php tools\rebuild_database.php --target=scratch
```
*Note: The live database (`udm_radar`) cannot be targeted by automated rebuild scripts.*

Do not commit database backups or local dumps to Git (enforced in `.gitignore`).

## 7. Configure the Python Environment

From the project root:

```powershell
cd python_ml
python -m venv venv
venv\Scripts\activate
```

Install the pinned dependencies:

```powershell
pip install -r requirements.txt
```

Expected packages generally include:

```text
Flask
pandas
NumPy
scikit-learn
openpyxl
joblib
```

Use the actual `requirements.txt` as the source of truth.

## 8. Configure the Prediction Service

Review the Python service configuration and the PHP endpoint that calls the service, such as:

```text
api/batch_predict.php
```

Confirm that both sides agree on:

- service URL and port;
- request field names;
- response schema;
- timeout behavior;
- prediction source value;
- model artifact location;
- error logging.

Do not place secrets directly in committed source files. Use the repository's local configuration pattern.

## 9. Start the Application

Start in this order:

1. MySQL or MariaDB
2. Apache
3. Python virtual environment
4. Flask prediction service

A typical Python startup sequence is:

```powershell
cd C:\xampp\htdocs\udm-radar\python_ml
venv\Scripts\activate
python app.py
```

Use the actual Flask entry point present in `python_ml/` if the file name differs.

Open the PHP application:

```text
http://localhost/udm-radar/login.php
```

If Apache was moved to port 8080, use:

```text
http://localhost:8080/udm-radar/login.php
```

## 10. Verify the Installation

### Authentication

- Login works for Student, Faculty, and Admin test accounts.
- Each role is restricted to its own portal.
- Logout destroys the authenticated session.

### Database

- Student profiles and class loads appear.
- Current and historical grades load.
- Support and feedback pages do not report missing tables.
- Export audit logging succeeds.

### Grading rules

- Preliminary, Midterm, and Pre-Final display as percentages.
- Final Grade displays as an official point value or supported textual status.
- Missing data displays as `N/A`, No Data, or an em dash rather than `0.00`.

### Prediction service

- Batch prediction succeeds while Flask is running.
- The PHP request ends cleanly when Flask is stopped.
- Service errors are logged without exposing sensitive server details.
- The latest prediction uses deterministic timestamp-and-ID ordering.

### Exports

Test:

- Program Risk Roster CSV
- Program Snapshot CSV and PDF
- Program Analytics PDF
- Subject Performance CSV and PDF
- Intervention Audit Trail CSV and PDF

Confirm that PDFs render without clipping and CSV files respect active filters.

## 11. Current Model-Governance Development

The intended controlled model lifecycle is:

```text
Upload dataset
-> Validate schema and scale
-> Train candidate model
-> Evaluate candidate
-> Compare against active model
-> Promote explicitly
-> Retain rollback version
-> Log the event
```

Until every part is implemented, do not overwrite the only active model artifact manually. Back up the current model before testing retraining workflows.

## 12. Development Safety

Use test accounts and a copied database for destructive or adversarial testing.

Before major testing:

```text
1. Export the database.
2. Copy the project directory or create a Git branch.
3. Record the initial model version.
4. Use non-production student data.
```

Do not commit:

- `config/db.php`
- `config/mail.php`
- local `.env` files
- virtual environments
- generated student reports or populated PEF/CC workbooks
- imported real student datasets or registrar evaluations
- private PEF/CC workbook templates containing real student records
- temporary model candidates
- application or web-server logs containing private data

### 12.1 Synthetic Records & PEF/CC Privacy Boundary

- **Prototype Nature:** UDM-RADAR operates on synthetic academic records authorized by the Capstone Adviser. All user-facing views carry the label: `Prototype performance on synthetic academic records`.
- **Private PEF/CC Templates:** Original registrar Curriculum Checklist spreadsheets and populated student workbooks contain identity-bearing registrar evaluations and must remain outside version control.
- **Decision Support Only:** Advisory predictions do not constitute binding disciplinary actions, registrar evaluations, or official honors determinations.

### 12.2 Member Git Update Workflow

To synchronize with `origin/main` without risking local work:

```powershell
# 1. Check working tree status
git status

# 2. Switch to main and fast-forward pull
git switch main
git fetch origin
git pull --ff-only origin main
```

- **If you have uncommitted local changes:** Do NOT pull directly. Either commit your changes to a feature branch (`git switch -c feat/my-feature; git commit -m "wip"`) or stash them (`git stash push -m "wip"`).
- **If dependencies changed:** Run `composer install --no-dev` or `pip install -r python_ml\requirements.txt`.
- **If migrations were added:** Inspect `database/migrations/` and apply pending files in order.
- **For full update scenarios:** Consult [docs/MEMBER_GIT_UPDATE_GUIDE.md](docs/MEMBER_GIT_UPDATE_GUIDE.md).

## 13. Troubleshooting

### Apache does not start

Another application may be using port 80 or 443. Check XAMPP logs and the Apache configuration before changing ports.

### MySQL connection is denied

Verify that:

- MySQL is running;
- the database name matches `config/db.php`;
- the username and password are correct;
- the imported database exists.

### Blank PHP page

Check:

```text
C:\xampp\apache\logs\error.log
```

Temporarily enable local PHP error display only in a safe development environment.

### `vendor/autoload.php` missing

Run:

```powershell
composer install
```

### Dompdf export fails

Check:

- Composer dependencies;
- PHP memory limit;
- writable temporary directory;
- local logo path;
- HTML validity;
- table pagination and supported CSS.

### Python is not recognized

Restart the terminal after installing Python. Confirm:

```powershell
python --version
```

If needed, use the Python launcher:

```powershell
py --version
```

### Flask service is unavailable

Confirm that the virtual environment is active and the configured service port matches the PHP bridge.

### Export says a required table is missing

The database import is stale. Import the current consolidated schema rather than bypassing the prerequisite check.

## 14. Updating the Consolidated Database Export

After an approved schema change:

1. Apply and test the migration locally.
2. Export a clean consolidated schema/data snapshot appropriate for the repository.
3. Remove unnecessary personal or sensitive records.
4. Replace the old consolidated SQL file.
5. Document the schema impact in the pull request.
6. Verify a fresh installation using only the updated export.

A new contributor should not need to reconstruct the database by guessing which historical patches to replay.
