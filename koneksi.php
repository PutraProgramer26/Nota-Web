<?php
// koneksi.php
// Buat koneksi ke database MySQL dengan nama database nota_web

$host = 'localhost';
$username = 'u170828859_putra';
$password = 'Programer260705';
$database = 'u170828859_Nota_Web';

$sessionTimeoutSeconds = 900;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['last_activity'])) {
    $idleSeconds = time() - (int)$_SESSION['last_activity'];
    if ($idleSeconds > $sessionTimeoutSeconds) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

$_SESSION['last_activity'] = time();

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die('Koneksi gagal: ' . mysqli_connect_error());
}

// Set charset ke UTF-8 untuk dukungan karakter internasional
mysqli_set_charset($conn, 'utf8');

// Gunakan variabel $conn di file lain setelah include atau require
?>