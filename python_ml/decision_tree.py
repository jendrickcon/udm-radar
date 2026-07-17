# python_ml/decision_tree.py
# Decision Tree model for UdM-RADAR
# This replaces the inline logic scattered across your mock_data.py predictions

import numpy as np
from sklearn.tree import DecisionTreeClassifier
from sklearn.preprocessing import LabelEncoder
import pickle
import os

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')

# ── Feature extraction ─────────────────────────────────────────────────────
def extract_features(grade_data: dict) -> list:
  """
  Convert student grade dict into a feature vector for the Decision Tree.
  grade_data should contain: historical_gwa, current_prelim_avg,
                              failed_subjects_count, irregular_semesters
  """
  return [
    float(grade_data.get('historical_gwa', 0)),
    float(grade_data.get('current_prelim_avg', 0)),
    int(grade_data.get('failed_subjects_count', 0)),
    int(grade_data.get('irregular_semesters', 0)),
  ]

# ── Prediction ─────────────────────────────────────────────────────────────
def predict(grade_data: dict) -> dict:
  """
  Takes student grade data, returns prediction dict.
  Returns: { risk_level, predicted_gwa, latin_honor, irregular_prob }
  """
  if not os.path.exists(MODEL_PATH):
    # Fallback: rule-based prediction if model not yet trained
    return _rule_based_predict(grade_data)

  with open(MODEL_PATH, 'rb') as f:
    model = pickle.load(f)

  features = np.array([extract_features(grade_data)])
  risk = model.predict(features)[0]
  proba = model.predict_proba(features)[0]

  hist_gwa = float(grade_data.get('historical_gwa', 0))
  prelim   = float(grade_data.get('current_prelim_avg', 0))
  pred_gwa = round(hist_gwa * 0.70 + prelim * 0.30, 2)

  return {
    'risk_level':    risk,
    'predicted_gwa': pred_gwa,
    'latin_honor':   _get_latin_honor(pred_gwa),
    'irregular_prob': round(float(proba[list(model.classes_).index('HIGH')]) * 100, 1)
                     if 'HIGH' in model.classes_ else 0.0,
  }

def _rule_based_predict(grade_data: dict) -> dict:
  """Fallback rule-based prediction (mirrors theme.py logic)."""
  hist_gwa   = float(grade_data.get('historical_gwa', 0))
  prelim_avg = float(grade_data.get('current_prelim_avg', 0))
  failed     = int(grade_data.get('failed_subjects_count', 0))

  pred_gwa = round(hist_gwa * 0.70 + prelim_avg * 0.30, 2)

  if failed > 1 or pred_gwa < 1.50:
    risk = 'HIGH'
  elif failed == 1 or pred_gwa < 2.50:
    risk = 'MODERATE'
  else:
    risk = 'LOW'

  return {
    'risk_level':    risk,
    'predicted_gwa': pred_gwa,
    'latin_honor':   _get_latin_honor(pred_gwa),
    'irregular_prob': 70.0 if risk == 'HIGH' else (30.0 if risk == 'MODERATE' else 5.0),
  }

def _get_latin_honor(gwa: float) -> str:
  if gwa >= 3.75: return 'Summa Cum Laude'
  if gwa >= 3.50: return 'Magna Cum Laude'
  if gwa >= 3.25: return 'Cum Laude'
  return 'Not Eligible'