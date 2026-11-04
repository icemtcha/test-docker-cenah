<?php
require_once __DIR__ . '/config.php';

$totalProduk   = (int) db()->query('SELECT COUNT(*) FROM produk')->fetchColumn();
$totalKategori = (int) db()->query('SELECT COUNT(*) FROM kategori')->fetchColumn();
$menipis       = produk_stok_menipis();
$penjualanHariIni = db()->query("SELECT COALESCE(SUM(total),0), COUNT(*) FROM transaksi WHERE tanggal = CURDATE()")->fetch(PDO::FETCH_NUM);
$totalHariIni  = (float) $penjualanHariIni[0];
$trxHariIni    = (int) $penjualanHariIni[1];

$trxTerbaru = db()->query('SELECT * FROM transaksi ORDER BY created_at DESC LIMIT 5')->fetchAll();

require_once __DIR__ . '/header.php';
?>
<h2>Dashboard</h2>

<div class="stat-grid" style="margin-bottom:20px;">
  <div class="stat">
    <div class="label">Total Produk</div>
    <div class="value"><?= $totalProduk ?></div>
  </div>
  <div class="stat">
    <div class="label">Total Kategori</div>
    <div class="value"><?= $totalKategori ?></div>
  </div>
  <div class="stat">
    <div class="label">Transaksi Hari Ini</div>
    <div class="value"><?= $trxHariIni ?></div>
  </div>
  <div class="stat">
    <div class="label">Penjualan Hari Ini</div>
    <div class="value"><?= rupiah($totalHariIni) ?></div>
  </div>
</div>

<?php if ($menipis): ?>
<div class="card">
  <h2>&#9888; Peringatan Stok Menipis (<?= count($menipis) ?> produk)</h2>
  <table>
    <thead>
      <tr><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Stok</th><th>Stok Minimum</th></tr>
    </thead>
    <tbody>
      <?php foreach ($menipis as $p): ?>
      <tr>
        <td><?= e($p['kode']) ?></td>
        <td><?= e($p['nama']) ?></td>
        <td><?= e($p['kategori_nama'] ?? '-') ?></td>
        <td><span class="badge badge-low"><?= (int)$p['stok'] ?> <?= e($p['satuan']) ?></span></td>
        <td><?= (int)$p['stok_minimum'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted" style="margin-bottom:0;">Segera lakukan stok masuk melalui menu <a href="stok.php">Stok</a>.</p>
</div>
<?php else: ?>
<div class="alert alert-success">Semua stok produk dalam kondisi aman.</div>
<?php endif; ?>

<div class="card">
  <h2>Transaksi Terbaru</h2>
  <?php if ($trxTerbaru): ?>
  <table>
    <thead>
      <tr><th>Kode</th><th>Tanggal</th><th>Total</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($trxTerbaru as $t): ?>
      <tr>
        <td><?= e($t['kode_transaksi']) ?></td>
        <td><?= e(date('d-m-Y', strtotime($t['tanggal']))) ?></td>
        <td><?= rupiah($t['total']) ?></td>
        <td><a class="btn btn-sm" href="transaksi.php?action=detail&id=<?= (int)$t['id'] ?>">Detail</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">Belum ada transaksi.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
