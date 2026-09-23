<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$initial = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard — Perch &amp; Pour Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<style>
    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: Georgia, 'Times New Roman', serif;
        color: var(--ink);
        background:
            repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(33,30,24,0.035) 39px, rgba(33,30,24,0.035) 40px),
            var(--paper);
        display: flex;
        min-height: 100vh;
        font-size: 15px;
        line-height: 1.5;
    }

    .main { flex: 1; min-width: 0; }

    .topbar {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 20px 32px;
        border-bottom: 1px solid var(--paper-line);
    }

    .menu-toggle {
        display: none;
        background: none;
        border: 1px solid var(--paper-line);
        width: 36px;
        height: 36px;
        border-radius: 4px;
        color: var(--ink);
        font-size: 15px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .topbar h1 {
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 24px;
        margin: 0;
    }

    .topbar .date-stamp {
        font-size: 12px;
        color: var(--ink-soft);
        margin-top: 2px;
    }

    .content { padding: 30px 32px 50px; max-width: 1100px; }

    .welcome-card {
        background: var(--card);
        border: 1px solid var(--paper-line);
        padding: 24px 28px;
        margin-bottom: 28px;
    }

    .welcome-card h2 {
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 21px;
        margin: 0 0 6px;
        color: var(--brown);
    }

    .welcome-card p { margin: 0; font-size: 14px; color: var(--ink-soft); }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 18px;
        margin-bottom: 28px;
    }

    .stat-card { background: var(--card); border: 1px solid var(--paper-line); padding: 16px 18px; }

    .stat-card .stat-label {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ink-soft);
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .stat-card .stat-label i { color: var(--brown); }

    .stat-card .stat-value {
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 28px;
        line-height: 1;
        color: var(--ink);
    }

    .section-title {
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 16px;
        margin: 0 0 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--paper-line);
    }

    .empty-note {
        border: 1px solid var(--paper-line);
        color: var(--ink-soft);
        font-size: 13px;
        padding: 26px;
        text-align: center;
    }

    @media (max-width: 820px) {
        .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
        .content { padding: 22px 18px 40px; }
        .topbar { padding: 16px 18px; }
    }
</style>
</head>
<body>

<?php include '../include/sidebar.php'; ?>

<div class="main">

    <div class="topbar">
        <button type="button" class="menu-toggle" onclick="openSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
            <h1>Dashboard</h1>
            <div class="date-stamp"><?php echo date("l, F j, Y"); ?></div>
        </div>
    </div>

    <div class="content">

        <div class="welcome-card">
            <h2>Welcome, <?php echo htmlspecialchars($fullName); ?></h2>
            <p>Naka-login ka bilang admin ng Perch &amp; Pour system.</p>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fa-solid fa-receipt"></i> Pending Orders</div>
                <div class="stat-value">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fa-solid fa-mug-hot"></i> In Preparation</div>
                <div class="stat-value">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fa-solid fa-users"></i> Staff Clocked In</div>
                <div class="stat-value">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Items</div>
                <div class="stat-value">0</div>
            </div>
        </div>

        <h3 class="section-title">Recent Activity</h3>
        <div class="empty-note">
            <i class="fa-solid fa-inbox"></i>
            No recent activity yet.
        </div>

    </div>
</div>

</body>
</html>