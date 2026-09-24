<?php
session_start();
require 'db.php';

if (!isset($_POST['name'])) {
    header("Location: index.php");
    exit();
}

$name      = trim($_POST['name']);
$age       = (int) $_POST['age'];
$gender    = trim($_POST['gender']);
$email     = trim($_POST['email']);
$phone     = trim($_POST['phone']);
$desc      = trim($_POST['desc']);
$pass      = $_POST['password'];
$cpass     = $_POST['cpassword'];

if ($pass !== $cpass) {
    header("Location: index.php?error=" . urlencode("Passwords do not match."));
    exit();
}
if (strlen($pass) < 6) {
    header("Location: index.php?error=" . urlencode("Password must be at least 6 characters."));
    exit();
}

// email must be unique
$check = $con->prepare("SELECT Sno FROM users WHERE Email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    header("Location: index.php?error=" . urlencode("This email is already registered."));
    exit();
}
$check->close();

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $con->prepare(
    "INSERT INTO users (Name, Age, Gender, Email, Phone, Other, Password, Date)
     VALUES (?, ?, ?, ?, ?, ?, ?, current_timestamp())"
);
$stmt->bind_param("sisssss", $name, $age, $gender, $email, $phone, $desc, $hash);

if ($stmt->execute()) {
    $stmt->close();
    $con->close();
    header("Location: login.php?status=registered");
    exit();
} else {
    $err = $stmt->error;
    $stmt->close();
    $con->close();
    header("Location: index.php?error=" . urlencode("Error: " . $err));
    exit();
}
?>
