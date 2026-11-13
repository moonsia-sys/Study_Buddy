<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in first."));
    exit();
}

require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

$sno = (int) $_SESSION['user_id'];

$name      = trim($_POST['name'] ?? '');
$age       = (int) ($_POST['age'] ?? 0);
$gender    = trim($_POST['gender'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$course    = trim($_POST['course'] ?? '');
$studytime = trim($_POST['studytime'] ?? '');
$location  = trim($_POST['location'] ?? '');
$goals     = trim($_POST['goals'] ?? '');
$method    = trim($_POST['method'] ?? '');
$desc      = trim($_POST['desc'] ?? '');
$newpass   = $_POST['newpassword'] ?? '';
$cnewpass  = $_POST['cnewpassword'] ?? '';

function back($msg) {
    header("Location: profile.php?error=" . urlencode($msg));
    exit();
}

if ($name === '' || $email === '' || $phone === '') {
    back("Please fill in name, email and phone.");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back("Please enter a valid email address.");
}
if ($age < 15 || $age > 100) {
    back("Please enter a valid age (15–100).");
}
if ($course === '' || $studytime === '' || $location === '' || $method === '') {
    back("Please choose your course, study time, location and study method.");
}

// email must stay unique (excluding my own account)
$check = $con->prepare("SELECT Sno FROM users WHERE Email = ? AND Sno <> ?");
$check->bind_param("si", $email, $sno);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    $con->close();
    back("That email is already used by another account.");
}
$check->close();

$changePass = ($newpass !== '');
if ($changePass) {
    if (strlen($newpass) < 6) {
        $con->close();
        back("New password must be at least 6 characters.");
    }
    if ($newpass !== $cnewpass) {
        $con->close();
        back("New passwords do not match.");
    }
    $hash = password_hash($newpass, PASSWORD_DEFAULT);
    $stmt = $con->prepare(
        "UPDATE users SET Name=?, Age=?, Gender=?, Email=?, Phone=?, Other=?, Course=?, StudyTime=?, Location=?, Goals=?, Method=?, Password=? WHERE Sno=?"
    );
    $stmt->bind_param("sissssssssssi", $name, $age, $gender, $email, $phone, $desc, $course, $studytime, $location, $goals, $method, $hash, $sno);
} else {
    $stmt = $con->prepare(
        "UPDATE users SET Name=?, Age=?, Gender=?, Email=?, Phone=?, Other=?, Course=?, StudyTime=?, Location=?, Goals=?, Method=? WHERE Sno=?"
    );
    $stmt->bind_param("sisssssssssi", $name, $age, $gender, $email, $phone, $desc, $course, $studytime, $location, $goals, $method, $sno);
}

if ($stmt->execute()) {
    $stmt->close();
    $con->close();
    $_SESSION['user_name'] = $name; // keep the topbar and welcome text in sync
    header("Location: profile.php?status=updated");
    exit();
} else {
    $err = $stmt->error;
    $stmt->close();
    $con->close();
    back("Error: " . $err);
}
?>
