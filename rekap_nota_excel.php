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
$grandTotal = 0;
$notaSummaries = [];
foreach ($rows as $row) {
    $grandTotal += (float)($row['total_harga'] ?? 0);

    $registerKey = (string)($row['no_register'] ?? '');
    if ($registerKey === '') {
        $registerKey = '__empty__';
    }

    if (!isset($notaSummaries[$registerKey])) {
        $notaSummaries[$registerKey] = [
            'no_register' => $row['no_register'] ?? '',
            'tanggal_belanja' => $row['tanggal_belanja'] ?? '',
            'project' => $row['project'] ?? '',
            'nama_toko' => $row['nama_toko'] ?? '',
            'pemesan' => $row['pemesan'] ?? '',
            'keterangan' => $row['keterangan'] ?? '',
            'grand_total' => 0,
            'items' => [],
        ];
    }

    $notaSummaries[$registerKey]['grand_total'] += (float)($row['total_harga'] ?? 0);
    $notaSummaries[$registerKey]['items'][] = [
        'nama_barang' => $row['nama_barang'] ?? '',
        'harga_barang' => (float)($row['harga_barang'] ?? 0),
        'jumlah_barang' => $row['jumlah_barang'] ?? 0,
        'satuan_barang' => $row['satuan_barang'] ?? '',
        'total_harga' => (float)($row['total_harga'] ?? 0),
    ];
}
$notaSummaries = array_values($notaSummaries);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=rekap_nota.xls');

$output = fopen('php://output', 'w');
$header = ['No Register', 'Tanggal', 'Project', 'Toko', 'Rincian Material', 'Grand Total', 'Order By', 'Keterangan'];
fputcsv($output, $header, "\t");

foreach ($notaSummaries as $summary) {
    $materialDetail = [];
    foreach ($summary['items'] as $item) {
        $materialDetail[] = sprintf(
            '%s | Qty: %s %s | Harga: Rp %s | Total: Rp %s',
            $item['nama_barang'] ?: '-',
            $item['jumlah_barang'] ?? 0,
            $item['satuan_barang'] ?: '-',
            number_format($item['harga_barang'] ?? 0, 0, '.', ','),
            number_format($item['total_harga'] ?? 0, 0, '.', ',')
        );
    }

    fputcsv($output, [
        $summary['no_register'] ?: '-',
        !empty($summary['tanggal_belanja']) ? date('d-M-Y', strtotime($summary['tanggal_belanja'])) : '-',
        $summary['project'] ?: '-',
        $summary['nama_toko'] ?: '-',
        implode("; ", $materialDetail),
        number_format($summary['grand_total'] ?? 0, 0, '.', ','),
        $summary['pemesan'] ?: '-',
        $summary['keterangan'] ?? '-'
    ], "\t");
}

fputcsv($output, ['', '', '', '', 'TOTAL KESELURUHAN', number_format($grandTotal, 0, '.', ','), '', ''], "\t");

fclose($output);
