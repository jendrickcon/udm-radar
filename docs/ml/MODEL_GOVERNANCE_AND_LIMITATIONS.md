# UDM-RADAR — Machine Learning Model Governance & Operational Limitations

> [!NOTE]
> **Architecture Governance Standard:** This document governs active model baseline preservation, candidate training boundaries, and operational limitations established under WP-7.
> For overall package status and roadmap planning, consult:
> - **Canonical Roadmap:** [docs/ROADMAP.md](../ROADMAP.md)
> - **Implementation History:** [docs/IMPLEMENTATION_HISTORY.md](../IMPLEMENTATION_HISTORY.md)

**Document Status:** Approved Architecture Standard  
**Work Package Alignment:** WP-7 (Prediction Remediation, Workflow Integrity & Model Governance)  
**Target Component:** `python_ml/` service, `api/model_governance.php`, Admin Governance Console  
**Date:** October 2026

---

## 1. Executive Summary & Core Principle

UDM-RADAR employs a machine learning decision-support service to assist faculty and administrators in identifying students who may benefit from academic interventions. The machine learning pipeline is strictly **advisory and non-punitive**; it does not replace official grades, academic deliberations, or faculty evaluation.

To ensure ethical accountability, data privacy, and operational stability, the repository enforces **strict model governance boundaries**. The active baseline model is protected against unvetted promotion or accidental drift.

---

## 2. Active Model Baseline & Preservation Rules

### 2.1 Baseline Model Status
- **Active Model File:** `python_ml/model.pkl`
- **Active Metrics Manifest:** `python_ml/model_metrics.json`
- **Algorithm:** Decision Tree Regressor (`sklearn.tree.DecisionTreeRegressor`)
- **Trained Feature Contract:**
  - `historical_gwa` (float, [1.00, 5.00])
  - `current_prelim_point_avg` (float, [1.00, 5.00]) — bridged to internal `current_prelim_avg` for legacy compatibility
  - `failed_subjects_count` (int, non-negative)
  - `irregular_semesters` (int, non-negative)
- **Target Variable:** `final_gwa` (float, [1.00, 5.00])

### 2.2 Preservation Invariant
1. **Never Overwrite in Development/Audit Phases:** `python_ml/model.pkl` and `database/udm_radar.sql` must never be casually overwritten or modified during routine testing, data migration, or feature development.
2. **Read-Only Production Integrity:** In production environments, the active model file must be owned by a restricted service user with read-only permissions for the runtime web server.
3. **No Automatic Promotion:** Candidate training pipelines are strictly decoupled from active inference. Creating or testing a candidate model must never mutate the active model in place.

---

## 3. Training Data Provenance & Synthetic Limitations

### 3.1 Historical Training Dataset
- **File:** `python_ml/training_data.csv`
- **Volume:** 150 records
- **Origin & Nature:** Synthetic cohort generated for academic prototype evaluation and architectural validation.
- **Data Privacy:** Contains **zero** Personally Identifiable Information (PII), zero real student numbers, and zero institutional identity records.

### 3.2 Known Limitations of the Baseline Dataset
1. **Sample Size:** 150 rows represent a demonstration proof-of-concept, sufficient for verifying regression pipeline mechanics but not indicative of cross-institutional generalizability.
2. **Distribution Assumptions:** Synthetic relationships model standard curricular risk curves (higher failed subject counts correlate with higher predicted GWA / elevated risk), but edge-case academic paths (e.g., cross-enrollees, shifters) require real institutional calibration before formal deployment.
3. **Institutional Boundary:** Any candidate trained on unverified or non-institutional data must be flagged with `is_synthetic: true` in its metrics manifest.

---

## 4. Candidate Model Isolation & Staging Architecture

### 4.1 Staged Directory Isolation
Candidate models created via administrative workflows are strictly quarantined from the active serving path:
- **Staging Directory:** `python_ml/candidates/`
- **Staged Model Artifact:** `python_ml/candidates/candidate_model.pkl`
- **Staged Metrics Manifest:** `python_ml/candidates/candidate_metrics.json`
- **Git Tracking:** All `.pkl` and `.json` artifacts inside `candidates/` are excluded by `.gitignore` to prevent repository bloat and accidental version pollution.

### 4.2 Data Validation & Ingestion Guards
When retraining or evaluating candidates via `train_candidate`:
1. **File Format:** Strict CSV validation (`text/csv` mime type, `.csv` extension, max 5MB).
2. **Feature Schema Completeness:** Must contain exact required columns: `historical_gwa`, `current_prelim_point_avg`, `failed_subjects_count`, `irregular_semesters`, `final_gwa`.
3. **Missing Value Rejection:** No rows with `NaN`, null, or missing feature values are accepted.
4. **Range & Boundary Constraints:**
   - `historical_gwa`: $[1.00, 5.00]$
   - `current_prelim_point_avg`: $[1.00, 5.00]$
   - `failed_subjects_count`: $\ge 0$
   - `irregular_semesters`: $\ge 0$
   - `final_gwa`: $[1.00, 5.00]$
5. **Minimum Data Volume:** A minimum of **50 valid, complete rows** is mandatory. If valid rows $< 50$ after cleaning, training aborts with HTTP 400.

---

## 5. Security & Access Control Boundaries

### 5.1 Defense-in-Depth Architecture
The Flask machine learning microservice (`127.0.0.1:5000`) is never directly exposed to client browsers or public networks.

```
+------------------+         CSRF + Session Cookie         +---------------------------+
|  Admin Browser   | -----------------------------------> | api/model_governance.php   |
|  (User Interface)| <----------------------------------- | (PHP Backend Gateway)      |
+------------------+             JSON Response             +---------------------------+
                                                                         |
                                                            Server-Side  |  X-API-Key: <SECRET>
                                                            Internal Net |  HTTP/1.1 POST
                                                                         v
                                                           +---------------------------+
                                                           | python_ml/app.py          |
                                                           | (Flask Service: 5000)     |
                                                           +---------------------------+
```

### 5.2 Security Enforcements
1. **No Client-Side Secrets:** Browser JavaScript never receives, stores, or transmits the ML governance shared secret.
2. **Fail-Closed Secret Management:** `getMlGovernanceSecret()` fails closed (HTTP 503) if `UDM_RADAR_ML_SECRET` is unset or empty. No hardcoded fallback strings are permitted in production or version control.
3. **Role & Session Gating:** Every call to `api/model_governance.php` enforces `requireRole('admin')` and checks a valid 64-character CSRF token.
4. **Timing-Safe Key Verification:** Flask authenticates incoming proxy requests via `hmac.compare_digest`.
5. **Host Binding:** Flask is bound exclusively to `127.0.0.1` (`localhost`), blocking direct external network ingress.

---

## 6. Formal Model Promotion Workflow

Promotion of a candidate model to active status must adhere to institutional governance:

1. **Candidate Training:** Staged inside `python_ml/candidates/` with full provenance logs.
2. **Automated Metric Evaluation:** Mean Absolute Error (MAE), Root Mean Squared Error (RMSE), $R^2$, and Risk Classification Accuracy are calculated on a 20% holdout test set.
3. **Human Administrative Review:** An authorized administrator reviews candidate metrics alongside the active baseline via the Admin Governance Console.
4. **Atomic Promotion with Rollback Backup:**
   - If promoted, the existing active model and metrics are automatically archived with versioned timestamps (`model_backup_YYYYMMDD_HHMMSS.pkl`).
   - The candidate is atomically moved to `python_ml/model.pkl`.
   - The candidate metrics become the new active `model_metrics.json`.
5. **Audit Logging:** Every promotion action is logged in administrative audit trails with user identity, timestamp, and previous vs. new metrics.
6. **No Retroactive Case Mutation:** Promoting a model does **not** retroactively alter resolved academic support cases or modify historical official grade records.
