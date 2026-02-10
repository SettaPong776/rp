<?php
require_once 'config/db_connect.php';

$query = "ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'building_staff') NOT NULL DEFAULT 'user'";

if (mysqli_query($conn, $query)) {
    echo "Database updated successfully: Added 'building_staff' role enum.";
} else {
    echo "Error updating database: " . mysqli_error($conn);
}
?>