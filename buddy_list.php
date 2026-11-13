<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in first."));
    exit();
}

require 'db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$me       = (int) $_SESSION['user_id'];
$fullName = $_SESSION['user_name'];
$parts    = preg_split('/\s+/', trim($fullName));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

// ---- Remove a buddy (deletes the accepted connection in both directions) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $token = $_POST['csrf_token'] ?? '';
    $buddyId = (int) ($_POST['buddy_id'] ?? 0);
    if (is_string($token) && hash_equals($_SESSION['csrf_token'], $token) && $buddyId > 0) {
        $del = $con->prepare(
            "DELETE FROM buddy_requests WHERE status = 'accepted'
             AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))"
        );
        $del->bind_param("iiii", $me, $buddyId, $buddyId, $me);
        $del->execute();
        $removed = $del->affected_rows > 0;
        $del->close();
        $con->close();
        header('Location: buddy_list.php?notice=' . ($removed ? 'removed' : 'not_found'));
        exit();
    }
    $con->close();
    header('Location: buddy_list.php?notice=invalid');
    exit();
}

// ---- Unread messages badge ----
$unreadTotal = 0;
try {
    $um = $con->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $um->bind_param("i", $me);
    $um->execute();
    $um->bind_result($unreadTotal);
    $um->fetch();
    $um->close();
} catch (mysqli_sql_exception $e) {
    $unreadTotal = 0;
}

// ---- My accepted buddies, with unread count per buddy ----
$palette = ['#6c5ce7', '#ff6b5a', '#14b8a6', '#2563eb', '#f59e0b', '#e23b3b'];
$buddies = [];
$tableMissing = false;
try {
    $bs = $con->prepare(
        "SELECT u.Sno, u.Name, u.Age, u.Gender, u.Email, u.Course, u.StudyTime, u.Location, u.Goals, u.Method,
                MAX(br.updated_at) AS since
         FROM buddy_requests br
         JOIN users u ON u.Sno = IF(br.sender_id = ?, br.receiver_id, br.sender_id)
         WHERE br.status = 'accepted' AND (br.sender_id = ? OR br.receiver_id = ?)
         GROUP BY u.Sno
         ORDER BY u.Name"
    );
    $bs->bind_param("iii", $me, $me, $me);
    $bs->execute();
    $res = $bs->get_result();
    $n = 0;
    while ($r = $res->fetch_assoc()) {
        $np = preg_split('/\s+/', trim($r['Name']));
        $buddies[(int) $r['Sno']] = $r + [
            'initials' => strtoupper(substr($np[0], 0, 1) . (isset($np[1]) ? substr($np[1], 0, 1) : '')),
            'color'    => $palette[$n++ % count($palette)],
            'unread'   => 0,
        ];
    }
    $bs->close();
} catch (mysqli_sql_exception $e) {
    $tableMissing = true;
}

if ($buddies) {
    try {
        $uc = $con->prepare("SELECT sender_id, COUNT(*) c FROM messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id");
        $uc->bind_param("i", $me);
        $uc->execute();
        $ur = $uc->get_result();
        while ($r = $ur->fetch_assoc()) {
            if (isset($buddies[(int) $r['sender_id']])) $buddies[(int) $r['sender_id']]['unread'] = (int) $r['c'];
        }
        $uc->close();
    } catch (mysqli_sql_exception $e) {
        // messages table not imported yet
    }
}
$con->close();

$q = trim($_GET['q'] ?? '');
$visible = array_filter($buddies, function ($b) use ($q) {
    return $q === '' || stripos($b['Name'] . ' ' . $b['Course'] . ' ' . $b['Location'], $q) !== false;
});

$noticeMessages = [
    'removed'   => ['ok', 'Buddy removed from your list.'],
    'not_found' => ['err', 'That buddy could not be found.'],
    'invalid'   => ['err', 'Your session expired. Please try again.'],
];
$tagClass = ['p', 'c', 't'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buddy List — Study Buddy</title>
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
            <a href="messages.php">
                <span class="text">Messages</span>
                <?php if ($unreadTotal > 0): ?><span class="badge"><?php echo $unreadTotal; ?></span><?php endif; ?>
            </a>
            <a href="buddy_list.php" class="active"><span class="text">Buddy List</span></a>
            <a href="profile.php"><span class="text">Profile</span></a>
            <a href="logout.php" class="nav-logout"><span class="text">Logout</span></a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <h2>Buddy List</h2>
            <span class="chip">BAUSTK · CSE</span>
            <div class="spacer"></div>
            <?php include 'notif_bell.php'; ?><a href="profile.php" class="avatar" title="My profile"><?php echo htmlspecialchars($initials); ?></a>
        </header>

        <main class="content">
            <section class="welcome">
                <h1>My study buddies</h1>
                <p>You have <?php echo count($buddies); ?> connected buddy(s). Message them to plan your next study session.</p>
            </section>

            <?php if (isset($_GET['notice'], $noticeMessages[$_GET['notice']])): [$cls, $txt] = $noticeMessages[$_GET['notice']]; ?>
                <div class="msg <?php echo $cls; ?> request-notice"><?php echo htmlspecialchars($txt); ?></div>
            <?php endif; ?>

            <?php if ($tableMissing): ?>
                <div class="msg err request-notice">Import add_buddy_requests_table.sql in phpMyAdmin to enable the buddy list.</div>
            <?php endif; ?>

            <?php if ($buddies): ?>
                <form class="filters" method="get" action="buddy_list.php">
                    <input type="text" name="q" placeholder="Search your buddies..." value="<?php echo htmlspecialchars($q); ?>">
                    <button type="submit" class="find-btn">Search</button>
                    <a href="buddy_list.php" class="reset">Reset</a>
                </form>

                <section class="buddies">
                    <?php foreach ($visible as $id => $b): ?>
                    <div class="buddy">
                        <?php if ($b['unread'] > 0): ?><span class="match-badge"><?php echo $b['unread']; ?> new message(s)</span><?php endif; ?>
                        <div class="av" style="background: <?php echo $b['color']; ?>"><?php echo htmlspecialchars($b['initials']); ?></div>
                        <h4><?php echo htmlspecialchars($b['Name']); ?></h4>
                        <div class="meta">Age <?php echo (int) $b['Age']; ?> · <?php echo htmlspecialchars($b['Gender']); ?></div>
                        <div class="meta"><?php echo htmlspecialchars($b['Email']); ?></div>
                        <?php if ($b['Method'] !== ''): ?>
                            <div class="meta detail"><span class="d-ico">📚</span> <?php echo htmlspecialchars($b['Method']); ?></div>
                        <?php endif; ?>
                        <?php if ($b['Goals'] !== ''): ?>
                            <div class="meta detail"><span class="d-ico">🎯</span> <?php echo htmlspecialchars(mb_strimwidth($b['Goals'], 0, 70, '…')); ?></div>
                        <?php endif; ?>
                        <div class="tags">
                            <?php $ti = 0; foreach ([$b['Course'], $b['StudyTime'], $b['Location']] as $t): if ($t === '') continue; ?>
                                <span class="tag <?php echo $tagClass[$ti++ % 3]; ?>"><?php echo htmlspecialchars($t); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="meta">Buddies since <?php echo htmlspecialchars(date('M j, Y', strtotime($b['since']))); ?></div>
                        <div class="buddy-actions">
                            <a class="connect" href="messages.php?with=<?php echo (int) $id; ?>">Message</a>
                            <form class="request-form" method="post" action="buddy_list.php" onsubmit="return confirm('Remove <?php echo htmlspecialchars(addslashes($b['Name']), ENT_QUOTES); ?> from your buddies?');">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="buddy_id" value="<?php echo (int) $id; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button class="connect remove-btn" type="submit">Remove</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php if (!$visible): ?><p class="empty">No buddies match "<?php echo htmlspecialchars($q); ?>".</p><?php endif; ?>
            <?php elseif (!$tableMissing): ?>
                <div class="empty-state">
                    <p class="empty">You don't have any study buddies yet.</p>
                    <a href="find_buddy.php"><button class="find-btn">Find Buddy</button></a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
