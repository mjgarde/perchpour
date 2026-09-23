<?php
$host = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "perchpour";

$conn = mysqli_connect($host, $dbuser, $dbpass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>