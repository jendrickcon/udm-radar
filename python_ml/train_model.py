# python_ml/train_model.py
import os
import pickle
import numpy as np
import pandas as pd
from sklearn.tree import DecisionTreeRegressor, export_text
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')
DATA_PATH = os.path.join(os.path.dirname(__file__), 'training_data.csv')

def generate_sample_data(num_samples=150):
    """Generates synthetic baseline data matching official schema if no CSV is found.

    final_gwa is the regression target: a synthetic "what the student's final
    GWA actually turned out to be," built by nudging the 70/30 historical/prelim
    blend with a bit of noise so the tree has something to learn beyond just
    reproducing the blend formula exactly. failed_subjects_count and
    irregular_semesters are also allowed to depress final_gwa a little, since
    those are real predictive signals the linear blend alone doesn't capture.

    risk_level is NOT a training target. It's derived from final_gwa the same
    way it always will be at prediction time (via computeRiskFromAvg()'s
    thresholds), and is included here only as a sanity-check column for anyone
    reading the CSV by eye — never fed to the model.
    """
    np.random.seed(42)
    hist_gwa = np.random.uniform(1.25, 3.75, num_samples)
    prelim_avg = np.clip(hist_gwa + np.random.normal(0, 0.4, num_samples), 1.0, 4.0)
    failed_count = np.random.choice([0, 1, 2, 3, 4], size=num_samples, p=[0.70, 0.15, 0.08, 0.05, 0.02])
    irregular_sem = np.where(failed_count > 0, np.random.choice([1, 2, 3], size=num_samples), 0)

    # Synthetic ground-truth final GWA: the 70/30 blend, penalized slightly for
    # failed subjects/irregular semesters, plus noise so it isn't a perfectly
    # learnable linear function of the inputs.
    noise = np.random.normal(0, 0.15, num_samples)
    final_gwa = (hist_gwa * 0.7) + (prelim_avg * 0.3) - (failed_count * 0.05) - (irregular_sem * 0.03) + noise
    final_gwa = np.clip(final_gwa, 1.00, 4.00)

    # risk_level here mirrors PHP's computeRiskFromAvg() thresholds exactly —
    # kept in sync by hand since this is a one-way synthetic label, not a
    # second source of truth read anywhere in the pipeline.
    risk_labels = np.where(final_gwa < 1.75, 'HIGH', np.where(final_gwa < 2.50, 'MODERATE', 'LOW'))

    df = pd.DataFrame({
        'historical_gwa': np.round(hist_gwa, 2),
        'current_prelim_avg': np.round(prelim_avg, 2),
        'failed_subjects_count': failed_count,
        'irregular_semesters': irregular_sem,
        'final_gwa': np.round(final_gwa, 2),
        'risk_level': risk_labels,  # reference/sanity-check only, not a model input or target
    })
    df.to_csv(DATA_PATH, index=False)
    print(f"[*] Generated baseline dataset at: {DATA_PATH} ({num_samples} records)")
    return df

def train():
    if not os.path.exists(DATA_PATH):
        print(f"[-] Training file not found at {DATA_PATH}. Creating baseline data...")
        df = generate_sample_data(150)
    else:
        df = pd.read_csv(DATA_PATH)
        print(f"[+] Loaded dataset: {DATA_PATH} ({len(df)} records)")
        if 'final_gwa' not in df.columns:
            raise SystemExit(
                "[-] training_data.csv is missing 'final_gwa' (the regression target). "
                "This file predates the switch from risk-level classification to GWA "
                "regression. Delete it and rerun this script to regenerate a compatible "
                "synthetic dataset, or add a real final_gwa column if this is now real data."
            )

    # 1. Feature and Target Extraction
    # Target is the continuous final GWA — NOT risk_level. risk_level and Latin
    # honor eligibility are derived downstream from the predicted GWA via
    # computeRiskFromAvg() / getLatinHonor() in config/constants.php, so there
    # is exactly one place that defines those thresholds, not two.
    feature_cols = ['historical_gwa', 'current_prelim_avg', 'failed_subjects_count', 'irregular_semesters']
    X = df[feature_cols]
    y = df['final_gwa']

    # 2. Train/Test Split (80/20)
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.20, random_state=42
    )

    # 3. Model Training
    reg = DecisionTreeRegressor(
        criterion='squared_error',
        max_depth=4,
        min_samples_split=5,
        min_samples_leaf=2,
        random_state=42
    )
    reg.fit(X_train, y_train)

    # 4. Evaluation — regression metrics, not classification accuracy.
    y_pred = reg.predict(X_test)
    mae = mean_absolute_error(y_test, y_pred)
    rmse = mean_squared_error(y_test, y_pred) ** 0.5
    r2 = r2_score(y_test, y_pred)

    print("\n" + "="*50)
    print("DECISION TREE REGRESSOR EVALUATION METRICS")
    print("="*50)
    print(f"Mean Absolute Error (MAE):  {mae:.4f}  (average GWA points off, on the 1.00-4.00 scale)")
    print(f"Root Mean Squared Error (RMSE): {rmse:.4f}")
    print(f"R-Squared (R2): {r2:.4f}")

    print("\nSample predictions vs. actual (first 10 test rows):")
    comparison = pd.DataFrame({
        'actual_gwa': y_test.values[:10],
        'predicted_gwa': np.round(y_pred[:10], 2),
    })
    comparison['abs_error'] = np.round((comparison['actual_gwa'] - comparison['predicted_gwa']).abs(), 2)
    print(comparison.to_string(index=False))

    print("\nDecision Tree Rules:")
    print(export_text(reg, feature_names=feature_cols))

    # 5. Export Serialized Model
    with open(MODEL_PATH, 'wb') as f:
        pickle.dump(reg, f)
    print(f"\n[+] Serialized regressor model successfully saved to: {MODEL_PATH}")

if __name__ == '__main__':
    train()
