<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: ../auth/login_staff.php"); exit(); }
$staff_id = $_SESSION['staff_id'];

// Handle Approvals
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $pid = $_POST['p_id'];
    $hid = $_POST['h_id'];
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'Active' : 'Rejected';
    $notif_text = ($action === 'approve') ? "Your resource request has been APPROVED. You are now Active on the waitlist." : "Your resource request has been REJECTED by your Medical Officer.";
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE Waitlisted_As SET Wait_Status = ? WHERE Patient_ID = ? AND H_ID = ?");
        $stmt->bind_param("sii", $status, $pid, $hid);
        $stmt->execute();
        
        $notif = $conn->prepare("INSERT INTO Notifications (Target_Role, Target_ID, Message) VALUES ('Patient', ?, ?)");
        $notif->bind_param("is", $pid, $notif_text);
        $notif->execute();
        
        $conn->commit();
        $_SESSION['flash_msg'] = "Patient #$pid has been $status.";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['flash_msg'] = "Error updating patient status.";
    }
    header("Location: staff_dash.php");
    exit();
}

$msg = $_SESSION['flash_msg'] ?? "";
unset($_SESSION['flash_msg']);

// Pending Patient Requests
$pending_reqs = $conn->query("
    SELECT w.Patient_ID, w.H_ID, p.Name, p.Blood_Grp, p.Urgency, w.Reqd_Resource 
    FROM Waitlisted_As w 
    JOIN Patient p ON w.Patient_ID = p.Patient_ID 
    WHERE w.Wait_Status = 'Pending'
");

// Staff Notifications
$notif_stmt = $conn->query("SELECT Message, Created_At FROM Notifications WHERE Target_Role = 'Staff' ORDER BY Created_At DESC LIMIT 5");

// Feature 6: Rare Trait Priority Scanner
$q6 = $conn->query("
    WITH TotalDonors AS (SELECT COUNT(DISTINCT Donor_ID) AS Total FROM Donor),
    MarkerFrequency AS (SELECT Marker_ID, COUNT(Donor_ID) AS Donor_Count, (COUNT(Donor_ID) * 100.0 / (SELECT Total FROM TotalDonors)) AS Rarity_Percent FROM Possesses GROUP BY Marker_ID)
    SELECT p.Patient_ID, p.Name, mf.Marker_ID, mf.Rarity_Percent FROM Patient p JOIN Has_Marker hm ON p.Patient_ID = hm.Patient_ID JOIN MarkerFrequency mf ON hm.Marker_ID = mf.Marker_ID WHERE mf.Rarity_Percent < 1.0
");

// Feature 7: Emergency Nearby Resource Locator 
$q7 = $conn->query("
    SELECT 
        TargetHospital.H_ID AS Sending_Hospital, 
        TargetHospital.Facility_Name, 
        zd.Distance_KM, 
        i.Resc_Type,
        COUNT(i.Unit_ID) AS Available_Units
    FROM Zone_Distances zd
    JOIN Hospital TargetHospital ON zd.Zone_B = TargetHospital.Zone
    JOIN Inventory_Log i ON TargetHospital.H_ID = i.H_ID
    WHERE i.Status = 'Available'
    GROUP BY TargetHospital.H_ID, TargetHospital.Facility_Name, zd.Distance_KM, i.Resc_Type
    ORDER BY zd.Distance_KM ASC LIMIT 3
");

// Feature 8: Waitlist Priority Escalator
$q8 = $conn->query("
    SELECT Patient_ID, Reqd_Resource, Date_Added, CURRENT_DATE - Date_Added AS Days_Waiting, PERCENT_RANK() OVER (PARTITION BY Reqd_Resource ORDER BY CURRENT_DATE - Date_Added ASC) AS Wait_Time_Percentile FROM Waitlisted_As WHERE Wait_Status = 'Active'
");

//Feature 9: Best Match Scoring System
$patients_list = $conn->query("SELECT Patient_ID, Name, Blood_Grp FROM Patient ORDER BY Patient_ID ASC");
$selected_patient_id = isset($_GET['match_patient_id']) ? intval($_GET['match_patient_id']) : 0;
$q9 = null;

if ($selected_patient_id > 0) {
    $q9 = $conn->query("
        WITH PatientInfo AS (SELECT Patient_ID, Blood_Grp FROM Patient WHERE Patient_ID = $selected_patient_id),
        BloodScoring AS (
            SELECT d.Donor_ID, d.Name AS Donor_Name, d.Blood_Grp AS Donor_Blood, bc.Score AS Blood_Score
            FROM Donor d CROSS JOIN PatientInfo p JOIN Blood_Compatibility bc ON p.Blood_Grp = bc.Recipient_Blood_Grp AND d.Blood_Grp = bc.Donor_Blood_Grp
            WHERE d.Recovery_Status = 'Recovered'
        ),
        TissueScoring AS (
            SELECT dp.Donor_ID, (COUNT(ph.Marker_ID) * 10) AS Tissue_Score FROM Possesses dp JOIN Has_Marker ph ON dp.Marker_ID = ph.Marker_ID WHERE ph.Patient_ID = $selected_patient_id GROUP BY dp.Donor_ID
        )
        SELECT b.Donor_ID, b.Donor_Name, b.Donor_Blood, b.Blood_Score, COALESCE(t.Tissue_Score, 0) AS Tissue_Score, (b.Blood_Score + COALESCE(t.Tissue_Score, 0)) AS Total_Match_Score
        FROM BloodScoring b LEFT JOIN TissueScoring t ON b.Donor_ID = t.Donor_ID
        ORDER BY Total_Match_Score DESC, b.Blood_Score DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Doctor Dashboard - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
    <script>
        function toggleSection(id) {
            const section = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            if (section.classList.contains('hidden')) {
                section.classList.remove('hidden');
                icon.innerHTML = '▲';
            } else {
                section.classList.add('hidden');
                icon.innerHTML = '▼';
            }
        }
    </script>
</head>
<body class="app-page bg-slate-50 text-slate-900 min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Medical Officer Patient Care Hub</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-black hover:bg-slate-800 px-4 py-2 rounded-lg text-white transition">Logout</a>
        </div>

        <?php if($msg): ?><div class="bg-slate-100 border border-slate-300 text-slate-900 text-xs p-3 rounded-lg mb-6"><?= $msg ?></div><?php endif; ?>

        <!-- Pending Approvals (Always visible) -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm mb-6">
            <h2 class="text-sm font-bold text-slate-900 uppercase mb-4">Pending Patient Requests</h2>
            <div class="overflow-auto max-h-[250px] w-full">
                <table class="w-full text-xs text-left text-slate-900">
                    <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                        <th class="pb-2">ID</th><th>Name</th><th>Blood</th><th>Urgency</th><th>Resource</th><th>Actions</th>
                    </tr>
                    <?php if($pending_reqs && $pending_reqs->num_rows > 0): ?>
                        <?php while($row = $pending_reqs->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-3"><?= $row['Patient_ID'] ?></td>
                            <td><?= htmlspecialchars($row['Name']) ?></td>
                            <td class="font-bold"><?= $row['Blood_Grp'] ?></td>
                            <td><?= $row['Urgency'] ?>/10</td>
                            <td><?= $row['Reqd_Resource'] ?></td>
                            <td class="py-3">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="p_id" value="<?= $row['Patient_ID'] ?>">
                                    <input type="hidden" name="h_id" value="<?= $row['H_ID'] ?>">
                                    <button type="submit" name="action" value="approve" class="bg-black text-white px-3 py-1 rounded text-xs">Approve</button>
                                    <button type="submit" name="action" value="reject" class="bg-red-600 text-white px-3 py-1 rounded text-xs">Reject</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="py-4 text-slate-500 italic">No pending registrations.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Advanced SQL Features (Clicky/Collapsible) -->
        <div class="dashboard-grid grid grid-cols-1 md:grid-cols-2 items-start gap-6">

            <!-- Feature 6 -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden self-start">
                <button onclick="toggleSection('feat6')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Rare Trait Priority</h2>
                    <span id="icon-feat6" class="text-slate-500 text-xs">▼</span>
                </button>
                <div id="feat6" class="hidden p-4 overflow-auto max-h-[300px]">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200">
                            <th class="pb-2">Patient ID</th><th>Name</th><th>Marker ID</th><th>Rarity %</th>
                        </tr>
                        <?php if($q6 && $q6->num_rows > 0): while($r = $q6->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2"><?= $r['Patient_ID'] ?></td>
                            <td><?= htmlspecialchars($r['Name']) ?></td>
                            <td><?= $r['Marker_ID'] ?></td>
                            <td class="font-bold"><?= round($r['Rarity_Percent'], 2) ?>%</td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="py-4 text-slate-500 italic">No rare traits detected.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Feature 7 -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden self-start">
                <button onclick="toggleSection('feat7')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Emergency Locator</h2>
                    <span id="icon-feat7" class="text-slate-500 text-xs">▼</span>
                </button>
                <div id="feat7" class="hidden p-4 overflow-auto max-h-[300px]">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200">
                            <th class="pb-2">Hospital ID</th><th>Facility</th><th>Resource</th><th>Distance</th><th>Available Units</th>
                        </tr>
                        <?php if($q7 && $q7->num_rows > 0): while($r = $q7->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2"><?= $r['Sending_Hospital'] ?></td>
                            <td class="font-bold"><?= htmlspecialchars($r['Facility_Name']) ?></td>
                            <td class="text-slate-500"><?= $r['Resc_Type'] ?></td>
                            <td><?= $r['Distance_KM'] ?> km</td>
                            <td class="text-green-600 font-bold"><?= $r['Available_Units'] ?> Units</td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="py-4 text-slate-500 italic">No nearby resources found.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Feature 8 -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden self-start">
                <button onclick="toggleSection('feat8')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Waitlist Escalator</h2>
                    <span id="icon-feat8" class="text-slate-500 text-xs">▼</span>
                </button>
                <div id="feat8" class="hidden p-4 overflow-auto max-h-[300px]">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200">
                            <th class="pb-2">Patient ID</th><th>Resource</th><th>Days Waiting</th><th>Percentile</th>
                        </tr>
                        <?php if($q8 && $q8->num_rows > 0): while($r = $q8->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2"><?= $r['Patient_ID'] ?></td>
                            <td><?= $r['Reqd_Resource'] ?></td>
                            <td><?= $r['Days_Waiting'] ?></td>
                            <td class="font-bold text-red-500"><?= round($r['Wait_Time_Percentile'] * 100) ?>th</td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="4" class="py-4 text-slate-500 italic">No active waitlist data.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Feature 9 -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden self-start">
                <button onclick="toggleSection('feat9')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Best Match Scoring</h2>
                    <span id="icon-feat9" class="text-slate-500 text-xs"><?= ($selected_patient_id > 0) ? '▲' : '▼' ?></span>
                </button>
                <div id="feat9" class="<?= ($selected_patient_id > 0) ? 'p-4 overflow-auto max-h-[400px]' : 'hidden p-4 overflow-auto max-h-[400px]' ?>">
                    
                    <!-- Dynamic Selection Form -->
                    <div class="mb-4 bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <form method="GET" action="staff_dash.php" class="flex gap-2 items-end">
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-slate-600 mb-1">Select Target Patient</label>
                                <select name="match_patient_id" class="w-full bg-white border border-slate-300 rounded p-2 text-xs focus:outline-none focus:border-slate-900" required>
                                    <option value="">-- Choose Patient --</option>
                                    <?php while($p_opt = $patients_list->fetch_assoc()): ?>
                                        <option value="<?= $p_opt['Patient_ID'] ?>" <?= ($selected_patient_id == $p_opt['Patient_ID']) ? 'selected' : '' ?>>
                                            ID <?= $p_opt['Patient_ID'] ?>: <?= htmlspecialchars($p_opt['Name']) ?> (<?= $p_opt['Blood_Grp'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="bg-black text-white px-4 py-2 rounded text-xs font-bold hover:bg-slate-800 transition">Find Matches</button>
                        </form>
                    </div>

                    <!-- Scoring Table -->
                    <?php if($selected_patient_id > 0): ?>
                        <table class="w-full text-xs text-left text-slate-900">
                            <tr class="text-slate-600 border-b border-slate-200">
                                <th class="pb-2">Donor</th><th>Blood Match</th><th>Tissue (HLA)</th><th>Total Score</th>
                            </tr>
                            <?php if($q9 && $q9->num_rows > 0): while($r = $q9->fetch_assoc()): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2">#<?= $r['Donor_ID'] ?> - <?= htmlspecialchars($r['Donor_Name']) ?></td>
                                <td><?= $r['Blood_Score'] ?></td>
                                <td>+<?= $r['Tissue_Score'] ?></td>
                                <td class="font-bold text-green-600"><?= $r['Total_Match_Score'] ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" class="py-4 text-slate-500 italic">No eligible donor matches available.</td></tr>
                            <?php endif; ?>
                        </table>
                    <?php else: ?>
                        <div class="py-4 text-center text-slate-500 italic text-xs">Please select a patient above to calculate match scores.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</body>
</html>