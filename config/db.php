<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'donordash_db';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_errno) {
    http_response_code(503);
    exit('DonorDash is temporarily unavailable. Start MySQL in XAMPP, then refresh this page.');
}
?>  