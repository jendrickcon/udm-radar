# UDM-RADAR — ISO/IEC 25010 Software Product Quality Evaluation Instrument

> **System Nature & Boundary:** Universidad de Manila — Risk Analytics & Decision-support for Academic Records (UDM-RADAR) is an academic decision-support prototype. Due to Registrar data privacy restrictions, the system operates on synthetic academic records authorized by the Capstone Adviser. Predictive outputs provide non-punitive academic decision support and do not constitute official registrar evaluations, binding disciplinary actions, or official Latin honor determinations.

---

## 1. Study Overview & Evaluation Objectives

This evaluation instrument gathers empirical stakeholder feedback to assess the software product quality of the **UDM-RADAR** web application based on the **ISO/IEC 25010** software quality model. Evaluators assess observable system behaviors following a structured hands-on walkthrough of the prototype.

Participation is entirely voluntary. All responses will be processed in aggregate to inform Chapter 4 (*Results and Discussion*) and Chapter 5 (*Summary of Findings, Conclusions, and Recommendations*) of the capstone research manuscript.

---

## 2. Scales: Response Options vs. Weighted-Mean Interpretations

To maintain methodological rigor, this study strictly separates the **evaluator agreement response scale** (used by respondents when completing the questionnaire) from the **statistical weighted-mean interpretation scale** (used by researchers to interpret computed composite scores).

### 2.1 Evaluator Response Scale (Clickable Survey Choices)
Respondents rate each statement using a five-point Likert agreement scale reflecting their direct observation and experience:

| Point | Response Choice | Criteria |
| :---: | :--- | :--- |
| **5** | **Strongly Agree** | The respondent completely agrees that the observable software behavior fulfills the stated criterion without defect or ambiguity. |
| **4** | **Agree** | The respondent agrees that the software behavior fulfills the stated criterion with only minor, non-disruptive room for refinement. |
| **3** | **Neither Agree nor Disagree** | The respondent is neutral, or observed that the software behavior meets baseline needs with notable room for improvement. |
| **2** | **Disagree** | The respondent disagrees that the software behavior adequately fulfills the stated criterion. |
| **1** | **Strongly Disagree** | The respondent strongly disagrees, having observed that the software behavior fails or is completely unsatisfactory. |

### 2.2 Weighted-Mean Interpretation Scale (Analytical Synthesis)
The following verbal interpretation intervals apply strictly to computed weighted-mean scores:

| Numerical Interval | Verbal Interpretation | Approval Status |
| :---: | :--- | :--- |
| **4.30 – 5.00** | **Excellent** | Provisional pending adviser confirmation *(Owner constraint)* |
| **3.50 – 4.29** | **Very Satisfactory** | Provisional pending adviser confirmation *(Owner constraint)* |
| **2.70 – 3.49** | **Satisfactory** | Provisional pending adviser confirmation |
| **1.90 – 2.69** | **Fair** | Provisional pending adviser confirmation |
| **1.00 – 1.89** | **Poor** | Provisional pending adviser confirmation |

*Note: Evaluators never see the terms "Excellent" through "Poor" as response choices for agreement statements; these terms apply exclusively to the statistical interpretation of computed weighted means in Chapter 4.*

---

## 3. Instrument Routing & Question Visibility Architecture

The master questionnaire contains **25 unique Likert statements**. To maintain construct validity, respondents are only required to evaluate observable features relevant to their stakeholder role. Hidden items are excluded from a group's denominator and are never treated as missing responses:

- **15 Shared Items:** Answered by all respondents (`FS01`–`FS02`, `PE01`–`PE02`, `US01`–`US03`, `US06`, `RE01`–`RE02`, `RE04`, `SE01`–`SE03`, `SE06`).
- **BSIT Students:** 15 Shared + 4 Student-specific (`FS03`, `PE03`, `US04`, `US05`) = **19 required Likert items**.
- **CCS Academic Users:** 15 Shared + 3 Academic-specific (`FS04`, `PE04`, `SE04`) = **18 required Likert items**.
- **IT Practitioners / Experts:** 15 Shared + 3 IT-specific (`FS05`, `RE03`, `SE05`) = **18 required Likert items**.

---

## 4. Part I: Evaluator Demographics & Verification

1. **Informed Consent Gate (Required):**
   - [ ] *I have read the Data Privacy Statement. I voluntarily agree to participate in this evaluation and authorize the aggregated processing of my feedback for academic capstone research.*
2. **Respondent Group Classification (Required):**
   - ( ) **BSIT Student** *(Enrolled in the BSIT program)*
   - ( ) **CCS Academic User** *(Faculty, Academic Adviser, Department Administrator, or Academic Staff)*
   - ( ) **IT Practitioner or Expert** *(Software Engineer, Systems Analyst, QA, Security Specialist, or Academic IT Personnel)*
3. **Walkthrough Confirmation (Required):**
   - [ ] *I confirm that I have interacted with the UDM-RADAR prototype or completed the guided demonstration walkthrough prior to completing this evaluation.*

---

## 5. Part II: Core Shared Evaluation Items (All Respondents — 15 Items)

*Response Scale: `5 = Strongly Agree`, `4 = Agree`, `3 = Neither Agree nor Disagree`, `2 = Disagree`, `1 = Strongly Disagree`*

### Characteristic 1: Functional Suitability
* **FS01 (Functional Completeness):** The system provides the necessary functions to view and monitor recorded academic grades and status.
* **FS02 (Functional Correctness):** The displayed GWA matched the expected result in the provided evaluation scenario.

### Characteristic 2: Performance Efficiency
* **PE01 (Time Behaviour):** The application pages and dashboards loaded promptly during the evaluation session.
* **PE02 (Capacity):** The system displayed multi-subject grade lists without noticeable slowdown.

### Characteristic 3: Usability
* **US01 (Appropriateness Recognizability):** The system interface clearly indicates the purpose and role of the portal being accessed.
* **US02 (Learnability):** The layout, navigation menus, and information cards are straightforward to learn and navigate.
* **US03 (User Error Protection):** The system provides clear feedback or notices when input is invalid or an action cannot be completed.
* **US06 (Accessibility):** The application provides useful display options, including text-size adjustment, Light and Dark appearances, reduced-motion preference, and optional link underlining.

### Characteristic 4: Reliability
* **RE01 (Maturity):** The application performed its functions consistently throughout the evaluation session without crashing.
* **RE02 (Availability):** Application pages, navigation routes, and dashboard views were accessible whenever requested.
* **RE04 (Recoverability):** The system allows users to continue normal operation after entering invalid input or canceling an action.

### Characteristic 5: Security
* **SE01 (Authenticity):** Access to the portal requires valid user authentication credentials.
* **SE02 (Confidentiality):** The system restricts users to information appropriate to their assigned role.
* **SE03 (Integrity):** Academic records and displayed grades remain protected against unauthenticated or unauthorized modification.
* **SE06 (Accountability):** The system clearly identifies the currently signed-in account and portal role during use.

---

## 6. Part III-A: Student-Specific Evaluation Items (4 Items)

*Visible and required only for respondents classified as **BSIT Student** (Path Total: 19 Items)*

* **FS03 (Functional Appropriateness):** The Grade Goal Calculator helps in estimating the term grade required to pursue a target academic mark.
* **PE03 (Time Behaviour):** The Grade Goal Calculator recalculated required grades immediately upon changing the target point.
* **US04 (User Interface Aesthetics):** The system clearly presented the configured academic-distinction guidance ranges.
* **US05 (Operability):** Academic risk indicators are expressed in clear, supportive, and non-punitive language.

---

## 7. Part III-B: CCS Academic User-Specific Evaluation Items (3 Items)

*Visible and required only for respondents classified as **CCS Academic User** (Path Total: 18 Items)*

* **FS04 (Functional Appropriateness):** The academic support workflow allows recording consultation notes and intervention reasons for students needing advising.
* **PE04 (Time Behaviour):** The class section roster and grade monitoring views loaded section records without disruptive latency during review.
* **SE04 (Non-repudiation):** Where acknowledgement records are available, the system preserves evidence of the recorded action and time.

---

## 8. Part III-C: IT Practitioner & Expert-Specific Evaluation Items (3 Items)

*Visible and required only for respondents classified as **IT Practitioner or Expert** (Path Total: 18 Items)*

* **FS05 (Functional Completeness):** The application enforces distinct functional boundaries and appropriate data segregation across the Student, Faculty, and Admin portals.
* **RE03 (Fault Tolerance):** The system handles missing grades and non-numeric marks without application failure or injecting unintended zero values.
* **SE05 (Integrity):** The application protects sensitive actions and state-changing requests through session controls and token verification.

---

## 9. Part IV: Qualitative Observations (Optional)

1. *What specific aspect or feature of the UDM-RADAR prototype functioned most effectively during your evaluation?*
   `[Open Text Response]`
2. *What usability, visual, or workflow areas require further improvement prior to institutional deployment?*
   `[Open Text Response]`
3. *Additional technical, academic, or security observations:*
   `[Open Text Response]`
