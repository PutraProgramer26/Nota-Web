<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'koneksi.php';

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS project_register_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_project VARCHAR(100) NOT NULL,
        inisial_project VARCHAR(20) NOT NULL,
        nomor_awal_register INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_nama_project (nama_project),
        UNIQUE KEY uniq_inisial_project (inisial_project)
    )
");

$message = '';
$editData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $namaProject = trim($_POST['nama_project'] ?? '');
    $inisialProject = strtoupper(trim($_POST['inisial_project'] ?? ''));
    $nomorAwal = (int)($_POST['nomor_awal_register'] ?? 1);

    if ($namaProject === '' || $inisialProject === '' || $nomorAwal < 1) {
        $message = 'Semua field wajib diisi dengan benar.';
    } else {
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE project_register_settings SET nama_project = ?, inisial_project = ?, nomor_awal_register = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssii', $namaProject, $inisialProject, $nomorAwal, $id);
            mysqli_stmt_execute($stmt);
            $message = 'Data berhasil diperbarui.';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO project_register_settings (nama_project, inisial_project, nomor_awal_register) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssi', $namaProject, $inisialProject, $nomorAwal);
            mysqli_stmt_execute($stmt);
            $message = 'Data berhasil disimpan.';
        }

        header('Location: pengaturan_project.php');
        exit;
    }
}

if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, nama_project, inisial_project, nomor_awal_register FROM project_register_settings WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $editData = mysqli_fetch_assoc($result);
}

$result = mysqli_query($conn, "SELECT id, nama_project, inisial_project, nomor_awal_register FROM project_register_settings ORDER BY nama_project ASC");
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pengaturan Project</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Pengaturan Project</h2>
            <div class="btn-group">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                <a href="input.php" class="btn btn-outline-secondary btn-sm">Input Nota</a>
                <a href="lihat_nota.php" class="btn btn-outline-secondary btn-sm">Lihat Nota</a>
                <a href="rekap_nota.php" class="btn btn-outline-secondary btn-sm">Rekap Nota</a>
            </div>
        </div>

        <?php if ($message !== '') : ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Form Pengaturan Awal Nomor Register</h5>
                <form method="post" action="pengaturan_project.php">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editData['id'] ?? ''); ?>" />
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nama Project</label>
                            <input type="text" name="nama_project" class="form-control" required value="<?php echo htmlspecialchars($editData['nama_project'] ?? ''); ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Inisial Project</label>
                            <input type="text" name="inisial_project" class="form-control" required maxlength="10" value="<?php echo htmlspecialchars($editData['inisial_project'] ?? ''); ?>" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nomor Awal Register</label>
                            <input type="number" name="nomor_awal_register" class="form-control" min="1" required value="<?php echo htmlspecialchars($editData['nomor_awal_register'] ?? '1'); ?>" />
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="pengaturan_project.php" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Daftar Project</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Project</th>
                                <th>Inisial</th>
                                <th>Nomor Awal Register</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada data project.</td>
                                </tr>
                            <?php else : ?>
                                <?php $no = 1; foreach ($rows as $row) : ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_project']); ?></td>
                                        <td><?php echo htmlspecialchars($row['inisial_project']); ?></td>
                                        <td><?php echo htmlspecialchars($row['nomor_awal_register']); ?></td>
                                        <td>
                                            <a href="pengaturan_project.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
