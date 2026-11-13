<?php
// Live chat updates for messages.php (called every few seconds by the open chat).
// Returns new messages from the partner and which of my messages they have seen.
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

require 'db.php';
require_once 'msg_receipts.php';
ensureReadReceipts($con);

$me      = (int) $_SESSION['user_id'];
$pid     = isset($_GET['with'])  ? (int) $_GET['with']  : 0;
$after   = isset($_GET['after']) ? (int) $_GET['after'] : 0;
$visible = isset($_GET['visible']) && $_GET['visible'] === '1';

if ($pid <= 0 || $pid === $me) {
    echo json_encode(['ok' => false]);
    exit();
}

// The chat is open on screen, so anything they sent me is now seen.
if ($visible) {
    markConversationSeen($con, $pid, $me);
}

// New messages from the partner since the last one on screen
$new = [];
$stmt = $con->prepare("SELECT id, body, created_at FROM messages WHERE sender_id = ? AND receiver_id = ? AND id > ? ORDER BY id ASC LIMIT 50");
$stmt->bind_param("iii", $pid, $me, $after);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $new[] = [
        'id'   => (int) $row['id'],
        'body' => $row['body'],
        'time' => date('d M, h:i A', strtotime($row['created_at'])),
    ];
}
$stmt->close();

// Which of my messages to them have been seen (latest 200)
$seen = [];
$lastSeenLabel = null;
$stmt = $con->prepare("SELECT id, read_at FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 1 ORDER BY id DESC LIMIT 200");
$stmt->bind_param("ii", $me, $pid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if ($lastSeenLabel === null) $lastSeenLabel = seenLabel($row['read_at']);
    $seen[] = (int) $row['id'];
}
$stmt->close();
$con->close();

echo json_encode([
    'ok'        => true,
    'new'       => $new,
    'seen'      => $seen,
    'seenLabel' => $lastSeenLabel,
]);
