<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in first."));
    exit();
}

require 'db.php';
require_once 'msg_receipts.php';
ensureReadReceipts($con);

$me       = (int) $_SESSION['user_id'];
$fullName = $_SESSION['user_name'];
$parts    = preg_split('/\s+/', trim($fullName));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

$with = isset($_GET['with']) ? (int) $_GET['with'] : 0;

// ---- Who am I chatting with? ----
$partner = null;
if ($with > 0) {
    $stmt = $con->prepare("SELECT Sno, Name, Course, StudyTime FROM users WHERE Sno = ? AND Sno <> ?");
    $stmt->bind_param("ii", $with, $me);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 1) {
        $partner = $res->fetch_assoc();
    }
    $stmt->close();
    if ($partner === null) {
        $con->close();
        header("Location: messages.php?error=" . urlencode("That student could not be found."));
        exit();
    }
}

// ---- Send a message (then redirect so refresh doesn't send twice) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $partner !== null) {
    $pid  = (int) $partner['Sno'];
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        if (mb_strlen($body) > 1000) $body = mb_substr($body, 0, 1000);
        $stmt = $con->prepare("INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $me, $pid, $body);
        $stmt->execute();
        $stmt->close();
    }
    $con->close();
    header("Location: messages.php?with=" . $pid);
    exit();
}

// ---- Opening a chat marks their messages as read ----
if ($partner !== null) {
    $pid  = (int) $partner['Sno'];
    markConversationSeen($con, $pid, $me);
}

// ---- Messages of this conversation ----
$chat = [];
if ($partner !== null) {
    $pid  = (int) $partner['Sno'];
    $stmt = $con->prepare(
        "SELECT id, sender_id, body, is_read, read_at, created_at FROM messages
         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
         ORDER BY id ASC"
    );
    $stmt->bind_param("iiii", $me, $pid, $pid, $me);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $chat[] = $row;
    $stmt->close();
}

// ---- Conversation list: every other student, with last message + unread count ----
$list = [];
$stmt = $con->prepare(
    "SELECT u.Sno, u.Name, u.Course,
        (SELECT m.body FROM messages m WHERE (m.sender_id = u.Sno AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.Sno) ORDER BY m.id DESC LIMIT 1) AS last_body,
        (SELECT m.sender_id FROM messages m WHERE (m.sender_id = u.Sno AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.Sno) ORDER BY m.id DESC LIMIT 1) AS last_from,
        (SELECT m.is_read FROM messages m WHERE (m.sender_id = u.Sno AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.Sno) ORDER BY m.id DESC LIMIT 1) AS last_read,
        (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.Sno AND m.receiver_id = ? AND m.is_read = 0) AS unread
     FROM users u
     WHERE u.Sno <> ?
     ORDER BY u.Sno DESC"
);
$stmt->bind_param("iiiiiiii", $me, $me, $me, $me, $me, $me, $me, $me);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $list[] = $row;
$stmt->close();

// ---- Total unread for the sidebar badge ----
$unreadTotal = 0;
$stmt = $con->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $me);
$stmt->execute();
$stmt->bind_result($unreadTotal);
$stmt->fetch();
$stmt->close();

$con->close();

$palette = ['#6c5ce7', '#ff6b5a', '#14b8a6', '#2563eb', '#f59e0b', '#e23b3b'];
function avColor($id) { global $palette; return $palette[$id % count($palette)]; }
function avInit($name) {
    $p = preg_split('/\s+/', trim($name));
    return strtoupper(substr($p[0], 0, 1) . (isset($p[1]) ? substr($p[1], 0, 1) : ''));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — Study Buddy</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="logo">SB</div>
            <span>Study Buddy</span>
        </div>

        <nav class="nav">
            <a href="dashboard.php"><span class="text">Dashboard</span></a>
            <a href="find_buddy.php"><span class="text">Find Buddy</span></a>
            <a href="requests.php"><span class="text">Requests</span></a>
            <a href="buddy_list.php"><span class="text">Buddy List</span></a>
            <a href="messages.php" class="active">
                <span class="text">Messages</span>
                <?php if ($unreadTotal > 0): ?><span class="badge"><?php echo $unreadTotal; ?></span><?php endif; ?>
            </a>
            <a href="profile.php"><span class="text">Profile</span></a>
            <a href="logout.php" class="nav-logout"><span class="text">Logout</span></a>
        </nav>
    </aside>

    <div class="main">

        <header class="topbar">
            <h2>Study Buddy</h2>
            <span class="chip">BAUSTK · CSE</span>
            <div class="spacer"></div>
            <?php include 'notif_bell.php'; ?>
            <a href="profile.php" class="avatar" title="My profile"><?php echo htmlspecialchars($initials); ?></a>
        </header>

        <main class="content">

            <section class="welcome">
                <h1>Messages</h1>
                <p>Chat privately with your study buddies. Pick a student on the left to start.</p>
            </section>

            <?php if (isset($_GET['error'])): ?>
                <div class="msg err"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="chat-layout">

                <!-- Left: people list -->
                <div class="conv-list">
                    <?php if (count($list) === 0): ?>
                        <div class="conv-empty">No other students have registered yet.</div>
                    <?php endif; ?>
                    <?php foreach ($list as $p):
                        $pid = (int) $p['Sno'];
                        $last = trim((string) $p['last_body']);
                        $lastMine = ($last !== '' && (int) $p['last_from'] === $me);
                        if ($lastMine) {
                            $last = 'You: ' . $last;
                        }
                    ?>
                        <a class="conv-item<?php echo ($partner !== null && $pid === (int) $partner['Sno']) ? ' selected' : ''; ?>"
                           href="messages.php?with=<?php echo $pid; ?>">
                            <span class="av" style="background: <?php echo avColor($pid); ?>"><?php echo htmlspecialchars(avInit($p['Name'])); ?></span>
                            <span class="conv-info">
                                <span class="conv-name"><?php echo htmlspecialchars($p['Name']); ?></span>
                                <span class="conv-last"><?php if ($lastMine): ?><span class="tick<?php echo (int) $p['last_read'] === 1 ? ' seen' : ''; ?>" title="<?php echo (int) $p['last_read'] === 1 ? 'Seen' : 'Sent'; ?>"><?php echo (int) $p['last_read'] === 1 ? '✓✓' : '✓'; ?></span> <?php endif; ?><?php echo $last !== '' ? htmlspecialchars(mb_strimwidth($last, 0, 38, '…')) : htmlspecialchars($p['Course'] !== '' ? $p['Course'] : 'Say hi 👋'); ?></span>
                            </span>
                            <?php if ((int) $p['unread'] > 0): ?>
                                <span class="unread-badge"><?php echo (int) $p['unread']; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Right: the conversation -->
                <div class="chat-panel">
                    <?php if ($partner === null): ?>
                        <div class="chat-empty">
                            <div class="big">💬</div>
                            <h3>Select a student to start chatting</h3>
                            <p>You can also open a chat from any buddy card on the dashboard.</p>
                        </div>
                    <?php else: ?>
                        <div class="chat-head">
                            <a class="chat-back" href="messages.php">←</a>
                            <span class="av sm" style="background: <?php echo avColor((int) $partner['Sno']); ?>"><?php echo htmlspecialchars(avInit($partner['Name'])); ?></span>
                            <div>
                                <div class="chat-name"><?php echo htmlspecialchars($partner['Name']); ?></div>
                                <div class="chat-sub">
                                    <?php
                                    $psub = [];
                                    if ($partner['Course'] !== '')    $psub[] = $partner['Course'];
                                    if ($partner['StudyTime'] !== '') $psub[] = $partner['StudyTime'];
                                    echo htmlspecialchars(implode(' · ', $psub));
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="chat-scroll" id="chatScroll">
                            <?php if (count($chat) === 0): ?>
                                <div class="chat-none">No messages yet. Say hello to <?php echo htmlspecialchars(explode(' ', trim($partner['Name']))[0]); ?>!</div>
                            <?php endif; ?>
                            <?php
                            $lastId = 0;
                            foreach ($chat as $i => $m):
                                $mine   = ((int) $m['sender_id'] === $me);
                                $seen   = ((int) $m['is_read'] === 1);
                                $lastId = max($lastId, (int) $m['id']);
                            ?>
                                <div class="bubble-row <?php echo $mine ? 'me' : 'them'; ?>" data-id="<?php echo (int) $m['id']; ?>">
                                    <div class="bubble">
                                        <div class="bubble-text"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                                        <div class="bubble-time">
                                            <?php echo date('d M, h:i A', strtotime($m['created_at'])); ?>
                                            <?php if ($mine): ?>
                                                <span class="tick<?php echo $seen ? ' seen' : ''; ?>" title="<?php echo $seen ? 'Seen' : 'Sent'; ?>"><?php echo $seen ? '✓✓' : '✓'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php
                            // Under my latest message (when it's the newest in the chat): "Sent" or "Seen 08:31 PM"
                            $lastMsg = count($chat) > 0 ? $chat[count($chat) - 1] : null;
                            if ($lastMsg !== null && (int) $lastMsg['sender_id'] === $me):
                                $lastSeen = ((int) $lastMsg['is_read'] === 1);
                            ?>
                                <div class="seen-status<?php echo $lastSeen ? ' is-seen' : ''; ?>" id="seenStatus">
                                    <?php echo htmlspecialchars($lastSeen ? seenLabel($lastMsg['read_at']) : 'Sent'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form class="chat-form" method="post" action="messages.php?with=<?php echo (int) $partner['Sno']; ?>">
                            <textarea name="body" placeholder="Write a message…" maxlength="1000" required></textarea>
                            <button type="submit" class="chat-send">Send</button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>

            <script>
            // keep the newest message in view + live "Seen" updates while the chat is open
            (function () {
                var sc = document.getElementById('chatScroll');
                if (!sc) return;
                sc.scrollTop = sc.scrollHeight;

                var partnerId = <?php echo $partner !== null ? (int) $partner['Sno'] : 0; ?>;
                var lastId    = <?php echo isset($lastId) ? (int) $lastId : 0; ?>;
                if (!partnerId) return;

                function nearBottom() { return sc.scrollHeight - sc.scrollTop - sc.clientHeight < 80; }

                function addIncoming(m) {
                    var none = sc.querySelector('.chat-none');
                    if (none) none.remove();
                    var status = document.getElementById('seenStatus');
                    if (status) status.remove(); // they replied, so the "Seen" line is no longer needed

                    var row = document.createElement('div');
                    row.className = 'bubble-row them';
                    row.setAttribute('data-id', m.id);
                    var b = document.createElement('div'); b.className = 'bubble';
                    var t = document.createElement('div'); t.className = 'bubble-text'; t.textContent = m.body;
                    t.style.whiteSpace = 'pre-wrap';
                    var tm = document.createElement('div'); tm.className = 'bubble-time'; tm.textContent = m.time;
                    b.appendChild(t); b.appendChild(tm); row.appendChild(b);
                    sc.appendChild(row);
                }

                function poll() {
                    var visible = document.visibilityState === 'visible' ? '1' : '0';
                    fetch('chat_status.php?with=' + partnerId + '&after=' + lastId + '&visible=' + visible, { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (!d || !d.ok) return;
                            var stick = nearBottom();

                            d.new.forEach(function (m) {
                                if (m.id > lastId) { addIncoming(m); lastId = m.id; }
                            });

                            // turn ✓ (Sent) into ✓✓ (Seen) on my bubbles
                            var seen = {};
                            d.seen.forEach(function (id) { seen[id] = true; });
                            sc.querySelectorAll('.bubble-row.me').forEach(function (row) {
                                var tick = row.querySelector('.tick');
                                if (tick && seen[row.getAttribute('data-id')] && !tick.classList.contains('seen')) {
                                    tick.classList.add('seen');
                                    tick.textContent = '✓✓';
                                    tick.title = 'Seen';
                                }
                            });
                            var status = document.getElementById('seenStatus');
                            var mineRows = sc.querySelectorAll('.bubble-row.me');
                            var lastMine = mineRows.length ? mineRows[mineRows.length - 1] : null;
                            if (status && lastMine) {
                                var lastMineTick = lastMine.querySelector('.tick.seen');
                                if (lastMineTick && d.seenLabel) {
                                    status.textContent = d.seenLabel;
                                    status.classList.add('is-seen');
                                }
                            }

                            if (stick) sc.scrollTop = sc.scrollHeight;
                        })
                        .catch(function () {});
                }

                setInterval(poll, 3000);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') poll();
                });
            })();
            </script>

        </main>
    </div>
</div>

</body>
</html>
