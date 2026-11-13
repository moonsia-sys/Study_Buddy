<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=" . urlencode("Please log in first."));
    exit();
}

require 'db.php';

$sno = (int) $_SESSION['user_id'];

// ---- Load my saved details ----
$stmt = $con->prepare("SELECT Name, Age, Gender, Email, Phone, Other, Course, StudyTime, Location, Goals, Method FROM users WHERE Sno = ?");
$stmt->bind_param("i", $sno);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    // account no longer exists — log out
    $stmt->close();
    $con->close();
    header("Location: logout.php");
    exit();
}

$u = $res->fetch_assoc();

// total unread private messages (for the sidebar badge)
$unreadTotal = 0;
try {
    $um = $con->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $um->bind_param("i", $sno);
    $um->execute();
    $um->bind_result($unreadTotal);
    $um->fetch();
    $um->close();
} catch (mysqli_sql_exception $e) {
    $unreadTotal = 0; // messages table not created yet
}

$stmt->close();
$con->close();

$fullName  = $_SESSION['user_name'];
$parts     = preg_split('/\s+/', trim($fullName));
$initials  = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

$courseOptions    = ["DSA", "DBMS", "C++", "OOP", "Web Programming", "Digital Logic", "Mathematics", "Other"];
$timeOptions      = ["Morning", "Afternoon", "Evening"];
$locationOptions  = ["Library", "Online", "Campus Cafe", "Dept. Lab", "Other"];
$methodOptions    = ["Group study", "One-on-one", "Flashcards & quizzes", "Practice problems", "Video lectures", "Other"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Study Buddy</title>
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
            <a href="messages.php">
                <span class="text">Messages</span>
                <?php if ($unreadTotal > 0): ?><span class="badge"><?php echo $unreadTotal; ?></span><?php endif; ?>
            </a>
            <a href="profile.php" class="active"><span class="text">Profile</span></a>
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
                <h1>My Profile</h1>
                <p>Edit your information and it will update on the dashboard for other students to see.</p>
            </section>

            <?php
            if (isset($_GET['status']) && $_GET['status'] == 'updated') {
                echo "<div class='msg ok' style='max-width:860px'>Your profile has been updated.</div>";
            }
            if (isset($_GET['error'])) {
                echo "<div class='msg err' style='max-width:860px'>" . htmlspecialchars($_GET['error']) . "</div>";
            }
            ?>

            <div class="page-card">

                <h1><?php echo htmlspecialchars($u['Name']); ?></h1>
                <p class="sub">Member since you registered · <?php echo htmlspecialchars($u['Email']); ?></p>

                <form action="update.php" method="post">

                    <div class="row">
                        <div class="field">
                            <label for="name">Full name</label>
                            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($u['Name']); ?>" required>
                        </div>
                        <div class="field">
                            <label for="age">Age</label>
                            <input type="number" name="age" id="age" min="15" max="100" value="<?php echo htmlspecialchars($u['Age']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="gender">Gender</label>
                            <select name="gender" id="gender" required>
                                <?php foreach (["Female", "Male", "Other"] as $g): ?>
                                    <option <?php if ($u['Gender'] === $g) echo 'selected'; ?>><?php echo $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($u['Phone']); ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($u['Email']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="course">Course you want a buddy for</label>
                            <select name="course" id="course" required>
                                <option value="">Select course</option>
                                <?php foreach ($courseOptions as $c): ?>
                                    <option <?php if ($u['Course'] === $c) echo 'selected'; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="studytime">Preferred study time</label>
                            <select name="studytime" id="studytime" required>
                                <option value="">Select time</option>
                                <?php foreach ($timeOptions as $t): ?>
                                    <option <?php if ($u['StudyTime'] === $t) echo 'selected'; ?>><?php echo $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label for="location">Preferred study location</label>
                        <select name="location" id="location" required>
                            <option value="">Select location</option>
                            <?php foreach ($locationOptions as $l): ?>
                                <option <?php if ($u['Location'] === $l) echo 'selected'; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="method">Preferred study method</label>
                            <select name="method" id="method" required>
                                <option value="">Select method</option>
                                <?php foreach ($methodOptions as $m): ?>
                                    <option <?php if ($u['Method'] === $m) echo 'selected'; ?>><?php echo htmlspecialchars($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="goals">Study goals</label>
                            <input type="text" name="goals" id="goals" placeholder="e.g. Pass the DSA final exam" value="<?php echo htmlspecialchars($u['Goals']); ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="desc">About you</label>
                        <textarea name="desc" id="desc" placeholder="Your department, year and subjects you want to study"><?php echo htmlspecialchars($u['Other']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="field">
                            <label for="newpassword">New password (leave empty to keep current)</label>
                            <input type="password" name="newpassword" id="newpassword" minlength="6">
                        </div>
                        <div class="field">
                            <label for="cnewpassword">Confirm new password</label>
                            <input type="password" name="cnewpassword" id="cnewpassword" minlength="6">
                        </div>
                    </div>

                    <button class="btn" type="submit">Save changes</button>

                </form>

                <div class="danger-zone">
                    <div>
                        <h3>Delete my account</h3>
                        <p>This removes your profile permanently and logs you out. It cannot be undone.</p>
                    </div>
                    <form action="delete.php" method="post" onsubmit="return confirm('Are you sure? Your account will be deleted permanently.');">
                        <button class="btn-danger" type="submit">Delete account</button>
                    </form>
                </div>

            </div>

        </main>
    </div>
</div>

</body>
</html>
