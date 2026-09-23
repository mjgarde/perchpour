<?php
session_start();
include 'config/db_connect.php';

$tableToken = trim($_GET['table'] ?? '');

if ($tableToken === '') {
    header('Location: qrcode.php');
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT * FROM tables WHERE qr_code = ?");
mysqli_stmt_bind_param($stmt, "s", $tableToken);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$tableInfo = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$menuByCategory = [];

if ($tableInfo) {
    $res = mysqli_query($conn, "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY category ASC, item_name ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        $menuByCategory[$row['category']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menu — Perch & Pour</title>
<link rel="stylesheet" href="vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="vendor/fontawesome-free-7.3.1/css/all.min.css">
<style>
    :root {
        --paper:   #faf9f6;
        --ink:     #1a1a1a;
        --ink-2:   #6b6b6b;
        --ink-3:   #a8a8a8;
        --line:    #e8e6e1;
        --accent:  #8b5a2b;
    }

    * { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }

    html, body {
        background: var(--paper);
        color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 15px;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }

    h1, h2, h3 { font-family: Georgia, 'Times New Roman', serif; }

    .cust-topbar {
        background: #fff;
        border-bottom: 1px solid var(--line);
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        position: sticky;
        top: 0;
        z-index: 40;
    }
    .cust-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: var(--ink);
    }
    .cust-brand .mark {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--ink);
        color: var(--paper);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .cust-brand .name {
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 17px;
        line-height: 1.1;
    }
    .cust-brand .name small {
        display: block;
        font-family: -apple-system, sans-serif;
        font-size: 10.5px;
        color: var(--ink-2);
        font-weight: 400;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .table-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--ink);
        color: var(--paper);
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12.5px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .table-chip i { font-size: 11px; }

    .cat-nav {
        position: sticky;
        top: 63px;
        background: var(--paper);
        z-index: 30;
        border-bottom: 1px solid var(--line);
        overflow-x: auto;
        white-space: nowrap;
        padding: 12px 18px;
        scrollbar-width: none;
    }
    .cat-nav::-webkit-scrollbar { display: none; }
    .cat-nav a {
        display: inline-block;
        padding: 8px 16px;
        margin-right: 8px;
        border-radius: 20px;
        background: #fff;
        border: 1px solid var(--line);
        color: var(--ink-2);
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.15s;
    }
    .cat-nav a:hover,
    .cat-nav a.active {
        background: var(--ink);
        color: var(--paper);
        border-color: var(--ink);
    }

    .menu-section { padding: 32px 18px 120px; max-width: 720px; margin: 0 auto; }

    .cat-heading {
        font-family: Georgia, serif;
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 4px;
        letter-spacing: -0.02em;
    }
    .cat-sub {
        font-size: 12.5px;
        color: var(--ink-3);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 22px;
    }
    .cat-block { margin-bottom: 42px; }

    .menu-item {
        display: flex;
        gap: 14px;
        padding: 16px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        margin-bottom: 12px;
        transition: box-shadow 0.15s, transform 0.15s;
        cursor: pointer;
    }
    .menu-item:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transform: translateY(-1px);
    }

    .menu-thumb {
        width: 78px;
        height: 78px;
        border-radius: 10px;
        background: var(--paper);
        border: 1px solid var(--line);
        flex-shrink: 0;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--ink-3);
        font-size: 24px;
    }
    .menu-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .menu-body { flex: 1; min-width: 0; }
    .menu-body .name {
        font-family: Georgia, serif;
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 4px;
        line-height: 1.25;
    }
    .menu-body .price {
        font-size: 15px;
        font-weight: 600;
        color: var(--accent);
        margin-bottom: 6px;
    }
    .menu-body .tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .tag-chip {
        font-size: 10.5px;
        padding: 2px 7px;
        border-radius: 10px;
        background: #f5f3ee;
        color: var(--ink-2);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .menu-action {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--ink);
        color: var(--paper);
        flex-shrink: 0;
        align-self: center;
        font-size: 13px;
    }

    .floating-bar {
        position: fixed;
        bottom: 20px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        gap: 10px;
        padding: 0 20px;
        z-index: 50;
        pointer-events: none;
    }
    .floating-bar > * { pointer-events: auto; }

    .float-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--ink);
        color: var(--paper);
        border: none;
        border-radius: 28px;
        padding: 13px 22px;
        font-size: 13.5px;
        font-weight: 600;
        box-shadow: 0 8px 28px rgba(0,0,0,0.18);
        cursor: pointer;
        transition: transform 0.15s, background 0.15s;
    }
    .float-btn:hover { transform: translateY(-2px); background: #333; color: var(--paper); }
    .float-btn.gold { background: var(--accent); }
    .float-btn.gold:hover { background: #6d4522; }

    .error-state {
        text-align: center;
        padding: 80px 24px;
    }
    .error-state i {
        font-size: 40px;
        color: var(--ink-3);
        margin-bottom: 20px;
        display: block;
    }
    .error-state h2 {
        font-size: 20px;
        margin: 0 0 8px;
    }
    .error-state p {
        color: var(--ink-2);
        font-size: 14px;
        max-width: 300px;
        margin: 0 auto 24px;
    }

    .btn-back {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--ink);
        color: var(--paper);
        border: none;
        border-radius: 24px;
        padding: 11px 22px;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.15s;
    }
    .btn-back:hover { background: #333; color: #fff; }
</style>
</head>
<body>

<?php if (!$tableInfo): ?>

    <div class="error-state">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2>Invalid table QR</h2>
        <p>The QR code you scanned is not recognized. Please ask a staff member for help.</p>
        <a href="qrcode.php" class="btn-back">
            <i class="fa-solid fa-qrcode"></i>
            Scan Again
        </a>
    </div>

<?php else: ?>

    <div class="cust-topbar">
        <div class="cust-brand">
            <div class="mark"><i class="fa-solid fa-mug-hot"></i></div>
            <div class="name">
                Perch &amp; Pour
                <small>Dine-in Menu</small>
            </div>
        </div>
        <div class="table-chip">
            <i class="fa-solid fa-chair"></i>
            Table <?= htmlspecialchars($tableInfo['table_number']) ?>
        </div>
    </div>

    <?php if (empty($menuByCategory)): ?>

        <div class="error-state">
            <i class="fa-solid fa-mug-hot"></i>
            <h2>Menu not ready</h2>
            <p>Our menu is not available at the moment. Please call a staff member for assistance.</p>
        </div>

    <?php else: ?>

        <?php $categories = array_keys($menuByCategory); ?>

        <nav class="cat-nav" id="catNav">
            <?php foreach ($categories as $i => $cat): ?>
                <a href="#cat-<?= $i ?>"
                   class="<?= $i === 0 ? 'active' : '' ?>"
                   onclick="setActive(this)">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="menu-section">
            <?php foreach ($categories as $i => $cat): ?>
                <div class="cat-block" id="cat-<?= $i ?>">
                    <h2 class="cat-heading"><?= htmlspecialchars($cat) ?></h2>
                    <div class="cat-sub"><?= count($menuByCategory[$cat]) ?> item<?= count($menuByCategory[$cat]) === 1 ? '' : 's' ?></div>

                    <?php foreach ($menuByCategory[$cat] as $item): ?>
                        <div class="menu-item">
                            <div class="menu-thumb">
                                <?php if (!empty($item['image']) && file_exists($item['image'])): ?>
                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>">
                                <?php else: ?>
                                    <i class="fa-solid fa-mug-hot"></i>
                                <?php endif; ?>
                            </div>
                            <div class="menu-body">
                                <div class="name"><?= htmlspecialchars($item['item_name']) ?></div>
                                <div class="price">₱<?= number_format($item['price'], 2) ?></div>
                                <div class="tags">
                                    <?php if ($item['has_size']): ?><span class="tag-chip">Size</span><?php endif; ?>
                                    <?php if ($item['has_sweetness']): ?><span class="tag-chip">Sweetness</span><?php endif; ?>
                                    <?php if ($item['has_milk_type']): ?><span class="tag-chip">Milk</span><?php endif; ?>
                                    <?php if ($item['has_addons']): ?><span class="tag-chip">Add-ons</span><?php endif; ?>
                                </div>
                            </div>
                            <div class="menu-action">
                                <i class="fa-solid fa-plus"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="floating-bar">
            <button type="button" class="float-btn gold" onclick="alert('Request Assistance — coming soon!')">
                <i class="fa-solid fa-bell-concierge"></i>
                Request Assistance
            </button>
        </div>

    <?php endif; ?>

<?php endif; ?>

<script src="vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
    function setActive(el) {
        document.querySelectorAll('#catNav a').forEach(function (a) {
            a.classList.remove('active');
        });
        el.classList.add('active');
    }
</script>

</body>
</html>