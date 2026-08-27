<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$selectedProjectCategory = trim((string)($_GET['project_category'] ?? 'Project'));
$projectCategories = ['Mixer', 'Internal', 'Project'];
if (!in_array($selectedProjectCategory, $projectCategories, true)) {
    $selectedProjectCategory = 'Project';
}
$selectedToko = trim((string)($_GET['toko'] ?? ''));
$selectedBulan = trim((string)($_GET['bulan'] ?? ''));
$selectedKategori = 'invoice';

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota WHERE keterangan = ?";
$params = [$selectedKategori];
$types = 's';

if ($selectedProjectCategory === 'Mixer') {
    $sql .= " AND LOWER(project) = LOWER(?)";
    $params[] = 'Mixer';
    $types .= 's';
} elseif ($selectedProjectCategory === 'Internal') {
    $internalProjects = ['Rumah Karitas', 'Mess Karitas', 'Petakan Panjat Tebing', 'Mess Panjat Tebing', 'Petakan Waker', 'Mess Waker', 'Workshop SP2'];
    $projectPlaceholders = implode(', ', array_fill(0, count($internalProjects), '?'));
    $sql .= " AND LOWER(project) IN ($projectPlaceholders)";
    foreach ($internalProjects as $projectValue) {
        $params[] = $projectValue;
        $types .= 's';
    }
} elseif ($selectedProjectCategory === 'Project') {
    $excludedProjects = ['Mixer', 'Rumah Karitas', 'Mess Karitas', 'Petakan Panjat Tebing', 'Mess Panjat Tebing', 'Petakan Waker', 'Mess Waker', 'Workshop SP2'];
    $projectPlaceholders = implode(', ', array_fill(0, count($excludedProjects), '?'));
    $sql .= " AND LOWER(project) NOT IN ($projectPlaceholders)";
    foreach ($excludedProjects as $projectValue) {
        $params[] = $projectValue;
        $types .= 's';
    }
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
    $rowTotalHarga = (float)($row['total_harga'] ?? 0);
    $rowTotalHargaWithPpn = $tokoName === 'Cahaya Timika' ? $rowTotalHarga * 1.11 : $rowTotalHarga;

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
    $summaryRows[$groupKey]['grand_total'] += $rowTotalHargaWithPpn;
    $grandTotal += $rowTotalHargaWithPpn;

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
        th, td { border: 1px solid #000; padding: 5px; vertical-align: top; }
        th { background: #f1f1f1; }
        .number-cell { text-align: right; white-space: nowrap; }
        .label-cell { font-weight: bold; }
        .info-table td { border: 1px solid #000; }
        .info-table td:first-child { width: 22%; }
        .info-table td:last-child { width: 78%; }
    </style>
</head>
<body>
    <table class="info-table" style="margin-bottom: 8px;">
        <tr>
            <td colspan="2" style="font-weight:bold; font-size:12pt; text-align:center;">Summary Pembelian Material</td>
        </tr>
        <tr>
            <td class="label-cell">Periode</td>
            <td><?php echo htmlspecialchars($selectedBulan !== '' ? $selectedBulan : 'Semua Bulan'); ?></td>
        </tr>
        <tr>
            <td class="label-cell">Toko</td>
            <td><?php echo htmlspecialchars($selectedToko !== '' ? $selectedToko : 'Semua Toko'); ?></td>
        </tr>
        <tr>
            <td class="label-cell">Kategori</td>
            <td>Invoice</td>
        </tr>
        <tr>
            <td class="label-cell">Tanggal Export</td>
            <td><?php echo date('d F Y'); ?></td>
        </tr>
    </table>
    <table>
        <thead>
            <tr>
                <th style="text-align:center;">No</th>
                <th style="text-align:center;">Project</th>
                <th>Jumlah Total Harga</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($summaryRows)) : ?>
                <tr>
                    <td colspan="3" style="text-align:center;">Tidak ada data summary invoice untuk kombinasi yang dipilih.</td>
                </tr>
            <?php else : ?>
                <?php $no = 1; foreach ($summaryRows as $summary) : ?>
                    <tr>
                        <td style="text-align:center;"><?php echo $no++; ?></td>
                        <td style="text-align:center;"><?php echo htmlspecialchars($summary['project']); ?></td>
                        <td class="number-cell">Rp <?php echo number_format($summary['grand_total'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="2" class="label-cell" style="text-align:right;">GRAND TOTAL</td>
                    <td class="number-cell">Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table style="margin-top: 28px;">
        <tr>
            <?php
            $data_ttd = [
                'Direktur' => 'Joule Rizal',
                'Direktris' => 'Pravita F. Anggreini',
                'Project Manager' => '....................',
                'Material' => '....................'
            ];
            $ttdKeys = array_keys($data_ttd);
            foreach ($ttdKeys as $index => $jabatan) :
                $colspan = $index === count($ttdKeys) - 1 ? 2 : 1;
            ?>
                <td colspan="<?php echo $colspan; ?>" style="text-align:center; border:none; vertical-align:top;">
                    <div style="font-weight:bold; margin-bottom: 36px;"><?php echo htmlspecialchars($jabatan); ?></div>
                    <div style="height: 60px;"></div>
                    <div>(<?php echo htmlspecialchars($data_ttd[$jabatan]); ?>)</div>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
</body>
</html>
