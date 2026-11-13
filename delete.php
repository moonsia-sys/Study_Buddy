<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in first."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

require 'db.php';

$sno = (int) $_SESSION['user_id'];

$stmt = $con->prepare("DELETE FROM users WHERE Sno = ?");
$stmt->bind_param("i", $sno);
$stmt->execute();
$stmt->close();

// also remove my contact requests
$stmt = $con->prepare("DELETE FROM buddy_requests WHERE sender_id = ? OR receiver_id = ?");
$stmt->bind_param("ii", $sno, $sno);
$stmt->execute();
$stmt->close();

// also remove my private messages so nothing is left behind
$stmt = $con->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
$stmt->bind_param("ii", $sno, $sno);
$stmt->execute();
$stmt->close();

$con->close();

session_unset();
session_destroy();

header("Location: login.php?status=deleted");
exit();
?>
