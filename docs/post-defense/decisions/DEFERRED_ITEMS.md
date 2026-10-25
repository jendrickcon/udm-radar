# Deferred Items and Future Scope Register — UDM-RADAR

**Document Version:** 1.0.0
**Accepted Baseline Git SHA:** `a79fb4838c5e2bc163cf943cda20afba9946d26b`
**Purpose:** Formal record of prospective features, schema adjustments, and institutional extensions explicitly deferred from the current prototype baseline.

---

## 1. Explicitly Deferred Items

| # | Proposed Item / Feature | Initial Source / Rationale | Deferral Reason & Boundary | Revisit Condition |
|---|---|---|---|---|
| **DEF-01** | **Migration 011** | Drafted during early academic support prototyping. | Migration 011 is excluded from the accepted migrations 000 to 010 baseline and remains unapproved. Core support workflows were satisfied via Migrations 009 & 010. | Requires a separately approved work package and explicit adviser authorization. |
| **DEF-02** | **Automatic INC Expiry or Conversion** | Proposal to automatically convert incomplete marks (`INC`) to `0.00` after one calendar year. | **Strict Institutional Boundary:** UDM academic manual delegates grade conversion strictly to the College Dean and University Registrar. Automated expiry violates academic due process. | Pending official registrar automation contract. |
| **DEF-03** | **Direct Public `.xlsx` PEF/CC Download** | Student request to download the uncompiled Curriculum Checklist Excel spreadsheet. | Original Excel template contains proprietary registrar cell formulas, conditional formatting macros, and institutional signatures. Direct spreadsheet editing invites student tampering. | Public contract remains strictly legal folio PDF with internal private COM engine. |
| **DEF-04** | **Cross-Faculty Evaluative Rankings** | Administrative analytics sorting faculty by student pass rates or average GWA. | **Strict Ethics Boundary:** Conflates student academic risk with teacher performance evaluation; violates faculty union standards. | Permanently prohibited in UDM-RADAR. |
| **DEF-05** | **Live University LMS / Moodle Integration** | Real-time synchronization with institutional Moodle attendance and quiz logs. | Scope delimitation: UDM-RADAR is bounded to registrar curriculum records and encoded term grades. Live API access to production LMS was not authorized by institutional IT. | Post-defense institutional enterprise rollout. |
| **DEF-06** | **Automated Unilateral Student Email Dispatch** | Automated notification dispatch upon high-risk prediction generation. | **Human-in-the-Loop Principle:** Prevents automated panic and false alarms. Faculty review and structured reason selection must precede notice delivery. | Retain current human-reviewed faculty referral workflow. |
| **DEF-07** | **Multi-College Expansion** | Expanding curriculum data beyond the College of Computing Studies (CCS). | Chapter 1 scope is strictly Third-Year BSIT students. Cross-college curriculum trees require independent department chair approvals. | Subsequent capstone phases. |
| **DEF-08** | **Dedicated Case Closure Timestamp Column** | Column `closed_at` on `support_case_referrals` to support MTR-21. | Current baseline schema tracks closure via case notes and status history tables rather than a dedicated timestamp column in the referrals table. | Phase 4 intervention schema evaluation. |
