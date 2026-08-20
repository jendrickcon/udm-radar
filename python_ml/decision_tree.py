# python_ml/decision_tree.py
#
# This module's only job is to turn the 4 input features into a predicted
# numeric final GWA. It deliberately does NOT compute risk_level or
# latin_honor — those are rule-based derivations of a GWA number, and
# config/constants.php (computeRiskFromAvg(), getLatinHonor()) is the single
# source of truth for those thresholds. Duplicating that logic here in Python
# is how the honors-eligibility and risk-level bugs happened before; predict()
# now returns predicted_gwa only, and api/predict.php derives everything else
# from it exactly the way it already does for the heuristic fallback path.
import os
import pickle
import pandas as pd

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')
FEATURE_COLS = ['historical_gwa', 'current_prelim_avg', 'failed_subjects_count', 'irregular_semesters']

def extract_features(grade_data: dict) -> pd.DataFrame:
    # A one-row DataFrame with the training-time column names/order, so the
    # model doesn't warn about (or silently mis-align) unnamed feature columns.
    return pd.DataFrame([{
        'historical_gwa': float(grade_data.get('historical_gwa', 0)),
        'current_prelim_avg': float(grade_data.get('current_prelim_avg', 0)),
        'failed_subjects_count': int(grade_data.get('failed_subjects_count', 0)),
        'irregular_semesters': int(grade_data.get('irregular_semesters', 0)),
    }], columns=FEATURE_COLS)

def predict(grade_data: dict) -> dict:
    """Returns {'predicted_gwa': float, 'source': 'decision_tree' | 'fallback_blend'}.

    Falls back to the same 70/30 historical/prelim blend used elsewhere in the
    codebase if no trained model is available yet, so the Flask endpoint never
    hard-fails just because model.pkl hasn't been generated.
    """
    hist_gwa = float(grade_data.get('historical_gwa', 0))
    prelim   = float(grade_data.get('current_prelim_avg', 0))

    if not os.path.exists(MODEL_PATH) or os.path.getsize(MODEL_PATH) == 0:
        return _fallback_predict(hist_gwa, prelim)

    try:
        with open(MODEL_PATH, 'rb') as f:
            model = pickle.load(f)
    except (EOFError, pickle.UnpicklingError):
        return _fallback_predict(hist_gwa, prelim)

    features = extract_features(grade_data)
    pred_gwa = float(model.predict(features)[0])
    pred_gwa = max(1.00, min(4.00, pred_gwa))

    return {
        'predicted_gwa': round(pred_gwa, 2),
        'source': 'decision_tree',
    }

def _fallback_predict(hist_gwa: float, prelim: float) -> dict:
    pred_gwa = round(hist_gwa * 0.70 + prelim * 0.30, 2)
    pred_gwa = max(1.00, min(4.00, pred_gwa))
    return {
        'predicted_gwa': pred_gwa,
        'source': 'fallback_blend',
    }
