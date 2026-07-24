<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$selectedProject = trim((string)($_GET['project'] ?? ''));
$selectedToko = trim((string)($_GET['toko'] ?? ''));

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota WHERE 1=1";
$params = [];
$types = '';

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
$notaCount = 0;
$itemCount = 0;

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
        $notaCount += 1;
    }

    $summaryRows[$groupKey]['item_count'] += 1;
    $summaryRows[$groupKey]['grand_total'] += (float)($row['total_harga'] ?? 0);
    $grandTotal += (float)($row['total_harga'] ?? 0);
    $itemCount += 1;

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

$projectList = mysqli_query($conn, "SELECT DISTINCT project FROM nota WHERE project IS NOT NULL AND project <> '' ORDER BY project ASC");
$tokoList = mysqli_query($conn, "SELECT DISTINCT nama_toko FROM nota WHERE nama_toko IS NOT NULL AND nama_toko <> '' ORDER BY nama_toko ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Summary Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
    <style>
        body { background: #f8f9fa; font-family: 'Calibri', Arial, sans-serif; }
        .summary-card { border-radius: 16px; }
        .stat-box { background: linear-gradient(135deg, #eef5ff, #ffffff); border: 1px solid #d7e5ff; border-radius: 14px; }
        .table thead th { white-space: nowrap; }
        .number-cell { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="page-shell">
        <?php include 'sidebar.php'; ?>
        <div class="main-content" id="mainContent">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <div>
                        <h2 class="mb-1">Summary Project</h2>
                        <p class="text-muted mb-0">Ringkasan total pembelanjaan berdasarkan pilihan project dan toko yang sudah terinput.</p>
                    </div>
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                        <a href="input.php" class="btn btn-outline-secondary btn-sm">Input Nota</a>
                        <a href="lihat_nota.php" class="btn btn-outline-secondary btn-sm">Lihat Nota</a>
                        <a href="rekap_nota.php" class="btn btn-outline-secondary btn-sm">Rekap Nota</a>
                    </div>
                </div>

                <div class="card shadow-sm summary-card mb-4">
                    <div class="card-body">
                        <form method="get" action="summary_project.php" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label">Pilih Project</label>
                                <select name="project" class="form-select">
                                    <option value="">Semua Project</option>
                                    <?php while ($projectRow = mysqli_fetch_assoc($projectList)) : ?>
                                        <option value="<?php echo htmlspecialchars($projectRow['project']); ?>" <?php echo $selectedProject === $projectRow['project'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($projectRow['project']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Pilih Toko / Vendor</label>
                                <select name="toko" class="form-select">
                                    <option value="">Semua Toko</option>
                                    <?php mysqli_data_seek($tokoList, 0); ?>
                                    <?php while ($tokoRow = mysqli_fetch_assoc($tokoList)) : ?>
                                        <option value="<?php echo htmlspecialchars($tokoRow['nama_toko']); ?>" <?php echo $selectedToko === $tokoRow['nama_toko'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tokoRow['nama_toko']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary">Buat Summary</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="stat-box p-3 h-100">
                            <div class="text-muted small">Project Dipilih</div>
                            <div class="fw-bold fs-5"><?php echo htmlspecialchars($selectedProject !== '' ? $selectedProject : 'Semua'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box p-3 h-100">
                            <div class="text-muted small">Toko Dipilih</div>
                            <div class="fw-bold fs-5"><?php echo htmlspecialchars($selectedToko !== '' ? $selectedToko : 'Semua'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box p-3 h-100">
                            <div class="text-muted small">Total Summary</div>
                            <div class="fw-bold fs-5">Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm summary-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Project</th>
                                        <th>Toko / Vendor</th>
                                        <th>Jumlah Nota</th>
                                        <th>Jumlah Item</th>
                                        <th>Grand Total</th>
                                        <th>Tanggal Awal</th>
                                        <th>Tanggal Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($summaryRows)) : ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Belum ada data summary untuk kombinasi project dan toko yang dipilih.</td>
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'sidebar-script.php'; ?>
</body>
</html>
