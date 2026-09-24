<?php
session_start();
require 'db.php';

if (!isset($_POST['email'])) {
    header("Location: login.php");
    exit();
}

$email = trim($_POST['email']);
$pass  = $_POST['password'];

$stmt = $con->prepare("SELECT Sno, Name, Password FROM users WHERE Email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $con->close();
    header("Location: login.php?error=" . urlencode("No account found with that email."));
    exit();
}

$stmt->bind_result($sno, $name, $hash);
$stmt->fetch();
$stmt->close();
$con->close();

// password_verify works for new accounts; old rows saved before the password
// column existed will simply fail and must register again.
if (!$hash || !password_verify($pass, $hash)) {
    header("Location: login.php?error=" . urlencode("Wrong password. Please try again."));
    exit();
}

$_SESSION['user_id']   = $sno;
$_SESSION['user_name'] = $name;

header("Location: dashboard.php");
exit();
?>
