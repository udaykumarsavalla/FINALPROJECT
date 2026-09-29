"""
CarePulse AI - Next-Day OPD Patient Inflow Predictor Training Script
Trains a Scikit-learn RandomForestRegressor on hospital OPD operational data.
"""

import os
import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, r2_score

# Feature columns:
# [day_of_week (0-6), is_holiday (0/1), month (1-12), lag_1_day_patients, lag_7_day_avg, season_index (0:winter, 1:spring, 2:summer, 3:fall)]
# Target: next_day_total_patients

def generate_synthetic_historical_data():
    """Generates 365 days of realistic hospital outpatient traffic patterns"""
    np.random.seed(42)
    dates = pd.date_range(start='2025-01-01', periods=365)
    
    rows = []
    base_patients = 110
    
    # Monday has highest inflow, Saturday moderate, Sunday lowest
    day_multipliers = {0: 1.25, 1: 1.05, 2: 1.0, 3: 1.08, 4: 1.15, 5: 1.20, 6: 0.55}

    for d in dates:
        dow = d.weekday()
        is_hol = 1 if dow == 6 or np.random.rand() < 0.04 else 0
        month = d.month
        season = (month % 12 + 3) // 3 - 1
        
        # Base count with seasonal and daily variations
        factor = day_multipliers[dow]
        if is_hol and dow != 6:
            factor *= 0.65
            
        noise = np.random.normal(0, 7)
        total = int(max(30, (base_patients * factor) + noise + (5 * np.sin(month / 12 * 2 * np.pi))))
        
        online_ratio = 0.40 + np.random.uniform(-0.05, 0.05)
        online = int(total * online_ratio)
        in_person = total - online
        
        rows.append({
            'date': d,
            'dow': dow,
            'is_holiday': is_hol,
            'month': month,
            'season': season,
            'total_patients': total,
            'online_patients': online,
            'in_person_patients': in_person
        })
        
    df = pd.DataFrame(rows)
    
    # Engineer lag features
    df['lag_1_patients'] = df['total_patients'].shift(1).fillna(df['total_patients'].mean())
    df['lag_7_avg'] = df['total_patients'].rolling(window=7, min_periods=1).mean()
    
    return df

def train_and_save():
    print("Training CarePulse AI Next-Day OPD Inflow Predictor...")
    df = generate_synthetic_historical_data()
    
    feature_cols = ['dow', 'is_holiday', 'month', 'season', 'lag_1_patients', 'lag_7_avg']
    X = df[feature_cols]
    y = df['total_patients']
    
    # Train-test split (chronological last 60 days)
    X_train, X_test = X.iloc[:-60], X.iloc[-60:]
    y_train, y_test = y.iloc[:-60], y.iloc[-60:]
    
    model = RandomForestRegressor(n_estimators=120, max_depth=8, random_state=42)
    model.fit(X_train, y_train)
    
    preds = model.predict(X_test)
    mae = mean_absolute_error(y_test, preds)
    r2 = r2_score(y_test, preds)
    
    print(f"Model Training Complete! Test MAE: {mae:.2f} patients, R2 Score: {r2:.3f}")
    
    model_path = os.path.join(os.path.dirname(__file__), 'opd_model.joblib')
    joblib.dump({
        'model': model,
        'feature_cols': feature_cols,
        'mae': mae,
        'r2': r2
    }, model_path)
    print(f"Saved OPD inflow prediction model to: {model_path}")

if __name__ == '__main__':
    train_and_save()
