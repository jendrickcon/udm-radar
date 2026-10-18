# UDM-RADAR — Chapter 4 Narrative Reporting Templates

> **Academic Authorship Guide:** This document provides standardized, evidence-anchored narrative writing shells for Chapter 4 (*Results and Discussion*). These templates prompt researchers to report empirical findings strictly derived from the completed calculation workbook. Researchers must never pre-write favorable outcomes, fabricate literature citations, or speculate before empirical survey data are formally collected and analyzed.

---

## 1. General Reporting Principles for Chapter 4

1. **Scale Vocabulary Precision:** Evaluators rated agreement statements using a five-point agreement scale (`5 = Strongly Agree` to `1 = Strongly Disagree`). The qualitative terms `Excellent`, `Very Satisfactory`, `Satisfactory`, `Fair`, and `Poor` apply strictly as statistical interpretations of computed weighted-mean scores.
2. **Cross-Group Comparability Rule:** When comparing perceptions between Students, Academic Users, and IT Experts, researchers must report the **Shared-Item Cross-Group Comparison (Table 4.7)** based strictly on the 15 shared items. Groups must never be ranked against one another using role-specific items.
3. **Within-Group Scope Reporting:** Within-group comprehensive evaluations (Table 4.8) evaluate each group across its respective observable scope (19 items for Students, 18 for Academic Users, 18 for IT Experts). These three comprehensive means are reported independently and are not ranked against each other.
4. **Valid-Response Characteristic Pooling:** Characteristic composites reflect valid-response pooled weighting ($\text{Score Sum} / \text{Valid } N$), correctly accounting for differing response counts between shared and routed questions.
5. **Literature Integration Hook:** Support findings using verified academic citations that contextualize why specific software attributes influence user satisfaction and academic decision-support efficacy (`[Insert verified related literature]`).

---

## 2. Demographic Profile Narrative Template (Table 4.1)

```markdown
### 4.1 Demographic Profile of Evaluators

A total of [Total N] respondents participated in the software product quality evaluation of the UDM-RADAR prototype. As presented in Table 4.1, the evaluation sample was categorized into three distinct stakeholder groups to ensure multi-perspective evaluation:

1. **BSIT Students:** Comprising [N_Students] respondents ([%_Students]% of the total sample), this group evaluated the user experience, grade visibility, Grade Goal Calculator, and supportive communication framing across 19 required items.
2. **CCS Academic Users:** Representing [N_Academic] respondents ([%_Academic]%), this group consisted of faculty members, academic advisers, and department administrators who evaluated class section monitoring, student triage indicators, and the academic support referral workflow across 18 required items.
3. **IT Practitioners and Experts:** Consisting of [N_IT] respondents ([%_IT]%), this cohort included software engineers, systems analysts, QA specialists, and IT personnel who evaluated architectural boundaries, fault tolerance under missing data, interface responsiveness, and session security across 18 required items.

[Discuss recruitment methodology, evaluation setting, e.g., whether evaluation was conducted in computer laboratory sessions or online guided walkthroughs, and note that all respondents completed the mandatory guided scenario verification prior to evaluation.]
```

---

## 3. Characteristic-Specific Narrative Templates (Tables 4.2 to 4.6)

### 3.1 Functional Suitability Narrative Shell (Table 4.2)

```markdown
### 4.2 Evaluation Results for Functional Suitability

Table 4.2 presents the evaluation results for Functional Suitability, assessing the degree to which UDM-RADAR provides functions that meet stated academic decision-support needs. Overall, Functional Suitability attained a primary pooled composite weighted mean of [WM_FS], corresponding to a provisional verbal interpretation of [Interpretation_FS].

Among the specific functional subcriteria evaluated:
- **Highest-Rated Item:** Item [Item_Code_Highest, e.g., FS02] ("[Statement_Text]") received the highest rating with a composite mean of [WM_Highest] ([Interpretation_Highest]). This was evaluated most favorably by the [Respondent_Group_Highest] group ([WM_Group_Highest]), indicating that [explain why system mechanics, such as transparent GWA scenario calculation or clear grade tables, supported this rating].
- **Lowest-Rated Item:** Conversely, Item [Item_Code_Lowest, e.g., FS04] ("[Statement_Text]") recorded the lowest composite mean in this category at [WM_Lowest] ([Interpretation_Lowest]), with the [Respondent_Group_Lowest] cohort providing an average rating of [WM_Group_Lowest]. Evaluator feedback indicated that [summarize qualitative feedback explaining the limitation or needed workflow refinement].

The empirical findings for Functional Suitability align with the observations of [Insert verified related literature author, Year], who noted that [insert synthesized literature finding regarding functional completeness in academic monitoring tools]. [If supported by survey evidence, state whether results indicate that UDM-RADAR delivers core decision-support capabilities, while noting operational limitations, e.g., need for broader institutional grade type integration].
```

---

### 3.2 Performance Efficiency Narrative Shell (Table 4.3)

```markdown
### 4.3 Evaluation Results for Performance Efficiency

The evaluation results for Performance Efficiency are summarized in Table 4.3. The prototype achieved an overall valid-response pooled composite weighted mean of [WM_PE], interpreted verbally as [Interpretation_PE].

Key observations across performance indicators include:
- **Highest-Rated Item:** Item [Item_Code_Highest] ("[Statement_Text]") achieved the highest composite rating of [WM_Highest] ([Interpretation_Highest]), driven primarily by the [Respondent_Group_Highest] group ([WM_Group_Highest]). This reflects the [explain client-side optimization, e.g., immediate DOM responsiveness of the Grade Goal Calculator or lightweight page asset delivery].
- **Lowest-Rated Item:** Item [Item_Code_Lowest] ("[Statement_Text]") received the lowest evaluation at [WM_Lowest] ([Interpretation_Lowest]) from the [Respondent_Group_Lowest] group ([WM_Group_Lowest]). Qualitative remarks suggested that [discuss latency or rendering considerations during large roster inspection].

These performance results are contextualized by the findings of [Insert verified related literature author, Year], who emphasized that responsive client-side feedback in academic decision-support interfaces reduces cognitive friction. While the prototype demonstrated prompt responsiveness during local and staging evaluation sessions, performance under large-scale concurrent campus deployments remains an area for future operational validation.
```

---

### 3.3 Usability Narrative Shell (Table 4.4)

```markdown
### 4.4 Evaluation Results for Usability

Table 4.4 details the evaluation outcomes for Usability, encompassing appropriateness recognizability, learnability, error protection, interface aesthetics, supportive language, and display accessibility. Usability obtained an overall pooled composite mean of [WM_US], verbally interpreted as [Interpretation_US].

Specific item highlights include:
- **Highest-Rated Item:** Item [Item_Code_Highest] ("[Statement_Text]") garnered the strongest consensus with a weighted mean of [WM_Highest] ([Interpretation_Highest]), rated highest by [Respondent_Group_Highest] ([WM_Group_Highest]). Evaluators highlighted that [discuss specific usability feature, e.g., non-punitive supportive risk phrasing or clean display preference toggles].
- **Lowest-Rated Item:** Item [Item_Code_Lowest] ("[Statement_Text]") was rated lowest at [WM_Lowest] ([Interpretation_Lowest]) by [Respondent_Group_Lowest] ([WM_Group_Lowest]). Evaluators noted that [discuss usability friction, e.g., visual density or mobile table scrolling adjustments].

The strong usability ratings reflect deliberate design enhancements implemented in the prototype, specifically the elimination of punitive countdown banners in favor of supportive advising vocabulary. As argued by [Insert verified related literature author, Year], non-threatening feedback in student analytics fosters constructive student engagement rather than academic disengagement.
```

---

### 3.4 Reliability Narrative Shell (Table 4.5)

```markdown
### 4.5 Evaluation Results for Reliability

The evaluation outcomes for Reliability, presented in Table 4.5, assess system maturity, availability, fault tolerance, and recoverability. The characteristic achieved a pooled composite weighted mean of [WM_RE], corresponding to [Interpretation_RE].

Analysis of reliability subcriteria reveals:
- **Highest-Rated Item:** Item [Item_Code_Highest] achieved a composite score of [WM_Highest] ([Interpretation_Highest]), led by [Respondent_Group_Highest] ([WM_Group_Highest]). This indicates that [explain stability observation, e.g., zero crashes during evaluation walkthroughs].
- **Lowest-Rated Item:** Item [Item_Code_Lowest] recorded a mean of [WM_Lowest] ([Interpretation_Lowest]) from [Respondent_Group_Lowest] ([WM_Group_Lowest]), pointing to [discuss fault tolerance or input recovery feedback].

Significantly, technical evaluators in the IT Expert cohort specifically examined the system's fault tolerance (Item RE03), noting that [discuss how graceful handling of missing grades and non-numeric marks prevented application failure]. These results corroborate the architectural principles outlined by [Insert verified related literature author, Year] regarding robust input handling in administrative data pipelines.
```

---

### 3.5 Security Narrative Shell (Table 4.6)

```markdown
### 4.6 Evaluation Results for Security

Table 4.6 displays the evaluation results for Security, covering authenticity, confidentiality, integrity, non-repudiation, and accountability. Security attained a composite weighted mean of [WM_SE], interpreted as [Interpretation_SE].

Key evaluation points include:
- **Highest-Rated Item:** Item [Item_Code_Highest] ("[Statement_Text]") recorded the highest rating of [WM_Highest] ([Interpretation_Highest]), with the [Respondent_Group_Highest] group rating it at [WM_Group_Highest]. This reflects [explain security perception, e.g., reliable login gates and clear active-account profile identification].
- **Lowest-Rated Item:** Item [Item_Code_Lowest] ("[Statement_Text]") received [WM_Lowest] ([Interpretation_Lowest]) from [Respondent_Group_Lowest] ([WM_Group_Lowest]), reflecting [discuss evaluator suggestions, e.g., desire for multi-factor authentication in future production builds].

[If supported by security evaluation results, discuss whether evaluators perceived that UDM-RADAR enforces appropriate role-based session isolation.] In accordance with [Insert verified related literature author, Year], strict access partitioning is essential in educational decision-support tools to safeguard sensitive student academic records from unauthorized peer disclosure.
```

---

## 4. Cross-Group Comparison & Within-Group Synthesis (Tables 4.7 to 4.9)

```markdown
### 4.7 Cross-Stakeholder Comparative Analysis (Shared Items Only)

To examine how perceptions varied across stakeholder roles without introducing construct bias, Table 4.7 presents a cross-group comparison restricted exclusively to the 15 shared items.

Overall, the pooled shared-item composite weighted mean across all [Total N] evaluators was [Pooled_Shared_WM] ([Interpretation_Shared]). Comparing stakeholder perspectives across shared items:
- **BSIT Students:** Recorded a shared-item mean of [Students_Shared_WM] ([Interpretation_Students_Shared]), reflecting [discuss student experience on shared core items].
- **CCS Academic Users:** Recorded a shared-item mean of [Academic_Shared_WM] ([Interpretation_Academic_Shared]), highlighting [discuss faculty/adviser experience on shared core items].
- **IT Practitioners / Experts:** Recorded a shared-item mean of [IT_Shared_WM] ([Interpretation_IT_Shared]), offering technical assessment on [discuss expert view on shared core items].

[Discuss any notable perceptual variance between technical experts and student/academic users, contextualizing differences through system architectural traits.]

### 4.8 Within-Group Comprehensive Evaluations

Table 4.8 presents the comprehensive evaluations for each stakeholder group within its respective operational scope:
- **BSIT Students (19 items):** Evaluated their full portal experience (including the Grade Goal Calculator, distinction gauge, and supportive risk copy), achieving a group comprehensive mean of [Student_Comprehensive_WM] ([Interpretation_Student_Comp]).
- **CCS Academic Users (18 items):** Evaluated their full administrative experience (including section roster review, midterm visibility, and support intervention tracking), achieving a group comprehensive mean of [Academic_Comprehensive_WM] ([Interpretation_Academic_Comp]).
- **IT Practitioners / Experts (18 items):** Evaluated complete technical behavior (including portal boundary segregation, missing data fault tolerance, and session integrity), achieving a group comprehensive mean of [IT_Comprehensive_WM] ([Interpretation_IT_Comp]).

Because the item compositions reflect role-specific workflows, these three comprehensive group means are not ranked against one another, but demonstrate robust within-role satisfaction across all user profiles.

### 4.9 Master Characteristic Summary and Cross-Characteristic Synthesis

Table 4.9 summarizes the valid-response pooled composite weighted means and rankings across all five ISO/IEC 25010 characteristics in the master 25-item pool. UDM-RADAR achieved an overall Grand Master Composite Weighted Mean of [Grand_Pooled_WM], interpreted verbally as [Grand_Interpretation].

Ranking of characteristics:
1. **[Rank 1 Characteristic]:** Pooled Composite Mean = [WM_1] ([Interpretation_1])
2. **[Rank 2 Characteristic]:** Pooled Composite Mean = [WM_2] ([Interpretation_2])
3. **[Rank 3 Characteristic]:** Pooled Composite Mean = [WM_3] ([Interpretation_3])
4. **[Rank 4 Characteristic]:** Pooled Composite Mean = [WM_4] ([Interpretation_4])
5. **[Rank 5 Characteristic]:** Pooled Composite Mean = [WM_5] ([Interpretation_5])

[If supported by composite findings, conclude whether UDM-RADAR satisfies the software product quality criteria established under the ISO/IEC 25010 model.]
```
