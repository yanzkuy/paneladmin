<?php
// Set header to return JSON
header('Content-Type: application/json');

// --- Helper Functions ---
function clean_currency($currency_string) {
    if ($currency_string === null || $currency_string === '') return 0;
    $cleaned = trim(str_replace('Rp', '', $currency_string));
    $cleaned = str_replace(['.', ','], '', $cleaned);
    return is_numeric($cleaned) ? (float)$cleaned : 0;
}

// --- Response Array ---
$response = [
    'error' => null,
    'employee' => null,
    'electronic_debts' => [],
    'sembako_debts' => [],
    'total_electronic_debt' => 0,
    'total_sembako_debt' => 0,
    'total_overall_debt' => 0,
];

// --- NIK Check ---
if (!isset($_GET['nik']) || empty($_GET['nik'])) {
    $response['error'] = 'NIK tidak disediakan.';
    echo json_encode($response);
    exit;
}

$nik_search = trim($_GET['nik']);

// --- Database Configuration ---
$db_host = '103.79.244.233';
$db_name = 'kpabcent_localarea';
$db_user = 'kpabcent_localarea';
$db_pass = 'Rianzshon2023';

// --- Database Connection ---
$pdo = null;
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    $response['error'] = "Kesalahan Koneksi Database: " . $e->getMessage();
    echo json_encode($response);
    exit;
}

// --- Fetch Data ---
try {
    // 1. Get Employee Data
    $stmt = $pdo->prepare("SELECT NIK, Nama_lengkap AS NAMA, Departemen, status FROM db_anggotakpab WHERE NIK = ?");
    $stmt->execute([$nik_search]);
    $employee = $stmt->fetch();

    if (!$employee) {
        $response['error'] = 'Anggota tidak ditemukan.';
        echo json_encode($response);
        exit;
    }
    $response['employee'] = $employee;

    // 2. Get Electronic Debts
    $stmt_elec = $pdo->prepare("SELECT JENIS_BARANG, TOTAL_HUTANG, TENOR, SISA_BULAN, ANGSURAN_PERBULAN FROM db_hutangelectronik WHERE NIK = ?");
    $stmt_elec->execute([$nik_search]);
    foreach ($stmt_elec->fetchAll() as $debt) {
        $angsuran = clean_currency($debt['ANGSURAN_PERBULAN']);
        $sisa_bulan = (int)$debt['SISA_BULAN'];
        if ($sisa_bulan > 0 && $angsuran > 0) {
            $total_sisa = $angsuran * $sisa_bulan;
            $response['total_electronic_debt'] += $total_sisa;
            $response['electronic_debts'][] = [
                'nama_barang' => $debt['JENIS_BARANG'],
                'tenor' => $debt['TENOR'],
                'sisa_angsuran' => $sisa_bulan,
                'angsuran_perbulan' => $angsuran,
                'total_sisa_hutang_per_item' => $total_sisa
            ];
        }
    }

    // 3. Get Sembako Debts
    $stmt_sem = $pdo->prepare("SELECT NAMA_BARANG, QTY, satuan, JUMLAH FROM db_hutangsembako WHERE NIK = ?");
    $stmt_sem->execute([$nik_search]);
    foreach ($stmt_sem->fetchAll() as $item) {
        $jumlah = clean_currency($item['JUMLAH']);
        if ($jumlah > 0) {
            $response['total_sembako_debt'] += $jumlah;
            $response['sembako_debts'][] = [
                'nama_barang' => $item['NAMA_BARANG'],
                'qty' => $item['QTY'],
                'satuan' => $item['satuan'],
                'jumlah' => $jumlah
            ];
        }
    }

    // 4. Calculate Total
    $response['total_overall_debt'] = $response['total_electronic_debt'] + $response['total_sembako_debt'];

} catch (\PDOException $e) {
    $response['error'] = "Kesalahan Pengambilan Data: " . $e->getMessage();
}

// --- Return Final JSON Response ---
echo json_encode($response);
?>
