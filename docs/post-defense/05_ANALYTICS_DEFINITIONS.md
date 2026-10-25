# Analytical Definitions and Mathematical Specifications — UDM-RADAR

**Document Version:** 1.0.0 (Post-Defense Revision)
**Accepted Baseline SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Standard Scale:** Universidad de Manila $4.00\text{--}1.00$ discrete point scale ($4.00$ = Highest/Excellent; $1.00$ = Minimum Passing; $0.00$ = Numeric Failure).

---

> [!CAUTION]
> **CRITICAL STATISTICAL WARNINGS FOR CONTRIBUTING AGENTS & MEMBERS:**
> 1. **Do NOT call $R^2$ an "accuracy percentage".** $R^2$ (coefficient of determination) measures the proportion of variance in numerical GWA explained by the regression tree model relative to a mean baseline. It is a goodness-of-fit indicator, not a percentage of correct predictions.
> 2. **Do NOT call an empirical error margin a "confidence interval".** A confidence interval requires verified asymptotic normality and independent observations. UDM-RADAR uses an **empirical prediction margin** derived from test Mean Absolute Error ($\text{MAE}$).
> 3. **Scale Directionality:** Higher numerical points indicate stronger academic achievement ($4.00 > 1.00$). A positive difference ($+0.25$) denotes academic improvement; a negative difference ($-0.15$) denotes a lower point result.

---

## 1. Student-Level Metrics

### 1. Current Term GWA
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

### 2. Previous Comparable GWA
- **Formula:** $\text{GWA}_{\text{prev}} = \text{GWA}$ of the most recent completed regular academic semester preceding the current term.
- **Numerator / Denominator:** Computed using standard unit-weighted GWA formula applied to the preceding regular term.
- **Eligible Records:** Completed regular semesters (Semester 1 or 2).
- **Excluded Records:** Current in-progress semester, terms with zero completed numeric grades, summer terms (unless explicitly comparing summer-to-summer).
- **Missing-Data Behavior:** Returns `NULL` / `First Term on Record` for first-year students in their first semester.
- **Intended Roles:** Student, Admin.
- **Interpretation Warning:** Trajectory comparison is valid only when curriculum loading is reasonably comparable.

---

### 3. Point Change ($\Delta\text{GWA}$)
- **Formula:**
  $$\Delta\text{GWA} = \text{Current/Estimated GWA} - \text{Previous Comparable GWA}$$
- **Numerator / Denominator:** Direct subtraction between two scalar point values.
- **Eligible Records:** Students with both a current/estimated GWA and a valid previous comparable GWA.
- **Excluded Records:** Students in their first semester of enrollment.
- **Missing-Data Behavior:** Displays an em dash (`—`) when previous comparable GWA is unavailable.
- **Intended Roles:** Student, Admin.
- **Interpretation Warning:** Positive delta ($> 0$) represents an improvement in point achievement. Negative delta ($< 0$) represents a drop.

---

### 4. Relative Percentage Change
- **Formula:**
  $$\text{Relative Change (\%)} = \left(\frac{\text{Current/Estimated GWA} - \text{Previous GWA}}{\text{Previous GWA}}\right) \times 100\%$$
- **Numerator:** $\Delta\text{GWA}$.
- **Denominator:** Previous Comparable GWA.
- **Eligible Records:** Same as Point Change.
- **Excluded Records:** First-term students, cases where previous GWA is missing or invalid.
- **Missing-Data Behavior:** Displays `N/A`.
- **Intended Roles:** Supporting tooltip for Student and Admin views.
- **Interpretation Warning:** Secondary informational metric only. A 10% point change on a 1.50 base represents a different qualitative shift than on a 3.00 base.

---

### 5. Logarithmic Change
- **Formula:**
  $$\text{Log Change} = \ln\left(\frac{\text{Current/Estimated GWA}}{\text{Previous GWA}}\right)$$
- **Numerator / Denominator:** Natural log of the ratio of current to previous GWA.
- **Eligible Records:** Valid numeric positive GWAs.
- **Excluded Records:** Zero, negative, or undefined values.
- **Missing-Data Behavior:** Excluded from user-facing UI.
- **Intended Roles:** Internal research analysis and manuscript technical tables only.
- **Interpretation Warning:** Strictly prohibited as a primary user-facing dashboard label.

---

### 6. Estimated GWA (Decision Tree Output)
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

### 7. Empirical Prediction Range (Residual-Based Interval)
- **Formula:**
  $$\text{Lower Bound} = \max(1.00, \; \widehat{\text{GWA}} - \text{MAE}_{\text{test}})$$
  $$\text{Upper Bound} = \min(4.00, \; \widehat{\text{GWA}} + \text{MAE}_{\text{test}})$$
  where $\text{MAE}_{\text{test}}$ is the recorded test Mean Absolute Error from `python_ml/model_metrics.json` (currently $\pm 0.15$).
- **Eligible Records:** Records with a valid `decision_tree` prediction.
- **Excluded Records:** Deterministic calculation fallbacks (which carry zero model variance).
- **Missing-Data Behavior:** In fallback mode, the range collapses to the exact deterministic calculation.
- **Intended Roles:** Student, Admin.
- **Interpretation Warning:** Represents typical empirical residual variation on test cohorts, not an exact theoretical confidence interval.

---

### 8. Advisory Risk Category
- **Formula:**
  $$\text{Risk}(\text{GWA}) = \begin{cases} \text{HIGH}, & \text{if } \text{GWA} < 1.75 \\ \text{MODERATE}, & \text{if } 1.75 \le \text{GWA} \le 2.49 \\ \text{LOW}, & \text{if } \text{GWA} \ge 2.50 \end{cases}$$
- **Input:** Current projected GWA or historical cumulative GWA.
- **Eligible Records:** Students with a calculable or estimated GWA.
- **Excluded Records:** Students with missing grade inputs (`No Data`).
- **Missing-Data Behavior:** Never defaults to `LOW` or `HIGH`; displays `No Data`.
- **Intended Roles:** Student, Faculty, Admin.
- **Interpretation Warning:** Non-punitive institutional risk triage. Does NOT determine probation, retention, or dismissal.

---

## 2. Institutional & Cohort Comparative Aggregates

### 9. Average GWA by Semester
- **Formula:**
  $$\overline{\text{GWA}}_{\text{sem}} = \frac{\sum_{j=1}^{M} \text{GWA}_j}{M}$$
  where $\text{GWA}_j$ is the completed term GWA of student $j$, and $M$ is the number of eligible students.
- **Numerator:** Sum of completed GWAs for the semester.
- **Denominator:** Count of eligible students ($M$) with calculable term GWAs.
- **Eligible Records:** Students enrolled in the target semester with recorded completed final grades.
- **Excluded Records:** Incomplete term records, dropped students.
- **Missing-Data Behavior:** Reported along with Grade Coverage Rate ($M / N_{\text{total}}$).
- **Intended Roles:** Admin.
- **Interpretation Warning:** Do not sum student GWAs ("Total GWA"); always report arithmetic mean and student count.

---

### 10. Average GWA by School Year
- **Formula:**
  $$\overline{\text{GWA}}_{\text{SY}} = \frac{\sum \text{GWA}_{\text{Sem1}} + \sum \text{GWA}_{\text{Sem2}}}{M_{\text{Sem1}} + M_{\text{Sem2}}}$$
- **Numerator:** Sum of all completed term GWAs recorded across the school year.
- **Denominator:** Total number of student-term completed GWA instances.
- **Eligible / Excluded Records:** Same as Semester Average.
- **Intended Roles:** Admin.

---

### 11. Average GWA by Year Level
- **Formula:** Mean completed or projected GWA across all students belonging to curriculum Year Level $k \in \{1, 2, 3, 4\}$.
- **Numerator:** Sum of GWAs of students where `student_profiles.year_level = k`.
- **Denominator:** Total eligible students in Year Level $k$.
- **Intended Roles:** Admin.
- **Interpretation Warning:** Differences across year levels often reflect curriculum difficulty and specialization tracks, not student capability shifts.

---

### 12. Subject Average Grade
- **Formula:**
  $$\overline{G}_{\text{subject}} = \frac{\sum_{k=1}^{S} G_k}{S}$$
  where $G_k$ is the final point grade in subject $s$, and $S$ is the count of numeric passing and failing point grades.
- **Numerator:** Sum of discrete numeric final point grades ($4.00\text{--}1.00$, $0.00$).
- **Denominator:** Total recognized numeric outcomes ($S = \text{Passed} + \text{Failed}$).
- **Excluded Records:** `INC`, `DRP`, `P`.
- **Intended Roles:** Faculty, Admin.

---

### 13. Section Average Grade
- **Formula:** Mean completed or preliminary grade across all students enrolled in section $X$ for a given subject.
- **Denominator:** Number of students in section $X$ with recorded grades.
- **Intended Roles:** Faculty, Admin.

---

### 14. Faculty-Subject Class Average
- **Formula:** Mean grade in subject $s$ taught by faculty member $F$ in section $X$.
- **Eligible Records:** Students enrolled in the specific teaching assignment matching `faculty_class_loads`.
- **Intended Roles:** Faculty (own loads only), Admin.
- **Mandatory Disclaimer:** *"Class comparisons serve student support review only and do not measure faculty teaching efficacy."*

---

## 3. Data Completeness & Coverage Indicators

### 15. Grade Coverage Rate
- **Formula:**
  $$\text{Coverage}_{\text{grade}} = \left(\frac{N_{\text{graded}}}{N_{\text{enrolled}}}\right) \times 100\%$$
- **Numerator:** Count of enrolled student-subject entries with recorded grades for the term.
- **Denominator:** Total expected student-subject enrollment slots.
- **Intended Roles:** Faculty, Admin.

---

### 16. Prediction Coverage Rate
- **Formula:**
  $$\text{Coverage}_{\text{pred}} = \left(\frac{N_{\text{predicted}}}{N_{\text{active}}}\right) \times 100\%$$
- **Numerator:** Count of active students with a generated prediction for the term.
- **Denominator:** Total active student population.
- **Intended Roles:** Admin.

---

## 4. Academic Support & Intervention Lifecycle Metrics

### 17. Referral Count
- Total count of subject-level support referrals initiated by faculty (`support_case_referrals`).

### 18. Notice Count
- Total count of referrals where `notice_issued_at IS NOT NULL`.

### 19. Acknowledgment Count
- Total count of referrals where `student_acknowledged_at IS NOT NULL`.

### 20. Time to First Action & Time to Closure
- **Time to First Action (Faculty Responsiveness):**
  $$\Delta t_{\text{action}} = \text{notice\_issued\_at} - \text{created\_at}$$
- **Time to Closure (Case Resolution):**
  $$\Delta t_{\text{closure}} = \text{closed\_at} - \text{created\_at}$$
- **Computation:** Expressed in business days, excluding records where status remains pending.
- **Intended Roles:** Admin, Faculty (own assigned cases only).
