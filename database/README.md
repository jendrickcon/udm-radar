# UDM-RADAR Canonical Database & Onboarding Guide

This directory contains the canonical database bootstrap schema, approved database migrations, synthetic demonstration seed scripts, and read-only verification utilities for **UDM-RADAR** (Universidad de Manila — Risk Analytics & Decision-support for Academic Records).

---

## 1. Contents of `database/udm_radar.sql`

* **Canonical Baseline Schema (Mode A — Schema-Only Bootstrap):** Contains complete DDL definitions for all **18 application base tables**, including primary keys, foreign keys, constraints, defaults, indexes, and CHECK constraints reconciled through Migration `010`.
* **Standard Encoding:** `utf8mb4` character set with `utf8mb4_unicode_ci` / `utf8mb4_general_ci` collation.
* **Compatibility:** Fully compatible with MySQL 8.0+ and MariaDB 10.4+ (default XAMPP distribution).

---

## 2. Intentional Exclusions & Data Privacy Boundary

* **Zero Genuine Personal Information:** Contains **no** real student numbers, genuine faculty identities, real names, email addresses, phone numbers, credentials, or actual registrar evaluations.
* **No Ephemeral or Operational Records:** Excludes session rows, password-reset tokens, OTP tokens, audit logs, error logs, model secrets, or generated export files.
* **Migration 011 Strictly Excluded:** Migration `011` remains blocked from the canonical schema pending institutional review.
* **No Database Users or Grants:** Excludes machine-specific `DEFINER` clauses, privilege statements, or user grants.

---

## 3. Fresh Import Instructions (Onboarding for Vincent & Member 2)

### Step 3.1: Create Local Database
Open your MySQL / MariaDB client (phpMyAdmin or CLI):
```sql
CREATE DATABASE `udm_radar` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 3.2: Import Canonical Schema
Run the import via command-line MySQL or phpMyAdmin **Import** tab:
```bash
# Windows / XAMPP Command Prompt:
C:\xampp\mysql\bin\mysql.exe -u root udm_radar < database/udm_radar.sql

# macOS / Linux Command Line:
mysql -u root -p udm_radar < database/udm_radar.sql
```

### Step 3.3: Populate Starter Synthetic Demo Dataset
To populate the database with synthetic user accounts, subjects, and records for local testing and portal evaluation:
```bash
# Windows / XAMPP:
C:\xampp\mysql\bin\mysql.exe -u root udm_radar < database/seeds/001_synthetic_demo.sql

# macOS / Linux:
mysql -u root -p udm_radar < database/seeds/001_synthetic_demo.sql
```

---

## 4. Documented Synthetic Demo Credentials

The starter seed provides safe, privacy-compliant synthetic accounts for all three portals:

| Portal | User ID | Password | Synthetic Identity |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `AdminPassword2026!` | Demo System Administrator |
| **Faculty** | `faculty01` | `FacultyPassword2026!` | Synthetic Faculty Alpha |
| **Faculty** | `faculty02` | `FacultyPassword2026!` | Synthetic Faculty Beta |
| **Student** | `24-00-001` | `StudentPassword2026!` | Synthetic Student 001 |
| **Student** | `24-00-002` | `StudentPassword2026!` | Synthetic Student 002 |
| **Student** | `24-00-003` | `StudentPassword2026!` | Synthetic Student 003 |

*(Change passwords in shared team staging environments).*

---

## 5. Post-Import Local Configuration

Configure your local database credentials in `config/db.php` or via environment variables:
* `DB_HOST`: `localhost` or `127.0.0.1`
* `DB_USER`: `root` (or local user)
* `DB_PASS`: empty string for standard XAMPP (or local password)
* `DB_NAME`: `udm_radar`

---

## 6. How to Verify Successful Import

Execute the read-only verification script to ensure all 18 tables, column data types, and check constraints match canonical requirements:
```bash
mysql -u root udm_radar < database/verify/verify_schema.sql
```
Expected output:
* `table_count_status`: `PASS` (Total tables: 18)
* All 18 application tables: `PRESENT`
* Critical column types (`grades.final_grade`, `support_case_referrals.notice_issued_at`, etc.): `PASS`
* Mandatory CHECK constraints: `VERIFIED`

---

## 7. Migration Governance and Boundaries

* **Approved Migration Range:** The canonical dump incorporates approved migrations `000` through `010`. After importing `database/udm_radar.sql`, do **not** re-run migrations `000` through `010`.
* **Migration 011 Excluded:** Migration `011` is not part of this release and must not be executed.
* **Strict Repository Invariants:**
  * Members must **never** replace `database/udm_radar.sql` with a live database dump.
  * Members must **never** commit their personal database exports or machine-specific backups to Git.
  * All canonical database changes require approved work packages and disposable-database verification before promotion.

