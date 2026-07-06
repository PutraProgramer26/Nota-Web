<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota
        ORDER BY tanggal_belanja DESC, no_register DESC";
$result = mysqli_query($conn, $sql);
$allRows = [];
if ($result) {
    $allRows = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Group by no_register and collect items
$notaSummaries = [];
foreach ($allRows as $row) {
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
        'keterangan' => $row['keterangan'] ?? '',
    ];

}

// Convert to indexed array
$rows = array_values($notaSummaries);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Daftar Nota</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
    <style>
        .nota-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: auto;
        }
        .nota-table th,
        .nota-table td {
            padding: 8px 6px;
            border: 1px solid #dee2e6;
            text-align: left;
            vertical-align: middle;
        }
        .nota-table th {
            background: #f1f3f5;
            font-weight: bold;
            text-align: center;
        }
        .nota-table td {
            background: white;
        }
        .nota-table .number-cell {
            text-align: right;
            padding-right: 5px;
        }
        .nota-table .center-cell {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <?php include 'sidebar.php'; ?>
        <div class="main-content" id="mainContent">
            <div class="container py-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                    <h2 class="mb-0">Daftar Nota Pembelian</h2>
                    <div class="btn-group mt-2 mt-md-0">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                        <a href="input.php" class="btn btn-outline-secondary btn-sm">Input Nota</a>
                        <a href="lihat_nota.php" class="btn btn-outline-primary btn-sm active">Lihat Nota</a>
                        <a href="rekap_nota.php" class="btn btn-outline-secondary btn-sm">Rekap Nota</a>
                    </div>
                </div>
        <div class="card shadow-sm">
            <div class="card-body table-responsive">
                <table class="nota-table">
                    <thead>
                        <tr>
                            <th style="width: 8%;">No Register</th>
                            <th style="width: 8%;">Tanggal</th>
                            <th style="width: 10%;">Project</th>
                            <th style="width: 10%;">Toko</th>
                            <th style="width: 15%;">Nama Barang</th>
                            <th style="width: 8%;">Qty</th>
                            <th style="width: 10%;">Harga</th>
                            <th style="width: 10%;">Total</th>
                            <th style="width: 10%;">Grand Total</th>
                            <th style="width: 8%;">Pemesan</th>
                            <th style="width: 9%;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr><td colspan="11" class="center-cell" style="padding: 20px;">Belum ada data nota.</td></tr>
                        <?php else : ?>
                            <?php foreach ($rows as $summary) : ?>
                                <?php $rowspan = count($summary['items']); ?>
                                <?php foreach ($summary['items'] as $index => $item) : ?>
                                    <tr>
                                        <?php if ($index === 0) : ?>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['no_register'] ?: '-'); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['tanggal_belanja'] ?: '-'); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['project'] ?: '-'); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['nama_toko'] ?: '-'); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($item['nama_barang'] ?: '-'); ?></td>
                                        <td class="center-cell"><?php echo htmlspecialchars($item['jumlah_barang'] ?? 0); ?> <?php echo htmlspecialchars($item['satuan_barang'] ?: '-'); ?></td>
                                        <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($item['harga_barang'] ?? 0, 0, '.', ',')); ?></td>
                                        <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($item['total_harga'] ?? 0, 0, '.', ',')); ?></td>
                                        <?php if ($index === 0) : ?>
                                            <td class="number-cell" rowspan="<?php echo $rowspan; ?>">Rp <?php echo htmlspecialchars(number_format($summary['grand_total'] ?? 0, 0, '.', ',')); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['pemesan'] ?: '-'); ?></td>
                                        <?php endif; ?>
                                        <td class="center-cell"><?php echo htmlspecialchars($item['keterangan'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include 'sidebar-script.php'; ?>
</body>
</html>
