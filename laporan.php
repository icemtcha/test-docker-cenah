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

if (($_GET['export'] ?? '') === 'csv') {
    $namaFile = 'laporan-' . $tanggal . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Laporan Penjualan Harian']);
    fputcsv($out, ['Tanggal', $tanggal]);
    fputcsv($out, []);
    fputcsv($out, ['Jumlah Transaksi', (int)$ringkasan['jumlah_transaksi']]);
    fputcsv($out, ['Total Item Terjual', $totalItem]);
    fputcsv($out, ['Total Pendapatan', (float)$ringkasan['total_penjualan']]);
    fputcsv($out, []);
    fputcsv($out, ['Kode', 'Produk', 'Total Terjual', 'Satuan', 'Total Penjualan']);
    foreach ($perProduk as $p) {
        fputcsv($out, [$p['kode'], $p['nama'], (int)$p['total_qty'], $p['satuan'], (float)$p['total_penjualan']]);
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/header.php';
?>
<h2 class="text-xl font-bold mb-4">Laporan Penjualan Harian</h2>

<div class="card">
  <form method="get" action="laporan.php" class="flex flex-wrap gap-2 items-center">
    <input type="date" name="tanggal" value="<?= e($tanggal) ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-200">
    <button class="btn" type="submit">Tampilkan</button>
    <a class="btn btn-secondary" href="laporan.php">Hari Ini</a>
    <a class="btn btn-warn" href="laporan.php?export=csv&tanggal=<?= e($tanggal) ?>">&#128190; Export CSV</a>
  </form>
</div>

<div class="flex gap-4 flex-wrap mb-5">
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
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Produk</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Total Terjual</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Total Penjualan</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($perProduk as $p): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['kode']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($p['nama']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= (int)$p['total_qty'] ?> <?= e($p['satuan']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($p['total_penjualan']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Tidak ada penjualan pada tanggal ini.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Daftar Transaksi</h2>
  <?php if ($trxList): ?>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Waktu</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Total</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($trxList as $t): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($t['kode_transaksi']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e(date('H:i', strtotime($t['created_at']))) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($t['total']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><a class="btn btn-sm" href="transaksi.php?action=detail&id=<?= (int)$t['id'] ?>">Detail</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Belum ada transaksi pada tanggal ini.</p>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
