<?php
$fullName = $fullName ?? ($_SESSION['full_name'] ?? 'Admin');
$initial  = strtoupper(substr($fullName, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    :root {
        --paper: #EFEBE1;
        --paper-line: #D8D1C0;
        --card: #FBF9F3;
        --ink: #211E18;
        --ink-soft: #6E6656;
        --brown: #8A5A34;
        --press-red: #B0311D;
        --sidebar: #211E18;
        --sidebar-text: #D9D3C4;
        --sidebar-soft: #948B76;
    }

    .sidebar {
        width: 240px;
        flex-shrink: 0;
        background: var(--sidebar);
        color: var(--sidebar-text);
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        height: 100vh;
        z-index: 40;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 22px 20px;
        border-bottom: 1px solid rgba(217,211,196,0.15);
    }

    .sidebar-brand .logo-mark {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        overflow: hidden;
        border: 1px solid rgba(217,211,196,0.4);
        background: var(--paper);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .sidebar-brand .logo-mark img { width: 100%; height: 100%; object-fit: contain; }

    .sidebar-brand .brand-text strong {
        display: block;
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 17px;
        letter-spacing: 0.01em;
    }

    .sidebar-brand .brand-text span {
        font-size: 10px;
        color: var(--sidebar-soft);
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .sidebar-close {
        display: none;
        margin-left: auto;
        background: none;
        border: none;
        color: var(--sidebar-soft);
        font-size: 16px;
        cursor: pointer;
    }

    .sidebar-section-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--sidebar-soft);
        padding: 18px 20px 8px;
    }

    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding-bottom: 10px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 20px;
        color: var(--sidebar-text);
        text-decoration: none;
        font-size: 13.5px;
        font-family: Georgia, serif;
    }

    .sidebar-nav a i {
        width: 16px;
        text-align: center;
        font-size: 13px;
        color: var(--sidebar-soft);
    }

    .sidebar-nav a:hover { color: #ffffff; }
    .sidebar-nav a:hover i { color: #ffffff; }

    .sidebar-nav a.active { color: #D9A066; font-weight: 700; }
    .sidebar-nav a.active i { color: #D9A066; }

    .sidebar-account {
        border-top: 1px solid rgba(217,211,196,0.15);
        position: relative;
    }

    .sidebar-account-trigger {
        width: 100%;
        background: none;
        border: none;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-family: Georgia, serif;
        text-align: left;
    }

    .sidebar-account-trigger:hover { background: rgba(217,211,196,0.06); }

    .sidebar-account .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--brown);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .sidebar-account .info {
        line-height: 1.25;
        min-width: 0;
        flex: 1;
    }

    .sidebar-account .info strong {
        display: block;
        font-size: 13px;
        color: #fff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-account .info span {
        font-size: 10.5px;
        color: var(--sidebar-soft);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .sidebar-account .chevron {
        color: var(--sidebar-soft);
        font-size: 12px;
        transition: transform 0.15s ease;
        flex-shrink: 0;
    }

    .sidebar-account.open .chevron { transform: rotate(180deg); }

    .sidebar-account-menu {
        display: none;
        position: absolute;
        bottom: calc(100% + 8px);
        left: 20px;
        right: 20px;
        background: var(--card);
        border: 1px solid var(--paper-line);
        box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        z-index: 20;
    }

    .sidebar-account.open .sidebar-account-menu { display: block; }

    .sidebar-account-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--press-red);
        text-decoration: none;
        font-size: 13px;
        font-family: Georgia, serif;
        padding: 12px 16px;
    }

    .sidebar-account-menu a:hover { background: rgba(176,49,29,0.07); }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 30;
    }

    @media (max-width: 820px) {
        .sidebar {
            position: fixed;
            left: -240px;
            top: 0;
            height: 100vh;
            transition: left 0.2s ease;
        }
        .sidebar.open { left: 0; }
        .sidebar-close { display: block; }
        .sidebar-overlay.show { display: block; }
    }
</style>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-mark"><img src="../img/logo.png" alt="Perch & Pour"></div>
        <div class="brand-text">
            <strong>Perch &amp; Pour</strong>
            <span>Admin Panel</span>
        </div>
        <button type="button" class="sidebar-close" onclick="closeSidebar()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Overview</div>
        <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-gauge"></i> Dashboard</a>

        <div class="sidebar-section-label">Ordering &amp; Kitchen</div>
        <a href="orders.php" class="<?php echo $currentPage === 'orders.php' ? 'active' : ''; ?>"><i class="fa-solid fa-receipt"></i> Orders</a>
        <a href="kitchen.php" class="<?php echo $currentPage === 'kitchen.php' ? 'active' : ''; ?>"><i class="fa-solid fa-mug-hot"></i> Kitchen Display</a>
        <a href="tables.php" class="<?php echo $currentPage === 'tables.php' ? 'active' : ''; ?>"><i class="fa-solid fa-qrcode"></i> Tables &amp; QR</a>
        <a href="service_requests.php" class="<?php echo $currentPage === 'service_requests.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bell-concierge"></i> Service Requests</a>

        <div class="sidebar-section-label">Staff Management</div>
        <a href="attendance.php" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>"><i class="fa-solid fa-fingerprint"></i> Attendance / DTR</a>
        <a href="cleaning.php" class="<?php echo $currentPage === 'cleaning.php' ? 'active' : ''; ?>"><i class="fa-solid fa-broom"></i> Cleaning Assignments</a>
        <a href="staff.php" class="<?php echo $currentPage === 'staff.php' ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i> Staff</a>

        <div class="sidebar-section-label">Store &amp; Inventory</div>
        <a href="menu.php" class="<?php echo $currentPage === 'menu.php' ? 'active' : ''; ?>"><i class="fa-solid fa-book-open"></i> Menu Items</a>
        <a href="inventory.php" class="<?php echo $currentPage === 'inventory.php' ? 'active' : ''; ?>"><i class="fa-solid fa-boxes-stacked"></i> Inventory</a>
        <a href="receipts.php" class="<?php echo $currentPage === 'receipts.php' ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice"></i> Receipts</a>

        <div class="sidebar-section-label">Customer Engagement</div>
        <a href="feedback.php" class="<?php echo $currentPage === 'feedback.php' ? 'active' : ''; ?>"><i class="fa-solid fa-comment-dots"></i> Feedback</a>
        <a href="chatbot.php" class="<?php echo $currentPage === 'chatbot.php' ? 'active' : ''; ?>"><i class="fa-solid fa-robot"></i> Chatbot Logs</a>

        <div class="sidebar-section-label">Reports</div>
        <a href="reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-line"></i> Reports &amp; Analytics</a>
    </nav>

    <div class="sidebar-account" id="sidebarAccount">
        <button type="button" class="sidebar-account-trigger" onclick="document.getElementById('sidebarAccount').classList.toggle('open')">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="info">
                <strong><?php echo htmlspecialchars($fullName); ?></strong>
                <span>Administrator</span>
            </div>
            <i class="fa-solid fa-chevron-down chevron"></i>
        </button>
        <div class="sidebar-account-menu">
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
</aside>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
</script>