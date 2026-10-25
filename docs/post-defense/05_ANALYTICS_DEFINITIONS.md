# Analytical Definitions and Mathematical Specifications — UDM-RADAR

**Document Version:** 1.1.0 (Post-Defense Revision & Metric Clarification)
**Accepted Baseline SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Standard Scale:** The discrete point scale currently configured and accepted for the UDM-RADAR prototype (guided by the Universidad de Manila Student Manual 2025 and codified in `config/constants.php`): $4.00\text{--}1.00$ discrete point scale ($4.00$ = Highest/Excellent; $1.00$ = Minimum Passing; $0.00$ = Numeric Failure).

---

> [!CAUTION]
> **CRITICAL STATISTICAL WARNINGS FOR CONTRIBUTING AGENTS & MEMBERS:**
> 1. **Do NOT call $R^2$ an "accuracy percentage".** $R^2$ (coefficient of determination) measures the proportion of variance in numerical GWA explained by the regression tree model relative to a mean baseline. It is a goodness-of-fit indicator, not a percentage of correct predictions.
> 2. **Do NOT call an empirical error margin a "confidence interval".** A confidence interval requires verified asymptotic normality and independent observations. UDM-RADAR uses an **empirical prediction margin** derived from test Mean Absolute Error ($\text{MAE}$).
> 3. **Scale Directionality:** Higher numerical points indicate stronger academic achievement ($4.00 > 1.00$). A positive difference ($+0.25$) denotes academic improvement; a negative difference ($-0.15$) denotes a lower point result.
> 4. **Prototype Boundaries vs Official Institutional Standing:** Risk tiers (`HIGH`: $<1.75$, `MODERATE`: $1.75\text{--}2.49$, `LOW`: $\ge 2.50$) are decision-support thresholds for advisory triage only. They do not determine probation, warning status, retention, or official registrar standing.

---

## 1. Student-Level Metrics

### MTR-01: Current Term GWA
- **Formula:**
  $$\text{GWA}_{\text{curr}} = \frac{\sum_{i=1}^{n} (G_i \times U_i)}{\sum_{i=1}^{n} U_i}$$
  where $G_i$ is the official numeric final grade ($4.00\text{--}1.00$) for enrolled subject $i$, and $U_i$ is the curriculum unit weight.
- **Numerator:** Sum of unit-weighted final numeric grades for the current evaluation semester.
- **Denominator:** Total curriculum units of subjects with recorded numeric grades ($4.00\text{--}1.00$).
- **Eligible Records:** Current enrolled semester subjects with discrete point final grades.
- **Excluded Records:** Non-numeric outcomes (`INC`, `DRP`, `P`, `DO`, `DU`, `FA`, `UD`), legacy zeros, unrecorded/in-progress subjects.
- **Missing-Data Behavior:** Returns `NULL` / `Pending` if any enrolled subject lacks a final grade. Does not compute partial term GWA.
- **Intended Roles:** Student, Faculty, Admin.
- **Interpretation Warning:** Advisory calculation only; does not replace the Registrar's official end-of-term evaluation.

---

### MTR-02: Previous Comparable GWA
- **Formula:** $\text{GWA}_{\text{prev}} = \text{GWA}$ of the most recent completed regular academic semester preceding the current term.
- **Numerator / Denominator:** Computed using standard unit-weighted GWA formula applied to the preceding regular term.
- **Eligible Records:** Completed regular semesters (Semester 1 or 2).
- **Excluded Records:** Current in-progress semester, terms with zero completed numeric grades, summer terms (unless explicitly comparing summer-to-summer).
- **Missing-Data Behavior:** Returns `NULL` / `First Term on Record` for first-year students in their first semester.
- **Intended Roles:** Student, Admin.
- **Interpretation Warning:** Trajectory comparison is valid only when curriculum loading is reasonably comparable.

---

### MTR-03: Point Change ($\Delta\text{GWA}$)
- **Formula:**
  $$\Delta\text{GWA} = \text{Current/Estimated GWA} - \text{Previous Comparable GWA}$$
- **Numerator / Denominator:** Direct subtraction between two scalar point values.
- **Eligible Records:** Students with both a current/estimated GWA and a valid previous comparable GWA.
- **Excluded Records:** Students in their first semester of enrollment.
- **Missing-Data Behavior:** Displays an em dash (`—`) when previous comparable GWA is unavailable.
- **Intended Roles:** Student, Admin.
- **Interpretation Warning:** On the prototype-configured $4.00\text{--}1.00$ scale, positive delta ($> 0$) represents an improvement in point achievement. Negative delta ($< 0$) represents a drop.

---

### MTR-04: Relative Percentage Change
- **Formula:**
  $$\text{Relative Change (\%)} = \left(\frac{\text{Current/Estimated GWA} - \text{Previous GWA}}{\text{Previous GWA}}\right) \times 100\%$$
- **Numerator:** $\Delta\text{GWA}$.
- **Denominator:** Previous Comparable GWA.
- **Eligible Records:** Same as $\Delta\text{GWA}$.
- **Excluded Records:** Students with zero or non-numeric previous GWA.
- **Missing-Data Behavior:** Secondary contextual text only (e.g. tooltips); never a primary standalone KPI.
- **Intended Roles:** Student (tooltip only), Admin.
- **Interpretation Warning:** Percentage changes on bounded point scales can distort perception; a $20\%$ change near $1.25$ represents a modest point shift.

---

### MTR-05: Logarithmic Change ($\Delta\ln(\text{GWA})$)
- **Formula:**
  $$\Delta\ln(\text{GWA}) = \ln\left(\frac{\text{Current/Estimated GWA}}{\text{Previous GWA}}\right)$$
- **Numerator / Denominator:** Natural log of the ratio of current to previous GWA.
- **Eligible Records:** Valid numeric positive GWAs.
- **Excluded Records:** Zero, negative, or undefined values.
- **Missing-Data Behavior:** Strictly excluded from user-facing UI.
- **Intended Roles:** Internal research analysis and manuscript technical tables only.
- **Interpretation Warning:** Strictly prohibited as a primary user-facing dashboard label.

---

### MTR-06: Estimated GWA (Decision Tree Output)
- **Formula:**
  $$\widehat{\text{GWA}} = f_{\text{DecisionTree}}(\mathbf{x})$$
  where $\mathbf{x} = [\text{historical\_gwa}, \text{prelim\_point\_avg}, \text{units\_passed}, \text{curriculum\_progress}, \dots]$.
- **Domain:** Continuous numeric value bounded to $[1.00, 4.00]$.
- **Eligible Records:** Active students with required feature vector inputs.
- **Excluded Records:** Students missing all preliminary and historical grade data.
- **Missing-Data Behavior:** Falls back to `calculation_fallback` or heuristic model when ML inputs are incomplete, logging provenance.
- **Intended Roles:** Student, Admin.
- **Interpretation Warning:** This is a continuous regression estimate, NOT a discrete risk classification.

---

### MTR-07: Empirical Prediction Range (Residual-Based Interval)
- **Formula:**
  $$\text{Lower Bound} = \max(1.00, \; \widehat{\text{GWA}} - \text{MAE}_{\text{verified}})$$
  $$\text{Upper Bound} = \min(4.00, \; \widehat{\text{GWA}} + \text{MAE}_{\text{verified}})$$
  where $\text{MAE}_{\text{verified}}$ is the recorded test Mean Absolute Error from `python_ml/model_metrics.json`.
- **Eligible Records:** Records with a valid `decision_tree` prediction.
- **Excluded Records:** Deterministic calculation fallbacks (which carry zero model variance).
- **Missing-Data Behavior:** In fallback mode, the range collapses to the exact deterministic calculation.
- **Intended Roles:** Student, Admin.
- **Mandatory Limitations and Warnings:**
  > [!CAUTION]
  > **STATISTICAL AND INTERPRETATION BOUNDARIES (MANDATORY COPY):**
  > 1. **Interpretive Aid Only:** This range applies the verified aggregate test Mean Absolute Error ($\text{MAE}$) symmetrically around an individual point estimate as an interpretive aid.
  > 2. **Not Individualized Uncertainty:** It does NOT estimate an individual student's conditional error variance or idiosyncratic distribution.
  > 3. **Not a Confidence Interval:** It is NOT a statistical confidence interval, Bayesian credible interval, or parametric prediction interval. It assumes no Gaussian error distribution.
  > 4. **Zero Guarantee:** It offers no guarantee that the student's eventual realized GWA will fall within the interval.
  > 5. **Blocking Gate:** If no reproducible, verified $\text{MAE}$ evaluated on an approved held-out test set is available for the active model version, MTR-07 must remain **`BLOCKED`** or **`DEFERRED`**. It must NEVER be calculated using an arbitrary, hardcoded, or unverified constant.

---

### MTR-08: Advisory Risk Category
- **Formula:**
  $$\text{Risk}(\text{GWA}) = \begin{cases} \text{HIGH}, & \text{if } \text{GWA} < 1.75 \\ \text{MODERATE}, & \text{if } 1.75 \le \text{GWA} \le 2.49 \\ \text{LOW}, & \text{if } \text{GWA} \ge 2.50 \end{cases}$$
- **Input:** Current projected GWA or historical cumulative GWA.
- **Eligible Records:** Students with a calculable or estimated GWA.
- **Excluded Records:** Students with missing grade inputs (`No Data`).
- **Missing-Data Behavior:** Never defaults to `LOW` or `HIGH`; displays `No Data`.
- **Intended Roles:** Student, Faculty, Admin.
- **Interpretation Warning:** Non-punitive institutional risk triage. Does NOT determine probation, retention, or official academic standing.

---

## 2. Institutional Comparative Metrics

### MTR-09: Average GWA by Semester
- **Formula:**
  $$\overline{\text{GWA}}_{\text{sem}} = \frac{\sum_{j=1}^{M} \text{GWA}_j}{M}$$
  where $M$ is the number of enrolled students with a valid completed term GWA in that semester.
- **Numerator:** Sum of individual term GWAs.
- **Denominator:** Number of eligible students with complete final grades.
- **Excluded Records:** Incomplete student terms, students on leave, dropped terms.
- **Intended Roles:** Admin.

---

### MTR-10: Average GWA by School Year
- **Formula:** Arithmetic mean of completed term GWAs across 1st and 2nd Semesters within academic year $Y$.
- **Denominator:** Total student-terms completed in academic year $Y$.
- **Intended Roles:** Admin.

---

### MTR-11: Average GWA by Year Level
- **Formula:** Mean cumulative GWA grouped by current curriculum year level (1st Year, 2nd Year, 3rd Year, 4th Year).
- **Denominator:** Active student headcount per year level.
- **Intended Roles:** Admin.
- **Interpretation Warning:** Differences across year levels often reflect curriculum difficulty and specialization tracks, not student capability shifts.

---

### MTR-12: Subject Average Grade
- **Formula:**
  $$\overline{G}_{\text{subject}} = \frac{\sum_{k=1}^{S} G_k}{S}$$
  where $G_k$ is the final point grade in subject $s$, and $S$ is the count of numeric passing and failing point grades.
- **Numerator:** Sum of discrete numeric final point grades ($4.00\text{--}1.00$, $0.00$).
- **Denominator:** Total recognized numeric outcomes ($S = \text{Passed} + \text{Failed}$).
- **Excluded Records:** `INC`, `DRP`, `P`.
- **Intended Roles:** Faculty, Admin.

---

### MTR-13: Section Average Grade
- **Formula:** Mean completed or preliminary grade across all students enrolled in section $X$ for a given subject.
- **Denominator:** Number of students in section $X$ with recorded grades.
- **Intended Roles:** Faculty, Admin.

---

### MTR-14: Faculty-Subject Class Average (Curriculum Pacing Indicator)
- **Formula:** Mean grade in subject $s$ taught by faculty member $F$ in section $X$.
- **Eligible Records:** Students enrolled in the specific teaching assignment matching `faculty_class_loads`.
- **Intended Roles:** Faculty (own loads only), Admin.
- **Mandatory Disclaimer:** *"Class comparisons serve student support review and curriculum pacing only. They do not measure faculty teaching efficacy."*

---

## 3. Data Completeness & Coverage Indicators

### MTR-15: Grade Coverage Rate
- **Formula:**
  $$\text{Coverage}_{\text{grade}} = \left(\frac{N_{\text{graded}}}{N_{\text{enrolled}}}\right) \times 100\%$$
- **Numerator:** Count of enrolled student-subject entries with recorded grades for the term.
- **Denominator:** Total expected student-subject enrollment slots.
- **Intended Roles:** Faculty, Admin.

---

### MTR-16: Prediction Coverage Rate
- **Formula:**
  $$\text{Coverage}_{\text{pred}} = \left(\frac{N_{\text{predicted}}}{N_{\text{active}}}\right) \times 100\%$$
- **Numerator:** Count of active students with a generated prediction for the term.
- **Denominator:** Total active student population.
- **Intended Roles:** Admin.

---

## 4. Academic Support & Intervention Lifecycle Metrics

### MTR-17: Academic Support Referral Volume
- **Concept:** Total volume of subject-level support referrals initiated by faculty.
- **Formula:**
  $$N_{\text{referrals}} = \text{COUNT}(\text{referral\_id})$$
- **Numerator:** Count of records in `support_case_referrals` created within the reporting window.
- **Denominator:** N/A (Scalar count).
- **Eligible Records:** Valid referral records in `support_case_referrals`.
- **Excluded Records:** Cancelled referrals, duplicate test records, records targeting scratch databases.
- **Missing-Data Behavior:** Defaults to $0$ if no referrals exist.
- **Role Visibility:** Admin (program-wide), Faculty (own referrals only).
- **Interpretation Warning:** Reflects proactive faculty identification of students needing assistance, not institutional failure rates.

---

### MTR-18: Academic Support Notice Volume
- **Concept:** Total volume of support referrals reviewed and formally issued as notices to students.
- **Formula:**
  $$N_{\text{notices}} = \text{COUNT}(\text{referral\_id}) \quad \text{WHERE } \text{notice\_issued\_at IS NOT NULL}$$
- **Numerator:** Count of referrals with recorded issuance timestamps.
- **Denominator:** N/A (Scalar count).
- **Eligible Records:** Referrals with valid `notice_issued_at`.
- **Excluded Records:** Draft or unissued referrals (`notice_issued_at IS NULL`).
- **Missing-Data Behavior:** Defaults to $0$.
- **Role Visibility:** Admin, Faculty.
- **Interpretation Warning:** Measures faculty action completion following initial automated risk alerts.

---

### MTR-19: Student Receipt Acknowledgment Rate
- **Concept:** Proportion of issued support notices acknowledged by students.
- **Formula:**
  $$\text{Rate}_{\text{ack}} = \left(\frac{\text{COUNT}(\text{acknowledged\_at IS NOT NULL})}{\text{COUNT}(\text{notice\_issued\_at IS NOT NULL})}\right) \times 100\%$$
- **Numerator:** Count of referrals where `student_acknowledged_at IS NOT NULL`.
- **Denominator:** Count of referrals where `notice_issued_at IS NOT NULL`.
- **Eligible Records:** Formally issued notices during the reporting period.
- **Excluded Records:** Referrals never issued as notices (`notice_issued_at IS NULL`).
- **Missing-Data Behavior:** Returns `N/A` if zero notices were issued.
- **Role Visibility:** Admin, Faculty.
- **Interpretation Warning:** Acknowledgment confirms receipt of notice only; it does not imply student admission of fault, culpability, or agreement with risk assessments.

---

### MTR-20: Mean Time to First Action ($\text{MTTFA}$)
- **Concept:** Measures operational responsiveness from initial referral creation to the first documented institutional action (e.g. notice issuance or scheduled consultation).
- **Formula:**
  $$\text{MTTFA} = \frac{\sum_{c \in C_{\text{action}}} (t_{\text{first\_action}, c} - t_{\text{created}, c})}{|C_{\text{action}}|}$$
- **Start Timestamp ($t_{\text{created}, c}$):** Exact `support_case_referrals.created_at` timestamp.
- **End Timestamp ($t_{\text{first\_action}, c}$):** Earliest timestamp of an authorized intervention event: `MIN(notice_issued_at, first_meeting_logged_at)` on referral case $c$.
- **Numerator:** Sum of elapsed calendar days (or decimal hours) elapsed between `created_at` and `first_action_at` across all eligible acted-upon cases.
- **Denominator ($|C_{\text{action}}|$):** Total count of unique eligible support cases that have at least one documented first-action timestamp within the reporting window.
- **Eligible Cases ($C_{\text{action}}$):** Non-duplicate support referral cases created in the active evaluation period having a valid, documented first-action event ($t_{\text{first\_action}} \ge t_{\text{created}}$).
- **Exclusions:** Cancelled cases (`status = 'cancelled'`), duplicate submissions, cases without a documented action (`first_action_at IS NULL`), synthetic scratch validation probes.
- **Open-Case Behavior:** Cases currently in `open`/`referred` status with NO documented action are **excluded** from the denominator and numerator. They are reported separately under an operational backlog counter ("Pending First Action"). Unacted open cases must **never** be assigned zero elapsed time.
- **Reopened-Case Behavior:** First action remains permanently anchored to the initial action taken on the referral. Reopening a previously closed case does not alter the historical `first_action_at`.
- **Missing Timestamp Behavior:** If `first_action_at` is `NULL` or corrupt/pre-dates creation ($t_{\text{action}} < t_{\text{created}}$), the record is flagged in integrity logs and excluded from the mean calculation.
- **Role Visibility:** Admin (aggregate institutional/departmental mean), Faculty (mean across own assigned load cases only).
- **Interpretation Warning:** Evaluates operational responsiveness only. Does not measure pedagogical depth, consultation quality, or student rapport.

---

### MTR-21: Mean Time to Intervention Closure ($\text{MTTC}$)
- **Concept:** Evaluates the average duration from support referral creation to documented case resolution.
- **Defensible Definition:** The mean duration from support-case creation to the latest valid closure timestamp among eligible cases that reached a documented closed state during the selected reporting period.
- **Formula:**
  $$\text{MTTC} = \frac{\sum_{c \in C_{\text{closed}}} (t_{\text{latest\_closed}, c} - t_{\text{created}, c})}{|C_{\text{closed}}|}$$
- **Start Timestamp ($t_{\text{created}, c}$):** Exact `support_case_referrals.created_at` timestamp.
- **End Timestamp ($t_{\text{latest\_closed}, c}$):** Latest documented closure timestamp (`latest_closed_at` or `resolved_at`) where the case reached `status IN ('closed', 'resolved')`.
- **Numerator:** Sum of elapsed calendar days from `created_at` to `latest_closed_at` across eligible closed cases.
- **Denominator ($|C_{\text{closed}}|$):** Exactly the count of unique eligible cases that reached a documented closed state during the selected reporting period. Does NOT include open, pending, in-progress, cancelled, or unclosed cases.
- **Eligible Cases ($C_{\text{closed}}$):** Cases with `status IN ('closed', 'resolved')` and a valid $t_{\text{latest\_closed}} \ge t_{\text{created}}$.
- **Exclusions:** Open cases, in-progress cases, cancelled cases, duplicate referral records, synthetic fixture probe cases.
- **Open-Case Behavior:** Unclosed cases are strictly excluded from the mean calculation; open cases must **never** be assigned zero closure duration. Open cases are tracked under a separate operational metric ("Active Case Age / Open Case Backlog").
- **Reopened-Case Behavior:** If a case is reopened and subsequently re-closed, the calculation uses the **latest valid closure timestamp** ($t_{\text{latest\_closed}}$) to reflect the complete active resolution lifecycle duration.
- **Missing Timestamp Behavior:** Any case marked `status = 'closed'` lacking a valid closure timestamp is excluded from the calculation and flagged for data audit.
- **Current Schema Support Status:** In the active baseline schema (migrations 000 to 010), `support_case_referrals` has `created_at`, `notice_issued_at`, and `student_acknowledged_at`. Explicit case-level closure timestamps are currently tracked in case note history or status audit logs. Therefore, MTR-21 is explicitly classified as **`PLANNED / BLOCKED`** pending schema/table verification in Phase 4, and must **not** be rendered as an established implemented analytic.
- **Role Visibility:** Admin (aggregate CCS lifecycle mean), Faculty (mean for cases initiated by the instructor).
- **Interpretation Warning:** Shorter closure time does not inherently indicate academic recovery. Complex academic difficulties require sustained multi-week counseling.
