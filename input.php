<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$projectSettings = [];
$settingsResult = mysqli_query($conn, "SELECT nama_project, inisial_project, nomor_awal_register FROM project_register_settings ORDER BY nama_project ASC");
while ($setting = mysqli_fetch_assoc($settingsResult)) {
    $prefix = strtoupper(trim($setting['inisial_project'] ?? ''));
    $startNumber = max(1, (int)($setting['nomor_awal_register'] ?? 1));
    $prefixLength = strlen($prefix);
    $maxNoQuery = mysqli_query($conn, "SELECT MAX(CAST(SUBSTRING(no_register, " . ($prefixLength + 1) . ") AS UNSIGNED)) AS max_no FROM nota WHERE no_register IS NOT NULL AND no_register <> '' AND no_register LIKE '" . mysqli_real_escape_string($conn, $prefix) . "%'");
    $maxNoRow = mysqli_fetch_assoc($maxNoQuery);
    $nextNumber = max($startNumber, ((int)($maxNoRow['max_no'] ?? 0)) + 1);

    $projectSettings[] = [
        'nama_project' => $setting['nama_project'],
        'inisial_project' => $prefix,
        'next_number' => $nextNumber,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_register = trim($_POST['no_register'] ?? '');
    $tanggal_belanja = trim($_POST['tanggal_belanja'] ?? '');
    $nama_toko = trim($_POST['nama_toko'] ?? '');
    $project = trim($_POST['project'] ?? '');
    $pemesan = trim($_POST['pemesan'] ?? '');

    $barang_names = $_POST['nama_barang'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $satuans = $_POST['satuan'] ?? [];
    $harga_satuans = $_POST['harga_satuan'] ?? [];
    $keterangans = $_POST['keterangan'] ?? [];

    $inserted = false;

    if ($no_register !== '' && $tanggal_belanja !== '' && $nama_toko !== '' && $project !== '' && $pemesan !== '' && !empty($barang_names)) {
        foreach ($barang_names as $index => $nama_barang) {
            $nama_barang = trim($nama_barang);
            $qty = (int)($qtys[$index] ?? 0);
            $satuan = trim($satuans[$index] ?? '');
            $keterangan = trim($keterangans[$index] ?? '');
            $harga_satuan = (float)str_replace([',', '.'], '', $harga_satuans[$index] ?? 0);

            if ($nama_barang === '' || $qty <= 0 || $satuan === '') {
                continue;
            }

            // Harga satuan wajib diisi kecuali untuk Stock Gudang
            if (strtolower($keterangan) !== 'stock gudang' && $harga_satuan <= 0) {
                continue;
            }

            $total_harga = $qty * $harga_satuan;

            $sql = "INSERT INTO nota (no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssdsssssss', $no_register, $nama_barang, $harga_satuan, $qty, $satuan, $total_harga, $project, $pemesan, $nama_toko, $tanggal_belanja, $keterangan);

            if (mysqli_stmt_execute($stmt)) {
                $inserted = true;
            }
        }
    }

    if ($inserted) {
        $message = 'Data nota berhasil disimpan.';
    } else {
        $message = 'Gagal menyimpan data. Pastikan semua field wajib diisi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Input Nota Pembelanjaan Material</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
    <style>
        body { background: #f8f9fa; }
        .card { border-radius: 14px; }
        .table-responsive { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="page-shell">
        <?php include 'sidebar.php'; ?>
        <div class="main-content" id="mainContent">
            <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Input Nota Pembelanjaan Material</h2>
            <div class="btn-group">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                <a href="input.php" class="btn btn-outline-primary btn-sm active">Input Nota</a>
                <a href="lihat_nota.php" class="btn btn-outline-secondary btn-sm">Lihat Nota</a>
                <a href="rekap_nota.php" class="btn btn-outline-secondary btn-sm">Rekap Nota</a>
            </div>
        </div>
        <?php if (!empty($message)) : ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" novalidate id="formNota">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nomor Register</label>
                            <input type="text" name="no_register" id="no_register" class="form-control" readonly />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Belanja</label>
                            <input type="date" name="tanggal_belanja" class="form-control" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Toko / Vendor</label>
                            <input type="text" name="nama_toko" class="form-control" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Project</label>
                            <input type="text" name="project" id="project" class="form-control" required />
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Nama Pemesan / Requestor</label>
                            <input type="text" name="pemesan" class="form-control" required />
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Rincian Belanja</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addRow">+ Tambah Barang</button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="barangTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 24%">Nama Barang</th>
                                    <th style="width: 10%">Qty</th>
                                    <th style="width: 10%">Satuan</th>
                                    <th style="width: 15%">Harga Satuan</th>
                                    <th style="width: 20%">Keterangan</th>
                                    <th style="width: 8%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                        <tr>
                                    <td><input type="text" name="nama_barang[]" class="form-control" /></td>
                                    <td><input type="number" name="qty[]" class="form-control" min="1" /></td>
                                    <td><input type="text" name="satuan[]" class="form-control" /></td>
                                    <td><input type="number" name="harga_satuan[]" class="form-control harga-satuan" min="0" step="0.01" /></td>
                                    <td>
                                        <select name="keterangan[]" class="form-select keterangan-select">
                                            <option value="Cash">Cash</option>
                                            <option value="invoice">invoice</option>
                                            <option value="stock gudang">Stock Gudang</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-danger btn-sm removeRow">Hapus</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="fw-bold fs-5 text-primary" id="totalBelanjaLabel">Total Belanja: Rp 0</div>
                        <button type="submit" class="btn btn-primary">Simpan Nota</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const projectInput = document.getElementById('project');
        const noRegisterInput = document.getElementById('no_register');
        const projectSettings = <?php echo json_encode($projectSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function getProjectSetting(projectName) {
            const normalized = (projectName || '').trim().toLowerCase();
            return projectSettings.find(setting => (setting.nama_project || '').trim().toLowerCase() === normalized) || null;
        }

        function generateNoRegister() {
            const project = (projectInput.value || '').trim();
            const setting = getProjectSetting(project);
            let initials = 'PR';
            let counter = 100;

            if (setting) {
                initials = setting.inisial_project || initials;
                counter = parseInt(setting.next_number || 1, 10);
            } else {
                initials = project
                    ? project
                        .split(/\s+/)
                        .filter(Boolean)
                        .slice(0, 2)
                        .map(word => word.charAt(0).toUpperCase())
                        .join('')
                        .slice(0, 2)
                    : initials;
            }

            noRegisterInput.value = `${initials}${String(counter).padStart(3, '0')}`;
        }

        projectInput.addEventListener('input', generateNoRegister);

        function addBarangRow() {
            const tableBody = document.querySelector('#barangTable tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" name="nama_barang[]" class="form-control" /></td>
                <td><input type="number" name="qty[]" class="form-control" min="1" /></td>
                <td><input type="text" name="satuan[]" class="form-control" /></td>
                <td><input type="number" name="harga_satuan[]" class="form-control harga-satuan" min="0" step="0.01" /></td>
                <td>
                    <select name="keterangan[]" class="form-select keterangan-select">
                        <option value="Cash">Cash</option>
                        <option value="invoice">invoice</option>
                        <option value="stock gudang">Stock Gudang</option>
                    </select>
                </td>
                <td><button type="button" class="btn btn-danger btn-sm removeRow">Hapus</button></td>`;
            tableBody.appendChild(row);
            attachKeteranganListeners(row);
            updateTotalBelanja();
        }

        function attachKeteranganListeners(row) {
            const keteranganSelect = row.querySelector('.keterangan-select');
            const hargaInput = row.querySelector('.harga-satuan');

            function updateHargaRequired() {
                const isStockGudang = keteranganSelect.value.toLowerCase() === 'stock gudang';
                if (isStockGudang) {
                    hargaInput.removeAttribute('required');
                    hargaInput.classList.add('border-warning');
                } else {
                    hargaInput.setAttribute('required', 'required');
                    hargaInput.classList.remove('border-warning');
                }
            }

            keteranganSelect.addEventListener('change', updateHargaRequired);
            updateHargaRequired();
        }

        function updateTotalBelanja() {
            const rows = document.querySelectorAll('#barangTable tbody tr');
            let total = 0;

            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('input[name="qty[]"]').value) || 0;
                const harga = parseFloat(row.querySelector('input[name="harga_satuan[]"]').value) || 0;
                total += qty * harga;
            });

            document.getElementById('totalBelanjaLabel').textContent = 'Total Belanja: Rp ' + total.toLocaleString('id-ID');
        }

        document.addEventListener('input', function (e) {
            if (e.target.name === 'qty[]' || e.target.name === 'harga_satuan[]') {
                updateTotalBelanja();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('removeRow')) {
                e.target.closest('tr').remove();
                updateTotalBelanja();
            }
        });

        // Custom form validation
        function validateFormBefore() {
            const rows = document.querySelectorAll('#barangTable tbody tr');
            let hasValidRow = false;

            for (let row of rows) {
                const namaBarang = row.querySelector('input[name="nama_barang[]"]').value.trim();
                const qty = row.querySelector('input[name="qty[]"]').value;
                const satuan = row.querySelector('input[name="satuan[]"]').value.trim();
                const hargaSatuan = row.querySelector('input[name="harga_satuan[]"]').value;
                const keterangan = row.querySelector('select[name="keterangan[]"]').value.toLowerCase();

                // Skip empty rows
                if (!namaBarang || !qty || !satuan) {
                    continue;
                }

                // For stock gudang, harga boleh kosong
                if (keterangan === 'stock gudang') {
                    hasValidRow = true;
                    continue;
                }

                // For other keterangan, harga wajib diisi
                if (!hargaSatuan || parseFloat(hargaSatuan) <= 0) {
                    alert('Harga Satuan wajib diisi untuk keterangan Cash atau Invoice!');
                    return false;
                }

                hasValidRow = true;
            }

            if (!hasValidRow) {
                alert('Minimal ada satu barang yang harus diisi dengan lengkap!');
                return false;
            }

            return true;
        }

        // Attach listeners to existing rows
        document.querySelectorAll('#barangTable tbody tr').forEach(row => {
            attachKeteranganListeners(row);
        });

        // Add form submit validation
        document.getElementById('formNota').addEventListener('submit', function(e) {
            if (!validateFormBefore()) {
                e.preventDefault();
                return false;
            }
        });

        document.getElementById('addRow').addEventListener('click', addBarangRow);

        generateNoRegister();
        updateTotalBelanja();
    </script>
    <?php include 'sidebar-script.php'; ?>
</body>
</html>
