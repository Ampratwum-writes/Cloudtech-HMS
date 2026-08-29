<?php

$servername = "Server_name";
$username = "Database_Username";
$password = "Your_database_password";
$dbname = "Your_database_name";
$port = 3306;

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname,
    $port
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
