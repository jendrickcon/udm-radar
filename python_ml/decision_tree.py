# python_ml/decision_tree.py
import os
import pickle
import numpy as np
from sklearn.tree import DecisionTreeClassifier
from sklearn.preprocessing import LabelEncoder

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')

def extract_features(grade_data: dict) -> list:
    return [
        float(grade_data.get('historical_gwa', 0)),
        float(grade_data.get('current_prelim_avg', 0)),
        int(grade_data.get('failed_subjects_count', 0)),
        int(grade_data.get('irregular_semesters', 0)),
    ]

# ... (top of file remains the same)

def predict(grade_data: dict) -> dict:
    if not os.path.exists(MODEL_PATH) or os.path.getsize(MODEL_PATH) == 0:
        return _rule_based_predict(grade_data)

    try:
        with open(MODEL_PATH, 'rb') as f:
            model = pickle.load(f)
    except (EOFError, pickle.UnpicklingError):
        return _rule_based_predict(grade_data)

    features = np.array([extract_features(grade_data)])
    risk = model.predict(features)[0]
    proba = model.predict_proba(features)[0]

    hist_gwa = float(grade_data.get('historical_gwa', 0))
    prelim   = float(grade_data.get('current_prelim_avg', 0))
    failed   = int(grade_data.get('failed_subjects_count', 0))
    
    pred_gwa = round(hist_gwa * 0.70 + prelim * 0.30, 2)

    return {
        'risk_level':    risk,
        'predicted_gwa': pred_gwa,
        # Pass the failed count to enforce policy
        'latin_honor':   _get_latin_honor(pred_gwa, failed), 
        'irregular_prob': round(float(proba[list(model.classes_).index('HIGH')]) * 100, 1)
                         if 'HIGH' in model.classes_ else 0.0,
    }

def _rule_based_predict(grade_data: dict) -> dict:
    hist_gwa   = float(grade_data.get('historical_gwa', 0))
    prelim_avg = float(grade_data.get('current_prelim_avg', 0))
    failed     = int(grade_data.get('failed_subjects_count', 0))

    pred_gwa = round(hist_gwa * 0.70 + prelim_avg * 0.30, 2)

    if failed > 1 or pred_gwa < 1.75:
        risk = 'HIGH'
    elif failed == 1 or pred_gwa < 2.50:
        risk = 'MODERATE'
    else:
        risk = 'LOW'

    return {
        'risk_level':    risk,
        'predicted_gwa': pred_gwa,
        # Pass the failed count to enforce policy
        'latin_honor':   _get_latin_honor(pred_gwa, failed),
        'irregular_prob': 70.0 if risk == 'HIGH' else (30.0 if risk == 'MODERATE' else 5.0),
    }

# Updated to enforce institutional policy
def _get_latin_honor(gwa: float, failed_count: int) -> str:
    if failed_count > 0:
        return 'Not Eligible' # Disqualified due to past failures
        
    if gwa >= 3.75: return 'Summa Cum Laude'
    if gwa >= 3.50: return 'Magna Cum Laude'
    if gwa >= 3.25: return 'Cum Laude'
    return 'Not Eligible'