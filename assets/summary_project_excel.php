<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$selectedProject = trim((string)($_GET['project'] ?? ''));
$selectedToko = trim((string)($_GET['toko'] ?? ''));
$selectedBulan = trim((string)($_GET['bulan'] ?? ''));
$selectedKategori = 'invoice';

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota WHERE keterangan = ?";
$params = [$selectedKategori];
$types = 's';

if ($selectedProject !== '') {
    $sql .= " AND project = ?";
    $params[] = $selectedProject;
    $types .= 's';
}

if ($selectedToko !== '') {
    $sql .= " AND nama_toko = ?";
    $params[] = $selectedToko;
    $types .= 's';
}

if ($selectedBulan !== '') {
    $sql .= " AND DATE_FORMAT(tanggal_belanja, '%Y-%m') = ?";
    $params[] = $selectedBulan;
    $types .= 's';
}

$sql .= " ORDER BY project ASC, nama_toko ASC, tanggal_belanja ASC, id ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

$summaryRows = [];
$grandTotal = 0.0;

foreach ($rows as $row) {
    $projectName = trim((string)($row['project'] ?? ''));
    $tokoName = trim((string)($row['nama_toko'] ?? ''));
    $registerKey = trim((string)($row['no_register'] ?? ''));
    $tanggalBelanja = trim((string)($row['tanggal_belanja'] ?? ''));
    $groupKey = $projectName . '|' . $tokoName;

    if ($groupKey === '|') {
        continue;
    }

    if (!isset($summaryRows[$groupKey])) {
        $summaryRows[$groupKey] = [
            'project' => $projectName,
            'nama_toko' => $tokoName,
            'nota_count' => 0,
            'item_count' => 0,
            'grand_total' => 0.0,
            'tanggal_awal' => $tanggalBelanja,
            'tanggal_akhir' => $tanggalBelanja,
            'registers' => [],
        ];
    }

    if ($registerKey !== '' && !isset($summaryRows[$groupKey]['registers'][$registerKey])) {
        $summaryRows[$groupKey]['registers'][$registerKey] = true;
        $summaryRows[$groupKey]['nota_count'] += 1;
    }

    $summaryRows[$groupKey]['item_count'] += 1;
    $summaryRows[$groupKey]['grand_total'] += (float)($row['total_harga'] ?? 0);
    $grandTotal += (float)($row['total_harga'] ?? 0);

    if ($tanggalBelanja !== '') {
        if ($summaryRows[$groupKey]['tanggal_awal'] === '' || $tanggalBelanja < $summaryRows[$groupKey]['tanggal_awal']) {
            $summaryRows[$groupKey]['tanggal_awal'] = $tanggalBelanja;
        }
        if ($summaryRows[$groupKey]['tanggal_akhir'] === '' || $tanggalBelanja > $summaryRows[$groupKey]['tanggal_akhir']) {
            $summaryRows[$groupKey]['tanggal_akhir'] = $tanggalBelanja;
        }
    }
}

$summaryRows = array_values($summaryRows);
usort($summaryRows, function ($a, $b) {
    return strcmp($a['project'], $b['project']) ?: strcmp($a['nama_toko'], $b['nama_toko']);
});

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=summary_pembelian_material.xls');
echo "\xEF\xBB\xBF";
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 10pt; table-layout: auto; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background: #f1f1f1; }
        .number-cell { text-align: right; white-space: nowrap; }
        .label-cell { font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="8" style="font-weight:bold; font-size:12pt; text-align:center;">Summary Pembelian Material</td>
        </tr>
        <tr>
            <td class="label-cell">Periode</td>
            <td colspan="7"><?php echo htmlspecialchars($selectedBulan !== '' ? $selectedBulan : 'Semua Bulan'); ?></td>
        </tr>
        <tr>
            <td class="label-cell">Project</td>
            <td colspan="7"><?php echo htmlspecialchars($selectedProject !== '' ? $selectedProject : 'Semua Project'); ?></td>
        </tr>
        <tr>
            <td class="label-cell">Toko</td>
            <td colspan="7"><?php echo htmlspecialchars($selectedToko !== '' ? $selectedToko : 'Semua Toko'); ?></td>
        </tr>
        <tr>
            <td class="label-cell">Kategori</td>
            <td colspan="7">Invoice</td>
        </tr>
        <tr>
            <td class="label-cell">Tanggal Export</td>
            <td colspan="7"><?php echo date('d F Y'); ?></td>
        </tr>
        <tr><td colspan="8" style="height:8px; border:none;"></td></tr>
        <thead>
            <tr>
                <th>No</th>
                <th>Project</th>
                <th>Toko / Vendor</th>
                <th>Jumlah Nota</th>
                <th>Jumlah Item</th>
                <th>Jumlah Total Harga</th>
                <th>Tanggal Awal</th>
                <th>Tanggal Akhir</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($summaryRows)) : ?>
                <tr>
                    <td colspan="8" style="text-align:center;">Tidak ada data summary invoice untuk kombinasi yang dipilih.</td>
                </tr>
            <?php else : ?>
                <?php $no = 1; foreach ($summaryRows as $summary) : ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($summary['project']); ?></td>
                        <td><?php echo htmlspecialchars($summary['nama_toko']); ?></td>
                        <td><?php echo number_format($summary['nota_count']); ?></td>
                        <td><?php echo number_format($summary['item_count']); ?></td>
                        <td class="number-cell">Rp <?php echo number_format($summary['grand_total'], 0, ',', '.'); ?></td>
                        <td><?php echo htmlspecialchars($summary['tanggal_awal'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($summary['tanggal_akhir'] ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="5" class="label-cell" style="text-align:right;">TOTAL</td>
                    <td class="number-cell">Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
