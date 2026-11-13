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
$course    = trim($_POST['course'] ?? '');
$studytime = trim($_POST['studytime'] ?? '');
$location  = trim($_POST['location'] ?? '');
$method    = trim($_POST['method'] ?? '');
$goals     = trim($_POST['goals'] ?? '');
$pass      = $_POST['password'];
$cpass     = $_POST['cpassword'];

if ($name === '' || $email === '' || $phone === '') {
    header("Location: index.php?error=" . urlencode("Please fill in every field."));
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?error=" . urlencode("Please enter a valid email address."));
    exit();
}
if ($course === '' || $studytime === '' || $location === '' || $method === '') {
    header("Location: index.php?error=" . urlencode("Please choose your course, study time, location and study method."));
    exit();
}
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
    $con->close();
    header("Location: index.php?error=" . urlencode("This email is already registered."));
    exit();
}
$check->close();

$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $con->prepare(
    "INSERT INTO users (Name, Age, Gender, Email, Phone, Other, Course, StudyTime, Location, Goals, Method, Password, Date)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp())"
);
$stmt->bind_param("sissssssssss", $name, $age, $gender, $email, $phone, $desc, $course, $studytime, $location, $goals, $method, $hash);

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
