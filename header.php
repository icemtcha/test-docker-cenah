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

$stokMenipisJumlah = 0;
try {
    $stokMenipisJumlah = count(produk_stok_menipis());
} catch (Throwable $ex) {
    $stokMenipisJumlah = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Toko Kelontong &mdash; <?= e($menu[$halaman] ?? 'Aplikasi') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#0f766e',
          dark: '#115e59',
          light: '#14b8a6',
        },
      },
    },
  },
  plugins: [
    function ({ addComponents }) {
      addComponents({
        '.card': {
          backgroundColor: '#fff',
          borderRadius: '0.75rem',
          padding: '1.25rem',
          boxShadow: '0 1px 3px rgba(0,0,0,.08)',
          marginBottom: '1.25rem',
        },
        '.card h2': { fontSize: '1.125rem', fontWeight: '700', marginTop: '0', marginBottom: '0.75rem' },
        '.btn': {
          display: 'inline-flex',
          alignItems: 'center',
          gap: '.35rem',
          padding: '.5rem .875rem',
          borderRadius: '.5rem',
          border: 'none',
          cursor: 'pointer',
          fontSize: '13px',
          textDecoration: 'none',
          color: '#fff',
          backgroundColor: '#0f766e',
          transition: 'background-color .15s ease',
        },
        '.btn:hover': { backgroundColor: '#115e59' },
        '.btn-sm': { padding: '.3rem .625rem', fontSize: '12px' },
        '.btn-danger': { backgroundColor: '#dc2626' },
        '.btn-danger:hover': { backgroundColor: '#b91c1c' },
        '.btn-warn': { backgroundColor: '#d97706' },
        '.btn-warn:hover': { backgroundColor: '#b45309' },
        '.btn-secondary': { backgroundColor: '#64748b' },
        '.btn-secondary:hover': { backgroundColor: '#475569' },
        '.form-group': { marginBottom: '.875rem', flex: '1', minWidth: '180px' },
        '.form-group label': { display: 'block', marginBottom: '.375rem', fontSize: '13px', fontWeight: '600' },
        '.form-group input, .form-group select, .form-group textarea': {
          width: '100%',
          padding: '.55rem .7rem',
          border: '1px solid #cbd5e1',
          borderRadius: '.5rem',
          fontSize: '14px',
          outline: 'none',
        },
        '.form-group input:focus, .form-group select:focus, .form-group textarea:focus': {
          borderColor: '#14b8a6',
          boxShadow: '0 0 0 3px rgba(20,184,166,.25)',
        },
        '.form-row': { display: 'flex', gap: '.875rem', flexWrap: 'wrap' },
        '.alert': { padding: '.75rem 1rem', borderRadius: '.5rem', marginBottom: '1rem', fontSize: '14px' },
        '.alert-success': { backgroundColor: '#dcfce7', color: '#166534' },
        '.alert-danger': { backgroundColor: '#fee2e2', color: '#991b1b' },
        '.alert-warning': { backgroundColor: '#fef3c7', color: '#92400e' },
        '.badge': { display: 'inline-block', padding: '.15rem .55rem', borderRadius: '9999px', fontSize: '12px', fontWeight: '600' },
        '.badge-ok': { backgroundColor: '#dcfce7', color: '#166534' },
        '.badge-low': { backgroundColor: '#fee2e2', color: '#991b1b' },
        '.stat': { flex: '1', minWidth: '180px', backgroundColor: '#fff', borderRadius: '.75rem', padding: '1.125rem', boxShadow: '0 1px 3px rgba(0,0,0,.08)' },
        '.stat .label': { fontSize: '13px', color: '#64748b' },
        '.stat .value': { fontSize: '1.5rem', fontWeight: '700', marginTop: '.375rem', color: '#0f766e' },
        '.actions': { display: 'flex', gap: '.375rem' },
        '.muted': { color: '#64748b', fontSize: '13px' },
      });
    },
  ],
};
</script>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<header class="bg-brand text-white shadow-md">
  <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-3">
    <h1 class="text-lg font-bold flex items-center gap-2 whitespace-nowrap">&#128717; Toko Kelontong</h1>
    <nav class="flex flex-wrap items-center gap-1">
      <?php foreach ($menu as $file => $label): ?>
        <?php $aktif = $halaman === $file; ?>
        <a href="<?= e($file) ?>"
           class="px-3.5 py-2 rounded-md text-sm transition-colors relative <?= $aktif ? 'bg-brand-light text-white font-semibold' : 'text-emerald-100 hover:bg-brand-dark hover:text-white' ?>">
          <?= e($label) ?>
          <?php if ($file === 'stok.php' && $stokMenipisJumlah > 0): ?>
            <span class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold align-middle"><?= $stokMenipisJumlah ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['tipe']) ?>"><?= e($flash['pesan']) ?></div>
<?php endif; ?>
