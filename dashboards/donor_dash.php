<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['donor_id'])) { 
    header("Location: ../auth/login_donor.php"); 
    exit(); 
}
$did = $_SESSION['donor_id'];
$msg = "";

//Handle Dismiss Notification (Mark as Read)
if (isset($_GET['dismiss'])) {
    $notif_id = intval($_GET['dismiss']);
    if (!isset($_SESSION['dismissed_donors'])) $_SESSION['dismissed_donors'] = [];
    $_SESSION['dismissed_donors'][] = $notif_id;
    header("Location: donor_dash.php");
    exit();
}

//Handle New Donation Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['donate_resource'])) {
    $resc_type = $_POST['resc_type'];
    $stmt_ins = $conn->prepare("INSERT INTO Inventory_Log (Donor_ID, H_ID, Resc_Type, Status, Receipt_Date) VALUES (?, 1, ?, 'Available', CURDATE())");
    $stmt_ins->bind_param("is", $did, $resc_type);
    if($stmt_ins->execute()) {
        $msg = "New donation of $resc_type logged successfully!";
    } else {
        $msg = "Error submitting donation.";
    }
}

//Donor Profile
$stmt = $conn->prepare("SELECT * FROM Donor WHERE Donor_ID = ?");
$stmt->bind_param("i", $did);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();

// Check Last Donation & Eligibility Countdown (56 days mandatory recovery window)
$last_don = $conn->query("SELECT MAX(Receipt_Date) as Last_Date FROM Inventory_Log WHERE Donor_ID = $did")->fetch_assoc();
$eligible = true;
$days_left = 0;
if($last_don && $last_don['Last_Date']) {
    $last_date = new DateTime($last_don['Last_Date']);
    $today = new DateTime();
    $diff = $today->diff($last_date)->days;
    if($diff < 56) {
        $eligible = false;
        $days_left = 56 - $diff;
    }
}

//Notifications
$notif_stmt = $conn->prepare("SELECT Notification_ID, Message, Created_At FROM Notifications WHERE Target_Role = 'Donor' AND Target_ID = ? ORDER BY Created_At DESC");
$notif_stmt->bind_param("i", $did);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
$dismissed = $_SESSION['dismissed_donors'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Hub - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 min-h-screen p-8">
    <div class="max-w-3xl mx-auto space-y-6">
        
        <div class="flex justify-between items-center border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Donor Personal Portal</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-black hover:bg-slate-800 px-4 py-2 rounded-lg text-white transition">Logout</a>
        </div>
        
        <?php if($msg): ?><div class="bg-slate-100 border border-slate-300 text-slate-900 text-xs p-3 rounded-lg"><?= $msg ?></div><?php endif; ?>

        <!-- Profile Details -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm space-y-3">
            <h2 class="text-sm font-bold text-slate-900 uppercase">Profile Details</h2>
            <p class="text-sm text-slate-900"><strong>Donor ID:</strong> #<?= $did ?></p>
            <p class="text-sm text-slate-900"><strong>Name:</strong> <?= htmlspecialchars($donor['Name']) ?></p>
            <p class="text-sm text-slate-900"><strong>Blood Group:</strong> <?= htmlspecialchars($donor['Blood_Grp']) ?></p>
            <p class="text-sm text-slate-900"><strong>Recovery Status:</strong> 
                <span class="px-2 py-1 rounded border border-slate-300 bg-slate-50 font-bold text-xs"><?= $donor['Recovery_Status'] ?></span>
            </p>
            <p class="text-sm text-slate-900"><strong>Donation Eligibility:</strong> 
                <?php if($eligible): ?>
                    <span class="text-slate-900 font-bold">Eligible to Donate</span>
                <?php else: ?>
                    <span class="text-slate-600 font-bold">Not Eligible (Wait <?= $days_left ?> more days)</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Add New Donation Form -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-slate-900 uppercase">Schedule New Donation</h2>
            <?php if($eligible): ?>
                <form method="POST" class="flex gap-4">
                    <select name="resc_type" required class="flex-1 bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm text-slate-900 focus:outline-none focus:border-slate-900">
                        <option value="">Select Resource to Donate...</option>
                        <option value="Whole Blood">Whole Blood</option>
                        <option value="Plasma">Plasma</option>
                        <option value="Platelets">Platelets</option>
                        <option value="Red Cells">Red Cells</option>
                    </select>
                    <button type="submit" name="donate_resource" class="bg-black hover:bg-slate-800 text-white font-medium px-6 py-3 rounded-lg text-sm transition">Submit Donation</button>
                </form>
            <?php else: ?>
                <p class="text-xs text-slate-500 italic">You must complete your mandatory recovery window before logging a new donation.</p>
            <?php endif; ?>
        </div>

        <!-- System Alerts -->
        <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-sm font-bold text-slate-900 uppercase">System Alerts</h2>
                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-medium">Live Notification Center</span>
            </div>
            <div class="space-y-3">
                <?php 
                $active_alerts = 0;
                while($alert = $notifications->fetch_assoc()): 
                    $nid = $alert['Notification_ID'] ?? 0;
                    if(in_array($nid, $dismissed)) continue;
                    $active_alerts++;
                ?>
                    <div class="p-3 bg-slate-50 border-l-2 border-slate-900 rounded text-xs text-slate-900 flex justify-between items-start transition duration-300 hover:bg-slate-100">
                        <div>
                            <span class="block text-slate-500 mb-1"><?= date('M j, Y, g:i a', strtotime($alert['Created_At'])) ?></span>
                            <?= htmlspecialchars($alert['Message']) ?>
                        </div>
                        <a href="donor_dash.php?dismiss=<?= $nid ?>" class="text-[10px] bg-black text-white hover:bg-slate-800 px-2 py-1 rounded ml-4 whitespace-nowrap transition">Mark as Read</a>
                    </div>
                <?php endwhile; ?>
                <?php if ($active_alerts === 0): ?>
                    <p class="text-xs text-slate-500 italic">No unread notifications.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</body>
</html>