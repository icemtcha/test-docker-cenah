<?php
require_once __DIR__ . '/config.php';

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$stmt = db()->prepare("SELECT COALESCE(SUM(total),0) AS total_penjualan, COUNT(*) AS jumlah_transaksi
                       FROM transaksi WHERE tanggal = ?");
$stmt->execute([$tanggal]);
$ringkasan = $stmt->fetch();
$totalItem = 0;
$stmt = db()->prepare("SELECT COALESCE(SUM(d.qty),0) FROM transaksi_detail d JOIN transaksi t ON t.id = d.transaksi_id WHERE t.tanggal = ?");
$stmt->execute([$tanggal]);
$totalItem = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT d.produk_id, p.kode, p.nama, p.satuan, SUM(d.qty) AS total_qty, SUM(d.subtotal) AS total_penjualan
                       FROM transaksi_detail d
                       JOIN transaksi t ON t.id = d.transaksi_id
                       JOIN produk p ON p.id = d.produk_id
                       WHERE t.tanggal = ?
                       GROUP BY d.produk_id, p.kode, p.nama, p.satuan
                       ORDER BY total_qty DESC");
$stmt->execute([$tanggal]);
$perProduk = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM transaksi WHERE tanggal = ? ORDER BY created_at DESC');
$stmt->execute([$tanggal]);
$trxList = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<h2>Laporan Penjualan Harian</h2>

<div class="card">
  <form method="get" action="laporan.php" class="search-bar" style="margin:0;">
    <input type="date" name="tanggal" value="<?= e($tanggal) ?>">
    <button class="btn" type="submit">Tampilkan</button>
    <a class="btn btn-secondary" href="laporan.php">Hari Ini</a>
  </form>
</div>

<div class="stat-grid" style="margin-bottom:20px;">
  <div class="stat">
    <div class="label">Tanggal Laporan</div>
    <div class="value" style="font-size:18px;"><?= e(date('d-m-Y', strtotime($tanggal))) ?></div>
  </div>
  <div class="stat">
    <div class="label">Jumlah Transaksi</div>
    <div class="value"><?= (int)$ringkasan['jumlah_transaksi'] ?></div>
  </div>
  <div class="stat">
    <div class="label">Total Item Terjual</div>
    <div class="value"><?= $totalItem ?></div>
  </div>
  <div class="stat">
    <div class="label">Total Pendapatan</div>
    <div class="value"><?= rupiah($ringkasan['total_penjualan']) ?></div>
  </div>
</div>

<div class="card">
  <h2>Rekap Per Produk</h2>
  <?php if ($perProduk): ?>
  <table>
    <thead>
      <tr><th>Kode</th><th>Produk</th><th>Total Terjual</th><th>Total Penjualan</th></tr>
    </thead>
    <tbody>
      <?php foreach ($perProduk as $p): ?>
      <tr>
        <td><?= e($p['kode']) ?></td>
        <td><?= e($p['nama']) ?></td>
        <td><?= (int)$p['total_qty'] ?> <?= e($p['satuan']) ?></td>
        <td><?= rupiah($p['total_penjualan']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">Tidak ada penjualan pada tanggal ini.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Daftar Transaksi</h2>
  <?php if ($trxList): ?>
  <table>
    <thead>
      <tr><th>Kode</th><th>Waktu</th><th>Total</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($trxList as $t): ?>
      <tr>
        <td><?= e($t['kode_transaksi']) ?></td>
        <td><?= e(date('H:i', strtotime($t['created_at']))) ?></td>
        <td><?= rupiah($t['total']) ?></td>
        <td><a class="btn btn-sm" href="transaksi.php?action=detail&id=<?= (int)$t['id'] ?>">Detail</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">Belum ada transaksi pada tanggal ini.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
