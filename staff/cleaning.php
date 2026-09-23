<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit();
}

$staff_id = (int) $_SESSION['staff_id'];
$fullName = $_SESSION['full_name'] ?? 'Staff';
$initial  = strtoupper(substr($fullName, 0, 1));

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_task'])) {
    $aid = (int) $_POST['assignment_id'];

    $stmt = mysqli_prepare($conn,
        "UPDATE cleaning_assignments
         SET status = 'in_progress', started_at = NOW()
         WHERE assignment_id = ? AND staff_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt, "ii", $aid, $staff_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $success = 'Task started.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_task'])) {
    $aid     = (int) $_POST['assignment_id'];
    $remarks = trim($_POST['staff_remarks'] ?? '');
    $proofPath = null;

    if (!empty($_FILES['proof_image']['name']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $error = 'Proof image must be JPG, PNG, or WEBP.';
        } elseif ($_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
            $error = 'Proof image must be 5MB or smaller.';
        } else {
            $uploadDir = '../uploads/cleaning/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = 'clean_' . $aid . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $target   = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $target)) {
                $proofPath = 'uploads/cleaning/' . $filename;
            } else {
                $error = 'Failed to upload proof image.';
            }
        }
    }

    if ($error === '') {
        $stmt = mysqli_prepare($conn,
            "UPDATE cleaning_assignments
             SET status = 'completed', completed_at = NOW(),
                 staff_remarks = ?, proof_image = COALESCE(?, proof_image)
             WHERE assignment_id = ? AND staff_id = ?
                   AND status IN ('pending','in_progress')");
        mysqli_stmt_bind_param($stmt, "ssii", $remarks, $proofPath, $aid, $staff_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $success = 'Task marked as completed. Salamat!';
    }
}

$today = date('Y-m-d');
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];

$stmt = mysqli_prepare($conn,
    "SELECT status, COUNT(*) AS c FROM cleaning_assignments
     WHERE staff_id = ? AND assigned_date = ?
     GROUP BY status");
mysqli_stmt_bind_param($stmt, "is", $staff_id, $today);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $stats[$row['status']] = (int) $row['c'];
    $stats['total'] += (int) $row['c'];
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn,
    "SELECT * FROM cleaning_assignments
     WHERE staff_id = ? AND assigned_date = ?
       AND status IN ('pending','in_progress','missed')
     ORDER BY FIELD(status, 'in_progress','pending','missed'),
              due_time ASC, assignment_id ASC");
mysqli_stmt_bind_param($stmt, "is", $staff_id, $today);
mysqli_stmt_execute($stmt);
$todayTasks = mysqli_stmt_get_result($stmt);

$stmt = mysqli_prepare($conn,
    "SELECT * FROM cleaning_assignments
     WHERE staff_id = ?
       AND status IN ('completed','missed')
     ORDER BY completed_at DESC, assigned_date DESC
     LIMIT 50");
mysqli_stmt_bind_param($stmt, "i", $staff_id);
mysqli_stmt_execute($stmt);
$history = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Cleaning Tasks — Perch &amp; Pour Staff</title>
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
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 3px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }
    .status-pill.pending     { background: #eef0f2; color: #444; }
    .status-pill.in_progress { background: #fdf3e0; color: #a86b00; }
    .status-pill.completed   { background: #eaf6ec; color: #1a7f37; }
    .status-pill.missed      { background: #fdecec; color: #b91c1c; }

    .freq-badge {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 700;
    }
    .freq-badge.daily  { background: #111; color: #fff; }
    .freq-badge.weekly { background: #e5e7eb; color: #111; }

    .task-card {
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 16px 18px;
        border-radius: 4px;
        transition: box-shadow 0.12s;
    }
    .task-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }

    .task-title { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
    .task-meta  { font-size: 13px; color: #6b7280; }

    .proof-thumb {
        width: 42px; height: 42px; object-fit: cover;
        border: 1px solid #dee2e6; cursor: pointer; border-radius: 3px;
    }

    .empty-box {
        border: 1px dashed #dee2e6;
        color: #6b7280;
        text-align: center;
        padding: 40px 20px;
        border-radius: 4px;
    }

    .nav-tabs .nav-link {
        border-radius: 0; color: #444; font-weight: 600; font-size: 13.5px;
    }
    .nav-tabs .nav-link.active {
        color: #111; border-color: #111 #111 #fff; border-top-width: 2px;
    }

    .history-table tbody tr {
        cursor: pointer;
        transition: background 0.12s;
    }
    .history-table tbody tr:hover { background: #f5f5f5; }
    .history-table tbody tr:hover td { background: #f5f5f5; }
</style>
</head>
<body>

<div class="d-flex min-vh-100">

<?php include '../include/staff_sidebar.php'; ?>

<div class="flex-grow-1 min-w-0">

    <div class="d-flex align-items-center gap-3 bg-white border-bottom px-4 py-3">
        <button type="button" class="btn btn-outline-dark d-lg-none" onclick="openSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div>
            <h1 class="h4 fw-bold mb-0">My Cleaning Tasks</h1>
            <div class="small text-secondary"><?= date("l, F j, Y") ?></div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <?php if ($success): ?>
            <div class="alert alert-success rounded-0 border-0 small py-2">
                <i class="fa-solid fa-circle-check me-1"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-0 border-0 small py-2">
                <i class="fa-solid fa-circle-exclamation me-1"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-list-check"></i><span>Assigned Today</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['total'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-hourglass-half text-warning"></i><span>To Do</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['pending'] + $stats['in_progress'] ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100"><div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                        <i class="fa-solid fa-spinner text-warning"></i><span>In Progress</span>
                    </div>
                    <div class="fs-3 fw-bold lh-1"><?= $stats['in_progress'] ?></div>
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
        </div>

        <ul class="nav nav-tabs mb-0" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-today" type="button">
                    <i class="fa-solid fa-broom me-1"></i> Today
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-history" type="button">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> History
                </button>
            </li>
        </ul>

        <div class="tab-content border border-top-0 bg-white p-3 p-md-4">

            <div class="tab-pane fade show active" id="pane-today">

                <?php if (mysqli_num_rows($todayTasks) === 0): ?>

                    <div class="empty-box">
                        <i class="fa-solid fa-mug-hot d-block mb-2 fs-3"></i>
                        No cleaning task.
                    </div>

                <?php else: ?>

                    <div class="row g-3">
                        <?php while ($t = mysqli_fetch_assoc($todayTasks)):
                            $statusKey = $t['status'];
                            $statusLbl = ucwords(str_replace('_',' ', $statusKey));
                            $due = $t['due_time'] ? date('h:i A', strtotime($t['due_time'])) : null;
                        ?>
                            <div class="col-12 col-lg-6">
                                <div class="task-card">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <div class="task-title"><?= htmlspecialchars($t['task_name']) ?></div>
                                            <div class="task-meta">
                                                <i class="fa-solid fa-location-dot me-1"></i>
                                                <?= htmlspecialchars($t['area']) ?>
                                            </div>
                                        </div>
                                        <span class="status-pill <?= $statusKey ?>"><?= $statusLbl ?></span>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                        <span class="freq-badge <?= $t['frequency'] ?>"><?= $t['frequency'] ?></span>
                                        <?php if ($due): ?>
                                            <span class="task-meta">
                                                <i class="fa-regular fa-clock me-1"></i>Due <?= $due ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($t['started_at']): ?>
                                            <span class="task-meta">
                                                <i class="fa-solid fa-play me-1"></i>Started <?= date('h:i A', strtotime($t['started_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($t['notes'])): ?>
                                        <div class="small text-secondary mb-3">
                                            <i class="fa-solid fa-note-sticky me-1"></i>
                                            <?= htmlspecialchars($t['notes']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex gap-2">
                                        <?php if ($statusKey === 'pending'): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="assignment_id" value="<?= $t['assignment_id'] ?>">
                                                <button type="submit" name="start_task"
                                                        class="btn btn-dark btn-sm rounded-0">
                                                    <i class="fa-solid fa-play me-1"></i> Start Task
                                                </button>
                                            </form>
                                        <?php elseif ($statusKey === 'in_progress'): ?>
                                            <button type="button" class="btn btn-success btn-sm rounded-0"
                                                    data-bs-toggle="modal" data-bs-target="#completeModal"
                                                    data-id="<?= $t['assignment_id'] ?>"
                                                    data-name="<?= htmlspecialchars($t['task_name'], ENT_QUOTES) ?>">
                                                <i class="fa-solid fa-circle-check me-1"></i> Mark as Done
                                            </button>
                                        <?php elseif ($statusKey === 'missed'): ?>
                                            <span class="text-danger small fw-semibold">
                                                <i class="fa-solid fa-triangle-exclamation me-1"></i>Missed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                <?php endif; ?>

            </div>

            <div class="tab-pane fade" id="pane-history">

                <?php if (mysqli_num_rows($history) === 0): ?>

                    <div class="empty-box">
                        <i class="fa-solid fa-clock-rotate-left d-block mb-2 fs-3"></i>
                        No history.
                    </div>

                <?php else: ?>

                    <p class="small text-secondary mb-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Click any row to see full details.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 history-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Task</th>
                                    <th>Area</th>
                                    <th>Completed</th>
                                    <th>Status</th>
                                    <th>Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($h = mysqli_fetch_assoc($history)):
                                    $statusKey = $h['status'];
                                    $statusLbl = ucwords($statusKey);
                                ?>
                                    <tr data-bs-toggle="modal" data-bs-target="#detailModal"
                                        data-date="<?= htmlspecialchars(date('F j, Y', strtotime($h['assigned_date']))) ?>"
                                        data-task="<?= htmlspecialchars($h['task_name'], ENT_QUOTES) ?>"
                                        data-area="<?= htmlspecialchars($h['area'], ENT_QUOTES) ?>"
                                        data-frequency="<?= htmlspecialchars($h['frequency'], ENT_QUOTES) ?>"
                                        data-status="<?= $statusLbl ?>"
                                        data-status-key="<?= $statusKey ?>"
                                        data-due="<?= $h['due_time'] ? date('h:i A', strtotime($h['due_time'])) : '' ?>"
                                        data-started="<?= $h['started_at'] ? date('F j, Y h:i A', strtotime($h['started_at'])) : '' ?>"
                                        data-completed="<?= $h['completed_at'] ? date('F j, Y h:i A', strtotime($h['completed_at'])) : '' ?>"
                                        data-notes="<?= htmlspecialchars($h['notes'] ?? '', ENT_QUOTES) ?>"
                                        data-remarks="<?= htmlspecialchars($h['staff_remarks'] ?? '', ENT_QUOTES) ?>"
                                        data-proof="<?= !empty($h['proof_image']) ? '../' . htmlspecialchars($h['proof_image'], ENT_QUOTES) : '' ?>">
                                        <td class="small"><?= date('M d, Y', strtotime($h['assigned_date'])) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($h['task_name']) ?></td>
                                        <td class="small text-secondary"><?= htmlspecialchars($h['area']) ?></td>
                                        <td class="small">
                                            <?= $h['completed_at'] ? date('h:i A', strtotime($h['completed_at'])) : '—' ?>
                                        </td>
                                        <td><span class="status-pill <?= $statusKey ?>"><?= $statusLbl ?></span></td>
                                        <td>
                                            <?php if (!empty($h['proof_image'])): ?>
                                                <img src="../<?= htmlspecialchars($h['proof_image']) ?>" class="proof-thumb" alt="proof">
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

        </div>

    </div>
</div>

</div>

<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title h6 fw-bold mb-0">
                        <i class="fa-solid fa-circle-check text-success me-1"></i>
                        Mark Task as Complete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-secondary mb-3">
                        Task: <strong class="text-dark" id="modalTaskName">—</strong>
                    </p>

                    <input type="hidden" name="assignment_id" id="modalAssignmentId">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">
                            Proof Image <span class="text-secondary">(optional, max 5MB)</span>
                        </label>
                        <input type="file" name="proof_image" accept="image/*"
                               class="form-control form-control-sm rounded-0 border-dark-subtle">
                        <div class="form-text small">
                            JPG, PNG, o WEBP. Kung ayaw mong mag-upload, okay lang.
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-semibold">Remarks</label>
                        <textarea name="staff_remarks" rows="3"
                                  class="form-control form-control-sm rounded-0 border-dark-subtle"
                                  placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-dark btn-sm rounded-0"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="complete_task"
                            class="btn btn-success btn-sm rounded-0">
                        <i class="fa-solid fa-check me-1"></i> Complete Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-0">
            <div class="modal-header border-bottom">
                <h5 class="modal-title h6 fw-bold mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Task Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <div class="h5 fw-bold mb-1" id="dTask">—</div>
                        <div class="small text-secondary">
                            <i class="fa-solid fa-location-dot me-1"></i>
                            <span id="dArea">—</span>
                        </div>
                    </div>
                    <span class="status-pill" id="dStatusPill">—</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="small text-secondary text-uppercase" style="letter-spacing:0.05em; font-size:10.5px;">Date</div>
                        <div class="fw-semibold small" id="dDate">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-secondary text-uppercase" style="letter-spacing:0.05em; font-size:10.5px;">Frequency</div>
                        <div class="fw-semibold small" id="dFrequency">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-secondary text-uppercase" style="letter-spacing:0.05em; font-size:10.5px;">Due</div>
                        <div class="fw-semibold small" id="dDue">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-secondary text-uppercase" style="letter-spacing:0.05em; font-size:10.5px;">Completed</div>
                        <div class="fw-semibold small" id="dCompleted">—</div>
                    </div>
                </div>

                <hr>

                <div class="mb-4" id="dStartedWrap">
                    <div class="small text-secondary text-uppercase mb-1" style="letter-spacing:0.05em; font-size:10.5px;">Started At</div>
                    <div class="small" id="dStarted">—</div>
                </div>

                <div class="mb-4" id="dNotesWrap">
                    <div class="small text-secondary text-uppercase mb-1" style="letter-spacing:0.05em; font-size:10.5px;">Admin Notes</div>
                    <div class="small" id="dNotes">—</div>
                </div>

                <div class="mb-4" id="dRemarksWrap">
                    <div class="small text-secondary text-uppercase mb-1" style="letter-spacing:0.05em; font-size:10.5px;">Your Remarks</div>
                    <div class="small" id="dRemarks">—</div>
                </div>

                <div id="dProofWrap">
                    <div class="small text-secondary text-uppercase mb-2" style="letter-spacing:0.05em; font-size:10.5px;">Proof Image</div>
                    <div id="dProofNone" class="small text-secondary fst-italic">No proof image submitted.</div>
                    <a id="dProofLink" href="#" target="_blank" class="d-none">
                        <img id="dProofImg" src="" alt="proof"
                             style="max-width:100%; max-height:420px; border:1px solid #dee2e6; border-radius:4px;">
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('completeModal').addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('modalAssignmentId').value = btn.dataset.id;
        document.getElementById('modalTaskName').textContent = btn.dataset.name;
    });

    document.getElementById('detailModal').addEventListener('show.bs.modal', function (event) {
        const tr = event.relatedTarget;

        document.getElementById('dTask').textContent      = tr.dataset.task || '—';
        document.getElementById('dArea').textContent      = tr.dataset.area || '—';
        document.getElementById('dDate').textContent      = tr.dataset.date || '—';
        document.getElementById('dFrequency').textContent = tr.dataset.frequency || '—';
        document.getElementById('dDue').textContent       = tr.dataset.due || '—';
        document.getElementById('dCompleted').textContent = tr.dataset.completed || '—';
        document.getElementById('dStarted').textContent   = tr.dataset.started || '—';
        document.getElementById('dNotes').textContent     = tr.dataset.notes || '—';
        document.getElementById('dRemarks').textContent   = tr.dataset.remarks || '—';

        const pill = document.getElementById('dStatusPill');
        pill.textContent = tr.dataset.status || '—';
        pill.className = 'status-pill ' + (tr.dataset.statusKey || '');

        document.getElementById('dStartedWrap').style.display = tr.dataset.started ? '' : 'none';
        document.getElementById('dNotesWrap').style.display   = tr.dataset.notes   ? '' : 'none';
        document.getElementById('dRemarksWrap').style.display = tr.dataset.remarks ? '' : 'none';

        const proof = tr.dataset.proof || '';
        const none  = document.getElementById('dProofNone');
        const link  = document.getElementById('dProofLink');
        const img   = document.getElementById('dProofImg');

        if (proof) {
            img.src = proof;
            link.href = proof;
            link.classList.remove('d-none');
            none.classList.add('d-none');
        } else {
            link.classList.add('d-none');
            none.classList.remove('d-none');
        }
    });
</script>

</body>
</html>