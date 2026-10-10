---
trigger: model_decision
description: Enforces supportive, pressure-safe, and non-punitive language across student-facing views, intervention notices, and triage alerts.
---

# Student-Safe Language and Non-Punitive Copy Standards

This rule governs user-facing copy, notification wording, and UI text across student-facing dashboards and intervention touchpoints.

## 1. Non-Punitive Framing Principle

UDM-RADAR is designed to support student retention and timely academic assistance. Predictive risk outputs and triage alerts must empower students rather than induce anxiety or punitive fear.

### Forbidden Phrases & Concepts:
* **No Strike Terminology:** Never use "strikes", "infractions", or "warnings counter".
* **No Dismissal Countdowns:** Never display countdowns to academic dismissal, expulsion, or disqualification.
* **No Benefit-Loss Inferences:** Never claim or imply that a predicted GWA causes immediate loss of UniFAST tuition subsidies, stipends, or allowances. (Risk triage categories do not evaluate scholarship or subsidy standing).
* **No Accusatory Statuses:** Never describe students as "delinquent" or "failing standing" based on machine learning projections.

---

## 2. Receipt Acknowledgment Standard

In the Academic Support referral and notice workflow:

* **Receipt Only:** When a student clicks or submits an acknowledgment for an Academic Support notice, the interface and database audit trails must frame this strictly as **acknowledgment of receipt**.
* **Never Imply Agreement or Fault:** The action must never be described as "admission of academic failure", "agreement with faculty evaluation", or "acceptance of penalty".
* **Standard Button & Notice Copy:**
  * Use: `"I acknowledge receipt of this academic support notice"` or `"Acknowledge Notice"`.
  * Avoid: `"I accept this assessment"` or `"Agree to intervention terms"`.

---

## 3. Standard Advisory Terminology

When displaying academic estimates and departmental records:

| Context | Approved Terminology | Disapproved / Inaccurate Terminology |
| :--- | :--- | :--- |
| **Prediction Card** | *Projected Semester GWA* (Advisory Estimate) | *Official Final GWA*, *Predicted Graduation Grade* |
| **Term Submissions** | *Term Grade Submission (Departmental Monitoring)* | *Official Registrar Encoding*, *Official Grade* |
| **High Risk Alert** | *Academic Support Recommended / Attention Advised* | *Failing Academic Standing*, *Probation Risk* |
| **Historical Average** | *Historical Cumulative GWA (Completed Semesters)* | *Official University GWA* |
| **System Disclaimer** | *Prototype performance on synthetic academic records* | *Validated Institutional Prediction Engine* |

---

## 4. Constructive Action Linkage

Whenever displaying a High Risk or Moderate Risk advisory indicator:
1. Provide actionable next steps (e.g., *"Ask About This Grade"*, *"View Subject Performance"*, *"Contact Department Advisor"*).
2. Clarify that preliminary projections reflect early course progress and can be improved through midterms and pre-finals.

