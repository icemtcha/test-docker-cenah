<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $id   = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    if ($nama === '') {
        set_flash('danger', 'Nama kategori wajib diisi.');
        redirect('kategori.php');
    }
    try {
        if ($id > 0) {
            db()->prepare('UPDATE kategori SET nama = ? WHERE id = ?')->execute([$nama, $id]);
            set_flash('success', 'Kategori berhasil diperbarui.');
        } else {
            db()->prepare('INSERT INTO kategori (nama) VALUES (?)')->execute([$nama]);
            set_flash('success', 'Kategori berhasil ditambahkan.');
        }
    } catch (PDOException $ex) {
        set_flash('danger', 'Gagal menyimpan: ' . ($ex->getCode() === '23000' ? 'Nama kategori sudah ada.' : $ex->getMessage()));
    }
    redirect('kategori.php');
}

if ($action === 'delete' && $id > 0) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM produk WHERE kategori_id = ?');
    $stmt->execute([$id]);
    $dipakai = (int)$stmt->fetchColumn();
    if ($dipakai > 0) {
        set_flash('danger', "Kategori tidak dapat dihapus, masih dipakai oleh $dipakai produk.");
    } else {
        db()->prepare('DELETE FROM kategori WHERE id = ?')->execute([$id]);
        set_flash('success', 'Kategori berhasil dihapus.');
    }
    redirect('kategori.php');
}

$editData = null;
if ($action === 'edit' && $id > 0) {
    $stmt = db()->prepare('SELECT * FROM kategori WHERE id = ?');
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

$kategoriList = db()->query('SELECT k.*, (SELECT COUNT(*) FROM produk p WHERE p.kategori_id = k.id) AS jumlah_produk
                             FROM kategori k ORDER BY k.nama')->fetchAll();

require_once __DIR__ . '/header.php';
?>
<h2 class="text-xl font-bold mb-4">Kategori Produk</h2>

<div class="card">
  <h2><?= $editData ? 'Edit Kategori' : 'Tambah Kategori' ?></h2>
  <form method="post" action="kategori.php">
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= $editData ? (int)$editData['id'] : 0 ?>">
    <div class="form-group">
      <label>Nama Kategori</label>
      <input type="text" name="nama" required value="<?= e($editData['nama'] ?? '') ?>">
    </div>
    <button class="btn" type="submit">Simpan</button>
    <?php if ($editData): ?><a class="btn btn-secondary" href="kategori.php">Batal</a><?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>Daftar Kategori</h2>
  <?php if ($kategoriList): ?>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Nama Kategori</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Jumlah Produk</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($kategoriList as $k): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($k['nama']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= (int)$k['jumlah_produk'] ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100 actions">
          <a class="btn btn-sm" href="kategori.php?action=edit&id=<?= (int)$k['id'] ?>">Edit</a>
          <a class="btn btn-sm btn-danger" href="kategori.php?action=delete&id=<?= (int)$k['id'] ?>" onclick="return confirm('Hapus kategori ini?')">Hapus</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Belum ada kategori.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
