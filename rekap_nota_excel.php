<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$selectedToko = $_GET['toko'] ?? '';
$selectedProject = $_GET['project'] ?? '';
$selectedBulan = $_GET['bulan'] ?? '';
$selectedKeterangan = $_GET['keterangan'] ?? '';

$sql = "SELECT no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota WHERE 1=1";
$params = [];
$types = '';

if ($selectedToko !== '') {
    $sql .= " AND nama_toko = ?";
    $params[] = $selectedToko;
    $types .= 's';
}
if ($selectedProject !== '') {
    $sql .= " AND project = ?";
    $params[] = $selectedProject;
    $types .= 's';
}
if ($selectedBulan !== '') {
    $sql .= " AND DATE_FORMAT(tanggal_belanja, '%Y-%m') = ?";
    $params[] = $selectedBulan;
    $types .= 's';
}
if ($selectedKeterangan !== '') {
    $sql .= " AND keterangan = ?";
    $params[] = $selectedKeterangan;
    $types .= 's';
}

$sql .= " ORDER BY tanggal_belanja DESC, id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=rekap_nota.xls');

$output = fopen('php://output', 'w');
$header = ['No Register', 'Nama Barang', 'Harga', 'Qty', 'Satuan', 'Total', 'Project', 'Pemesan', 'Toko', 'Tanggal', 'Keterangan'];
fputcsv($output, $header, "\t");

foreach ($rows as $row) {
    fputcsv($output, [
        $row['no_register'],
        $row['nama_barang'],
        $row['harga_barang'],
        $row['jumlah_barang'],
        $row['satuan_barang'],
        $row['total_harga'],
        $row['project'],
        $row['pemesan'],
        $row['nama_toko'],
        $row['tanggal_belanja'],
        $row['keterangan'] ?? '-'
    ], "\t");
}

fclose($output);
