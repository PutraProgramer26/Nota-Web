<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

$sql = "SELECT id, no_register, nama_barang, harga_barang, jumlah_barang, satuan_barang, total_harga, project, pemesan, nama_toko, tanggal_belanja, keterangan
        FROM nota
        ORDER BY tanggal_belanja DESC, id DESC";
$result = mysqli_query($conn, $sql);
$rows = [];
if ($result) {
    $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Daftar Nota</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
</head>
<body>
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
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>No Register</th>
                            <th>Nama Barang</th>
                            <th>Harga</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Total</th>
                            <th>Project</th>
                            <th>Pemesan</th>
                            <th>Toko</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr><td colspan="12" class="text-center text-muted">Belum ada data nota.</td></tr>
                        <?php else : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_register']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($row['harga_barang'] ?? 0, 0, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars($row['jumlah_barang']); ?></td>
                                    <td><?php echo htmlspecialchars($row['satuan_barang']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($row['total_harga'] ?? 0, 0, ',', '.')); ?></td>
                                    <td><?php echo htmlspecialchars($row['project']); ?></td>
                                    <td><?php echo htmlspecialchars($row['pemesan']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_toko']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tanggal_belanja']); ?></td>
                                    <td><?php echo htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
