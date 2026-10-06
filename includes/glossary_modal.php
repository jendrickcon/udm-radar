<?php
// includes/glossary_modal.php — Centralized Slide-In Academic Decision-Support Guide & Reference
?>

<!-- Floating Academic Guide & Help Button -->
<button type="button" id="udm-floating-help-btn" class="floating-help-btn" onclick="openGlossaryModal()" aria-label="Open Academic Guide &amp; Glossary" title="Academic Guide &amp; Glossary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
        <line x1="12" y1="17" x2="12.01" y2="17"></line>
    </svg>
</button>

<!-- Slide-in Drawer Overlay -->
<div class="guide-drawer-overlay" id="glossary-drawer-overlay" role="dialog" aria-modal="true" aria-labelledby="glossary-drawer-title" onclick="if(event.target===this) closeGlossaryModal();">
    <div class="guide-drawer" id="glossary-drawer">
        <!-- Header -->
        <div class="guide-drawer-header">
            <div>
                <h2 id="glossary-drawer-title" style="margin: 0 0 4px 0; font-size: 1.25rem; color: var(--text-dark); font-weight: 700;">UDM Academic Reference &amp; Guide</h2>
                <p style="margin: 0; font-size: 0.8rem; color: var(--text-gray);">Official grading standards, prediction policies, and monitoring principles.</p>
            </div>
            <button type="button" onclick="closeGlossaryModal()" aria-label="Close guide" style="background: none; border: none; cursor: pointer; color: var(--text-gray); padding: 4px; border-radius: 6px; display: flex; align-items: center; justify-content: center; transition: color 0.2s;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        <!-- Navigation Tabs -->
        <div class="guide-drawer-tabs" role="tablist">
            <button type="button" class="guide-tab-btn active" role="tab" aria-selected="true" id="tab-btn-quick" onclick="switchGuideTab('quick')">Quick Guide</button>
            <button type="button" class="guide-tab-btn" role="tab" aria-selected="false" id="tab-btn-grading" onclick="switchGuideTab('grading')">Official Grading Scale</button>
            <button type="button" class="guide-tab-btn" role="tab" aria-selected="false" id="tab-btn-risk" onclick="switchGuideTab('risk')">Risk &amp; Projections</button>
            <button type="button" class="guide-tab-btn" role="tab" aria-selected="false" id="tab-btn-about" onclick="switchGuideTab('about')">About Prototype</button>
        </div>

        <!-- Drawer Body -->
        <div class="guide-drawer-body">
            <!-- TAB 1: Quick Guide -->
            <div id="tab-pane-quick" class="guide-tab-content active" role="tabpanel" aria-labelledby="tab-btn-quick">
                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg>
                        Cumulative GWA vs. Projected Semester GWA
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 8px;">
                        <li>
                            <strong>Cumulative GWA (Historical):</strong> The credit-unit weighted average point of all completed semesters recorded in academic history. Computed solely from official final grades in completed terms.
                        </li>
                        <li>
                            <strong>Projected Semester GWA (In-Term):</strong> An early decision-support estimate of expected academic performance for the <em>current active term</em>, computed from current Preliminary submissions blended with historical trajectory.
                        </li>
                    </ul>
                </div>

                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        Departmental Decision-Support Scope
                    </h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-dark); line-height: 1.5;">
                        UDM-RADAR projections and risk flags are departmental decision-support metrics intended for formative advising, early faculty mentoring, and student goal-setting. They do not replace official transcripts, permanent scholastic records, or institutional decisions maintained by the University Registrar.
                    </p>
                </div>
            </div>

            <!-- TAB 2: Official Grading Scale (UDM 2025 Manual) -->
            <div id="tab-pane-grading" class="guide-tab-content" role="tabpanel" aria-labelledby="tab-btn-grading">
                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                        Official UDM Grading System (2025 Student Manual)
                    </h3>
                    <p style="margin: 0 0 8px 0; font-size: 0.82rem; color: var(--text-gray);">
                        In the Universidad de Manila system, <strong>4.00 is the highest numerical point grade</strong> and <strong>1.00 is the minimum passing grade</strong>. Scores 74% and below represent a Failed mark (0.00).
                    </p>
                    <table class="guide-table">
                        <thead>
                            <tr>
                                <th>Point Grade</th>
                                <th>Raw Percentage</th>
                                <th>Official Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>4.00</strong></td><td>99% – 100%</td><td>Excellent</td></tr>
                            <tr><td><strong>3.75</strong></td><td>97% – 98%</td><td>Outstanding</td></tr>
                            <tr><td><strong>3.50</strong></td><td>95% – 96%</td><td>Outstanding</td></tr>
                            <tr><td><strong>3.25</strong></td><td>92% – 94%</td><td>Outstanding</td></tr>
                            <tr><td><strong>3.00</strong></td><td>90% – 91%</td><td>Very Satisfactory</td></tr>
                            <tr><td><strong>2.75</strong></td><td>88% – 89%</td><td>Very Satisfactory</td></tr>
                            <tr><td><strong>2.50</strong></td><td>86% – 87%</td><td>Very Satisfactory</td></tr>
                            <tr><td><strong>2.25</strong></td><td>84% – 85%</td><td>Satisfactory</td></tr>
                            <tr><td><strong>2.00</strong></td><td>82% – 83%</td><td>Satisfactory</td></tr>
                            <tr><td><strong>1.75</strong></td><td>80% – 81%</td><td>Satisfactory</td></tr>
                            <tr><td><strong>1.50</strong></td><td>78% – 79%</td><td>Fair</td></tr>
                            <tr><td><strong>1.25</strong></td><td>76% – 77%</td><td>Fair</td></tr>
                            <tr><td><strong>1.00</strong></td><td>75%</td><td>Passed (Course Credit)</td></tr>
                            <tr><td><strong>0.00</strong></td><td>74% and below</td><td>Failed</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Official Grade Computation &amp; Term Weights
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px; font-size: 0.85rem;">
                        <li><strong>Official Final Formula:</strong> 30% Preliminary + 30% Midterm + 40% Pre-Final.</li>
                        <li><strong>In-Term Evaluations:</strong> Encoded as raw percentages (0%–100%).</li>
                        <li><strong>Semester Final Grades &amp; GWA:</strong> Recorded on the 1.00–4.00 point scale.</li>
                        <li><strong>Special Statuses:</strong> <code>INC</code> (Incomplete), <code>DO</code> (Dropped Officially), <code>DU</code> (Dropped Unofficially).</li>
                        <li><strong>Passing vs. Retention:</strong> While 1.00 is passing for individual course credit, grades below 1.75 indicate academic vulnerability and disqualify from Latin honors.</li>
                        <li><strong>Latin Honors Thresholds:</strong> Summa Cum Laude (3.76 – 4.00), Magna Cum Laude (3.51 – 3.75), Cum Laude (3.25 – 3.50).</li>
                    </ul>
                </div>
            </div>

            <!-- TAB 3: Risk & Projections -->
            <div id="tab-pane-risk" class="guide-tab-content" role="tabpanel" aria-labelledby="tab-btn-risk">
                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                        Academic Risk Classifications
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px;">
                        <div style="border-left: 3px solid var(--risk-low); padding: 8px 12px; background: rgba(5, 150, 105, 0.06); border-radius: 4px;">
                            <strong style="color: var(--risk-low); display: flex; align-items: center; gap: 6px;">
                                <span class="status-dot risk-low"></span> Low Risk (≥ 2.50)
                            </strong>
                            <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-gray);">
                                Corresponds to "Very Satisfactory" and higher. Strong, stable academic trajectory.
                            </p>
                        </div>
                        <div style="border-left: 3px solid var(--risk-mod); padding: 8px 12px; background: rgba(217, 119, 6, 0.06); border-radius: 4px;">
                            <strong style="color: var(--risk-mod); display: flex; align-items: center; gap: 6px;">
                                <span class="status-dot risk-mod"></span> Moderate Risk (1.75 – 2.49)
                            </strong>
                            <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-gray);">
                                Corresponds to "Satisfactory". Borderline trajectory; academic monitoring and advising recommended.
                            </p>
                        </div>
                        <div style="border-left: 3px solid var(--risk-high); padding: 8px 12px; background: rgba(220, 38, 38, 0.06); border-radius: 4px;">
                            <strong style="color: var(--risk-high); display: flex; align-items: center; gap: 6px;">
                                <span class="status-dot risk-high"></span> High Risk (&lt; 1.75)
                            </strong>
                            <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-gray);">
                                Corresponds to "Fair", barely "Passed", or failing history. Critical vulnerability; proactive faculty mentoring and support referrals advised.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        Prediction Provenance &amp; Completeness
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px; font-size: 0.85rem;">
                        <li><strong>AI-Based Projection:</strong> Computed via Decision Tree Regression when complete features (historical GWA, current Preliminary average, historical failed courses, irregular semesters) are available.</li>
                        <li><strong>Calculation-Based Estimate:</strong> Formulaic weighted blend (50% historical GWA + 50% current Preliminary average) applied whenever the ML service is unreachable or in fallback mode.</li>
                        <li><strong>Provisional Estimates:</strong> Derived when only partial data exists (e.g., historical GWA without current prelim grades, or prelim grades for first-semester students). Clearly labeled with a Provisional badge.</li>
                    </ul>
                </div>
            </div>

            <!-- TAB 4: About Prototype -->
            <div id="tab-pane-about" class="guide-tab-content" role="tabpanel" aria-labelledby="tab-btn-about">
                <div class="guide-card-box">
                    <h3 class="guide-card-title">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Prototype Environment &amp; Data Disclosure
                    </h3>
                    <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: var(--text-dark); line-height: 1.55;">
                        This system is an institutional evaluation prototype operating on <strong>synthetic and de-identified academic records</strong>. It demonstrates predictive analytics and academic decision-support workflows for the Universidad de Manila College of Computing Studies.
                    </p>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-dark); line-height: 1.55;">
                        Projections and risk scores are non-punitive, non-binding, and intended exclusively to facilitate early mentoring, tutoring, and retention support.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openGlossaryModal() {
    const overlay = document.getElementById('glossary-drawer-overlay');
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        const activeTab = overlay.querySelector('.guide-tab-btn.active');
        if (activeTab) activeTab.focus();
    }
}

function closeGlossaryModal() {
    const overlay = document.getElementById('glossary-drawer-overlay');
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        const trigger = document.getElementById('udm-floating-help-btn');
        if (trigger) trigger.focus();
    }
}

function switchGuideTab(tabId) {
    const tabs = document.querySelectorAll('.guide-tab-btn');
    const panes = document.querySelectorAll('.guide-tab-content');
    
    tabs.forEach(t => {
        t.classList.remove('active');
        t.setAttribute('aria-selected', 'false');
    });
    panes.forEach(p => p.classList.remove('active'));
    
    const activeBtn = document.getElementById('tab-btn-' + tabId);
    const activePane = document.getElementById('tab-pane-' + tabId);
    
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.setAttribute('aria-selected', 'true');
    }
    if (activePane) {
        activePane.classList.add('active');
    }
}

document.addEventListener('keydown', function(e) {
    const overlay = document.getElementById('glossary-drawer-overlay');
    if (!overlay || !overlay.classList.contains('open')) return;

    if (e.key === 'Escape') {
        closeGlossaryModal();
        return;
    }

    if (e.key !== 'Tab') return;

    const drawer = overlay.querySelector('.guide-drawer');
    if (!drawer) return;

    const focusableElements = Array.from(drawer.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter(element => element.getClientRects().length > 0 && getComputedStyle(element).visibility !== 'hidden');

    if (focusableElements.length === 0) return;

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    const focusIsInsideDrawer = drawer.contains(document.activeElement);

    if (e.shiftKey && (document.activeElement === firstElement || !focusIsInsideDrawer)) {
        e.preventDefault();
        lastElement.focus();
    } else if (!e.shiftKey && (document.activeElement === lastElement || !focusIsInsideDrawer)) {
        e.preventDefault();
        firstElement.focus();
    }
});
</script>
