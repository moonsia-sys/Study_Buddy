<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Study Buddy Finder</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

<img class="bg" src="bg.jpg" alt="Students studying in a university library">

<div class="card wide">

    <div class="brand">
        <div class="logo">SB</div>
        <span>Study Buddy</span>
    </div>

    <h1>Create your account</h1>
    <p class="sub">Fill in your details and find the perfect study partner.</p>

    <?php
    if (isset($_GET['status']) && $_GET['status'] == 'success') {
        echo "<div class='msg ok'>Registration complete! You can log in now.</div>";
    }
    if (isset($_GET['error'])) {
        echo "<div class='msg err'>" . htmlspecialchars($_GET['error']) . "</div>";
    }
    ?>

    <form action="process.php" method="post">

        <div class="field">
            <label for="name">Full name</label>
            <input type="text" name="name" id="name" required>
        </div>

        <div class="row">
            <div class="field">
                <label for="age">Age</label>
                <input type="number" name="age" id="age" min="15" max="100" required>
            </div>
            <div class="field">
                <label for="gender">Gender</label>
                <select name="gender" id="gender" required>
                    <option value="">Select</option>
                    <option>Female</option>
                    <option>Male</option>
                    <option>Other</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="field">
            <label for="phone">Phone</label>
            <input type="tel" name="phone" id="phone" required>
        </div>

        <div class="row">
            <div class="field">
                <label for="course">Course you want a buddy for</label>
                <select name="course" id="course" required>
                    <option value="">Select course</option>
                    <option>DSA</option>
                    <option>DBMS</option>
                    <option>C++</option>
                    <option>OOP</option>
                    <option>Web Programming</option>
                    <option>Digital Logic</option>
                    <option>Mathematics</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="field">
                <label for="studytime">Preferred study time</label>
                <select name="studytime" id="studytime" required>
                    <option value="">Select time</option>
                    <option>Morning</option>
                    <option>Afternoon</option>
                    <option>Evening</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="location">Preferred study location</label>
            <select name="location" id="location" required>
                <option value="">Select location</option>
                <option>Library</option>
                <option>Online</option>
                <option>Campus Cafe</option>
                <option>Dept. Lab</option>
                <option>Other</option>
            </select>
        </div>

        <div class="row">
            <div class="field">
                <label for="method">Preferred study method</label>
                <select name="method" id="method" required>
                    <option value="">Select method</option>
                    <option>Group study</option>
                    <option>One-on-one</option>
                    <option>Flashcards &amp; quizzes</option>
                    <option>Practice problems</option>
                    <option>Video lectures</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="field">
                <label for="goals">Study goals</label>
                <input type="text" name="goals" id="goals" placeholder="e.g. Pass the DSA final exam">
            </div>
        </div>

        <div class="row">
            <div class="field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" minlength="6" required>
            </div>
            <div class="field">
                <label for="cpassword">Confirm password</label>
                <input type="password" name="cpassword" id="cpassword" minlength="6" required>
            </div>
        </div>

        <div class="field">
            <label for="desc">About you</label>
            <textarea name="desc" id="desc" placeholder="Your department, year and subjects you want to study"></textarea>
        </div>

        <div class="btn-row">
            <button class="btn" type="submit">Register</button>
        </div>

    </form>

    <p class="alt">Already registered? <a href="login.php">Log in here</a></p>

</div>

</body>
</html>
