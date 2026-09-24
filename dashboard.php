
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in first."));
    exit();
}

$fullName  = $_SESSION['user_name'];
$firstName = explode(' ', trim($fullName))[0];

// initials for the avatar ("Moonsia Nowrin" -> "MN")
$parts = preg_split('/\s+/', trim($fullName));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
// status side
$pendingRequests = 3;
$subjects        = 6;
$studyBuddies    = 8;

$recommended = [
    ["name" => "Moonsia Nowrin",  "meta" => "CSE · 3rd Year", "color" => "#6c5ce7", "tags" => [["C++", "p"], ["DSA", "t"]]],
    ["name" => "Mikasa Ackerman", "meta" => "CSE · 2nd Year", "color" => "#ff6b5a", "tags" => [["DBMS", "c"], ["OOP", "p"]]],
    ["name" => "Maki Zenin",  "meta" => "EEE · 3rd Year", "color" => "#14b8a6", "tags" => [["DSA", "t"], ["C++", "p"]]],
];
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
            <div class="bell"><span class="dot"></span></div>
            <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
        </header>

        <main class="content">

            <section class="welcome">
                <h1>Welcome back, <?php echo htmlspecialchars($firstName); ?></h1>
                <p>Find a study partner for your upcoming exams and stay on track together. Happy Study!</p>
            </section>

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

            <h3 class="section-title">Recommended study buddies</h3>

            <section class="buddies">
                <?php foreach ($recommended as $b):
                    $bp = preg_split('/\s+/', $b['name']);
                    $bi = strtoupper(substr($bp[0], 0, 1) . substr($bp[1], 0, 1));
                ?>
                <div class="buddy">
                    <div class="av" style="background: <?php echo $b['color']; ?>"><?php echo $bi; ?></div>
                    <h4><?php echo htmlspecialchars($b['name']); ?></h4>
                    <div class="meta"><?php echo htmlspecialchars($b['meta']); ?></div>
                    <div class="tags">
                        <?php foreach ($b['tags'] as $t): ?>
                            <span class="tag <?php echo $t[1]; ?>"><?php echo $t[0]; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <button class="connect">Connect</button>
                </div>
                <?php endforeach; ?>
            </section>

            <div class="find-row">
                <a href="find_buddy.php"><button class="find-btn"> Find Buddy</button></a>
                <span class="hint">Browse all available study partners at your university.</span>
            </div>

        </main>
    </div>
</div>

</body>
</html>
