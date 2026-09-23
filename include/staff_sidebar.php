<?php
$fullName    = $fullName ?? ($_SESSION['full_name'] ?? 'Staff');
$initial     = strtoupper(substr($fullName, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);

$navGroups = [
    'Overview' => [
        ['dashboard.php', 'fa-solid fa-gauge', 'Dashboard'],
    ],
    'Daily Operations' => [
        ['kitchen.php', 'fa-solid fa-mug-hot', 'Kitchen Display'],
        ['service_requests.php', 'fa-solid fa-bell-concierge', 'Service Requests'],
        ['cleaning.php', 'fa-solid fa-broom', 'Cleaning Tasks'],
    ],
    'My Records' => [
        ['attendance.php', 'fa-solid fa-fingerprint', 'Attendance / DTR'],
    ],
];
?>

<style>
    :root {
        --sb-bg: #fbfbfa;
        --sb-text: #1d1d1f;
        --sb-muted: #86868b;
        --sb-hover: rgba(0, 0, 0, 0.035);
        --sb-active-bg: #1d1d1f;
        --sb-active-text: #ffffff;
        --sb-border: #e8e8e6;
        --sb-accent: #0071e3;
    }

    .sidebar {
        width: 248px;
        flex-shrink: 0;
        background: var(--sb-bg);
        border-right: 1px solid var(--sb-border);
        display: flex;
        flex-direction: column;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif;
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 20px 20px 18px;
    }

    .sidebar-brand .logo-mark {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .sidebar-brand .logo-mark img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sidebar-brand .brand-text {
        line-height: 1.25;
    }

    .sidebar-brand .brand-text strong {
        display: block;
        font-size: 13.5px;
        font-weight: 600;
        letter-spacing: -0.01em;
        color: var(--sb-text);
    }

    .sidebar-brand .brand-text span {
        font-size: 10.5px;
        font-weight: 500;
        letter-spacing: 0.01em;
        color: var(--sb-muted);
    }

    .sidebar-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 4px 12px 12px;
    }

    .sidebar-scroll::-webkit-scrollbar { width: 6px; }
    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: rgba(0,0,0,0.12);
        border-radius: 10px;
    }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }

    .sidebar-section-title {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
        color: var(--sb-muted);
        padding: 16px 10px 6px;
    }

    .sidebar-section:first-child .sidebar-section-title {
        padding-top: 8px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 7px 10px;
        border-radius: 7px;
        color: var(--sb-text);
        text-decoration: none;
        font-size: 13.5px;
        font-weight: 450;
        letter-spacing: -0.005em;
        line-height: 1.3;
        transition: background 0.15s ease, color 0.15s ease;
        margin-bottom: 1px;
    }

    .sidebar-link:hover {
        background: var(--sb-hover);
        color: var(--sb-text);
    }

    .sidebar-link.active {
        background: var(--sb-hover);
        color: var(--sb-text);
        font-weight: 600;
    }

    .sidebar-link.active i { color: var(--sb-text); }

    .sidebar-link i {
        width: 16px;
        text-align: center;
        font-size: 13.5px;
        color: var(--sb-muted);
        transition: color 0.15s ease;
    }

    .sidebar-link:hover i { color: var(--sb-text); }

    .sidebar-footer {
        border-top: 1px solid var(--sb-border);
        padding: 10px 12px;
    }

    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        background: none;
        border: none;
        padding: 7px 8px;
        border-radius: 8px;
        text-align: left;
        cursor: pointer;
    }

    .sidebar-user:hover { background: var(--sb-hover); }

    .sidebar-user .avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--sb-active-bg);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
        flex-shrink: 0;
    }

    .sidebar-user .user-info {
        flex: 1;
        min-width: 0;
        line-height: 1.25;
    }

    .sidebar-user .user-info strong {
        display: block;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--sb-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-user .user-info span {
        font-size: 10.5px;
        color: var(--sb-muted);
    }

    .sidebar-user i.fa-chevron-up {
        font-size: 10px;
        color: var(--sb-muted);
    }

    @media (max-width: 991.98px) {
        .sidebar { display: none; }
    }

    .offcanvas.sidebar-drawer {
        width: 272px;
        background: var(--sb-bg);
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif;
    }

    .offcanvas.sidebar-drawer .offcanvas-body {
        padding: 0 12px 12px;
    }
</style>

<div class="offcanvas offcanvas-start sidebar-drawer" tabindex="-1" id="sidebarDrawer" aria-labelledby="sidebarDrawerLabel">
    <div class="offcanvas-header border-bottom" style="border-color: var(--sb-border) !important; padding: 16px 20px;">
        <div class="d-flex align-items-center gap-2" id="sidebarDrawerLabel">
            <div class="logo-mark d-flex align-items-center justify-content-center"
                 style="width:30px;height:30px;border-radius:7px;overflow:hidden;">
                <img src="../img/logo.png" alt="Perch & Pour" style="width:100%;height:100%;object-fit:cover;">
            </div>
            <div class="brand-text lh-sm">
                <strong class="d-block" style="font-size:13.5px;font-weight:600;">Perch &amp; Pour</strong>
                <span class="text-secondary" style="font-size:10.5px;">Staff Panel</span>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <div class="sidebar-scroll flex-grow-1">
            <?php foreach ($navGroups as $groupLabel => $links): ?>
                <div class="sidebar-section">
                    <div class="sidebar-section-title"><?= htmlspecialchars($groupLabel) ?></div>
                    <?php foreach ($links as [$href, $icon, $label]):
                        $isActive = ($currentPage === $href); ?>
                        <a href="<?= htmlspecialchars($href) ?>"
                           class="sidebar-link <?= $isActive ? 'active' : '' ?>">
                            <i class="<?= htmlspecialchars($icon) ?>"></i>
                            <span><?= htmlspecialchars($label) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-link text-danger">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<aside class="sidebar d-none d-lg-flex">
    <div class="sidebar-brand">
        <div class="logo-mark"><img src="../img/logo.png" alt="Perch & Pour"></div>
        <div class="brand-text">
            <strong>Perch &amp; Pour</strong>
            <span>Staff Panel</span>
        </div>
    </div>

    <div class="sidebar-scroll">
        <?php foreach ($navGroups as $groupLabel => $links): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-title"><?= htmlspecialchars($groupLabel) ?></div>
                <?php foreach ($links as [$href, $icon, $label]):
                    $isActive = ($currentPage === $href); ?>
                    <a href="<?= htmlspecialchars($href) ?>"
                       class="sidebar-link <?= $isActive ? 'active' : '' ?>">
                        <i class="<?= htmlspecialchars($icon) ?>"></i>
                        <span><?= htmlspecialchars($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
        <div class="dropdown dropup">
            <button type="button" class="sidebar-user" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($fullName) ?></strong>
                    <span>Staff</span>
                </div>
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <ul class="dropdown-menu w-100 shadow-sm" style="border-color: var(--sb-border); font-size: 13px;">
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="logout.php">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</aside>

<script>
    function openSidebar() {
        const el = document.getElementById('sidebarDrawer');
        bootstrap.Offcanvas.getOrCreateInstance(el).show();
    }
    function closeSidebar() {
        const el = document.getElementById('sidebarDrawer');
        bootstrap.Offcanvas.getOrCreateInstance(el).hide();
    }
</script>