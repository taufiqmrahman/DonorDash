<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DonorDash - Medical Logistics Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 font-sans min-h-screen flex flex-col justify-between selection:bg-red-500 selection:text-slate-900">
    <!-- Navbar -->
    <header class="border-b border-slate-200 backdrop-blur-md bg-white/70 sticky top-0 z-50 px-8 py-4 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <div class="h-3 w-3 rounded-full bg-red-600 animate-pulse"></div>
            <span class="font-extrabold text-xl tracking-wider text-slate-900">DONORDASH</span>
        </div>
        <div class="text-xs text-slate-600 border border-slate-200 px-3 py-1.5 rounded-full bg-white">
            Triple T
        </div>
    </header>

    <!-- Main Content Hero -->
    <main class="max-w-6xl mx-auto px-6 py-20 text-center">
        <p class="text-xs font-bold uppercase tracking-widest text-teal-700 mb-5">A clearer way to move care forward</p>
        <h1 class="text-5xl md:text-7xl font-black tracking-tight mb-6 text-slate-900">
            Every donation.<br><span class="text-red-600">Right where it matters.</span>
        </h1>
        

        <!-- Role Select Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-left">
            <a href="auth/login_admin.php" class="portal-card bg-white border border-slate-200 hover:border-slate-900 hover:shadow-md p-6 rounded-2xl transition duration-300 group">
                <div class="text-slate-900 font-bold text-lg mb-2 group-hover:translate-x-1 transition">Admin &rarr;</div>
                <p class="text-slate-600 text-xs">Logistics oversight, stockout predictors, and audit modules.</p>
            </a>
            <a href="auth/login_staff.php" class="portal-card bg-white border border-slate-200 hover:border-slate-900 hover:shadow-md p-6 rounded-2xl transition duration-300 group">
                <div class="text-slate-900 font-bold text-lg mb-2 group-hover:translate-x-1 transition">Staff Portal &rarr;</div>
                <p class="text-slate-600 text-xs">Medical Officers, Doctor matching engines, and Data Entry clerks.</p>
            </a>
            <a href="auth/login_donor.php" class="portal-card bg-white border border-slate-200 hover:border-slate-900 hover:shadow-md p-6 rounded-2xl transition duration-300 group">
                <div class="text-slate-900 font-bold text-lg mb-2 group-hover:translate-x-1 transition">Donor Portal &rarr;</div>
                <p class="text-slate-600 text-xs">View recovery windows, donation tracking, and eligibility.</p>
            </a>
            <a href="auth/login_patient.php" class="portal-card bg-white border border-slate-200 hover:border-slate-900 hover:shadow-md p-6 rounded-2xl transition duration-300 group">
                <div class="text-slate-900 font-bold text-lg mb-2 group-hover:translate-x-1 transition">Patient  &rarr;</div>
                <p class="text-slate-600 text-xs">Live waitlist position tracking and match escalations.</p>
            </a>
        </div>
    </main>

    <footer class="text-center py-6 text-xs text-slate-500 border-t border-slate-200 bg-white">
        DonorDash Core Infrastructure • Database Systems Project
    </footer>
</body>
</html>