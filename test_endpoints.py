import urllib.request
import re
import http.cookiejar
import json

base_url = 'http://localhost/carepulse-ai'

def test_flow():
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

    # 1. Test Homepage
    resp = opener.open(f'{base_url}/index.php')
    html = resp.read().decode('utf-8')
    assert 'CarePulse' in html
    assert 'Master Navigation' in html
    print("[PASS] Homepage loaded successfully.")

    # 2. Get CSRF Token from login page
    login_html = opener.open(f'{base_url}/login.php').read().decode('utf-8')
    m = re.search(r'name="csrf_token"\s+value="([^"]+)"', login_html)
    assert m, "CSRF token not found"
    csrf_token = m.group(1)
    print(f"[PASS] CSRF token retrieved: {csrf_token[:12]}...")

    # 3. Test Admin Login
    login_payload = json.dumps({
        'action': 'login',
        'email': 'admin@carepulse.ai',
        'password': 'Password@123',
        'csrf_token': csrf_token
    }).encode('utf-8')
    req_login = urllib.request.Request(f'{base_url}/api/auth.php', data=login_payload, headers={'Content-Type': 'application/json'})
    login_res = json.loads(opener.open(req_login).read().decode('utf-8'))
    assert login_res['success'] is True
    print(f"[PASS] Admin login successful: role = {login_res['role']}, redirect = {login_res['redirect']}")

    # 4. Test OPD Prediction API
    req_opd = urllib.request.Request(f'{base_url}/api/admin_stats.php?action=opd_prediction')
    opd_res = json.loads(opener.open(req_opd).read().decode('utf-8'))
    assert opd_res['success'] is True
    print(f"[PASS] OPD Prediction API successful:")
    print(f"       - Predicted Patients Tomorrow: {opd_res['predicted_total_inflow']}")
    print(f"       - Expected Peak Time: {opd_res['expected_peak_hours']}")
    print(f"       - Highest Demand Department: {opd_res['highest_demand_department']}")
    print(f"       - Doctor Allocation: {opd_res['required_doctor_allocation']}")

    # 5. Test Analytics API (Funnel, Cancellation, Monthly)
    req_stats = urllib.request.Request(f'{base_url}/api/admin_stats.php?action=summary')
    stats_res = json.loads(opener.open(req_stats).read().decode('utf-8'))
    assert stats_res['success'] is True
    print(f"[PASS] Analytics API successful:")
    print(f"       - Consultation Funnel: {stats_res['funnel_stats']['completed']['pct']}% completed")
    print(f"       - Cancellation Rate: {stats_res['cancellation_stats']['cancellation_rate']}%")
    print(f"       - Total Bookings: {stats_res['funnel_stats']['booked']['count']}")
    print(f"       - Monthly comparison data points: {len(stats_res['monthly_revenue'])}")

    # 6. Test Doctor Login
    cj2 = http.cookiejar.CookieJar()
    opener2 = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj2))
    login2_html = opener2.open(f'{base_url}/login.php').read().decode('utf-8')
    m2 = re.search(r'name="csrf_token"\s+value="([^"]+)"', login2_html)
    csrf2 = m2.group(1)
    doc_payload = json.dumps({
        'action': 'login',
        'email': 'doctor.sharma@carepulse.ai',
        'password': 'Password@123',
        'csrf_token': csrf2
    }).encode('utf-8')
    req_doc = urllib.request.Request(f'{base_url}/api/auth.php', data=doc_payload, headers={'Content-Type': 'application/json'})
    doc_res = json.loads(opener2.open(req_doc).read().decode('utf-8'))
    assert doc_res['success'] is True
    print(f"[PASS] Doctor login successful: role = {doc_res['role']}, redirect = {doc_res['redirect']}")

    # 7. Test Telemedicine Room
    tele_resp = opener.open(f'{base_url}/telemedicine/index.php')
    assert tele_resp.getcode() == 200
    tele_html = tele_resp.read().decode('utf-8')
    assert 'WebRTC' in tele_html
    print(f"[PASS] Telemedicine suite loaded successfully.")

    print("\nALL ENDPOINTS AND FLOWS VERIFIED SUCCESSFULLY!")

if __name__ == '__main__':
    test_flow()
