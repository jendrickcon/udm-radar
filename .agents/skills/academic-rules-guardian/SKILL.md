---
name: academic-rules-guardian
description: Validates academic grading calculations, UDM 2025 scale conventions, GWA unit-weighting, and pass-rate denominators against repository constants.
---

# Academic Rules Guardian Skill

This skill enforces UDM academic grading conventions, calculations, and data domain validations.

## When to Use
Use this skill whenever:
* Modifying grade encoding, validation, or storage logic (`faculty/grades.php`, `admin/grades.php`, `api/save_grade.php`).
* Updating GWA calculations or summary statistics (`admin/analytics.php`, `student/trend.php`, `tools/audit_gwa_parity.php`).
* Working with risk triage thresholds, Dean's List honors cutoffs, or subject passing rates.

---

## 1. Source of Truth: `config/constants.php`

Always read recognized grading vocabularies, honors thresholds, and conversion boundaries directly from `config/constants.php`. Never invent or duplicate independent lists of textual outcomes.

### Canonical Constants Reference (from `config/constants.php`):
* **Latin Honors Defines:**
  * `SUMMA_CUM_LAUDE` ($3.75$)
  * `MAGNA_CUM_LAUDE` ($3.50$)
  * `CUM_LAUDE` ($3.25$)
  * `DEANS_LISTER` ($3.25$)
* **Grade Computation Weight Defines:**
  * `WEIGHT_PRELIM` ($0.30$)
  * `WEIGHT_MIDTERM` ($0.30$)
  * `WEIGHT_PREFINAL` ($0.40$)
* **Canonical Arrays:**
  * `FINAL_GRADE_POINTS`: Array of discrete points from `'1.00'` to `'4.00'`
  * `FINAL_GRADE_STATUSES`: Array of recognized textual outcomes (`INC`, `DRP`, `P`, `DO`, `DU`, `FA`, `UD`)

---

## 2. Core Grading Directives

1. **Direction of the Scale:**
   * $4.00$ is highest / excellent.
   * $1.00$ is lowest passing point grade.
   * $0.00$ is failed.
2. **Clarification on $1.75$ and Risk:**
   * A final point grade of $1.50$ or $1.25$ is a passing subject mark ($\ge 1.00$).
   * A cumulative or projected GWA below $1.75$ triggers the **High Risk** prototype triage category.
   * **Do not equate High Risk with subject failure.** Passing courses with grades between $1.00$ and $1.50$ may yield an average in High Risk, but course credits are still earned.
   * **Do not infer official academic standing or benefit loss.**
3. **Term Percentage Domain vs. Final Grade Domain:**
   * Preliminary, Midterm, and Pre-Final must be strictly numeric percentages ($0\text{--}100$).
   * Final Grade is stored as `VARCHAR(10)` and must strictly match canonical points or uppercase textual statuses.
4. **Unit-Weighted GWA Calculation:**
   $$\text{GWA} = \frac{\sum (\text{Point Grade}_i \times \text{Units}_i)}{\sum \text{Units}_i}$$
   * Non-numeric outcomes (`INC`, `DO`, `DU`, `DRP`, `PASSED`) are strictly excluded from both grade weighting sums and total unit denominators.
5. **Pass-Rate Denominators in Historical Analytics:**
   * Formula: $\text{Pass Rate} = \frac{\text{Passed Count}}{\text{Recognized Outcome Count}}$
   * $\text{Recognized Outcome Count} = \text{Passed Count} + \text{Failed Count}$.
   * Strictly exclude `DRP`, `INC`, `NULL`, and unparseable codes from the denominator.

---

## 3. Required Verification After Grade-Related Changes

After modifying any file handling grades or averages, run applicable PHP regression checks:
```powershell
php tests/unit/final_grade_helpers_test.php
php tests/unit/gwa_helpers_test.php
php tests/integration/final_grade_storage_test.php
php tests/integration/gwa_parity_test.php
```
Ensure all assertions pass with exit code `0`.
