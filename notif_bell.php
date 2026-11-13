<?php
// Notification bell for the top bar. Include it inside <header class="topbar">.
// Uses its own database connection, so it works even after the page closed $con.
$notifItems = [];
$notifMe = (int) ($_SESSION['user_id'] ?? 0);
$notifCon = @mysqli_connect("localhost", "root", "", "study_buddy");

if ($notifCon && $notifMe > 0) {
    try {
        // Incoming buddy requests waiting for me
        $s = $notifCon->prepare(
            "SELECT u.Name, br.created_at FROM buddy_requests br JOIN users u ON u.Sno = br.sender_id
             WHERE br.receiver_id = ? AND br.status = 'pending' ORDER BY br.created_at DESC LIMIT 5"
        );
        $s->bind_param("i", $notifMe);
        $s->execute();
        $r = $s->get_result();
        while ($row = $r->fetch_assoc()) {
            $notifItems[] = ['icon' => '🤝', 'text' => $row['Name'] . ' sent you a buddy request', 'link' => 'requests.php', 'time' => $row['created_at']];
        }
        $s->close();

        // My requests accepted in the last 7 days
        $s = $notifCon->prepare(
            "SELECT u.Name, br.updated_at FROM buddy_requests br JOIN users u ON u.Sno = br.receiver_id
             WHERE br.sender_id = ? AND br.status = 'accepted' AND br.updated_at >= NOW() - INTERVAL 7 DAY
             ORDER BY br.updated_at DESC LIMIT 5"
        );
        $s->bind_param("i", $notifMe);
        $s->execute();
        $r = $s->get_result();
        while ($row = $r->fetch_assoc()) {
            $notifItems[] = ['icon' => '✅', 'text' => $row['Name'] . ' accepted your request', 'link' => 'buddy_list.php', 'time' => $row['updated_at']];
        }
        $s->close();
    } catch (mysqli_sql_exception $e) {
        // buddy_requests table not imported yet
    }

    try {
        // Unread messages, grouped by sender
        $s = $notifCon->prepare(
            "SELECT u.Sno, u.Name, COUNT(*) c, MAX(m.created_at) t FROM messages m JOIN users u ON u.Sno = m.sender_id
             WHERE m.receiver_id = ? AND m.is_read = 0 GROUP BY u.Sno, u.Name ORDER BY t DESC LIMIT 5"
        );
        $s->bind_param("i", $notifMe);
        $s->execute();
        $r = $s->get_result();
        while ($row = $r->fetch_assoc()) {
            $notifItems[] = ['icon' => '💬', 'text' => $row['Name'] . ' sent you ' . $row['c'] . ' new message(s)', 'link' => 'messages.php?with=' . (int) $row['Sno'], 'time' => $row['t']];
        }
        $s->close();
    } catch (mysqli_sql_exception $e) {
        // messages table not imported yet
    }
    $notifCon->close();
}

usort($notifItems, fn($a, $b) => strcmp($b['time'], $a['time']));

if (!function_exists('notifAgo')) {
    function notifAgo($dt) {
        $d = time() - strtotime($dt);
        if ($d < 60) return 'just now';
        if ($d < 3600) return floor($d / 60) . 'm ago';
        if ($d < 86400) return floor($d / 3600) . 'h ago';
        return floor($d / 86400) . 'd ago';
    }
}
?>
<div class="bell-wrap">
    <button type="button" class="bell" id="bellBtn" aria-label="Notifications" aria-expanded="false">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($notifItems): ?><span class="dot"></span><?php endif; ?>
    </button>
    <div class="notif-panel" id="notifPanel" hidden>
        <div class="notif-head">Notifications <?php if ($notifItems): ?><span class="notif-count"><?php echo count($notifItems); ?></span><?php endif; ?></div>
        <?php if ($notifItems): ?>
            <?php foreach ($notifItems as $it): ?>
                <a class="notif-item" href="<?php echo htmlspecialchars($it['link']); ?>">
                    <span class="notif-ico"><?php echo $it['icon']; ?></span>
                    <span class="notif-text"><?php echo htmlspecialchars($it['text']); ?><small><?php echo notifAgo($it['time']); ?></small></span>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="notif-empty">You're all caught up!</p>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var b = document.getElementById('bellBtn'), p = document.getElementById('notifPanel');
    b.addEventListener('click', function (e) {
        e.stopPropagation();
        p.hidden = !p.hidden;
        b.setAttribute('aria-expanded', String(!p.hidden));
    });
    document.addEventListener('click', function (e) { if (!p.contains(e.target)) p.hidden = true; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') p.hidden = true; });
})();
</script>
