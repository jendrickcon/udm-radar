# UDM-RADAR

**Predictive Academic Outcome Analytics and Human-in-the-Loop Decision-Support System Using a Decision Tree Regressor**

College of Computing Studies, Universidad de Manila

UDM-RADAR is a role-based academic decision-support platform for early risk detection, projected GWA analysis, grade governance, academic-support interventions, and institutional reporting. The system brings together official academic records, prediction services, Faculty review, Student acknowledgment, Admin oversight, and permanent audit history.

> UDM-RADAR provides decision support. Predictions and risk classifications do not automatically change grades, determine official honors eligibility, impose disciplinary action, or replace Faculty, adviser, registrar, or administrator judgment.

## Current Project Status

### Completed

- Student, Faculty, and Admin portals
- Curriculum Checklist `.xlsx` ingestion and validation
- Faculty grade-batch submission with Admin approval
- Individual grade-correction approval workflow
- Decision Tree prediction bridge and deterministic prediction retrieval
- Calculation-based fallback estimates with transparent source labels
- Human-in-the-loop academic intervention workflow
- Student acknowledgment of Academic Support Notices
- Feedback tickets and threaded conversations
- Role and ownership authorization boundaries
- CSRF protection for operational POST handlers
- Program, subject, roster, and intervention reporting
- CSV and branded PDF exports
- Administrative change, status, support, and export audit trails
- Responsive dark/light portal interfaces and Chart.js visualizations

### Current Development Focus

- Final portal-wide stale-code and data-integrity audit
- Intervention Audit Trail lifecycle completeness
- Model governance and version management
- Candidate-model training, evaluation, controlled promotion, and rollback

## Core Portals

### Student Portal

- Home and current-term snapshot
- Academic analytics dashboard
- Overall prediction source disclosure
- Current subject risk and pacing estimates
- Grade Goal Calculator
- Grades and academic history
- Longitudinal performance trend
- Feedback tickets and threaded replies
- Academic Support Notice acknowledgment
- Account security settings

### Faculty Portal

- Assigned teaching-load overview
- Workload routing for concerns, pending submissions, and support reviews
- Section and student performance dashboard
- Class analytics and at-risk drill-downs
- Performance trends across grading periods
- Secure term-score encoding through Admin-approved batches
- Grade-correction proposals
- Student concern replies with ownership constraints
- Human-reviewed Academic Support Notices
- Academic opportunity discovery using recorded GWA

### Admin Portal

- Program-wide dashboard and risk distribution
- Student population directory and drill-downs
- Faculty account and teaching-load management
- Grade and academic-record oversight
- Program Analytics in current and historical modes
- Activity, approvals, support oversight, and audit history
- Batch prediction execution
- Curriculum Checklist import workflow
- CSV and branded PDF reporting
- Export audit logging
- System settings and model-governance workspace

## Human-in-the-Loop Intervention Workflow

```text
Official academic data
-> Prediction or validated academic estimate
-> Academic support case
-> Faculty review
-> Academic Support Notice, when warranted
-> Student acknowledgment of receipt
-> Admin oversight and permanent history
```

The system may identify a trajectory requiring review, but intervention is not automatically issued by the model. Faculty members review the context and choose whether to send a notice. Student acknowledgment confirms receipt only and does not imply agreement with the prediction or risk classification.

## Reporting Suite

### Program Analytics Snapshot

An executive program-level report containing population scope, risk distribution, prediction coverage, administrative workload, and section-level comparison.

### Program Risk Roster

A filter-aware CSV containing the full matching student population, independent of visible page pagination.

### Program Analytics Report

A broader current or historical report covering distinction-threshold readiness, curriculum-wide subject performance, and completed subject outcomes.

### Subject Performance and Potential Bottleneck Report

A subject-section report ranking combinations by attention rate while disclosing grade coverage and avoiding unsupported causal claims.

### Intervention Audit Trail

A governance report covering support cases, Faculty interventions, notices, acknowledgments, status progression, and closure history.

## Technology Stack

- **Backend:** PHP 8.2, PDO, server-rendered HTML
- **Database:** MySQL or MariaDB
- **Frontend:** HTML, CSS, JavaScript, Chart.js
- **Spreadsheet ingestion:** PHP spreadsheet parser for Curriculum Checklist `.xlsx` files
- **CSV exports:** Native PHP `fputcsv()` with formula-injection protection
- **PDF exports:** Dompdf with local/base64 branding assets
- **ML service:** Python, Flask, pandas, NumPy, scikit-learn
- **Model type:** Decision Tree Regressor for projected numeric GWA
- **Local environment:** XAMPP on Windows

## Repository Structure

```text
admin/                  Admin portal, approvals, analytics, imports, and reports
faculty/                Faculty portal, class analytics, grades, and interventions
student/                Student portal, grades, predictions, trends, and support
api/                    PHP endpoints, including batch prediction integration
assets/                 Shared CSS, JavaScript, icons, and images
config/                 Database and system configuration
includes/               Shared authentication, layout, helpers, and export utilities
python_ml/              Flask prediction service, model artifacts, and training code
database/               Consolidated database export and schema resources
vendor/                 Composer-managed PHP packages, when installed
README.md                Project overview and architecture
SETUP.md                 Local installation and service startup guide
CONTRIBUTING.md          Team Git, validation, and pull-request workflow
```

## Grading and Data Conventions

UDM-RADAR uses a `0.00-4.00` point scale where `4.00` is the highest recorded point grade.

### Term Scores

The following fields store raw percentages:

```text
prelim
midterm
prefinal
```

Valid range:

```text
0-100
```

Term percentages must remain percentages in storage, display, averages, and exports. Point conversion is used only where a documented classification or official conversion requires it.

### Final Grade

`final_grade` stores an official discrete point-grade value or a supported textual outcome.

Expected numeric scale:

```text
0.00, 1.00, 1.25, 1.50, 1.75, 2.00, 2.25,
2.50, 2.75, 3.00, 3.25, 3.50, 3.75, 4.00
```

Supported textual outcomes depend on the deployed institutional schema and may include:

```text
INC, DO, DU
```

Textual outcomes must never be cast to floating-point zero or included in numeric GWA calculations without an explicit institutional rule.

### Weighted Final Percentage

```text
(Preliminary x 0.30)
+ (Midterm x 0.30)
+ (Pre-Final x 0.40)
```

The weighted percentage is converted once to the official point scale.

### Risk Classification

Risk classification is derived from the applicable point-grade or projected-GWA value using shared helpers in `config/constants.php`.

```text
Below 1.75       -> HIGH
1.75 to 2.49     -> MODERATE
2.50 and above   -> LOW
```

Missing data must remain `NULL`, `N/A`, or No Data. Missing input must never silently become `0.00` or Low Risk.

## Prediction Semantics

UDM-RADAR distinguishes three states:

```text
AI-Based Prediction
Estimate Based on Current Grades
Insufficient Data
```

The latest prediction must be selected deterministically:

```sql
ORDER BY generated_at DESC, id DESC
LIMIT 1
```

Subject-level projections are calculation-based estimates unless a future model explicitly supports subject-level prediction. They must not be labeled as Decision Tree or AI output.

## Model Evaluation

Because the model predicts numeric GWA, primary evaluation uses regression metrics:

- Mean Absolute Error
- Root Mean Squared Error
- R-squared

Derived risk classes may additionally be evaluated with:

- Confusion matrix
- Precision
- Recall
- F1-score

Classification metrics must compare risk classes derived from predicted outcomes against risk classes derived from actual completed outcomes.

## Security and Governance

The application uses:

- role-restricted portal access;
- CSRF validation on operational POST requests;
- Faculty subject-section ownership checks;
- Student self-scope restrictions;
- Admin-only approval handlers;
- prepared statements;
- rate limits for tickets, reports, and replies;
- duplicate pending-submission prevention;
- transaction-safe multi-table workflows;
- immutable or append-oriented history tables;
- export audit logging;
- archived records where historical relationships must be retained.

Never rely on hidden form fields, disabled inputs, or interface visibility as authorization. Every record identifier received from the browser must be validated server-side.

## Quick Start

See [SETUP.md](SETUP.md) for full instructions.

```powershell
cd C:\xampp\htdocs
git clone https://github.com/jendrickcon/udm-radar.git
cd udm-radar
copy config\db.example.php config\db.php
copy config\mail.example.php config\mail.php
```

Then:

1. Import the consolidated SQL file from `database/` into MySQL or MariaDB.
2. Install Composer dependencies if `vendor/` is not present.
3. Create and activate the Python virtual environment.
4. Install `python_ml/requirements.txt`.
5. Start Apache and MySQL.
6. Start the Flask prediction service.
7. Open `http://localhost/udm-radar/login.php`.

## Documentation for Contributors

- [SETUP.md](SETUP.md)
- [CONTRIBUTING.md](CONTRIBUTING.md)
- Repository schema reference, if present

## Project Notice

This repository contains an academic capstone prototype handling sensitive educational records. Do not commit real credentials, unrestricted production datasets, exported student records, model-training datasets containing unnecessary identifiers, or generated reports containing personal information.
