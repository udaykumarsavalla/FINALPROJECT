#!/usr/bin/env python3
"""
CarePulse AI - Next-Day OPD Inflow Predictor CLI
Predicts tomorrow's expected OPD patient volume, online vs walk-in split, and staffing recommendations.
"""

import sys
import os
import json
import datetime
import joblib
import numpy as np
import pandas as pd

def predict_tomorrow(custom_date_str=None, lag_1=None, lag_7_avg=None):
    model_path = os.path.join(os.path.dirname(__file__), 'opd_model.joblib')
    if not os.path.exists(model_path):
        return {
            "success": False,
            "error": "Trained OPD model file not found. Please train model first."
        }

    try:
        saved_data = joblib.load(model_path)
        model = saved_data['model']

        # Determine target date
        if custom_date_str:
            target_date = datetime.datetime.strptime(custom_date_str, '%Y-%m-%d').date()
        else:
            target_date = datetime.date.today() + datetime.timedelta(days=1)

        dow = target_date.weekday() # 0 = Monday, 6 = Sunday
        is_holiday = 1 if dow == 6 else 0
        month = target_date.month
        season = (month % 12 + 3) // 3 - 1

        # Defaults if not passed
        recent_lag_1 = float(lag_1) if lag_1 else (125.0 if dow in [0, 4, 5] else 105.0)
        recent_lag_7 = float(lag_7_avg) if lag_7_avg else 112.0

        features = pd.DataFrame([[dow, is_holiday, month, season, recent_lag_1, recent_lag_7]], columns=saved_data['feature_cols'])
        predicted_total = int(round(model.predict(features)[0]))
        
        # Error margin from model MAE
        mae = saved_data.get('mae', 6.5)
        lower_bound = max(20, int(round(predicted_total - (1.96 * mae))))
        upper_bound = int(round(predicted_total + (1.96 * mae)))

        # Split predictions
        online_ratio = 0.42
        online_predicted = int(round(predicted_total * online_ratio))
        in_person_predicted = predicted_total - online_predicted

        day_names = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]
        
        # Workload severity
        if predicted_total > 135:
            workload_status = "High Surge Expected"
            staff_recommendation = "Deploy 2 additional triage nurses and open standby consultation rooms."
            alert_level = "warning"
        elif predicted_total > 100:
            workload_status = "Normal Moderate Load"
            staff_recommendation = "Standard doctor roster is optimal. Standard shift rotation."
            alert_level = "success"
        else:
            workload_status = "Light Load Expected"
            staff_recommendation = "Roster can accommodate doctor tele-consultations and elective reviews."
            alert_level = "info"

        # Departmental distribution forecast
        dept_distribution = {
            "Cardiology": int(round(predicted_total * 0.26)),
            "General Medicine": int(round(predicted_total * 0.28)),
            "Neurology": int(round(predicted_total * 0.12)),
            "Orthopedics": int(round(predicted_total * 0.16)),
            "Dermatology": int(round(predicted_total * 0.10)),
            "Pediatrics": int(round(predicted_total * 0.08))
        }

        # Highest demand department
        highest_demand_dept = max(dept_distribution, key=dept_distribution.get)

        # Expected peak consultation hours
        expected_peak_hours = "10:00 AM – 01:00 PM" if dow in [0, 4, 5] else "10:30 AM – 01:15 PM"

        # Required doctor allocation (triage staffing calculation)
        doctor_allocation = {
            dept: max(1, int(np.ceil(cnt / 14.0))) for dept, cnt in dept_distribution.items()
        }

        return {
            "success": True,
            "target_date": target_date.strftime('%Y-%m-%d'),
            "target_day_name": day_names[dow],
            "is_weekend_or_holiday": bool(is_holiday),
            "predicted_total_inflow": predicted_total,
            "expected_peak_hours": expected_peak_hours,
            "highest_demand_department": highest_demand_dept,
            "required_doctor_allocation": doctor_allocation,
            "confidence_interval": {
                "lower_bound": lower_bound,
                "upper_bound": upper_bound,
                "mae": round(mae, 2)
            },
            "split": {
                "online_consultations": online_predicted,
                "hospital_visits": in_person_predicted
            },
            "workload_status": workload_status,
            "alert_level": alert_level,
            "staff_recommendation": staff_recommendation,
            "department_distribution": dept_distribution
        }

    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

if __name__ == '__main__':
    custom_date = sys.argv[1] if len(sys.argv) > 1 else None
    result = predict_tomorrow(custom_date)
    print(json.dumps(result, indent=2))
