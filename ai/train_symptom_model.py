"""
CarePulse AI - Symptom to Department Classifier Training Script
Uses Scikit-learn TF-IDF Vectorizer + Calibrated Logistic Regression/MultinomialNB
"""

import os
import joblib
import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline
from sklearn.calibration import CalibratedClassifierCV
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, accuracy_score

# Clinical dataset: Symptoms mapped to Medical Departments
dataset = [
    # Cardiology
    ("chest pain, tightness in chest, pain radiating to left arm and jaw", "Cardiology"),
    ("palpitations, irregular rapid heartbeat, fluttering in chest", "Cardiology"),
    ("shortness of breath when lying flat, ankle swelling, fatigue", "Cardiology"),
    ("high blood pressure, severe hypertension, lightheadedness, pounding in neck", "Cardiology"),
    ("exertional angina, pressure in center of chest during walking or stairs", "Cardiology"),
    ("cold sweats, sudden chest heaviness, nausea, dizziness", "Cardiology"),
    ("heart racing, skipping beats, pulse over 120 bpm at rest", "Cardiology"),
    ("swollen feet and ankles, persistent fatigue, fluid retention, dyspnea", "Cardiology"),
    ("burning chest pain behind breastbone after heavy meals or exertion", "Cardiology"),
    ("breathlessness during mild exercise, cyanosis, blue lips", "Cardiology"),

    # Neurology
    ("severe throbbing headache on one side of head, sensitivity to light and sound, nausea", "Neurology"),
    ("dizziness, loss of balance, vertigo, spinning sensation, unsteady gait", "Neurology"),
    ("numbness and tingling in hands and feet, loss of sensation, pins and needles", "Neurology"),
    ("sudden weakness in arm or leg, facial drooping, slurred speech", "Neurology"),
    ("seizure, involuntary muscle convulsions, loss of consciousness, confusion", "Neurology"),
    ("chronic migraine, visual aura, flashes of light, blind spots", "Neurology"),
    ("tremor in hands, resting shakiness, muscle rigidity, slow movement", "Neurology"),
    ("memory loss, disorientation, difficulty finding words, confusion", "Neurology"),
    ("sharp shooting nerve pain, burning neuralgia down leg, sciatica", "Neurology"),
    ("fainting spells, syncope, sudden blackouts, disorientation", "Neurology"),

    # Orthopedics
    ("knee joint pain, swelling, difficulty bending knee, cracking sound", "Orthopedics"),
    ("severe lower back pain, inability to stand straight, lumbar spasm", "Orthopedics"),
    ("shoulder stiffness, frozen shoulder, inability to lift arm overhead", "Orthopedics"),
    ("twisted ankle, bone fracture, sudden sharp bone pain, inability to bear weight", "Orthopedics"),
    ("hip pain while walking, clicking sensation in hip, groin stiffness", "Orthopedics"),
    ("morning joint stiffness, finger joint swelling, arthritis, inflammation", "Orthopedics"),
    ("neck stiffness, cervical spine pain radiating to shoulder blade", "Orthopedics"),
    ("sports injury, torn ligament, pop sound in knee, acute instability", "Orthopedics"),
    ("heel pain in morning, plantar fasciitis, aching sole", "Orthopedics"),
    ("wrist pain, carpal tunnel numbness, weak hand grip, clicking tendon", "Orthopedics"),

    # Dermatology
    ("red itchy skin rash, dry scaly patches on elbows and knees, psoriasis", "Dermatology"),
    ("cystic facial acne, pimples, blackheads, hormonal breakouts on cheeks", "Dermatology"),
    ("severe itching, hives, red raised welts across body, allergic skin reaction", "Dermatology"),
    ("hair loss, thinning patches on scalp, alopecia areata, excessive shedding", "Dermatology"),
    ("fungal infection between toes, ringworm, circular red scaling borders", "Dermatology"),
    ("eczema breakout, cracked bleeding skin on hands, intense pruritus", "Dermatology"),
    ("dark changing mole, asymmetrical pigment spot, irregular skin growth", "Dermatology"),
    ("blisters on skin, burning rash, peeling sunburn, contact dermatitis", "Dermatology"),
    ("dandruff flaking, oily itchy scalp, seborrheic dermatitis", "Dermatology"),
    ("brittle discolored toenails, yellow fungal nail infection", "Dermatology"),

    # General Medicine
    ("high grade fever, shivering, body aches, generalized fatigue, weakness", "General Medicine"),
    ("persistent viral fever, chills, loss of appetite, dehydration", "General Medicine"),
    ("continuous dry cough, mild sore throat, running nose, congestion", "General Medicine"),
    ("abdominal stomach pain, loose watery motions, food poisoning, diarrhea", "General Medicine"),
    ("unexplained sudden weight loss, excessive thirst, frequent urination, diabetes", "General Medicine"),
    ("yellowish eyes, dark urine, jaundice, lethargy, nausea", "General Medicine"),
    ("acid reflux, heartburn, sour taste in mouth, bloating, indigestion", "General Medicine"),
    ("chronic exhaustion, iron deficiency anemia, pale skin, weakness", "General Medicine"),
    ("mild covid-like symptoms, low grade fever, malaise, sinus headache", "General Medicine"),
    ("acute gastrointestinal infection, vomiting, stomach cramps", "General Medicine"),

    # Pediatrics
    ("infant high fever, continuous crying, refusal to feed, irritability", "Pediatrics"),
    ("baby colic, excessive gas, drawing legs to chest, crying in evenings", "Pediatrics"),
    ("childhood chickenpox rash, itchy fluid-filled spots, low fever", "Pediatrics"),
    ("toddler wheezing, barking cough, croup, stridor during breathing", "Pediatrics"),
    ("child growth milestone delay, slow weight gain, speech delay in 2 year old", "Pediatrics"),
    ("diaper rash, red sore inflamed skin in groin area of newborn", "Pediatrics"),
    ("hand foot and mouth disease, sores in toddler mouth, red spots on palms", "Pediatrics"),
    ("teething fever, drooling, swollen gums, fussiness in baby", "Pediatrics"),
    ("child ear pulling, crying with cold, middle ear infection otitis", "Pediatrics"),
    ("infant vomiting after every formula feed, projectile reflux", "Pediatrics"),

    # Psychiatry
    ("constant overwhelming anxiety, panic attacks, rapid breathing, feeling of dread", "Psychiatry"),
    ("severe chronic depression, feeling hopeless, loss of interest in everything, crying spells", "Psychiatry"),
    ("insomnia, difficulty falling asleep, waking up at 3 AM, racing thoughts", "Psychiatry"),
    ("obsessive intrusive thoughts, compulsive hand washing, repetitive checking behaviors", "Psychiatry"),
    ("extreme mood swings, periods of manic euphoria followed by deep depressive crash", "Psychiatry"),
    ("post traumatic stress, flashbacks of trauma, nightmares, emotional numbness", "Psychiatry"),
    ("social phobia, extreme fear of talking in public, trembling in crowds", "Psychiatry"),
    ("adult ADHD, severe lack of focus, hyperactivity, inability to finish tasks", "Psychiatry"),
    ("burnout, emotional exhaustion, existential despair, chronic mental stress", "Psychiatry"),
    ("loss of appetite from stress, grief counseling need, overwhelming sadness", "Psychiatry"),

    # ENT (Otolaryngology)
    ("severe earache, ear drainage, muffled hearing, clogged ear sensation", "ENT (Otolaryngology)"),
    ("tinnitus, constant ringing or buzzing noise in ears, hearing difficulty", "ENT (Otolaryngology)"),
    ("severe sore throat, difficulty swallowing food, enlarged tonsils with white pus", "ENT (Otolaryngology)"),
    ("chronic sinusitis, facial pressure around eyes and nose, thick yellow mucus", "ENT (Otolaryngology)"),
    ("blocked nasal passage, deviated nasal septum, loud snoring, mouth breathing", "ENT (Otolaryngology)"),
    ("hoarseness of voice, laryngitis, lost voice after speaking, throat tickle", "ENT (Otolaryngology)"),
    ("foreign object lodged in ear or nose, acute pain, bleeding", "ENT (Otolaryngology)"),
    ("recurrent nosebleeds, epistaxis, dry nasal mucosa", "ENT (Otolaryngology)"),
    ("swollen neck lymph nodes, painful salivary gland, dry mouth", "ENT (Otolaryngology)"),
    ("vertigo triggered by head turns, benign paroxysmal positional vertigo BPPV", "ENT (Otolaryngology)")
]

def train_and_save():
    print("Training CarePulse AI Symptom Classifier...")
    texts = [item[0] for item in dataset]
    labels = [item[1] for item in dataset]

    # Create TF-IDF + Calibrated Logistic Regression pipeline for reliable probability estimates
    model = Pipeline([
        ('tfidf', TfidfVectorizer(ngram_range=(1, 2), min_df=1, stop_words='english', sublinear_tf=True)),
        ('clf', CalibratedClassifierCV(LogisticRegression(C=2.0, max_iter=500), cv=3))
    ])

    model.fit(texts, labels)

    train_acc = accuracy_score(labels, model.predict(texts))
    print(f"Model Training Complete! Training Accuracy: {train_acc * 100:.2f}%")

    model_path = os.path.join(os.path.dirname(__file__), 'symptom_model.joblib')
    joblib.dump(model, model_path)
    print(f"Saved trained symptom model to: {model_path}")

    # Test prediction
    test_sample = "sudden chest tightness and left arm pain when walking upstairs"
    probs = model.predict_proba([test_sample])[0]
    best_idx = np.argmax(probs)
    print(f"Test input: '{test_sample}'")
    print(f"Predicted Department: {model.classes_[best_idx]} ({probs[best_idx]*100:.1f}%)")

if __name__ == '__main__':
    train_and_save()
