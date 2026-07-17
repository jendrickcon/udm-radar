# UdM-RADAR

**Predictive Academic Outcome Analytics and Decision-Support System Using Decision Tree Algorithm**
College of Computing Studies, Universidad de Manila

A capstone project providing early risk detection, GWA prediction, and role-differentiated
academic dashboards for students, faculty, and administrators.

## Tech Stack

- **Backend:** PHP 8.2 (procedural, PDO for MySQL)
- **Database:** MySQL / MariaDB (XAMPP)
- **Frontend:** Vanilla HTML/CSS/JS, Chart.js for visualizations
- **ML pipeline:** Python (planned — `python_ml/`, not yet integrated)

## Project Structure

```
├── admin/              Admin portal (program-wide analytics, CRUD, grade overrides)
├── faculty/             Faculty portal (class analytics, grade encoding, trends)
├── student/             Student portal (dashboard, grades, GWA trend, goal calculator)
├── config/               Configuration (see Setup below)
├── includes/            Shared auth, header, footer, sidebar
├── assets/               CSS/JS shared across all portals
└── python_ml/            Decision Tree prediction pipeline (in progress)
```

## Setup

1. Clone this repo into your XAMPP `htdocs` folder.
2. Copy the example config files and fill in your local values:
   ```
   cp config/db.example.php config/db.php
   cp config/mail.example.php config/mail.php
   ```
3. Import the database schema and seed data (see `/database` exports, if included).
4. Start Apache + MySQL in XAMPP Control Panel.
5. Visit `http://localhost/udm-radar/login.php`.

## Grading & Data Conventions

- **Scale:** 1.00–4.00, where 4.00 is highest (not 5.00 — see `config/constants.php`).
- **`prelim`/`midterm`/`prefinal`** columns are always raw 0–100 percentages.
- **`final_grade`** is always already point-scale (1.00–4.00).
  Never assume the scale from a number's magnitude — use `normalizeTermGrade()` /
  `normalizePointGrade()` in `config/constants.php`.
- **Final grade formula:** `(0.30 × Prelim) + (0.30 × Midterm) + (0.40 × Pre-Final)`.
- **Risk thresholds** (`computeRiskFromAvg()`), anchored to UdM's official grade-description table:
  - `< 1.75` → HIGH
  - `1.75–2.49` → MODERATE
  - `≥ 2.50` → LOW
- **Latin honors** (`getLatinHonor()`): Cum Laude ≥3.25, Magna Cum Laude ≥3.50, Summa Cum Laude ≥3.75.

## Current Status

- Student, Faculty, and Admin portals: built.
- Prediction: currently a documented heuristic placeholder
  (`predictFinalGradeHeuristic()` — 50/50 blend of current-term performance and
  historical GWA), clearly labeled as such in the UI. Real Decision Tree model
  not yet implemented (`python_ml/`).

## Notes for Contributors / Adviser

See `schema_reference.md` for a plain-English walkthrough of how the database
tables connect — in particular, `predictions` is an append-only log (always
query with `ORDER BY generated_at DESC LIMIT 1`), and `grades.student_id`
points to `users.id`, not a separate `students` table.
