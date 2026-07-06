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
echo "\xEF\xBB\xBF";

$periodeLabel = $selectedBulan !== '' ? $selectedBulan : 'Semua Periode';
?><html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 10pt; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background: #f1f1f1; }
        .meta-table td { border: none; padding: 3px 5px; }
        .meta-table td:first-child { font-weight: bold; }
    </style>
</head>
<body>
    <table class="meta-table">
        <tr><td>Periode</td><td><?php echo htmlspecialchars($periodeLabel); ?></td></tr>
        <tr><td>Diterbitkan</td><td><?php echo date('d F Y'); ?></td></tr>
        <tr><td>Toko</td><td><?php echo htmlspecialchars($selectedToko ?: 'Semua Toko'); ?></td></tr>
        <tr><td>Project</td><td><?php echo htmlspecialchars($selectedProject ?: 'Semua Project'); ?></td></tr>
    </table>
    <br />
    <table>
        <thead>
            <tr>
                <th>No Register</th>
                <th>Tanggal</th>
                <th>Project</th>
                <th>Toko</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th>Harga Barang</th>
                <th>Harga Total</th>
                <th>Grand Total</th>
                <th>Order By</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notaSummaries as $summary) : ?>
                <?php $rowspan = count($summary['items']); ?>
                <?php foreach ($summary['items'] as $index => $item) : ?>
                    <tr>
                        <?php if ($index === 0) : ?>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['no_register'] ?: '-'); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars(!empty($summary['tanggal_belanja']) ? date('d-M-Y', strtotime($summary['tanggal_belanja'])) : '-'); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['project'] ?: '-'); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['nama_toko'] ?: '-'); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars($item['nama_barang'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($item['jumlah_barang'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars($item['satuan_barang'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item['harga_barang'] ?? 0, 0, '.', ',')); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item['total_harga'] ?? 0, 0, '.', ',')); ?></td>
                        <?php if ($index === 0) : ?>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars(number_format($summary['grand_total'] ?? 0, 0, '.', ',')); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['pemesan'] ?: '-'); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['keterangan'] ?? '-'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <tr>
                <td colspan="7" style="text-align:right; font-weight:bold;">TOTAL KESELURUHAN</td>
                <td><?php echo htmlspecialchars(number_format($grandTotal, 0, '.', ',')); ?></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php
