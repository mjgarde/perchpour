<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['full_name'] ?? 'Admin';
$initial = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard — Perch &amp; Pour Admin</title>
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
            <h1 class="h4 fw-bold mb-0">Dashboard</h1>
            <div class="small text-secondary"><?php echo date("l, F j, Y"); ?></div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4" style="max-width: 1100px;">

        <div class="card border-dark-subtle rounded-0 mb-4">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-1">Welcome, <?php echo htmlspecialchars($fullName); ?></h2>
                <p class="text-secondary small mb-0">Naka-login ka bilang admin ng Perch &amp; Pour system.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-receipt"></i>
                            <span>Pending Orders</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1">0</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-mug-hot"></i>
                            <span>In Preparation</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1">0</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-users"></i>
                            <span>Staff Clocked In</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1">0</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card border-dark-subtle rounded-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 text-uppercase text-secondary small fw-semibold mb-2">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Low Stock Items</span>
                        </div>
                        <div class="fs-3 fw-bold lh-1">0</div>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="h6 fw-bold border-bottom pb-2 mb-3">Recent Activity</h3>

        <div class="border border-dark-subtle text-center text-secondary py-5 px-3">
            <i class="fa-solid fa-inbox d-block mb-2 fs-4"></i>
            No recent activity yet.
        </div>

    </div>
</div>

</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>