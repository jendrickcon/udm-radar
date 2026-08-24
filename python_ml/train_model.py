# python_ml/decision_tree.py
#
# This module's only job is to turn the 4 input features into a predicted
# numeric final GWA. It deliberately does NOT compute risk_level or
# latin_honor — those are rule-based derivations of a GWA number, and
# config/constants.php (computeRiskFromAvg(), getLatinHonor()) is the single
# source of truth for those thresholds. 
import os
import pickle
import pandas as pd

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')

# FIXED: Explicitly named "point_avg" to prevent percentage vs decimal confusion
FEATURE_COLS = ['historical_gwa', 'current_prelim_point_avg', 'failed_subjects_count', 'irregular_semesters']

def extract_features(grade_data: dict) -> pd.DataFrame:
    # FIXED: Throw explicit errors if core predictive features are missing entirely
    if 'historical_gwa' not in grade_data or 'current_prelim_point_avg' not in grade_data:
        raise ValueError("Missing critical academic data required for prediction (historical_gwa or current_prelim_point_avg).")

    return pd.DataFrame([{
        'historical_gwa': float(grade_data.get('historical_gwa', 0)),
        'current_prelim_point_avg': float(grade_data.get('current_prelim_point_avg', 0)),
        'failed_subjects_count': int(grade_data.get('failed_subjects_count', 0)),
        'irregular_semesters': int(grade_data.get('irregular_semesters', 0)),
    }], columns=FEATURE_COLS)

def predict(grade_data: dict) -> dict:
    """Returns {'predicted_gwa': float, 'source': 'decision_tree' | 'fallback_blend'}.
    """
    try:
        hist_gwa = float(grade_data.get('historical_gwa', 0))
        prelim   = float(grade_data.get('current_prelim_point_avg', 0))
    except (TypeError, ValueError):
        return {'error': 'Invalid numeric format for grading inputs'}

    if not os.path.exists(MODEL_PATH) or os.path.getsize(MODEL_PATH) == 0:
        return _fallback_predict(hist_gwa, prelim)

    try:
        with open(MODEL_PATH, 'rb') as f:
            model = pickle.load(f)
    except (EOFError, pickle.UnpicklingError):
        return _fallback_predict(hist_gwa, prelim)

    try:
        features = extract_features(grade_data)
        pred_gwa = float(model.predict(features)[0])
        # Clamp bounds strictly between highest and lowest possible grades
        pred_gwa = max(1.00, min(4.00, pred_gwa))

        return {
            'predicted_gwa': round(pred_gwa, 2),
            'source': 'decision_tree',
        }
    except Exception as e:
        # If extraction or prediction fails, safely fallback
        return _fallback_predict(hist_gwa, prelim)

def _fallback_predict(hist_gwa: float, prelim: float) -> dict:
    # Safely guard against zero division or missing baseline data
    if hist_gwa <= 0:
        hist_gwa = prelim
    if prelim <= 0:
        prelim = hist_gwa
        
    pred_gwa = round((hist_gwa * 0.70) + (prelim * 0.30), 2)
    pred_gwa = max(1.00, min(4.00, pred_gwa))
    return {
        'predicted_gwa': pred_gwa,
        'source': 'fallback_blend',
    }