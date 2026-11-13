<?php
// Read receipts helper (Seen / Sent) for private messages.
// Adds the `read_at` column to the messages table the first time it is needed,
// so nothing has to be imported by hand. (add_read_receipts.sql does the same.)

function ensureReadReceipts($con) {
    static $done = false;
    if ($done) return;
    $res = $con->query("SHOW COLUMNS FROM messages LIKE 'read_at'");
    if ($res && $res->num_rows === 0) {
        $con->query("ALTER TABLE messages ADD COLUMN read_at DATETIME NULL DEFAULT NULL AFTER is_read");
    }
    $done = true;
}

// Mark every unread message from $partnerId to $me as seen (right now).
function markConversationSeen($con, $partnerId, $me) {
    $stmt = $con->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $partnerId, $me);
    $stmt->execute();
    $stmt->close();
}

// "Seen 08:31 PM" today, otherwise "Seen 10 Nov, 08:31 PM"
function seenLabel($readAt) {
    if ($readAt === null || $readAt === '') return 'Seen';
    $t = strtotime($readAt);
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'Seen ' . date('h:i A', $t);
    return 'Seen ' . date('d M, h:i A', $t);
}
?>
