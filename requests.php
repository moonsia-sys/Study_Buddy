<?php

session_start();



if (!isset($_SESSION['user_id'])) {

    header("Location: login.php?error=" . urlencode("Please log in first."));

    exit();

}



if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

}



require 'db.php';



$me = (int) $_SESSION['user_id'];

$notice = '';



function redirectRequests($notice = '') {

    $url = 'requests.php';

    if ($notice !== '') $url .= '?notice=' . urlencode($notice);

    header('Location: ' . $url);

    exit();

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {

        $con->close();

        redirectRequests('invalid');

    }



    $action = $_POST['action'] ?? '';

    if ($action === 'send') {

        $back = in_array($_POST['return_to'] ?? '', ['dashboard.php', 'find_buddy.php'], true) ? $_POST['return_to'] : 'dashboard.php';
        $buddyId = isset($_POST['buddy_id']) ? (int) $_POST['buddy_id'] : 0;

        if ($buddyId < 1 || $buddyId === $me) {

            $con->close();

            redirectRequests('not_found');

        }



        $student = $con->prepare("SELECT Sno FROM users WHERE Sno = ?");

        $student->bind_param("i", $buddyId);

        $student->execute();

        $student->store_result();

        $exists = $student->num_rows === 1;

        $student->close();

        if (!$exists) {

            $con->close();

            redirectRequests('not_found');

        }



        $existing = $con->prepare("SELECT status FROM buddy_requests WHERE sender_id = ? AND receiver_id = ? LIMIT 1");

        $existing->bind_param("ii", $me, $buddyId);

        $existing->execute();

        $existing->bind_result($existingStatus);

        $hasExisting = $existing->fetch();

        $existing->close();



        if ($hasExisting && $existingStatus === 'pending') {

            $con->close();

            header('Location: ' . $back . '?notice=pending');

            exit();

        }

        if ($hasExisting && $existingStatus === 'accepted') {

            $con->close();

            header('Location: ' . $back . '?notice=connected');

            exit();

        }



        $reverse = $con->prepare("SELECT status FROM buddy_requests WHERE sender_id = ? AND receiver_id = ? LIMIT 1");

        $reverse->bind_param("ii", $buddyId, $me);

        $reverse->execute();

        $reverse->bind_result($reverseStatus);

        $hasReverse = $reverse->fetch();

        $reverse->close();



        if ($hasReverse && $reverseStatus === 'pending') {

            $con->close();

            header('Location: ' . $back . '?notice=received');

            exit();

        }

        if ($hasReverse && $reverseStatus === 'accepted') {

            $con->close();

            header('Location: ' . $back . '?notice=connected');

            exit();

        }



        if ($hasExisting) {

            $send = $con->prepare("UPDATE buddy_requests SET status = 'pending', created_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE sender_id = ? AND receiver_id = ? AND status = 'declined'");

            $send->bind_param("ii", $me, $buddyId);

        } else {

            $send = $con->prepare("INSERT INTO buddy_requests (sender_id, receiver_id) VALUES (?, ?)");

            $send->bind_param("ii", $me, $buddyId);

        }

        $send->execute();

        $send->close();

        $con->close();



        $returnTo = ($_POST['return_to'] ?? '') !== '' ? $back . '?notice=sent' : 'requests.php?notice=sent';

        header('Location: ' . $returnTo);

        exit();

    }



    if ($action === 'respond') {

        $requestId = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

        $decision = $_POST['decision'] ?? '';

        if (!in_array($decision, ['accepted', 'declined'], true) || $requestId < 1) {

            $con->close();

            redirectRequests('invalid');

        }

        $respond = $con->prepare("UPDATE buddy_requests SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND receiver_id = ? AND status = 'pending'");

        $respond->bind_param("sii", $decision, $requestId, $me);

        $respond->execute();

        $changed = $respond->affected_rows === 1;

        $respond->close();

        $con->close();

        redirectRequests($changed ? $decision : 'stale');

    }



    $con->close();

    redirectRequests('invalid');

}



$incoming = [];

$stmt = $con->prepare(

    "SELECT r.id, r.status, r.created_at, u.Sno, u.Name, u.Course, u.StudyTime, u.Location

     FROM buddy_requests r JOIN users u ON u.Sno = r.sender_id

     WHERE r.receiver_id = ? ORDER BY (r.status = 'pending') DESC, r.updated_at DESC, r.id DESC"

);

$stmt->bind_param("i", $me);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) $incoming[] = $row;

$stmt->close();



$outgoing = [];

$stmt = $con->prepare(

    "SELECT r.status, r.created_at, u.Sno, u.Name, u.Course, u.StudyTime, u.Location

     FROM buddy_requests r JOIN users u ON u.Sno = r.receiver_id

     WHERE r.sender_id = ? ORDER BY r.updated_at DESC, r.id DESC"

);

$stmt->bind_param("i", $me);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) $outgoing[] = $row;

$stmt->close();



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

$con->close();



$fullName = $_SESSION['user_name'];

$parts = preg_split('/\s+/', trim($fullName));

$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

$noticeMessages = [

    'sent' => ['ok', 'Your contact request has been sent.'],

    'accepted' => ['ok', 'You are now connected. You can message each other.'],

    'declined' => ['ok', 'The request was declined.'],

    'invalid' => ['err', 'That action could not be completed. Refresh the page and try again.'],

    'stale' => ['err', 'This request has already been handled.'],

    'not_found' => ['err', 'That student could not be found.'],

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contact Requests — Study Buddy</title>

    <link rel="stylesheet" href="style.css">

    <style>
      /* Contact-request cards: prevent the global .connect rule from squeezing names. */
      .request-columns { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:24px; align-items:start; }
      .request-section, .request-list { min-width:0; }
      .request-item {
        display:flex; flex-direction:row; align-items:center; flex-wrap:nowrap;
        gap:12px; width:100%; min-width:0; box-sizing:border-box;
      }
      .request-item > .av { flex:0 0 32px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; }
      .request-item > .request-person { flex:1 1 0%; min-width:0; width:auto; }
      .request-person strong {
        display:block; white-space:normal; word-break:normal;
        overflow-wrap:break-word; line-height:1.4;
      }
      .request-person span {
        display:block; white-space:normal; word-break:normal;
        overflow-wrap:break-word; line-height:1.45;
      }
      .request-item > .request-pill { flex:0 0 auto; white-space:nowrap; }
      .request-item > .request-actions {
        display:flex; flex:0 0 auto; flex-direction:row; flex-wrap:wrap;
        align-items:center; gap:6px; width:auto; margin:0;
      }
      .request-item > .msg-link {
        display:inline-flex !important; align-items:center; justify-content:center;
        flex:0 0 auto !important; width:auto !important; max-width:max-content;
        min-width:90px; white-space:nowrap; margin:0;
      }
      @media (max-width:900px) {
        .request-columns { grid-template-columns:minmax(0,1fr); }
        .request-item { flex-wrap:wrap; }
        .request-item > .request-person { flex:1 1 180px; }
      }
      @media (max-width:480px) {
        .request-item { gap:8px; padding:10px; }
      }
    </style>

</head>

<body>

<div class="layout">

    <aside class="sidebar">

        <div class="brand"><div class="logo">SB</div><span>Study Buddy</span></div>

        <nav class="nav">

            <a href="dashboard.php"><span class="text">Dashboard</span></a>

            <a href="requests.php" class="active"><span class="text">Requests</span><?php if (count(array_filter($incoming, function ($r) { return $r['status'] === 'pending'; })) > 0): ?><span class="badge"><?php echo count(array_filter($incoming, function ($r) { return $r['status'] === 'pending'; })); ?></span><?php endif; ?></a>

            <a href="messages.php"><span class="text">Messages</span><?php if ($unreadTotal > 0): ?><span class="badge"><?php echo $unreadTotal; ?></span><?php endif; ?></a>

            <a href="profile.php"><span class="text">Profile</span></a>

            <a href="logout.php" class="nav-logout"><span class="text">Logout</span></a>

        </nav>

    </aside>

    <div class="main">

        <header class="topbar"><h2>Study Buddy</h2><span class="chip">BAUSTK · CSE</span><div class="spacer"></div><?php include 'notif_bell.php'; ?><a href="profile.php" class="avatar" title="My profile"><?php echo htmlspecialchars($initials); ?></a></header>

        <main class="content">

            <section class="welcome"><h1>Contact requests</h1><p>See who wants to study together and check the status of your requests.</p></section>

            <?php if (isset($_GET['notice'], $noticeMessages[$_GET['notice']])): ?>

                <div class="msg <?php echo $noticeMessages[$_GET['notice']][0]; ?> request-notice"><?php echo htmlspecialchars($noticeMessages[$_GET['notice']][1]); ?></div>

            <?php endif; ?>

            <div class="request-columns">

                <section class="request-section">

                    <h2>Received</h2>

                    <div class="request-list">

                        <?php if (!$incoming): ?><p class="request-empty">No contact requests yet.</p><?php endif; ?>

                        <?php foreach ($incoming as $request):

                            $meta = array_filter([$request['Course'], $request['StudyTime'], $request['Location']]);

                            $initialParts = preg_split('/\s+/', trim($request['Name']));

                            $avatarInitials = strtoupper(substr($initialParts[0], 0, 1) . (isset($initialParts[1]) ? substr($initialParts[1], 0, 1) : ''));

                        ?>

                            <article class="request-item">

                                <span class="av" style="background: var(--purple)"><?php echo htmlspecialchars($avatarInitials); ?></span>

                                <div class="request-person"><strong><?php echo htmlspecialchars($request['Name']); ?></strong><span><?php echo htmlspecialchars(implode(' · ', $meta) ?: 'Study Buddy Finder'); ?></span><span><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at']))); ?></span></div>

                                <?php if ($request['status'] === 'pending'): ?>

                                    <form class="request-actions" method="post" action="requests.php">

                                        <input type="hidden" name="action" value="respond">

                                        <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">

                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                        <button class="accept" type="submit" name="decision" value="accepted">Accept</button>

                                        <button type="submit" name="decision" value="declined">Decline</button>

                                    </form>

                                <?php else: ?>

                                    <span class="request-pill <?php echo htmlspecialchars($request['status']); ?>"><?php echo ucfirst(htmlspecialchars($request['status'])); ?></span>

                                    <?php if ($request['status'] === 'accepted'): ?><a class="connect msg-link" href="messages.php?with=<?php echo (int) $request['Sno']; ?>">Message</a><?php endif; ?>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

                <section class="request-section">

                    <h2>Sent</h2>

                    <div class="request-list">

                        <?php if (!$outgoing): ?><p class="request-empty">Requests you send will appear here.</p><?php endif; ?>

                        <?php foreach ($outgoing as $request):

                            $meta = array_filter([$request['Course'], $request['StudyTime'], $request['Location']]);

                            $initialParts = preg_split('/\s+/', trim($request['Name']));

                            $avatarInitials = strtoupper(substr($initialParts[0], 0, 1) . (isset($initialParts[1]) ? substr($initialParts[1], 0, 1) : ''));

                        ?>

                            <article class="request-item">

                                <span class="av" style="background: var(--coral)"><?php echo htmlspecialchars($avatarInitials); ?></span>

                                <div class="request-person"><strong><?php echo htmlspecialchars($request['Name']); ?></strong><span><?php echo htmlspecialchars(implode(' · ', $meta) ?: 'Study Buddy Finder'); ?></span><span><?php echo htmlspecialchars(date('M j, Y', strtotime($request['created_at']))); ?></span></div>

                                <span class="request-pill <?php echo htmlspecialchars($request['status']); ?>"><?php echo $request['status'] === 'pending' ? 'Awaiting response' : ucfirst(htmlspecialchars($request['status'])); ?></span>

                                <?php if ($request['status'] === 'accepted'): ?><a class="connect msg-link" href="messages.php?with=<?php echo (int) $request['Sno']; ?>">Message</a><?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

            </div>

        </main>

    </div>

</div>

</body>

</html>