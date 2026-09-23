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

// ===== ADD EMPLOYEE =====
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_staff'])) {
    $emp_name   = trim($_POST['full_name']);
    $emp_user   = trim($_POST['username']);
    $emp_pass   = $_POST['password'];
    $emp_address = trim($_POST['address']);
    $emp_contact = trim($_POST['contact_number']);

    if ($emp_name === '' || $emp_user === '' || $emp_pass === '' || $emp_address === '') {
        $error = "Please fill in all required fields.";
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
            } else {
                $error = "Something went wrong. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check);
    }
}

// ===== TOGGLE STATUS =====
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    mysqli_query($conn, "UPDATE staff SET status = IF(status='active','inactive','active') WHERE staff_id = $id");
    header("Location: staff.php");
    exit();
}

// ===== FETCH EMPLOYEES =====
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
        justify-content: space-between;
        gap: 14px;
        padding: 20px 32px;
        border-bottom: 1px solid var(--paper-line);
    }

    .topbar-left { display: flex; align-items: center; gap: 14px; }

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

    .topbar h1 { font-family: Georgia, serif; font-weight: 700; font-size: 24px; margin: 0; }
    .topbar .date-stamp { font-size: 12px; color: var(--ink-soft); margin-top: 2px; }

    .btn-add {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--ink);
        color: var(--card);
        border: none;
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 14px;
        padding: 10px 18px;
        cursor: pointer;
    }
    .btn-add:hover { background: var(--brown); }

    .content { padding: 30px 32px 50px; max-width: 1100px; }

    .alert {
        border: 1px solid var(--paper-line);
        padding: 10px 16px;
        font-size: 13.5px;
        margin-bottom: 20px;
    }
    .alert-success { border-color: #2C5C3F; color: #2C5C3F; background: #E7F0E8; }
    .alert-error { border-color: var(--press-red); color: var(--press-red); background: #F7E7E3; }

    .emp-table-wrap {
        background: var(--card);
        border: 1px solid var(--paper-line);
        overflow-x: auto;
    }

    table.emp-table { width: 100%; border-collapse: collapse; min-width: 620px; }

    table.emp-table th {
        text-align: left;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ink-soft);
        padding: 12px 16px;
        border-bottom: 1px solid var(--paper-line);
        font-weight: 700;
    }

    table.emp-table td {
        padding: 13px 16px;
        border-bottom: 1px solid var(--paper-line);
        font-size: 13.5px;
        vertical-align: middle;
    }

    table.emp-table tr:last-child td { border-bottom: none; }

    .emp-name { display: flex; align-items: center; gap: 10px; }

    .emp-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--brown);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 12px;
        flex-shrink: 0;
    }

    .status-pill {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 3px 10px;
        border: 1px solid;
    }
    .status-active { color: #2C5C3F; border-color: #2C5C3F; }
    .status-inactive { color: var(--ink-soft); border-color: var(--paper-line); }

    .link-toggle {
        color: var(--brown);
        text-decoration: none;
        font-size: 12.5px;
    }
    .link-toggle:hover { color: var(--press-red); }

    .empty-note {
        border: 1px solid var(--paper-line);
        color: var(--ink-soft);
        font-size: 13px;
        padding: 30px;
        text-align: center;
    }

    /* ===== MODAL ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(33,30,24,0.45);
        z-index: 60;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.show { display: flex; }

    .modal-box {
        background: var(--card);
        border: 1px solid var(--ink);
        box-shadow: 4px 4px 0 rgba(33,30,24,0.14);
        width: 100%;
        max-width: 420px;
        padding: 26px 26px 22px;
    }

    .modal-box h3 {
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 19px;
        margin: 0 0 4px;
    }

    .modal-box .modal-sub {
        font-size: 12.5px;
        color: var(--ink-soft);
        margin: 0 0 18px;
    }

    .field { margin-bottom: 14px; }

    .field label {
        display: block;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ink-soft);
        font-weight: 700;
        margin-bottom: 5px;
    }

    .field input, .field select {
        width: 100%;
        border: none;
        border-bottom: 1.5px solid var(--ink);
        background: transparent;
        font-family: Georgia, serif;
        font-size: 14px;
        color: var(--ink);
        padding: 6px 2px;
        outline: none;
    }
    .field input:focus, .field select:focus { border-bottom-color: var(--press-red); }

    .modal-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .btn-cancel {
        flex: 1;
        background: transparent;
        border: 1px solid var(--paper-line);
        color: var(--ink-soft);
        font-family: Georgia, serif;
        font-size: 14px;
        padding: 10px;
        cursor: pointer;
    }
    .btn-cancel:hover { border-color: var(--ink); color: var(--ink); }

    .btn-submit {
        flex: 1;
        background: var(--ink);
        border: none;
        color: var(--card);
        font-family: Georgia, serif;
        font-weight: 700;
        font-size: 14px;
        padding: 10px;
        cursor: pointer;
    }
    .btn-submit:hover { background: var(--press-red); }

    @media (max-width: 820px) {
        .menu-toggle { display: inline-flex; align-items: center; justify-content: center; }
        .content { padding: 22px 18px 40px; }
        .topbar { padding: 16px 18px; flex-wrap: wrap; }
        .btn-add span { display: none; }
    }
</style>
</head>
<body>

<?php include '../include/sidebar.php'; ?>

<div class="main">

    <div class="topbar">
        <div class="topbar-left">
            <button type="button" class="menu-toggle" onclick="openSidebar()"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Staff</h1>
                <div class="date-stamp"><?php echo count($staff); ?> staff on record</div>
            </div>
        </div>
        <button type="button" class="btn-add" onclick="document.getElementById('addModal').classList.add('show')">
            <i class="fa-solid fa-plus"></i> <span>Add Staff</span>
        </button>
    </div>

    <div class="content">

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (count($staff) === 0): ?>
            <div class="empty-note">
                <i class="fa-solid fa-users"></i>
                No staff added yet. Click "Add Staff" to create a staff account.
            </div>
        <?php else: ?>
            <div class="emp-table-wrap">
                <table class="emp-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Date Added</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $emp): ?>
                        <tr>
                            <td>
                                <div class="emp-name">
                                    <div class="emp-avatar"><?php echo htmlspecialchars(strtoupper(substr($emp['full_name'], 0, 1))); ?></div>
                                    <?php echo htmlspecialchars($emp['full_name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($emp['username']); ?></td>
                            <td><?php echo htmlspecialchars($emp['address']); ?></td>
                            <td><?php echo htmlspecialchars($emp['contact_number'] ?: '—'); ?></td>
                            <td>
                                <span class="status-pill status-<?php echo $emp['status']; ?>">
                                    <?php echo ucfirst($emp['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($emp['created_at'])); ?></td>
                            <td>
                                <a class="link-toggle" href="staff.php?toggle=<?php echo $emp['staff_id']; ?>">
                                    <?php echo $emp['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ===== ADD EMPLOYEE MODAL ===== -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3>Add Staff</h3>
        <p class="modal-sub">Create a staff account for the Perch &amp; Pour system.</p>

        <form method="POST" action="">
            <div class="field">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required>
            </div>

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" required>
            </div>

            <div class="field">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" placeholder="09XX XXX XXXX">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('addModal').classList.remove('show')">Cancel</button>
                <button type="submit" name="add_staff" class="btn-submit">Create Account</button>
            </div>
        </form>
    </div>
</div>

<?php if ($error || $success): ?>
<script>document.getElementById('addModal').classList.add('show');</script>
<?php endif; ?>

</body>
</html>