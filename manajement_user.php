<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? 'user') !== 'superadmin') {
    header('Location: index.php');
    exit;
}

include 'koneksi.php';

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('superadmin', 'user') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$message = '';
$editData = null;
if (isset($_GET['msg'])) {
    $message = trim($_GET['msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        if ($deleteId > 0) {
            if ($deleteId === ($_SESSION['user_id'] ?? 0)) {
                header('Location: manajement_user.php?msg=' . urlencode('Anda tidak dapat menghapus akun sendiri.'));
                exit;
            }
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $deleteId);
            mysqli_stmt_execute($stmt);
            header('Location: manajement_user.php?msg=' . urlencode('User berhasil dihapus.'));
            exit;
        }
    }

    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'user';

    if ($username === '') {
        $message = 'Username wajib diisi.';
    } else {
        if ($id > 0) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'sssi', $username, $hash, $role, $id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, role = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ssi', $username, $role, $id);
            }
            mysqli_stmt_execute($stmt);
            header('Location: manajement_user.php?msg=' . urlencode('Data user berhasil diperbarui.'));
            exit;
        } else {
            if ($password === '') {
                $message = 'Password wajib diisi untuk user baru.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sss', $username, $hash, $role);
                mysqli_stmt_execute($stmt);
                header('Location: manajement_user.php?msg=' . urlencode('User berhasil ditambahkan.'));
                exit;
            }
        }
    }
}

if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, username, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $editData = mysqli_fetch_assoc($result);
}

$result = mysqli_query($conn, "SELECT id, username, role, created_at FROM users ORDER BY username ASC");
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manajemen User</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/app.css" />
</head>
<body>
    <div class="page-shell">
        <?php include 'sidebar.php'; ?>
        <div class="main-content" id="mainContent">
            <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manajemen User</h2>
            <div class="btn-group">
                <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
                <a href="input.php" class="btn btn-outline-secondary btn-sm">Input Nota</a>
                <a href="lihat_nota.php" class="btn btn-outline-secondary btn-sm">Lihat Nota</a>
                <a href="rekap_nota.php" class="btn btn-outline-secondary btn-sm">Rekap Nota</a>
                <a href="pengaturan_project.php" class="btn btn-outline-secondary btn-sm">Pengaturan Project</a>
            </div>
        </div>

        <?php if ($message !== '') : ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Form User</h5>
                <form method="post" action="manajement_user.php">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editData['id'] ?? ''); ?>" />
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($editData['username'] ?? ''); ?>" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" <?php echo empty($editData) ? 'required' : ''; ?> />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Hak Akses</label>
                            <select name="role" class="form-select">
                                <option value="superadmin" <?php echo (($editData['role'] ?? 'user') === 'superadmin') ? 'selected' : ''; ?>>Superadmin</option>
                                <option value="user" <?php echo (($editData['role'] ?? 'user') === 'user') ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="manajement_user.php" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Daftar User</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Username</th>
                                <th>Hak Akses</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)) : ?>
                                <tr><td colspan="5" class="text-center text-muted">Belum ada user.</td></tr>
                            <?php else : ?>
                                <?php $no = 1; foreach ($rows as $row) : ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($row['role'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                        <td>
                                            <a href="manajement_user.php?edit_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <?php if ($row['id'] !== ($_SESSION['user_id'] ?? 0)) : ?>
                                                <form method="post" class="d-inline ms-1" onsubmit="return confirm('Hapus user ini?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>" />
                                                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                                </form>
                                            <?php else : ?>
                                                <span class="text-muted small ms-2">Sedang login</span>
                                            <?php endif; ?>
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
    <?php include 'sidebar-script.php'; ?>
</body>
</html>
