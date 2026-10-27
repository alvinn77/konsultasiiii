
<?php
session_start();
require __DIR__ . '/db.php';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $npm = trim($_POST['npm'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM users WHERE npm = ?');
        $stmt->execute([$npm]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $account['id'],
                'nama' => $account['nama'],
                'npm' => $account['npm'],
                'role' => $account['role']
            ];

            header('Location: index.php');
            exit;
        }

        $error = 'ID login atau password salah.';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    } elseif (isset($_SESSION['user'])) {
        $user = $_SESSION['user'];

        if ($action === 'tambah' && $user['role'] === 'mahasiswa') {
            $matkul = trim($_POST['mata_kuliah'] ?? '');
            $pertanyaan = trim($_POST['pertanyaan'] ?? '');

            if ($matkul === '' || $pertanyaan === '') {
                $error = 'Semua kolom wajib diisi.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO konsultasi
                    (nama_mahasiswa, npm, mata_kuliah, pertanyaan)
                    VALUES (?, ?, ?, ?)'
                );

                $stmt->execute([
                    $user['nama'],
                    $user['npm'],
                    $matkul,
                    $pertanyaan
                ]);

                header('Location: index.php?pesan=kirim');
                exit;
            }
        } elseif ($action === 'update' && $user['role'] === 'dosen') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $catatan = trim($_POST['catatan_dosen'] ?? '');
            $allowed = ['Menunggu', 'Diproses', 'Selesai'];

            if ($id > 0 && in_array($status, $allowed, true)) {
                $stmt = $pdo->prepare(
                    'UPDATE konsultasi
                     SET status = ?, catatan_dosen = ?
                     WHERE id = ?'
                );

                $stmt->execute([$status, $catatan, $id]);
                header('Location: index.php?pesan=update');
                exit;
            }

            $error = 'Data tindak lanjut tidak valid.';
        }
    }
}

$user = $_SESSION['user'] ?? null;

if (($_GET['pesan'] ?? '') === 'kirim') {
    $success = 'Konsultasi berhasil dikirim.';
} elseif (($_GET['pesan'] ?? '') === 'update') {
    $success = 'Tindak lanjut berhasil disimpan.';
}

$rows = [];

if ($user) {
    if ($user['role'] === 'dosen') {
        $stmt = $pdo->query(
            'SELECT * FROM konsultasi ORDER BY created_at DESC'
        );
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM konsultasi
             WHERE npm = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$user['npm']]);
        $rows = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Konsultasi Akademik</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">

<header class="bg-blue-700 text-white p-5">
    <div class="max-w-5xl mx-auto flex justify-between items-center gap-3">
        <div>
            <h1 class="text-xl font-bold">Sistem Konsultasi Akademik</h1>
            <p class="text-sm text-blue-100">Layanan mahasiswa dan dosen</p>
        </div>

        <?php if ($user): ?>
            <form method="post">
                <input type="hidden" name="action" value="logout">
                <button class="bg-white text-blue-700 px-4 py-2 rounded-lg">
                    Keluar
                </button>
            </form>
        <?php endif; ?>
    </div>
</header>

<main class="max-w-5xl mx-auto p-4 md:p-6">

    <?php if ($error): ?>
        <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!$user): ?>

        <section class="max-w-md mx-auto mt-10 bg-white rounded-2xl shadow p-6">
            <h2 class="text-2xl font-bold mb-2">Login</h2>
            <p class="text-slate-500 mb-5">Masuk ke akun kamu.</p>

            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="login">

                <div>
                    <label class="block mb-1">NPM / ID login</label>
                    <input name="npm" required
                        class="w-full border rounded-lg p-3">
                </div>

                <div>
                    <label class="block mb-1">Password</label>
                    <input type="password" name="password" required
                        class="w-full border rounded-lg p-3">
                </div>

                <button class="w-full bg-blue-700 text-white rounded-lg p-3">
                    Masuk
                </button>
            </form>

            <div class="bg-slate-50 rounded-lg p-3 mt-5 text-sm">
                <p class="font-semibold">Akun pengujian</p>
                <p>Mahasiswa: 12345678 / 12345</p>
                <p>Dosen: 12345 / 12345</p>
            </div>
        </section>

    <?php else: ?>

        <section class="bg-white rounded-2xl shadow p-5 mb-6">
            <p class="text-sm text-slate-500">Selamat datang</p>
            <h2 class="text-2xl font-bold"><?= e($user['nama']) ?></h2>
            <p class="text-blue-700"><?= e(ucfirst($user['role'])) ?></p>
        </section>

        <?php if ($user['role'] === 'mahasiswa'): ?>
            <section class="bg-white rounded-2xl shadow p-5 mb-6">
                <h2 class="text-xl font-bold mb-4">Ajukan Konsultasi</h2>

                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="tambah">

                    <div>
                        <label class="block mb-1">Mata kuliah</label>
                        <input name="mata_kuliah" required
                            class="w-full border rounded-lg p-3"
                            placeholder="Contoh: Basis Data">
                    </div>

                    <div>
                        <label class="block mb-1">Pertanyaan / kendala</label>
                        <textarea name="pertanyaan" rows="4" required
                            class="w-full border rounded-lg p-3"
                            placeholder="Tuliskan kendala akademik kamu"></textarea>
                    </div>

                    <button class="bg-blue-700 text-white px-5 py-3 rounded-lg">
                        Kirim Konsultasi
                    </button>
                </form>
            </section>
        <?php endif; ?>

        <section class="bg-white rounded-2xl shadow p-5">
            <h2 class="text-xl font-bold mb-4">
                <?= $user['role'] === 'dosen'
                    ? 'Semua Konsultasi'
                    : 'Riwayat Konsultasi Saya' ?>
            </h2>

            <?php if (!$rows): ?>
                <p class="text-slate-500">Belum ada konsultasi.</p>
            <?php endif; ?>

            <div class="space-y-4">
                <?php foreach ($rows as $r): ?>
                    <article class="border rounded-xl p-4">
                        <div class="flex justify-between flex-wrap gap-2 mb-3">
                            <h3 class="font-bold text-lg">
                                <?= e($r['mata_kuliah']) ?>
                            </h3>
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                                <?= e($r['status']) ?>
                            </span>
                        </div>

                        <?php if ($user['role'] === 'dosen'): ?>
                            <p class="text-sm text-slate-500 mb-2">
                                Mahasiswa: <?= e($r['nama_mahasiswa']) ?>
                                (<?= e($r['npm']) ?>)
                            </p>
                        <?php endif; ?>

                        <p class="font-semibold">Pertanyaan</p>
                        <p class="mb-3 whitespace-pre-wrap"><?= e($r['pertanyaan']) ?></p>

                        <p class="font-semibold">Catatan dosen</p>
                        <p class="mb-3 whitespace-pre-wrap">
                            <?= e($r['catatan_dosen'] ?: 'Belum ada catatan.') ?>
                        </p>

                        <p class="text-xs text-slate-500 mb-4">
                            <?= e($r['created_at']) ?>
                        </p>

                        <?php if ($user['role'] === 'dosen'): ?>
                            <form method="post" class="border-t pt-4 space-y-3">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                                <div>
                                    <label class="block mb-1">Status</label>
                                    <select name="status" class="w-full border rounded-lg p-3">
                                        <?php foreach (['Menunggu', 'Diproses', 'Selesai'] as $s): ?>
                                            <option value="<?= e($s) ?>"
                                                <?= $r['status'] === $s ? 'selected' : '' ?>>
                                                <?= e($s) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block mb-1">Catatan / tindak lanjut</label>
                                    <textarea name="catatan_dosen" rows="3"
                                        class="w-full border rounded-lg p-3"><?= e($r['catatan_dosen']) ?></textarea>
                                </div>

                                <button class="bg-emerald-600 text-white px-4 py-2 rounded-lg">
                                    Simpan Tindak Lanjut
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

    <?php endif; ?>

</main>

<footer class="text-center text-slate-500 text-sm p-6">
    Apps by Alvinestarss © 2026
</footer>

</body>
</html>
