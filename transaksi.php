<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $tanggal = ($_POST['tanggal'] ?? '') !== '' ? $_POST['tanggal'] : date('Y-m-d');
    $items   = $_POST['items'] ?? [];

    $rows = [];
    foreach ($items as $row) {
        $produkId = (int)($row['produk_id'] ?? 0);
        $qty      = (int)($row['qty'] ?? 0);
        if ($produkId > 0 && $qty > 0) {
            $rows[$produkId] = ($rows[$produkId] ?? 0) + $qty;
        }
    }

    if (!$rows) {
        set_flash('danger', 'Tambahkan minimal satu produk dengan qty lebih dari 0.');
        redirect('transaksi.php?action=create');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $kode = buat_kode_transaksi();
        $total = 0;
        $detail = [];

        foreach ($rows as $produkId => $qty) {
            $stmt = $pdo->prepare('SELECT id, nama, harga, stok, satuan FROM produk WHERE id = ? FOR UPDATE');
            $stmt->execute([$produkId]);
            $p = $stmt->fetch();
            if (!$p) {
                throw new RuntimeException("Produk tidak ditemukan (#$produkId).");
            }
            if ((int)$p['stok'] < $qty) {
                throw new RuntimeException("Stok {$p['nama']} tidak cukup. Tersedia: {$p['stok']}, diminta: $qty.");
            }
            $subtotal = (float)$p['harga'] * $qty;
            $total += $subtotal;
            $detail[] = ['produk_id' => $produkId, 'nama' => $p['nama'], 'harga' => (float)$p['harga'], 'qty' => $qty, 'subtotal' => $subtotal];
        }

        $ins = $pdo->prepare('INSERT INTO transaksi (kode_transaksi, total, tanggal) VALUES (?,?,?)');
        $ins->execute([$kode, $total, $tanggal]);
        $trxId = (int)$pdo->lastInsertId();

        $insDetail = $pdo->prepare('INSERT INTO transaksi_detail (transaksi_id, produk_id, harga, qty, subtotal) VALUES (?,?,?,?,?)');
        $updStok = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id = ?');
        $insMutasi = $pdo->prepare('INSERT INTO stok_mutasi (produk_id, tipe, jumlah, keterangan, tanggal) VALUES (?,?,?,?,?)');

        foreach ($detail as $d) {
            $insDetail->execute([$trxId, $d['produk_id'], $d['harga'], $d['qty'], $d['subtotal']]);
            $updStok->execute([$d['qty'], $d['produk_id']]);
            $insMutasi->execute([$d['produk_id'], 'keluar', $d['qty'], 'Penjualan ' . $kode, $tanggal]);
        }

        $pdo->commit();
        set_flash('success', "Transaksi $kode berhasil disimpan. Total " . rupiah($total) . '.');
        redirect('transaksi.php?action=detail&id=' . $trxId);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Transaksi gagal: ' . $ex->getMessage());
        redirect('transaksi.php?action=create');
    }
}

if ($action === 'delete' && $id > 0) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT d.produk_id, d.qty, t.kode_transaksi FROM transaksi_detail d JOIN transaksi t ON t.id = d.transaksi_id WHERE d.transaksi_id = ?');
        $stmt->execute([$id]);
        $details = $stmt->fetchAll();

        $updStok = $pdo->prepare('UPDATE produk SET stok = stok + ? WHERE id = ?');
        foreach ($details as $d) {
            $updStok->execute([(int)$d['qty'], (int)$d['produk_id']]);
        }

        $pdo->prepare('DELETE FROM transaksi WHERE id = ?')->execute([$id]);
        if ($details) {
            $kode = $details[0]['kode_transaksi'];
            $pdo->prepare("DELETE FROM stok_mutasi WHERE keterangan = ?")->execute(['Penjualan ' . $kode]);
        }
        $pdo->commit();
        set_flash('success', 'Transaksi dibatalkan dan stok dikembalikan.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Gagal menghapus transaksi: ' . $ex->getMessage());
    }
    redirect('transaksi.php');
}

$produkList = ambil_produk();

require_once __DIR__ . '/header.php';
?>

<?php if ($action === 'create'): ?>
<h2 class="text-xl font-bold mb-4">Transaksi Penjualan Baru</h2>
<form method="post" action="transaksi.php" id="formTrx">
  <input type="hidden" name="aksi" value="simpan">
  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label>Tanggal</label>
        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <div class="overflow-x-auto">
    <table id="tabelItem" class="w-full border-collapse text-sm">
      <thead>
        <tr class="bg-slate-50 text-slate-600 text-left">
          <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Produk</th>
          <th class="px-3 py-2.5 border-b border-slate-200 font-semibold" style="width:110px;">Harga</th>
          <th class="px-3 py-2.5 border-b border-slate-200 font-semibold" style="width:90px;">Qty</th>
          <th class="px-3 py-2.5 border-b border-slate-200 font-semibold" style="width:130px;">Subtotal</th>
          <th class="px-3 py-2.5 border-b border-slate-200 font-semibold" style="width:60px;"></th>
        </tr>
      </thead>
      <tbody id="bodyItem"></tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="px-3 py-2.5 text-right font-bold">Total</td>
          <td class="px-3 py-2.5 font-bold text-brand" id="totalTrx">Rp 0</td>
          <td class="px-3 py-2.5"></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <p class="mt-3 flex flex-wrap gap-2">
      <button type="button" class="btn btn-secondary" onclick="tambahBaris()">+ Tambah Baris</button>
      <button type="submit" class="btn btn-warn">Simpan Transaksi</button>
      <a class="btn btn-secondary" href="transaksi.php">Batal</a>
    </p>
  </div>
</form>

<script>
const daftarProduk = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'],
    'label' => $p['kode'] . ' - ' . $p['nama'],
    'harga' => (float)$p['harga'],
    'stok' => (int)$p['stok'],
], $produkList), JSON_UNESCAPED_UNICODE) ?>;

function opsiProduk() {
  let html = '<option value="">- Pilih -</option>';
  daftarProduk.forEach(p => {
    html += `<option value="${p.id}" data-harga="${p.harga}" data-stok="${p.stok}">${p.label} (stok: ${p.stok})</option>`;
  });
  return html;
}

let idxBaris = 0;

function tambahBaris() {
  const i = idxBaris++;
  const tb = document.getElementById('bodyItem');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="px-3 py-2.5 border-b border-slate-100"><select name="items[${i}][produk_id]" class="sel-produk w-full px-2 py-1.5 border border-slate-300 rounded-md text-sm" onchange="hitung()" required>${opsiProduk()}</select></td>
    <td class="px-3 py-2.5 border-b border-slate-100"><input type="text" class="inp-harga w-full px-2 py-1.5 border border-slate-300 rounded-md text-sm bg-slate-100" readonly value="0"></td>
    <td class="px-3 py-2.5 border-b border-slate-100"><input type="number" name="items[${i}][qty]" class="inp-qty w-full px-2 py-1.5 border border-slate-300 rounded-md text-sm" min="1" value="1" onchange="hitung()" oninput="hitung()"></td>
    <td class="cell-sub px-3 py-2.5 border-b border-slate-100 font-semibold">Rp 0</td>
    <td class="px-3 py-2.5 border-b border-slate-100"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();hitung()">x</button></td>`;
  tb.appendChild(tr);
}

function formatRp(n) {
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function hitung() {
  let total = 0;
  document.querySelectorAll('#bodyItem tr').forEach(tr => {
    const sel = tr.querySelector('.sel-produk');
    const opt = sel.options[sel.selectedIndex];
    const harga = opt && opt.dataset.harga ? parseFloat(opt.dataset.harga) : 0;
    const qty = parseInt(tr.querySelector('.inp-qty').value) || 0;
    tr.querySelector('.inp-harga').value = harga;
    const sub = harga * qty;
    tr.querySelector('.cell-sub').textContent = formatRp(sub);
    total += sub;
  });
  document.getElementById('totalTrx').textContent = formatRp(total);
}

tambahBaris();
</script>

<?php elseif ($action === 'detail' && $id > 0): ?>
<?php
$stmt = db()->prepare('SELECT * FROM transaksi WHERE id = ?');
$stmt->execute([$id]);
$trx = $stmt->fetch();
if (!$trx) {
    set_flash('danger', 'Transaksi tidak ditemukan.');
    redirect('transaksi.php');
}
$stmt = db()->prepare('SELECT d.*, p.nama, p.kode, p.satuan FROM transaksi_detail d JOIN produk p ON p.id = d.produk_id WHERE d.transaksi_id = ?');
$stmt->execute([$id]);
$details = $stmt->fetchAll();
?>
<h2 class="text-xl font-bold mb-4">Detail Transaksi</h2>
<div class="card">
  <p class="mb-4"><strong>Kode:</strong> <?= e($trx['kode_transaksi']) ?> &nbsp;|&nbsp;
     <strong>Tanggal:</strong> <?= e(date('d-m-Y', strtotime($trx['tanggal']))) ?></p>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Produk</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Harga</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Qty</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($details as $d): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($d['kode']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($d['nama']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($d['harga']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= (int)$d['qty'] ?> <?= e($d['satuan']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($d['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" class="px-3 py-2.5 text-right font-bold">Total</td><td class="px-3 py-2.5 font-bold text-brand"><?= rupiah($trx['total']) ?></td></tr>
    </tfoot>
  </table>
  </div>
  <p class="mt-4 flex flex-wrap gap-2">
    <a class="btn btn-secondary" href="transaksi.php">Kembali</a>
    <a class="btn btn-danger" href="transaksi.php?action=delete&id=<?= (int)$trx['id'] ?>" onclick="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">Batalkan Transaksi</a>
  </p>
</div>

<?php else: ?>
<?php
$filterTanggal = $_GET['tanggal'] ?? '';
$sql = 'SELECT t.*, (SELECT COUNT(*) FROM transaksi_detail d WHERE d.transaksi_id = t.id) AS jumlah_item FROM transaksi t';
$params = [];
if ($filterTanggal !== '') {
    $sql .= ' WHERE t.tanggal = :tgl';
    $params[':tgl'] = $filterTanggal;
}
$sql .= ' ORDER BY t.created_at DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();
?>
<h2 class="text-xl font-bold mb-4">Riwayat Transaksi</h2>
<div class="card">
  <form method="get" action="transaksi.php" class="flex flex-wrap gap-2 items-center mb-4">
    <input type="date" name="tanggal" value="<?= e($filterTanggal) ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-200">
    <button class="btn" type="submit">Filter</button>
    <a class="btn btn-secondary" href="transaksi.php">Reset</a>
    <a class="btn btn-warn" href="transaksi.php?action=create">+ Transaksi Baru</a>
  </form>
  <?php if ($list): ?>
  <div class="overflow-x-auto">
  <table class="w-full border-collapse text-sm">
    <thead>
      <tr class="bg-slate-50 text-slate-600 text-left">
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Kode</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Tanggal</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Jumlah Item</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Total</th>
        <th class="px-3 py-2.5 border-b border-slate-200 font-semibold">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($list as $t): ?>
      <tr class="hover:bg-slate-50">
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e($t['kode_transaksi']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= e(date('d-m-Y', strtotime($t['tanggal']))) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= (int)$t['jumlah_item'] ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100"><?= rupiah($t['total']) ?></td>
        <td class="px-3 py-2.5 border-b border-slate-100 actions">
          <a class="btn btn-sm" href="transaksi.php?action=detail&id=<?= (int)$t['id'] ?>">Detail</a>
          <a class="btn btn-sm btn-danger" href="transaksi.php?action=delete&id=<?= (int)$t['id'] ?>" onclick="return confirm('Batalkan transaksi ini?')">Batalkan</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="muted">Belum ada transaksi.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
