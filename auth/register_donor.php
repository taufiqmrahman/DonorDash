<?php
session_start();
require_once '../config/db.php';
$msg = "";
$err = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $blood_group = trim($_POST['blood_grp'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $blood_group === '' || $password === '') {
        $err = "Please complete all fields.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO Donor (Name, Blood_Grp, Password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $blood_group, $hashed_password);

        if ($stmt->execute()) {
            $msg = "Registration successful! Your Donor ID is <strong>" . $stmt->insert_id . "</strong>. You can now log in.";
        } else {
            $err = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Registration - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 flex items-center justify-center min-h-screen py-10">
    <div class="bg-white border border-slate-200 p-8 rounded-2xl w-full max-w-md shadow-2xl">
        <h2 class="text-xl font-bold mb-1 text-slate-900">Donor Registration</h2>
        <p class="text-xs text-slate-600 mb-6">Create your donor profile to support the medical supply network.</p>

        <?php if($msg): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 text-xs p-3 rounded-lg mb-4"><?= $msg ?></div>
        <?php endif; ?>
        <?php if($err): ?>
            <div class="bg-red-500/10 border border-red-500 text-red-400 text-xs p-3 rounded-lg mb-4"><?= $err ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="text-xs text-slate-600 block mb-1" for="name">Full Name</label>
                <input id="name" type="text" name="name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-red-500">
            </div>
            <div>
                <label class="text-xs text-slate-600 block mb-1" for="blood_grp">Blood Group</label>
                <input id="blood_grp" type="text" name="blood_grp" placeholder="e.g., O+" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-red-500">
            </div>
            <div>
                <label class="text-xs text-slate-600 block mb-1" for="password">Create Password</label>
                <input id="password" type="password" name="password" minlength="6" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-red-500">
            </div>
            <button type="submit" class="app-button w-full bg-red-600 hover:bg-red-700 text-slate-900 font-medium py-3 rounded-lg text-sm transition">Create Donor Account</button>
        </form>

        <div class="mt-4 text-center">
            <a href="login_donor.php" class="text-xs text-slate-600 hover:text-slate-900">&larr; Back to Donor Login</a>
        </div>
    </div>
</body>
</html>