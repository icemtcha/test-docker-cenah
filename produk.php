<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['aksi'] ?? '') === 'simpan') {
        $id        = (int)($_POST['id'] ?? 0);
        $kode      = trim($_POST['kode'] ?? '');
        $nama      = trim($_POST['nama'] ?? '');
        $harga     = (float)($_POST['harga'] ?? 0);
        $satuan    = trim($_POST['satuan'] ?? 'pcs');
        $kategori  = (($_POST['kategori_id'] ?? '') !== '') ? (int)$_POST['kategori_id'] : null;
        $stok      = (int)($_POST['stok'] ?? 0);
        $min       = (int)($_POST['stok_minimum'] ?? 0);

        if ($kode === '' || $nama === '') {
            set_flash('danger', 'Kode dan nama produk wajib diisi.');
            redirect('produk.php?action=' . ($id ? 'edit&id=' . $id : 'create'));
        }

        try {
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE produk SET kode=:kode, nama=:nama, harga=:harga, satuan=:satuan, kategori_id=:kat, stok=:stok, stok_minimum=:min WHERE id=:id');
                $stmt->execute([':kode'=>$kode, ':nama'=>$nama, ':harga'=>$harga, ':satuan'=>$satuan, ':kat'=>$kategori, ':stok'=>$stok, ':min'=>$min, ':id'=>$id]);
                set_flash('success', 'Produk berhasil diperbarui.');
            } else {
                $stmt = db()->prepare('INSERT INTO produk (kode, nama, harga, satuan, kategori_id, stok, stok_minimum) VALUES (:kode,:nama,:harga,:satuan,:kat,:stok,:min)');
                $stmt->execute([':kode'=>$kode, ':nama'=>$nama, ':harga'=>$harga, ':satuan'=>$satuan, ':kat'=>$kategori, ':stok'=>$stok, ':min'=>$min]);
                set_flash('success', 'Produk berhasil ditambahkan.');
            }
        } catch (PDOException $ex) {
            set_flash('danger', 'Gagal menyimpan: ' . ($ex->getCode() === '23000' ? 'Kode produk sudah digunakan.' : $ex->getMessage()));
        }
        redirect('produk.php');
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        db()->prepare('DELETE FROM produk WHERE id = ?')->execute([$id]);
        set_flash('success', 'Produk berhasil dihapus.');
    } catch (PDOException $ex) {
        set_flash('danger', 'Produk tidak dapat dihapus karena sudah dipakai pada transaksi.');
    }
    redirect('produk.php');
}

$cari = trim($_GET['q'] ?? '');
$kategoriList = ambil_kategori();
$produkList = ambil_produk($cari);

$editData = null;
if ($action === 'edit' && $id > 0) {
    $stmt = db()->prepare('SELECT * FROM produk WHERE id = ?');
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

require_once __DIR__ . '/header.php';
?>
<h2 class="text-xl font-bold mb-4">Produk</h2>

<?php if ($action === 'create' || $editData): ?>
<div class="card">
  <h2><?= $editData ? 'Edit Produk' : 'Tambah Produk' ?></h2>
  <form method="post" action="produk.php">
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= $editData ? (int)$editData['id'] : 0 ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Kode Produk</label>
        <input type="text" name="kode" required value="<?= e($editData['kode'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Nama Produk</label>
        <input type="text" name="nama" required value="<?= e($editData['nama'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Harga (Rp)</label>
        <input type="number" name="harga" min="0" step="0.01" required value="<?= e($editData['harga'] ?? '0') ?>">
      </div>
      <div class="form-group">
        <label>Satuan</label>
        <input type="text" name="satuan" required value="<?= e($editData['satuan'] ?? 'pcs') ?>">
      </div>
      <div class="form-group">
        <label>Kategori</label>
        <select name="kategori_id">
          <option value="">- Tanpa Kategori -</option>
          <?php foreach ($kategoriList as $k): ?>
            <option value="<?= (int)$k['id'] ?>" <?= ($editData && (int)$editData['kategori_id'] === (int)$k['id']) ? 'selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Stok Saat Ini</label>
        <input type="number" name="stok" min="0" value="<?= e($editData['stok'] ?? '0') ?>">
      </div>
      <div class="form-group">
        <label>Stok Minimum</label>
        <input type="number" name="stok_minimum" min="0" value="<?= e($editData['stok_minimum'] ?? '5') ?>">
      </div>
    </div>
    <button class="btn" type="submit">Simpan</button>
    <a class="btn btn-secondary" href="produk.php">Batal</a>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Daftar Produk</h2>
  <form method="get" action="produk.php" class="flex flex-wrap gap-2 items-center mb-4">
    <input type="text" name="q" placeholder="Cari nama atau kode produk..." value="<?= e($cari) ?>" class="flex-1 min-w-[200px] px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-200">
    <button class="btn" type="submit">Cari</button>
    <a class="btn btn-secondary" href="produk.php">Reset</a>
    <a class="btn btn-warn" href="produk.php?action=create">+ Tambah</a>
  </form>
  <?php if ($produkList): ?>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Nama</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kategori</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Harga</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Satuan</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Stok</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($produkList as $p): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['kode']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['nama']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['kategori_nama'] ?? '-') ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($p['harga']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['satuan']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100">
          <?php if ((int)$p['stok'] <= (int)$p['stok_minimum']): ?>
            <span class="badge badge-low"><?= (int)$p['stok'] ?> (menipis)</span>
          <?php else: ?>
            <span class="badge badge-ok"><?= (int)$p['stok'] ?></span>
          <?php endif; ?>
        </td>
        <td class="px-3 py-2.5 border-b border-slate-100 actions">
          <a class="btn btn-sm" href="produk.php?action=edit&id=<?= (int)$p['id'] ?>">Edit</a>
          <a class="btn btn-sm btn-danger" href="produk.php?action=delete&id=<?= (int)$p['id'] ?>" onclick="return confirm('Hapus produk ini?')">Hapus</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Tidak ada produk<?= $cari ? ' untuk pencarian "' . e($cari) . '"' : '' ?>.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
