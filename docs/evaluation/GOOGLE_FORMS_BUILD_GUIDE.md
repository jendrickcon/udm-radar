# UDM-RADAR — Google Forms Construction & Administration Guide

> **Administration Authority:** This guide provides the complete, step-by-step procedure for configuring, routing, testing, administering, and exporting the UDM-RADAR ISO/IEC 25010 software quality evaluation survey using Google Forms.

---

## 1. Overview & Google Forms Routing Architecture

Google Forms enforces section branching at the section boundary. To ensure respondents evaluate only their applicable role features without technical navigation friction or mid-survey branching errors, the form is architected into **six logical sections**, routing evaluators directly from demographic classification into dedicated role sections:

```
[Section 1: Study Introduction & Title]
       │
[Section 2: Data Privacy & Informed Consent Gate]
       │
[Section 3: Respondent Classification & Role Selection]
       │
       ├─► (If BSIT Student) ──────────► [Section 4A: Student Path (15 Shared + 4 Student Items = 19 Items)] ──┐
       ├─► (If CCS Academic User) ─────► [Section 4B: Academic Path (15 Shared + 3 Academic Items = 18 Items)] ─┼─► [Section 5: Comments] ──► [Section 6: Submit]
       └─► (If IT Expert) ─────────────► [Section 4C: IT Expert Path (15 Shared + 3 IT Items = 18 Items)] ────┘
```

* **No Missing Data Penalty:** Unrouted items are never presented to inapplicable groups. They are excluded from that group's denominator and must never be treated as null or zero in statistical analysis.
* **Master Pool Identity:** All 15 shared questions repeated across Sections 4A, 4B, and 4C must retain identical item codes, question wording, five-point agreement choices, and Required settings.

---

## 2. Section-by-Section Form Configuration

### Section 1: Study Title & Context
* **Form Title:** `UDM-RADAR: ISO/IEC 25010 Software Quality Evaluation`
* **Form Description:**
  > Universidad de Manila — Risk Analytics & Decision-support for Academic Records (UDM-RADAR)
  >
  > This survey evaluates the software product quality of the UDM-RADAR academic decision-support prototype based on the ISO/IEC 25010 standard. UDM-RADAR operates on synthetic academic records authorized by the Capstone Adviser to evaluate academic risk triage, grade goal calculation, and intervention workflows.
  >
  > Please complete this questionnaire based on your direct interaction with the prototype or guided evaluation walkthrough.

---

### Section 2: Data Privacy & Informed Consent (Mandatory Gate)
* **Section Description:** Displays the institutional Data Privacy Statement (see `DATA_PRIVACY_STATEMENTS.md`).
* **Question 2.1 (Multiple Choice / Checkbox — REQUIRED):**
  * *Prompt:* `Consent Acknowledgment`
  * *Option 1:* `I have read the Data Privacy Statement. I voluntarily agree to participate in this evaluation and authorize the aggregated processing of my feedback for academic capstone research.`
* **Section Navigation Rule:** Selecting Option 1 is required to proceed.

---

### Section 3: Respondent Classification & Direct Routing Gate
* **Question 3.1 (Multiple Choice — REQUIRED):**
  * *Prompt:* `Please select your evaluator stakeholder category:`
  * *Option 1:* `BSIT Student` &rarr; Set to **Go to Section 4A (BSIT Student Evaluation)**
  * *Option 2:* `CCS Academic User` (Faculty, Academic Adviser, Department Administrator, or Staff) &rarr; Set to **Go to Section 4B (CCS Academic User Evaluation)**
  * *Option 3:* `IT Practitioner or Expert` (Software Engineer, Systems Analyst, QA, Security Specialist, IT Staff) &rarr; Set to **Go to Section 4C (IT Practitioner & Expert Evaluation)**
* **Question 3.2 (Checkbox — REQUIRED):**
  * *Prompt:* `Walkthrough Verification`
  * *Option 1:* `I confirm that I have interacted with the UDM-RADAR prototype or completed the guided evaluation demonstration prior to answering this survey.`

---

### Section 4A: BSIT Student Evaluation (19 Items — REQUIRED)
*Visible only to respondents selecting "BSIT Student". Response scale for all items: `5 = Strongly Agree`, `4 = Agree`, `3 = Neither Agree nor Disagree`, `2 = Disagree`, `1 = Strongly Disagree`.*

#### Core Shared Items (15 Items):
1. **[FS01]** The system provides the necessary functions to view and monitor recorded academic grades and status.
2. **[FS02]** The displayed GWA matched the expected result in the provided evaluation scenario.
3. **[PE01]** The application pages and dashboards loaded promptly during the evaluation session.
4. **[PE02]** The system displayed multi-subject grade lists without noticeable slowdown.
5. **[US01]** The system interface clearly indicates the purpose and role of the portal being accessed.
6. **[US02]** The layout, navigation menus, and information cards are straightforward to learn and navigate.
7. **[US03]** The system provides clear feedback or notices when input is invalid or an action cannot be completed.
8. **[US06]** The application provides useful display options, including text-size adjustment, Light and Dark appearances, reduced-motion preference, and optional link underlining.
9. **[RE01]** The application performed its functions consistently throughout the evaluation session without crashing.
10. **[RE02]** Application pages, navigation routes, and dashboard views were accessible whenever requested.
11. **[RE04]** The system allows users to continue normal operation after entering invalid input or canceling an action.
12. **[SE01]** Access to the portal requires valid user authentication credentials.
13. **[SE02]** The system restricts users to information appropriate to their assigned role.
14. **[SE03]** Academic records and displayed grades remain protected against unauthenticated or unauthorized modification.
15. **[SE06]** The system clearly identifies the currently signed-in account and portal role during use.

#### Student-Specific Routed Items (4 Items):
16. **[FS03]** The Grade Goal Calculator helps in estimating the term grade required to pursue a target academic mark.
17. **[PE03]** The Grade Goal Calculator recalculated required grades immediately upon changing the target point.
18. **[US04]** The system clearly presented the configured academic-distinction guidance ranges.
19. **[US05]** Academic risk indicators are expressed in clear, supportive, and non-punitive language.

* **Section Navigation Rule:** "After Section 4A: Go to Section 5 (Optional Comments)".

---

### Section 4B: CCS Academic User Evaluation (18 Items — REQUIRED)
*Visible only to respondents selecting "CCS Academic User". Scale: `5 = Strongly Agree` to `1 = Strongly Disagree`.*

#### Core Shared Items (15 Items):
*Identical items FS01, FS02, PE01, PE02, US01, US02, US03, US06, RE01, RE02, RE04, SE01, SE02, SE03, SE06.*

#### Academic User-Specific Routed Items (3 Items):
16. **[FS04]** The academic support workflow allows recording consultation notes and intervention reasons for students needing advising.
17. **[PE04]** The class section roster and grade monitoring views loaded section records without disruptive latency during review.
18. **[SE04]** Where acknowledgement records are available, the system preserves evidence of the recorded action and time.

* **Section Navigation Rule:** "After Section 4B: Go to Section 5 (Optional Comments)".

---

### Section 4C: IT Practitioner & Expert Evaluation (18 Items — REQUIRED)
*Visible only to respondents selecting "IT Practitioner or Expert". Scale: `5 = Strongly Agree` to `1 = Strongly Disagree`.*

#### Core Shared Items (15 Items):
*Identical items FS01, FS02, PE01, PE02, US01, US02, US03, US06, RE01, RE02, RE04, SE01, SE02, SE03, SE06.*

#### IT Expert-Specific Routed Items (3 Items):
16. **[FS05]** The application enforces distinct functional boundaries and appropriate data segregation across the Student, Faculty, and Admin portals.
17. **[RE03]** The system handles missing grades and non-numeric marks without application failure or injecting unintended zero values.
18. **[SE05]** The application protects sensitive actions and state-changing requests through session controls and token verification.

* **Section Navigation Rule:** "After Section 4C: Go to Section 5 (Optional Comments)".

---

### Section 5: Optional Qualitative Observations (Optional)
*Paragraph text fields marked Optional.*
* **Question 5.1:** `What specific feature or workflow in the prototype functioned most effectively during your evaluation?`
* **Question 5.2:** `What usability, visual, or workflow areas require further improvement prior to institutional deployment?`
* **Question 5.3:** `Additional technical, academic, or security observations:`

* **Section Navigation Rule:** Advance to Section 6.

---

### Section 6: Submission Confirmation & Receipt
* **Section Header:** `Submission Complete`
* **Confirmation Message:**
  > Thank you for participating in the software product quality evaluation of UDM-RADAR. Your feedback has been recorded and will be analyzed in aggregate for the capstone study.

---

## 3. Shared-Item Normalization in Analysis

Because Google Forms assigns separate export columns for questions in different sections (e.g., `FS01` in Section 4A appears in a different column than `FS01` in Section 4B and 4C), researchers must normalize repeated columns into a single canonical variable during workbook import:

### Normalization Workflow:
1. In the Google Forms raw export, identify the three column headers for each shared item (e.g., `[4A] FS01`, `[4B] FS01`, `[4C] FS01`).
2. In the working table `tblResponses`, define a single canonical column `FS01`.
3. Consolidate the value using an Excel formula or Power Query conditional column:
   ```excel
   =IF(ISNUMBER([@[Raw_4A_FS01]]), [@[Raw_4A_FS01]], IF(ISNUMBER([@[Raw_4B_FS01]]), [@[Raw_4B_FS01]], [@[Raw_4C_FS01]]))
   ```
4. Each respondent will have exactly one numeric rating in `tblResponses[FS01]`, because routing guarantees they answered only Section 4A, 4B, or 4C.

### Approved Form Alternative (Three Separate Forms):
If section normalization introduces administrative complexity, the research team is authorized to deploy **three separate Google Forms** generated from the master instrument (Form 1: Students, Form 2: Academic Users, Form 3: IT Experts). The exported CSV rows are then stacked directly into `tblResponses` with identical headers.

---

## 4. Privacy, Duplicate Control & Pilot Protocol

* **Duplicate Control Verification:**
  > Before deployment, verify whether the selected duplicate-submission control requires sign-in or processes account identifiers. Record the tested Forms settings and observed behavior in the administration log.
* **Anonymous Mode Boundary:** Do not state that one-response enforcement is identity-free without empirical verification of Google Workspace account handling. Note that Google and the organization may process limited technical metadata under applicable service and privacy policies.
* **Synthetic Pilot Testing:**
  - Submit 1 test entry through each path (Student, Academic User, IT Expert).
  - Verify that the Student row populates 19 items, Academic populates 18 items, and IT populates 18 items.
* **Mandatory Pilot Purge:**
  - Open Google Forms **Responses** tab &rarr; Click three dots &rarr; Select **Delete all responses**.
  - Verify active response counter reads **0 responses** before sharing the survey URL.
