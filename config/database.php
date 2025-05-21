<?php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = 'sHh7KbTDydZwP4hL';
$DB_NAME = 'mygame';

$DB_HOST_RO = 'localhost';
$DB_USER_RO = 'root';
$DB_PASS_RO = 'sHh7KbTDydZwP4hL';
$DB_NAME_RO = 'ro_xd_r2';

// Koneksi untuk database utama
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Koneksi untuk database ro_xd_r2
$conn_ro = new mysqli($DB_HOST_RO, $DB_USER_RO, $DB_PASS_RO, $DB_NAME_RO);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($conn_ro->connect_error) {
    die("RO XD R2 database connection failed: " . $conn_ro->connect_error);
}
?>