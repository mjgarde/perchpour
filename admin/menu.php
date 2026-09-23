<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$initial  = strtoupper(substr($fullName, 0, 1));

$success = "";
$error   = "";
$reopenEdit = false;

$categories = ['Coffee', 'Tea', 'Soda', 'Fruit Juice', 'Milk'];

$uploadDir = '../uploads/menu/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function validate_menu_input($name, $category, $price, $validCategories) {
    if ($name === '' || $category === '' || $price === '') {
        return "Please fill in all required fields.";
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        return "Item name must be between 2 and 100 characters.";
    }
    if (!in_array($category, $validCategories, true)) {
        return "Please select a valid category.";
    }
    if (!is_numeric($price) || (float)$price < 0) {
        return "Price must be a valid positive number.";
    }
    return "";
}

function handle_image_upload($uploadDir) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, ""];
    }
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return [null, "Error uploading image. Please try again."];
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        return [null, "Image must be a JPG, PNG, WEBP, or GIF file."];
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        return [null, "Image must be less than 5MB."];
    }

    $filename = uniqid('menu_', true) . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
        return [null, "Failed to save uploaded image."];
    }

    return [$filename, ""];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_item'])) {
    $item_name   = trim($_POST['item_name']);
    $category    = trim($_POST['category']);
    $price       = trim($_POST['price']);

    $validationError = validate_menu_input($item_name, $category, $price, $categories);

    if ($validationError !== "") {
        $error = $validationError;
    } else {
        [$imageFile, $uploadError] = handle_image_upload($uploadDir);

        if ($uploadError !== "") {
            $error = $uploadError;
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO menu_items (item_name, category, price, image) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssds", $item_name, $category, $price, $imageFile);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Menu item added successfully.";
            } else {
                $error = "Something went wrong. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_item'])) {
    $edit_id     = (int) $_POST['item_id'];
    $item_name   = trim($_POST['item_name']);
    $category    = trim($_POST['category']);
    $price       = trim($_POST['price']);

    $validationError = validate_menu_input($item_name, $category, $price, $categories);

    if ($validationError !== "") {
        $error = $validationError;
        $reopenEdit = true;
    } else {
        [$imageFile, $uploadError] = handle_image_upload($uploadDir);

        if ($uploadError !== "") {
            $error = $uploadError;
            $reopenEdit = true;
        } else {
            if ($imageFile !== null) {
                $old = mysqli_prepare($conn, "SELECT image FROM menu_items WHERE item_id = ?");
                mysqli_stmt_bind_param($old, "i", $edit_id);
                mysqli_stmt_execute($old);
                mysqli_stmt_bind_result($old, $oldImage);
                mysqli_stmt_fetch($old);
                mysqli_stmt_close($old);

                if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
                    unlink($uploadDir . $oldImage);
                }

                $stmt = mysqli_prepare($conn, "UPDATE menu_items SET item_name=?, category=?, price=?, image=? WHERE item_id=?");
                mysqli_stmt_bind_param($stmt, "ssdsi", $item_name, $category, $price, $imageFile, $edit_id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE menu_items SET item_name=?, category=?, price=? WHERE item_id=?");
                mysqli_stmt_bind_param($stmt, "ssdi", $item_name, $category, $price, $edit_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $success = "Menu item updated successfully.";
            } else {
                $error = "Something went wrong. Please try again.";
                $reopenEdit = true;
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_availability'])) {
    $toggle_id = (int) $_POST['item_id'];
    $stmt = mysqli_prepare($conn, "UPDATE menu_items SET is_available = NOT is_available WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $toggle_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: menu.php?toggled=1");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_item'])) {
    $delete_id = (int) $_POST['item_id'];

    $old = mysqli_prepare($conn, "SELECT image FROM menu_items WHERE item_id = ?");
    mysqli_stmt_bind_param($old, "i", $delete_id);
    mysqli_stmt_execute($old);
    mysqli_stmt_bind_result($old, $oldImage);
    mysqli_stmt_fetch($old);
    mysqli_stmt_close($old);

    $stmt = mysqli_prepare($conn, "DELETE FROM menu_items WHERE item_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $delete_id);
    if (mysqli_stmt_execute($stmt)) {
        if (!empty($oldImage) && file_exists($uploadDir . $oldImage)) {
            unlink($uploadDir . $oldImage);
        }
        header("Location: menu.php?deleted=1");
        exit();
    } else {
        $error = "Unable to delete this menu item.";
    }
    mysqli_stmt_close($stmt);
}

if (isset($_GET['deleted'])) {
    $success = "Menu item deleted successfully.";
}
if (isset($_GET['toggled'])) {
    $success = "Availability updated.";
}

$menuItems = [];
$result = mysqli_query($conn, "SELECT * FROM menu_items ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $menuItems[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Menu Items — Perch &amp; Pour Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<style>
    :root {
        --bg: #fbfbfa;
        --card: #ffffff;
        --ink: #1d1d1f;
        --ink-soft: #86868b;
        --border: #e8e8e6;
        --hover: rgba(0,0,0,0.035);
        --accent: #0071e3;
        --green: #1a7f37;
        --green-bg: #eaf6ec;
        --red: #d70015;
        --red-bg: #fdeceb;
        --amber: #9a6700;
        --amber-bg: #fff6e0;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif;
        color: var(--ink);
        background: var(--bg);
        display: flex;
        min-height: 100vh;
        font-size: 14px;
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    .main { flex: 1; min-width: 0; }

    .topbar {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 22px 32px;
        border-bottom: 1px solid var(--border);
        background: var(--card);
    }

    .menu-toggle {
        display: none;
        background: var(--card);
        border: 1px solid var(--border);
        width: 34px;
        height: 34px;
        border-radius: 8px;
        color: var(--ink);
        font-size: 14px;
        cursor: pointer;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
    }

    .topbar h1 {
        font-size: 20px;
        font-weight: 600;
        letter-spacing: -0.015em;
        margin: 0;
    }

    .topbar .date-stamp {
        font-size: 12.5px;
        color: var(--ink-soft);
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .content { padding: 24px 32px 50px; }

    .toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
        max-width: 320px;
    }

    .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ink-soft);
        font-size: 13px;
        pointer-events: none;
    }

    .search-box input {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--card);
        font-family: inherit;
        font-size: 13.5px;
        color: var(--ink);
        padding: 9px 12px 9px 34px;
        outline: none;
        transition: border-color 0.15s ease;
    }
    .search-box input:focus { border-color: var(--accent); }

    .filter-select-wrap { position: relative; }

    .filter-select-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ink-soft);
        font-size: 12px;
        pointer-events: none;
        z-index: 2;
    }

    .filter-select {
        appearance: none;
        position: relative;
        z-index: 1;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--card);
        font-family: inherit;
        font-size: 13px;
        color: var(--ink);
        padding: 9px 30px 9px 32px;
        outline: none;
        cursor: pointer;
        transition: border-color 0.15s ease;
    }
    .filter-select:focus { border-color: var(--accent); }

    .filter-select-wrap::after {
        content: "\f078";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 10px;
        color: var(--ink-soft);
        pointer-events: none;
    }

    .toolbar-spacer { flex: 1; }

    .btn-add {
        display: flex;
        align-items: center;
        gap: 7px;
        background: var(--accent);
        color: #fff;
        border: none;
        font-weight: 500;
        font-size: 13.5px;
        letter-spacing: -0.005em;
        padding: 9px 16px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s ease;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .btn-add:hover { background: #0067cf; }

    .alert {
        display: flex;
        align-items: center;
        gap: 8px;
        border-radius: 10px;
        padding: 11px 16px;
        font-size: 13.5px;
        margin-bottom: 18px;
    }
    .alert-success { color: var(--green); background: var(--green-bg); }
    .alert-error { color: var(--red); background: var(--red-bg); }

    .result-count {
        font-size: 12.5px;
        color: var(--ink-soft);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
    }

    .menu-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: box-shadow 0.15s ease;
        display: flex;
        flex-direction: column;
    }
    .menu-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.06); }

    .menu-card-img {
        width: 100%;
        aspect-ratio: 4 / 3;
        background: #f2f2f0 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23cfcfcd'%3E%3Cpath d='M18.06 23h.69A1.24 1.24 0 0 0 20 21.75v-.69l-3.22-3.22-3.22 3.22V21.75A1.24 1.24 0 0 0 14.81 23z'/%3E%3C/svg%3E") center/40px no-repeat;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #cfcfcd;
        font-size: 28px;
        position: relative;
    }
    .menu-card-img img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .menu-card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; }

    .menu-card-cat {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--accent);
        font-weight: 600;
        margin-bottom: 4px;
    }

    .menu-card-title { font-size: 15px; font-weight: 600; margin-bottom: 4px; letter-spacing: -0.01em; }

    .menu-card-desc {
        font-size: 12.5px;
        color: var(--ink-soft);
        margin-bottom: 10px;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .menu-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
    }

    .menu-card-price { font-size: 15px; font-weight: 700; }

    .badge-avail {
        font-size: 10.5px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 20px;
    }
    .badge-avail.yes { color: var(--green); background: var(--green-bg); }
    .badge-avail.no { color: var(--red); background: var(--red-bg); }

    .menu-card-actions {
        display: flex;
        gap: 6px;
        padding: 10px 16px 14px;
        border-top: 1px solid var(--border);
    }

    .icon-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 7px;
        background: none;
        border: 1px solid var(--border);
        color: var(--ink-soft);
        cursor: pointer;
        font-size: 12.5px;
        transition: all 0.15s ease;
    }
    .icon-btn:hover { background: var(--hover); color: var(--accent); }
    .icon-btn.danger:hover { color: var(--red); }
    .icon-btn.toggle-on { color: var(--amber); }

    .btn-toggle-text {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: none;
        font-size: 12px;
        font-weight: 500;
        color: var(--ink-soft);
        cursor: pointer;
        padding: 0 10px;
        transition: all 0.15s ease;
    }
    .btn-toggle-text:hover { background: var(--hover); }

    .empty-note {
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--card);
        color: var(--ink-soft);
        font-size: 13.5px;
        padding: 46px 30px;
        text-align: center;
    }
    .empty-note i { display: block; font-size: 26px; margin-bottom: 12px; color: #cfcfcd; }
    .empty-note strong { display: block; color: var(--ink); font-size: 14px; margin-bottom: 4px; font-weight: 600; }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.35);
        backdrop-filter: blur(2px);
        z-index: 60;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.show { display: flex; }

    .modal-box {
        background: var(--card);
        border-radius: 14px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.18);
        width: 100%;
        max-width: 440px;
        padding: 26px 26px 22px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-box h3 {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 17px;
        font-weight: 600;
        letter-spacing: -0.015em;
        margin: 0 0 4px;
    }
    .modal-box h3 i { font-size: 15px; color: var(--accent); }
    .modal-box h3 i.danger-icon { color: var(--red); }

    .modal-box .modal-sub { font-size: 12.5px; color: var(--ink-soft); margin: 0 0 18px; }

    .field { margin-bottom: 14px; }

    .field label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 600;
        color: var(--ink-soft);
        margin-bottom: 6px;
    }
    .field label i { font-size: 10.5px; width: 12px; }

    .field input, .field select, .field textarea {
        width: 100%;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #fafafa;
        font-family: inherit;
        font-size: 13.5px;
        color: var(--ink);
        padding: 9px 11px;
        outline: none;
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .field input:focus, .field select:focus, .field textarea:focus { border-color: var(--accent); background: #fff; }
    .field textarea { resize: vertical; min-height: 60px; }

    .field .hint { font-size: 11px; color: var(--ink-soft); margin-top: 4px; }

    .current-img-preview {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        font-size: 12px;
        color: var(--ink-soft);
    }
    .current-img-preview img {
        width: 44px;
        height: 44px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .modal-actions { display: flex; gap: 10px; margin-top: 20px; }

    .btn-cancel {
        flex: 1;
        background: #f2f2f2;
        border: none;
        border-radius: 8px;
        color: var(--ink);
        font-size: 13.5px;
        font-weight: 500;
        padding: 10px;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .btn-cancel:hover { background: #e8e8e6; }

    .btn-submit {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        background: var(--accent);
        border: none;
        border-radius: 8px;
        color: #fff;
        font-weight: 600;
        font-size: 13.5px;
        padding: 10px;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .btn-submit:hover { background: #0067cf; }
    .btn-submit.danger { background: var(--red); }
    .btn-submit.danger:hover { background: #a80010; }

    @media (max-width: 991.98px) { .menu-toggle { display: inline-flex; } }

    @media (max-width: 820px) {
        .content { padding: 20px 16px 40px; }
        .topbar { padding: 16px 18px; }
        .toolbar { gap: 8px; }
        .search-box { max-width: none; flex-basis: 100%; order: 1; }
        .filter-select-wrap { order: 2; }
        .toolbar-spacer { display: none; }
        .btn-add { order: 3; margin-left: auto; }
    }

    @media (max-width: 560px) {
        .topbar h1 { font-size: 18px; }
        .search-box, .filter-select-wrap, .btn-add { flex: 1 1 100%; order: initial; }
        .filter-select { width: 100%; }
        .toolbar { flex-direction: column; align-items: stretch; }
        .modal-box { padding: 20px 18px 18px; }
        .menu-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    }
</style>
</head>
<body>

<?php include '../include/admin_sidebar.php'; ?>

<div class="main">

    <div class="topbar">
        <button type="button" class="menu-toggle" onclick="openSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
            <h1>Menu Items</h1>
            <div class="date-stamp"><i class="fa-solid fa-mug-hot"></i> <?php echo count($menuItems); ?> items on the menu</div>
        </div>
    </div>

    <div class="content">

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="searchInput" placeholder="Search item name..." oninput="filterMenu()">
            </div>

            <div class="filter-select-wrap">
                <i class="fa-solid fa-layer-group"></i>
                <select class="filter-select" id="categorySelect" onchange="filterMenu()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-select-wrap">
                <i class="fa-solid fa-toggle-on"></i>
                <select class="filter-select" id="availSelect" onchange="filterMenu()">
                    <option value="">All Status</option>
                    <option value="1">Available</option>
                    <option value="0">Unavailable</option>
                </select>
            </div>

            <div class="toolbar-spacer"></div>

            <button type="button" class="btn-add" onclick="document.getElementById('addModal').classList.add('show')">
                <i class="fa-solid fa-plus"></i> <span>Add Item</span>
            </button>
        </div>

        <?php if (count($menuItems) === 0): ?>
            <div class="empty-note">
                <i class="fa-solid fa-mug-hot"></i>
                <strong>No menu items yet</strong>
                Click "Add Item" to create your first product.
            </div>
        <?php else: ?>
            <div class="result-count" id="resultCount"></div>
            <div class="menu-grid" id="menuGrid">
                <?php foreach ($menuItems as $item): ?>
                <div class="menu-card"
                    data-name="<?php echo htmlspecialchars(strtolower($item['item_name'])); ?>"
                    data-category="<?php echo htmlspecialchars($item['category']); ?>"
                    data-available="<?php echo (int) $item['is_available']; ?>"
                >
                    <div class="menu-card-img">
                        <?php if (!empty($item['image']) && file_exists($uploadDir . $item['image'])): ?>
                            <img src="../uploads/menu/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                        <?php else: ?>
                            <i class="fa-solid fa-mug-hot"></i>
                        <?php endif; ?>
                    </div>
                    <div class="menu-card-body">
                        <div class="menu-card-cat"><?php echo htmlspecialchars($item['category']); ?></div>
                        <div class="menu-card-title"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <div class="menu-card-footer">
                            <div class="menu-card-price">₱<?php echo number_format((float) $item['price'], 2); ?></div>
                            <span class="badge-avail <?php echo $item['is_available'] ? 'yes' : 'no'; ?>">
                                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="menu-card-actions">
                        <form method="POST" action="" style="flex:1;">
                            <input type="hidden" name="item_id" value="<?php echo (int) $item['item_id']; ?>">
                            <button type="submit" name="toggle_availability" class="btn-toggle-text" style="width:100%;">
                                <i class="fa-solid fa-power-off"></i> <?php echo $item['is_available'] ? 'Mark Out' : 'Mark In'; ?>
                            </button>
                        </form>
                        <button type="button" class="icon-btn" title="Edit"
                            onclick='openEditModal(<?php echo json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="icon-btn danger" title="Delete"
                            onclick="openDeleteModal(<?php echo (int) $item['item_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>')">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-plus"></i>Add Menu Item</h3>
        <p class="modal-sub">Add a new product to the Perch &amp; Pour menu.</p>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="field">
                <label for="item_name"><i class="fa-solid fa-mug-hot"></i>Item Name</label>
                <input type="text" id="item_name" name="item_name" required minlength="2" maxlength="100">
            </div>

            <div class="field">
                <label for="category"><i class="fa-solid fa-layer-group"></i>Category</label>
                <select id="category" name="category" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="price"><i class="fa-solid fa-peso-sign"></i>Price</label>
                <input type="number" id="price" name="price" required min="0" step="0.01" placeholder="0.00">
            </div>

            <div class="field">
                <label for="image"><i class="fa-solid fa-image"></i>Product Image</label>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                <div class="hint">JPG, PNG, WEBP, or GIF. Max 5MB.</div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="add_item" class="btn-submit"><i class="fa-solid fa-check"></i>Add Item</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-pen"></i>Edit Menu Item</h3>
        <p class="modal-sub">Update this product's details.</p>

        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="item_id" id="edit_item_id">

            <div class="field">
                <label for="edit_item_name"><i class="fa-solid fa-mug-hot"></i>Item Name</label>
                <input type="text" id="edit_item_name" name="item_name" required minlength="2" maxlength="100">
            </div>

            <div class="field">
                <label for="edit_category"><i class="fa-solid fa-layer-group"></i>Category</label>
                <select id="edit_category" name="category" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="edit_price"><i class="fa-solid fa-peso-sign"></i>Price</label>
                <input type="number" id="edit_price" name="price" required min="0" step="0.01">
            </div>

            <div class="field">
                <label for="edit_image"><i class="fa-solid fa-image"></i>Product Image</label>
                <div class="current-img-preview" id="currentImgPreview"></div>
                <input type="file" id="edit_image" name="image" accept=".jpg,.jpeg,.png,.webp,.gif">
                <div class="hint">Leave blank to keep the current image.</div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="edit_item" class="btn-submit"><i class="fa-solid fa-check"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-triangle-exclamation danger-icon"></i>Delete Menu Item</h3>
        <p class="modal-sub" id="deleteModalText">Are you sure you want to delete this item? This cannot be undone.</p>

        <form method="POST" action="">
            <input type="hidden" name="item_id" id="delete_item_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="delete_item" class="btn-submit danger"><i class="fa-solid fa-trash"></i>Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(item) {
    document.getElementById('edit_item_id').value = item.item_id;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_category').value = item.category;
    document.getElementById('edit_price').value = item.price;
    document.getElementById('edit_image').value = '';

    const preview = document.getElementById('currentImgPreview');
    if (item.image) {
        preview.innerHTML = '<img src="../uploads/menu/' + item.image + '" alt=""> <span>Current image</span>';
    } else {
        preview.innerHTML = '<span>No image uploaded yet</span>';
    }

    document.getElementById('editModal').classList.add('show');
}

function openDeleteModal(id, name) {
    document.getElementById('delete_item_id').value = id;
    document.getElementById('deleteModalText').textContent = 'Are you sure you want to delete "' + name + '"? This cannot be undone.';
    document.getElementById('deleteModal').classList.add('show');
}

function filterMenu() {
    const grid = document.getElementById('menuGrid');
    if (!grid) return;

    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    const category = document.getElementById('categorySelect').value;
    const avail = document.getElementById('availSelect').value;
    const cards = Array.from(grid.querySelectorAll('.menu-card'));

    let visibleCount = 0;
    cards.forEach(function(card) {
        const matchesQuery = query === '' || card.dataset.name.includes(query);
        const matchesCategory = category === '' || card.dataset.category === category;
        const matchesAvail = avail === '' || card.dataset.available === avail;
        const show = matchesQuery && matchesCategory && matchesAvail;
        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });

    const countLabel = document.getElementById('resultCount');
    if (countLabel) {
        countLabel.innerHTML = '<i class="fa-solid fa-filter"></i> Showing ' + visibleCount + ' of ' + cards.length + ' items';
    }
}

document.addEventListener('DOMContentLoaded', filterMenu);

<?php if ($error && $reopenEdit): ?>
document.getElementById('editModal').classList.add('show');
<?php elseif ($error): ?>
document.getElementById('addModal').classList.add('show');
<?php endif; ?>
</script>

</body>
</html>