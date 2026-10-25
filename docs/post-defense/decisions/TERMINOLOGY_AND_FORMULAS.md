# Quick Reference: Institutional Terminology and Academic Formulas — UDM-RADAR

**Document Version:** 1.0.0
**Source of Truth:** Universidad de Manila Student Manual 2025; `config/constants.php`

---

## 1. Official UDM Grading Scale Table

| Raw Percentage | Official Discrete Point Grade | Status Description | GWA Inclusion |
|:---:|:---:|---|:---:|
| **99 – 100%** | **4.00** | Excellent / Highest Honor | Included |
| **96 – 98%** | **3.75** | Superior | Included |
| **93 – 95%** | **3.50** | Very Good | Included |
| **90 – 92%** | **3.25** | Very Good | Included |
| **87 – 89%** | **3.00** | Good | Included |
| **84 – 86%** | **2.75** | Good | Included |
| **81 – 83%** | **2.50** | Fair | Included |
| **78 – 80%** | **2.25** | Fair | Included |
| **75 – 77%** | **2.00** | Passing | Included |
| **72 – 74%** | **1.75** | Passing | Included |
| **69 – 71%** | **1.50** | Passing | Included |
| **66 – 68%** | **1.25** | Passing | Included |
| **65%** | **1.00** | Minimum Passing Point Grade | Included |
| **Below 65%** | **0.00** | Numeric Failing Grade | Excluded from active GWA denominator |
| — | **INC** | Incomplete (Unresolved) | **Excluded** |
| — | **DRP** | Dropped Course | **Excluded** |
| — | **P** | Passed (Textual outcome) | **Excluded from numeric GWA** |
| — | **DO** | Dropped Official | **Excluded** |
| — | **DU** | Dropped Unofficial | **Excluded** |
| — | **FA** | Failure due to Absences | **Excluded** |
| — | **UD** | Unofficial Drop | **Excluded** |

---

## 2. Term Weighting and Conversion Formula

$$\text{Final Percentage} = (\text{Prelim} \times 0.30) + (\text{Midterm} \times 0.30) + (\text{Pre-Final} \times 0.40)$$
- **Prelim, Midterm, Pre-Final:** Strictly raw percentages ($0\text{--}100$). Never discrete points.
- **Final Grade:** Official discrete point grade ($4.00\text{--}1.00$, $0.00$) or textual outcome.

---

## 3. General Weighted Average (GWA) Formula

$$\text{GWA} = \frac{\sum_{i=1}^{n} (\text{Grade}_i \times \text{Units}_i)}{\sum_{i=1}^{n} \text{Units}_i}$$
- **Units:** Academic credit units ($1\text{--}6$).
- **Rule:** Only subjects with discrete numeric point grades ($4.00\text{--}1.00$) contribute to the grade sum and unit denominator.

---

## 4. Academic Progression Metrics

1. **Point Change:**
   $$\Delta\text{GWA} = \text{Current GWA} - \text{Previous GWA}$$
   *Example:* $2.61 - 2.43 = +0.18$ points (academic improvement).

2. **Relative Percentage Change:**
   $$\text{Change (\%)} = \left(\frac{\text{Current} - \text{Previous}}{\text{Previous}}\right) \times 100\%$$

3. **Logarithmic Change (Internal Research Only):**
   $$\text{Log Change} = \ln\left(\frac{\text{Current}}{\text{Previous}}\right)$$

---

## 5. Comparative Averages & Denominators

- **Average GWA by Year Level:** Arithmetic mean of student GWAs in a specific curriculum year.
- **Average GWA by Semester:** Arithmetic mean of student GWAs enrolled in a specific term.
- **Pass-Rate Denominator:** Strictly $\text{Recognized Outcomes} = \text{Passed} + \text{Failed}$. Exclude dropped (`DRP`), incomplete (`INC`), and missing grades.
