# UDM-RADAR — Current Demonstration Playbook & Script

> [!NOTE]
> **Historical Demonstration Script Snapshot:** This playbook provides demonstration scripts established during earlier sprints.
> For current project status, portal workflows, and roadmap, consult:
> - **Canonical Roadmap:** [docs/ROADMAP.md](docs/ROADMAP.md)
> - **Implementation History:** [docs/IMPLEMENTATION_HISTORY.md](docs/IMPLEMENTATION_HISTORY.md)

This playbook provides defense-ready demonstration scripts for the three core stakeholder journeys, specifically structured to avoid overclaiming while showcasing UDM-RADAR's decision-support and intervention capabilities.

> [!NOTE]
> This report was produced primarily through static interface inspection. Visual, responsive, accessibility, and interaction findings require later browser or Playwright revalidation.

---

## 1. Ground Rules: What NOT to Claim

Before executing any presentation, ensure the entire capstone team adheres to these boundary statements:

1. **DO NOT CLAIM that UDM-RADAR is an official university grade registry.**  
   *State instead:* "UDM-RADAR is a departmental academic monitoring and decision-support prototype operating within the College of Computing Studies."
2. **DO NOT CLAIM that the machine learning model diagnoses student capabilities or guarantees outcomes.**  
   *State instead:* "The Decision Tree provides an estimated end-of-term trajectory based on four high-level indicators. It serves as a triage signal, not an authoritative academic verdict."
3. **DO NOT CLAIM that synthetic dataset metrics (e.g., R² = 0.90) prove real-world predictive accuracy.**  
   *State instead:* "The model was trained on 150 synthetic baseline records to validate the end-to-end data pipeline. Real-world validation requires longitudinal data governance under ICTO approval."
4. **DO NOT CLAIM that Grade Batch submissions alter university transcripts.**  
   *State instead:* "Grade batches record term percentage progress (Prelim, Midterm, Pre-Final) for early-warning tracking only. Final grades and official corrections remain governed by the University Registrar."
5. **DO NOT CLAIM that the system replaces faculty advising.**  
   *State instead:* "The system automates administrative tracking so faculty can focus on timely, individualized pedagogical interventions."

---

## 2. Demonstration Scenario 1: Student Academic Self-Awareness

### Objective
Demonstrate how an at-risk student discovers their academic trajectory, understands contributing risk factors, calculates required scores, and engages in the intervention loop.

### Account & Preconditions
- **Test Account:** `Student Scenario A` (e.g., Student ID `S-268` or active test student)
- **Status:** 3rd Year BSIT, 8 enrolled current subjects, 2 subjects with Prelim scores below 75%, historical GWA = 2.75.
- **Database Staging Requirement:** At least one support referral must be in `action_taken` status so the notice appears in Tab 2 of `student/feedback.php`.

### Step-by-Step Script & Narrative
1. **Login & Landing (`student/index.php`):**
   - *Presenter:* "When Student Scenario A logs in, the portal immediately displays an 'Action Required' banner highlighting that 2 current subjects require academic attention."
   - *Show:* The Current Term Snapshot cards (Enrolled Subjects, Grades Available, Awaiting Grades).
2. **Dashboard Review (`student/dashboard.php`):**
   - *Presenter:* "Navigating to the Analytics Dashboard, the student sees their Cumulative GWA (historical completed terms) alongside their Projected Final GWA."
   - *Show:* The prediction source pill labeled 'AI-Based Prediction' (or Calculation-Based Estimate) with its explanatory tooltip.
   - *Show:* Risk Factor Analysis: Highlight the specific reasons displayed (e.g., 'Failing prelim score in Subject X', 'Trajectory drop of 0.45 points').
3. **Actionable Exploration (Subject Triage & Calculator):**
   - *Presenter:* "In the Subject Triage table, the student inspects their in-progress courses. Clicking the Calculator button launches the Grade Goal Calculator."
   - *Action:* Demonstrate setting a target grade of `2.50` (86%) and show how the calculator computes the exact Midterm (88%) and Pre-Final (90%) percentages required to achieve it.
4. **Intervention & Notice Acknowledgment (`student/feedback.php`):**
   - *Presenter:* "Under Feedback & Support, the student navigates to 'Academic Support Notices'. Here they review an official academic advisory issued by their instructor."
   - *Show:* The instructor's specific guidance note.
   - *Show:* The disclaimer: *"Acknowledging this notice confirms receipt... It does not represent an agreement to a failing grade."*
   - *Action:* Click **"Acknowledge Receipt"**. Verify status updates cleanly to "Receipt Acknowledged".

---

## 3. Demonstration Scenario 2: Faculty Triage & Early Intervention

### Objective
Demonstrate how an instructor reviews class progress, identifies struggling students within their own teaching load, and issues targeted academic support notices.

### Account & Preconditions
- **Test Account:** `Faculty Account B` (Faculty User ID `302` or assigned instructor)
- **Status:** Assigned 4 section loads (e.g., Networking / Systems Analysis). Has assigned student support referrals.

### Step-by-Step Script & Narrative
1. **Faculty Home & Workload Alert (`faculty/index.php`):**
   - *Presenter:* "Upon login, Faculty Account B sees a high-level Teaching Snapshot and an amber warning alert indicating which assigned sections have incomplete Preliminary grades."
   - *Show:* The Assigned Classes Preview table showing `% Prelim encoded` progress bars.
2. **Section Overview & Roster Triage (`faculty/dashboard.php`):**
   - *Presenter:* "On the Section Overview dashboard, the instructor sees a top banner highlighting open student concerns and support reviews requiring attention."
   - *Action:* Click "View Students" on section `IT-35`.
   - *Show:* The interactive roster tab `My Class Performance`. Point out the difference between low-performing students and unencoded grades.
3. **Issuing an Academic Support Notice (`faculty/feedback.php`):**
   - *Presenter:* "The instructor switches to the Concerns & Reports workspace and opens the 'Academic Support Referrals' tab."
   - *Show:* The referral card displaying the student's program risk level alongside their subject-specific prelim grade.
   - *Action:* Click **"Issue Notice to Student"**.
   - *Action:* Enter sample guidance: *"Please attend consultation hours this Thursday at 2:00 PM to review your lab exercises."*
   - *Action:* Submit the notice. Verify the referral status transitions from `needs_review` to `action_taken`.

---

## 4. Demonstration Scenario 3: Administrative Oversight & Governance

### Objective
Demonstrate how the College Administrator monitors cohort risk distributions, oversees support cases, and maintains audit trails without bypassing institutional boundaries.

### Account & Preconditions
- **Test Account:** `Administrator Account A` (Admin User ID `1`)
- **Status:** Full administrative access to student directory, batch approvals, support cases, and audit logs.

### Step-by-Step Script & Narrative
1. **Administrative Command Center (`admin/index.php`):**
   - *Presenter:* "The Administrator Dashboard leads with an Action Required banner that unifies open feedback tickets, pending grade batches, and active support cases."
   - *Show:* Risk distribution donut chart and section mean GWA bar chart.
   - *Show:* Filterable Student Directory table. Demonstrate filtering by section (`IT-31`) and risk (`HIGH`).
2. **Activity & Approvals Workspace (`admin/activity.php`):**
   - *Presenter:* "Opening the Activity Workspace, the administrator navigates between four dedicated tabs."
   - *Show:* Tab 2 (Approvals): Point out the structural separation between **Pending Grade Batches** (faculty term percentages) and **Pending Record Corrections** (individual grade updates).
   - *Show:* Tab 3 (Academic Support Cases): Display the program-level parent case. Show how it tracks child referrals across different instructors and logs student acknowledgment timestamps.
3. **Audit History & Reporting (`admin/activity.php` / `admin/analytics.php`):**
   - *Presenter:* "Every administrative decision is logged in the immutable change history."
   - *Show:* Tab 4 (History & Audit): Review the chronological table logging user ID, action type, old value, new value, and timestamp.
   - *Show:* Export capabilities: Demonstrate one-click generation of the Program Risk Roster CSV export.

---

## 5. Defense Failure-Point Mitigation Matrix

| Potential Live Failure | What Panel Sees | Recommended Defense Response |
|---|---|---|
| **Batch Prediction button fails (403 CSRF error)** | Error alert: *"Session expired"* or 403 in network tab | *"Prediction execution is secured via CSRF tokens. For this live demonstration, predictions were pre-calculated during nightly batch processing."* (Refer to `BUG-CUR-01` in backlog) |
| **Blank course/program values in directory** | Table displays `—` under Course column | *"Historical course fields are currently normalized under WP-6 migration; student records are grouped by academic year and section."* (Refer to `BUG-CUR-02`) |
| **Panel asks: 'Why doesn't this change registrar records?'** | Admin approval screen shows grade applied | *"By design, UDM-RADAR maintains strict boundary separation. As stated in our architecture disclaimers, the system acts as a decision-support layer. Official registrar modifications follow formal University ICTO protocols."* |
| **Panel asks: 'How accurate is the Decision Tree?'** | Model metrics show R² = 0.90 | *"We transparently declare that this prototype model was trained on 150 synthetic records to establish pipeline mechanics. Our architecture includes an AI Model Governance module specifically designed to train and promote candidate models once longitudinal registrar data is approved."* |
