<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = (int) $_SESSION['admin_id'];
$fullName = $_SESSION['full_name'] ?? 'Admin';
$initial  = strtoupper(substr($fullName, 0, 1));

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $task_name = trim($_POST['task_name'] ?? '');
    $area      = trim($_POST['area'] ?? '');
    $frequency = $_POST['frequency'] ?? 'daily';
    $staff_id  = (int) ($_POST['staff_id'] ?? 0);
    $date      = $_POST['assigned_date'] ?? date('Y-m-d');
    $due_time  = $_POST['due_time'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    if ($task_name === '' || $area === '' || $staff_id <= 0) {
        $error = 'Task name, area, and staff are required.';
    } elseif (!in_array($frequency, ['daily', 'weekly'], true)) {
        $error = 'Invalid frequency.';
    } else {
        $dueTimeVal = $due_time !== '' ? $due_time : null;

        $stmt = mysqli_prepare($conn,
            "INSERT INTO cleaning_assignments
             (task_name, area, frequency, staff_id, assigned_by, assigned_date, due_time, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sssiisss",
            $task_name, $area, $frequency, $staff_id, $admin_id, $date, $dueTimeVal, $notes);

        if (mysqli_stmt_execute($stmt)) {
            $success = 'Task assigned successfully.';
        } else {
            $error = 'Failed to assign task.';
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_assignment'])) {
    $aid = (int) $_POST['assignment_id'];
    $stmt = mysqli_prepare($conn,
        "UPDATE cleaning_assignments SET status = 'cancelled' WHERE assignment_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $aid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $success = 'Assignment cancelled.';
}

$filterDate   = $_GET['date']   ?? date('Y-m-d');
$filterStatus = $_GET['status'] ?? '';
$filterStaff  = isset($_GET['staff']) ? (int) $_GET['staff'] : 0;

$staffList = [];
$res = mysqli_query($conn, "SELECT staff_id, full_name FROM staff ORDER BY full_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $staffList[] = $row;
}

$today = date('Y-m-d');
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'missed' => 0];
$res = mysqli_query($conn,
    "SELECT status, COUNT(*) AS c FROM cleaning_assignments
     WHERE assigned_date = '$today' GROUP BY status");
while ($row = mysqli_fetch_assoc($res)) {
    $stats[$row['status']] = (int) $row['c'];
    $stats['total'] += (int) $row['c'];
}

$sql = "SELECT a.*, s.full_name AS staff_name
        FROM cleaning_assignments a
        INNER JOIN staff s ON s.staff_id = a.staff_id
        WHERE a.assigned_date = ?";
$params = [$filterDate];
$types  = "s";

if ($filterStatus !== '' && in_array($filterStatus, ['pending','in_progress','completed','missed','cancelled'], true)) {
    $sql .= " AND a.status = ?";
    $params[] = $filterStatus;
    $types   .= "s";
}
if ($filterStaff > 0) {
    $sql .= " AND a.staff_id = ?";
    $params[] = $filterStaff;
    $types   .= "i";
}
$sql .= " ORDER BY
            FIELD(a.status, 'in_progress','pending','completed','missed','cancelled'),
            a.due_time ASC, a.assignment_id DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$assignments = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cleaning Assignments — Perch &amp; Pour Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
    body {
        font-family: Georgia, 'Times New Roman', serif;
        background-color:
        color:
    }

    .table thead th {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color:
        font-weight: 700;
        border-bottom: 2px solid
        white-space: nowrap;
    }
    .table tbody td { vertical-align: middle; font-size: 14px; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 3px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .status-pill.pending     { background:
    .status-pill.in_progress { background:
    .status-pill.completed   { background:
    .status-pill.missed      { background:
    .status-pill.cancelled   { background:

    .freq-badge {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 700;
    }
    .freq-badge.daily  { background:
    .freq-badge.weekly { background:

    .avatar-sm {
        width: 30px; height: 30px; border-radius: 50%;
        background:
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 12px; flex-shrink: 0;
    }

    .nav-tabs .nav-link {
        border-radius: 0; color:
    }
    .nav-tabs .nav-link.active {
        color:
    }

    .proof-thumb {
        width: 42px; height: 42px; object-fit: cover;
        border: 1px solid
    }
</style>
</head>
<body>

<div class="d-flex min-vh-100">

<?php include '../include/admin_sidebar.php'; ?>

<div class="flex-grow-1 min-w-0">

    <div class="d-flex align-items-center gap-3 bg-white border-bottom px-4 py-3">
        <button type="button" class="btn btn-outline-dark d-lg-none" onclick="openSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1 class="h4 fw-bold mb-0">Cleaning Assignments</h1>
            <div class="small text-secondary"><?= date("l, F j, Y") ?></div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <?php if ($success): ?>
            <div class="alert alert-success rounded-0 border-0 small py-2"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-0 border-0 small py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-list-check"></i><span>Total Today</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['total'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-hourglass-half text-warning"></i><span>Pending</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['pending'] + $stats['in_progress'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-circle-check text-success"></i><span>Completed</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['completed'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-triangle-exclamation text-danger"></i><span>Missed</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['missed'] ?></div>
                </div></div>
            </div>
        </div>

        <!-- TABS -->
        <ul class="nav nav-tabs mb-0" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-monitoring" type="button">
                    <i class="fa-solid fa-chart-line me-1"></i> Monitoring
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-assign" type="button">
                    <i class="fa-solid fa-user-check me-1"></i> Assign Task
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 bg-white p-3 p-md-4">

            <!-- ==================== TAB 1: MONITORING ==================== -->
            <div class="tab-pane fade show active" id="pane-monitoring">

                <form method="get" id="filterForm" class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-secondary mb-1">Date</label>
                        <input type="date" name="date" class="form-control form-control-sm rounded-0 border-dark-subtle"
                               value="<?= htmlspecialchars($filterDate) ?>"
                               onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-secondary mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm rounded-0 border-dark-subtle"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="">All Statuses</option>
                            <?php foreach (['pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','missed'=>'Missed','cancelled'=>'Cancelled'] as $k=>$v): ?>
                                <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-secondary mb-1">Staff</label>
                        <select name="staff" class="form-select form-select-sm rounded-0 border-dark-subtle"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="0">All Staff</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['staff_id'] ?>" <?= $filterStaff === (int)$s['staff_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <a href="cleaning.php" class="btn btn-outline-dark btn-sm rounded-0 w-100">
                            <i class="fa-solid fa-rotate me-1"></i> Reset
                        </a>
                    </div>
                </form>

                <?php if (mysqli_num_rows($assignments) === 0): ?>
                    <div class="border border-dark-subtle text-center text-secondary py-5 px-3">
                        <i class="fa-solid fa-inbox d-block mb-2 fs-4"></i>
                        No assignments found for this filter.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Task</th>
                                    <th>Area</th>
                                    <th>Assigned To</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th>Proof</th>
                                    <th>Remarks</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($r = mysqli_fetch_assoc($assignments)):
                                    $statusKey = $r['status'];
                                    $statusLbl = ucwords(str_replace('_',' ', $statusKey));
                                    $due = $r['due_time'] ? date('h:i A', strtotime($r['due_time'])) : '—';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($r['task_name']) ?></div>
                                            <span class="freq-badge <?= $r['frequency'] ?>"><?= $r['frequency'] ?></span>
                                        </td>
                                        <td class="small text-secondary"><?= htmlspecialchars($r['area']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="avatar-sm"><?= htmlspecialchars(strtoupper(substr($r['staff_name'],0,1))) ?></span>
                                                <span class="small fw-semibold"><?= htmlspecialchars($r['staff_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="small"><?= $due ?></td>
                                        <td><span class="status-pill <?= $statusKey ?>"><?= $statusLbl ?></span></td>
                                        <td>
                                            <?php if (!empty($r['proof_image'])): ?>
                                                <a href="../<?= htmlspecialchars($r['proof_image']) ?>" target="_blank">
                                                    <img src="../<?= htmlspecialchars($r['proof_image']) ?>" class="proof-thumb" alt="proof">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-secondary small">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-secondary" style="max-width:200px;">
                                            <?= $r['staff_remarks'] ? htmlspecialchars($r['staff_remarks']) : '—' ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (in_array($statusKey, ['pending','in_progress'], true)): ?>
                                                <form method="post" class="d-inline"
                                                      onsubmit="return confirm('Cancel this assignment?');">
                                                    <input type="hidden" name="assignment_id" value="<?= $r['assignment_id'] ?>">
                                                    <button type="submit" name="cancel_assignment"
                                                            class="btn btn-sm btn-outline-danger rounded-0" title="Cancel">
                                                        <i class="fa-solid fa-xmark"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-secondary small">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>

            <!-- ==================== TAB 2: ASSIGN ==================== -->
            <div class="tab-pane fade" id="pane-assign">

                <?php if (empty($staffList)): ?>
                    <div class="alert alert-warning rounded-0 small">
                        Wala pang staff. Mag-add muna ng staff bago mag-assign.
                    </div>
                <?php else: ?>

                    <form method="post" class="row g-3" style="max-width:760px;">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold">Task Name <span class="text-danger">*</span></label>
                            <input type="text" name="task_name"
                                   class="form-control form-control-sm rounded-0 border-dark-subtle"
                                   placeholder="e.g. Wipe tables & chairs" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-semibold">Area <span class="text-danger">*</span></label>
                            <input type="text" name="area"
                                   class="form-control form-control-sm rounded-0 border-dark-subtle"
                                   placeholder="e.g. Dining Area" required>
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-semibold">Frequency</label>
                            <select name="frequency"
                                    class="form-select form-select-sm rounded-0 border-dark-subtle">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-8">
                            <label class="form-label small fw-semibold">Assign To <span class="text-danger">*</span></label>
                            <select name="staff_id"
                                    class="form-select form-select-sm rounded-0 border-dark-subtle" required>
                                <option value="">— Select Staff —</option>
                                <?php foreach ($staffList as $s): ?>
                                    <option value="<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="assigned_date"
                                   class="form-control form-control-sm rounded-0 border-dark-subtle"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-6 col-md-4">
                            <label class="form-label small fw-semibold">Due Time</label>
                            <input type="time" name="due_time"
                                   class="form-control form-control-sm rounded-0 border-dark-subtle">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="form-control form-control-sm rounded-0 border-dark-subtle"
                                      placeholder="Optional reminders..."></textarea>
                        </div>

                        <div class="col-12">
                            <button type="submit" name="assign_task"
                                    class="btn btn-dark btn-sm rounded-0 px-4">
                                <i class="fa-solid fa-plus me-1"></i> Assign Task
                            </button>
                        </div>
                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>
</div>

</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>