# UDM-RADAR — Evaluation Administration Standard Operating Procedure (SOP)

> **Operational Authority:** This standard operating procedure outlines the mandatory protocol for preparing, piloting, administering, analyzing, verifying, and archiving the ISO/IEC 25010 software product quality evaluation for UDM-RADAR.

---

## 1. 20-Step Administration Workflow

```
[Phase 1: Institutional Alignment] ──► Steps 1 to 4
[Phase 2: Form Build & Verification] ─► Steps 5 to 10
[Phase 3: Live Administration] ───────► Steps 11 to 12
[Phase 4: Data Custody & Analysis] ──► Steps 13 to 18
[Phase 5: Manuscript Population] ─────► Steps 19 to 20
```

---

### Phase 1: Institutional Alignment & Pre-Administration Preparation

- [ ] **Step 1: Confirm Stakeholder Groups with Capstone Adviser:**
  Submit the three provisional respondent groups (BSIT Students, CCS Academic Users, IT Practitioners/Experts) to Engr. Rochelle Atal. Confirm whether Faculty and Administrators remain unified under Group 2 or require split reporting.
- [ ] **Step 2: Confirm Target Sample Sizes ($N$):**
  Establish the minimum required sample size for each group (e.g., $N_1$ Students, $N_2$ Academic Users, $N_3$ IT Experts) based on adviser guidance and cohort availability.
- [ ] **Step 3: Confirm Lower Scale Interpretation Ranges:**
  Submit the proposed lower scale boundaries (`2.70–3.49 Satisfactory`, `1.90–2.69 Fair`, `1.00–1.89 Poor`) alongside the owner constraints (`4.30–5.00 Excellent`, `3.50–4.29 Very Satisfactory`) for formal adviser endorsement. All interpretation ranges remain provisional until confirmed.
- [ ] **Step 4: Finalize Walkthrough Scenarios & Evaluation Briefing:**
  Prepare the standardized scenario script ensuring evaluators interact with observable features (grade viewing, calculator, distinction gauge, class monitoring, and display preferences) prior to answering survey items.

---

### Phase 2: Form Construction, Branching & Pilot Testing

- [ ] **Step 5: Configure 6-Section Direct-Routing Google Form:**
  Build the Google Form following the exact specifications in `GOOGLE_FORMS_BUILD_GUIDE.md`:
  - Section 1: Introduction
  - Section 2: Privacy & Consent Gate
  - Section 3: Respondent Classification & Direct Routing Gate
  - Section 4A: BSIT Student (15 shared + 4 Student-specific = 19 items)
  - Section 4B: CCS Academic User (15 shared + 3 Academic-specific = 18 items)
  - Section 4C: IT Practitioner & Expert (15 shared + 3 IT-specific = 18 items)
  - Section 5: Optional Comments
  - Section 6: Submission Confirmation
  *(Alternatively, deploy three separate forms from the master instrument if section column normalization introduces administrative overhead).*
- [ ] **Step 6: Embed Data Privacy Disclosure & Pre-Deployment Verification:**
  Embed the approved Data Privacy Statement from `DATA_PRIVACY_STATEMENTS.md` into Section 2. Before deployment, verify whether the selected duplicate-submission control requires sign-in or processes account identifiers. Record the tested Forms settings and observed behavior in the administration log.
- [ ] **Step 7: Enforce Mandatory Item Settings & Agreement Scale:**
  Verify that every visible Likert question, the consent acknowledgment, and the stakeholder group selection are configured as strictly **Required**. Ensure response options use the agreement scale: `5 = Strongly Agree`, `4 = Agree`, `3 = Neither Agree nor Disagree`, `2 = Disagree`, `1 = Strongly Disagree`.
- [ ] **Step 8: Verify Section Branching Routing:**
  Test section routing rules from Section 3:
  - BSIT Student &rarr; routes directly to Section 4A.
  - CCS Academic User &rarr; routes directly to Section 4B.
  - IT Expert &rarr; routes directly to Section 4C.
  - Ensure all role sections route cleanly to Section 5 (Comments).
- [ ] **Step 9: Execute Synthetic Pilot Testing:**
  Conduct at least one synthetic test submission for each respondent path to verify routing, response capture, and export column alignment.
- [ ] **Step 10: Mandatory Pilot Response Purge:**
  Open the Google Forms **Responses** tab, select **Delete all responses**, and certify that the active response counter reads **0 responses** before sharing the public link.

---

### Phase 3: Live Administration & Response Monitoring

- [ ] **Step 11: Deploy Live Form to Authorized Respondents:**
  Distribute the verified evaluation link along with the guided scenario briefing to targeted BSIT students, CCS faculty, and qualified IT practitioners.
- [ ] **Step 12: Monitor Response Quotas & Close Form:**
  Track incoming submissions against group sample targets. Once the required quotas ($N_1, N_2, N_3$) are fulfilled, toggle form settings to **Not accepting responses**.

---

### Phase 4: Data Custody, Integrity Auditing & Workbook Analysis

- [ ] **Step 13: Export Original Responses:**
  Download the complete response dataset from Google Forms as an unedited CSV or XLSX file.
- [ ] **Step 14: Save Read-Only Source Archive:**
  Immediately archive the raw file as `UDM_RADAR_RAW_RESPONSES_SOURCE_[DATE].csv`. Set file attribute to **Read-Only**.
- [ ] **Step 15: Compute and Record SHA-256 Hash:**
  Execute `Get-FileHash -Algorithm SHA256` in PowerShell to generate the 64-character cryptographic checksum. Record the hash, export timestamp, and source filename in the `Metadata` sheet.
- [ ] **Step 16: Import Working Data & Normalize Shared Items:**
  Import response data into `tblResponses` in the Excel analysis workbook. Normalize repeated section columns (`4A`, `4B`, `4C`) into canonical shared columns (`FS01`..`SE06`). Ensure unrouted questions for a group remain completely **Blank** (never filled with zero).
- [ ] **Step 17: Execute De-Identification Protocol (If Option B Used):**
  If institutional emails were collected, the designated Data Custodian must strip email addresses and replace them with anonymous codes (`ST-001`, `AC-001`, `IT-001`) before analytical processing. The identity-bearing source export will be stored in a restricted-access location approved by the research team and adviser. The selected storage location, authorized custodians, retention period, and deletion process must be recorded before deployment.
- [ ] **Step 18: Independent Formula Audit & Valid-Response Pooling:**
  Verify that all weighted means, frequency counts, and characteristic pooled composites calculate dynamically via structured formulas without hardcoded numbers. Verify that `tblScaleLegend` lower bounds are ascending and that XLOOKUP / VLOOKUP correctly classifies boundary values.

---

### Phase 5: Manuscript Reporting, Objective Mapping & Archival

- [ ] **Step 19: Populate Chapter 4 Result Tables:**
  Transfer verified weighted means, frequencies, percentages, and interpretations from the workbook directly into Table 4.1 through Table 4.9 in `CHAPTER_4_TABLE_TEMPLATES.md`. Ensure cross-group comparisons (Table 4.7) use shared items only, and within-group evaluations (Table 4.8) are analyzed separately.
- [ ] **Step 20: Draft Evidence Narratives, Map Chapter 5 & Archive:**
  Author Chapter 4 narrative discussions following `CHAPTER_4_NARRATIVE_TEMPLATES.md`. Populate the conditional Chapter 5 conclusions using `CHAPTER_5_MAPPING_TEMPLATE.md`. Upload the complete evidence package to SharePoint per `SHAREPOINT_DELIVERABLE_CHECKLIST.md`.

---

## 2. Objective 3 Non-Likert Prediction Technical Verification Checklist

> **Methodological Guardrail:** Objective 3 evaluates the advisory predictive component. In compliance with research standards, survey respondents are **not** asked to certify machine learning mathematical accuracy (which requires labeled historical validation data). Instead, the technical integrity and ethical governance of the predictive component are verified against this non-Likert architectural checklist:

| Verification Item | Requirement / Standard | Verification Evidence / Location | Status |
| :--- | :--- | :--- | :---: |
| **1. Disclosed Training Basis** | System explicitly discloses that predictions derive from academic history and current Prelim grades. | Student Dashboard risk tooltips; `docs/ml/MODEL_GOVERNANCE_AND_LIMITATIONS.md`. | [ ] Verified |
| **2. Midterm Model Exclusion** | Interface and documentation clearly disclose that Midterm grades are available for academic review but are **not** active model inputs. | Academic guide drawer; model feature contract (`historical_gwa`, `current_prelim_avg`, `failed_count`, `irregular_semesters`). | [ ] Verified |
| **3. Provisional Partial Data** | Projections based on incomplete current terms are labeled as provisional estimates. | Student Dashboard disclaimer: *"Additional grades may change this estimate."* | [ ] Verified |
| **4. No Fabricated Inference** | When no numeric final grades exist, the system renders `N/A` rather than fabricating predictions. | Student Dashboard missing GWA fallback tests (`student_dashboard_gwa_test.php`). | [ ] Verified |
| **5. Unavailable vs. Zero** | Missing subject grades and unresolved `INC` marks are handled as unavailable (`N/A`), never coerced to zero. | Grade tables and unit-weighted GWA helper (`config/constants.php`). | [ ] Verified |
| **6. Advisory Non-Punitive Role** | Risk categories (High, Moderate, Low) provide supportive decision support and never trigger automated sanctions. | Supportive copy audit (`tests/unit/student_supportive_copy_test.php`); student glossary. | [ ] Verified |
| **7. No Official Decisions** | Outputs clearly state they do not determine official academic standing, dean's list, probation, or scholarship eligibility. | Student Dashboard footer disclaimer; Academic Guide drawer. | [ ] Verified |
| **8. Metric Separation** | Model regression metrics (MAE, RMSE, R²) are verified against committed `python_ml/model_metrics.json` when generated; artifact/manifest hashes, dataset boundary, and verification date are recorded separately from ISO survey ratings. | Metrics manifest `python_ml/model_metrics.json`; Chapter 5 Finding 5.1.3. | [ ] Verified |
