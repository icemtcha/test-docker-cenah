<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'mutasi') {
    $produkId = (int)($_POST['produk_id'] ?? 0);
    $tipe     = $_POST['tipe'] ?? 'masuk';
    $jumlah   = (int)($_POST['jumlah'] ?? 0);
    $ket      = trim($_POST['keterangan'] ?? '');
    $tanggal  = ($_POST['tanggal'] ?? '') !== '' ? $_POST['tanggal'] : date('Y-m-d');

    if ($produkId <= 0 || $jumlah <= 0 || !in_array($tipe, ['masuk', 'keluar'], true)) {
        set_flash('danger', 'Data mutasi tidak valid. Jumlah harus lebih dari 0.');
        redirect('stok.php');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT stok, nama FROM produk WHERE id = ? FOR UPDATE');
        $stmt->execute([$produkId]);
        $produk = $stmt->fetch();
        if (!$produk) {
            throw new RuntimeException('Produk tidak ditemukan.');
        }

        if ($tipe === 'keluar' && (int)$produk['stok'] < $jumlah) {
            throw new RuntimeException('Stok tidak cukup. Stok saat ini: ' . (int)$produk['stok'] . '.');
        }

        $stokBaru = $tipe === 'masuk' ? (int)$produk['stok'] + $jumlah : (int)$produk['stok'] - $jumlah;

        $pdo->prepare('UPDATE produk SET stok = ? WHERE id = ?')->execute([$stokBaru, $produkId]);
        $pdo->prepare('INSERT INTO stok_mutasi (produk_id, tipe, jumlah, keterangan, tanggal) VALUES (?,?,?,?,?)')
            ->execute([$produkId, $tipe, $jumlah, $ket ?: null, $tanggal]);

        $pdo->commit();
        set_flash('success', 'Stok berhasil dicatat. Stok ' . $produk['nama'] . ' kini ' . $stokBaru . '.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Gagal mencatat stok: ' . $ex->getMessage());
    }
    redirect('stok.php');
}

$produkList = ambil_produk();

$filterTipe = $_GET['tipe'] ?? '';
$sql = 'SELECT m.*, p.nama AS produk_nama, p.satuan
        FROM stok_mutasi m
        JOIN produk p ON p.id = m.produk_id';
$params = [];
if (in_array($filterTipe, ['masuk', 'keluar'], true)) {
    $sql .= ' WHERE m.tipe = :tipe';
    $params[':tipe'] = $filterTipe;
}
$sql .= ' ORDER BY m.tanggal DESC, m.id DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$mutasi = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<h2 class="text-xl font-bold mb-4">Stok Masuk / Keluar</h2>

<div class="card">
  <h2>Catat Mutasi Stok</h2>
  <?php if ($produkList): ?>
  <form method="post" action="stok.php">
    <input type="hidden" name="aksi" value="mutasi">
    <div class="form-row">
      <div class="form-group">
        <label>Produk</label>
        <select name="produk_id" required>
          <option value="">- Pilih Produk -</option>
          <?php foreach ($produkList as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['kode'] . ' - ' . $p['nama']) ?> (stok: <?= (int)$p['stok'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Tipe</label>
        <select name="tipe" required>
          <option value="masuk">Stok Masuk</option>
          <option value="keluar">Stok Keluar</option>
        </select>
      </div>
      <div class="form-group">
        <label>Jumlah</label>
        <input type="number" name="jumlah" min="1" required value="1">
      </div>
      <div class="form-group">
        <label>Tanggal</label>
        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Keterangan</label>
      <input type="text" name="keterangan" placeholder="Mis. pembelian supplier / barang rusak">
    </div>
    <button class="btn" type="submit">Simpan Mutasi</button>
  </form>
  <?php else: ?>
  <p class="muted">Belum ada produk. Tambahkan produk terlebih dahulu di menu <a href="produk.php">Produk</a>.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Riwayat Mutasi (100 terakhir)</h2>
  <div class="flex flex-wrap gap-2 mb-4">
    <a class="btn <?= $filterTipe === '' ? '' : 'btn-secondary' ?>" href="stok.php">Semua</a>
    <a class="btn <?= $filterTipe === 'masuk' ? '' : 'btn-secondary' ?>" href="stok.php?tipe=masuk">Masuk</a>
    <a class="btn <?= $filterTipe === 'keluar' ? '' : 'btn-secondary' ?>" href="stok.php?tipe=keluar">Keluar</a>
  </div>
  <?php if ($mutasi): ?>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Tanggal</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Produk</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Tipe</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Jumlah</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Keterangan</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($mutasi as $m): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e(date('d-m-Y', strtotime($m['tanggal']))) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($m['produk_nama']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100">
          <?php if ($m['tipe'] === 'masuk'): ?>
            <span class="badge badge-ok">Masuk</span>
          <?php else: ?>
            <span class="badge badge-low">Keluar</span>
          <?php endif; ?>
        </td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= (int)$m['jumlah'] ?> <?= e($m['satuan']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($m['keterangan'] ?? '-') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Belum ada mutasi stok.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
