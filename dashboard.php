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

$me        = (int) $_SESSION['user_id'];
$fullName  = $_SESSION['user_name'];
$firstName = explode(' ', trim($fullName))[0];

// initials for the avatar ("Moonsia Nowrin" -> "MN")
$parts = preg_split('/\s+/', trim($fullName));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

// ---- Real student profiles from the database (everyone except me) ----
$buddies = [];
$stmt = $con->prepare("SELECT Sno, Name, Age, Gender, Course, StudyTime, Location, Goals, Method, Other FROM users WHERE Sno <> ? ORDER BY Sno DESC");
$stmt->bind_param("i", $me);
$stmt->execute();
$res = $stmt->get_result();

$palette = ['#6c5ce7', '#ff6b5a', '#14b8a6', '#2563eb', '#f59e0b', '#e23b3b'];
$n = 0;
while ($row = $res->fetch_assoc()) {
    $bp = preg_split('/\s+/', trim($row['Name']));
    $bi = strtoupper(substr($bp[0], 0, 1) . (isset($bp[1]) ? substr($bp[1], 0, 1) : ''));
    $tags = [];
    foreach ([$row['Course'], $row['StudyTime'], $row['Location']] as $t) {
        if ($t !== '') $tags[] = $t;
    }
    $buddies[] = [
        'id'       => (int) $row['Sno'],
        'name'     => $row['Name'],
        'meta'     => 'Age ' . $row['Age'] . ' · ' . $row['Gender'],
        'color'    => $palette[$n % count($palette)],
        'course'   => $row['Course'],
        'time'     => $row['StudyTime'],
        'location' => $row['Location'],
        'goal'     => $row['Goals'],
        'method'   => $row['Method'],
        'about'    => $row['Other'],
        'tags'     => $tags,
    ];
    $n++;
}
$stmt->close();

// Contact request states and incoming pending count
$requestStates = [];
$pendingRequests = 0;
try {
    $requestStmt = $con->prepare(
        "SELECT sender_id, receiver_id, status FROM buddy_requests
         WHERE sender_id = ? OR receiver_id = ? ORDER BY id DESC"
    );
    $requestStmt->bind_param("ii", $me, $me);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    $statePriority = ['declined' => 1, 'outgoing_pending' => 2, 'incoming_pending' => 3, 'accepted' => 4];
    while ($request = $requestResult->fetch_assoc()) {
        $senderId = (int) $request['sender_id'];
        $receiverId = (int) $request['receiver_id'];
        $otherId = ($senderId === $me) ? $receiverId : $senderId;
        $state = $request['status'];
        if ($state === 'pending') {
            $state = ($receiverId === $me) ? 'incoming_pending' : 'outgoing_pending';
            if ($receiverId === $me) $pendingRequests++;
        }
        if (!isset($requestStates[$otherId]) || $statePriority[$state] > $statePriority[$requestStates[$otherId]]) {
            $requestStates[$otherId] = $state;
        }
    }
    $requestStmt->close();
} catch (mysqli_sql_exception $e) {
    // The migration can be imported later without making the dashboard unavailable.
}

// total unread private messages (for the sidebar badge)
$unreadTotal = 0;
try {
    $um = $con->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $um->bind_param("i", $me);
    $um->execute();
    $um->bind_result($unreadTotal);
    $um->fetch();
    $um->close();
} catch (mysqli_sql_exception $e) {
    $unreadTotal = 0; // messages table not created yet
}
$con->close();

$courses_list = array_values(array_unique(array_filter(array_column($buddies, 'course'))));
$subjects     = count($courses_list);
$studyBuddies = count($buddies);

// Sample cards shown only until real students register
if ($n === 0) {
    $buddies = [
        ["name" => "Moonsia Nowrin",  "meta" => "CSE · 3rd Year", "color" => "#6c5ce7", "course" => "DSA",  "time" => "Evening",   "location" => "Library",     "goal" => "Pass the DSA final exam",     "method" => "Group study",         "about" => "", "tags" => ["DSA", "Evening", "Library"]],
        ["name" => "Mikasa Ackerman", "meta" => "CSE · 2nd Year", "color" => "#ff6b5a", "course" => "DBMS", "time" => "Morning",   "location" => "Online",      "goal" => "Master SQL queries & ER diagrams", "method" => "Flashcards & quizzes", "about" => "", "tags" => ["DBMS", "Morning", "Online"]],
        ["name" => "Maki Zenin",      "meta" => "EEE · 3rd Year", "color" => "#14b8a6", "course" => "C++",  "time" => "Afternoon", "location" => "Campus Cafe", "goal" => "Solve 100 practice problems", "method" => "Practice problems",   "about" => "", "tags" => ["C++", "Afternoon", "Campus Cafe"]],
    ];
    $sample = true;
} else {
    $sample = false;
}

// ---- Search & filter (from the URL, e.g. ?q=maki&course=DSA) ----
$q       = trim($_GET['q'] ?? '');
$fCourse = $_GET['course'] ?? '';
$fTime   = $_GET['time'] ?? '';
$fLoc    = $_GET['location'] ?? '';

$times = ["Morning", "Afternoon", "Evening"];
$courses   = array_values(array_unique(array_filter(array_column($buddies, 'course'))));
$locations = array_values(array_unique(array_filter(array_column($buddies, 'location'))));
sort($courses); sort($locations);

function searchText($b) {
    return strtolower($b['name'] . ' ' . $b['meta'] . ' ' . $b['course'] . ' ' . $b['time'] . ' ' . $b['location'] . ' ' . $b['goal'] . ' ' . $b['method'] . ' ' . $b['about'] . ' ' . implode(' ', $b['tags']));
}

$visibleCount = 0;
foreach ($buddies as $i => $b) {
    $ok = ($q === '' || strpos(searchText($b), strtolower($q)) !== false)
       && ($fCourse === '' || $b['course'] === $fCourse)
       && ($fTime === ''   || $b['time'] === $fTime)
       && ($fLoc === ''    || $b['location'] === $fLoc);
    $buddies[$i]['show'] = $ok;
    if ($ok) $visibleCount++;
}

function opts($list, $selected) {
    foreach ($list as $v) {
        $s = ($v === $selected) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($v) . '"' . $s . '>' . htmlspecialchars($v) . '</option>';
    }
}

$tagClass = ['p', 'c', 't'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Study Buddy</title>
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
            <a href="dashboard.php" class="active"> <span class="text">Dashboard</span></a>
            <a href="find_buddy.php"><span class="text">Find Buddy</span></a>
            <a href="requests.php"> <span class="text">Requests</span></a>
            <a href="messages.php">
                <span class="text">Messages</span>
                <?php if ($unreadTotal > 0): ?><span class="badge"><?php echo $unreadTotal; ?></span><?php endif; ?>
            </a>
            <a href="buddy_list.php"> <span class="text">Buddy List</span></a>
            <a href="profile.php"><span class="text">Profile</span></a>
            <a href="logout.php" class="nav-logout"> <span class="text">Logout</span></a>
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
                <h1>Welcome back, <?php echo htmlspecialchars($firstName); ?></h1>
                <p>Find a study partner for your upcoming exams and stay on track together. Happy Study!</p>
            </section>

            <?php
            $noticeMessages = [
                'sent' => 'Your contact request has been sent.',
                'pending' => 'Your request is already waiting for a response.',
                'connected' => 'You are already connected with this student.',
                'received' => 'This student has already sent you a request. Review it in Requests.',
                'declined' => 'This student previously declined your request. You can try again.',
                'not_found' => 'That student could not be found.',
            ];
            if (isset($_GET['notice'], $noticeMessages[$_GET['notice']])):
            ?>
                <div class="msg <?php echo $_GET['notice'] === 'sent' ? 'ok' : 'err'; ?> request-notice"><?php echo htmlspecialchars($noticeMessages[$_GET['notice']]); ?></div>
            <?php endif; ?>

            <section class="stats">
                <div class="stat">
                    <div class="icon i-purple"></div>
                    <div>
                        <div class="label">Subjects</div>
                        <div class="value"><?php echo $subjects; ?></div>
                    </div>
                </div>
                <div class="stat">
                    <div class="icon i-coral"></div>
                    <div>
                        <div class="label">Buddy Requests</div>
                        <div class="value"><?php echo $pendingRequests; ?></div>
                    </div>
                </div>
                <div class="stat">
                    <div class="icon i-teal"></div>
                    <div>
                        <div class="label">Study Buddies</div>
                        <div class="value"><?php echo $studyBuddies; ?></div>
                    </div>
                </div>
            </section>

            <h3 class="section-title">Find study buddies</h3>

            <form class="filters" method="get" action="dashboard.php">
                <input type="text" name="q" id="fq" placeholder="Search by name or subject..." value="<?php echo htmlspecialchars($q); ?>">
                <select name="course" id="fcourse">
                    <option value="">All courses</option>
                    <?php opts($courses, $fCourse); ?>
                </select>
                <select name="time" id="ftime">
                    <option value="">Any time</option>
                    <?php opts($times, $fTime); ?>
                </select>
                <select name="location" id="floc">
                    <option value="">Any location</option>
                    <?php opts($locations, $fLoc); ?>
                </select>
                <button type="submit" class="find-btn">Search</button>
                <a href="dashboard.php" class="reset">Reset</a>
            </form>

            <?php if ($sample): ?>
                <p class="result-count" id="resultCount">Sample buddies — they will be replaced by real student profiles as classmates register.</p>
            <?php else: ?>
                <p class="result-count" id="resultCount"><?php echo $visibleCount; ?> buddy(s) found</p>
            <?php endif; ?>

            <section class="buddies" id="buddyGrid">
                <?php foreach ($buddies as $b):
                    $bp = preg_split('/\s+/', $b['name']);
                    $bi = strtoupper(substr($bp[0], 0, 1) . (isset($bp[1]) ? substr($bp[1], 0, 1) : ''));
                ?>
                <div class="buddy" <?php if (!$b['show']) echo 'style="display:none"'; ?>
                     data-search="<?php echo htmlspecialchars(searchText($b)); ?>"
                     data-course="<?php echo htmlspecialchars($b['course']); ?>"
                     data-time="<?php echo htmlspecialchars($b['time']); ?>"
                     data-location="<?php echo htmlspecialchars($b['location']); ?>">
                    <div class="av" style="background: <?php echo $b['color']; ?>"><?php echo $bi; ?></div>
                    <h4><?php echo htmlspecialchars($b['name']); ?></h4>
                    <div class="meta"><?php echo htmlspecialchars($b['meta']); ?></div>
                    <div class="meta"><?php echo htmlspecialchars($b['time']); ?> · <?php echo htmlspecialchars($b['location']); ?></div>
                    <?php if ($b['method'] !== ''): ?>
                        <div class="meta detail"><span class="d-ico">📚</span> <?php echo htmlspecialchars($b['method']); ?></div>
                    <?php endif; ?>
                    <?php if ($b['goal'] !== ''): ?>
                        <div class="meta detail"><span class="d-ico">🎯</span> <?php echo htmlspecialchars(mb_strimwidth($b['goal'], 0, 55, '…')); ?></div>
                    <?php endif; ?>
                    <?php if ($b['about'] !== ''): ?>
                        <div class="meta"><?php echo htmlspecialchars(mb_strimwidth($b['about'], 0, 60, '…')); ?></div>
                    <?php endif; ?>
                    <div class="tags">
                        <?php foreach ($b['tags'] as $ti => $t): ?>
                            <span class="tag <?php echo $tagClass[$ti % 3]; ?>"><?php echo htmlspecialchars($t); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="buddy-actions">
                        <?php if (!empty($b['id'])):
                            $connectionState = $requestStates[(int) $b['id']] ?? '';
                        ?>
                            <?php if ($connectionState === 'accepted'): ?>
                                <span class="connect request-status accepted">Connected</span>
                            <?php elseif ($connectionState === 'incoming_pending'): ?>
                                <a class="connect request-status" href="requests.php">Respond to request</a>
                            <?php elseif ($connectionState === 'outgoing_pending'): ?>
                                <span class="connect request-status pending">Request pending</span>
                            <?php else: ?>
                                <form class="request-form" method="post" action="requests.php">
                                    <input type="hidden" name="action" value="send">
                                    <input type="hidden" name="buddy_id" value="<?php echo (int) $b['id']; ?>">
                                    <input type="hidden" name="return_to" value="dashboard.php">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button class="connect" type="submit"><?php echo $connectionState === 'declined' ? 'Request again' : 'Connect'; ?></button>
                                </form>
                            <?php endif; ?>
                            <a class="connect msg-link" href="messages.php?with=<?php echo (int) $b['id']; ?>">Message</a>
                        <?php else: ?>
                            <span class="connect request-status">Sample profile</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </section>
            <p class="empty" id="emptyMsg" <?php if ($visibleCount) echo 'style="display:none"'; ?>>No study buddies match your filters. Try changing them.</p>

            <script>
            // Live filtering while typing / changing a dropdown
            (function () {
                var q = document.getElementById('fq'), c = document.getElementById('fcourse'),
                    t = document.getElementById('ftime'), l = document.getElementById('floc');
                var cards = document.querySelectorAll('#buddyGrid .buddy');
                function run() {
                    var n = 0, s = q.value.trim().toLowerCase();
                    cards.forEach(function (card) {
                        var ok = (!s || card.dataset.search.indexOf(s) !== -1) &&
                                 (!c.value || card.dataset.course === c.value) &&
                                 (!t.value || card.dataset.time === t.value) &&
                                 (!l.value || card.dataset.location === l.value);
                        card.style.display = ok ? '' : 'none';
                        if (ok) n++;
                    });
                    document.getElementById('resultCount').textContent = n + ' buddy(s) found';
                    document.getElementById('emptyMsg').style.display = n ? 'none' : '';
                }
                q.addEventListener('input', run);
                [c, t, l].forEach(function (el) { el.addEventListener('change', run); });
            })();
            </script>

            <div class="find-row">
                <a href="find_buddy.php"><button class="find-btn"> Find Buddy</button></a>
                <span class="hint">Browse all available study partners at your university.</span>
            </div>

        </main>
    </div>
</div>

</body>
</html>
