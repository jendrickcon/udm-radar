# UDM-RADAR — SharePoint Deliverables & Archival Checklist

> **Archival Standard:** This checklist enumerates the required academic, technical, and regulatory deliverables that must be archived in the official Universidad de Manila Capstone SharePoint repository prior to final capstone defense and institutional sign-off.

---

## 1. Manuscript & Institutional Documentation

- [ ] **Chapter 1: Introduction & Project Background** (Final DOCX & PDF)
- [ ] **Chapter 2: Review of Related Literature & Studies** (Final DOCX & PDF)
- [ ] **Chapter 3: Methodology & Architectural Framework** (Final DOCX & PDF)
- [ ] **Chapter 4: Results and Discussion** (Populated from verified Excel analysis; Final DOCX & PDF)
- [ ] **Chapter 5: Summary of Findings, Conclusions, and Recommendations** (Final DOCX & PDF)
- [ ] **Complete Compiled Manuscript** (Preliminary pages, signature sheet, abstract, Chapters 1–5, references, and appendices in unified PDF)
- [ ] **Curriculum Vitae (CVs):** Individual academic CVs for all capstone group members.
- [ ] **Capstone Adviser Acceptance Letter:** Formally signed endorsement from Engr. Rochelle Atal.
- [ ] **Client / Beneficiary Acceptance Letter:** Formally signed acceptance from College of Computer Studies Dean / Department Chair.

---

## 2. Evaluation Instruments & Empirical Audit Trail

- [ ] **Google Forms Questionnaire Link & Live Form Archive:** Verified link to the active Google Form (or 3-form suite) and PDF printout of the master questionnaire.
- [ ] **Data Privacy & Informed Consent Configuration Evidence:** Screenshot of Google Forms settings documenting Option A (Anonymous) or Option B (Institution-Verified) configuration.
- [ ] **Raw Google Forms Export (Read-Only Source Copy):** Original, unedited CSV/XLSX export preserved as `UDM_RADAR_RAW_RESPONSES_SOURCE_[DATE].csv`.
- [ ] **Cryptographic Hash Evidence (SHA-256):** PowerShell/terminal log certifying the SHA-256 checksum of the raw export file.
- [ ] **Completed Excel Analysis Workbook:** Working calculation workbook containing `tblResponses`, `tblScaleLegend`, `Question_Map`, frequency tables, and verified weighted-mean formulas.
- [ ] **Formula Verification Sign-Off Sheet:** Documented verification confirming zero hardcoded means, correct ascending lookup behavior, and proper routed-denominator handling.

---

## 3. Presentation, Media & Demonstration Assets

- [ ] **Capstone Defense Presentation Deck:** Official PowerPoint slide deck (`.pptx`) and presentation PDF.
- [ ] **Prerecorded System Demonstration Video:** High-resolution ($\ge 7$ minutes) walkthrough video demonstrating:
  - Student Portal: Grade viewing, Academic Distinction gauge, Grade Goal Calculator, and Display Settings.
  - Faculty Portal: Class section monitoring, Prelim/Midterm grade review, and academic support intervention modal.
  - Admin Portal: Longitudinal student records, batch prediction overview, and distinction readiness analytics.
- [ ] **Project Film / Demonstration Poster:** High-resolution promotional poster graphic (`udm-radar-project-film-poster.png`).

---

## 4. Technical Governance, Test Evidence & Model Artifacts

- [ ] **Adviser Synthetic Data Authorization Memo:** Formal memo signed by the Capstone Adviser authorizing the use of calibrated synthetic records under Registrar privacy constraints.
- [ ] **Machine Learning Model Governance Manifest:**
  - Active model metrics manifest: `python_ml/model_metrics.json` (Verify current MAE, RMSE, and R² against the committed manifest when generated; record model artifact hash, metrics-manifest hash, evaluation dataset boundary, and verification date).
  - Model governance standard: `docs/ml/MODEL_GOVERNANCE_AND_LIMITATIONS.md`
  - Training pipeline provenance log verifying 150-row synthetic demonstration dataset.
- [ ] **System Automated Test Verification Logs:**
  - Automated PHP regression check logs: `tests/unit/student_supportive_copy_test.php` (290 assertions passed)
  - GWA calculation fixture logs: `tests/unit/student_dashboard_gwa_test.php` (31 assertions passed)
  - Display preferences contract test logs: `tests/unit/display_preferences_contract_test.js` (32 assertions passed)
  - Database schema baseline audit log (18 base tables verified).
  - Playwright cross-portal layout regression logs.
- [ ] **Git Repository Release Snapshot:** Git bundle or clean ZIP archive of the repository at the approved release tag, excluding local configuration, temporary testbeds, and unvetted scratch databases.

---

## 5. Ethical Compliance & Archival Security Rule

> [!CAUTION]
> **Data Privacy Invariant:** Never upload genuine student names, real student numbers, un-deidentified institutional email records, or personal login passwords to GitHub or public SharePoint folders. The identity-bearing source export will be stored in a restricted-access location approved by the research team and adviser. The selected storage location, authorized custodians, retention period, and deletion process must be recorded before deployment. All evaluation data archived in public or repository folders must be strictly de-identified and aggregated.
