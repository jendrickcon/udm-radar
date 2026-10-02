# python_ml/decision_tree.py
#
# Prediction logic using Decision Tree Regressor with canonical feature contract.
# Canonical preliminary grade feature: 'current_prelim_point_avg'
# Backward-compatibility alias: 'current_prelim_avg' (expected by model.pkl)

import os
import pickle
import pandas as pd

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')
FEATURE_COLS = ['historical_gwa', 'current_prelim_avg', 'failed_subjects_count', 'irregular_semesters']

def extract_features(grade_data: dict) -> pd.DataFrame:
    """Extracts and validates features for the Decision Tree model.
    
    Validates presence of required features without silent 0.0 zero-injection.
    Maps canonical 'current_prelim_point_avg' to internal model column 'current_prelim_avg'.
    """
    if not isinstance(grade_data, dict):
        raise TypeError("grade_data must be a dictionary")

    # 1. Resolve canonical prelim feature with alias
    prelim = grade_data.get('current_prelim_point_avg')
    if prelim is None:
        prelim = grade_data.get('current_prelim_avg')
    if prelim is None:
        raise ValueError("Missing required feature: 'current_prelim_point_avg'")

    # 2. Resolve historical GWA
    hist_gwa = grade_data.get('historical_gwa')
    if hist_gwa is None:
        raise ValueError("Missing required feature: 'historical_gwa'")

    # 3. Resolve historical counts
    failed_count = grade_data.get('failed_subjects_count')
    if failed_count is None:
        raise ValueError("Missing required feature: 'failed_subjects_count'")

    irreg_sems = grade_data.get('irregular_semesters')
    if irreg_sems is None:
        raise ValueError("Missing required feature: 'irregular_semesters'")

    try:
        hist_val = float(hist_gwa)
        prelim_val = float(prelim)
        failed_val = int(failed_count)
        irreg_val = int(irreg_sems)
    except (ValueError, TypeError) as e:
        raise ValueError(f"Invalid numeric feature value: {e}")

    # Canonical DataFrame mapping to model.pkl feature names
    return pd.DataFrame([{
        'historical_gwa': hist_val,
        'current_prelim_avg': prelim_val,  # compatibility alias for model.pkl
        'failed_subjects_count': failed_val,
        'irregular_semesters': irreg_val,
    }], columns=FEATURE_COLS)

def predict(grade_data: dict) -> dict:
    """Returns {'predicted_gwa': float, 'source': 'decision_tree' | 'fallback_blend'}.
    
    Enforces feature validation and fails if required features are absent.
    Falls back to canonical 50/50 blend if model artifact is unavailable.
    """
    if not isinstance(grade_data, dict):
        raise TypeError("grade_data must be a dictionary")

    # Validate features before proceeding
    features = extract_features(grade_data)
    hist_gwa = float(features.iloc[0]['historical_gwa'])
    prelim   = float(features.iloc[0]['current_prelim_avg'])

    if not os.path.exists(MODEL_PATH) or os.path.getsize(MODEL_PATH) == 0:
        return _fallback_predict(hist_gwa, prelim)

    try:
        with open(MODEL_PATH, 'rb') as f:
            model = pickle.load(f)
    except (EOFError, pickle.UnpicklingError):
        return _fallback_predict(hist_gwa, prelim)

    pred_gwa = float(model.predict(features)[0])
    pred_gwa = max(1.00, min(4.00, pred_gwa))

    return {
        'predicted_gwa': round(pred_gwa, 2),
        'source': 'decision_tree',
    }

def _fallback_predict(hist_gwa: float, prelim: float) -> dict:
    """Canonical deterministic calculation fallback: 50/50 blend snapped to 0.25 increment.
    
    Synchronized with config/constants.php::predictFinalGradeHeuristic().
    Clamped between 1.00 and 4.00, snapped to 0.25 increment.
    """
    blend = (0.50 * prelim) + (0.50 * hist_gwa)
    blend = max(1.00, min(4.00, blend))
    pred_gwa = round(blend * 4) / 4
    return {
        'predicted_gwa': round(pred_gwa, 2),
        'source': 'fallback_blend',
    }
