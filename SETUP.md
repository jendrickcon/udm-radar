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

Import the current consolidated SQL file from the repository's `database/` directory.

The imported database must include the current application tables, including the applicable versions of:

```text
users
student_profiles
subjects
grades
predictions
faculty_class_loads
pending_grade_batches
pending_corrections
feedback_reports
feedback_messages
feedback_status_history
academic_support_cases
support_actions
support_status_history
admin_change_log
export_audit_log
```

Table names may vary only if the application code and consolidated schema have been updated together.

### Database warning

Do not treat an old base schema as sufficient if newer portal code depends on support, pending-approval, or export-audit tables. The repository's consolidated export should represent a complete runnable installation.

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
- generated student reports
- imported real student datasets
- temporary model candidates
- application or web-server logs containing private data

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
