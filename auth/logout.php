<?php
session_start();

// Clear all session variables
session_unset();

// Destroy the session completely
session_destroy();

// Redirect back to the main landing page
header("Location: ../index.php");
exit();
?>  