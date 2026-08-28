<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: ../auth/login_staff.php"); exit(); }
$staff_id = $_SESSION['staff_id'];

//Handle Approvals
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

//Pending Patient Requests
$pending_reqs = $conn->query("
    SELECT w.Patient_ID, w.H_ID, p.Name, p.Blood_Grp, p.Urgency, w.Reqd_Resource 
    FROM Waitlisted_As w 
    JOIN Patient p ON w.Patient_ID = p.Patient_ID 
    WHERE w.Wait_Status = 'Pending'
");

//Staff Notifications
$notif_stmt = $conn->query("SELECT Message, Created_At FROM Notifications WHERE Target_Role = 'Staff' ORDER BY Created_At DESC LIMIT 5");

//Feature 6: Rare Trait Priority Scanner
$q6 = $conn->query("
    WITH TotalDonors AS (SELECT COUNT(DISTINCT Donor_ID) AS Total FROM Donor),
    MarkerFrequency AS (SELECT Marker_ID, COUNT(Donor_ID) AS Donor_Count, (COUNT(Donor_ID) * 100.0 / (SELECT Total FROM TotalDonors)) AS Rarity_Percent FROM Possesses GROUP BY Marker_ID)
    SELECT p.Patient_ID, p.Name, mf.Marker_ID, mf.Rarity_Percent FROM Patient p JOIN Has_Marker hm ON p.Patient_ID = hm.Patient_ID JOIN MarkerFrequency mf ON hm.Marker_ID = mf.Marker_ID WHERE mf.Rarity_Percent < 1.0
");

//Feature 8: Waitlist Priority Escalator
$q8 = $conn->query("
    SELECT Patient_ID, Reqd_Resource, Date_Added, CURRENT_DATE - Date_Added AS Days_Waiting, PERCENT_RANK() OVER (PARTITION BY Reqd_Resource ORDER BY CURRENT_DATE - Date_Added ASC) AS Wait_Time_Percentile FROM Waitlisted_As WHERE Wait_Status = 'Active'
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Doctor Dashboard - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Medical Officer Patient Care Hub</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-black hover:bg-slate-800 px-4 py-2 rounded-lg text-white transition">Logout</a>
        </div>

        <?php if($msg): ?><div class="bg-slate-100 border border-slate-300 text-slate-900 text-xs p-3 rounded-lg mb-6"><?= $msg ?></div><?php endif; ?>

        <!-- ROW 1: Pending Approvals & Notifications -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 items-stretch">
            
            <div class="md:col-span-2 bg-white border border-slate-200 p-6 rounded-2xl shadow-sm flex flex-col">
                <h2 class="text-sm font-bold text-slate-900 uppercase mb-4">Pending Patient Requests</h2>
                <div class="overflow-auto max-h-[350px] w-full flex-1">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white z-10">
                            <th class="pb-2">ID</th><th>Name</th><th>Blood</th><th>Urgency</th><th>Resource</th><th>Actions</th>
                        </tr>
                        <?php if($pending_reqs && $pending_reqs->num_rows > 0): ?>
                            <?php while($row = $pending_reqs->fetch_assoc()): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-3"><?= $row['Patient_ID'] ?></td>
                                <td><?= htmlspecialchars($row['Name']) ?></td>
                                <td class="font-bold text-slate-900"><?= $row['Blood_Grp'] ?></td>
                                <td><?= $row['Urgency'] ?>/10</td>
                                <td><?= $row['Reqd_Resource'] ?></td>
                                <td class="py-3">
                                    <form method="POST" class="flex gap-2">
                                        <input type="hidden" name="p_id" value="<?= $row['Patient_ID'] ?>">
                                        <input type="hidden" name="h_id" value="<?= $row['H_ID'] ?>">
                                        <button type="submit" name="action" value="approve" class="app-button bg-black hover:bg-slate-800 text-white px-3 py-1 rounded transition text-xs">Approve</button>
                                        <button type="submit" name="action" value="reject" class="app-button bg-black hover:bg-slate-800 text-white px-3 py-1 rounded transition text-xs">Reject</button>
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

            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm flex flex-col">
                <h2 class="text-sm font-bold text-slate-900 uppercase mb-4">Staff Alerts</h2>
                <div class="overflow-auto max-h-[350px] w-full flex-1 space-y-3">
                    <?php if($notif_stmt && $notif_stmt->num_rows > 0): ?>
                        <?php while($alert = $notif_stmt->fetch_assoc()): ?>
                            <div class="p-3 bg-slate-50 border-l-2 border-slate-900 rounded text-xs text-slate-900">
                                <span class="block text-slate-500 mb-1"><?= date('M j, Y, g:i a', strtotime($alert['Created_At'])) ?></span>
                                <?= htmlspecialchars($alert['Message']) ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-xs text-slate-500 italic">No new staff alerts.</p>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>

        <!-- ROW 2: Features 6 & 8 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-stretch">
            
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm flex flex-col">
                <h2 class="text-sm font-bold text-slate-900 uppercase mb-3">Feature 6: Rare Trait Priority</h2>
                <div class="overflow-auto max-h-[350px] w-full flex-1">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Patient ID</th><th>Name</th><th>Marker ID</th><th>Rarity %</th>
                        </tr>
                        <?php if($q6 && $q6->num_rows > 0): ?>
                            <?php while($r = $q6->fetch_assoc()): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2"><?= $r['Patient_ID'] ?></td>
                                <td><?= htmlspecialchars($r['Name']) ?></td>
                                <td><?= $r['Marker_ID'] ?></td>
                                <td class="font-bold"><?= round($r['Rarity_Percent'], 2) ?>%</td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="py-4 text-slate-500 italic">No rare traits detected.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm flex flex-col">
                <h2 class="text-sm font-bold text-slate-900 uppercase mb-3">Feature 8: Waitlist Escalator</h2>
                <div class="overflow-auto max-h-[350px] w-full flex-1">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Patient ID</th><th>Resource</th><th>Days Waiting</th><th>Percentile</th>
                        </tr>
                        <?php if($q8 && $q8->num_rows > 0): ?>
                            <?php while($r = $q8->fetch_assoc()): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-2"><?= $r['Patient_ID'] ?></td>
                                <td><?= $r['Reqd_Resource'] ?></td>
                                <td><?= $r['Days_Waiting'] ?></td>
                                <td class="font-bold"><?= round($r['Wait_Time_Percentile'] * 100) ?>th</td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="py-4 text-slate-500 italic">No active waitlist data.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</body>
</html>