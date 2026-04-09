<?php
$host     = "localhost";
$user     = "root";
$password = "07082006";
$database = "airbiru";

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

mysqli_set_charset($koneksi, "utf8");
?>
