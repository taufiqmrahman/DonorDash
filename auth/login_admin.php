<?php
session_start();
require_once '../config/db.php';
$err = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $stmt = $conn->prepare("SELECT Staff_ID, Password FROM Hospital_Staff WHERE Username = ? AND Role = 'Admin'");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if ($p === $row['Password'] || password_verify($p, $row['Password'])) {
            $_SESSION['admin_id'] = $row['Staff_ID'];
            header("Location: ../dashboards/admin_dash.php");
            exit();
        }
    }
    $err = "Invalid Admin credentials.";
}
?>
<!DOCTYPE html>
<html lang="en" >
<head>
    <meta charset="UTF-8">
    <title>Admin Login - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-100 flex items-center justify-center min-h-screen">
    <div class="bg-white border border-slate-200 p-8 rounded-2xl w-full max-w-md shadow-2xl">
        <h2 class="text-xl font-bold mb-1 text-slate-900">Admin Logistics Login</h2>
        <p class="text-xs text-slate-600 mb-6">Access oversight hub and predictive analytics.</p>
        <?php if($err): ?><div class="bg-red-500/10 border border-red-500 text-red-400 text-xs p-3 rounded-lg mb-4"><?= $err ?></div><?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label class="text-xs text-slate-600 block mb-1">Username</label>
                <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-slate-900">
            </div>
            <div>
                <label class="text-xs text-slate-600 block mb-1">Password</label>
                <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm focus:outline-none focus:border-slate-900">
            </div>
            <button type="submit" class="app-button w-full bg-red-600 hover:bg-red-700 text-slate-900 font-medium py-3 rounded-lg text-sm transition">Authenticate</button>
        </form>
        <div class="mt-4 text-center"><a href="../index.php" class="text-xs text-slate-500 hover:text-slate-300">&larr; Back to Home</a></div>
    </div>
</body>
</html>