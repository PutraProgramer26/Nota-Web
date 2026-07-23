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

$ppn = 0;
$totalAkhir = 0;

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

if ($selectedToko === 'Cahaya Timika') {
    $ppn = $grandTotal * 0.11;
    $totalAkhir = $grandTotal + $ppn;
} else {
    $totalAkhir = $grandTotal;
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=rekap_nota.xls');
echo "\xEF\xBB\xBF";

$periodeLabel = $selectedBulan !== '' ? $selectedBulan : 'Semua Periode';
?><html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 10pt; table-layout: auto; }
        th, td { border: 1px solid #000; padding: 5px; }
        th { background: #f1f1f1; }
        .meta-table td { border: none; padding: 3px 5px; }
        .meta-table td:first-child { font-weight: bold; }
        .number-cell { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        th.number-column { white-space: nowrap; min-width: 110px; }
        .total-label { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="12" style="font-weight:bold; font-size:12pt; text-align:center;">Laporan Pembelian Material</td>
        </tr>
        <tr>
            <td><strong>Periode</strong></td>
            <td colspan="11"><?php echo htmlspecialchars($periodeLabel); ?></td>
        </tr>
        <tr>
            <td><strong>Diterbitkan</strong></td>
            <td colspan="11"><?php echo date('d F Y'); ?></td>
        </tr>
        <tr>
            <td><strong>Toko</strong></td>
            <td colspan="11"><?php echo htmlspecialchars($selectedToko ?: 'Semua Toko'); ?></td>
        </tr>
        <tr>
            <td><strong>Project</strong></td>
            <td colspan="11"><?php echo htmlspecialchars($selectedProject ?: 'Semua Project'); ?></td>
        </tr>
        <tr><td colspan="12" style="height:8px; border:none;"></td></tr>
        <thead>
            <tr>
                <th>No Register</th>
                <th>Tanggal</th>
                <th>Project</th>
                <th>Toko</th>
                <th style="min-width: 180px;">Nama Barang</th>
                <th style="min-width: 60px;">Qty</th>
                <th style="min-width: 70px;">Satuan</th>
                <th class="number-column" style="min-width: 110px;">Harga Barang</th>
                <th class="number-column" style="min-width: 110px;">Harga Total</th>
                <th class="number-column" style="min-width: 120px;">Grand Total</th>
                <th style="min-width: 80px;">Order By</th>
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
                        <td class="number-cell"><?php echo htmlspecialchars(number_format($item['harga_barang'] ?? 0, 0, '.', ',')); ?></td>
                        <td class="number-cell"><?php echo htmlspecialchars(number_format($item['total_harga'] ?? 0, 0, '.', ',')); ?></td>
                        <?php if ($index === 0) : ?>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars(number_format($summary['grand_total'] ?? 0, 0, '.', ',')); ?></td>
                            <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['pemesan'] ?: '-'); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <tr>
                <td colspan="8" class="total-label">TOTAL</td>
                <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($grandTotal, 0, '.', ',')); ?></td>
                <td colspan="2"></td>
            </tr>
            <?php if ($selectedToko === 'Cahaya Timika' && $ppn > 0) : ?>
            <tr>
                <td colspan="8" class="total-label">PPN 11%</td>
                <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($ppn, 0, '.', ',')); ?></td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="8" class="total-label">TOTAL KESELURUHAN (Grand Total + PPN)</td>
                <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($totalAkhir, 0, '.', ',')); ?></td>
                <td colspan="2"></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="12" style="border:none; height:24px;"></td>
            </tr>
            <tr>
                <?php
                $data_ttd = [
                    'Direktur' => 'Joule Rizal',
                    'Direktris' => 'Pravita F. Anggreini',
                    'Project Manager' => '....................',
                    'Manager Material' => '....................',
                    'Material' => '....................'
                ];
                $ttdKeys = array_keys($data_ttd);
                foreach ($ttdKeys as $index => $jabatan) :
                    $colspan = $index === count($ttdKeys) - 1 ? 4 : 2;
                ?>
                    <td colspan="<?php echo $colspan; ?>" style="text-align:center; border:none; vertical-align:top;">
                        <div style="font-weight:bold; margin-bottom: 36px;"><?php echo htmlspecialchars($jabatan); ?></div>
                        <div style="height: 60px;"></div>
                        <div>(<?php echo htmlspecialchars($data_ttd[$jabatan]); ?>)</div>
                    </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php
