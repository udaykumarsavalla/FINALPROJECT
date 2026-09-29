#!/usr/bin/env python3
"""
CarePulse AI - Symptom Predictor CLI
Takes input text or JSON and returns department classification with confidence percentages.
"""

import sys
import os
import json
import joblib
import numpy as np

def predict(symptom_text):
    model_path = os.path.join(os.path.dirname(__file__), 'symptom_model.joblib')
    if not os.path.exists(model_path):
        return {
            "success": False,
            "error": "Trained symptom model file not found. Please train model first."
        }

    try:
        model = joblib.load(model_path)
        probs = model.predict_proba([symptom_text])[0]
        classes = model.classes_

        # Sort classes by descending probability
        sorted_indices = np.argsort(probs)[::-1]
        
        best_dept = classes[sorted_indices[0]]
        best_conf = round(float(probs[sorted_indices[0]]) * 100, 2)

        top_predictions = []
        for idx in sorted_indices[:4]:
            top_predictions.append({
                "department": classes[idx],
                "confidence": round(float(probs[idx]) * 100, 2)
            })

        return {
            "success": True,
            "symptom_text": symptom_text,
            "department": best_dept,
            "confidence": best_conf,
            "top_predictions": top_predictions
        }

    except Exception as e:
        return {
            "success": False,
            "error": str(e)
        }

if __name__ == '__main__':
    # Get input from argument or stdin
    if len(sys.argv) > 1:
        query = " ".join(sys.argv[1:])
    else:
        query = sys.stdin.read().strip()

    if not query:
        query = "headache and dizziness"

    result = predict(query)
    print(json.dumps(result, indent=2))
