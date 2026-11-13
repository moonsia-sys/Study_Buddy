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

// ---- My own profile (used to calculate a match score) ----
$mine = ['Course' => '', 'StudyTime' => '', 'Location' => '', 'Method' => ''];
$ms = $con->prepare("SELECT Course, StudyTime, Location, Method FROM users WHERE Sno = ?");
$ms->bind_param("i", $me);
$ms->execute();
$row = $ms->get_result()->fetch_assoc();
if ($row) $mine = $row;
$ms->close();
$profileIncomplete = ($mine['Course'] === '' && $mine['StudyTime'] === '' && $mine['Location'] === '');

// ---- Request states (same rules as the dashboard) ----
$requestStates = [];
try {
    $rs = $con->prepare("SELECT sender_id, receiver_id, status FROM buddy_requests WHERE sender_id = ? OR receiver_id = ?");
    $rs->bind_param("ii", $me, $me);
    $rs->execute();
    $rr = $rs->get_result();
    $priority = ['declined' => 1, 'outgoing_pending' => 2, 'incoming_pending' => 3, 'accepted' => 4];
    while ($r = $rr->fetch_assoc()) {
        $sid = (int) $r['sender_id'];
        $rid = (int) $r['receiver_id'];
        $other = ($sid === $me) ? $rid : $sid;
        $state = $r['status'];
        if ($state === 'pending') $state = ($rid === $me) ? 'incoming_pending' : 'outgoing_pending';
        if (!isset($requestStates[$other]) || $priority[$state] > $priority[$requestStates[$other]]) {
            $requestStates[$other] = $state;
        }
    }
    $rs->close();
} catch (mysqli_sql_exception $e) {
    // buddy_requests table not imported yet
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

// ---- All other students ----
$palette = ['#6c5ce7', '#ff6b5a', '#14b8a6', '#2563eb', '#f59e0b', '#e23b3b'];
$students = [];
$st = $con->prepare("SELECT Sno, Name, Age, Gender, Course, StudyTime, Location, Goals, Method, Other FROM users WHERE Sno <> ? ORDER BY Sno DESC");
$st->bind_param("i", $me);
$st->execute();
$res = $st->get_result();
$n = 0;
while ($r = $res->fetch_assoc()) {
    // Match score: 1 point for each shared preference (max 4)
    $score = 0;
    $shared = [];
    foreach (['Course' => 'Same course', 'StudyTime' => 'Same study time', 'Location' => 'Same place', 'Method' => 'Same method'] as $col => $label) {
        if ($mine[$col] !== '' && strcasecmp($mine[$col], $r[$col]) === 0) { $score++; $shared[] = $label; }
    }
    $np = preg_split('/\s+/', trim($r['Name']));
    $students[] = [
        'id'       => (int) $r['Sno'],
        'name'     => $r['Name'],
        'initials' => strtoupper(substr($np[0], 0, 1) . (isset($np[1]) ? substr($np[1], 0, 1) : '')),
        'meta'     => 'Age ' . $r['Age'] . ' · ' . $r['Gender'],
        'color'    => $palette[$n++ % count($palette)],
        'course'   => $r['Course'],
        'time'     => $r['StudyTime'],
        'location' => $r['Location'],
        'goal'     => $r['Goals'],
        'method'   => $r['Method'],
        'about'    => $r['Other'],
        'gender'   => $r['Gender'],
        'score'    => $score,
        'shared'   => $shared,
        'state'    => $requestStates[(int) $r['Sno']] ?? '',
    ];
}
$st->close();
$con->close();

// ---- Filters from the URL ----
$q       = trim($_GET['q'] ?? '');
$fCourse = $_GET['course'] ?? '';
$fTime   = $_GET['time'] ?? '';
$fLoc    = $_GET['location'] ?? '';
$fMethod = $_GET['method'] ?? '';
$fGender = $_GET['gender'] ?? '';
$hideConnected = isset($_GET['hide_connected']);
$sort    = $_GET['sort'] ?? 'match';

$courses   = array_values(array_unique(array_filter(array_column($students, 'course'))));
$locations = array_values(array_unique(array_filter(array_column($students, 'location'))));
$methods   = array_values(array_unique(array_filter(array_column($students, 'method'))));
$genders   = array_values(array_unique(array_filter(array_column($students, 'gender'))));
sort($courses); sort($locations); sort($methods); sort($genders);
$times = ["Morning", "Afternoon", "Evening"];

$results = array_values(array_filter($students, function ($s) use ($q, $fCourse, $fTime, $fLoc, $fMethod, $fGender, $hideConnected) {
    $text = strtolower($s['name'] . ' ' . $s['course'] . ' ' . $s['location'] . ' ' . $s['goal'] . ' ' . $s['method'] . ' ' . $s['about']);
    return ($q === '' || strpos($text, strtolower($q)) !== false)
        && ($fCourse === '' || $s['course'] === $fCourse)
        && ($fTime === ''   || $s['time'] === $fTime)
        && ($fLoc === ''    || $s['location'] === $fLoc)
        && ($fMethod === '' || $s['method'] === $fMethod)
        && ($fGender === '' || $s['gender'] === $fGender)
        && (!$hideConnected || $s['state'] !== 'accepted');
}));

if ($sort === 'name') {
    usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));
} elseif ($sort === 'match') {
    usort($results, fn($a, $b) => $b['score'] <=> $a['score'] ?: $b['id'] <=> $a['id']);
} // 'newest' keeps the database order

function opts($list, $selected) {
    foreach ($list as $v) {
        $s = ($v === $selected) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($v) . '"' . $s . '>' . htmlspecialchars($v) . '</option>';
    }
}

$noticeMessages = [
    'sent'      => 'Your contact request has been sent.',
    'pending'   => 'Your request is already waiting for a response.',
    'connected' => 'You are already connected with this student.',
    'received'  => 'This student has already sent you a request. Review it in Requests.',
    'not_found' => 'That student could not be found.',
];
$tagClass = ['p', 'c', 't'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Buddy — Study Buddy</title>
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
            <a href="find_buddy.php" class="active"><span class="text">Find Buddy</span></a>
            <a href="requests.php"><span class="text">Requests</span></a>
            <a href="messages.php">
                <span class="text">Messages</span>
                <?php if ($unreadTotal > 0): ?><span class="badge"><?php echo $unreadTotal; ?></span><?php endif; ?>
            </a>
            <a href="buddy_list.php"><span class="text">Buddy List</span></a>
            <a href="profile.php"><span class="text">Profile</span></a>
            <a href="logout.php" class="nav-logout"><span class="text">Logout</span></a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <h2>Find Buddy</h2>
            <span class="chip">BAUSTK · CSE</span>
            <div class="spacer"></div>
            <?php include 'notif_bell.php'; ?><a href="profile.php" class="avatar" title="My profile"><?php echo htmlspecialchars($initials); ?></a>
        </header>

        <main class="content">
            <section class="welcome">
                <h1>Find a study buddy</h1>
                <p>Students who share your course, study time and place are shown first.</p>
            </section>

            <?php if (isset($_GET['notice'], $noticeMessages[$_GET['notice']])): ?>
                <div class="msg <?php echo $_GET['notice'] === 'sent' ? 'ok' : 'err'; ?> request-notice"><?php echo htmlspecialchars($noticeMessages[$_GET['notice']]); ?></div>
            <?php endif; ?>

            <?php if ($profileIncomplete): ?>
                <div class="msg err request-notice">Add your course, study time and location in your <a href="profile.php">Profile</a> to get better matches.</div>
            <?php endif; ?>

            <form class="filters" method="get" action="find_buddy.php">
                <input type="text" name="q" placeholder="Search name, subject or goal..." value="<?php echo htmlspecialchars($q); ?>">
                <select name="course"><option value="">All courses</option><?php opts($courses, $fCourse); ?></select>
                <select name="time"><option value="">Any time</option><?php opts($times, $fTime); ?></select>
                <select name="location"><option value="">Any location</option><?php opts($locations, $fLoc); ?></select>
                <select name="method"><option value="">Any method</option><?php opts($methods, $fMethod); ?></select>
                <select name="gender"><option value="">Any gender</option><?php opts($genders, $fGender); ?></select>
                <select name="sort">
                    <option value="match" <?php if ($sort === 'match') echo 'selected'; ?>>Best match</option>
                    <option value="newest" <?php if ($sort === 'newest') echo 'selected'; ?>>Newest</option>
                    <option value="name" <?php if ($sort === 'name') echo 'selected'; ?>>Name A–Z</option>
                </select>
                <label class="check"><input type="checkbox" name="hide_connected" <?php if ($hideConnected) echo 'checked'; ?>> Hide my buddies</label>
                <button type="submit" class="find-btn">Search</button>
                <a href="find_buddy.php" class="reset">Reset</a>
            </form>

            <p class="result-count"><?php echo count($results); ?> student(s) found</p>

            <section class="buddies">
                <?php foreach ($results as $b): ?>
                <div class="buddy">
                    <?php if ($b['score'] > 0): ?>
                        <span class="match-badge"><?php echo $b['score'] * 25; ?>% match</span>
                    <?php endif; ?>
                    <div class="av" style="background: <?php echo $b['color']; ?>"><?php echo htmlspecialchars($b['initials']); ?></div>
                    <h4><?php echo htmlspecialchars($b['name']); ?></h4>
                    <div class="meta"><?php echo htmlspecialchars($b['meta']); ?></div>
                    <?php if ($b['method'] !== ''): ?>
                        <div class="meta detail"><span class="d-ico">📚</span> <?php echo htmlspecialchars($b['method']); ?></div>
                    <?php endif; ?>
                    <?php if ($b['goal'] !== ''): ?>
                        <div class="meta detail"><span class="d-ico">🎯</span> <?php echo htmlspecialchars(mb_strimwidth($b['goal'], 0, 70, '…')); ?></div>
                    <?php endif; ?>
                    <div class="tags">
                        <?php $ti = 0; foreach ([$b['course'], $b['time'], $b['location']] as $t): if ($t === '') continue; ?>
                            <span class="tag <?php echo $tagClass[$ti++ % 3]; ?>"><?php echo htmlspecialchars($t); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($b['shared']): ?>
                        <div class="meta shared">✓ <?php echo htmlspecialchars(implode(' · ', $b['shared'])); ?></div>
                    <?php endif; ?>
                    <div class="buddy-actions">
                        <?php if ($b['state'] === 'accepted'): ?>
                            <span class="connect request-status accepted">Connected</span>
                        <?php elseif ($b['state'] === 'incoming_pending'): ?>
                            <a class="connect request-status" href="requests.php">Respond to request</a>
                        <?php elseif ($b['state'] === 'outgoing_pending'): ?>
                            <span class="connect request-status pending">Request pending</span>
                        <?php else: ?>
                            <form class="request-form" method="post" action="requests.php">
                                <input type="hidden" name="action" value="send">
                                <input type="hidden" name="buddy_id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="return_to" value="find_buddy.php">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button class="connect" type="submit"><?php echo $b['state'] === 'declined' ? 'Request again' : 'Connect'; ?></button>
                            </form>
                        <?php endif; ?>
                        <a class="connect msg-link" href="messages.php?with=<?php echo $b['id']; ?>">Message</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </section>

            <?php if (!$results): ?>
                <p class="empty"><?php echo $students ? 'No students match your filters. Try changing them.' : 'No other students have registered yet. Invite your classmates!'; ?></p>
            <?php endif; ?>
        </main>
    </div>
</div>
</body>
</html>
