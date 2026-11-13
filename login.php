<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Study Buddy Finder</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

<img class="bg" src="bg.jpg" alt="Students studying in a university library">

<div class="card">

    <div class="brand">
        <div class="logo">SB</div>
        <span>Study Buddy </span>
    </div>

    <h1>Welcome back</h1>
    <p class="sub">Log in to see your study buddies and requests.</p>

    <?php
    if (isset($_GET['status']) && $_GET['status'] == 'registered') {
        echo "<div class='msg ok'>Registration complete. Please log in.</div>";
    }
    if (isset($_GET['status']) && $_GET['status'] == 'loggedout') {
        echo "<div class='msg ok'>You have been logged out.</div>";
    }
    if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
        echo "<div class='msg ok'>Your account has been deleted.</div>";
    }
    if (isset($_GET['error'])) {
        echo "<div class='msg err'>" . htmlspecialchars($_GET['error']) . "</div>";
    }
    ?>

    <form action="auth.php" method="post">

        <div class="field">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" name="password" id="password"  required>
        </div>

        <button class="btn" type="submit">Log in</button>

    </form>

    <p class="alt">New here? <a href="index.php">Create an account</a></p>

</div>

</body>
</html>
