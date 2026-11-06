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
<h2 class="text-xl font-bold mb-4">Dashboard</h2>

<div class="flex gap-4 flex-wrap mb-5">
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
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Nama Produk</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kategori</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Stok</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Stok Minimum</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($menipis as $p): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['kode']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['nama']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['kategori_nama'] ?? '-') ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><span class="badge badge-low"><?= (int)$p['stok'] ?> <?= e($p['satuan']) ?></span></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= (int)$p['stok_minimum'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted mt-3 mb-0">Segera lakukan stok masuk melalui menu <a class="text-brand underline" href="stok.php">Stok</a>.</p>
</div>
<?php else: ?>
<div class="alert alert-success">Semua stok produk dalam kondisi aman.</div>
<?php endif; ?>

<div class="card">
  <h2>Transaksi Terbaru</h2>
  <?php if ($trxTerbaru): ?>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Tanggal</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Total</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($trxTerbaru as $t): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($t['kode_transaksi']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e(date('d-m-Y', strtotime($t['tanggal']))) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($t['total']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><a class="btn btn-sm" href="transaksi.php?action=detail&id=<?= (int)$t['id'] ?>">Detail</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Belum ada transaksi.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
