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

    

     

    </div>
</div>

</div>

<script src="../vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>