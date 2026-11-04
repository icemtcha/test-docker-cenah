<?php
if (!function_exists('e')) {
    require_once __DIR__ . '/config.php';
}
$halaman = basename($_SERVER['PHP_SELF']);
$menu = [
    'index.php'     => 'Dashboard',
    'produk.php'    => 'Produk',
    'kategori.php'  => 'Kategori',
    'stok.php'      => 'Stok',
    'transaksi.php' => 'Transaksi',
    'laporan.php'   => 'Laporan',
];
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Toko Kelontong &mdash; <?= e($menu[$halaman] ?? 'Aplikasi') ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background: #f1f5f9; color: #1e293b; }
  header.topbar { background: #0f766e; color: #fff; padding: 14px 24px; display: flex; align-items: center; gap: 24px; flex-wrap: wrap; }
  header.topbar h1 { font-size: 18px; margin: 0; }
  nav a { color: #d1fae5; text-decoration: none; padding: 8px 14px; border-radius: 6px; font-size: 14px; }
  nav a:hover { background: #115e59; color: #fff; }
  nav a.active { background: #14b8a6; color: #06255b; font-weight: 600; }
  .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
  .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 20px; }
  .card h2 { margin-top: 0; font-size: 18px; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
  th { background: #f8fafc; font-weight: 600; }
  tr:hover td { background: #f8fafc; }
  .btn { display: inline-block; padding: 8px 14px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; color: #fff; background: #0f766e; }
  .btn:hover { background: #115e59; }
  .btn-sm { padding: 5px 10px; font-size: 12px; }
  .btn-danger { background: #dc2626; }
  .btn-danger:hover { background: #b91c1c; }
  .btn-warn { background: #d97706; }
  .btn-secondary { background: #64748b; }
  .form-group { margin-bottom: 14px; }
  .form-group label { display: block; margin-bottom: 6px; font-size: 13px; font-weight: 600; }
  .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; }
  .form-row { display: flex; gap: 14px; flex-wrap: wrap; }
  .form-row .form-group { flex: 1; min-width: 180px; }
  .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
  .alert-success { background: #dcfce7; color: #166534; }
  .alert-danger { background: #fee2e2; color: #991b1b; }
  .alert-warning { background: #fef3c7; color: #92400e; }
  .badge { padding: 3px 9px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .badge-ok { background: #dcfce7; color: #166534; }
  .badge-low { background: #fee2e2; color: #991b1b; }
  .stat-grid { display: flex; gap: 16px; flex-wrap: wrap; }
  .stat { flex: 1; min-width: 180px; background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  .stat .label { font-size: 13px; color: #64748b; }
  .stat .value { font-size: 24px; font-weight: 700; margin-top: 6px; color: #0f766e; }
  .actions { display: flex; gap: 6px; }
  .inline-form { display: flex; gap: 8px; align-items: center; }
  .search-bar { display: flex; gap: 8px; margin-bottom: 16px; }
  .search-bar input { flex: 1; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 6px; }
  .muted { color: #64748b; font-size: 13px; }
</style>
</head>
<body>
<header class="topbar">
  <h1>&#128717; Toko Kelontong</h1>
  <nav>
    <?php foreach ($menu as $file => $label): ?>
      <a href="<?= e($file) ?>" class="<?= $halaman === $file ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<div class="wrap">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['tipe']) ?>"><?= e($flash['pesan']) ?></div>
<?php endif; ?>
