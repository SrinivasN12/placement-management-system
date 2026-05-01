<?php
// config/db.php — Database connection
$host = "localhost";
$user = "root";
$pass = "";        // leave empty for XAMPP default
$db   = "pms_db";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("<h3 style='color:red;font-family:sans-serif;padding:20px'>
        ❌ Database connection failed: " . mysqli_connect_error() . "<br><br>
        Make sure:<br>
        1. XAMPP MySQL is running<br>
        2. You have run <a href='../setup.php'>setup.php</a> first
    </h3>");
}
mysqli_set_charset($conn, "utf8");
?>
