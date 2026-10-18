# UDM-RADAR — ISO/IEC 25010 Evaluation Question Item Matrix

> **Governance Standard:** This matrix operationalizes each evaluation item into an observable interface feature, guided evaluation scenario, verifiable intended evidence, and technical boundary. It governs the construction of the Google Forms instrument, the Excel calculation schema, and the Chapter 4 analytical tables.

---

## 1. Summary of Question Allocation

* **Master Pool Size:** 25 Unique Likert Items
* **Core Shared Items:** 15 Items (Answered by all respondent groups)
* **Student-Specific Path:** 4 Items (Total Visible: 19)
* **Academic User-Specific Path:** 3 Items (Total Visible: 18)
* **IT Expert-Specific Path:** 3 Items (Total Visible: 18)

```
Distribution by Characteristic:
- Functional Suitability: 5 items (2 Shared, 1 Student, 1 Academic User, 1 IT Expert)
- Performance Efficiency: 4 items (2 Shared, 1 Student, 1 Academic User, 0 IT Expert)
- Usability:              6 items (4 Shared, 2 Student, 0 Academic User, 0 IT Expert)
- Reliability:            4 items (3 Shared, 0 Student, 0 Academic User, 1 IT Expert)
- Security:               6 items (4 Shared, 0 Student, 1 Academic User, 1 IT Expert)
Total:                   25 Unique Items
```

* **Evaluator Response Scale:** `5 = Strongly Agree`, `4 = Agree`, `3 = Neither Agree nor Disagree`, `2 = Disagree`, `1 = Strongly Disagree`.
* **Weighted-Mean Interpretations:** `4.30–5.00` Excellent, `3.50–4.29` Very Satisfactory, `2.70–3.49` Satisfactory, `1.90–2.69` Fair, `1.00–1.89` Poor *(All provisional pending adviser confirmation)*.

---

## 2. Master Item Specification Matrix

### 2.1 Functional Suitability (5 Items)

#### FS01 — Functional Completeness (Shared)
* **Exact Statement:** "The system provides the necessary functions to view and monitor recorded academic grades and status."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Academic summary cards, multi-term grade tables, and portal navigation views.
* **Guided Scenario:** Evaluator navigates to their home dashboard and grades view, inspecting current term records, preliminary percentages, and historical grade points.
* **Intended Evidence:** Evaluator confirms that academic records, subject titles, credit units, and status indicators are accessible and readable.
* **Limitation / Boundary:** Evaluates read-only prototype grade visibility; does not assess registrar database writing or official transcript generation.

#### FS02 — Functional Correctness (Shared)
* **Exact Statement:** "The displayed GWA matched the expected result in the provided evaluation scenario."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Unit-weighted Grade Weighted Average (GWA) calculation displayed on dashboard cards.
* **Guided Scenario:** Evaluator is provided a reference test scenario (e.g., three 3-unit subjects with grades `1.75`, `2.00`, and `2.25`) and inspects the displayed cumulative GWA (`2.00`).
* **Intended Evidence:** Evaluator confirms the displayed numeric GWA matches the exact arithmetic unit-weighted average of the provided test marks.
* **Limitation / Boundary:** Confirms mathematical correctness within the controlled evaluation scenario; does not certify complete institutional historical datasets.

#### FS03 — Functional Appropriateness (Student Path)
* **Exact Statement:** "The Grade Goal Calculator helps in estimating the term grade required to pursue a target academic mark."
* **Visibility:** BSIT Student
* **Observable Feature:** Interactive Grade Goal Calculator in the Student Dashboard.
* **Guided Scenario:** Student selects an unlocked course, chooses a target point grade from the dropdown (e.g., `2.00`), and inspects the calculated percentage required in remaining terms.
* **Intended Evidence:** Student observes that setting a reachable goal outputs a clear required percentage, while unattainable goals (>100%) trigger protective advisories.
* **Limitation / Boundary:** Advisory calculation tool only; does not alter recorded instructor marks or guarantee passing standing.

#### FS04 — Functional Appropriateness (Academic User Path)
* **Exact Statement:** "The academic support workflow allows recording consultation notes and intervention reasons for students needing advising."
* **Visibility:** CCS Academic User
* **Observable Feature:** Faculty support intervention modal and advisory case tracking.
* **Guided Scenario:** Faculty user opens an intervention modal for a student in triage, selects an advisory reason category, enters mentoring notes, and saves the support record.
* **Intended Evidence:** Academic user verifies that the system captures and displays structured intervention reasons and consultation notes.
* **Limitation / Boundary:** Records internal academic support notes; does not trigger official university probation, disciplinary action, or registrar status changes.

#### FS05 — Functional Completeness (IT Expert Path)
* **Exact Statement:** "The application enforces distinct functional boundaries and appropriate data segregation across the Student, Faculty, and Admin portals."
* **Visibility:** IT Practitioner or Expert
* **Observable Feature:** Multi-portal routing, role session enforcement, and unauthorized access redirection.
* **Guided Scenario:** Technical evaluator reviews authenticated routes, testing access to Admin/Faculty controllers using standard student credentials.
* **Intended Evidence:** Evaluator confirms that administrative operations and peer records cannot be viewed or manipulated outside authorized role contexts.
* **Limitation / Boundary:** Evaluates application-level access control (RBAC); does not evaluate server-level hypervisor or physical infrastructure isolation.

---

### 2.2 Performance Efficiency (4 Items)

#### PE01 — Time Behaviour (Shared)
* **Exact Statement:** "The application pages and dashboards loaded promptly during the evaluation session."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Page transitions across dashboard, grades, and settings.
* **Guided Scenario:** Evaluator clicks through primary navigation links and loads multi-panel dashboard views during normal evaluation use.
* **Intended Evidence:** Perceived rapid page rendering without frozen screens or excessive loading states.
* **Limitation / Boundary:** Reflects perceived responsiveness under local/staging server conditions; does not reflect high-latency public internet constraints.

#### PE02 — Capacity (Shared)
* **Exact Statement:** "The system displayed multi-subject grade lists without noticeable slowdown."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Multi-term academic subject history tables and grade display grids.
* **Guided Scenario:** Evaluator opens a full academic load containing 8 or more subjects and scrolls through course details, grades, and units.
* **Intended Evidence:** Tables render completely without horizontal stutter, browser freeze, or layout shifting.
* **Limitation / Boundary:** Evaluates standard semester curriculum capacity (21–26 units); does not evaluate multi-thousand record stress limits.

#### PE03 — Time Behaviour (Student Path)
* **Exact Statement:** "The Grade Goal Calculator recalculated required grades immediately upon changing the target point."
* **Visibility:** BSIT Student
* **Observable Feature:** Client-side DOM calculation in Grade Goal Calculator modal.
* **Guided Scenario:** Student toggles target grade points (e.g., from `2.00` to `1.75` to `2.50`) and observes calculation speed.
* **Intended Evidence:** Immediate client-side update of required scores without page refreshes or spinner delays.
* **Limitation / Boundary:** Client execution speed dependent on user browser device capability.

#### PE04 — Time Behaviour (Academic User Path)
* **Exact Statement:** "The class section roster and grade monitoring views loaded section records without disruptive latency during review."
* **Visibility:** CCS Academic User
* **Observable Feature:** Faculty dashboard class section selector, student roster tables, and grade inspection views.
* **Guided Scenario:** Faculty user toggles between assigned course sections and reviews student enrollment rosters and Midterm grade columns.
* **Intended Evidence:** Section rosters populate promptly, enabling efficient academic review.
* **Limitation / Boundary:** Evaluates normal departmental section size (~40 students); does not reflect concurrent campus-wide final-grading submission peaks.

---

### 2.3 Usability (6 Items)

#### US01 — Appropriateness Recognizability (Shared)
* **Exact Statement:** "The system interface clearly indicates the purpose and role of the portal being accessed."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Portal header badges, role-labeled breadcrumbs, and sidebar branding.
* **Guided Scenario:** Evaluator logs in and identifies whether they are operating in the Student, Faculty, or Admin environment.
* **Intended Evidence:** Clear visual orientation confirming the active portal role upon initial page view.
* **Limitation / Boundary:** Evaluates visual labeling; does not measure user institutional policy comprehension.

#### US02 — Learnability (Shared)
* **Exact Statement:** "The layout, navigation menus, and information cards are straightforward to learn and navigate."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Standardized sidebar navigation, stat metric cards, and Academic Guide drawer.
* **Guided Scenario:** Evaluator locates grades, settings, and guidance documentation without external prompting.
* **Intended Evidence:** User successfully navigates the interface on first use without operational confusion.
* **Limitation / Boundary:** Basic interface learnability; does not replace formalized institutional user onboarding.

#### US03 — User Error Protection (Shared)
* **Exact Statement:** "The system provides clear feedback or notices when input is invalid or an action cannot be completed."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Form validation prompts, locked field indicators, and unattainable goal alert dialogues.
* **Guided Scenario:** Evaluator attempts to enter an invalid score or select a goal requiring >100% in remaining terms.
* **Intended Evidence:** Application presents an informative modal/banner explaining why the action was rejected.
* **Limitation / Boundary:** Evaluates client/server form guards; does not assess database transaction rollback mechanics.

#### US04 — User Interface Aesthetics (Student Path)
* **Exact Statement:** "The system clearly presented the configured academic-distinction guidance ranges."
* **Visibility:** BSIT Student
* **Observable Feature:** Student Dashboard Academic Distinction half-doughnut gauge and three-item legend.
* **Guided Scenario:** Student inspects the distinction gauge displaying historical cumulative GWA and visual boundary markers for Dean's List (`3.25`), Magna Cum Laude (`3.50`), and Summa Cum Laude (`3.75`).
* **Intended Evidence:** Student can identify where their GWA sits relative to configured distinction thresholds without visual confusion.
* **Limitation / Boundary:** Presents advisory distinction guidance markers; does not constitute official registrar Latin honor certification.

#### US05 — Operability (Student Path)
* **Exact Statement:** "Academic risk indicators are expressed in clear, supportive, and non-punitive language."
* **Visibility:** BSIT Student
* **Observable Feature:** Student Dashboard overall risk card, triage focus area banners, and supportive disclaimers.
* **Guided Scenario:** Student reviews an advisory risk indicator (e.g., Moderate Risk: `1.75–2.49`) and reads the accompanying contextual notes.
* **Intended Evidence:** Information is perceived as mentoring guidance ("Additional grades may change this estimate") rather than disciplinary warnings or dismissal threats.
* **Limitation / Boundary:** Evaluates linguistic framing; does not eliminate underlying academic performance criteria.

#### US06 — Accessibility (Shared)
* **Exact Statement:** "The application provides useful display options, including text-size adjustment, Light and Dark appearances, reduced-motion preference, and optional link underlining."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Settings "Display & Accessibility" disclosure panel.
* **Guided Scenario:** Evaluator opens Settings, expands Display & Accessibility, toggles text size (Default/Large/XL), switches between Light and Dark themes, and enables link underlines.
* **Intended Evidence:** Interface adapts immediately without layout clipping, text overlapping, or horizontal container breakage.
* **Limitation / Boundary:** Evaluates observable application display controls; does not certify complete WCAG 2.1 AA/AAA compliance.

---

### 2.4 Reliability (4 Items)

#### RE01 — Maturity (Shared)
* **Exact Statement:** "The application performed its functions consistently throughout the evaluation session without crashing."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Application runtime stability during scenario execution.
* **Guided Scenario:** Evaluator performs a sequence of 5 or more portal tasks (browsing grades, toggling settings, viewing rosters).
* **Intended Evidence:** Zero fatal PHP errors, zero white-screen crashes, and zero unexpected session terminations.
* **Limitation / Boundary:** Measures stability during the evaluation window; does not certify 365-day continuous production MTBF.

#### RE02 — Availability (Shared)
* **Exact Statement:** "Application pages, navigation routes, and dashboard views were accessible whenever requested."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Web portal endpoint responsiveness across all tested paths.
* **Guided Scenario:** Evaluator clicks across all navigation links within their authorized portal.
* **Intended Evidence:** Every requested endpoint returns successful HTTP 200 views without 404 or 500 server errors.
* **Limitation / Boundary:** Evaluates availability on the staging testbed; does not evaluate external ISP routing.

#### RE03 — Fault Tolerance (IT Expert Path)
* **Exact Statement:** "The system handles missing grades and non-numeric marks without application failure or injecting unintended zero values."
* **Visibility:** IT Practitioner or Expert
* **Observable Feature:** Missing grade (`N/A`) indicators, non-numeric mark handling (`INC`, `DRP`), and GWA calculation scripts.
* **Guided Scenario:** Technical evaluator inspects student profiles with incomplete (`INC`) grades or pending terms, verifying that missing values are excluded from GWA denominators.
* **Intended Evidence:** The system renders graceful `N/A` states and omits non-numeric marks from weighted averages without zero-injection or calculation failure.
* **Limitation / Boundary:** Evaluates application input resilience; does not modify institutional grading completion policies.

#### RE04 — Recoverability (Shared)
* **Exact Statement:** "The system allows users to continue normal operation after entering invalid input or canceling an action."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Modal dismissal buttons, form reset triggers, and input cancellation workflows.
* **Guided Scenario:** Evaluator opens an intervention modal or input field, enters partial data, clicks Cancel, and re-navigates.
* **Intended Evidence:** User returns cleanly to previous operational dashboard without lingering locks or corrupted form state.
* **Limitation / Boundary:** Evaluates UI and session recovery; does not evaluate server-side database disaster restoration.

---

### 2.5 Security (6 Items)

#### SE01 — Authenticity (Shared)
* **Exact Statement:** "Access to the portal requires valid user authentication credentials."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Portal login form, session credential validation, and unauthenticated redirection.
* **Guided Scenario:** Evaluator attempts to navigate directly to an internal URL (e.g., `/student/dashboard.php`) without an active session.
* **Intended Evidence:** System redirects unauthenticated request directly to `login.php` with an access notice.
* **Limitation / Boundary:** Evaluates application login gate; does not assess hardware-token or Multi-Factor Authentication.

#### SE02 — Confidentiality (Shared)
* **Exact Statement:** "The system restricts users to information appropriate to their assigned role."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Role-based view containment and URL authorization checks.
* **Guided Scenario:** Evaluator observes that only role-appropriate pages and information are accessible during the guided evaluation scenario.
* **Intended Evidence:** Evaluator observes that student accounts only access personal records, while faculty accounts access assigned sections.
* **Limitation / Boundary:** Evaluator perception during guided scenario; automated technical penetration and authorization tests remain separate evidence.

#### SE03 — Integrity (Shared)
* **Exact Statement:** "Academic records and displayed grades remain protected against unauthenticated or unauthorized modification."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Read-only student grade displays and authenticated faculty entry forms.
* **Guided Scenario:** Evaluator inspects student grade tables to verify that displayed point grades cannot be edited in place.
* **Intended Evidence:** Student interface provides read-only presentation controls without client-side data mutation vectors.
* **Limitation / Boundary:** Application interface integrity; does not evaluate backend database administrator permissions.

#### SE04 — Non-repudiation (Academic User Path)
* **Exact Statement:** "Where acknowledgement records are available, the system preserves evidence of the recorded action and time."
* **Visibility:** CCS Academic User
* **Observable Feature:** Academic support notice timeline, faculty creation metadata, and student receipt acknowledgment timestamp.
* **Guided Scenario:** Academic user inspects an acknowledged support notice, reviewing the logged faculty sender, recorded reason, creation timestamp, and student receipt time.
* **Intended Evidence:** System displays an immutable timestamped log of when the notice was dispatched and when the student acknowledged receipt.
* **Limitation / Boundary:** Preserves internal application audit logs; does not establish legal digital signatures or cryptographic non-repudiation.

#### SE05 — Integrity (IT Expert Path)
* **Exact Statement:** "The application protects sensitive actions and state-changing requests through session controls and token verification."
* **Visibility:** IT Practitioner or Expert
* **Observable Feature:** Session cookies (`HttpOnly`, `SameSite`), CSRF token verification, and POST-only state mutations.
* **Guided Scenario:** Technical evaluator inspects form submission headers and network payloads during batch prediction and support notice creation.
* **Intended Evidence:** Verification that state-changing requests require active session tokens and reject unauthenticated cross-site requests.
* **Limitation / Boundary:** Web application layer security controls; does not assess network transport eavesdropping or physical data center security.

#### SE06 — Accountability (Shared)
* **Exact Statement:** "The system clearly identifies the currently signed-in account and portal role during use."
* **Visibility:** Shared (All Groups)
* **Observable Feature:** Top-right user profile header, user display name, and portal role badge.
* **Guided Scenario:** Evaluator logs in under different test accounts (Student, Faculty, Admin) and verifies visible identity indicators.
* **Intended Evidence:** The respondent can immediately identify which account and portal role is currently active.
* **Limitation / Boundary:** Evaluates visible application accountability; does not establish external identity-provider assurance.
