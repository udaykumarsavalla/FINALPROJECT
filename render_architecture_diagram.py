#!/usr/bin/env python3
"""
CarePulse AI - High-Resolution Architecture Diagram Generator
Generates both PNG and SVG vector image formats of the System Architecture Diagram.
"""

import os
from PIL import Image, ImageDraw, ImageFont

def render_diagram():
    # 2400 x 1350 for sharp 16:9 4K-ready quality
    width, height = 2400, 1350
    img = Image.new("RGBA", (width, height), (15, 23, 42, 255)) # #0F172A Deep Navy
    draw = ImageDraw.Draw(img)

    # Color definitions
    C_BG = (15, 23, 42, 255)
    C_CARD_BG = (30, 41, 59, 240)       # #1E293B
    C_INNER_CARD = (15, 23, 42, 220)     # #0F172A
    C_TEAL = (13, 148, 136, 255)         # #0D9488
    C_CYAN = (6, 182, 212, 255)          # #06B6D4
    C_AMBER = (245, 158, 11, 255)        # #F59E0B
    C_BLUE = (59, 130, 246, 255)         # #3B82F6
    C_GREEN = (16, 185, 129, 255)        # #10B981
    C_WHITE = (255, 255, 255, 255)
    C_SLATE = (148, 163, 184, 255)       # #94A3B8
    C_DARK_TEXT = (203, 213, 225, 255)   # #CBD5E1

    # Load system fonts or fallback
    try:
        font_title = ImageFont.truetype("arialbd.ttf", 46)
        font_sub = ImageFont.truetype("arial.ttf", 22)
        font_sec_title = ImageFont.truetype("arialbd.ttf", 24)
        font_card_title = ImageFont.truetype("arialbd.ttf", 21)
        font_card_sub = ImageFont.truetype("arialbd.ttf", 15)
        font_body = ImageFont.truetype("arial.ttf", 16)
        font_badge = ImageFont.truetype("arialbd.ttf", 14)
        font_arrow = ImageFont.truetype("arialbd.ttf", 16)
    except:
        font_title = ImageFont.load_default()
        font_sub = font_title
        font_sec_title = font_title
        font_card_title = font_title
        font_card_sub = font_title
        font_body = font_title
        font_badge = font_title
        font_arrow = font_title

    # Header Bar
    draw.rectangle([(80, 40), (96, 110)], fill=C_CYAN)
    draw.text((120, 42), "CarePulse AI  |  System Architecture Diagram", fill=C_WHITE, font=font_title)
    draw.text((120, 95), "Tri-Tier Model-View-Controller & Decoupled Machine Learning Microservice Architecture", fill=C_CYAN, font=font_sub)

    # Helper: Rounded Box
    def draw_box(x, y, w, h, bg, border, radius=12, border_w=2):
        draw.rounded_rectangle([(x, y), (x + w, y + h)], radius=radius, fill=bg, outline=border, width=border_w)

    # Helper: Badge
    def draw_badge(x, y, text, bg, fg):
        bbox = font_badge.getbbox(text)
        bw = (bbox[2] - bbox[0]) + 18
        bh = (bbox[3] - bbox[1]) + 10
        draw.rounded_rectangle([(x, y), (x + bw, y + bh)], radius=bh//2, fill=bg)
        draw.text((x + 9, y + 4), text, fill=fg, font=font_badge)

    # =========================================================================
    # TIER 1: PRESENTATION TIER (CLIENT LAYER)
    # =========================================================================
    t1_y = 150
    t1_h = 300
    draw_box(80, t1_y, 2240, t1_h, C_CARD_BG, C_CYAN, radius=16, border_w=2)
    draw_badge(110, t1_y + 15, "LAYER 1", C_CYAN, (15, 23, 42, 255))
    draw.text((200, t1_y + 15), "PRESENTATION TIER (CLIENT LAYER)", fill=C_WHITE, font=font_sec_title)
    draw.text((700, t1_y + 18), "Responsive Web Standard: HTML5, CSS3 Glassmorphism, Bootstrap 5.3, JavaScript ES6 & WebRTC", fill=C_SLATE, font=font_body)

    # 3 Client Portal Cards
    p_w = 710
    p_gap = 35
    p_y = t1_y + 60
    p_h = 220

    # Portal 1: Patient Portal
    px1 = 110
    draw_box(px1, p_y, p_w, p_h, C_INNER_CARD, C_TEAL, radius=10, border_w=1)
    draw_badge(px1 + 15, p_y + 15, "PATIENT PORTAL", C_TEAL, C_WHITE)
    draw.text((px1 + 175, p_y + 17), "Mobile / Desktop Client", fill=C_SLATE, font=font_card_sub)
    draw.text((px1 + 20, p_y + 55), "• Clinical NLP Symptom Triage with urgency rating & doctor matching", fill=C_DARK_TEXT, font=font_body)
    draw.text((px1 + 20, p_y + 85), "• Conflict-Free Slot Booking (In-Hospital Visit or Online Video Consult)", fill=C_DARK_TEXT, font=font_body)
    draw.text((px1 + 20, p_y + 115), "• Multi-Gateway Checkout: UPI (Dynamic QR/VPA), Cards & Net Banking", fill=C_DARK_TEXT, font=font_body)
    draw.text((px1 + 20, p_y + 145), "• Live Dynamic Queue Wait-Time Tracker with real-time token countdown", fill=C_DARK_TEXT, font=font_body)
    draw.text((px1 + 20, p_y + 175), "• Lifetime Digital Prescription Vault & Diagnostic Records Manager", fill=C_DARK_TEXT, font=font_body)

    # Portal 2: Physician Tele-Desk
    px2 = px1 + p_w + p_gap
    draw_box(px2, p_y, p_w, p_h, C_INNER_CARD, C_BLUE, radius=10, border_w=1)
    draw_badge(px2 + 15, p_y + 15, "PHYSICIAN WORKSTATION", C_BLUE, C_WHITE)
    draw.text((px2 + 230, p_y + 17), "Clinician Tele-Desk", fill=C_SLATE, font=font_card_sub)
    draw.text((px2 + 20, p_y + 55), "• Outpatient Department (OPD) Queue Roster with 'Call Next' controls", fill=C_DARK_TEXT, font=font_body)
    draw.text((px2 + 20, p_y + 85), "• Zero-Install WebRTC Video Suite (Camera/Mic Toggles & Screen Share)", fill=C_DARK_TEXT, font=font_body)
    draw.text((px2 + 20, p_y + 115), "• In-Call Clinical Scratchpad: Real-time observations auto-saved to DB", fill=C_DARK_TEXT, font=font_body)
    draw.text((px2 + 20, p_y + 145), "• Digital Rx Builder: Morning / Afternoon / Night dosage schedules", fill=C_DARK_TEXT, font=font_body)
    draw.text((px2 + 20, p_y + 175), "• Instant Access to verified patient lab reports and diagnostic uploads", fill=C_DARK_TEXT, font=font_body)

    # Portal 3: Admin Command Center
    px3 = px2 + p_w + p_gap
    draw_box(px3, p_y, p_w, p_h, C_INNER_CARD, C_AMBER, radius=10, border_w=1)
    draw_badge(px3 + 15, p_y + 15, "ADMIN COMMAND CENTER", C_AMBER, (15, 23, 42, 255))
    draw.text((px3 + 235, p_y + 17), "Executive Hospital Governance", fill=C_SLATE, font=font_card_sub)
    draw.text((px3 + 20, p_y + 55), "• ML Inflow Predictor: Next-day total outpatient forecast (R² = 0.927)", fill=C_DARK_TEXT, font=font_body)
    draw.text((px3 + 20, p_y + 85), "• 3 Key Callouts: Expected volume, peak hours & highest surge department", fill=C_DARK_TEXT, font=font_body)
    draw.text((px3 + 20, p_y + 115), "• Automated Doctor Roster Allocation Table (14 patients/doctor ratio)", fill=C_DARK_TEXT, font=font_body)
    draw.text((px3 + 20, p_y + 145), "• 4 Interactive Chart.js Views: 30-Day Line, Dept Doughnut, Bar & Area", fill=C_DARK_TEXT, font=font_body)
    draw.text((px3 + 20, p_y + 175), "• Consultation Funnel Conversion & Industry Cancellation Telemetry (<8.5%)", fill=C_DARK_TEXT, font=font_body)

    # Connectors Tier 1 -> Tier 2
    c_y1 = t1_y + t1_h
    c_y2 = c_y1 + 45
    draw.line([(450, c_y1), (450, c_y2)], fill=C_TEAL, width=3)
    draw.line([(1200, c_y1), (1200, c_y2)], fill=C_CYAN, width=3)
    draw.line([(1950, c_y1), (1950, c_y2)], fill=C_AMBER, width=3)

    draw.text((250, c_y1 + 12), "HTTPS / REST JSON APIs", fill=C_TEAL, font=font_arrow)
    draw.text((1050, c_y1 + 12), "WebRTC P2P Media (SRTP) / DataChannel", fill=C_CYAN, font=font_arrow)
    draw.text((1800, c_y1 + 12), "Asynchronous AJAX Polling", fill=C_AMBER, font=font_arrow)

    # =========================================================================
    # TIER 2: APPLICATION & CLINICAL LOGIC TIER (PHP 8 MVC GATEWAY)
    # =========================================================================
    t2_y = c_y2
    t2_h = 340
    draw_box(80, t2_y, 2240, t2_h, C_CARD_BG, C_TEAL, radius=16, border_w=2)
    draw_badge(110, t2_y + 15, "LAYER 2", C_TEAL, C_WHITE)
    draw.text((200, t2_y + 15), "APPLICATION & CLINICAL LOGIC TIER (PHP 8 MVC FRAMEWORK)", fill=C_WHITE, font=font_sec_title)

    # Security Interceptor Strip inside Layer 2
    sec_y = t2_y + 55
    draw_box(110, sec_y, 2180, 50, (15, 23, 42, 255), (71, 85, 105, 255), radius=8, border_w=1)
    draw_badge(125, sec_y + 10, "SECURITY GATEWAY", (225, 29, 72, 255), C_WHITE)
    draw.text((310, sec_y + 15), "32-Byte Cryptographic CSRF Tokens  •  BCRYPT Password Hashing (Cost 10)  •  Session RBAC Guard  •  IP Rate Limiter (6 req/5min)", fill=C_WHITE, font=font_card_title)

    # 4 Controller Modules inside Layer 2
    c_w = 525
    c_gap = 26
    c_box_y = sec_y + 65
    c_box_h = 200

    # Controller 1: Clinical Triage
    cx1 = 110
    draw_box(cx1, c_box_y, c_w, c_box_h, C_INNER_CARD, C_TEAL, radius=8, border_w=1)
    draw.text((cx1 + 15, c_box_y + 15), "1. Clinical Triage Controller", fill=C_TEAL, font=font_card_title)
    draw.text((cx1 + 15, c_box_y + 45), "• api/symptom_checker.php", fill=C_SLATE, font=font_card_sub)
    draw.text((cx1 + 15, c_box_y + 75), "• Sanitizes natural language patient inputs", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx1 + 15, c_box_y + 105), "• Subprocess bridge to Python NLP model", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx1 + 15, c_box_y + 135), "• Evaluates emergency triage urgency tiers", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx1 + 15, c_box_y + 165), "• Queries & surfaces active specialists", fill=C_DARK_TEXT, font=font_body)

    # Controller 2: ACID Booking & Payment
    cx2 = cx1 + c_w + c_gap
    draw_box(cx2, c_box_y, c_w, c_box_h, C_INNER_CARD, C_BLUE, radius=8, border_w=1)
    draw.text((cx2 + 15, c_box_y + 15), "2. ACID Booking & Payment Gateway", fill=C_BLUE, font=font_card_title)
    draw.text((cx2 + 15, c_box_y + 45), "• api/appointments.php & api/payments.php", fill=C_SLATE, font=font_card_sub)
    draw.text((cx2 + 15, c_box_y + 75), "• Enforces SELECT ... FOR UPDATE row locks", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx2 + 15, c_box_y + 105), "• Mathematically eliminates double-booking", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx2 + 15, c_box_y + 135), "• Multi-channel UPI QR / Card processing", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx2 + 15, c_box_y + 165), "• Generates printable PDF billing receipts", fill=C_DARK_TEXT, font=font_body)

    # Controller 3: Queue & Telemedicine
    cx3 = cx2 + c_w + c_gap
    draw_box(cx3, c_box_y, c_w, c_box_h, C_INNER_CARD, C_AMBER, radius=8, border_w=1)
    draw.text((cx3 + 15, c_box_y + 15), "3. Queue & Telemedicine Service", fill=C_AMBER, font=font_card_title)
    draw.text((cx3 + 15, c_box_y + 45), "• queue_service.php & consultation.php", fill=C_SLATE, font=font_card_sub)
    draw.text((cx3 + 15, c_box_y + 75), "• Adaptive wait-time prediction algorithm", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx3 + 15, c_box_y + 105), "• (QueuePos - Serving) × ConsultSpeed", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx3 + 15, c_box_y + 135), "• WebRTC meeting room UUID signaling", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx3 + 15, c_box_y + 165), "• In-call clinical scratchpad auto-saver", fill=C_DARK_TEXT, font=font_body)

    # Controller 4: Rx & Medication Reminders
    cx4 = cx3 + c_w + c_gap
    draw_box(cx4, c_box_y, c_w, c_box_h, C_INNER_CARD, C_GREEN, radius=8, border_w=1)
    draw.text((cx4 + 15, c_box_y + 15), "4. Rx Vault & Reminder Engine", fill=C_GREEN, font=font_card_title)
    draw.text((cx4 + 15, c_box_y + 45), "• prescriptions.php & email_service.php", fill=C_SLATE, font=font_card_sub)
    draw.text((cx4 + 15, c_box_y + 75), "• Formal PDF Rx with digital signatures", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx4 + 15, c_box_y + 105), "• Circadian routine: Morning/Afternoon/Night", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx4 + 15, c_box_y + 135), "• WhatsApp, SMS & Email dispatch engine", fill=C_DARK_TEXT, font=font_body)
    draw.text((cx4 + 15, c_box_y + 165), "• MIME finfo binary file vault validator", fill=C_DARK_TEXT, font=font_body)

    # Connectors Tier 2 -> Tier 3
    c2_y1 = t2_y + t2_h
    c2_y2 = c2_y1 + 45
    draw.line([(600, c2_y1), (600, c2_y2)], fill=C_CYAN, width=3)
    draw.line([(1800, c2_y1), (1800, c2_y2)], fill=C_AMBER, width=3)

    draw.text((380, c2_y1 + 12), "PDO Prepared Transactions (Port 3307)", fill=C_CYAN, font=font_arrow)
    draw.text((1600, c2_y1 + 12), "Command-Line Microservice Subprocess Calls", fill=C_AMBER, font=font_arrow)

    # =========================================================================
    # TIER 3: PERSISTENCE & MACHINE LEARNING TIER
    # =========================================================================
    t3_y = c2_y2
    t3_h = 370
    draw_box(80, t3_y, 2240, t3_h, C_CARD_BG, (99, 102, 241, 255), radius=16, border_w=2)
    draw_badge(110, t3_y + 15, "LAYER 3", (99, 102, 241, 255), C_WHITE)
    draw.text((200, t3_y + 15), "DATA PERSISTENCE & MACHINE LEARNING SUBSYSTEM", fill=C_WHITE, font=font_sec_title)

    # Subsystem A: Relational Database (Left)
    db_w = 1070
    db_x = 110
    db_y = t3_y + 60
    db_h = 290
    draw_box(db_x, db_y, db_w, db_h, C_INNER_CARD, C_CYAN, radius=10, border_w=1)
    draw_badge(db_x + 20, db_y + 15, "RELATIONAL DATA STORE", C_CYAN, (15, 23, 42, 255))
    draw.text((db_x + 240, db_y + 17), "MySQL 8 / MariaDB (Port 3307, 3NF)", fill=C_WHITE, font=font_card_title)
    draw.text((db_x + 20, db_y + 55), "• 12 Relational Tables: users, departments, doctor_profiles, appointments, payments,", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 80), "  consultations, prescriptions, medical_records, symptom_history, medication_reminders...", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 110), "• InnoDB Transaction Engine: Zero dirty reads, full ACID compliance, foreign key cascades", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 140), "• Concurrency Row Locking: SELECT ... FOR UPDATE serializes simultaneous slot reservations", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 170), "• Document Security Vault: Files stored with 32-char cryptographic hex names (random_bytes)", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 200), "• PHP finfo Inspection: Server-side binary magic-number validation (PDF, JPG, PNG, WebP)", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 230), "• 365-Day Historical Training Dataset (opd_daily_stats) powering time-series ML models", fill=C_DARK_TEXT, font=font_body)
    draw.text((db_x + 20, db_y + 260), "• Encrypted Session Storage: Strict HttpOnly session cookies with regeneration on login", fill=C_DARK_TEXT, font=font_body)

    # Subsystem B: Machine Learning Microservices (Right)
    ml_w = 1070
    ml_x = db_x + db_w + 40
    ml_y = db_y
    ml_h = db_h
    draw_box(ml_x, ml_y, ml_w, ml_h, C_INNER_CARD, C_AMBER, radius=10, border_w=1)
    draw_badge(ml_x + 20, ml_y + 15, "AI PREDICTIVE SUBSYSTEM", C_AMBER, (15, 23, 42, 255))
    draw.text((ml_x + 245, ml_y + 17), "Python Scikit-Learn Microservices", fill=C_WHITE, font=font_card_title)
    draw.text((ml_x + 20, ml_y + 55), "• Clinical NLP Classifier (ai/predict_symptom.py, symptom_model.joblib):", fill=C_WHITE, font=font_body)
    draw.text((ml_x + 35, ml_y + 80), "- TF-IDF n-gram vectorization + Calibrated Multi-Class Logistic Regression", fill=C_DARK_TEXT, font=font_body)
    draw.text((ml_x + 35, ml_y + 105), "- 98.75% validation accuracy across 8 specialized clinical hospital departments", fill=C_DARK_TEXT, font=font_body)
    draw.text((ml_x + 35, ml_y + 130), "- Platt scaling for calibrated confidence percentages & urgency classification", fill=C_DARK_TEXT, font=font_body)
    draw.text((ml_x + 20, ml_y + 160), "• OPD Patient Inflow Regressor (ai/predict_opd.py, opd_model.joblib):", fill=C_WHITE, font=font_body)
    draw.text((ml_x + 35, ml_y + 185), "- RandomForestRegressor (120 Estimators, R² = 0.927, MAE ±5.49 patients)", fill=C_DARK_TEXT, font=font_body)
    draw.text((ml_x + 35, ml_y + 210), "- Features: Day of Week, Holiday Flag, Month, Season, Lag-1 Count, 7-Day Moving Avg", fill=C_DARK_TEXT, font=font_body)
    draw.text((ml_x + 35, ml_y + 235), "- 3 Key Output Callouts: Predicted Volume, Peak Hours (10 AM - 1 PM), Highest Surge Dept", fill=C_DARK_TEXT, font=font_body)
    draw.text((ml_x + 35, ml_y + 260), "- Automated Doctor Roster Allocation Table based on 14 patients/doctor capacity ceiling", fill=C_DARK_TEXT, font=font_body)

    # Save to disk
    out_dir = os.path.dirname(__file__)
    png_path = os.path.join(out_dir, "system_architecture_diagram.png")
    img.save(png_path, "PNG")
    print(f"PNG Architecture Diagram saved to: {png_path}")

    # Also save into assets/images/
    assets_img_dir = os.path.join(out_dir, "assets", "images")
    os.makedirs(assets_img_dir, exist_ok=True)
    img.save(os.path.join(assets_img_dir, "system_architecture_diagram.png"), "PNG")

if __name__ == '__main__':
    render_diagram()
