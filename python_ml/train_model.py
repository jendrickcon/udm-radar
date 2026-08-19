# python_ml/train_model.py
import os
import pickle
import numpy as np
import pandas as pd
from sklearn.tree import DecisionTreeClassifier, export_text
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, confusion_matrix, accuracy_score

MODEL_PATH = os.path.join(os.path.dirname(__file__), 'model.pkl')
DATA_PATH = os.path.join(os.path.dirname(__file__), 'training_data.csv')

def generate_sample_data(num_samples=150):
    """Generates synthetic baseline data matching official schema if no CSV is found."""
    np.random.seed(42)
    hist_gwa = np.random.uniform(1.25, 3.75, num_samples)
    prelim_avg = np.clip(hist_gwa + np.random.normal(0, 0.4, num_samples), 1.0, 4.0)
    failed_count = np.random.choice([0, 1, 2, 3, 4], size=num_samples, p=[0.70, 0.15, 0.08, 0.05, 0.02])
    irregular_sem = np.where(failed_count > 0, np.random.choice([1, 2, 3], size=num_samples), 0)

    # Ground-truth heuristic for initial labeling
    risk_labels = []
    for gwa, prelim, failed, irreg in zip(hist_gwa, prelim_avg, failed_count, irregular_sem):
        combined = gwa * 0.7 + prelim * 0.3
        if failed >= 2 or combined < 1.75 or irreg >= 2:
            risk_labels.append('HIGH')
        elif failed == 1 or combined < 2.50 or irreg == 1:
            risk_labels.append('MODERATE')
        else:
            risk_labels.append('LOW')

    df = pd.DataFrame({
        'historical_gwa': np.round(hist_gwa, 2),
        'current_prelim_avg': np.round(prelim_avg, 2),
        'failed_subjects_count': failed_count,
        'irregular_semesters': irregular_sem,
        'risk_level': risk_labels
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

    # 1. Feature and Target Extraction
    feature_cols = ['historical_gwa', 'current_prelim_avg', 'failed_subjects_count', 'irregular_semesters']
    X = df[feature_cols]
    y = df['risk_level']

    # 2. Train/Test Split (80/20)
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.20, random_state=42, stratify=y
    )

    # 3. Model Training
    clf = DecisionTreeClassifier(
        criterion='gini',
        max_depth=4,
        min_samples_split=5,
        min_samples_leaf=2,
        class_weight='balanced',
        random_state=42
    )
    clf.fit(X_train, X_train_labels := y_train)

    # 4. Evaluation
    y_pred = clf.predict(X_test)
    acc = accuracy_score(y_test, y_pred)
    
    print("\n" + "="*50)
    print(f"DECISION TREE EVALUATION METRICS (Accuracy: {acc * 100:.2f}%)")
    print("="*50)
    print("\nConfusion Matrix:")
    labels = sorted(list(set(y)))
    cm = confusion_matrix(y_test, y_pred, labels=labels)
    cm_df = pd.DataFrame(cm, index=[f"Actual {l}" for l in labels], columns=[f"Pred {l}" for l in labels])
    print(cm_df)

    print("\nClassification Report:")
    print(classification_report(y_test, y_pred, labels=labels))

    print("\nDecision Tree Rules:")
    print(export_text(clf, feature_names=feature_cols))

    # 5. Export Serialized Model
    with open(MODEL_PATH, 'wb') as f:
        pickle.dump(clf, f)
    print(f"[+] Serialized model successfully saved to: {MODEL_PATH}")

if __name__ == '__main__':
    train()