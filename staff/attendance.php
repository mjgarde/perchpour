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
$today    = date('Y-m-d');

/* ---------- AJAX: any staff QR is accepted (kiosk mode) ---------- */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['scan_qr'])) {
    header('Content-Type: application/json');

    $scanned_code = trim($_POST['scan_qr']);

    // 1. Look up by QR code — ANY staff, not just the logged-in user.
    $stmt = mysqli_prepare($conn, "SELECT staff_id FROM staff WHERE qr_code = ?");
    mysqli_stmt_bind_param($stmt, "s", $scanned_code);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $staffRow = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$staffRow) {
        echo json_encode(['status' => 'error', 'message' => 'QR code not recognized.']);
        exit();
    }

    $sid = (int) $staffRow['staff_id'];

    // 2. Check today's attendance for that staff.
    $stmt = mysqli_prepare($conn, "SELECT * FROM attendance WHERE staff_id = ? AND attendance_date = ?");
    mysqli_stmt_bind_param($stmt, "is", $sid, $today);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $record = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$record) {
        $stmt = mysqli_prepare($conn, "INSERT INTO attendance (staff_id, attendance_date, time_in) VALUES (?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "is", $sid, $today);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Generic response — no name, no exact time (kiosk privacy).
        echo json_encode(['status' => 'success', 'action' => 'time_in', 'message' => 'Time-In recorded.']);
        exit();
    }

    if ($record['time_in'] && !$record['time_out']) {
        $stmt = mysqli_prepare($conn, "UPDATE attendance SET time_out = NOW() WHERE attendance_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $record['attendance_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['status' => 'success', 'action' => 'time_out', 'message' => 'Time-Out recorded.']);
        exit();
    }

    echo json_encode(['status' => 'error', 'message' => 'Attendance already completed for today.']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance — Perch &amp; Pour Staff</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<style>
    body {
        font-family: Georgia, 'Times New Roman', serif;
        background-color: #f8f9fa;
        color: #111;
    }

    #qr-reader {
        width: 100%;
        max-width: 460px;
        margin: 0 auto;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        overflow: hidden;
    }
    #qr-reader img { display: none; }

    .scan-status {
        min-height: 24px;
        font-size: 14px;
        text-align: center;
    }
    .scan-status.ok  { color: #1a7f37; font-weight: 600; }
    .scan-status.err { color: #d70015; font-weight: 600; }

    .scan-flash {
        position: fixed;
        inset: 0;
        background: rgba(26, 127, 55, 0.92);
        color: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 1080;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease;
    }
    .scan-flash.show { opacity: 1; pointer-events: auto; }
    .scan-flash i { font-size: 84px; margin-bottom: 18px; }
    .scan-flash .flash-text { font-size: 26px; font-weight: 700; letter-spacing: 0.02em; }

    .scan-flash.err { background: rgba(215, 0, 21, 0.92); }

    .kiosk-hint {
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 600;
    }
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
            <h1 class="h4 fw-bold mb-0">Attendance Kiosk</h1>
            <div class="small text-secondary"><?php echo date("l, F j, Y"); ?></div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <div class="row g-4">

            <div class="col-12 col-xl-8">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-header bg-white border-bottom py-3 px-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-qrcode"></i>
                                <span class="fw-bold">Scan Your QR Code</span>
                            </div>
                            <span class="kiosk-hint">
                                <i class="fa-solid fa-circle text-success me-1" style="font-size:8px;"></i> Camera Active
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4 p-md-5 d-flex flex-column align-items-center justify-content-center">
                        <div id="qr-reader"></div>
                        <div class="scan-status mt-4" id="scanStatus">
                            <span class="text-secondary">Position your QR code in front of the camera.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-dark-subtle rounded-0 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-3">How to use</h2>
                        <ol class="small text-secondary mb-0 ps-3">
                            <li class="mb-2">Face your personal QR code to the camera.</li>
                            <li class="mb-2">First scan of the day records your <strong>Time-In</strong>.</li>
                            <li class="mb-2">Second scan records your <strong>Time-Out</strong>.</li>
                            <li>One confirmation per scan — no need to tap anything.</li>
                        </ol>
                    </div>
                </div>

                <div class="card border-dark-subtle rounded-0">
                    <div class="card-body p-4 text-center">
                        <div class="kiosk-hint mb-3">Last Scan Result</div>
                        <div id="scanIdle">
                            <i class="fa-solid fa-camera text-secondary d-block fs-2 mb-2"></i>
                            <div class="small text-secondary">Waiting for a scan...</div>
                        </div>
                        <div id="scanResult" class="d-none">
                            <i id="resultIcon" class="fa-solid fa-circle-check text-success d-block fs-1 mb-2"></i>
                            <div id="resultText" class="fw-bold">Time-In recorded.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

</div>

<div class="scan-flash" id="scanFlash">
    <i id="flashIcon" class="fa-solid fa-circle-check"></i>
    <div class="flash-text" id="flashText">Time-In recorded.</div>
</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
let scanning = false;
let html5QrCode = null;
let cooldownUntil = 0;

function setStatus(message, kind) {
    const el = document.getElementById('scanStatus');
    el.textContent = message;
    el.className = 'scan-status ' + (kind || '');
}

function flashScreen(action, message) {
    const flash = document.getElementById('scanFlash');
    const icon  = document.getElementById('flashIcon');
    const text  = document.getElementById('flashText');

    flash.classList.remove('err');
    icon.className = 'fa-solid fa-circle-check';
    text.textContent = message;

    flash.classList.add('show');
    setTimeout(function () { flash.classList.remove('show'); }, 1400);
}

function flashError(message) {
    const flash = document.getElementById('scanFlash');
    const icon  = document.getElementById('flashIcon');
    const text  = document.getElementById('flashText');

    flash.classList.add('err');
    icon.className = 'fa-solid fa-circle-xmark';
    text.textContent = message;

    flash.classList.add('show');
    setTimeout(function () { flash.classList.remove('show'); }, 1400);
}

function showResultCard(kind, message) {
    document.getElementById('scanIdle').classList.add('d-none');
    const box = document.getElementById('scanResult');
    const icon = document.getElementById('resultIcon');
    const text = document.getElementById('resultText');

    box.classList.remove('d-none');
    if (kind === 'ok') {
        icon.className = 'fa-solid fa-circle-check text-success d-block fs-1 mb-2';
    } else {
        icon.className = 'fa-solid fa-circle-xmark text-danger d-block fs-1 mb-2';
    }
    text.textContent = message;
}

function onScanSuccess(decodedText) {
    const now = Date.now();
    if (scanning || now < cooldownUntil) return;
    scanning = true;
    cooldownUntil = now + 2500;

    setStatus('Verifying...', '');

    const formData = new FormData();
    formData.append('scan_qr', decodedText);

    fetch('attendance.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 'success') {
                setStatus(data.message, 'ok');
                showResultCard('ok', data.message);
                flashScreen(data.action, data.message);
            } else {
                setStatus(data.message, 'err');
                showResultCard('err', data.message);
                flashError(data.message);
            }
        })
        .catch(function () {
            setStatus('Network error. Please try again.', 'err');
            flashError('Network error.');
        })
        .finally(function () {
            setTimeout(function () { scanning = false; }, 800);
        });
}

function startScanner() {
    html5QrCode = new Html5Qrcode("qr-reader");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 260, height: 260 } },
        onScanSuccess
    ).catch(function () {
        setStatus('Unable to access camera. Please allow camera permission.', 'err');
    });
}

document.addEventListener('DOMContentLoaded', startScanner);
</script>

</body>
</html>