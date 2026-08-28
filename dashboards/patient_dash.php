<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['patient_id'])) { 
    header("Location: ../auth/login_patient.php"); 
    exit(); 
}
$pid = $_SESSION['patient_id'];

//Patient & Waitlist Profile
$stmt = $conn->prepare("
    SELECT p.*, w.Reqd_Resource, w.Date_Added, w.Wait_Status, h.Facility_Name 
    FROM Patient p 
    LEFT JOIN Waitlisted_As w ON p.Patient_ID = w.Patient_ID 
    LEFT JOIN Hospital h ON w.H_ID = h.H_ID 
    WHERE p.Patient_ID = ?
");
$stmt->bind_param("i", $pid);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

// Fetch Notifications
$notif_stmt = $conn->prepare("SELECT Message, Created_At FROM Notifications WHERE Target_Role = 'Patient' AND Target_ID = ? ORDER BY Created_At DESC");
$notif_stmt->bind_param("i", $pid);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Tracker - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 min-h-screen p-8">
    <div class="max-w-3xl mx-auto space-y-6">
        
        <div class="flex justify-between items-center border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Patient Live Waitlist Queue</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-black hover:bg-slate-800 px-4 py-2 rounded-lg text-white transition">Logout</a>
        </div>
        
        <!-- Queue Demands -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-slate-900 uppercase">Queue Demands</h2>
            <p class="text-sm text-slate-900"><strong>Patient ID:</strong> #<?= $pid ?></p>
            <p class="text-sm text-slate-900"><strong>Name:</strong> <?= htmlspecialchars($patient['Name']) ?></p>
            <p class="text-sm text-slate-900"><strong>Blood Group:</strong> <?= htmlspecialchars($patient['Blood_Grp']) ?></p>
            <p class="text-sm text-slate-900"><strong>Urgency Score:</strong> <?= $patient['Urgency'] ?> / 10</p>
            <p class="text-sm text-slate-900"><strong>Hospital Facility:</strong> <?= htmlspecialchars($patient['Facility_Name'] ?? 'Unassigned') ?></p>
            <p class="text-sm text-slate-900"><strong>Required Resource:</strong> <?= htmlspecialchars($patient['Reqd_Resource'] ?? 'N/A') ?></p>
            <p class="text-sm text-slate-900"><strong>Wait Status:</strong> 
                <span class="px-2 py-1 rounded border border-slate-300 bg-slate-50 font-bold text-xs">
                    <?= $patient['Wait_Status'] ?? 'Pending' ?>
                </span>
            </p>
        </div>

        <!-- Waitlist Alerts -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase">Waitlist Alerts</h2>
            <div class="space-y-3">
                <?php if ($notifications->num_rows > 0): ?>
                    <?php while($alert = $notifications->fetch_assoc()): ?>
                        <div class="p-3 bg-slate-50 border-l-2 border-slate-900 rounded text-xs text-slate-900">
                            <span class="block text-slate-500 mb-1"><?= date('M j, Y, g:i a', strtotime($alert['Created_At'])) ?></span>
                            <?= htmlspecialchars($alert['Message']) ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-500 italic">No new notifications.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</body>
</html>