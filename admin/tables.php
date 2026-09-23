<?php
session_start();
include '../config/db_connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$initial  = strtoupper(substr($fullName, 0, 1));

$CUSTOMER_BASE = 'http://' . $_SERVER['HTTP_HOST'] . '/perchpour/customer/index.php?table=';

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_table'])) {
    $table_number = trim($_POST['table_number'] ?? '');

    if ($table_number === '') {
        $error = 'Table number is required.';
    } else {
        $check = mysqli_prepare($conn, "SELECT table_id FROM tables WHERE table_number = ?");
        mysqli_stmt_bind_param($check, "s", $table_number);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = 'Table number already exists.';
        } else {
            $qr_code = 'TABLE-' . strtoupper(bin2hex(random_bytes(6)));

            $stmt = mysqli_prepare($conn,
                "INSERT INTO tables (table_number, qr_code) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $table_number, $qr_code);

            if (mysqli_stmt_execute($stmt)) {
                $success = 'Table added successfully.';
            } else {
                $error = 'Failed to add table.';
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_table'])) {
    $tid = (int) $_POST['table_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM tables WHERE table_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $tid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $success = 'Table deleted.';
}

$tables = mysqli_query($conn,
    "SELECT * FROM tables ORDER BY
        CAST(table_number AS UNSIGNED) ASC, table_number ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Tables &amp; QR — Perch &amp; Pour Admin</title>
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

    .table-card {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 18px;
        height: 100%;
        transition: box-shadow 0.12s;
    }
    .table-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.06); }

    .table-card .table-number {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .table-card .qr-token {
        font-size: 11px;
        color: #6b7280;
        font-family: monospace;
        margin-bottom: 12px;
        word-break: break-all;
    }
    .table-card .qr-preview {
        width: 100%;
        max-width: 180px;
        aspect-ratio: 1 / 1;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
        padding: 6px;
    }
    .table-card .qr-preview canvas,
    .table-card .qr-preview img {
        width: 100% !important;
        height: 100% !important;
        display: block;
    }

    .empty-box {
        border: 1px dashed #dee2e6;
        color: #6b7280;
        text-align: center;
        padding: 60px 20px;
        border-radius: 4px;
    }

    .qr-print-area {
        text-align: center;
        padding: 20px;
    }
    .qr-print-area #qrHolder canvas,
    .qr-print-area #qrHolder img {
        width: 280px !important;
        height: 280px !important;
        display: block;
        margin: 0 auto;
    }
    .qr-print-area .brand {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .qr-print-area .sub {
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 16px;
    }
    .qr-print-area .table-big {
        font-size: 30px;
        font-weight: 700;
        margin-top: 16px;
    }
    .qr-print-area .instruction {
        font-size: 13px;
        color: #444;
        margin-top: 8px;
        max-width: 320px;
        margin-left: auto;
        margin-right: auto;
    }

    .btn-danger {
        background: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }
    .btn-danger:hover, .btn-danger:focus {
        background: #b02a37;
        border-color: #b02a37;
        color: #fff;
    }

    @media print {
        @page { size: A4 portrait; margin: 15mm; }

        body * { visibility: hidden; }

        #qrPrint, #qrPrint * { visibility: visible; }

        #qrPrint {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0;
            margin: 0;
        }

        .qr-print-area #qrHolder canvas,
        .qr-print-area #qrHolder img {
            width: 320px !important;
            height: 320px !important;
        }

        .qr-print-area .brand { font-size: 26px; }

        .qr-print-area .table-big {
            font-size: 34px;
            margin-top: 20px;
        }

        .qr-print-area .instruction {
            font-size: 13px;
            max-width: 380px;
        }
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
            <h1 class="h4 fw-bold mb-0">Tables &amp; QR</h1>
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

        <div class="card border-dark-subtle rounded-0 mb-4">
            <div class="card-body p-3">
                <form method="post" class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-secondary mb-1">Table Number</label>
                        <input type="text" name="table_number"
                               class="form-control form-control-sm rounded-0 border-dark-subtle"
                               placeholder="e.g. 1, 2, A1, VIP" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <button type="submit" name="add_table"
                                class="btn btn-dark btn-sm rounded-0 w-100">
                            <i class="fa-solid fa-plus me-1"></i> Add Table
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (mysqli_num_rows($tables) === 0): ?>

            <div class="empty-box">
                <i class="fa-solid fa-qrcode d-block mb-2 fs-3"></i>
                Wala pang table. Mag-add sa itaas para makabuo ng QR codes.
            </div>

        <?php else: ?>

            <h3 class="h6 fw-bold border-bottom pb-2 mb-3">
                Tables (<?= mysqli_num_rows($tables) ?>)
            </h3>

            <div class="row g-3">
                <?php while ($t = mysqli_fetch_assoc($tables)):
                    $qrUrl = $CUSTOMER_BASE . urlencode($t['qr_code']);
                ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="table-card">

                            <div class="table-number">Table <?= htmlspecialchars($t['table_number']) ?></div>
                            <div class="qr-token"><?= htmlspecialchars($t['qr_code']) ?></div>

                            <div class="qr-preview">
                                <div class="qr-mini"
                                     data-qr="<?= htmlspecialchars($qrUrl) ?>"
                                     data-size="168"></div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-dark btn-sm rounded-0 flex-grow-1"
                                        onclick="printQr(<?= $t['table_id'] ?>)">
                                    <i class="fa-solid fa-print me-1"></i> Print QR
                                </button>
                                <form method="post"
                                      onsubmit="return confirm('Delete this table? This cannot be undone.');">
                                    <input type="hidden" name="table_id" value="<?= $t['table_id'] ?>">
                                    <button type="submit" name="delete_table"
                                            class="btn btn-danger btn-sm rounded-0">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

        <?php endif; ?>

    </div>
</div>

</div>

<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-0">
            <div class="modal-header border-bottom d-print-none">
                <h5 class="modal-title h6 fw-bold mb-0">
                    <i class="fa-solid fa-qrcode me-1"></i> Table QR Code
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="qrPrint" class="qr-print-area">
                    <div class="brand">Perch &amp; Pour</div>
                    <div class="sub">Scan to order</div>
                    <div id="qrHolder" style="display:flex; justify-content:center;"></div>
                    <div class="table-big" id="qrTableLabel">—</div>
                    <div class="instruction">
                        Open your camera or QR scanner and point it at the code to view our menu and place your order.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top d-print-none">
                <button type="button" class="btn btn-dark btn-sm rounded-0"
                        onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="../qrcode.min.js"></script>
<script>
    const customerBase = <?= json_encode($CUSTOMER_BASE) ?>;
    const tableData = {
        <?php
        mysqli_data_seek($tables, 0);
        $first = true;
        while ($t = mysqli_fetch_assoc($tables)) {
            if (!$first) echo ',';
            $first = false;
            echo $t['table_id'] . ':' . json_encode([
                'number' => $t['table_number'],
                'code'   => $t['qr_code']
            ]);
        }
        ?>
    };

    document.querySelectorAll('.qr-mini').forEach(function (el) {
        const size = parseInt(el.dataset.size || '168', 10);
        new QRCode(el, {
            text: el.dataset.qr,
            width: size,
            height: size,
            colorDark: "#111111",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    });

    function printQr(id) {
        const data = tableData[id];
        if (!data) return;

        const url = customerBase + encodeURIComponent(data.code);
        const holder = document.getElementById('qrHolder');
        holder.innerHTML = '';

        new QRCode(holder, {
            text: url,
            width: 320,
            height: 320,
            colorDark: "#111111",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        document.getElementById('qrTableLabel').textContent = 'Table ' + data.number;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('qrModal')).show();
    }
</script>

</body>
</html>