# Data Privacy, Academic Ethics, and Fairness Boundaries — UDM-RADAR

**Document Version:** 1.0.0 (Post-Defense Revision)
**Accepted Baseline SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Applicability:** Universal architectural and operational constraint for all agents, proponents, and developers.

---

## 1. Prototype Scope and Synthetic Data Boundary

1. **Synthetic Nature:**
   - Under University Registrar data privacy policies, the prototype operates exclusively on **synthetic academic records** authorized by the Capstone Adviser.
   - Zero genuine student transcripts, real student numbers, personal contact details, or official faculty evaluations are stored or distributed in version control.
2. **Mandatory Interface Labeling:**
   - Every public, administrative, faculty, and student view, report, or export must visibly present the standard disclosure:
     > `Prototype performance on synthetic academic records`
3. **Synthetic Namespace Standards:**
   - Synthetic student numbers must use reserved prefixes (e.g. `24-00-xxx`).
   - Synthetic emails must adhere to the `@synthetic.udm.edu.ph` domain.
   - Synthetic student and faculty names must use clearly demonstrative labels (e.g. `DemoStudent Alpha Reyes`, `DemoFaculty Academic Santos`).

---

## 2. Non-Punitive Academic Decision Support

1. **Advisory Role:**
   - UDM-RADAR is a decision-support prototype. Its outputs do not constitute official registrar evaluations, binding disciplinary actions, academic probation, or disqualification from retention or scholarship subsidies.
2. **Strict Terminology Separation:**
   Contributors and automated agents must never conflate these five distinct concepts:
   - **Subject Pass/Fail:** Earning a passing grade ($\ge 1.00$ or `P`) versus numeric failure ($0.00$).
   - **Prototype Academic Risk Classification:** Advisory triage category (`HIGH`, `MODERATE`, `LOW`) based on projected or cumulative GWA.
   - **Official Academic Standing:** Official institutional standing determined exclusively by university policy, the College Dean, and the Registrar (`Regular` vs `Irregular`).
   - **Curriculum Pacing:** Normal progress through the approved sequence of subjects.
   - **Enrollment Qualification & Subsidies:** Institutional retention and tuition subsidy eligibility.
3. **Forbidden Punitive Terms:**
   Interface text, notices, reports, and documentation must NEVER contain:
   - Strike systems or disciplinary count-ups.
   - Dismissal countdowns or expulsion warnings.
   - Scholarship-loss predictions or allowance-cancellation threats.
   - Deficit-based characterizations ("hopeless student", "chronically failing cohort").

---

## 3. Faculty Fairness and Non-Evaluative Analytics

1. **Strict Prohibition of Faculty Rankings:**
   - Dashboards and reports must **never rank faculty members**, score teaching effectiveness, or present "best and worst professor" comparisons.
   - Class-level performance metrics grouped by faculty assignment serve exclusively to analyze curriculum pacing, workload, and student support needs.
2. **Mandatory Non-Evaluative Disclaimer:**
   Whenever class performance is filtered or compared by faculty assignment, the interface must display:
   > *Disclaimer: Academic comparisons across faculty assignments are intended for curriculum review and student support pacing. They do not measure teaching quality or faculty competence. Variances reflect class composition, prerequisite backgrounds, and section scheduling conditions.*
3. **Role-Restricted Scope:**
   - Faculty members can only inspect class averages, grade distributions, and student rosters for sections to which they are assigned in `faculty_class_loads`.
   - Cross-faculty comparisons are restricted to authorized administrators.

---

## 4. Intervention Ethics and Receipt Acknowledgment

1. **Human-in-the-Loop Sovereignty:**
   - Automated predictions may generate internal support alerts for faculty review, but must **never dispatch unilateral notices to students** without faculty review and discretion.
2. **Receipt Acknowledgment Standard:**
   - When a student acknowledges an Academic Support Notice, the interface and database record receipt only:
     - Field: `student_acknowledged_at` (timestamp).
     - Copy: *"I acknowledge receipt of this academic support notice."*
   - Copy must NEVER require agreement, fault admission, or waiver of academic appeal rights.

---

## 5. Private PEF/CC Assets and Document Security

1. **Private Template Boundary:**
   - The original Curriculum Checklist `.xlsx` workbooks containing registrar formulas and historical institutional layouts must remain outside public repositories.
   - The legal folio PDF generation engine uses an internal private template (v0.2-r6) with verified SHA-256 integrity (`5941b7b36c5bbee585f32162397de2cd18e0279171cdd6470578e389b18e43ec`).
2. **Export Integrity:**
   - All PDF and CSV exports are logged in `export_audit_logs` recording user ID, report type, filter parameters, and row count.
   - Export generation failures must record sanitized, user-safe error reasons (`failure_reason`) without leaking server paths or credentials.
