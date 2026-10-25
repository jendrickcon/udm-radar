# Architectural Decision Log (ADR) — Post-Defense Revision

**Document Version:** 1.0.0
**Accepted Baseline Git SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Purpose:** Formal log of structural, statistical, and institutional architectural decisions.

---

## ADR-01: Discrete Point Delta ($\Delta\text{GWA}$) as Primary Student Progression Indicator
- **Context:** Panel requested intuitive semester-over-semester academic progression metrics for students.
- **Alternatives Considered:**
  1. *Relative Percentage Change alone ($+12.4\%$):* Misleading because a $0.25$ jump on a $1.50$ baseline produces a very different percentage than on a $3.00$ baseline.
  2. *Logarithmic Velocity ($\ln(\text{GWA}_t/\text{GWA}_{t-1})$):* Mathematically sound for research, but confusing to undergraduate students.
  3. *Discrete Point Delta ($\Delta\text{GWA} = \text{Current} - \text{Previous}$):* Immediately maps to the official institutional $4.00\text{--}1.00$ point grading scale.
- **Decision:** Adopt discrete point delta as the primary user-facing indicator. Provide relative percentage change as secondary tooltip context; retain logarithmic change purely for backend research.
- **Consequences:** Intuitive student interface; consistent directionality (positive delta = academic improvement).

---

## ADR-02: Empirical Residual Error Margin vs Gaussian Confidence Intervals
- **Context:** Panel observed that presenting single-point forecasts (e.g. `2.15`) gives an illusion of exact foresight.
- **Alternatives Considered:**
  1. *Standard 95% Gaussian Confidence Intervals:* Assumes normal i.i.d. residual distribution, which Decision Tree regressors on non-linear academic data do not satisfy.
  2. *Empirical Residual Error Margin based on test MAE:* Sets bounds to $[\widehat{\text{GWA}} - \text{MAE}, \; \widehat{\text{GWA}} + \text{MAE}]$, clamped to the valid domain $[1.00, 4.00]$.
- **Decision:** Use the empirical test MAE margin ($\pm 0.15$). Label it clearly as an advisory prediction range, NOT a confidence interval.
- **Consequences:** Defensible mathematical claims in manuscript and defense follow-ups.

---

## ADR-03: Prohibition of Cross-Faculty Evaluative Rankings
- **Context:** Panel requested multi-dimensional filtering by faculty assignment to inspect subject-level grade distributions.
- **Alternatives Considered:**
  1. *Faculty Leaderboards / Performance Averages:* Highly prone to misinterpretation as teacher evaluations.
  2. *Unranked Class Pattern Slicing with Mandatory Disclaimer:* Allows administrators to review section pacing while legally prohibiting evaluative comparisons.
- **Decision:** Strictly forbid faculty rankings. Filter by faculty assignment serves load review only. Include mandatory non-evaluative disclaimer on all assignment-filtered views.
- **Consequences:** Compliance with academic fairness principles and faculty union guidelines.

---

## ADR-04: Non-Coercion of Missing Grades to Failure
- **Context:** Incomplete academic records (unrecorded prelims, missing finals, pending INC) caused bugs where missing data was treated as `0.00` or failure.
- **Alternatives Considered:**
  1. *Defaulting missing grades to 0.00:* Corrupts student GWA calculations and falsely inflates High Risk triage.
  2. *Explicit NULL / In-Progress Representation:* Renders missing terms as `—` or `Pending` and excludes them from numeric denominators.
- **Decision:** Missing grades must remain `NULL` and render as neutral indicators. Term GWA remains `Pending` until all enrolled subjects are recorded.
- **Consequences:** Protects academic standing calculations from silent distortion.

---

## ADR-05: Receipt-Only Acknowledgment of Academic Notices
- **Context:** When high-risk students receive academic support notices, early designs asked them to "agree" to the notice.
- **Alternatives Considered:**
  1. *Agreement / Fault Admission:* Puts students in legal/disciplinary jeopardy.
  2. *Receipt Acknowledgment Timestamp:* Records `student_acknowledged_at` confirming that the notice was seen, without fault admission.
- **Decision:** Use receipt acknowledgment only (*"I acknowledge receipt of this academic support notice"*).
- **Consequences:** Ethical human-in-the-loop intervention supporting student welfare.

---

## ADR-06: Reconciled Post-010 Schema-Only Baseline with Starter Demo Seed
- **Context:** Pre-WP-9 `database/udm_radar.sql` was a 14,224-line dump mixing legacy schema and populated records.
- **Alternatives Considered:**
  1. *Continue replaying migrations 000-010 on a 10-table dump:* Fragile, slow, prone to duplicate alter errors.
  2. *Reconcile baseline dump to 18 tables post-010 (schema-only) and provide a separate synthetic starter seed:* Single-step clean setup with built-in safety refusal.
- **Decision:** Commit `b7a24a3` reconciled `database/udm_radar.sql` to post-010 schema-only (397 lines). PR #60 added `database/seeds/001_synthetic_demo.sql` with a safety refusal guard.
- **Consequences:** Fast, modular, fail-safe member onboarding without sensitive data leakage.
