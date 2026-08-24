# python_ml/app.py
import os
import pickle
import json
import pandas as pd
from datetime import datetime
from flask import Flask, request, jsonify
from flask_cors import CORS
from sklearn.tree import DecisionTreeRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score, accuracy_score

from decision_tree import predict

# FIXED: Basic symmetric security token to prevent unauthorized ML training
API_SECRET_KEY = "UDM_RADAR_ML_SECRET_2026"

app = Flask(__name__)
# FIXED: Restricted CORS. Only allows requests originating from the local web server
CORS(app, resources={r"/*": {"origins": ["http://localhost", "http://127.0.0.1"]}})

BASE_DIR = os.path.dirname(__file__)
MODEL_PATH = os.path.join(BASE_DIR, 'model.pkl')
CANDIDATE_PATH = os.path.join(BASE_DIR, 'candidate_model.pkl')
METRICS_PATH = os.path.join(BASE_DIR, 'model_metrics.json')
CANDIDATE_METRICS_PATH = os.path.join(BASE_DIR, 'candidate_metrics.json')

# FIXED: Synchronized with the updated feature contract
FEATURE_COLS = ['historical_gwa', 'current_prelim_point_avg', 'failed_subjects_count', 'irregular_semesters']

def require_api_key(func):
    """Decorator to enforce shared-secret authentication on governance endpoints."""
    def wrapper(*args, **kwargs):
        if request.headers.get('X-API-Key') != API_SECRET_KEY:
            return jsonify({'error': 'Unauthorized: Invalid or missing API Key'}), 401
        return func(*args, **kwargs)
    wrapper.__name__ = func.__name__
    return wrapper

def _get_risk_label(gwa):
    if gwa < 1.75: return 'HIGH'
    if gwa < 2.50: return 'MODERATE'
    return 'LOW'

@app.route('/predict', methods=['POST'])
def predict_endpoint():
    data = request.get_json()
    if not data:
        return jsonify({'error': 'No data provided'}), 400
    result = predict(data)
    return jsonify(result)

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'service': 'UdM-RADAR ML API'})

@app.route('/api/train_candidate', methods=['POST'])
@require_api_key
def train_candidate():
    if 'file' not in request.files:
        return jsonify({'error': 'No CSV file uploaded'}), 400
        
    file = request.files['file']
    
    # FIXED: 1. Enforce file extension
    if file.filename == '' or not file.filename.endswith('.csv'):
        return jsonify({'error': 'Upload Rejected: File must be a valid .csv format.'}), 400

    # FIXED: 2. Enforce absolute file size limit (5MB) to prevent memory exhaustion
    file.seek(0, os.SEEK_END)
    file_length = file.tell()
    file.seek(0)
    if file_length > 5 * 1024 * 1024:
        return jsonify({'error': 'Upload Rejected: Dataset exceeds the 5MB memory limit.'}), 400

    try:
        df = pd.read_csv(file)
    except Exception as e:
        return jsonify({'error': f'Failed to parse CSV: {str(e)}'}), 400

    # FIXED: 3. Schema Enforcement
    required_cols = FEATURE_COLS + ['final_gwa']
    missing_cols = [col for col in required_cols if col not in df.columns]
    if missing_cols:
        return jsonify({'error': f'Dataset missing required columns: {", ".join(missing_cols)}'}), 400

    # FIXED: 4. Drop rows with missing empty values safely
    df = df.dropna(subset=required_cols)
    
    # FIXED: 5. Strict Data Type Enforcement
    try:
        df['historical_gwa'] = pd.to_numeric(df['historical_gwa'])
        df['current_prelim_point_avg'] = pd.to_numeric(df['current_prelim_point_avg'])
        df['failed_subjects_count'] = pd.to_numeric(df['failed_subjects_count'])
        df['irregular_semesters'] = pd.to_numeric(df['irregular_semesters'])
        df['final_gwa'] = pd.to_numeric(df['final_gwa'])
    except ValueError:
        return jsonify({'error': 'Upload Rejected: All predictive features and final_gwa must be strictly numeric.'}), 400

    # FIXED: 6. Out-of-Bounds Filtering (removes impossible data like a GWA of 9.00)
    valid_bounds = (
        df['historical_gwa'].between(1.0, 5.0) &
        df['current_prelim_point_avg'].between(1.0, 5.0) &
        df['failed_subjects_count'].between(0, 50) &
        df['irregular_semesters'].between(0, 20) &
        df['final_gwa'].between(1.0, 5.0)
    )
    df = df[valid_bounds]

    # FIXED: 7. Enforce Minimum Data Volume
    if len(df) < 50:
        return jsonify({'error': 'Dataset too small after validation. At least 50 valid, complete records are required.'}), 400

    X = df[FEATURE_COLS]
    y = df['final_gwa']

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.20, random_state=42)

    reg = DecisionTreeRegressor(
        criterion='squared_error',
        max_depth=4,
        min_samples_split=5,
        min_samples_leaf=2,
        random_state=42
    )
    reg.fit(X_train, y_train)

    y_pred = reg.predict(X_test)
    y_pred = [max(1.00, min(4.00, p)) for p in y_pred]

    mae = float(mean_absolute_error(y_test, y_pred))
    rmse = float(mean_squared_error(y_test, y_pred) ** 0.5)
    r2 = float(r2_score(y_test, y_pred))
    
    actual_risks = [_get_risk_label(g) for g in y_test]
    pred_risks = [_get_risk_label(g) for g in y_pred]
    risk_accuracy = float(accuracy_score(actual_risks, pred_risks))

    # FIXED: Advanced Provenance Metrics Tracked
    metrics = {
        'mae': round(mae, 4),
        'rmse': round(rmse, 4),
        'r2': round(r2, 4),
        'risk_accuracy': round(risk_accuracy, 4),
        'dataset_size': len(df),
        'is_synthetic': False, 
        'training_date': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        'model_version': 'candidate_staging'
    }

    with open(CANDIDATE_PATH, 'wb') as f:
        pickle.dump(reg, f)
        
    with open(CANDIDATE_METRICS_PATH, 'w') as f:
        json.dump(metrics, f)

    return jsonify({
        'success': True,
        'metrics': metrics,
        'records_processed': len(df)
    })

@app.route('/api/promote_candidate', methods=['POST'])
@require_api_key
def promote_candidate():
    if not os.path.exists(CANDIDATE_PATH):
        return jsonify({'error': 'No candidate model found in staging area.'}), 400
        
    try:
        # FIXED: Versioned Atomic Backups using timestamps instead of static overwrites
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        backup_path = os.path.join(BASE_DIR, f'model_backup_{timestamp}.pkl')
        backup_metrics_path = os.path.join(BASE_DIR, f'model_metrics_backup_{timestamp}.json')
        
        if os.path.exists(MODEL_PATH):
            os.rename(MODEL_PATH, backup_path)
            
        if os.path.exists(METRICS_PATH):
            os.rename(METRICS_PATH, backup_metrics_path)
            
        os.rename(CANDIDATE_PATH, MODEL_PATH)
        
        if os.path.exists(CANDIDATE_METRICS_PATH):
            with open(CANDIDATE_METRICS_PATH, 'r') as f:
                cand_metrics = json.load(f)
            cand_metrics['model_version'] = timestamp
            with open(METRICS_PATH, 'w') as f:
                json.dump(cand_metrics, f)
            os.remove(CANDIDATE_METRICS_PATH)
        
        return jsonify({'success': True, 'message': f'Candidate model promoted successfully to version {timestamp}.'})
    except Exception as e:
        return jsonify({'error': f'System failed to promote model: {str(e)}'}), 500

@app.route('/api/cancel_candidate', methods=['POST'])
@require_api_key
def cancel_candidate():
    if os.path.exists(CANDIDATE_PATH):
        os.remove(CANDIDATE_PATH)
    if os.path.exists(CANDIDATE_METRICS_PATH):
        os.remove(CANDIDATE_METRICS_PATH)
    return jsonify({'success': True})

if __name__ == '__main__':
    # FIXED: debug mode turned off to block arbitrary code execution access
    app.run(host='127.0.0.1', port=5000, debug=False)