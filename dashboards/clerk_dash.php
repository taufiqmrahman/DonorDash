<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['staff_id'])) { header("Location: ../auth/login_staff.php"); exit(); }

$staff_id = $_SESSION['staff_id'];
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
        $stmt = $conn->prepare("UPDATE Waitlisted_As SET Wait_Status = ? WHERE Patient_ID = ? AND H_ID = ?");
        $stmt->bind_param("sii", $status, $pid, $hid);
        $stmt->execute();
        
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

// Process Donor Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_donor'])) {
    $name = $_POST['name'];
    $bg = $_POST['blood_grp'];
    $phone = $_POST['phone'];
    $pass = $_POST['password'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO Donor (Name, Blood_Grp, Phone, Recovery_Status, Password) VALUES (?, ?, ?, 'Recovered', ?)");
        $stmt->bind_param("ssss", $name, $bg, $phone, $pass);
        $stmt->execute();
        $msg = "New Donor <strong>$name</strong> registered successfully (ID: " . $stmt->insert_id . ").";
    } catch(Exception $e) {
        $msg = "Error registering donor.";
    }
}

// Process Patient Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_patient'])) {
    $name = $_POST['name'];
    $bg = $_POST['blood_grp'];
    $urg = $_POST['urgency'];
    $hid = $_POST['h_id'];
    $resc = $_POST['resc'];
    $pass = $_POST['password'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO Patient (Name, Blood_Grp, Urgency, Password, Staff_ID) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisi", $name, $bg, $urg, $pass, $staff_id);
        $stmt->execute();
        $pid = $stmt->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO Waitlisted_As (Patient_ID, H_ID, Reqd_Resource, Date_Added, Wait_Status) VALUES (?, ?, ?, CURDATE(), 'Pending')");
        $stmt2->bind_param("iis", $pid, $hid, $resc);
        $stmt2->execute();

        $conn->commit();
        $msg = "New Patient <strong>$name</strong> registered successfully (ID: $pid).";
    } catch(Exception $e) {
        $conn->rollback();
        $msg = "Error registering patient.";
    }
}

// Fetch Pending Requests & Hospitals
$pending_reqs = $conn->query("
    SELECT w.Patient_ID, w.H_ID, p.Name, p.Blood_Grp, p.Urgency, w.Reqd_Resource 
    FROM Waitlisted_As w 
    JOIN Patient p ON w.Patient_ID = p.Patient_ID 
    WHERE w.Wait_Status = 'Pending'
");
$hospitals = $conn->query("SELECT H_ID, Facility_Name FROM Hospital");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Data Clerk Hub - DonorDash</title>
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
<body class="app-page bg-slate-50 text-slate-100 min-h-screen p-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Data Entry & Approval Hub</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg text-white transition">Logout</a>
        </div>
        
        <?php if($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 text-xs p-3 rounded-lg mb-6"><?= $msg ?></div><?php endif; ?>

        <!-- Pending Approvals Queue (Collapsible) -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-6">
            <button onclick="toggleSection('pendingQueue')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition text-slate-900">
                <h2 class="text-sm font-bold uppercase">Pending Patient Requests (<?= $pending_reqs->num_rows ?>)</h2>
                <span id="icon-pendingQueue" class="text-slate-500 text-xs">▲</span>
            </button>
            <div id="pendingQueue" class="p-4 overflow-auto max-h-[400px]">
                <table class="w-full text-xs text-left text-slate-900">
                    <tr class="text-slate-500 border-b border-slate-200">
                        <th class="pb-2">Patient ID</th><th>Name</th><th>Blood Grp</th><th>Urgency</th><th>Resource</th><th>Actions</th>
                    </tr>
                    <?php while($row = $pending_reqs->fetch_assoc()): ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-3"><?= $row['Patient_ID'] ?></td>
                        <td class="font-bold"><?= $row['Name'] ?></td>
                        <td><?= $row['Blood_Grp'] ?></td>
                        <td><?= $row['Urgency'] ?></td>
                        <td><?= $row['Reqd_Resource'] ?></td>
                        <td class="flex gap-2 py-3">
                            <form method="POST">
                                <input type="hidden" name="p_id" value="<?= $row['Patient_ID'] ?>">
                                <input type="hidden" name="h_id" value="<?= $row['H_ID'] ?>">
                                <button type="submit" name="action" value="approve" class="app-button bg-green-600/20 text-green-700 border border-green-600 hover:bg-green-600 hover:text-white px-3 py-1 rounded transition">Approve</button>
                                <button type="submit" name="action" value="reject" class="app-button bg-red-600/20 text-red-700 border border-red-600 hover:bg-red-600 hover:text-white px-3 py-1 rounded transition">Reject</button>
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

        <!-- Registration Forms Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            
            <!-- Register Donor -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <button onclick="toggleSection('regDonor')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition text-slate-900">
                    <h2 class="text-sm font-bold uppercase">Register New Donor</h2>
                    <span id="icon-regDonor" class="text-slate-500 text-xs">▼</span>
                </button>
                <div id="regDonor" class="hidden p-6">
                    <form method="POST" class="space-y-4 text-slate-900">
                        <input type="text" name="name" placeholder="Full Name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <input type="text" name="blood_grp" placeholder="Blood Group (e.g., O+)" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <input type="text" name="phone" placeholder="Phone Number" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <input type="password" name="password" placeholder="Assign Initial Password" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <button type="submit" name="register_donor" class="app-button w-full bg-black hover:bg-slate-800 text-white font-medium py-3 rounded-lg text-sm transition">Submit Donor</button>
                    </form>
                </div>
            </div>

            <!-- Register Patient -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <button onclick="toggleSection('regPatient')" class="w-full flex justify-between items-center p-4 bg-slate-100 hover:bg-slate-200 transition text-slate-900">
                    <h2 class="text-sm font-bold uppercase">Register New Patient</h2>
                    <span id="icon-regPatient" class="text-slate-500 text-xs">▼</span>
                </button>
                <div id="regPatient" class="hidden p-6">
                    <form method="POST" class="space-y-4 text-slate-900">
                        <input type="text" name="name" placeholder="Full Name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <input type="text" name="blood_grp" placeholder="Blood Group (e.g., AB-)" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <input type="number" name="urgency" min="1" max="10" placeholder="Urgency (1-10)" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <select name="h_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none text-slate-600">
                            <option value="">Assign Target Hospital...</option>
                            <?php while($h = $hospitals->fetch_assoc()): ?>
                                <option value="<?= $h['H_ID'] ?>"><?= $h['Facility_Name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                        <input type="text" name="resc" placeholder="Required Resource" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <input type="password" name="password" placeholder="Assign Initial Password" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:border-slate-900 focus:outline-none">
                        <button type="submit" name="register_patient" class="app-button w-full bg-black hover:bg-slate-800 text-white font-medium py-3 rounded-lg text-sm transition">Submit Patient</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</body>
</html>