<?php
// File: config.php
// Deskripsi: Konfigurasi pusat untuk koneksi database dan session.

// Mulai session di awal untuk semua halaman
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Configuration ---
$db_host = '103.79.244.233';
$db_name = 'kpabcent_localarea';
$db_user = 'kpabcent_localarea';
$db_pass = 'Rianzshon2023';

// --- DATABASE CONNECTION (PDO) ---
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$db_error = '';
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    $db_error = "Kesalahan Koneksi Database: " . $e->getMessage();
    // Jangan 'die' di sini agar halaman bisa menampilkan pesan error dengan baik
}

/**
 * Fungsi untuk memeriksa status login dan role pengguna.
 * Akan me-redirect ke halaman login jika tidak memenuhi syarat.
 * @param string|null $required_role Role yang dibutuhkan untuk mengakses halaman.
 */
function check_login($required_role = null) {
    // Periksa apakah pengguna sudah login
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header("location: login.php");
        exit;
    }
    // Periksa apakah role pengguna sesuai dengan yang dibutuhkan
    if ($required_role && $_SESSION['role'] !== $required_role) {
        // Jika role tidak sesuai, redirect ke halaman login dengan pesan error
        header("location: login.php?error=access_denied");
        exit;
    }
}

// --- Helper Functions ---

function clean_currency($currency_string) {
    if ($currency_string === null || $currency_string === '') {
        return 0;
    }
    $cleaned = trim(str_replace('Rp', '', $currency_string));
    $cleaned = str_replace(['.', ','], '', $cleaned);
    if (!is_numeric($cleaned)) {
        return 0;
    }
    return (float)$cleaned;
}

function parse_db_date($date_string) {
    if (empty($date_string) || $date_string === '0000-00-00' || $date_string === '0000-00-00 00:00:00') {
        return null;
    }
    $date_string = trim($date_string);
    $date_string = preg_replace('/^\p{L}+,\s*/u', '', $date_string);
    $indonesian_months = [
        'Januari' => 'January', 'Februari' => 'February', 'Maret' => 'March', 'April' => 'April', 'Mei' => 'May', 'Juni' => 'June',
        'Juli' => 'July', 'Agustus' => 'August', 'September' => 'September', 'Oktober' => 'October', 'November' => 'November', 'Desember' => 'December'
    ];
    $date_string_en = str_ireplace(array_keys($indonesian_months), array_values($indonesian_months), $date_string);
    $formats_to_try = [
        'd F Y', 'M-y', 'Y-m-d H:i:s', 'Y-m-d', 'd-m-Y', 'd M Y', 'Y/m/d H:i:s', 'Y/m/d', 'd/m/Y H:i:s', 'd/m/Y', 'F Y', 'M Y', 'Y'
    ];
    foreach ($formats_to_try as $format) {
        $date = DateTime::createFromFormat($format, $date_string_en);
        if ($date !== false && DateTime::getLastErrors()['warning_count'] == 0 && DateTime::getLastErrors()['error_count'] == 0) {
            if (in_array($format, ['M-y', 'F Y', 'M Y', 'Y'])) {
                return $date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1);
            }
            return $date;
        }
    }
    try {
        return new DateTime($date_string_en);
    } catch (Exception $e) {
        error_log("Semua upaya parsing gagal untuk string tanggal: '{$date_string}'");
        return null;
    }
}

?>
