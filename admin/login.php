<?php
session_start();
include '../config/db_connect.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM admins WHERE username = '$username'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['full_name'] = $row['full_name'];

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "Username not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login — Perch &amp; Pour</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<style>
    * { box-sizing: border-box; }

    :root {
        --paper: #EFEBE1;
        --paper-line: #D8D1C0;
        --card: #FBF9F3;
        --ink: #211E18;
        --ink-soft: #6E6656;
        --press-red: #B0311D;
    }

    body {
        margin: 0;
        min-height: 100vh;
        background:
            repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(33,30,24,0.035) 39px, rgba(33,30,24,0.035) 40px),
            var(--paper);
        font-family: 'Courier New', Consolas, monospace;
        color: var(--ink);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 30px 16px;
    }

    .login-card {
        width: 100%;
        max-width: 380px;
        background: var(--card);
        border: 1px solid var(--ink);
        box-shadow: 4px 4px 0 rgba(33,30,24,0.14);
        padding: 34px 30px 28px;
    }

    .login-logo {
        width: 66px;
        height: 66px;
        margin: 0 auto 14px;
        border: 1px solid var(--ink);
        border-radius: 50%;
        overflow: hidden;
        background: var(--paper);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .login-logo img { width: 100%; height: 100%; object-fit: contain; }

    .login-card h1 {
        font-family: 'Arial Narrow', Arial, sans-serif;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 30px;
        text-align: center;
        letter-spacing: 0.01em;
        margin: 0 0 4px;
        line-height: 1;
    }

    .login-sub {
        text-align: center;
        font-size: 11.5px;
        color: var(--ink-soft);
        margin: 0 0 22px;
        letter-spacing: 0.02em;
    }

    .divider {
        border-top: 1px dashed var(--paper-line);
        margin: 18px 0;
    }

    .alert-error {
        border: 1px solid var(--press-red);
        color: var(--press-red);
        font-size: 12px;
        text-align: center;
        padding: 9px 10px;
        margin-bottom: 18px;
        background: #F7E7E3;
    }

    .field { margin-bottom: 18px; }

    .field label {
        display: block;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ink-soft);
        font-weight: 600;
        margin-bottom: 6px;
    }

    .field-input {
        display: flex;
        align-items: center;
        border-bottom: 1.5px solid var(--ink);
        padding-bottom: 6px;
    }

    .field-input i {
        color: var(--ink-soft);
        font-size: 13px;
        width: 20px;
    }

    .field-input input {
        border: none;
        background: transparent;
        outline: none;
        flex: 1;
        font-family: 'Courier New', Consolas, monospace;
        font-size: 14px;
        color: var(--ink);
        padding: 4px 2px;
    }

    .field-input input::placeholder {
        color: #A89578;
    }

    .field-input:focus-within {
        border-bottom-color: var(--press-red);
    }

    .btn-press {
        width: 100%;
        font-family: 'Arial Narrow', Arial, sans-serif;
        font-weight: 700;
        font-size: 16px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background: var(--ink);
        color: var(--card);
        border: none;
        padding: 12px;
        cursor: pointer;
        margin-top: 6px;
        transition: background 0.15s ease;
    }

    .btn-press:hover {
        background: var(--press-red);
    }
</style>
</head>
<body>

<div class="login-card">

    <div class="login-logo">
        <img src="../img/logo.png" alt="Perch & Pour Logo">
    </div>

    <h1>Perch &amp; Pour</h1>
    <p class="login-sub">Admin Panel</p>

    <div class="divider"></div>

    <?php if (!empty($error)) : ?>
        <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="field">
            <label for="username">Username</label>
            <div class="field-input">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username" name="username" placeholder="username" required autofocus>
            </div>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <div class="field-input">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn-press">Log In</button>
    </form>

</div>

</body>
</html>