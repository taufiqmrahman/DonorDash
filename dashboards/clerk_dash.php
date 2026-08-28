<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: ../auth/login_staff.php"); exit(); }

$msg = "";
// Process Approval or Rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $pid = $_POST['p_id'];
    $hid = $_POST['h_id'];
    $action = $_POST['action'];
    $status = ($action === 'approve') ? 'Active' : 'Rejected';
    $notif_text = ($action === 'approve') ? "Your resource request has been APPROVED. You are now Active on the waitlist." : "Your resource request has been REJECTED by hospital staff.";
    
    $conn->begin_transaction();
    try {
        // Update Waitlist Status
        $stmt = $conn->prepare("UPDATE Waitlisted_As SET Wait_Status = ? WHERE Patient_ID = ? AND H_ID = ?");
        $stmt->bind_param("sii", $status, $pid, $hid);
        $stmt->execute();
        
        // Notify Patient
        $notif = $conn->prepare("INSERT INTO Notifications (Target_Role, Target_ID, Message) VALUES ('Patient', ?, ?)");
        $notif->bind_param("is", $pid, $notif_text);
        $notif->execute();
        
        $conn->commit();
        $msg = "Patient $pid has been $status and notified.";
    } catch(Exception $e) {
        $conn->rollback();
        $msg = "Error updating status.";
    }
}

// Fetch Pending Requests
$pending_reqs = $conn->query("
    SELECT w.Patient_ID, w.H_ID, p.Name, p.Blood_Grp, p.Urgency, w.Reqd_Resource 
    FROM Waitlisted_As w 
    JOIN Patient p ON w.Patient_ID = p.Patient_ID 
    WHERE w.Wait_Status = 'Pending'
");
?>
<!DOCTYPE html>
<html lang="en" >
<head>
    <meta charset="UTF-8"><title>Data Clerk Hub - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-100 min-h-screen p-8">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Data Entry & Approval Hub</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-red-600 px-4 py-2 rounded-lg text-slate-900">Logout</a>
        </div>
        
        <?php if($msg): ?><div class="bg-green-500/10 border border-green-500 text-green-400 text-xs p-3 rounded-lg mb-6"><?= $msg ?></div><?php endif; ?>

        <!-- Pending Approvals Queue -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl mb-6">
            <h2 class="text-sm font-bold text-slate-900 uppercase mb-4">Pending Patient Requests</h2>
            <table class="w-full text-xs text-left">
                <tr class="text-slate-500 border-b border-slate-200">
                    <th class="pb-2">Patient ID</th><th>Name</th><th>Blood Grp</th><th>Urgency</th><th>Resource</th><th>Actions</th>
                </tr>
                <?php while($row = $pending_reqs->fetch_assoc()): ?>
                <tr class="border-b border-slate-100">
                    <td class="py-3"><?= $row['Patient_ID'] ?></td>
                    <td><?= $row['Name'] ?></td>
                    <td><?= $row['Blood_Grp'] ?></td>
                    <td><?= $row['Urgency'] ?></td>
                    <td><?= $row['Reqd_Resource'] ?></td>
                    <td class="flex gap-2 py-3">
                        <form method="POST">
                            <input type="hidden" name="p_id" value="<?= $row['Patient_ID'] ?>">
                            <input type="hidden" name="h_id" value="<?= $row['H_ID'] ?>">
                            <button type="submit" name="action" value="approve" class="app-button bg-green-600/20 text-green-500 border border-green-600 hover:bg-green-600 hover:text-slate-900 px-3 py-1 rounded transition">Approve</button>
                            <button type="submit" name="action" value="reject" class="app-button bg-red-600/20 text-slate-900 border border-red-600 hover:bg-red-600 hover:text-slate-900 px-3 py-1 rounded transition">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if($pending_reqs->num_rows === 0): ?>
                <tr><td colspan="6" class="py-4 text-slate-500 italic">No pending requests.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</body>
</html>