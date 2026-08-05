<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$selectedProjectRaw = $_GET['project'] ?? [];
if (!is_array($selectedProjectRaw)) {
    $selectedProjectRaw = [$selectedProjectRaw];
}
$selectedProjects = array_values(array_filter(array_map('trim', $selectedProjectRaw), function ($value) {
    return $value !== '';
}));
$selectedToko = trim((string)($_GET['toko'] ?? ''));
$selectedBulan = trim((string)($_GET['bulan'] ?? ''));
$selectedKategori = 'invoice';

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota WHERE keterangan = ?";
$params = [$selectedKategori];
$types = 's';

if (!empty($selectedProjects)) {
    $projectPlaceholders = implode(', ', array_fill(0, count($selectedProjects), '?'));
    $sql .= " AND project IN ($projectPlaceholders)";
    foreach ($selectedProjects as $projectValue) {
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

$projectList = mysqli_query($conn, "SELECT DISTINCT project FROM nota WHERE keterangan = 'invoice' AND project IS NOT NULL AND project <> '' ORDER BY project ASC");
$tokoList = mysqli_query($conn, "SELECT DISTINCT nama_toko FROM nota WHERE keterangan = 'invoice' AND nama_toko IS NOT NULL AND nama_toko <> '' ORDER BY nama_toko ASC");
$bulanList = mysqli_query($conn, "SELECT DISTINCT DATE_FORMAT(tanggal_belanja, '%Y-%m') AS bulan FROM nota WHERE keterangan = 'invoice' AND tanggal_belanja IS NOT NULL ORDER BY bulan DESC");

$bulanIndonesia = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                   '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Summary Pembelian Material</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
    <style>
        body { background: #f8f9fa; font-family: 'Calibri', Arial, sans-serif; }
        .summary-card { border-radius: 16px; }
        .stat-box { background: linear-gradient(135deg, #eef5ff, #ffffff); border: 1px solid #d7e5ff; border-radius: 14px; }
        .table thead th { white-space: nowrap; }
        .number-cell { text-align: right; white-space: nowrap; }
        .print-only { display: none; }
        .btn-print { background: #0d6efd; color: white; }
        .summary-print-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9pt;
        }
        .summary-print-table th,
        .summary-print-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        .summary-print-table th {
            background: #f1f1f1;
            text-align: center;
            font-weight: bold;
        }
        .summary-print-table td.number-cell {
            text-align: right;
            white-space: nowrap;
        }
        .summary-print-table tr.total-row td {
            font-weight: bold;
            background: #f8f9fa;
        }
        .project-dropdown {
            position: relative;
        }
        .project-dropdown-toggle {
            width: 100%;
            text-align: left;
            background: white;
            color: #1f2937;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 0.625rem 0.9rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .project-dropdown-toggle .caret {
            font-size: 0.8rem;
            color: #64748b;
        }
        .project-dropdown-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 20;
            background: white;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            padding: 8px;
            display: none;
            max-height: 240px;
            overflow-y: auto;
        }
        .project-dropdown.open .project-dropdown-menu {
            display: block;
        }
        .project-dropdown-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.92rem;
        }
        .project-dropdown-item:hover {
            background: #f3f4f6;
        }
        .project-dropdown-item input {
            margin: 0;
        }
        .signature-wrapper {
            display: none !important;
        }
        @media print {
            html, body {
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
            .page-shell {
                display: block !important;
            }
            .sidebar {
                display: none !important;
            }
            .main-content {
                width: 100% !important;
                margin-left: 0 !important;
                max-width: 100% !important;
            }
            .summary-output-card {
                display: none !important;
            }
            .report-header {
                text-align: center;
                margin-bottom: 10px;
                border-bottom: 2px solid #000;
                padding-bottom: 8px;
            }
            .report-header h2 {
                margin: 0;
                font-size: 14pt;
                font-weight: bold;
            }
            .report-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 12px;
                border-bottom: 1px solid #000;
                padding-bottom: 8px;
                font-size: 9pt;
            }
            .report-info-item {
                display: flex;
                align-items: center;
                margin-bottom: 4px;
            }
            .report-info-label {
                font-weight: bold;
                width: 40%;
            }
            .report-info-value {
                width: 60%;
            }
            .summary-print-table {
                font-size: 8.5pt;
            }
            .signature-wrapper {
                display: flex !important;
                justify-content: space-between;
                margin-top: 40px;
                width: 100%;
                text-align: center;
                flex-wrap: wrap;
                page-break-inside: avoid;
            }
            .signature-box {
                flex: 1;
                min-width: 100px;
                padding: 10px;
            }
            .signature-space {
                height: 70px;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
            .card-body {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <?php include 'sidebar.php'; ?>
        <div class="main-content" id="mainContent">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
                    <div>
                        <h2 class="mb-1">Summary Pembelian Material</h2>
                        <p class="text-muted mb-0">Ringkasan total pembelanjaan invoice berdasarkan pilihan bulan, project dan toko.</p>
                    </div>
                    <div class="btn-group">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                        <a href="input.php" class="btn btn-outline-secondary btn-sm">Input Nota</a>
                        <a href="lihat_nota.php" class="btn btn-outline-secondary btn-sm">Lihat Nota</a>
                        <a href="rekap_nota.php" class="btn btn-outline-secondary btn-sm">Rekap Nota</a>
                    </div>
                </div>

                <div class="card shadow-sm summary-card mb-4 no-print">
                    <div class="card-body">
                        <form method="get" action="summary_project.php" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Pilih Bulan</label>
                                <select name="bulan" class="form-select">
                                    <option value="">Semua Bulan</option>
                                    <?php mysqli_data_seek($bulanList, 0); ?>
                                    <?php while ($bulanRow = mysqli_fetch_assoc($bulanList)) : ?>
                                        <?php $bulanValue = htmlspecialchars($bulanRow['bulan']); ?>
                                        <option value="<?php echo $bulanValue; ?>" <?php echo $selectedBulan === $bulanRow['bulan'] ? 'selected' : ''; ?>>
                                            <?php
                                                $parts = explode('-', $bulanRow['bulan']);
                                                $monthName = $bulanIndonesia[$parts[1]] ?? $parts[1];
                                                echo htmlspecialchars($monthName . ' ' . $parts[0]);
                                            ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pilih Project</label>
                                <div class="project-dropdown" id="projectDropdown">
                                    <button type="button" class="project-dropdown-toggle" id="projectDropdownToggle">
                                        <span id="projectDropdownText"><?php echo htmlspecialchars(!empty($selectedProjects) ? 'Project Dipilih (' . count($selectedProjects) . ')' : 'Semua Project'); ?></span>
                                        <span class="caret">▾</span>
                                    </button>
                                    <div class="project-dropdown-menu" id="projectDropdownMenu">
                                        <?php while ($projectRow = mysqli_fetch_assoc($projectList)) : ?>
                                            <label class="project-dropdown-item">
                                                <input type="checkbox" name="project[]" value="<?php echo htmlspecialchars($projectRow['project']); ?>" <?php echo in_array($projectRow['project'], $selectedProjects, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($projectRow['project']); ?></span>
                                            </label>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-12 d-flex gap-2 justify-content-end">
                                <button type="submit" class="btn btn-primary">Buat Summary</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="d-flex gap-2 mb-3 no-print">
                    <button class="btn btn-print" onclick="window.print()">Cetak / PDF</button>
                    <a href="summary_project_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">Ekspor Excel</a>
                </div>

                <div class="row g-3 mb-4 no-print">
                    <div class="col-md-4">
                        <div class="stat-box p-3 h-100">
                            <div class="text-muted small">Bulan Dipilih</div>
                            <div class="fw-bold fs-5"><?php echo htmlspecialchars($selectedBulan !== '' ? $selectedBulan : 'Semua'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box p-3 h-100">
                            <div class="text-muted small">Project Dipilih</div>
                            <div class="fw-bold fs-5"><?php echo htmlspecialchars(!empty($selectedProjects) ? implode(', ', $selectedProjects) : 'Semua'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box p-3 h-100">
                            <div class="text-muted small">Toko Dipilih</div>
                            <div class="fw-bold fs-5"><?php echo htmlspecialchars($selectedToko !== '' ? $selectedToko : 'Semua'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="print-only report-header">
                    <h2>Summary Pembelian Material</h2>
                </div>

                <div class="print-only report-info">
                    <div>
                        <div class="report-info-item">
                            <span class="report-info-label">Periode</span>
                            <span class="report-info-value">: <?php echo htmlspecialchars($selectedBulan !== '' ? $selectedBulan : 'Semua Bulan'); ?></span>
                        </div>
                        <div class="report-info-item">
                            <span class="report-info-label">Project</span>
                            <span class="report-info-value">: <?php echo htmlspecialchars(!empty($selectedProjects) ? implode(', ', $selectedProjects) : 'Semua Project'); ?></span>
                        </div>
                    </div>
                    <div>
                        <div class="report-info-item">
                            <span class="report-info-label">Toko</span>
                            <span class="report-info-value">: <?php echo htmlspecialchars($selectedToko !== '' ? $selectedToko : 'Semua Toko'); ?></span>
                        </div>
                        <div class="report-info-item">
                            <span class="report-info-label">Kategori</span>
                            <span class="report-info-value">: Invoice</span>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm summary-card summary-output-card">
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
                                        <th>Jumlah Total Harga</th>
                                        <th>Tanggal Awal</th>
                                        <th>Tanggal Akhir</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($summaryRows)) : ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Belum ada data summary invoice untuk kombinasi bulan, project dan toko yang dipilih.</td>
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

                <div class="print-only">
                    <table class="summary-print-table">
                        <thead>
                            <tr>
                                <th style="width: 12%;">No</th>
                                <th style="width: 44%;">Project</th>
                                <th style="width: 44%;">Jumlah Total Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($summaryRows as $summary) : ?>
                                <tr>
                                    <td class="center-cell"><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($summary['project']); ?></td>
                                    <td class="number-cell">Rp <?php echo number_format($summary['grand_total'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2" style="text-align: right;">GRAND TOTAL</td>
                                <td class="number-cell">Rp <?php echo number_format($grandTotal, 0, ',', '.'); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="signature-wrapper">
                        <?php
                        $data_ttd = [
                            'Direktur' => 'Joule Rizal',
                            'Direktris' => 'Pravita F. Anggreini',
                            'Project Manager' => '....................',
                            'Manager Material' => '....................',
                            'Material' => '....................'
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
    </div>
    <?php include 'sidebar-script.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dropdown = document.getElementById('projectDropdown');
            const toggle = document.getElementById('projectDropdownToggle');
            const menu = document.getElementById('projectDropdownMenu');
            const text = document.getElementById('projectDropdownText');
            const checkboxes = menu.querySelectorAll('input[name="project[]"]');

            if (!dropdown || !toggle || !menu || !text || checkboxes.length === 0) {
                return;
            }

            const updateLabel = function () {
                const selected = Array.from(checkboxes)
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.parentElement.textContent.trim());

                if (selected.length > 0) {
                    text.textContent = selected.length === 1
                        ? selected[0]
                        : 'Project Dipilih (' + selected.length + ')';
                } else {
                    text.textContent = 'Semua Project';
                }
            };

            toggle.addEventListener('click', function () {
                dropdown.classList.toggle('open');
            });

            document.addEventListener('click', function (event) {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('open');
                }
            });

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', updateLabel);
            });

            updateLabel();
        });
    </script>
</body>
</html>
