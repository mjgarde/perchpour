<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$initial  = strtoupper(substr($fullName, 0, 1));

$filterDate  = $_GET['date']  ?? date('Y-m-d');
$filterStaff = isset($_GET['staff']) ? (int) $_GET['staff'] : 0;
$search      = trim($_GET['q'] ?? '');

$staffList = [];
$res = mysqli_query($conn, "SELECT staff_id, full_name FROM staff ORDER BY full_name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $staffList[] = $row;
}

$staffFilterLabel = 'All Staff';
foreach ($staffList as $s) {
    if ((int) $s['staff_id'] === $filterStaff) {
        $staffFilterLabel = $s['full_name'];
        break;
    }
}

$sql = "SELECT a.attendance_id, a.attendance_date, a.time_in, a.time_out,
               s.staff_id, s.full_name, s.username
        FROM attendance a
        INNER JOIN staff s ON s.staff_id = a.staff_id
        WHERE a.attendance_date = ?";
$params = [$filterDate];
$types  = "s";

if ($filterStaff > 0) {
    $sql .= " AND a.staff_id = ?";
    $params[] = $filterStaff;
    $types   .= "i";
}

if ($search !== '') {
    $sql .= " AND s.full_name LIKE ?";
    $params[] = "%" . $search . "%";
    $types   .= "s";
}

$sql .= " ORDER BY a.time_in DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$records = mysqli_stmt_get_result($stmt);

$totalRecords = 0;
$completed    = 0;
$ongoing      = 0;
$totalMinutes = 0;
$rows         = [];

while ($row = mysqli_fetch_assoc($records)) {
    $totalRecords++;

    if ($row['time_in'] && $row['time_out']) {
        $completed++;
        $totalMinutes += (strtotime($row['time_out']) - strtotime($row['time_in'])) / 60;
    } elseif ($row['time_in'] && !$row['time_out']) {
        $ongoing++;
    }

    $rows[] = $row;
}
mysqli_stmt_close($stmt);

$avgHours   = $completed > 0 ? round(($totalMinutes / $completed) / 60, 1) : 0;
$dateLabel  = date('F j, Y', strtotime($filterDate));
$generated  = date('F j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance / DTR — Perch &amp; Pour Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>
    body {
        font-family: Georgia, 'Times New Roman', serif;
        background-color: #f8f9fa;
        color: #111;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 3px 10px;
        border-radius: 20px;
    }
    .status-pill.complete { background: #eaf6ec; color: #1a7f37; }
    .status-pill.ongoing  { background: #fdf3e0; color: #a86b00; }
    .status-pill.open     { background: #eef0f2; color: #444; }

    .table thead th {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        font-weight: 700;
        border-bottom: 2px solid #111;
        white-space: nowrap;
    }
    .table tbody td {
        vertical-align: middle;
        font-size: 14px;
    }
    .avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #111;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12.5px;
        flex-shrink: 0;
    }

    @media print {
        @page { size: A4 portrait; margin: 14mm 12mm; }

        body { background: #fff !important; }
        .d-print-none { display: none !important; }
        #printArea { display: block !important; }

        .print-header {
            text-align: center;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .print-header img {
            height: 62px;
            width: auto;
            margin-bottom: 6px;
        }
        .print-header .brand {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin: 0;
        }
        .print-header .doc-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #444;
            margin-top: 3px;
        }

        .print-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            margin-bottom: 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #999;
        }
        .print-meta div { line-height: 1.4; }
        .print-meta strong { font-weight: 700; }

        .print-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        .print-table th,
        .print-table td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }
        .print-table th {
            background: #f0f0f0;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: 0.05em;
        }
        .print-table td.num { text-align: right; }
        .print-table td.center { text-align: center; }
        .print-table tfoot td {
            font-weight: 700;
            background: #f8f8f8;
        }

        .print-footer {
            margin-top: 26px;
            display: flex;
            justify-content: space-between;
            font-size: 10.5px;
            color: #444;
        }
    }
</style>
</head>
<body>

<div class="d-flex min-vh-100 d-print-none">

<?php include '../include/admin_sidebar.php'; ?>

<div class="flex-grow-1 min-w-0">

    <div class="d-flex align-items-center gap-3 bg-white border-bottom px-4 py-3">
        <button type="button" class="btn btn-outline-dark d-lg-none" onclick="openSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="flex-grow-1">
            <h1 class="h4 fw-bold mb-0">Attendance / DTR</h1>
            <div class="small text-secondary">Daily Time Record ng mga staff</div>
        </div>
        <button type="button" class="btn btn-dark rounded-0 btn-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i> Print
        </button>
    </div>

    <div class="container-fluid px-4 py-4">

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-users"></i>
                            <span>Total Records</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1"><?= $totalRecords ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-circle-check text-success"></i>
                            <span>Completed</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1"><?= $completed ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-clock text-warning"></i>
                            <span>Ongoing</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1"><?= $ongoing ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <span>Avg Hours</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1"><?= $avgHours ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-dark-subtle rounded-0 mb-4">
            <div class="card-body p-3">
                <form method="get" id="filterForm" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-secondary mb-1">Date</label>
                        <input type="date" name="date"
                               class="form-control form-control-sm rounded-0 border-dark-subtle"
                               value="<?= htmlspecialchars($filterDate) ?>"
                               onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-secondary mb-1">Staff</label>
                        <select name="staff"
                                class="form-select form-select-sm rounded-0 border-dark-subtle"
                                onchange="document.getElementById('filterForm').submit()">
                            <option value="0">All Staff</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['staff_id'] ?>" <?= $filterStaff === (int) $s['staff_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-secondary mb-1">Search</label>
                        <input type="text" name="q" id="searchInput"
                               class="form-control form-control-sm rounded-0 border-dark-subtle"
                               placeholder="Type to search by name..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <a href="attendance.php" class="btn btn-outline-dark btn-sm rounded-0 w-100">
                            <i class="fa-solid fa-rotate me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <h3 class="h6 fw-bold border-bottom pb-2 mb-3">
            Records for <?= htmlspecialchars($dateLabel) ?>
        </h3>

        <?php if (empty($rows)): ?>

            <div class="border border-dark-subtle text-center text-secondary py-5 px-3">
                <i class="fa-solid fa-inbox d-block mb-2 fs-4"></i>
                No attendance records found.
            </div>

        <?php else: ?>

            <div class="card border-dark-subtle rounded-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Staff</th>
                                <th>Username</th>
                                <th>Time-In</th>
                                <th>Time-Out</th>
                                <th>Total Hours</th>
                                <th class="pe-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r):
                                $hasIn  = !empty($r['time_in']);
                                $hasOut = !empty($r['time_out']);

                                if ($hasIn && $hasOut) {
                                    $status = 'complete';
                                    $label  = 'Completed';
                                    $icon   = 'fa-circle-check';
                                    $hours  = round((strtotime($r['time_out']) - strtotime($r['time_in'])) / 3600, 2);
                                } elseif ($hasIn) {
                                    $status = 'ongoing';
                                    $label  = 'Ongoing';
                                    $icon   = 'fa-clock';
                                    $hours  = '—';
                                } else {
                                    $status = 'open';
                                    $label  = 'No Time-In';
                                    $icon   = 'fa-circle-dot';
                                    $hours  = '—';
                                }
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-sm"><?= htmlspecialchars(strtoupper(substr($r['full_name'], 0, 1))) ?></span>
                                            <span class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-secondary small"><?= htmlspecialchars($r['username'] ?? '—') ?></td>
                                    <td><?= $hasIn ? date('h:i A', strtotime($r['time_in'])) : '—' ?></td>
                                    <td><?= $hasOut ? date('h:i A', strtotime($r['time_out'])) : '—' ?></td>
                                    <td><?= $hours === '—' ? '—' : $hours . ' hrs' ?></td>
                                    <td class="pe-3">
                                        <span class="status-pill <?= $status ?>">
                                            <i class="fa-solid <?= $icon ?>"></i> <?= $label ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

</div>

<div id="printArea" class="d-none d-print-block">

    <div class="print-header">
        <img src="../img/logo.png" alt="Perch &amp; Pour">
        <h1 class="brand">Perch &amp; Pour</h1>
        <div class="doc-title">Daily Time Record</div>
    </div>

    <div class="print-meta">
        <div>
            <div>Date: <strong><?= htmlspecialchars($dateLabel) ?></strong></div>
            <div>Staff: <strong><?= htmlspecialchars($staffFilterLabel) ?></strong></div>
            <?php if ($search !== ''): ?>
                <div>Search: <strong><?= htmlspecialchars($search) ?></strong></div>
            <?php endif; ?>
        </div>
        <div class="text-end">
            <div>Total Records: <strong><?= $totalRecords ?></strong></div>
            <div>Generated: <strong><?= htmlspecialchars($generated) ?></strong></div>
        </div>
    </div>

    <?php if (empty($rows)): ?>

        <p style="text-align:center; padding:40px 0; color:#666;">No attendance records found for this filter.</p>

    <?php else: ?>

        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:36px;">#</th>
                    <th>Staff Name</th>
                    <th>Username</th>
                    <th style="width:90px;">Time-In</th>
                    <th style="width:90px;">Time-Out</th>
                    <th style="width:90px;">Total Hours</th>
                    <th style="width:100px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r):
                    $hasIn  = !empty($r['time_in']);
                    $hasOut = !empty($r['time_out']);

                    if ($hasIn && $hasOut) {
                        $status = 'Completed';
                        $hours  = number_format((strtotime($r['time_out']) - strtotime($r['time_in'])) / 3600, 2);
                    } elseif ($hasIn) {
                        $status = 'Ongoing';
                        $hours  = '—';
                    } else {
                        $status = 'No Time-In';
                        $hours  = '—';
                    }
                ?>
                    <tr>
                        <td class="center"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['username'] ?? '—') ?></td>
                        <td class="center"><?= $hasIn ? date('h:i A', strtotime($r['time_in'])) : '—' ?></td>
                        <td class="center"><?= $hasOut ? date('h:i A', strtotime($r['time_out'])) : '—' ?></td>
                        <td class="num"><?= $hours === '—' ? '—' : $hours ?></td>
                        <td class="center"><?= $status ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="num">Totals:</td>
                    <td colspan="2" class="center">
                        Completed: <?= $completed ?> &nbsp;|&nbsp; Ongoing: <?= $ongoing ?>
                    </td>
                    <td class="num">Avg: <?= $avgHours ?> hrs</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="print-footer">
            <div>Perch &amp; Pour — Attendance / DTR</div>
            <div>Page 1</div>
        </div>

    <?php endif; ?>

</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const input = document.getElementById('searchInput');
        const form  = document.getElementById('filterForm');
        let timer;

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { form.submit(); }, 500);
        });

        if (input.value.length > 0) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    })();
</script>

</body>
</html>