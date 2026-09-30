# python_ml/train_model.py
import os
import json
import pickle
import numpy as np
import pandas as pd
from datetime import datetime
from sklearn.tree import DecisionTreeRegressor, export_text
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

BASE_DIR = os.path.dirname(__file__)
MODEL_PATH = os.path.join(BASE_DIR, 'model.pkl')
DATA_PATH = os.path.join(BASE_DIR, 'training_data.csv')
METRICS_PATH = os.path.join(BASE_DIR, 'model_metrics.json')

FEATURE_COLS = ['historical_gwa', 'current_prelim_avg', 'failed_subjects_count', 'irregular_semesters']

def load_or_generate_dataset() -> pd.DataFrame:
    """Loads existing training_data.csv or generates synthetic baseline if not present."""
    if os.path.exists(DATA_PATH):
        df = pd.read_csv(DATA_PATH)
        # Harmonize column names if current_prelim_point_avg is used
        if 'current_prelim_point_avg' in df.columns and 'current_prelim_avg' not in df.columns:
            df['current_prelim_avg'] = df['current_prelim_point_avg']
        elif 'current_prelim_avg' in df.columns and 'current_prelim_point_avg' not in df.columns:
            df['current_prelim_point_avg'] = df['current_prelim_avg']
        print(f"[+] Loaded existing training dataset: {DATA_PATH} ({len(df)} records)")
        return df

    print(f"[-] {DATA_PATH} not found. Generating synthetic baseline dataset...")
    np.random.seed(42)
    num_samples = 200

    hist_gwa = np.random.uniform(1.25, 3.75, num_samples)
    prelim_avg = np.clip(hist_gwa + np.random.normal(0, 0.35, num_samples), 1.0, 4.0)
    failed_count = np.random.choice([0, 1, 2, 3, 4], size=num_samples, p=[0.70, 0.15, 0.08, 0.05, 0.02])
    irregular_sem = np.where(failed_count > 0, np.random.choice([1, 2, 3], size=num_samples), 0)

    # Realistic continuous final GWA with non-linear penalties and natural variance
    noise = np.random.normal(0, 0.18, num_samples)
    final_gwa = (hist_gwa * 0.65) + (prelim_avg * 0.35) - (failed_count * 0.06) - (irregular_sem * 0.04) + noise
    final_gwa = np.clip(final_gwa, 1.00, 4.00)

    risk_labels = np.where(final_gwa < 1.75, 'HIGH', np.where(final_gwa < 2.50, 'MODERATE', 'LOW'))

    df = pd.DataFrame({
        'historical_gwa': np.round(hist_gwa, 2),
        'current_prelim_avg': np.round(prelim_avg, 2),
        'current_prelim_point_avg': np.round(prelim_avg, 2),
        'failed_subjects_count': failed_count,
        'irregular_semesters': irregular_sem,
        'final_gwa': np.round(final_gwa, 2),
        'risk_level': risk_labels
    })
    df.to_csv(DATA_PATH, index=False)
    print(f"[*] Generated baseline dataset at: {DATA_PATH} ({num_samples} records)")
    return df

def train():
    df = load_or_generate_dataset()

    if 'final_gwa' not in df.columns:
        raise SystemExit("[-] Error: 'final_gwa' target column is missing from training data.")

    # Harmonize column names
    if 'current_prelim_avg' not in df.columns and 'current_prelim_point_avg' in df.columns:
        df['current_prelim_avg'] = df['current_prelim_point_avg']

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

    print("\n" + "="*50)
    print("DECISION TREE REGRESSOR EVALUATION METRICS")
    print("="*50)
    print(f"Mean Absolute Error (MAE):      {mae:.4f} (GWA scale 1.00 - 4.00)")
    print(f"Root Mean Squared Error (RMSE): {rmse:.4f}")
    print(f"R-Squared (R2 Score):           {r2:.4f}")

    print("\nDecision Tree Rules:")
    print(export_text(reg, feature_names=FEATURE_COLS))

    # Serialize trained model artifact
    with open(MODEL_PATH, 'wb') as f:
        pickle.dump(reg, f)
    print(f"[+] Serialized model successfully saved to: {MODEL_PATH}")

    # Save metrics record
    metrics = {
        'mae': round(mae, 4),
        'rmse': round(rmse, 4),
        'r2': round(r2, 4),
        'dataset_size': len(df),
        'is_synthetic': True,
        'trained_at': datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        'features': FEATURE_COLS
    }
    with open(METRICS_PATH, 'w') as f:
        json.dump(metrics, f, indent=2)
    print(f"[+] Model metrics saved to: {METRICS_PATH}")

if __name__ == '__main__':
    train()