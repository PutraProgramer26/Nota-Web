<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$notaTable = 'nota';

function tableExists($conn, $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    return $result && mysqli_num_rows($result) > 0;
}

function fetchOne($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    return $result ? mysqli_fetch_assoc($result) : null;
}

function fetchAllRows($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

$projectCount = 0;
$notaLogCount = 0;
$notaPerProject = [];
$projectPerToko = [];
$historyLog = [];

if (tableExists($conn, $notaTable)) {
    $projectCount = fetchOne($conn, "SELECT COUNT(DISTINCT project) AS total_projects FROM {$notaTable}")['total_projects'] ?? 0;
    $notaLogCount = fetchOne($conn, "SELECT COUNT(*) AS total_nota_log FROM {$notaTable}")['total_nota_log'] ?? 0;

    $notaPerProject = fetchAllRows($conn, "SELECT project AS label, COUNT(*) AS value
        FROM {$notaTable}
        GROUP BY project
        ORDER BY value DESC");

    $projectPerToko = fetchAllRows($conn, "SELECT nama_toko AS label, COUNT(DISTINCT project) AS value
        FROM {$notaTable}
        GROUP BY nama_toko
        ORDER BY value DESC");

    $historyLogRaw = fetchAllRows($conn, "SELECT id, no_register, nama_barang, tanggal_belanja AS tanggal, project AS project_name, nama_toko AS toko_name, total_harga AS total, pemesan, keterangan
        FROM {$notaTable}
        ORDER BY tanggal_belanja DESC, id DESC");

    // Group by no_register and collect items
    $notaSummaries = [];
    foreach ($historyLogRaw as $row) {
        $registerKey = (string)($row['no_register'] ?? '');
        if ($registerKey === '') {
            $registerKey = '__empty__';
        }
        
        if (!isset($notaSummaries[$registerKey])) {
            $notaSummaries[$registerKey] = [
                'no_register' => $row['no_register'] ?? '',
                'tanggal' => $row['tanggal'] ?? '',
                'project_name' => $row['project_name'] ?? '',
                'toko_name' => $row['toko_name'] ?? '',
                'pemesan' => $row['pemesan'] ?? '',
                'grand_total' => 0,
                'items' => [],
            ];
        }
        
        $notaSummaries[$registerKey]['grand_total'] += (float)($row['total'] ?? 0);
        $notaSummaries[$registerKey]['items'][] = [
            'nama_barang' => $row['nama_barang'] ?? '',
            'total' => (float)($row['total'] ?? 0),
            'keterangan' => $row['keterangan'] ?? '',
        ];
    }

    // Convert to indexed array and limit to 50 notes (not items)
    $historyLog = array_slice(array_values($notaSummaries), 0, 50);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Nota Belanja</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: auto;
        }
        .history-table th,
        .history-table td {
            padding: 10px 8px;
            border: 1px solid #dee2e6;
            text-align: left;
            vertical-align: middle;
            word-break: break-word;
        }
        .history-table th {
            background: #f1f3f5;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            color: #333;
        }
        .history-table td {
            background: white;
            font-size: 14px;
        }
        .history-table .number-cell {
            text-align: right;
            padding-right: 12px;
            white-space: nowrap;
            font-family: 'Courier New', monospace;
        }
        .history-table .center-cell {
            text-align: center;
        }
        /* Responsive table styling */
        .history-table thead tr {
            background: #f1f3f5;
        }
        .history-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .history-table tbody tr:nth-child(odd) {
            background-color: #fafbfc;
        }
    </style>
</head>
<body>
    <div class="page-shell">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="brand-icon">N</div>
                <div class="brand-text">
                    <span class="sidebar-title">Aplikasi Nota</span>
                    <small class="sidebar-subtitle">Panel admin</small>
                </div>
            </div>
            <button class="sidebar-toggle" id="toggleSidebar" type="button" aria-label="Toggle sidebar">☰</button>
        </div>
        <nav class="nav flex-column mt-2">
            <div class="nav-section-label">Menu utama</div>
            <a class="nav-link active" href="index.php"><span class="nav-icon">▣</span><span class="nav-text">Dashboard</span></a>
            <a class="nav-link" href="input.php"><span class="nav-icon">✎</span><span class="nav-text">Input Nota</span></a>
            <a class="nav-link" href="lihat_nota.php"><span class="nav-icon">▤</span><span class="nav-text">Lihat Nota</span></a>
            <a class="nav-link" href="rekap_nota.php"><span class="nav-icon">◫</span><span class="nav-text">Rekap Nota</span></a>
            <a class="nav-link" href="pengaturan_project.php"><span class="nav-icon">⚙</span><span class="nav-text">Pengaturan Project</span></a>
            <a class="nav-link" href="manajement_user.php"><span class="nav-icon">👤</span><span class="nav-text">Manajement User</span></a>
            <div class="nav-section-label mt-2">Akun</div>
            <a class="nav-link text-danger" href="logout.php"><span class="nav-icon">↩</span><span class="nav-text">Log Out</span></a>
        </nav>
    </div>

    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                <div>
                    <h2 class="mb-1">Dashboard Nota Belanja Material Project</h2>
                    <p class="text-muted mb-0">Ringkasan data nota, project, toko, dan riwayat log nota.</p>
                </div>
            </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card border-primary shadow-sm stat-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted">Jumlah Project</h6>
                        <div class="value"><?php echo number_format($projectCount); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-success shadow-sm stat-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted">Total Nota Log</h6>
                        <div class="value"><?php echo number_format($notaLogCount); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-info shadow-sm stat-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted">Project Aktif</h6>
                        <p class="mb-0 text-muted">Berdasarkan data nota yang tersimpan.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card border-warning shadow-sm stat-card">
                    <div class="card-body">
                        <h6 class="text-uppercase text-muted">Log Terbaru</h6>
                        <p class="mb-0 text-muted">Riwayat 50 data terakhir.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Diagram Jumlah Nota Setiap Project</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartNotaPerProject" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Diagram Jumlah Project Setiap Toko</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartProjectPerToko" height="280"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">History Log Nota</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>No Register</th>
                            <th>Tanggal</th>
                            <th>Project</th>
                            <th>Toko</th>
                            <th>Nama Barang</th>
                            <th>Total Item</th>
                            <th>Grand Total</th>
                            <th>Pemesan</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historyLog) === 0): ?>
                            <tr>
                                <td colspan="9" class="center-cell" style="padding: 20px;">Belum ada data nota.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historyLog as $summary): ?>
                                <?php $rowspan = count($summary['items']); ?>
                                <?php foreach ($summary['items'] as $index => $item): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['no_register'] ?: '-'); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['tanggal'] ?: '-'); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['project_name'] ?: '-'); ?></td>
                                            <td class="center-cell" rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($summary['toko_name'] ?: '-'); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($item['nama_barang'] ?: '-'); ?></td>
                                        <td class="number-cell">Rp <?php echo htmlspecialchars(number_format($item['total'] ?? 0, 0, '.', ',')); ?></td>
                                        <?php if ($index === 0): ?>
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
    </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleSidebar = document.getElementById('toggleSidebar');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
        const notaPerProjectData = {
            labels: <?php echo json_encode(array_column($notaPerProject, 'label')); ?>,
            datasets: [{
                label: 'Jumlah Nota',
                data: <?php echo json_encode(array_column($notaPerProject, 'value')); ?>,
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
                borderWidth: 1
            }]
        };

        const projectPerTokoData = {
            labels: <?php echo json_encode(array_column($projectPerToko, 'label')); ?>,
            datasets: [{
                label: 'Jumlah Project',
                data: <?php echo json_encode(array_column($projectPerToko, 'value')); ?>,
                backgroundColor: ['#f6c23e', '#1cc88a', '#4e73df', '#e74a3b', '#858796'],
                borderWidth: 1
            }]
        };

        new Chart(document.getElementById('chartNotaPerProject'), {
            type: 'bar',
            data: notaPerProjectData,
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        new Chart(document.getElementById('chartProjectPerToko'), {
            type: 'bar',
            data: projectPerTokoData,
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>
</html>
