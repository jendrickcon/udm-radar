# UDM-RADAR — Chapter 5 Objective-to-Finding Mapping & Conditional Conclusion Shells

> **Academic Guardrail:** This document establishes the formal mapping between the four specific capstone research objectives and the Chapter 5 sections (*Summary of Findings, Conclusions, and Recommendations*). In strict adherence to scientific rigor, all conclusions are formatted as **conditional analytical shells**. Researchers must never pre-write affirmative conclusions or claim longitudinal outcomes (e.g., "improved student passing rates" or "prevented dropouts") that cannot be established through a cross-sectional software product quality survey.

---

## 1. Project Objective Alignment Framework

```
Capstone Objective 1: Academic Monitoring Gaps ──► Finding 5.1.1 ──► Conclusion 5.2.1 ──► Recommendation 5.3.1
Capstone Objective 2: Role-Based Portals       ──► Finding 5.1.2 ──► Conclusion 5.2.2 ──► Recommendation 5.3.2
Capstone Objective 3: Advisory ML Component     ──► Finding 5.1.3 ──► Conclusion 5.2.3 ──► Recommendation 5.3.3
Capstone Objective 4: ISO/IEC 25010 Evaluation  ──► Finding 5.1.4 ──► Conclusion 5.2.4 ──► Recommendation 5.3.4
```

---

## 2. Objective-to-Finding Mapping Table

| Project Objective | Relevant Prototype Feature & Chapter 4 Evidence | Chapter 5 Finding Anchor (5.1) | Conditional Conclusion Shell (5.2) | Actionable Recommendation (5.3) |
| :--- | :--- | :--- | :--- | :--- |
| **Objective 1:** Identify academic monitoring and risk-identification challenges in the BSIT program. | Legacy grade visibility friction, triage alert clarity, supportive linguistic framing, and missing-data handling. | **Finding 5.1.1:** Summary of stakeholder feedback regarding operational grade visibility and early warning awareness. | *“If supported by the Chapter 4 results, conclude whether the prototype addressed the identified monitoring gaps without introducing punitive friction.”* | Formalize institutional faculty advising protocols and standardized consultation review schedules. |
| **Objective 2:** Design and develop role-based web portals (Student, Faculty, Admin) for academic decision-support. | Usability (US01, US02), Functional Completeness (FS01, FS05), and Security (SE02, SE06) ratings across all three portals. | **Finding 5.1.2:** Summary of role-specific portal usability, interface navigation, and access control evaluation. | *“If supported by the Chapter 4 results, conclude whether dedicated role-based portals provided appropriate operational visibility.”* | Expand responsive mobile layout support across departmental dashboards and enhance print export options. |
| **Objective 3:** Implement an advisory predictive model based on historical academic records and preliminary grades. | Clear explanation of prediction basis (history + Prelim), transparency that Midterm is excluded from active model, provisional labeling of partial data, missing grades handled as unavailable rather than zero, supportive risk presentation, and understanding that predictions are advisory. | **Finding 5.1.3:** Summary of user understanding and technical verification that predictive triage operates as advisory decision support within its synthetic data boundary. | *“If supported by the Chapter 4 results and technical verification, conclude whether the advisory predictive triage offered transparent, non-punitive decision support within its synthetic data boundary.”* | Undertake longitudinal institutional model calibration once registrar data privacy approvals permit. |
| **Objective 4:** Evaluate the software product quality of UDM-RADAR based on the ISO/IEC 25010 standard. | Empirical composite weighted means across the 5 characteristics and overall Grand Master Composite Mean. | **Finding 5.1.4:** Comprehensive numerical and verbal summary of the five ISO/IEC 25010 quality characteristics. | *“Conclude whether the evaluated software product quality of UDM-RADAR met the institutional target based on the verified composite rating.”* | Prepare a supervised institutional pilot deployment within the College of Computer Studies. |

---

## 3. Chapter 5 Conditional Writing Templates

### 5.1 Summary of Findings (Section 5.1 Narrative Shell)

```markdown
### 5.1 Summary of Findings

Based on the empirical evaluation administered to [Total N] stakeholders across the BSIT Student ([N_Students]), CCS Academic User ([N_Academic]), and IT Practitioner ([N_IT]) cohorts, the following findings emerged:

1. **Identification of Academic Monitoring Gaps (Objective 1):**
   [If supported by survey evidence, state whether evaluators confirmed that automated triage indicators and centralized grade displays resolved information delays experienced in manual advising.] Evaluators rated the clarity of supportive risk communication at a weighted mean of [WM_US05] ([Interpretation_US05]).

2. **Role-Based Portal Implementation (Objective 2):**
   The implementation of dedicated portals for Students, Faculty, and Administrators achieved a composite Usability rating of [WM_US] ([Interpretation_US]) and Functional Completeness rating of [WM_FS01] ([Interpretation_FS01]). Cross-group comparison across shared items (Table 4.7) revealed a shared composite mean of [Pooled_Shared_WM] ([Interpretation_Shared]), confirming that [summarize observed role separation effectiveness].

3. **Advisory Predictive Component & Data Governance (Objective 3):**
   Evaluators engaged with the prototype's predictive triage under transparent disclosures:
   - Prediction basis is explicitly disclosed as academic history and preliminary grades;
   - Midterm records are clearly marked as available for faculty review but excluded from the active trained model inputs;
   - Incomplete records and non-numeric grades (`INC`) did not inject unintended zero values (Item RE03: [WM_RE03], [Interpretation_RE03]);
   - Risk indicators remained strictly advisory, with the Grade Goal Calculator evaluated at [WM_FS03] ([Interpretation_FS03]).
   - Predictive model regression metrics (MAE, RMSE, R²) are to be verified against the committed `python_ml/model_metrics.json` manifest when generated, recording the model artifact hash, metrics-manifest hash, evaluation dataset boundary, and verification date independently from survey perception.

4. **ISO/IEC 25010 Software Product Quality Evaluation (Objective 4):**
   The overall software quality of UDM-RADAR achieved a Grand Master Composite Weighted Mean of [Grand_Pooled_WM], verbally interpreted as [Grand_Interpretation]. The five characteristics ranked as follows:
   - Rank 1: [Characteristic_1] ([WM_1], [Interpretation_1])
   - Rank 2: [Characteristic_2] ([WM_2], [Interpretation_2])
   - Rank 3: [Characteristic_3] ([WM_3], [Interpretation_3])
   - Rank 4: [Characteristic_4] ([WM_4], [Interpretation_4])
   - Rank 5: [Characteristic_5] ([WM_5], [Interpretation_5])
```

---

### 5.2 Conclusions (Section 5.2 Narrative Shell)

```markdown
### 5.2 Conclusions

Based on the empirical findings, the following conclusions are drawn with respect to the project objectives:

1. **Academic Decision Support Without Punitive Friction:**
   If supported by Chapter 4 results, conclude whether UDM-RADAR modernizes academic monitoring by presenting grade trends and triage indicators using supportive, non-punitive language that encourages student advising engagement.

2. **Appropriate Operational Partitioning Across Portals:**
   If supported by Chapter 4 results, conclude whether the tripartite portal architecture provides tailored, role-appropriate workflows that segregate administrative oversight from student self-monitoring while preserving account accountability.

3. **Transparency and Governance of Advisory Machine Learning:**
   If supported by Chapter 4 results and verified technical checks, conclude whether the advisory predictive architecture maintains ethical transparency by clearly disclosing its reliance on Prelim and historical features, labeling incomplete data provisionally, and decoupling decision support from binding academic sanctions.

4. **Overall Compliance with ISO/IEC 25010 Quality Standards:**
   If supported by Chapter 4 composite results, conclude whether UDM-RADAR satisfies the institutional software product quality criteria for an undergraduate capstone prototype, achieving a verified overall rating of [Grand_Interpretation].
```

---

### 5.3 Recommendations (Section 5.3 Narrative Shell)

```markdown
### 5.3 Recommendations

To advance the prototype toward potential institutional deployment, the researchers recommend:

1. **Institutional Adoption Pre-conditions:**
   - Formalize institutional data-sharing agreements with the University Registrar to enable model calibration on real, historical anonymized academic records under full privacy compliance.
   - Establish college-level Faculty Advising Standard Operating Procedures defining how triage indicators translate into structured mentoring sessions.

2. **Technical & Architectural Enhancements:**
   - Incorporate multi-factor authentication (MFA) and expanded institutional Single Sign-On (SSO) integration.
   - Integrate automated curriculum mapping for shifting and irregular students across multiple college degree programs.
   - Expand progressive web app (PWA) caching for improved offline mobile accessibility.

3. **Policy & Ethical Data Governance:**
   - Maintain strict institutional boundaries ensuring automated projections remain exclusively advisory and are never utilized as automated determinants for academic probation, scholarship disqualification, or retention termination.
   - Regularly audit the decision tree model against updated grading policies and curriculum revisions to prevent algorithmic drift.
```
