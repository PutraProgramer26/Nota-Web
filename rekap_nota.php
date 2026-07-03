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

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
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

$sql .= " ORDER BY tanggal_belanja ASC, id ASC";

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
            'item_count' => 0,
            'grand_total' => 0,
            'items' => [],
        ];
    }

    $notaSummaries[$registerKey]['item_count'] += 1;
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

$tokoList = mysqli_query($conn, "SELECT DISTINCT nama_toko FROM nota WHERE nama_toko IS NOT NULL AND nama_toko <> '' ORDER BY nama_toko");
$projectList = mysqli_query($conn, "SELECT DISTINCT project FROM nota WHERE project IS NOT NULL AND project <> '' ORDER BY project");
$bulanList = mysqli_query($conn, "SELECT DISTINCT DATE_FORMAT(tanggal_belanja, '%Y-%m') AS bulan FROM nota WHERE tanggal_belanja IS NOT NULL ORDER BY bulan DESC");
$keteranganList = ['Cash', 'invoice', 'stock gudang'];

$bulanIndonesia = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni', 
                   '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$tanggalCetak = date('d F Y');
$bulanYearCetak = date('m');
$bulanNamaCetak = $bulanIndonesia[$bulanYearCetak] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rekap Nota</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
    <style>
        body { background: #f8f9fa; font-family: 'Calibri', Arial, sans-serif; }
        .table-responsive { overflow-x: auto; }
        .btn-print { background: #0d6efd; color: white; }
        .print-only { display: none; }

        @media print {
            * {
                margin: 0;
                padding: 0;
            }
            body {
                background: white;
                font-size: 10pt;
                line-height: 1.4;
            }
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 12mm;
            }
            .report-container {
                width: 100%;
                max-width: 100%;
                margin: 0 auto;
            }
            .report-header {
                text-align: center;
                margin-bottom: 10px;
                border-bottom: 2px solid #000;
                padding-bottom: 8px;
            }
            .report-header h2 { 
                margin: 0; 
                font-size: 15pt; 
                font-weight: bold;
            }
            .report-header p { 
                margin: 2px 0; 
                font-size: 10pt;
            }
            .hide-project-toko-print > thead > tr > th:nth-child(3),
            .hide-project-toko-print > thead > tr > th:nth-child(4),
            .hide-project-toko-print > tbody > tr > td:nth-child(3),
            .hide-project-toko-print > tbody > tr > td:nth-child(4) {
                display: none !important;
            }
            .report-info {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                justify-content: space-between;
                margin-bottom: 12px;
                font-size: 9pt;
                border-bottom: 1px solid #000;
                padding-bottom: 10px;
            }
            .report-info-item {
                width: calc(50% - 6px);
                display: flex;
                justify-content: flex-start;
                align-items: center;
                gap: 4px;
            }
            .report-info-label {
                font-weight: bold;
                width: auto;
                min-width: 90px;
            }
            .report-info-value {
                width: auto;
            }
            table {
                font-size: 9pt;
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 10px;
                table-layout: fixed;
                word-wrap: break-word;
            }
            th, td {
                padding: 6px 5px;
                border: 1px solid #000;
                text-align: left;
                vertical-align: top;
            }
            th {
                background: #e8e8e8;
                font-weight: bold;
                text-align: center;
            }
            td {
                background: white;
            }
            tr.total-row {
                font-weight: bold;
                background: #f0f0f0;
            }
            .number-cell {
                text-align: right;
                padding-right: 5px;
            }
            .center-cell {
                text-align: center;
            }
            .material-list {
                display: grid;
                gap: 3px;
            }
            .material-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 8.5pt;
            }
            .material-table th, .material-table td {
                border: 1px solid #dee2e6;
                padding: 3px 4px;
                text-align: left;
                background: white;
            }
            .material-table th {
                background: #f1f3f5;
                font-weight: bold;
                white-space: nowrap;
            }
            .material-table td {
                white-space: nowrap;
            }
            .material-table td:first-child {
                white-space: normal;
                min-width: 180px;
                word-break: break-word;
            }
            .material-table .number-cell {
                text-align: right;
                white-space: nowrap;
            }
            /* Mengatur kontainer agar tanda tangan tersebar merata */
        .signature-wrapper {
        display: flex;
        justify-content: space-between;
        margin-top: 50px;
        width: 100%;
        text-align: center;
        flex-wrap: wrap; /* Agar responsif jika layar kecil */
    }

    .signature-box {
        flex: 1;
        min-width: 100px; /* Menjaga agar tidak terlalu rapat */
        padding: 10px;
    }

    .signature-space {
        height: 80px; /* Ruang untuk tanda tangan basah */
    }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            .card-body { 
                padding: 0 !important; 
            }
            .table-responsive {
                overflow: visible;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h2 class="mb-0">Rekap Nota</h2>
            <div class="btn-group">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                <a href="input.php" class="btn btn-outline-secondary btn-sm">Input Nota</a>
                <a href="lihat_nota.php" class="btn btn-outline-secondary btn-sm">Lihat Nota</a>
                <a href="rekap_nota.php" class="btn btn-outline-primary btn-sm active">Rekap Nota</a>
            </div>
        </div>

        <div class="card shadow-sm mb-4 no-print">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Pilih Toko / Vendor</label>
                        <select name="toko" class="form-select">
                            <option value="">Semua Toko</option>
                            <?php while ($row = mysqli_fetch_assoc($tokoList)) : ?>
                                <option value="<?php echo htmlspecialchars($row['nama_toko']); ?>" <?php echo $selectedToko === $row['nama_toko'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['nama_toko']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama Project</label>
                        <select name="project" class="form-select">
                            <option value="">Semua Project</option>
                            <?php while ($row = mysqli_fetch_assoc($projectList)) : ?>
                                <option value="<?php echo htmlspecialchars($row['project']); ?>" <?php echo $selectedProject === $row['project'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['project']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bulan Periode</label>
                        <select name="bulan" class="form-select">
                            <option value="">Semua Bulan</option>
                            <?php while ($row = mysqli_fetch_assoc($bulanList)) : ?>
                                <option value="<?php echo htmlspecialchars($row['bulan']); ?>" <?php echo $selectedBulan === $row['bulan'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['bulan']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Keterangan</label>
                        <select name="keterangan" class="form-select">
                            <option value="">Semua</option>
                            <?php foreach ($keteranganList as $item) : ?>
                                <option value="<?php echo htmlspecialchars($item); ?>" <?php echo $selectedKeterangan === $item ? 'selected' : ''; ?>><?php echo htmlspecialchars($item); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex gap-2 mb-3 no-print">
            <button class="btn btn-print" onclick="window.print()">Cetak / PDF</button>
            <a href="rekap_nota_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">Ekspor Excel</a>
        </div>

        <div class="alert alert-info d-flex justify-content-between align-items-center mb-3 no-print">
            <span><strong>Total Harga Hasil Rekap:</strong> Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></span>
            <span class="text-muted">Jumlah data: <?php echo count($rows); ?></span>
        </div>

        <div class="report-container mx-auto">
            <div class="print-only report-header">
                <h2>Laporan Pembelian Material</h2>
                        <p>Ringkasan nota pembelian material dalam format cetak profesional.</p>
                    </div>

                    <div class="print-only report-info">
                        <div class="report-info-item">
                            <span class="report-info-label">Periode</span>
                            <span class="report-info-value">: <?php 
                                if ($selectedBulan) {
                                    $parts = explode('-', $selectedBulan);
                                    $monthName = $bulanIndonesia[$parts[1]] ?? $parts[1];
                                    echo htmlspecialchars($monthName . ' ' . $parts[0]);
                                } else {
                                    echo 'Semua Periode';
                                }
                            ?></span>
                        </div>
                        <div class="report-info-item">
                            <span class="report-info-label">Diterbitkan</span>
                            <span class="report-info-value">: Timika, <?php echo date('d F Y'); ?></span>


            <div class="card shadow-sm">
                <div class="card-body table-responsive">
                    <table class="hide-project-toko-print">
                        <thead>
                            <tr>
                                <th style="width: 8%; min-width: 70px;">No Reg</th>
                                <th style="width: 7%; min-width: 60px;">Tgl</th>
                                <th style="width: 12%; min-width: 100px;">Project</th>
                                <th style="width: 12%; min-width: 110px;">Toko</th>
                                <th style="width: 34%; min-width: 260px;">Rincian Material</th>
                                <th style="width: 12%; min-width: 120px;">Grand Total</th>
                                <th style="width: 9%; min-width: 90px;">Order By</th>
                                <th style="width: 6%; min-width: 60px;">Ket</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notaSummaries)) : ?>
                                <tr>
                                    <td colspan="8" class="center-cell" style="padding: 20px;">Tidak ada data yang sesuai filter</td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($notaSummaries as $summary) : ?>
                                    <tr>
                                        <td class="center-cell"><?php echo htmlspecialchars($summary['no_register'] ?: '-'); ?></td>
                                        <td class="center-cell"><?php echo htmlspecialchars(!empty($summary['tanggal_belanja']) ? date('d-M', strtotime($summary['tanggal_belanja'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($summary['project'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($summary['nama_toko'] ?: '-'); ?></td>
                                        <td>
                                            <div class="material-list">
                                                <table class="material-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Nama Barang</th>
                                                            <th>Qty</th>
                                                            <th>Harga Barang</th>
                                                            <th>Harga Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($summary['items'] as $item) : ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($item['nama_barang'] ?: '-'); ?></td>
                                                                <td><?php echo htmlspecialchars($item['jumlah_barang'] ?? 0); ?> <?php echo htmlspecialchars($item['satuan_barang'] ?: '-'); ?></td>
                                                                <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($item['harga_barang'] ?? 0, 0, '.', ',')); ?></td>
                                                                <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($item['total_harga'] ?? 0, 0, '.', ',')); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                        <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($summary['grand_total'] ?? 0, 0, '.', ',')); ?></td>
                                        <td class="center-cell" style="font-size: 8pt;"><?php echo htmlspecialchars($summary['pemesan'] ?: '-'); ?></td>
                                        <td class="center-cell" style="font-size: 8pt;"><?php echo htmlspecialchars($summary['keterangan'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="5" style="text-align: right; padding-right: 5px;">TOTAL KESELURUHAN :</td>
                                    <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($grandTotal, 0, '.', ',')); ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="signature-wrapper">
    <?php
    $data_ttd = [
        "Direktur" => "Joule Rizal",
        "Direktris" => "Pravita F. Anggreini",
        "Project Manager" => "....................",
        "Manager Material" => "....................",
        "Material" => "...................."
    ];

    foreach ($data_ttd as $jabatan => $nama) {
        echo '
        <div class="signature-box">
            <div><strong>' . $jabatan . '</strong></div>
            <div class="signature-space"></div>
            <div>(' . $nama . ')</div>
        </div>';
    }
    ?>
</div>
        </div>
        </div>
    </div>
    <?php include 'sidebar-script.php'; ?>
</body>
</html>
