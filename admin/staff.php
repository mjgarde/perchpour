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
$justAdded = false;
$reopenEdit = false;

function validate_staff_input($name, $user, $pass, $address, $contact, $requirePassword = true) {
    if ($name === '' || $user === '' || $address === '') {
        return "Please fill in all required fields.";
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        return "Full name must be between 2 and 100 characters.";
    }
    if (!preg_match("/^[a-zA-Z\s\.\-']+$/", $name)) {
        return "Full name contains invalid characters.";
    }
    if (mb_strlen($user) < 3 || mb_strlen($user) > 50) {
        return "Username must be between 3 and 50 characters.";
    }
    if (!preg_match("/^[a-zA-Z0-9_\.]+$/", $user)) {
        return "Username may only contain letters, numbers, underscores, and periods.";
    }
    if ($requirePassword && mb_strlen($pass) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (!$requirePassword && $pass !== '' && mb_strlen($pass) < 8) {
        return "Password must be at least 8 characters long.";
    }
    if (mb_strlen($address) < 5 || mb_strlen($address) > 200) {
        return "Address must be between 5 and 200 characters.";
    }
    if ($contact !== '' && !preg_match("/^09\d{9}$/", $contact)) {
        return "Contact number must be a valid PH mobile number (e.g. 09XXXXXXXXX).";
    }
    return "";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_staff'])) {
    $emp_name    = trim($_POST['full_name']);
    $emp_user    = trim($_POST['username']);
    $emp_pass    = $_POST['password'];
    $emp_address = trim($_POST['address']);
    $emp_contact = trim($_POST['contact_number']);

    $validationError = validate_staff_input($emp_name, $emp_user, $emp_pass, $emp_address, $emp_contact, true);

    if ($validationError !== "") {
        $error = $validationError;
    } else {
        $check = mysqli_prepare($conn, "SELECT staff_id FROM staff WHERE username = ?");
        mysqli_stmt_bind_param($check, "s", $emp_user);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Username already exists. Please choose another.";
        } else {
            $hashed = password_hash($emp_pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO staff (full_name, username, password, address, contact_number) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssss", $emp_name, $emp_user, $hashed, $emp_address, $emp_contact);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Staff account created successfully.";
                $justAdded = true;
            } else {
                $error = "Something went wrong. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_staff'])) {
    $edit_id      = (int) $_POST['staff_id'];
    $emp_name     = trim($_POST['full_name']);
    $emp_user     = trim($_POST['username']);
    $emp_pass     = $_POST['password'];
    $emp_address  = trim($_POST['address']);
    $emp_contact  = trim($_POST['contact_number']);

    $validationError = validate_staff_input($emp_name, $emp_user, $emp_pass, $emp_address, $emp_contact, false);

    if ($validationError !== "") {
        $error = $validationError;
        $reopenEdit = true;
    } else {
        $check = mysqli_prepare($conn, "SELECT staff_id FROM staff WHERE username = ? AND staff_id != ?");
        mysqli_stmt_bind_param($check, "si", $emp_user, $edit_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Username already exists. Please choose another.";
            $reopenEdit = true;
        } else {
            if ($emp_pass !== '') {
                $hashed = password_hash($emp_pass, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE staff SET full_name=?, username=?, password=?, address=?, contact_number=? WHERE staff_id=?");
                mysqli_stmt_bind_param($stmt, "sssssi", $emp_name, $emp_user, $hashed, $emp_address, $emp_contact, $edit_id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE staff SET full_name=?, username=?, address=?, contact_number=? WHERE staff_id=?");
                mysqli_stmt_bind_param($stmt, "ssssi", $emp_name, $emp_user, $emp_address, $emp_contact, $edit_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $success = "Staff account updated successfully.";
                $justAdded = true;
            } else {
                $error = "Something went wrong. Please try again.";
                $reopenEdit = true;
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_staff'])) {
    $delete_id = (int) $_POST['staff_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM staff WHERE staff_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $delete_id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: staff.php?deleted=1");
        exit();
    } else {
        $error = "Unable to delete this staff account.";
    }
    mysqli_stmt_close($stmt);
}

if (isset($_GET['deleted'])) {
    $success = "Staff account deleted successfully.";
}

$staff = [];
$result = mysqli_query($conn, "SELECT * FROM staff ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $staff[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff — Perch &amp; Pour Admin</title>
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

    .filter-select-wrap {
        position: relative;
    }

    .filter-select-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--ink-soft);
        font-size: 12px;
        pointer-events: none;
    }

    .filter-select {
        appearance: none;
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

    .emp-table-wrap {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        overflow-x: auto;
    }

    table.emp-table { width: 100%; border-collapse: collapse; min-width: 680px; }

    table.emp-table th {
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ink-soft);
        padding: 13px 18px;
        border-bottom: 1px solid var(--border);
        font-weight: 600;
        background: #fafafa;
        white-space: nowrap;
    }

    table.emp-table th i { margin-right: 6px; color: var(--ink-soft); font-size: 10.5px; }

    table.emp-table td {
        padding: 13px 18px;
        border-bottom: 1px solid var(--border);
        font-size: 13.5px;
        vertical-align: middle;
        color: var(--ink);
    }

    table.emp-table tr:last-child td { border-bottom: none; }
    table.emp-table tbody tr { transition: background 0.12s ease; }
    table.emp-table tbody tr:hover { background: var(--hover); }
    table.emp-table tbody tr.hidden-row { display: none; }

    .emp-name { display: flex; align-items: center; gap: 10px; font-weight: 500; }

    .emp-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--ink);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 12px;
        flex-shrink: 0;
    }

    .emp-username { color: var(--ink-soft); }

    .emp-date { color: var(--ink-soft); white-space: nowrap; }

    .row-actions { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }

    .icon-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 7px;
        background: none;
        border: 1px solid transparent;
        color: var(--ink-soft);
        cursor: pointer;
        font-size: 13px;
        transition: all 0.15s ease;
    }
    .icon-btn:hover { background: var(--hover); border-color: var(--border); color: var(--accent); }
    .icon-btn.danger:hover { color: var(--red); }

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
        max-width: 420px;
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

    .modal-box .modal-sub {
        font-size: 12.5px;
        color: var(--ink-soft);
        margin: 0 0 18px;
    }

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

    .field input, .field select {
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
    .field input:focus, .field select:focus {
        border-color: var(--accent);
        background: #fff;
    }

    .field .hint { font-size: 11px; color: var(--ink-soft); margin-top: 4px; }

    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

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

    @media (max-width: 991.98px) {
        .menu-toggle { display: inline-flex; }
    }

    @media (max-width: 820px) {
        .content { padding: 20px 16px 40px; }
        .topbar { padding: 16px 18px; }
        .toolbar { gap: 8px; }
        .search-box { max-width: none; flex-basis: 100%; order: 1; }
        .filter-select-wrap { order: 2; }
        .toolbar-spacer { display: none; }
        .btn-add { order: 3; margin-left: auto; }
        .btn-add span { display: inline; }
    }

    @media (max-width: 560px) {
        .topbar h1 { font-size: 18px; }
        .search-box, .filter-select-wrap, .btn-add { flex: 1 1 100%; order: initial; }
        .filter-select { width: 100%; }
        .toolbar { flex-direction: column; align-items: stretch; }
        .modal-box { padding: 20px 18px 18px; }
    }
</style>
</head>
<body>

<?php include '../include/admin_sidebar.php'; ?>

<div class="main">

    <div class="topbar">
        <button type="button" class="menu-toggle" onclick="openSidebar()"><i class="fa-solid fa-bars"></i></button>
        <div>
            <h1>Staff</h1>
            <div class="date-stamp"><i class="fa-solid fa-users"></i> <?php echo count($staff); ?> staff on record</div>
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
                <input type="text" id="searchInput" placeholder="Search name, username, address..." oninput="filterStaff()">
            </div>

            <div class="filter-select-wrap">
                <i class="fa-solid fa-arrow-down-a-z"></i>
                <select class="filter-select" id="sortSelect" onchange="filterStaff()">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="name_asc">Name A–Z</option>
                    <option value="name_desc">Name Z–A</option>
                </select>
            </div>

            <div class="toolbar-spacer"></div>

            <button type="button" class="btn-add" onclick="document.getElementById('addModal').classList.add('show')">
                <i class="fa-solid fa-user-plus"></i> <span>Add Staff</span>
            </button>
        </div>

        <?php if (count($staff) === 0): ?>
            <div class="empty-note">
                <i class="fa-solid fa-users"></i>
                <strong>No staff added yet</strong>
                Click "Add Staff" to create a staff account.
            </div>
        <?php else: ?>
            <div class="result-count" id="resultCount"></div>
            <div class="emp-table-wrap">
                <table class="emp-table" id="staffTable">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-user"></i>Name</th>
                            <th><i class="fa-solid fa-at"></i>Username</th>
                            <th><i class="fa-solid fa-location-dot"></i>Address</th>
                            <th><i class="fa-solid fa-phone"></i>Contact</th>
                            <th><i class="fa-solid fa-calendar"></i>Date Added</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $emp): ?>
                        <tr
                            data-name="<?php echo htmlspecialchars(strtolower($emp['full_name'])); ?>"
                            data-username="<?php echo htmlspecialchars(strtolower($emp['username'])); ?>"
                            data-address="<?php echo htmlspecialchars(strtolower($emp['address'])); ?>"
                            data-contact="<?php echo htmlspecialchars(strtolower($emp['contact_number'])); ?>"
                            data-created="<?php echo strtotime($emp['created_at']); ?>"
                        >
                            <td>
                                <div class="emp-name">
                                    <div class="emp-avatar"><?php echo htmlspecialchars(strtoupper(substr($emp['full_name'], 0, 1))); ?></div>
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </div>
                            </td>
                            <td><span class="emp-username"><?php echo htmlspecialchars($emp['username']); ?></span></td>
                            <td><?php echo htmlspecialchars($emp['address']); ?></td>
                            <td><?php echo htmlspecialchars($emp['contact_number'] ?: '—'); ?></td>
                            <td><span class="emp-date"><?php echo date("M d, Y", strtotime($emp['created_at'])); ?></span></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="icon-btn" title="Edit"
                                        onclick='openEditModal(<?php echo json_encode($emp, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="icon-btn danger" title="Delete"
                                        onclick="openDeleteModal(<?php echo (int) $emp['staff_id']; ?>, '<?php echo htmlspecialchars(addslashes($emp['full_name'])); ?>')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-user-plus"></i>Add Staff</h3>
        <p class="modal-sub">Create a staff account for the Perch &amp; Pour system.</p>

        <form method="POST" action="">
            <div class="field">
                <label for="full_name"><i class="fa-solid fa-user"></i>Full Name</label>
                <input type="text" id="full_name" name="full_name" required minlength="2" maxlength="100" pattern="[a-zA-Z\s\.\-']+">
            </div>

            <div class="field">
                <label for="username"><i class="fa-solid fa-at"></i>Username</label>
                <input type="text" id="username" name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_\.]+">
            </div>

            <div class="field">
                <label for="password"><i class="fa-solid fa-lock"></i>Password</label>
                <input type="password" id="password" name="password" required minlength="8">
                <div class="hint">At least 8 characters.</div>
            </div>

            <div class="field">
                <label for="address"><i class="fa-solid fa-location-dot"></i>Address</label>
                <input type="text" id="address" name="address" required minlength="5" maxlength="200">
            </div>

            <div class="field">
                <label for="contact_number"><i class="fa-solid fa-phone"></i>Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" placeholder="09XXXXXXXXX" pattern="09\d{9}" maxlength="11">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="add_staff" class="btn-submit"><i class="fa-solid fa-check"></i>Create Account</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-pen"></i>Edit Staff</h3>
        <p class="modal-sub">Update this staff account's details.</p>

        <form method="POST" action="">
            <input type="hidden" name="staff_id" id="edit_staff_id">

            <div class="field">
                <label for="edit_full_name"><i class="fa-solid fa-user"></i>Full Name</label>
                <input type="text" id="edit_full_name" name="full_name" required minlength="2" maxlength="100" pattern="[a-zA-Z\s\.\-']+">
            </div>

            <div class="field">
                <label for="edit_username"><i class="fa-solid fa-at"></i>Username</label>
                <input type="text" id="edit_username" name="username" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_\.]+">
            </div>

            <div class="field">
                <label for="edit_password"><i class="fa-solid fa-lock"></i>Password</label>
                <input type="password" id="edit_password" name="password" minlength="8">
                <div class="hint">Leave blank to keep the current password.</div>
            </div>

            <div class="field">
                <label for="edit_address"><i class="fa-solid fa-location-dot"></i>Address</label>
                <input type="text" id="edit_address" name="address" required minlength="5" maxlength="200">
            </div>

            <div class="field">
                <label for="edit_contact_number"><i class="fa-solid fa-phone"></i>Contact Number</label>
                <input type="text" id="edit_contact_number" name="contact_number" placeholder="09XXXXXXXXX" pattern="09\d{9}" maxlength="11">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('editModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="edit_staff" class="btn-submit"><i class="fa-solid fa-check"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-triangle-exclamation danger-icon"></i>Delete Staff</h3>
        <p class="modal-sub" id="deleteModalText">Are you sure you want to delete this staff account? This cannot be undone.</p>

        <form method="POST" action="">
            <input type="hidden" name="staff_id" id="delete_staff_id">
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('deleteModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="delete_staff" class="btn-submit danger"><i class="fa-solid fa-trash"></i>Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(staff) {
    document.getElementById('edit_staff_id').value = staff.staff_id;
    document.getElementById('edit_full_name').value = staff.full_name;
    document.getElementById('edit_username').value = staff.username;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_address').value = staff.address;
    document.getElementById('edit_contact_number').value = staff.contact_number;
    document.getElementById('editModal').classList.add('show');
}

function openDeleteModal(id, name) {
    document.getElementById('delete_staff_id').value = id;
    document.getElementById('deleteModalText').textContent = 'Are you sure you want to delete "' + name + '"? This cannot be undone.';
    document.getElementById('deleteModal').classList.add('show');
}

function filterStaff() {
    const table = document.getElementById('staffTable');
    if (!table) return;

    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    const sort = document.getElementById('sortSelect').value;
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    let visibleCount = 0;
    rows.forEach(function(row) {
        const haystack = row.dataset.name + ' ' + row.dataset.username + ' ' + row.dataset.address + ' ' + row.dataset.contact;
        const matches = query === '' || haystack.includes(query);
        row.classList.toggle('hidden-row', !matches);
        if (matches) visibleCount++;
    });

    rows.sort(function(a, b) {
        if (sort === 'newest') return b.dataset.created - a.dataset.created;
        if (sort === 'oldest') return a.dataset.created - b.dataset.created;
        if (sort === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
        if (sort === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
        return 0;
    });

    rows.forEach(function(row) { tbody.appendChild(row); });

    const countLabel = document.getElementById('resultCount');
    if (countLabel) {
        countLabel.innerHTML = '<i class="fa-solid fa-filter"></i> Showing ' + visibleCount + ' of ' + rows.length + ' staff';
    }
}

document.addEventListener('DOMContentLoaded', filterStaff);

<?php if ($error && $reopenEdit): ?>
document.getElementById('editModal').classList.add('show');
<?php elseif ($error): ?>
document.getElementById('addModal').classList.add('show');
<?php endif; ?>
</script>

</body>
</html>