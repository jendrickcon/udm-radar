# UDM-RADAR — Data Privacy & Informed Consent Disclosures

> **Regulatory Compliance:** This document establishes the research privacy and informed consent disclosures for the UDM-RADAR software product quality evaluation in compliance with the **Data Privacy Act of 2012 (Republic Act No. 10173)** and institutional academic ethics standards.

---

## 1. Option A: Anonymous-Response Configuration

*This configuration applies when Google Forms is configured with "Collect email addresses" = **Do not collect**.*

### Disclosure Statement (To be embedded in Google Forms Section 2)

```markdown
DATA PRIVACY STATEMENT & INFORMED CONSENT
(Anonymous Evaluation Configuration)

1. Purpose of the Study:
You are invited to participate in the software product quality evaluation of UDM-RADAR (Risk Analytics & Decision-support for Academic Records), an undergraduate capstone research prototype developed by BS Information Technology students at Universidad de Manila. This study evaluates software quality based on the ISO/IEC 25010 standard.

2. Voluntary Participation:
Your participation is entirely voluntary. You may decline to participate or discontinue your evaluation at any time without academic penalty, loss of benefits, or prejudice.

3. Information Collection & Service Metadata Qualification:
The research form does not request names, student numbers, or email addresses. Google and the organization may process limited technical metadata according to their applicable service and privacy policies. Research reporting will use aggregated responses.

4. Non-Punitive Prototype Boundary:
UDM-RADAR operates on synthetic academic records authorized by the Capstone Adviser. Evaluators are interacting with a demonstration decision-support tool. The system does not make binding registrar decisions, scholarship evaluations, or academic disciplinary determinations.

5. Data Retention & Access:
Aggregated evaluation results will be reported in Chapter 4 and Chapter 5 of the capstone manuscript. Raw survey exports will be archived in a restricted-access location approved by the research team and adviser and will be retained for [Retention Period], after which the digital files will be deleted per the documented deletion process.

6. Researcher & Institutional Contacts:
For inquiries regarding this study or your rights under RA 10173, you may contact:
- Lead Researcher: [Researcher Contact — Name & Email]
- Capstone Adviser: [Adviser Contact — Engr. Rochelle Atal / Email]
- College / Department: College of Computer Studies, Universidad de Manila
```

### Mandatory Consent Checkbox Text
```text
[ ] I have read and understood the Data Privacy Statement above. I voluntarily agree to participate in this evaluation and authorize the collection and aggregated processing of my responses for academic research purposes.
```

### Technical Administration Note on Duplicate Control
> **Pre-Deployment Verification:** Before deployment, verify whether the selected duplicate-submission control requires sign-in or processes account identifiers. Record the tested Forms settings and observed behavior in the administration log. Do not promise absolute anonymity or that no IP metadata exists anywhere in Google's cloud service infrastructure.

---

## 2. Option B: Institution-Verified Configuration

*This configuration applies when Google Forms is restricted to accounts under **[Verified Institutional Domain]**.*

### Disclosure Statement (To be embedded in Google Forms Section 2)

```markdown
DATA PRIVACY STATEMENT & INFORMED CONSENT
(Institution-Verified Configuration)

1. Purpose of the Study:
You are invited to participate in the software product quality evaluation of UDM-RADAR (Risk Analytics & Decision-support for Academic Records), an undergraduate capstone research prototype developed by BS Information Technology students at Universidad de Manila. This study evaluates software quality based on the ISO/IEC 25010 standard.

2. Voluntary Participation:
Your participation is entirely voluntary. You may decline to participate or discontinue your evaluation at any time without academic penalty or prejudice.

3. Institutional Verification & De-Identification Protocol:
To verify that respondents belong to the authorized stakeholder community and to prevent duplicate submissions, Google Forms authenticates your account under the [Verified Institutional Domain] domain. Google and the organization process limited technical metadata according to applicable enterprise service agreements.

De-Identification Protocol:
a. Who performs de-identification: The designated Capstone Data Custodian ([Researcher Contact]).
b. When it occurs: Immediately upon the formal conclusion of the survey administration window and prior to statistical processing.
c. Storage of identity-bearing source: The identity-bearing source export will be stored in a restricted-access location approved by the research team and adviser. The selected storage location, authorized custodians, retention period, and deletion process must be recorded before deployment.
d. Analytical separation: Email identifiers will be completely stripped and replaced by anonymous respondent codes (e.g., ST-001, AC-001, IT-001) in the working analysis workbook (tblResponses). All public manuscript presentations, Chapter 4 tables, and statistical summaries report aggregated data only.

4. Non-Punitive Prototype Boundary:
UDM-RADAR operates on synthetic academic records authorized by the Capstone Adviser. Evaluators are interacting with a demonstration decision-support tool. The system does not make binding registrar decisions, scholarship evaluations, or academic disciplinary determinations.

5. Data Retention & Access:
Survey records will be retained for [Retention Period], after which the digital identity files will be deleted per the documented deletion process.

6. Researcher & Institutional Contacts:
For inquiries regarding this study or your rights under RA 10173, you may contact:
- Lead Researcher: [Researcher Contact — Name & Email]
- Capstone Adviser: [Adviser Contact — Engr. Rochelle Atal / Email]
- College / Department: College of Computer Studies, Universidad de Manila
```

### Mandatory Consent Checkbox Text
```text
[ ] I have read and understood the Data Privacy Statement above. I voluntarily agree to participate in this evaluation and authorize the collection, de-identification, and aggregated processing of my responses for academic research purposes.
```

---

## 3. Configuration Comparison Matrix

| Property | Option A (Anonymous Mode) | Option B (Institution-Verified Mode) |
| :--- | :--- | :--- |
| **Google Forms Setting** | "Collect email addresses" = `Do not collect` | "Collect email addresses" = `Verified` |
| **Domain Restriction** | Open or Google login required without email logging | Restricted to `[Verified Institutional Domain]` accounts |
| **Personal Identifiers** | None captured by researchers | Institutional email captured at submission |
| **Duplicate Prevention** | Cookie / Google account check without identity logging | Exact 1-response enforcement via institutional account |
| **De-Identification Step** | Unnecessary; source file contains zero email headers | **Required:** De-identification protocol executed by Data Custodian prior to analysis |
| **Pre-Deployment Rule** | Verify if limit-to-1 processes account identifiers | Verify that student and faculty institutional domains match |
| **Primary Limitation** | Relies on respondent self-classification | Requires valid institutional account credentials |
