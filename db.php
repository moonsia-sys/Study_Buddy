<?php
// Shared database connection for XAMPP (phpMyAdmin / MariaDB)
$server   = "localhost";
$username = "root";
$password = "";
$database = "study_buddy";

$con = mysqli_connect($server, $username, $password, $database);

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
