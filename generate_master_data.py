import pandas as pd
import random
from faker import Faker
from datetime import datetime, timedelta

fake = Faker()
blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
hospital_ids = list(range(1, 11))

# 1. Generate Donors (500 Donors)
donors = []
for i in range(1, 501):
    donors.append({
        'Donor_ID': i,
        'Name': fake.name(),
        'Blood_Grp': random.choice(blood_groups),
        'Phone': fake.phone_number()[:15],
        'Recovery_Status': random.choice(['Recovered', 'Recovered', 'Recovering']) # Weight toward Recovered
    })
pd.DataFrame(donors).to_csv('donor.csv', index=False)

# 2. Generate Patients (200 Patients)
patients = []
# Edge Case: Feature 9 (Suspicious Activity) - Staff 2 registers 45 urgent patients
for i in range(1, 46):
    patients.append({
        'Patient_ID': i, 'Name': fake.name(), 'Blood_Grp': random.choice(blood_groups),
        'Urgency': random.randint(8, 10), 'Staff_ID': 2
    })
# Normal Patients
for i in range(46, 201):
    patients.append({
        'Patient_ID': i, 'Name': fake.name(), 'Blood_Grp': random.choice(blood_groups),
        'Urgency': random.randint(1, 7), 'Staff_ID': random.choice([5, 7, 10, 12, 14])
    })
pd.DataFrame(patients).to_csv('patient.csv', index=False)

# 3. Generate Tissue Marker Mappings (Possesses & Has_Marker)
possesses = []
has_marker = []

# Edge Case: Feature 6 (Rare Trait) - Marker 99 is assigned to only 2 Donors and 1 Patient
possesses.extend([{'Donor_ID': 1, 'Marker_ID': 99}, {'Donor_ID': 2, 'Marker_ID': 99}])
has_marker.append({'Patient_ID': 1, 'Marker_ID': 99})

for i in range(1, 501):
    # Give each donor 2-4 random normal markers (1-15)
    markers = random.sample(range(1, 16), random.randint(2, 4))
    for m in markers:
        possesses.append({'Donor_ID': i, 'Marker_ID': m})

for i in range(1, 201):
    markers = random.sample(range(1, 16), random.randint(2, 4))
    for m in markers:
        has_marker.append({'Patient_ID': i, 'Marker_ID': m})

pd.DataFrame(possesses).drop_duplicates().to_csv('possesses.csv', index=False)
pd.DataFrame(has_marker).drop_duplicates().to_csv('has_marker.csv', index=False)

# 4. Generate Waitlisted_As
waitlist = []
# Edge Case: Feature 2 (Resource Rebalancer) - H_ID 2 has a massive deficit for O-
for i in range(50, 100):
    waitlist.append({
        'Patient_ID': i, 'H_ID': 2, 'Reqd_Resource': 'O-',
        'Date_Added': (datetime.today() - timedelta(days=random.randint(1, 30))).strftime('%Y-%m-%d'),
        'Wait_Status': 'Active'
    })
# Normal Waitlist for others (supports Feature 7: Priority Escalator)
for i in range(100, 201):
    waitlist.append({
        'Patient_ID': i, 'H_ID': random.choice(hospital_ids), 'Reqd_Resource': random.choice(blood_groups),
        'Date_Added': (datetime.today() - timedelta(days=random.randint(1, 100))).strftime('%Y-%m-%d'),
        'Wait_Status': random.choice(['Active', 'Treated'])
    })
pd.DataFrame(waitlist).to_csv('waitlisted_as.csv', index=False)

# 5. Generate Inventory_Log
inventory = []
unit_id = 1

# Edge Case: Feature 2 (Resource Rebalancer) - H_ID 3 has a massive surplus of O-
for _ in range(100):
    inventory.append({
        'Unit_ID': unit_id, 'H_ID': 3, 'Donor_ID': random.randint(1, 500),
        'Resc_Type': 'O-', 'Status': 'Available',
        'Receipt_Date': '2026-08-25', 'Usage_Date': '', 'Expiry_Date': '2026-09-30'
    })
    unit_id += 1

# Edge Case: Feature 1 (Stockout Predictor) - H_ID 1 high burn rate (300 used, 15 available)
for _ in range(300):
    inventory.append({
        'Unit_ID': unit_id, 'H_ID': 1, 'Donor_ID': random.randint(1, 500),
        'Resc_Type': 'A+', 'Status': 'Used',
        'Receipt_Date': '2026-08-01', 'Usage_Date': '2026-08-15', 'Expiry_Date': '2026-09-10'
    })
    unit_id += 1
for _ in range(15):
    inventory.append({
        'Unit_ID': unit_id, 'H_ID': 1, 'Donor_ID': random.randint(1, 500),
        'Resc_Type': 'A+', 'Status': 'Available',
        'Receipt_Date': '2026-08-30', 'Usage_Date': '', 'Expiry_Date': '2026-10-01'
    })
    unit_id += 1

# Edge Case: Feature 3 (Donor Loyalty) - 50 Donors in 2025, 15 return in 2026
for d_id in range(400, 450):
    inventory.append({
        'Unit_ID': unit_id, 'H_ID': random.choice(hospital_ids), 'Donor_ID': d_id,
        'Resc_Type': 'B+', 'Status': 'Used',
        'Receipt_Date': '2025-05-15', 'Usage_Date': '2025-05-20', 'Expiry_Date': '2025-06-25'
    })
    unit_id += 1
for d_id in range(400, 415): # 30% retention
    inventory.append({
        'Unit_ID': unit_id, 'H_ID': random.choice(hospital_ids), 'Donor_ID': d_id,
        'Resc_Type': 'B+', 'Status': 'Available',
        'Receipt_Date': '2026-06-10', 'Usage_Date': '', 'Expiry_Date': '2026-07-20'
    })
    unit_id += 1

# Random background data (Supports Feature 8: Wastage Tracker & Feature 4: Nearby Locator)
for _ in range(500):
    status = random.choice(['Available', 'Used', 'Expired'])
    usage = '2026-08-20' if status == 'Used' else ''
    inventory.append({
        'Unit_ID': unit_id, 'H_ID': random.choice(hospital_ids), 'Donor_ID': random.randint(1, 500),
        'Resc_Type': random.choice(blood_groups), 'Status': status,
        'Receipt_Date': '2026-08-01', 'Usage_Date': usage, 'Expiry_Date': '2026-09-10'
    })
    unit_id += 1

pd.DataFrame(inventory).to_csv('inventory_log.csv', index=False)
print("6 CSV files generated. Import them in this order: donor -> patient -> possesses -> has_marker -> waitlisted_as -> inventory_log")