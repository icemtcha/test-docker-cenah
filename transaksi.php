<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $tanggal = $_POST['tanggal'] ?: date('Y-m-d');
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
<h2>Transaksi Penjualan Baru</h2>
<form method="post" action="transaksi.php" id="formTrx">
  <input type="hidden" name="aksi" value="simpan">
  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label>Tanggal</label>
        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <table id="tabelItem">
      <thead>
        <tr><th>Produk</th><th style="width:110px;">Harga</th><th style="width:90px;">Qty</th><th style="width:130px;">Subtotal</th><th style="width:60px;"></th></tr>
      </thead>
      <tbody id="bodyItem"></tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="text-align:right;font-weight:700;">Total</td>
          <td style="font-weight:700;" id="totalTrx">Rp 0</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <p style="margin-top:12px;">
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

function tambahBaris() {
  const tb = document.getElementById('bodyItem');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><select name="items[][produk_id]" class="sel-produk" onchange="hitung()" required>${opsiProduk()}</select></td>
    <td><input type="text" class="inp-harga" readonly value="0" style="background:#f1f5f9;"></td>
    <td><input type="number" name="items[][qty]" class="inp-qty" min="1" value="1" onchange="hitung()" oninput="hitung()"></td>
    <td class="cell-sub" style="font-weight:600;">Rp 0</td>
    <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove();hitung()">x</button></td>`;
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
<h2>Detail Transaksi</h2>
<div class="card">
  <p><strong>Kode:</strong> <?= e($trx['kode_transaksi']) ?> &nbsp;|&nbsp;
     <strong>Tanggal:</strong> <?= e(date('d-m-Y', strtotime($trx['tanggal']))) ?></p>
  <table>
    <thead>
      <tr><th>Kode</th><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($details as $d): ?>
      <tr>
        <td><?= e($d['kode']) ?></td>
        <td><?= e($d['nama']) ?></td>
        <td><?= rupiah($d['harga']) ?></td>
        <td><?= (int)$d['qty'] ?> <?= e($d['satuan']) ?></td>
        <td><?= rupiah($d['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" style="text-align:right;font-weight:700;">Total</td><td style="font-weight:700;"><?= rupiah($trx['total']) ?></td></tr>
    </tfoot>
  </table>
  <p style="margin-top:14px;">
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
<h2>Riwayat Transaksi</h2>
<div class="card">
  <div class="search-bar">
    <form method="get" action="transaksi.php" class="search-bar" style="margin:0;width:100%;">
      <input type="date" name="tanggal" value="<?= e($filterTanggal) ?>">
      <button class="btn" type="submit">Filter</button>
      <a class="btn btn-secondary" href="transaksi.php">Reset</a>
      <a class="btn btn-warn" href="transaksi.php?action=create">+ Transaksi Baru</a>
    </form>
  </div>
  <?php if ($list): ?>
  <table>
    <thead>
      <tr><th>Kode</th><th>Tanggal</th><th>Jumlah Item</th><th>Total</th><th>Aksi</th></tr>
    </thead>
    <tbody>
      <?php foreach ($list as $t): ?>
      <tr>
        <td><?= e($t['kode_transaksi']) ?></td>
        <td><?= e(date('d-m-Y', strtotime($t['tanggal']))) ?></td>
        <td><?= (int)$t['jumlah_item'] ?></td>
        <td><?= rupiah($t['total']) ?></td>
        <td class="actions">
          <a class="btn btn-sm" href="transaksi.php?action=detail&id=<?= (int)$t['id'] ?>">Detail</a>
          <a class="btn btn-sm btn-danger" href="transaksi.php?action=delete&id=<?= (int)$t['id'] ?>" onclick="return confirm('Batalkan transaksi ini?')">Batalkan</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="muted">Belum ada transaksi.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
