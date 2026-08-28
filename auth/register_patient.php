<?php
session_start();
require_once '../config/db.php';
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $bg = $_POST['blood_grp'];
    $urg = $_POST['urgency'];
    $pass = $_POST['password'];
    $hid = $_POST['h_id'];
    $resc = $_POST['resc'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO Patient (Name, Blood_Grp, Urgency, Password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $name, $bg, $urg, $pass);
        $stmt->execute();
        $pid = $stmt->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO Waitlisted_As (Patient_ID, H_ID, Reqd_Resource, Date_Added, Wait_Status) VALUES (?, ?, ?, CURDATE(), 'Pending')");
        $stmt2->bind_param("iis", $pid, $hid, $resc);
        $stmt2->execute();

        $conn->query("INSERT INTO Notifications (Target_Role, Message) VALUES ('Staff', 'New patient registration (ID: $pid) requires medical approval.')");

        $conn->commit();
        $msg = "Registered successfully! Your Patient ID is <strong>$pid</strong>. Awaiting staff approval.";
    } catch(Exception $e) {
        $conn->rollback();
        $msg = "Error during registration.";
    }
}
$hospitals = $conn->query("SELECT H_ID, Facility_Name FROM Hospital");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Patient Registration - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 flex items-center justify-center min-h-screen py-10">
    <div class="bg-white border border-slate-200 p-8 rounded-2xl w-full max-w-md shadow-sm hover:border-slate-900 transition duration-300">
        <h2 class="text-xl font-bold mb-1 text-slate-900">Patient Registration</h2>
        <p class="text-xs text-slate-600 mb-6">Join the waitlist. Subject to medical approval.</p>
        
        <?php if($msg): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-xs p-3 rounded-lg mb-4"><?= $msg ?></div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-4">
            <input type="text" name="name" placeholder="Full Name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
            
            <input type="text" name="blood_grp" placeholder="Blood Group (e.g., O+)" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
            
            <input type="number" name="urgency" min="1" max="10" placeholder="Urgency Level (1-10)" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
            
            <select name="h_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none text-slate-600">
                <option value="">Select Target Hospital...</option>
                <?php while($h = $hospitals->fetch_assoc()): ?>
                    <option value="<?= $h['H_ID'] ?>"><?= $h['Facility_Name'] ?></option>
                <?php endwhile; ?>
            </select>
            
            <input type="text" name="resc" placeholder="Required Resource (e.g., Plasma)" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
            
            <input type="password" name="password" placeholder="Create Password" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
            
            <button type="submit" class="app-button w-full bg-black hover:bg-slate-800 text-white font-medium py-3 rounded-lg text-sm transition mt-2">Submit Request</button>
        </form>
        
        <div class="mt-6 text-center border-t border-slate-200 pt-4">
            <a href="login_patient.php" class="text-xs text-slate-600 hover:text-slate-900">&larr; Back to Login</a>
        </div>
    </div>
</body>
</html>