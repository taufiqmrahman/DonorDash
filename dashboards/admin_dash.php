<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['admin_id'])) { header("Location: ../auth/login_admin.php"); exit(); }

// Feature 1: Stockout Predictor Query
$q1 = $conn->query("
    WITH DailyUsage AS (
        SELECT H_ID, Resc_Type, COUNT(Unit_ID) / 30.0 AS Daily_Burn_Rate 
        FROM Inventory_Log WHERE Status = 'Used' AND Usage_Date >= CURRENT_DATE - INTERVAL 30 DAY 
        GROUP BY H_ID, Resc_Type
    ), CurrentStock AS (
        SELECT H_ID, Resc_Type, COUNT(Unit_ID) AS Available_Units 
        FROM Inventory_Log WHERE Status = 'Available' 
        GROUP BY H_ID, Resc_Type
    )
    SELECT c.H_ID, c.Resc_Type, c.Available_Units, ROUND(c.Available_Units / NULLIF(u.Daily_Burn_Rate, 0), 1) AS Days_Until_Depletion
    FROM CurrentStock c LEFT JOIN DailyUsage u ON c.H_ID = u.H_ID AND c.Resc_Type = u.Resc_Type
    WHERE (c.Available_Units / NULLIF(u.Daily_Burn_Rate, 0)) < 3.0
");

// Feature 2: Automated Resource Rebalancer Query
$q2 = $conn->query("
    WITH AllNeeds AS (
        SELECT H_ID, Reqd_Resource AS Resource FROM Waitlisted_As WHERE Wait_Status = 'Active'
        UNION
        SELECT H_ID, Resc_Type AS Resource FROM Inventory_Log WHERE Status = 'Available'
    ),
    Demand AS (
        SELECT H_ID, Reqd_Resource, COUNT(Patient_ID) AS Demand_Count 
        FROM Waitlisted_As WHERE Wait_Status = 'Active' GROUP BY H_ID, Reqd_Resource
    ),
    Supply AS (
        SELECT H_ID, Resc_Type, COUNT(Unit_ID) AS Supply_Count 
        FROM Inventory_Log WHERE Status = 'Available' GROUP BY H_ID, Resc_Type
    ),
    Ratios AS (
        SELECT 
            a.H_ID, 
            a.Resource, 
            CASE 
                WHEN COALESCE(d.Demand_Count, 0) = 0 THEN 999.0 
                ELSE COALESCE(s.Supply_Count, 0) * 1.0 / d.Demand_Count 
            END AS Ratio
        FROM AllNeeds a
        LEFT JOIN Demand d ON a.H_ID = d.H_ID AND a.Resource = d.Reqd_Resource
        LEFT JOIN Supply s ON a.H_ID = s.H_ID AND a.Resource = s.Resc_Type
    )
    SELECT 
        Deficit.H_ID AS Receiver_Hospital, 
        Surplus.H_ID AS Sender_Hospital, 
        Deficit.Resource AS Reqd_Resource
    FROM Ratios Deficit
    JOIN Ratios Surplus ON Deficit.Resource = Surplus.Resource AND Deficit.H_ID != Surplus.H_ID
    WHERE Deficit.Ratio < 0.5 AND Surplus.Ratio > 2.0
");

// Feature 3: Donor Loyalty Analyzer Query
$q3 = $conn->query("
    WITH FirstDonation AS (
        SELECT Donor_ID, MIN(EXTRACT(YEAR FROM Receipt_Date)) AS Cohort_Year FROM Inventory_Log GROUP BY Donor_ID
    ), SubsequentDonation AS (
        SELECT DISTINCT d.Donor_ID, f.Cohort_Year FROM Inventory_Log d JOIN FirstDonation f ON d.Donor_ID = f.Donor_ID WHERE EXTRACT(YEAR FROM d.Receipt_Date) = f.Cohort_Year + 1
    )
    SELECT f.Cohort_Year, COUNT(DISTINCT f.Donor_ID) AS Original_Cohort_Size, COUNT(DISTINCT s.Donor_ID) AS Retained_Next_Year, (COUNT(DISTINCT s.Donor_ID) * 100.0 / COUNT(DISTINCT f.Donor_ID)) AS Retention_Rate
    FROM FirstDonation f LEFT JOIN SubsequentDonation s ON f.Donor_ID = s.Donor_ID GROUP BY f.Cohort_Year
");

// Feature 4: Hospital Wastage Tracker Query
$q4 = $conn->query("
    SELECT H_ID, COUNT(Unit_ID) AS Total_Units_Received, SUM(CASE WHEN Status = 'Expired' THEN 1 ELSE 0 END) AS Expired_Units, SUM(CASE WHEN Status = 'Used' THEN 1 ELSE 0 END) AS Successfully_Used, ROUND(SUM(CASE WHEN Status = 'Expired' THEN 1 ELSE 0 END) * 100.0 / COUNT(Unit_ID), 2) AS Wastage_Percentage
    FROM Inventory_Log WHERE Receipt_Date >= CURRENT_DATE - INTERVAL 1 YEAR GROUP BY H_ID ORDER BY Wastage_Percentage DESC
");

// Feature 5: Suspicious Activity Monitor Query
$q5 = $conn->query("
    WITH ClerkStats AS (
        SELECT hs.Staff_ID, COUNT(p.Patient_ID) AS Urgent_Registrations FROM Hospital_Staff hs JOIN Patient p ON hs.Staff_ID = p.Staff_ID WHERE p.Urgency >= 8 AND p.Registration_Date >= CURRENT_DATE - INTERVAL 30 DAY GROUP BY hs.Staff_ID
    ), GlobalAverages AS (
        SELECT AVG(Urgent_Registrations) AS Avg_Reg, STDDEV(Urgent_Registrations) AS Std_Dev FROM ClerkStats
    )
    SELECT c.Staff_ID, c.Urgent_Registrations FROM ClerkStats c CROSS JOIN GlobalAverages g WHERE c.Urgent_Registrations > (COALESCE(g.Avg_Reg, 0) + (2 * COALESCE(g.Std_Dev, 0)))
");

// Badge Counts for Presentation Showcase Polish
$c1 = $q1->num_rows;
$c2 = $q2->num_rows;
$c5 = ($q5) ? $q5->num_rows : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Admin Dashboard - DonorDash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/app.css">
</head>
<body class="app-page bg-slate-50 text-slate-900 min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-8 border-b border-slate-200 pb-4">
            <h1 class="text-2xl font-black text-slate-900">Hospital Admin Logistics Hub</h1>
            <a href="../auth/logout.php" class="app-button text-xs bg-black hover:bg-slate-800 px-4 py-2 rounded-lg text-white transition">Logout</a>
        </div>

        <!-- ROW 1: Short Tables (Features 1 & 3) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 items-start">
            <!-- Feature 1 -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm relative">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Feature 1: Stockout Predictor</h2>
                    <?php if($c1 > 0): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-900 border border-slate-300 animate-pulse">
                            🔥 <?= $c1 ?> Critical Alert<?= $c1 > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="overflow-auto w-full">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Hospital ID</th><th>Resource</th><th>Available</th><th>Days Left</th>
                        </tr>
                        <?php while($r = $q1->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-3"><?= $r['H_ID'] ?></td>
                            <td><?= $r['Resc_Type'] ?></td>
                            <td><?= $r['Available_Units'] ?></td>
                            <td class="font-bold text-slate-900"><?= $r['Days_Until_Depletion'] ?> Days</td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm relative">
                <h2 class="text-sm font-bold text-slate-900 uppercase mb-4">Feature 3: Donor Loyalty Analyzer</h2>
                <div class="overflow-auto w-full">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Cohort Year</th><th>Original Size</th><th>Retained</th><th>Retention Rate</th>
                        </tr>
                        <?php while($r = $q3->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-3"><?= $r['Cohort_Year'] ?></td>
                            <td><?= $r['Original_Cohort_Size'] ?></td>
                            <td><?= $r['Retained_Next_Year'] ?></td>
                            <td><?= round($r['Retention_Rate'], 1) ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ROW 2: Heavy Scrollable Tables (Features 2 & 4) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 items-stretch">
            <!-- Feature 2 -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm relative flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Feature 2: Automated Resource Rebalancer</h2>
                    <?php if($c2 > 0): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-900 border border-slate-300 animate-pulse">
                            ⚡ <?= $c2 ?> Rebalance Action Required
                        </span>
                    <?php endif; ?>
                </div>
                <div class="overflow-auto max-h-[350px] w-full flex-1">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Receiver H_ID</th><th>Sender H_ID</th><th>Resource</th>
                        </tr>
                        <?php while($r = $q2->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-3"><?= $r['Receiver_Hospital'] ?></td>
                            <td><?= $r['Sender_Hospital'] ?></td>
                            <td><?= $r['Reqd_Resource'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm flex flex-col">
                <h2 class="text-sm font-bold text-slate-900 uppercase mb-4">Feature 4: Hospital Wastage Tracker</h2>
                <div class="overflow-auto max-h-[350px] w-full flex-1">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Hospital ID</th><th>Total Received</th><th>Expired</th><th>Wastage %</th>
                        </tr>
                        <?php while($r = $q4->fetch_assoc()): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-3"><?= $r['H_ID'] ?></td>
                            <td><?= $r['Total_Units_Received'] ?></td>
                            <td><?= $r['Expired_Units'] ?></td>
                            <td class="font-bold text-slate-900"><?= $r['Wastage_Percentage'] ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- ROW 3: Full Width Table (Feature 5) -->
        <div class="w-full mb-6">
            <!-- Feature 5 -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm relative flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-sm font-bold text-slate-900 uppercase">Feature 5: Suspicious Activity Monitor</h2>
                    <?php if($c5 > 0): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-900 border border-slate-300 animate-pulse">
                            🚨 <?= $c5 ?> Security Flag Triggered
                        </span>
                    <?php endif; ?>
                </div>
                <div class="overflow-auto max-h-[350px] w-full flex-1">
                    <table class="w-full text-xs text-left text-slate-900">
                        <tr class="text-slate-600 border-b border-slate-200 sticky top-0 bg-white">
                            <th class="py-2">Staff ID</th><th>Urgent Registrations (Past 30 Days)</th>
                        </tr>
                        <?php if($q5 && $q5->num_rows > 0): ?>
                            <?php while($r = $q5->fetch_assoc()): ?>
                            <tr class="border-b border-slate-100">
                                <td class="py-3"><?= $r['Staff_ID'] ?></td>
                                <td class="font-bold text-slate-900"><?= $r['Urgent_Registrations'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="py-4 text-slate-500 italic">No anomalous staff activity detected.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

    </div>
</body>
</html>