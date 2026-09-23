<?php
session_start();
include 'config/db_connect.php';

$tableToken = trim($_GET['table'] ?? '');
$tableInfo  = null;
$menuByCategory = [];

if ($tableToken !== '') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM tables WHERE qr_code = ?");
    mysqli_stmt_bind_param($stmt, "s", $tableToken);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tableInfo = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if ($tableInfo) {
    $res = mysqli_query($conn, "SELECT * FROM menu_items WHERE is_available = 1 ORDER BY category ASC, item_name ASC");
    while ($row = mysqli_fetch_assoc($res)) {
        $menuByCategory[$row['category']][] = $row;
    }
}

function resolveProductImage($raw) {
    if (empty($raw)) return null;

    $raw = ltrim(str_replace('\\', '/', $raw), '/');

    $candidates = [];
    if (preg_match('#^uploads/#i', $raw)) {
        $candidates[] = $raw;
    }

    $filename = basename($raw);
    $candidates[] = 'uploads/menu/' . $filename;
    $candidates[] = 'uploads/cleaning/' . $filename;
    $candidates[] = 'uploads/' . $filename;

    foreach ($candidates as $relative) {
        if (file_exists(__DIR__ . '/' . $relative)) {
            return $relative;
        }
    }
    return null;
}

$sizeOptions = [
    ['label' => 'Small',  'price' => 0],
    ['label' => 'Medium', 'price' => 20],
    ['label' => 'Large',  'price' => 40],
];
$sweetnessOptions = [
    ['label' => '0%',   'price' => 0],
    ['label' => '25%',  'price' => 0],
    ['label' => '50%',  'price' => 0],
    ['label' => '75%',  'price' => 0],
    ['label' => '100%', 'price' => 0],
];
$milkOptions = [
    ['label' => 'Regular Milk', 'price' => 0],
    ['label' => 'Fresh Milk',   'price' => 20],
    ['label' => 'Oat Milk',     'price' => 30],
    ['label' => 'Almond Milk',  'price' => 30],
];
$addonOptions = [
    ['label' => 'Extra Shot',    'price' => 30],
    ['label' => 'Vanilla Syrup', 'price' => 20],
    ['label' => 'Caramel Syrup', 'price' => 20],
    ['label' => 'Whipped Cream', 'price' => 15],
    ['label' => 'Extra Ice',     'price' => 0],
];

$serviceTypes = [
    ['id' => 'call_staff', 'label' => 'Call a Staff', 'icon' => 'fa-user'],
    ['id' => 'water',      'label' => 'Request Water', 'icon' => 'fa-glass-water'],
    ['id' => 'bill',       'label' => 'Ask for the Bill', 'icon' => 'fa-receipt'],
    ['id' => 'other',      'label' => 'Other Request', 'icon' => 'fa-ellipsis'],
];

$menuItemsFlat = [];
foreach ($menuByCategory as $cat => $items) {
    foreach ($items as $item) {
        $imgPath = resolveProductImage($item['image'] ?? null);
        $menuItemsFlat[] = [
            'id'            => (int) ($item['id'] ?? 0),
            'name'          => $item['item_name'],
            'price'         => (float) $item['price'],
            'image'         => $imgPath,
            'category'      => $cat,
            'has_size'      => (bool) $item['has_size'],
            'has_sweetness' => (bool) $item['has_sweetness'],
            'has_milk_type' => (bool) $item['has_milk_type'],
            'has_addons'    => (bool) $item['has_addons'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $tableInfo ? 'Order — Perch & Pour' : 'Scan — Perch & Pour' ?></title>
<link rel="stylesheet" href="vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="html5-qrcode.min.js"></script>
<style>
    :root {
        --paper: #EFEBE1;
        --paper-2: #E2DCCB;
        --surface: #FBF9F3;
        --paper-line: #D8D1C0;
        --ink: #211E18;
        --ink-soft: #6E6656;
        --ink-3: #A9A297;
        --press-red: #B0311D;
        --gold: #B8860B;
    }

    * { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; box-sizing: border-box; }

    html, body {
        background:
            repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(33,30,24,0.035) 39px, rgba(33,30,24,0.035) 40px),
            var(--paper);
        color: var(--ink);
        font-family: 'IBM Plex Mono', monospace;
        font-size: 14px;
        margin: 0;
        padding: 0;
        min-height: 100vh;
    }
    h1, h2, h3, .display {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: -0.01em;
        line-height: 0.95;
    }
    button { font-family: 'IBM Plex Mono', monospace; }

    .cust-topbar {
        background: var(--surface);
        border-bottom: 2px solid var(--ink);
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        position: sticky;
        top: 0;
        z-index: 40;
    }
    .cust-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--ink); }
    .cust-brand .mark {
        width: 42px; height: 42px; overflow: hidden;
        border: 1px solid var(--ink);
        background: var(--paper-2);
        display: flex; align-items: center; justify-content: center;
        color: var(--ink); font-size: 17px; flex-shrink: 0;
    }
    .cust-brand .mark img { width: 100%; height: 100%; object-fit: contain; display: block; }
    .cust-brand .name {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 800; font-size: 22px; line-height: 0.95;
        text-transform: uppercase; letter-spacing: 0.01em;
    }
    .cust-brand .name small {
        display: block; font-family: 'IBM Plex Mono', monospace; font-size: 10px;
        color: var(--ink-soft); font-weight: 500;
        text-transform: uppercase; letter-spacing: 0.1em; margin-top: 3px;
    }
    .topbar-right { display: flex; align-items: center; gap: 10px; }
    .table-chip {
        display: inline-flex; align-items: center; gap: 7px;
        background: var(--ink); color: var(--paper);
        padding: 7px 14px; font-size: 12px;
        font-family: 'IBM Plex Mono', monospace; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em;
        border: 1px solid var(--ink);
    }
    .cart-icon-btn {
        position: relative; width: 42px; height: 42px;
        border: 1px solid var(--ink); background: var(--surface);
        color: var(--ink); display: flex; align-items: center; justify-content: center;
        font-size: 16px; cursor: pointer;
    }
    .cart-icon-btn:hover { background: var(--paper-2); }
    .cart-icon-btn .badge {
        position: absolute; top: -8px; right: -8px; min-width: 20px; height: 20px; padding: 0 5px;
        border: 1px solid var(--ink);
        background: var(--press-red); color: #fff;
        font-size: 10px; font-family: 'IBM Plex Mono', monospace; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }

    .masthead-band {
        display: flex; justify-content: space-between; align-items: center;
        padding: 8px 20px;
        background: var(--ink); color: var(--paper);
        font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase;
        font-family: 'IBM Plex Mono', monospace;
    }
    .masthead-band .dots { display: flex; gap: 6px; }
    .masthead-band .dots span { width: 7px; height: 7px; border-radius: 50%; background: var(--paper); }

    .hero { text-align: center; padding: 64px 24px 40px; }
    .hero .icon-big {
        width: 96px; height: 96px; margin: 0 auto 26px;
        border: 2px solid var(--ink); background: var(--surface);
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; font-size: 32px; color: var(--ink);
    }
    .hero .icon-big img { width: 62%; height: 62%; object-fit: contain; }
    .hero h1 { font-size: 46px; margin: 0 0 14px; }
    .hero .lede { color: var(--ink-soft); font-size: 13px; max-width: 340px; margin: 0 auto 34px; line-height: 1.65; }
    .btn-primary-cust {
        display: inline-flex; align-items: center; gap: 10px;
        background: var(--ink); color: var(--paper);
        border: 1px solid var(--ink);
        padding: 14px 30px; font-size: 13px; font-weight: 600;
        cursor: pointer; text-decoration: none;
        text-transform: uppercase; letter-spacing: 0.1em;
    }
    .btn-primary-cust:hover { background: #3a3529; color: var(--paper); }
    .btn-link-cust {
        display: inline-flex; align-items: center; gap: 6px;
        color: var(--ink-soft); font-size: 11.5px; text-decoration: none;
        margin-top: 22px; text-transform: uppercase; letter-spacing: 0.08em;
    }
    .btn-link-cust:hover { color: var(--press-red); }
    .scanner-wrap { padding: 24px; max-width: 460px; margin: 0 auto; }
    #qr-reader { width: 100%; border: 2px solid var(--ink); background: #000; min-height: 240px; overflow: hidden; }
    #qr-reader img { display: none; }
    .scan-hint {
        text-align: center; font-size: 11.5px; color: var(--ink-soft);
        margin-top: 18px; line-height: 1.6;
        text-transform: uppercase; letter-spacing: 0.08em;
    }

    .cat-nav {
        position: sticky; top: 71px;
        background: var(--paper);
        z-index: 30; border-bottom: 1px solid var(--paper-line);
        overflow-x: auto; white-space: nowrap;
        padding: 14px 20px 0; scrollbar-width: none;
        display: flex; gap: 22px;
    }
    .cat-nav::-webkit-scrollbar { display: none; }
    .cat-nav a {
        display: inline-block;
        padding: 8px 0;
        border-bottom: 3px solid transparent;
        color: var(--ink-soft);
        font-family: 'IBM Plex Mono', monospace;
        font-size: 12px; font-weight: 600;
        text-decoration: none;
        text-transform: uppercase; letter-spacing: 0.06em;
        transition: color 0.15s, border-color 0.15s;
    }
    .cat-nav a:hover, .cat-nav a.active {
        color: var(--ink);
        border-bottom-color: var(--press-red);
    }

    .menu-section { padding: 30px 20px 160px; max-width: 980px; margin: 0 auto; }
    .section-head {
        display: flex; justify-content: space-between; align-items: baseline;
        margin: 0 0 20px;
        border-bottom: 2px solid var(--ink);
        padding-bottom: 10px;
    }
    .section-head h2 { font-size: 30px; margin: 0; }
    .section-head .meta {
        font-size: 10.5px; color: var(--ink-soft);
        text-transform: uppercase; letter-spacing: 0.1em;
    }
    .cat-block { margin-bottom: 48px; }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }
    @media (min-width: 560px) { .menu-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (min-width: 860px) { .menu-grid { grid-template-columns: repeat(4, 1fr); } }

    .menu-card {
        background: var(--surface);
        border: 1px solid var(--ink);
        overflow: hidden;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    .menu-card:hover { background: #f3efe4; }
    .menu-card .thumb {
        aspect-ratio: 1 / 1;
        width: 100%;
        background: var(--paper-2);
        border-bottom: 1px dashed var(--ink);
        display: flex; align-items: center; justify-content: center;
        color: var(--ink-soft); font-size: 28px;
        position: relative;
        overflow: hidden;
    }
    .menu-card .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; filter: saturate(0.9) contrast(1.03); }
    .menu-card .add-fab {
        position: absolute; bottom: 8px; right: 8px;
        width: 32px; height: 32px;
        background: var(--ink); color: var(--paper);
        border: 1px solid var(--ink);
        display: flex; align-items: center; justify-content: center;
        font-size: 13px;
    }
    .menu-card .info { padding: 12px 13px 14px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
    .menu-card .name {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 17px; font-weight: 700;
        line-height: 1.1;
        min-height: 2.2em;
        text-transform: uppercase;
        letter-spacing: 0.01em;
    }
    .menu-card .price {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 13px; font-weight: 700;
        color: var(--press-red);
        letter-spacing: 0.02em;
    }
    .menu-card .tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
    .tag-chip {
        font-family: 'IBM Plex Mono', monospace;
        font-size: 9px;
        padding: 2px 6px;
        background: var(--paper-2);
        color: var(--ink-soft);
        font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em;
        border: 1px solid var(--paper-line);
    }

    .error-state {
        text-align: center; padding: 80px 24px;
        max-width: 480px; margin: 0 auto;
    }
    .error-state i { font-size: 42px; color: var(--ink-soft); margin-bottom: 22px; display: block; }
    .error-state h2 { font-size: 32px; margin: 0 0 10px; }
    .error-state p { color: var(--ink-soft); font-size: 13px; line-height: 1.65; }

    .floating-bar {
        position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
        background: linear-gradient(to top, var(--paper) 55%, transparent);
        padding: 30px 20px 18px;
        pointer-events: none;
    }
    .floating-bar-inner {
        pointer-events: auto;
        max-width: 980px; margin: 0 auto;
        display: flex; gap: 12px; align-items: center;
    }
    .assist-btn {
        display: inline-flex; align-items: center; gap: 9px;
        background: var(--surface); color: var(--ink);
        border: 1px solid var(--ink);
        padding: 14px 18px;
        font-size: 12px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em;
        cursor: pointer; white-space: nowrap;
    }
    .assist-btn:hover { background: var(--paper-2); }
    .cart-bar-btn {
        flex: 1;
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        background: var(--ink); color: var(--paper);
        border: 1px solid var(--ink);
        padding: 14px 20px;
        font-size: 13px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em;
        cursor: pointer;
    }
    .cart-bar-btn:hover { background: #3a3529; }
    .cart-bar-btn .left { display: flex; align-items: center; gap: 12px; }
    .cart-bar-btn .count-pill {
        background: var(--press-red); color: #fff;
        width: 26px; height: 26px;
        border: 1px solid var(--paper);
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
    }

    .overlay {
        position: fixed; inset: 0; background: rgba(20,18,15,0.55); z-index: 90;
        opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
    }
    .overlay.open { opacity: 1; pointer-events: auto; }

    .sheet {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 95;
        background: var(--surface);
        border-top: 2px solid var(--ink);
        max-height: 90vh; display: flex; flex-direction: column;
        transform: translateY(100%);
        transition: transform 0.28s cubic-bezier(.22,1,.36,1);
        box-shadow: 0 -20px 60px rgba(0,0,0,0.3);
    }
    .sheet.open { transform: translateY(0); }
    .sheet-handle {
        width: 52px; height: 5px; background: var(--ink);
        margin: 12px auto 8px; flex-shrink: 0;
    }
    .sheet-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 22px 16px;
        border-bottom: 2px solid var(--ink);
        flex-shrink: 0;
    }
    .sheet-header h3 {
        margin: 0; font-size: 26px;
        font-family: 'Barlow Condensed', sans-serif;
        text-transform: uppercase;
    }
    .sheet-close {
        width: 34px; height: 34px;
        border: 1px solid var(--ink); background: var(--paper-2);
        color: var(--ink);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 13px;
    }
    .sheet-close:hover { background: var(--paper); }
    .sheet-body { padding: 20px 22px; overflow-y: auto; flex: 1; }
    .sheet-footer {
        padding: 16px 22px calc(20px + env(safe-area-inset-bottom, 0px));
        border-top: 2px solid var(--ink);
        flex-shrink: 0;
        background: var(--paper);
    }

    .customize-preview { display: flex; gap: 14px; margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px dashed var(--ink); }
    .customize-preview .thumb {
        width: 82px; height: 82px;
        border: 1px solid var(--ink);
        background: var(--paper-2);
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; color: var(--ink-soft); font-size: 24px;
    }
    .customize-preview .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .customize-preview .name {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 24px; font-weight: 800; margin-bottom: 4px;
        text-transform: uppercase; line-height: 1;
    }
    .customize-preview .price {
        font-size: 13px; color: var(--press-red); font-weight: 700;
        font-family: 'IBM Plex Mono', monospace;
    }

    .option-group { margin-bottom: 22px; }
    .option-group .label {
        font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.1em;
        color: var(--ink-soft); margin-bottom: 10px;
        display: block;
    }
    .option-pills { display: flex; flex-wrap: wrap; gap: 8px; }
    .option-pill {
        padding: 9px 15px;
        border: 1px solid var(--ink);
        background: var(--surface);
        font-size: 12px; cursor: pointer; color: var(--ink);
        user-select: none;
        font-family: 'IBM Plex Mono', monospace; font-weight: 500;
    }
    .option-pill .plus { color: var(--ink-soft); font-size: 10.5px; margin-left: 5px; }
    .option-pill:hover { background: var(--paper-2); }
    .option-pill.selected {
        background: var(--ink); color: var(--paper); border-color: var(--ink);
    }
    .option-pill.selected .plus { color: var(--ink-3); }

    .qty-row {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 8px; padding-top: 14px; border-top: 1px dashed var(--ink);
    }
    .qty-row .label {
        font-size: 12px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.1em;
    }
    .qty-stepper { display: flex; align-items: center; gap: 16px; }
    .qty-stepper button {
        width: 38px; height: 38px;
        border: 1px solid var(--ink); background: var(--surface);
        font-size: 14px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .qty-stepper button:hover { background: var(--paper-2); }
    .qty-stepper span {
        font-size: 18px; font-weight: 700; min-width: 30px; text-align: center;
        font-family: 'Barlow Condensed', sans-serif;
    }

    .sheet-footer .totals-row {
        display: flex; justify-content: space-between; align-items: baseline;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px dashed var(--ink);
    }
    .sheet-footer .totals-row .label {
        font-size: 11px; color: var(--ink-soft);
        text-transform: uppercase; letter-spacing: 0.1em;
    }
    .sheet-footer .totals-row .amount {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 32px; font-weight: 800;
        letter-spacing: -0.01em;
    }
    .btn-block {
        width: 100%;
        background: var(--ink); color: var(--paper);
        border: 1px solid var(--ink);
        padding: 16px;
        font-size: 13px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.12em;
        cursor: pointer;
        font-family: 'IBM Plex Mono', monospace;
    }
    .btn-block:hover:not(:disabled) { background: #3a3529; }
    .btn-block:disabled { opacity: 0.45; cursor: not-allowed; }

    .cart-line {
        display: flex; gap: 14px; padding: 16px 0;
        border-bottom: 1px dashed var(--ink);
    }
    .cart-line:last-child { border-bottom: none; }
    .cart-line .thumb {
        width: 68px; height: 68px;
        border: 1px solid var(--ink); background: var(--paper-2);
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        overflow: hidden; color: var(--ink-soft);
    }
    .cart-line .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .cart-line .info { flex: 1; min-width: 0; }
    .cart-line .name {
        font-family: 'Barlow Condensed', sans-serif;
        font-weight: 700; font-size: 19px;
        text-transform: uppercase; line-height: 1.05;
        margin-bottom: 4px;
    }
    .cart-line .meta {
        font-size: 11px; color: var(--ink-soft); line-height: 1.55;
        font-family: 'IBM Plex Mono', monospace;
    }
    .cart-line .row-bottom {
        display: flex; align-items: center; justify-content: space-between;
        margin-top: 10px;
    }
    .cart-line .price {
        font-family: 'IBM Plex Mono', monospace;
        font-weight: 700; font-size: 13px;
        color: var(--press-red);
    }
    .cart-mini-stepper { display: flex; align-items: center; gap: 10px; }
    .cart-mini-stepper button {
        width: 28px; height: 28px;
        border: 1px solid var(--ink); background: var(--surface);
        font-size: 11px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .cart-mini-stepper button:hover { background: var(--paper-2); }
    .cart-mini-stepper span {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 16px; font-weight: 700; min-width: 20px; text-align: center;
    }
    .cart-remove {
        color: var(--press-red); font-size: 10.5px;
        background: none; border: none; cursor: pointer; padding: 0;
        text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;
        font-family: 'IBM Plex Mono', monospace;
    }
    .cart-remove:hover { text-decoration: underline; }
    .cart-empty {
        text-align: center; padding: 60px 20px; color: var(--ink-soft);
        font-size: 13px; line-height: 1.6;
    }
    .cart-empty i { font-size: 38px; color: var(--ink-soft); margin-bottom: 18px; display: block; }
    .cart-note-input {
        width: 100%;
        border: 1px solid var(--ink);
        padding: 12px 14px;
        font-size: 12.5px;
        resize: none; font-family: 'IBM Plex Mono', monospace;
        background: var(--surface);
        margin-top: 8px;
    }
    .cart-note-input:focus { outline: none; border-color: var(--press-red); }

    .service-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; }
    .service-option {
        border: 1px solid var(--ink);
        background: var(--surface);
        padding: 20px 14px;
        text-align: center; cursor: pointer;
    }
    .service-option:hover { background: var(--paper-2); }
    .service-option i { font-size: 22px; color: var(--press-red); margin-bottom: 10px; display: block; }
    .service-option span {
        font-size: 11.5px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em;
    }
    .service-option.selected {
        background: var(--ink); border-color: var(--ink);
    }
    .service-option.selected i, .service-option.selected span { color: var(--paper); }

    .confirm-wrap { text-align: center; padding: 10px 4px 6px; }
    .confirm-check {
        width: 74px; height: 74px;
        border: 2px solid var(--ink);
        color: var(--ink);
        display: flex; align-items: center; justify-content: center;
        font-size: 30px; margin: 0 auto 20px;
    }
    .confirm-number {
        font-family: 'Barlow Condensed', sans-serif;
        font-size: 40px; font-weight: 800;
        letter-spacing: 0.02em; margin-bottom: 6px;
        text-transform: uppercase;
    }
    .confirm-sub {
        font-size: 11.5px; color: var(--ink-soft); margin-bottom: 24px;
        text-transform: uppercase; letter-spacing: 0.08em;
    }
    .confirm-summary {
        text-align: left;
        background: var(--surface);
        border: 1px solid var(--ink);
        padding: 18px 20px;
        margin-bottom: 18px;
    }
    .confirm-summary .row {
        display: flex; justify-content: space-between;
        font-size: 12.5px; padding: 6px 0; color: var(--ink-soft);
        font-family: 'IBM Plex Mono', monospace;
    }
    .confirm-summary .row.total {
        border-top: 1px dashed var(--ink);
        margin-top: 8px; padding-top: 12px;
        font-weight: 700; color: var(--ink); font-size: 15px;
        font-family: 'Barlow Condensed', sans-serif;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .toast {
        position: fixed; top: 18px; left: 50%;
        transform: translateX(-50%) translateY(-24px);
        background: var(--ink); color: var(--paper);
        padding: 14px 22px;
        border: 1px solid var(--ink);
        font-size: 12px; font-weight: 600;
        z-index: 120; opacity: 0; pointer-events: none;
        transition: all 0.25s ease;
        display: flex; align-items: center; gap: 10px;
        max-width: 90vw;
        text-transform: uppercase; letter-spacing: 0.06em;
        font-family: 'IBM Plex Mono', monospace;
    }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .toast i { color: var(--gold); }
</style>
</head>
<body<?= ($tableInfo && !empty($menuByCategory)) ? ' class="has-floating-bar"' : '' ?>>

<?php if ($tableInfo): ?>

    <div class="cust-topbar">
        <div class="cust-brand">
            <div class="mark">
                <img src="img/logo.png" alt="Perch & Pour logo" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=&quot;fa-solid fa-mug-hot&quot;></i>';">
            </div>
            <div class="name">Perch &amp; Pour<small>Dine-in Order</small></div>
        </div>
        <div class="topbar-right">
            <div class="table-chip"><i class="fa-solid fa-chair"></i> Table <?= htmlspecialchars($tableInfo['table_number']) ?></div>
            <button type="button" class="cart-icon-btn" id="openCartBtn">
                <i class="fa-solid fa-basket-shopping"></i>
                <span class="badge d-none" id="cartBadge">0</span>
            </button>
        </div>
    </div>

    <?php if (empty($menuByCategory)): ?>

        <div class="masthead-band">
            <span>Order Slip &middot; Awaiting Menu</span>
            <div class="dots"><span></span><span></span><span></span></div>
        </div>

        <div class="error-state">
            <i class="fa-solid fa-mug-hot"></i>
            <h2>Menu Not Ready</h2>
            <p>Our menu is not available at the moment. Please call a staff member for assistance.</p>
        </div>

    <?php else: ?>

        <?php $categories = array_keys($menuByCategory); ?>

        <div class="masthead-band">
            <span>Order Slip &middot; Table <?= htmlspecialchars($tableInfo['table_number']) ?> &middot; Press Desk No. 01</span>
            <div class="dots"><span></span><span></span><span></span></div>
        </div>

        <nav class="cat-nav" id="catNav">
            <?php foreach ($categories as $i => $cat): ?>
                <a href="#cat-<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>" onclick="setActive(this)">
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="menu-section">
            <?php foreach ($categories as $i => $cat): ?>
                <div class="cat-block" id="cat-<?= $i ?>">
                    <div class="section-head">
                        <h2><?= htmlspecialchars($cat) ?></h2>
                        <span class="meta"><?= count($menuByCategory[$cat]) ?> item<?= count($menuByCategory[$cat]) === 1 ? '' : 's' ?></span>
                    </div>

                    <div class="menu-grid">
                        <?php foreach ($menuByCategory[$cat] as $item): ?>
                            <?php $imgPath = resolveProductImage($item['image'] ?? null); ?>
                            <div class="menu-card" data-item-id="<?= (int) $item['id'] ?>" onclick="openCustomize(<?= (int) $item['id'] ?>)">
                                <div class="thumb">
                                    <?php if ($imgPath): ?>
                                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($item['item_name']) ?>" loading="lazy">
                                    <?php else: ?>
                                        <i class="fa-solid fa-mug-hot"></i>
                                    <?php endif; ?>
                                    <div class="add-fab"><i class="fa-solid fa-plus"></i></div>
                                </div>
                                <div class="info">
                                    <div class="name"><?= htmlspecialchars($item['item_name']) ?></div>
                                    <div class="price">₱<?= number_format($item['price'], 2) ?></div>
                                    <div class="tags">
                                        <?php if ($item['has_size']): ?><span class="tag-chip">Size</span><?php endif; ?>
                                        <?php if ($item['has_milk_type']): ?><span class="tag-chip">Milk</span><?php endif; ?>
                                        <?php if ($item['has_addons']): ?><span class="tag-chip">Add-ons</span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="floating-bar">
            <div class="floating-bar-inner">
                <button type="button" class="assist-btn" id="openServiceBtn">
                    <i class="fa-solid fa-bell-concierge"></i>
                    <span class="d-none d-sm-inline">Request</span> Assistance
                </button>
                <button type="button" class="cart-bar-btn" id="openCartBtn2">
                    <span class="left">
                        <span class="count-pill" id="cartBarCount">0</span>
                        View Cart
                    </span>
                    <span id="cartBarTotal">₱0.00</span>
                </button>
            </div>
        </div>

    <?php endif; ?>

<?php elseif ($tableToken !== ''): ?>

    <div class="cust-topbar">
        <div class="cust-brand">
            <div class="mark">
                <img src="img/logo.png" alt="Perch & Pour logo" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=&quot;fa-solid fa-mug-hot&quot;></i>';">
            </div>
            <div class="name">Perch &amp; Pour<small>Dine-in Order</small></div>
        </div>
    </div>

    <div class="masthead-band">
        <span>Invalid Table Code</span>
        <div class="dots"><span></span><span></span><span></span></div>
    </div>

    <div class="error-state">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <h2>Invalid QR</h2>
        <p>The QR code you scanned is not recognized. Please ask a staff member for help.</p>
    </div>

<?php else: ?>

    <div class="cust-topbar">
        <div class="cust-brand">
            <div class="mark">
                <img src="img/logo.png" alt="Perch & Pour logo" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=&quot;fa-solid fa-mug-hot&quot;></i>';">
            </div>
            <div class="name">Perch &amp; Pour<small>Dine-in Order</small></div>
        </div>
    </div>

    <div class="masthead-band">
        <span>Welcome &middot; Scan to Begin</span>
        <div class="dots"><span></span><span></span><span></span></div>
    </div>

    <div class="hero" id="heroView">
        <div class="icon-big">
            <img src="img/logo.png" alt="Perch & Pour logo" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=&quot;fa-solid fa-qrcode&quot;></i>';">
        </div>
        <h1>Welcome to<br>Perch &amp; Pour</h1>
        <p class="lede">Scan the QR code on your table to view our menu and start ordering.</p>
        <button type="button" class="btn-primary-cust" onclick="openScanner()">
            <i class="fa-solid fa-camera"></i>
            Open Camera
        </button>
        <div>
            <a href="#" class="btn-link-cust" onclick="alert('Ask a staff member to help you scan the table QR code.'); return false;">
                <i class="fa-solid fa-circle-info"></i>
                Need Help?
            </a>
        </div>
    </div>

    <div class="scanner-wrap d-none" id="scannerView">
        <div id="qr-reader"></div>
        <div class="scan-hint">Point your camera at the QR code on your table.</div>
        <div class="text-center mt-3">
            <button type="button" class="btn-link-cust" onclick="closeScanner()">
                <i class="fa-solid fa-arrow-left"></i>
                Back
            </button>
        </div>
    </div>

<?php endif; ?>

<?php if ($tableInfo && !empty($menuByCategory)): ?>

<div class="overlay" id="overlay"></div>

<div class="sheet" id="customizeSheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <h3>Customize</h3>
        <button type="button" class="sheet-close" onclick="closeSheet('customizeSheet')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="sheet-body" id="customizeBody"></div>
    <div class="sheet-footer">
        <div class="totals-row">
            <span class="label">Item total</span>
            <span class="amount" id="customizeTotal">₱0.00</span>
        </div>
        <button type="button" class="btn-block" id="addToCartBtn" onclick="addCurrentToCart()">Add to Cart</button>
    </div>
</div>

<div class="sheet" id="cartSheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <h3>Your Order</h3>
        <button type="button" class="sheet-close" onclick="closeSheet('cartSheet')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="sheet-body" id="cartBody"></div>
    <div class="sheet-footer" id="cartFooter">
        <div class="totals-row">
            <span class="label">Total</span>
            <span class="amount" id="cartTotal">₱0.00</span>
        </div>
        <button type="button" class="btn-block" id="placeOrderBtn" onclick="placeOrder()">Place Order</button>
    </div>
</div>

<div class="sheet" id="serviceSheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <h3>Request Assistance</h3>
        <button type="button" class="sheet-close" onclick="closeSheet('serviceSheet')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="sheet-body">
        <div class="service-grid" id="serviceGrid">
            <?php foreach ($serviceTypes as $s): ?>
                <div class="service-option" data-type="<?= htmlspecialchars($s['id']) ?>" data-label="<?= htmlspecialchars($s['label']) ?>" onclick="selectServiceType(this)">
                    <i class="fa-solid <?= htmlspecialchars($s['icon']) ?>"></i>
                    <span><?= htmlspecialchars($s['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <textarea class="cart-note-input" id="serviceNote" rows="2" placeholder="Add a short note (optional)"></textarea>
    </div>
    <div class="sheet-footer">
        <button type="button" class="btn-block" id="sendServiceBtn" onclick="sendServiceRequest()" disabled>Notify Staff</button>
    </div>
</div>

<div class="sheet" id="confirmSheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <h3>Order Placed</h3>
        <button type="button" class="sheet-close" onclick="closeSheet('confirmSheet')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="sheet-body">
        <div class="confirm-wrap">
            <div class="confirm-check"><i class="fa-solid fa-check"></i></div>
            <div class="confirm-number" id="confirmOrderNumber">Order 000</div>
            <div class="confirm-sub">Show this number to staff if you need help with your order.</div>
            <div class="confirm-summary" id="confirmSummary"></div>
        </div>
    </div>
    <div class="sheet-footer">
        <button type="button" class="btn-block" onclick="closeSheet('confirmSheet')">Done</button>
    </div>
</div>

<div class="toast" id="toast"><i class="fa-solid fa-circle-check"></i><span id="toastMsg"></span></div>
<?php endif; ?>

<?php include __DIR__ . '/include/chatbot.php'; ?>

<script src="vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
    function setActive(el) {
        document.querySelectorAll('#catNav a').forEach(function (a) { a.classList.remove('active'); });
        el.classList.add('active');
    }

    let scannerInstance = null;

    function openScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            alert('Scanner library not loaded.\n\nCheck that this file exists:\n/perchpour/html5-qrcode.min.js');
            return;
        }
        if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            alert('Camera blocked by browser.\n\nBrowsers only allow camera access on:\n• https://\n• http://localhost\n• http://127.0.0.1\n\nYou are on: ' + location.protocol + '//' + location.hostname);
            return;
        }
        document.getElementById('heroView').classList.add('d-none');
        document.getElementById('scannerView').classList.remove('d-none');
        const readerEl = document.getElementById('qr-reader');
        readerEl.innerHTML = '';
        setTimeout(function () {
            scannerInstance = new Html5Qrcode("qr-reader");
            scannerInstance.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                onScanSuccess,
                function () {}
            ).catch(function (err) {
                console.error('Scanner error:', err);
                let msg = 'Cannot open camera.\n\n';
                const e = String(err).toLowerCase();
                if (e.indexOf('permission') !== -1 || e.indexOf('notallowed') !== -1) msg += 'Permission was denied. Please allow camera access in your browser settings.';
                else if (e.indexOf('notfound') !== -1 || e.indexOf('no camera') !== -1) msg += 'No camera detected on this device.';
                else if (e.indexOf('notreadable') !== -1) msg += 'Camera is in use by another app. Close other apps and try again.';
                else msg += 'Error: ' + err;
                alert(msg);
                closeScanner();
            });
        }, 300);
    }

    function closeScanner() {
        if (scannerInstance) {
            scannerInstance.stop().then(function () { scannerInstance.clear(); scannerInstance = null; }).catch(function () {});
        }
        document.getElementById('scannerView').classList.add('d-none');
        document.getElementById('heroView').classList.remove('d-none');
    }

    function onScanSuccess(decodedText) {
        let token = null;
        try {
            const url = new URL(decodedText);
            token = url.searchParams.get('table');
        } catch (e) {
            if (decodedText.indexOf('TABLE-') === 0) token = decodedText;
        }
        if (!token) { alert('This QR code is not a Perch & Pour table code.'); return; }
        if (scannerInstance) scannerInstance.stop().catch(function () {});
        window.location.href = 'index.php?table=' + encodeURIComponent(token);
    }

    <?php if ($tableInfo && !empty($menuByCategory)): ?>
    const MENU_ITEMS   = <?= json_encode($menuItemsFlat, JSON_UNESCAPED_UNICODE) ?>;
    const SIZE_OPTS    = <?= json_encode($sizeOptions) ?>;
    const SWEET_OPTS   = <?= json_encode($sweetnessOptions) ?>;
    const MILK_OPTS    = <?= json_encode($milkOptions) ?>;
    const ADDON_OPTS   = <?= json_encode($addonOptions) ?>;
    const TABLE_ID     = <?= (int) ($tableInfo['id'] ?? $tableInfo['table_id'] ?? 0) ?>;
    const TABLE_NUMBER = <?= json_encode((string) $tableInfo['table_number']) ?>;

    const money = n => '₱' + Number(n).toFixed(2);
    let cart = [];
    let currentItem = null;
    let currentConfig = { size: null, sweetness: null, milk: null, addons: [], qty: 1 };
    let selectedServiceType = null;

    function findMenuItem(id) { return MENU_ITEMS.find(m => m.id === id); }

    function openSheet(id) {
        document.getElementById('overlay').classList.add('open');
        document.getElementById(id).classList.add('open');
    }
    function closeSheet(id) {
        document.getElementById(id).classList.remove('open');
        const anyOpen = document.querySelectorAll('.sheet.open').length > 0;
        if (!anyOpen) document.getElementById('overlay').classList.remove('open');
    }
    document.getElementById('overlay').addEventListener('click', function () {
        document.querySelectorAll('.sheet.open').forEach(s => s.classList.remove('open'));
        this.classList.remove('open');
    });

    function openCustomize(itemId) {
        currentItem = findMenuItem(itemId);
        if (!currentItem) return;
        currentConfig = {
            size: currentItem.has_size ? SIZE_OPTS[0].label : null,
            sweetness: currentItem.has_sweetness ? SWEET_OPTS[2].label : null,
            milk: currentItem.has_milk_type ? MILK_OPTS[0].label : null,
            addons: [],
            qty: 1
        };
        renderCustomize();
        openSheet('customizeSheet');
    }

    function optionGroup(title, opts, groupKey, multi) {
        let html = '<div class="option-group"><div class="label">' + title + '</div><div class="option-pills">';
        opts.forEach(function (o) {
            const isSelected = multi
                ? currentConfig.addons.includes(o.label)
                : currentConfig[groupKey] === o.label;
            html += '<div class="option-pill' + (isSelected ? ' selected' : '') + '" '
                  + 'onclick="chooseOption(\'' + groupKey + '\',\'' + o.label.replace(/'/g,"\\'") + '\',' + (multi ? 'true' : 'false') + ')">'
                  + o.label + (o.price > 0 ? '<span class="plus">+' + money(o.price) + '</span>' : '')
                  + '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function renderCustomize() {
        let html = '';
        html += '<div class="customize-preview">'
              + '<div class="thumb">' + (currentItem.image ? '<img src="' + currentItem.image + '">' : '<i class="fa-solid fa-mug-hot"></i>') + '</div>'
              + '<div><div class="name">' + currentItem.name + '</div><div class="price">' + money(currentItem.price) + ' base price</div></div>'
              + '</div>';

        if (currentItem.has_size) html += optionGroup('Size', SIZE_OPTS, 'size', false);
        if (currentItem.has_sweetness) html += optionGroup('Sweetness Level', SWEET_OPTS, 'sweetness', false);
        if (currentItem.has_milk_type) html += optionGroup('Milk Type', MILK_OPTS, 'milk', false);
        if (currentItem.has_addons) html += optionGroup('Add-ons', ADDON_OPTS, 'addons', true);

        html += '<div class="qty-row"><div class="label">Quantity</div>'
              + '<div class="qty-stepper">'
              + '<button type="button" onclick="changeQty(-1)"><i class="fa-solid fa-minus"></i></button>'
              + '<span id="qtyVal">' + currentConfig.qty + '</span>'
              + '<button type="button" onclick="changeQty(1)"><i class="fa-solid fa-plus"></i></button>'
              + '</div></div>';

        document.getElementById('customizeBody').innerHTML = html;
        updateCustomizeTotal();
    }

    function chooseOption(groupKey, label, multi) {
        if (multi) {
            const idx = currentConfig.addons.indexOf(label);
            if (idx === -1) currentConfig.addons.push(label); else currentConfig.addons.splice(idx, 1);
        } else {
            currentConfig[groupKey] = label;
        }
        renderCustomize();
    }

    function changeQty(delta) {
        currentConfig.qty = Math.max(1, currentConfig.qty + delta);
        document.getElementById('qtyVal').textContent = currentConfig.qty;
        updateCustomizeTotal();
    }

    function computeUnitPrice(item, config) {
        let price = item.price;
        const sizeOpt = SIZE_OPTS.find(o => o.label === config.size);
        if (sizeOpt) price += sizeOpt.price;
        const milkOpt = MILK_OPTS.find(o => o.label === config.milk);
        if (milkOpt) price += milkOpt.price;
        (config.addons || []).forEach(function (a) {
            const opt = ADDON_OPTS.find(o => o.label === a);
            if (opt) price += opt.price;
        });
        return price;
    }

    function updateCustomizeTotal() {
        const unit = computeUnitPrice(currentItem, currentConfig);
        document.getElementById('customizeTotal').textContent = money(unit * currentConfig.qty);
    }

    function addCurrentToCart() {
        const unitPrice = computeUnitPrice(currentItem, currentConfig);
        cart.push({
            menu_item_id: currentItem.id,
            name: currentItem.name,
            image: currentItem.image,
            unit_price: unitPrice,
            qty: currentConfig.qty,
            size: currentConfig.size,
            sweetness: currentConfig.sweetness,
            milk: currentConfig.milk,
            addons: currentConfig.addons.slice()
        });
        renderCartBar();
        closeSheet('customizeSheet');
        showToast('Added to cart');
    }

    function cartCount() { return cart.reduce((n, l) => n + l.qty, 0); }
    function cartTotal() { return cart.reduce((n, l) => n + l.qty * l.unit_price, 0); }

    function renderCartBar() {
        const count = cartCount();
        document.getElementById('cartBarCount').textContent = count;
        document.getElementById('cartBarTotal').textContent = money(cartTotal());
        const badge = document.getElementById('cartBadge');
        if (count > 0) { badge.textContent = count; badge.classList.remove('d-none'); }
        else badge.classList.add('d-none');
    }

    function lineMeta(line) {
        const parts = [];
        if (line.size) parts.push(line.size);
        if (line.sweetness) parts.push(line.sweetness + ' sweetness');
        if (line.milk) parts.push(line.milk);
        if (line.addons && line.addons.length) parts.push(line.addons.join(', '));
        return parts.join(' · ');
    }

    function renderCart() {
        const body = document.getElementById('cartBody');
        const footer = document.getElementById('cartFooter');
        if (cart.length === 0) {
            body.innerHTML = '<div class="cart-empty"><i class="fa-solid fa-basket-shopping"></i>Your cart is empty.<br>Tap any item on the menu to add it.</div>';
            footer.style.display = 'none';
        } else {
            footer.style.display = 'block';
            let html = '';
            cart.forEach(function (line, idx) {
                html += '<div class="cart-line">'
                      + '<div class="thumb">' + (line.image ? '<img src="' + line.image + '">' : '<i class="fa-solid fa-mug-hot"></i>') + '</div>'
                      + '<div class="info">'
                      + '<div class="name">' + line.name + '</div>'
                      + '<div class="meta">' + (lineMeta(line) || 'No customizations') + '</div>'
                      + '<div class="row-bottom">'
                      + '<div class="cart-mini-stepper">'
                      + '<button type="button" onclick="changeCartQty(' + idx + ',-1)"><i class="fa-solid fa-minus"></i></button>'
                      + '<span>' + line.qty + '</span>'
                      + '<button type="button" onclick="changeCartQty(' + idx + ',1)"><i class="fa-solid fa-plus"></i></button>'
                      + '</div>'
                      + '<div class="price">' + money(line.unit_price * line.qty) + '</div>'
                      + '</div>'
                      + '<button type="button" class="cart-remove" onclick="removeCartLine(' + idx + ')">Remove</button>'
                      + '</div>'
                      + '</div>';
            });
            html += '<textarea class="cart-note-input" id="orderNote" rows="2" placeholder="Any special instructions? (optional)"></textarea>';
            body.innerHTML = html;
        }
        document.getElementById('cartTotal').textContent = money(cartTotal());
        renderCartBar();
    }

    function changeCartQty(idx, delta) {
        cart[idx].qty = Math.max(1, cart[idx].qty + delta);
        renderCart();
    }
    function removeCartLine(idx) {
        cart.splice(idx, 1);
        renderCart();
    }

    document.getElementById('openCartBtn').addEventListener('click', function () { renderCart(); openSheet('cartSheet'); });
    document.getElementById('openCartBtn2').addEventListener('click', function () { renderCart(); openSheet('cartSheet'); });

    function placeOrder() {
        if (cart.length === 0) return;
        const btn = document.getElementById('placeOrderBtn');
        btn.disabled = true;
        btn.textContent = 'Placing order…';

        const noteEl = document.getElementById('orderNote');
        const payload = {
            table_id: TABLE_ID,
            table_number: TABLE_NUMBER,
            notes: noteEl ? noteEl.value.trim() : '',
            items: cart.map(function (line) {
                return {
                    menu_item_id: line.menu_item_id,
                    item_name: line.name,
                    quantity: line.qty,
                    unit_price: line.unit_price,
                    size: line.size,
                    sweetness: line.sweetness,
                    milk_type: line.milk,
                    addons: (line.addons || []).join(', '),
                    subtotal: line.qty * line.unit_price
                };
            })
        };

        fetch('submit_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = 'Place Order';
            if (data.success) {
                showConfirmation(data.order_number, payload.items, data.total);
                cart = [];
                renderCartBar();
                closeSheet('cartSheet');
            } else {
                showToast(data.error || 'Could not place order. Please try again.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = 'Place Order';
            showToast('Network error. Please check your connection and try again.');
        });
    }

    function showConfirmation(orderNumber, items, total) {
        document.getElementById('confirmOrderNumber').textContent = orderNumber;
        let html = '';
        items.forEach(function (it) {
            html += '<div class="row"><span>' + it.quantity + '× ' + it.item_name + '</span><span>' + money(it.subtotal) + '</span></div>';
        });
        html += '<div class="row total"><span>Total</span><span>' + money(total) + '</span></div>';
        document.getElementById('confirmSummary').innerHTML = html;
        openSheet('confirmSheet');
    }

    document.getElementById('openServiceBtn').addEventListener('click', function () {
        selectedServiceType = null;
        document.querySelectorAll('.service-option').forEach(el => el.classList.remove('selected'));
        document.getElementById('serviceNote').value = '';
        document.getElementById('sendServiceBtn').disabled = true;
        openSheet('serviceSheet');
    });

    function selectServiceType(el) {
        document.querySelectorAll('.service-option').forEach(e => e.classList.remove('selected'));
        el.classList.add('selected');
        selectedServiceType = { id: el.dataset.type, label: el.dataset.label };
        document.getElementById('sendServiceBtn').disabled = false;
    }

    function sendServiceRequest() {
        if (!selectedServiceType) return;
        const btn = document.getElementById('sendServiceBtn');
        btn.disabled = true;
        btn.textContent = 'Sending…';

        fetch('submit_service_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_id: TABLE_ID,
                table_number: TABLE_NUMBER,
                request_type: selectedServiceType.label,
                note: document.getElementById('serviceNote').value.trim()
            })
        })
        .then(r => r.json())
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = 'Notify Staff';
            if (data.success) {
                closeSheet('serviceSheet');
                showToast('Staff has been notified — someone will be with you shortly.');
            } else {
                showToast(data.error || 'Could not send request. Please try again.');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = 'Notify Staff';
            showToast('Network error. Please check your connection and try again.');
        });
    }

    let toastTimer = null;
    function showToast(msg) {
        const toast = document.getElementById('toast');
        document.getElementById('toastMsg').textContent = msg;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toast.classList.remove('show'); }, 3200);
    }
    <?php endif; ?>
</script>

</body>
</html>