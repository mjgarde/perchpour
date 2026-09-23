<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = mysqli_query($conn, "SELECT * FROM admins WHERE username = '$username'");

    if (mysqli_num_rows($check) > 0) {
        echo "Username already exists.";
    } else {
        $sql = "INSERT INTO admins (username, password, full_name) VALUES ('$username', '$password', '$full_name')";
        if (mysqli_query($conn, $sql)) {
            echo "Account created successfully!";
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Create Admin Account</title></head>
<body>
    <h2>Create Admin Account</h2>
    <form method="POST" action="">
        <label>Full Name:</label><br>
        <input type="text" name="full_name" required><br><br>

        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Create Account</button>
    </form>
</body>
</html>