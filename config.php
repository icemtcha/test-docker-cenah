<?php
session_start();

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'kelontong');
define('DB_USER', getenv('DB_USER') ?: 'kelontong');
define('DB_PASS', getenv('DB_PASS') ?: 'kelontong');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('Koneksi database gagal: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function e($str): string
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function ambil_kategori(): array
{
    return db()->query('SELECT * FROM kategori ORDER BY nama')->fetchAll();
}

function ambil_produk(?string $cari = null): array
{
    $sql = 'SELECT p.*, k.nama AS kategori_nama
            FROM produk p
            LEFT JOIN kategori k ON k.id = p.kategori_id';
    if ($cari !== null && $cari !== '') {
        $sql .= ' WHERE p.nama LIKE :cari OR p.kode LIKE :cari';
    }
    $sql .= ' ORDER BY p.nama';
    $stmt = db()->prepare($sql);
    if ($cari !== null && $cari !== '') {
        $stmt->bindValue(':cari', '%' . $cari . '%');
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function produk_stok_menipis(): array
{
    return db()->query('SELECT p.*, k.nama AS kategori_nama
                        FROM produk p
                        LEFT JOIN kategori k ON k.id = p.kategori_id
                        WHERE p.stok <= p.stok_minimum
                        ORDER BY p.stok ASC')->fetchAll();
}

function buat_kode_transaksi(): string
{
    $prefix = 'TRX' . date('Ymd');
    $stmt = db()->prepare('SELECT COUNT(*) FROM transaksi WHERE kode_transaksi LIKE :p');
    $stmt->execute([':p' => $prefix . '%']);
    $n = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
