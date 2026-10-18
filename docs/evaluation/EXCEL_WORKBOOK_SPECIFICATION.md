# UDM-RADAR — Excel Evaluation Workbook Specification

> **Technical Authority:** This document defines the exact architecture, data dictionary, sheet organization, structured table names, and formulas for the Excel calculation workbook used to process ISO/IEC 25010 survey data for UDM-RADAR.

---

## 1. Workbook Architecture & Sheet Layout

The workbook contains **nine dedicated worksheets**:

1. **`Metadata`:** Record of source export file, export date, SHA-256 cryptographic hash, evaluator totals ($N$), and workbook audit history.
2. **`Question_Map`:** Master dictionary mapping question codes to ISO characteristics, subcriteria, exact statements, and target respondent groups.
3. **`Scale_Legend`:** Five-point Likert scale definitions, response options, ascending verbal interpretation boundaries, and approval statuses.
4. **`Raw_Responses`:** Working working-table import (`tblResponses`) containing clean, de-identified evaluation records.
5. **`Frequency_Counts`:** Structured frequency distributions per agreement rating (1 to 5) per item and group.
6. **`Weighted_Means`:** Item-level weighted means, score sums, valid response counts, and pooled item composites.
7. **`Group_Summary`:** Within-group comprehensive evaluations and shared-item cross-group comparisons.
8. **`Characteristic_Summary`:** Valid-response-weighted pooled characteristic composites and overall ranking.
9. **`Overall_Composite`:** Grand composite mean, verbal interpretation lookup, and optional equal-group comparison.

---

## 2. Source Export Integrity & SHA-256 Hash Protocol

Excel worksheets cannot guarantee immutability through software settings alone. The following audit-trail procedure must be strictly observed:

1. **Export Original Responses:** Download the raw Google Forms response export as a CSV or XLSX file.
2. **Save Read-Only Source Copy:** Immediately save the original export into a restricted-access location approved by the research team and adviser as `UDM_RADAR_RAW_RESPONSES_SOURCE_[DATE].csv`. Set the operating system file attribute to **Read-Only**.
3. **Compute Cryptographic Hash:** Generate the SHA-256 checksum of the source file via PowerShell:
   ```powershell
   Get-FileHash -Algorithm SHA256 "path\to\UDM_RADAR_RAW_RESPONSES_SOURCE_[DATE].csv"
   ```
4. **Document in `Metadata` Sheet:** Record the source filename, timestamp, file size, and the generated 64-character SHA-256 hash string in the `Metadata` worksheet.
5. **Working Import:** Copy the validated response columns into the analysis workbook table `tblResponses`. The working table is an analytical import, while the external source copy remains the immutable evidentiary anchor.

---

## 3. Data Dictionary: `tblResponses` (Sheet: `Raw_Responses`)

The imported response data must be formatted as an official Excel Table named `tblResponses`. Column headers must match the stable question codes exactly:

| Column Name | Data Type | Permissible Values | Description |
| :--- | :--- | :--- | :--- |
| `Response_ID` | Text | `R001`, `R002`, ... | Unique sequential respondent index |
| `Timestamp` | DateTime | Valid ISO timestamp | Submission date and time |
| `Respondent_Group` | Text | `BSIT Student`, `CCS Academic User`, `IT Practitioner or Expert` | Evaluator classification category |
| `Consent_Given` | Text | `Yes` | Acknowledgment of privacy disclosure |
| `Walkthrough_Done` | Text | `Yes` | Confirmation of prototype interaction |
| `FS01` .. `FS02` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Functional Suitability items |
| `FS03` | Integer / Blank | `1`–`5` (Students); Blank (Others) | Student-specific item |
| `FS04` | Integer / Blank | `1`–`5` (Academic); Blank (Others) | Academic User-specific item |
| `FS05` | Integer / Blank | `1`–`5` (IT); Blank (Others) | IT Expert-specific item |
| `PE01` .. `PE02` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Performance items |
| `PE03` | Integer / Blank | `1`–`5` (Students); Blank (Others) | Student-specific item |
| `PE04` | Integer / Blank | `1`–`5` (Academic); Blank (Others) | Academic User-specific item |
| `US01` .. `US03` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Usability items |
| `US04` .. `US05` | Integer / Blank | `1`–`5` (Students); Blank (Others) | Student-specific items |
| `US06` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Usability item |
| `RE01` .. `RE02` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Reliability items |
| `RE03` | Integer / Blank | `1`–`5` (IT); Blank (Others) | IT Expert-specific item |
| `RE04` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Reliability item |
| `SE01` .. `SE03` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Security items |
| `SE04` | Integer / Blank | `1`–`5` (Academic); Blank (Others) | Academic User-specific item |
| `SE05` | Integer / Blank | `1`–`5` (IT); Blank (Others) | IT Expert-specific item |
| `SE06` | Integer | `1`, `2`, `3`, `4`, `5` | Shared Security item |
| `Comments_Effective` | Text | Free text (Optional) | Qualitative positive observation |
| `Comments_Improvement`| Text | Free text (Optional) | Qualitative suggestion |

*Blank-Handling Guardrail:* Cells for unrouted questions must remain strictly **Blank** (empty cells). They must never be filled with `0`, `N/A`, or text, ensuring Excel's statistical functions omit them from group denominators.

---

## 4. Scale Legend Table: `tblScaleLegend` (Sheet: `Scale_Legend`)

The scale lookup table **must be sorted in ascending order by `Lower_Bound`** to support robust interval evaluation:

| Lower_Bound | Upper_Bound | Response_Option | Verbal_Interpretation | Approval_Status |
| :---: | :---: | :--- | :--- | :--- |
| **1.00** | 1.89 | 1 — Strongly Disagree | Poor | Provisional pending adviser confirmation |
| **1.90** | 2.69 | 2 — Disagree | Fair | Provisional pending adviser confirmation |
| **2.70** | 3.49 | 3 — Neither Agree nor Disagree | Satisfactory | Provisional pending adviser confirmation |
| **3.50** | 4.29 | 4 — Agree | Very Satisfactory | Provisional pending adviser confirmation *(Owner constraint)* |
| **4.30** | 5.00 | 5 — Strongly Agree | Excellent | Provisional pending adviser confirmation *(Owner constraint)* |

*Separation Rule:* Columns C (`Response_Option`) reflects the clickable survey choices, while Column D (`Verbal_Interpretation`) reflects the qualitative interpretation of computed weighted means.

---

## 5. Calculation Formulas Specification

All formulas must utilize structured references targeting `tblResponses` and `tblScaleLegend`.

### 5.1 Frequency Counts (Sheet: `Frequency_Counts`)
Counts response frequencies per agreement rating (1 to 5) per group:

* **BSIT Student Count for Rating 5 on Item FS01:**
  ```excel
  =COUNTIFS(tblResponses[Respondent_Group], "BSIT Student", tblResponses[FS01], 5)
  ```
* **CCS Academic User Count for Rating 4 on Item FS04:**
  ```excel
  =COUNTIFS(tblResponses[Respondent_Group], "CCS Academic User", tblResponses[FS04], 4)
  ```
* **IT Practitioner Count for Rating 3 on Item SE05:**
  ```excel
  =COUNTIFS(tblResponses[Respondent_Group], "IT Practitioner or Expert", tblResponses[SE05], 3)
  ```

### 5.2 Group-Specific Item Means (Sheet: `Weighted_Means`)
Calculates the mean for each item within a specific respondent group (automatically ignores blank cells):

* **BSIT Student Mean for Item FS01:**
  ```excel
  =IFERROR(AVERAGEIFS(tblResponses[FS01], tblResponses[Respondent_Group], "BSIT Student"), "")
  ```
* **CCS Academic User Mean for Item FS04:**
  ```excel
  =IFERROR(AVERAGEIFS(tblResponses[FS04], tblResponses[Respondent_Group], "CCS Academic User"), "")
  ```
* **IT Practitioner Mean for Item RE03:**
  ```excel
  =IFERROR(AVERAGEIFS(tblResponses[RE03], tblResponses[Respondent_Group], "IT Practitioner or Expert"), "")
  ```

### 5.3 Overall Item Pooled Weighted Mean (Sheet: `Weighted_Means`)
Calculates the primary pooled mean across all valid responses for an item:

* **Pooled Mean for Shared Item FS01 (All Evaluators):**
  ```excel
  =IFERROR(AVERAGE(tblResponses[FS01]), "")
  ```
* **Pooled Mean for Routed Item FS03 (Evaluated only by Students):**
  ```excel
  =IFERROR(AVERAGE(tblResponses[FS03]), "")
  ```
  *(Because `tblResponses[FS03]` is blank for non-students, `AVERAGE` correctly evaluates only valid student responses without skewing).*

---

## 6. Characteristic Composite Pooling Architecture

When a characteristic contains both shared items ($N_{\text{total}}$ responses) and routed items ($N_{\text{group}}$ responses), **calculating the characteristic composite as a simple unweighted average of item means is mathematically flawed**.

Researchers must compute a **valid-response-weighted pooled mean** across all items in the characteristic:

### 6.1 Item-Level Sums & Counts (Sheet: `Weighted_Means`)
For each item $i$ in `tblResponses`:
* **Item Score Sum:**
  ```excel
  =SUM(tblResponses[FS01])
  ```
* **Item Valid Response Count ($N$):**
  ```excel
  =COUNT(tblResponses[FS01])
  ```

### 6.2 Characteristic-Level Pooling (Sheet: `Characteristic_Summary`)
For a characteristic containing items $k_1, k_2, \dots, k_m$:
* **`Characteristic_Score_Sum`:**
  ```excel
  =SUM(Item_Score_Sums_for_Characteristic)
  ```
* **`Characteristic_Valid_N`:**
  ```excel
  =SUM(Item_Valid_Counts_for_Characteristic)
  ```
* **`Characteristic_Pooled_WM`:**
  ```excel
  =IF([@[Characteristic_Valid_N]] > 0, [@[Characteristic_Score_Sum]] / [@[Characteristic_Valid_N]], "")
  ```

### 6.3 Grand Overall Composite
Across all 25 items:
```excel
=IF(SUM(tblCharacteristicSummary[Characteristic_Valid_N]) > 0, SUM(tblCharacteristicSummary[Characteristic_Score_Sum]) / SUM(tblCharacteristicSummary[Characteristic_Valid_N]), "")
```

---

## 7. Group Evaluation Outputs Specification

The workbook generates two strictly separated analytical views in `Group_Summary`:

### Output A: Shared-Item Cross-Group Comparison
* **Scope:** Strictly the **15 shared items**.
* **Columns:** `Students Shared WM`, `Academic Users Shared WM`, `IT Experts Shared WM`, `Pooled Shared WM`.
* **Purpose:** Enables rigorous cross-stakeholder comparison because all three groups evaluated the exact same set of questions.

### Output B: Within-Group Comprehensive Evaluation
* **Scope:** Evaluated within each group's applicable scope.
  * BSIT Students: 15 Shared + 4 Student-specific = 19 items
  * CCS Academic Users: 15 Shared + 3 Academic-specific = 18 items
  * IT Practitioners / Experts: 15 Shared + 3 IT-specific = 18 items
* **Rule:** Do **not** directly rank or compare the three comprehensive group means against one another, because each group evaluated a different total set of questions.

---

## 8. Automated Verbal Interpretation Lookup

Translates a computed numeric weighted mean into its qualitative verbal interpretation:

* **Primary Modern Formula (XLOOKUP):**
  ```excel
  =IF(ISNUMBER([@[Composite_WM]]), XLOOKUP([@[Composite_WM]], tblScaleLegend[Lower_Bound], tblScaleLegend[Verbal_Interpretation], "", -1), "")
  ```
  *(Note: Match mode `-1` matches exact or next smaller lower bound in ascending table).*

* **Compatibility Alternative (VLOOKUP):**
  ```excel
  =IF(ISNUMBER([@[Composite_WM]]), VLOOKUP([@[Composite_WM]], tblScaleLegend, 4, TRUE), "")
  ```

---

## 9. Formula Audit & Verification Checklist

Before publishing evaluation numbers to Chapter 4, verify:

- [ ] **Blank Cell Integrity:** Filter `tblResponses[FS03]` to confirm non-student rows are empty (not `0`).
- [ ] **Denominator Verification:** Verify that `COUNT(tblResponses[FS01])` equals $N_{\text{total}}$, while `COUNT(tblResponses[FS03])` equals $N_{\text{students}}$.
- [ ] **Boundary Verification:** Test a synthetic score of `3.50` &rarr; returns `Very Satisfactory`; `4.30` &rarr; returns `Excellent`.
- [ ] **Hash Match:** Verify SHA-256 of the source CSV matches the value entered in `Metadata`.
- [ ] **Zero Hardcoding:** Verify that all Chapter 4 inputs derive directly from dynamic workbook formulas.
