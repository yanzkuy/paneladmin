<?php
// File: dashboard_personalia.php
// Deskripsi: Panel khusus untuk Personalia dengan tampilan detail anggota yang lengkap.
// Modifikasi: Merombak total layout dan UI/UX dengan skema warna baru.

// Memuat konfigurasi dan memulai session
require_once 'config.php';

// Memeriksa apakah pengguna sudah login dan memiliki role 'personalia'
check_login('personalia');

// Jika koneksi database gagal, tampilkan error dan hentikan eksekusi
if (!$pdo) {
    die($db_error);
}

// [BARU] Fungsi untuk mencatat riwayat aktivitas
function log_activity($pdo, $username, $action_type, $description) {
    try {
        // Mengambil user_id berdasarkan username dari session
        $stmt_user = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_user->execute([$username]);
        $user = $stmt_user->fetch();
        $user_id = $user ? $user['id'] : 0; // Default ke 0 jika tidak ditemukan

        $stmt = $pdo->prepare("INSERT INTO riwayat_aktivitas2 (user_id, username, action_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $action_type, $description]);
    } catch (PDOException $e) {
        // Abaikan error agar tidak menghentikan proses utama
        error_log("Gagal mencatat aktivitas: " . $e->getMessage());
    }
}


// --- Inisialisasi Variabel ---
$employee = null;
$electronic_debts = [];
$sembako_debts = [];
$riwayat_selisih = [];
$error_message = '';
$success_message = '';
$nik_search = '';
$join_date_obj = null;
$total_electronic_debt = 0;
$total_sembako_debt_overall = 0;
$total_akumulasi_selisih = 0;
$total_overall_debt = 0;
$mandatory_savings = 0;
$principal_saving = 50000;
$total_savings = 0;
$total_angsuran_elektronik = 0;
$potongan_wajib_preview = 25000;
$limit_potongan = 950000;
$total_estimasi_potongan = 0;
$potongan_ditampilkan = 0;
$kelebihan_potongan = 0;
$total_sembako_debt_this_period = 0;
$potongan_pokok = 0;
$semua_riwayat_potongan = [];

// [BARU] Variabel untuk kontainer baru
$riwayat_aktivitas_sistem = [];
$riwayat_update_anggota = [];
$jumlah_anggota_aktif = 0;
$jumlah_anggota_baru = 0;


// --- [BARU] Logika untuk mengambil data kontainer baru ---
try {
    // Kontainer 1: Riwayat Aktivitas Sistem
    $stmt_aktivitas = $pdo->query("SELECT username, description, timestamp FROM riwayat_aktivitas2 ORDER BY timestamp DESC LIMIT 5");
    $riwayat_aktivitas_sistem = $stmt_aktivitas->fetchAll(PDO::FETCH_ASSOC);

    // Kontainer 2: Riwayat Update Anggota
    $stmt_update = $pdo->query("SELECT username, description, timestamp FROM riwayat_aktivitas2 WHERE action_type = 'UPDATE' ORDER BY timestamp DESC LIMIT 5");
    $riwayat_update_anggota = $stmt_update->fetchAll(PDO::FETCH_ASSOC);

    // Kontainer 3: Statistik
    $jumlah_anggota_aktif = $pdo->query("SELECT COUNT(*) FROM db_anggotakpab WHERE status = 'AKTIF'")->fetchColumn();
    $tiga_puluh_hari_lalu = date('Y-m-d', strtotime('-30 days'));
    $jumlah_anggota_baru = $pdo->query("SELECT COUNT(*) FROM db_anggotakpab WHERE date_join >= '$tiga_puluh_hari_lalu'")->fetchColumn();

} catch (\PDOException $e) {
    $error_message = "Gagal mengambil data dasbor: " . $e->getMessage();
}


// --- Logika Penanganan Aksi (Update Status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (isset($_POST['nik']) && !empty($_POST['nik'])) {
        $nik_to_update = $_POST['nik'];
        $new_status = $_POST['status'];
        
        try {
            $pdo->beginTransaction();

            $stmt_emp = $pdo->prepare("SELECT Nama_lengkap FROM db_anggotakpab WHERE NIK = ?");
            $stmt_emp->execute([$nik_to_update]);
            $notif_employee = $stmt_emp->fetch();
            $nama_lengkap_log = $notif_employee['Nama_lengkap'] ?? 'N/A';

            if ($new_status === 'NON-AKTIF') {
                $message = sprintf(
                    "Anggota %s (NIK: %s) telah dinonaktifkan oleh Personalia.",
                    htmlspecialchars($nama_lengkap_log),
                    htmlspecialchars($nik_to_update)
                );
                log_activity($pdo, $_SESSION['username'], 'UPDATE', "Menonaktifkan anggota: {$nama_lengkap_log} (NIK: {$nik_to_update})");
            } else {
                $message = sprintf(
                    "Anggota %s (NIK: %s) telah diaktifkan kembali oleh Personalia.",
                    htmlspecialchars($nama_lengkap_log),
                    htmlspecialchars($nik_to_update)
                );
                log_activity($pdo, $_SESSION['username'], 'UPDATE', "Mengaktifkan kembali anggota: {$nama_lengkap_log} (NIK: {$nik_to_update})");
            }

            $stmt_update = $pdo->prepare("UPDATE db_anggotakpab SET status = ? WHERE NIK = ?");
            $stmt_update->execute([$new_status, $nik_to_update]);

            $stmt_notif = $pdo->prepare("INSERT INTO notifikasi (pesan, tanggal, dibaca) VALUES (?, NOW(), 0)");
            $stmt_notif->execute([$message]);
            
            $pdo->commit();
            $success_message = "Status karyawan NIK: {$nik_to_update} berhasil diperbarui.";
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $error_message = "Gagal memperbarui status: " . $e->getMessage();
        }
    }
}

// --- Logika Pencarian Karyawan (Diadaptasi dari index.php) ---
if (isset($_GET['nik']) && !empty($_GET['nik'])) {
    $nik_search = trim($_GET['nik']);
    try {
        $stmt = $pdo->prepare("SELECT NIK, no_kpab, Nama_lengkap AS NAMA, Departemen AS DEPARTEMEN, cost_center, Alamat_Rumah AS ALAMAT, No_ponsel AS NO_PONSEL, date_join AS TGL_MASUK, SIMPANAN_wajib, SIMPANAN_pokok, status FROM db_anggotakpab WHERE NIK = ?");
        $stmt->execute([$nik_search]);
        $employee = $stmt->fetch();

        if ($employee) {
            // [BARU] Mencatat aktivitas pencarian
            log_activity($pdo, $_SESSION['username'], 'SEARCH', "Mencari anggota: " . htmlspecialchars($employee['NAMA']) . " (NIK: " . htmlspecialchars($nik_search) . ")");

            $join_date_obj = parse_db_date($employee['TGL_MASUK']);
            $principal_saving = clean_currency($employee['SIMPANAN_pokok'] ?? 50000);

            $current_period_start = date('Y-m-01');
            $current_period_end = date('Y-m-t');

            // [MODIFIKASI] Menambahkan kolom 'total_hutang' ke query
            $stmt_elec = $pdo->prepare("SELECT JENIS_BARANG, total_hutang, SISA_BULAN, ANGSURAN_PERBULAN FROM db_hutangelectronik WHERE NIK = ? AND SISA_BULAN > 0");
            $stmt_elec->execute([$nik_search]);
            foreach ($stmt_elec->fetchAll() as $debt) {
                $angsuran_perbulan = clean_currency($debt['ANGSURAN_PERBULAN']);
                $sisa_bulan = (int)$debt['SISA_BULAN'];
                $harga_barang = clean_currency($debt['total_hutang']); // [BARU] Mengambil data harga
                $total_sisa_hutang_per_item = $angsuran_perbulan * $sisa_bulan;
                $total_electronic_debt += $total_sisa_hutang_per_item;
                
                // [MODIFIKASI] Menambahkan 'harga_barang' ke array
                $electronic_debts[] = [
                    'nama_barang' => $debt['JENIS_BARANG'],
                    'harga_barang' => $harga_barang,
                    'sisa_angsuran' => $sisa_bulan,
                    'angsuran_perbulan' => $angsuran_perbulan,
                    'total_sisa_hutang_per_item' => $total_sisa_hutang_per_item
                ];
                $total_angsuran_elektronik += $angsuran_perbulan;
            }
            
            $stmt_sem_all = $pdo->prepare("SELECT nama_barang, jumlah FROM db_hutangsembako WHERE NIK = ? AND nama_barang != 'HITUNGAN POKOK'");
            $stmt_sem_all->execute([$nik_search]);
            $sembako_debts = []; 
            foreach($stmt_sem_all->fetchAll(PDO::FETCH_ASSOC) as $item) {
                 if(!empty(trim($item['jumlah']))) {
                    $jumlah_cleaned = clean_currency($item['jumlah']);
                    $total_sembako_debt_overall += $jumlah_cleaned;
                    $sembako_debts[] = ['nama_barang' => $item['nama_barang'], 'jumlah' => $jumlah_cleaned];
                 }
            }
            
            $stmt_sem_period = $pdo->prepare("SELECT SUM(REPLACE(jumlah, ',', '')) as total FROM db_hutangsembako WHERE NIK = ? AND nama_barang != 'HITUNGAN POKOK' AND tanggal_ambil_barang BETWEEN ? AND ?");
            $stmt_sem_period->execute([$nik_search, $current_period_start, $current_period_end]);
            $total_sembako_debt_this_period = (float)$stmt_sem_period->fetchColumn();

            $stmt_selisih = $pdo->prepare("SELECT periode_bulan, jumlah_selisih FROM riwayat_selisih WHERE nik = ? AND status = 'terakumulasi' ORDER BY periode_bulan ASC");
            $stmt_selisih->execute([$nik_search]);
            $riwayat_selisih = $stmt_selisih->fetchAll();
            foreach($riwayat_selisih as $selisih) {
                $total_akumulasi_selisih += $selisih['jumlah_selisih'];
            }
            
            $potongan_pokok = 0;
            if ($join_date_obj && $join_date_obj->format('Y-m') === date('Y-m')) {
                $stmt_pokok = $pdo->prepare("SELECT jumlah FROM db_hutangsembako WHERE NIK = ? AND nama_barang = 'HITUNGAN POKOK'");
                $stmt_pokok->execute([$nik_search]);
                $potongan_pokok = $stmt_pokok->fetchColumn() ?: 0;
            }

            $total_overall_debt = $total_electronic_debt + $total_sembako_debt_overall;
            
            if ($join_date_obj && isset($employee['SIMPANAN_wajib'])) {
                $interval = $join_date_obj->diff(new DateTime());
                $months_joined = ($interval->y * 12) + $interval->m + 1;
                $monthly_saving_amount = clean_currency($employee['SIMPANAN_wajib']);
                $mandatory_savings = $months_joined * $monthly_saving_amount;
            }
            $total_savings = $mandatory_savings + $principal_saving;

            $total_estimasi_potongan = $total_angsuran_elektronik + $total_sembako_debt_this_period + $total_akumulasi_selisih + $potongan_wajib_preview + $potongan_pokok;
            $potongan_ditampilkan = min($total_estimasi_potongan, $limit_potongan);
            $kelebihan_potongan = $total_estimasi_potongan - $potongan_ditampilkan;
            
            $stmt_semua_riwayat = $pdo->prepare("SELECT * FROM laporan_potongan_gaji WHERE nik = ? ORDER BY periode_bulan DESC");
            $stmt_semua_riwayat->execute([$employee['NIK']]);
            $semua_riwayat_potongan = $stmt_semua_riwayat->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $error_message = "Karyawan dengan NIK '{$nik_search}' tidak ditemukan.";
            // [BARU] Mencatat aktivitas pencarian gagal
            log_activity($pdo, $_SESSION['username'], 'SEARCH_FAIL', "Pencarian anggota dengan NIK '{$nik_search}' tidak ditemukan.");
            $employee = null;
        }
    } catch (\PDOException $e) {
        $error_message = "Kesalahan Pengambilan Data: " . $e->getMessage();
    }
}

// Logika untuk label periode potongan dinamis (diambil dari index.php)
$periode_potongan_label = date('F Y', strtotime('first day of next month')); // Default
if (isset($employee['NIK'])) {
    $today = new DateTime();
    $cutoff_day = 6;
    if ((int)$today->format('j') < $cutoff_day) {
        $period_date = new DateTime('first day of last month');
    } else {
        $period_date = new DateTime('first day of this month');
    }
    $is_processed = false;
    try {
        $check_processed_stmt = $pdo->prepare("SELECT COUNT(*) FROM laporan_potongan_gaji WHERE nik = ? AND periode_bulan = ? AND status = 'processed'");
        $check_processed_stmt->execute([$employee['NIK'], $period_date->format('Y-m')]);
        if ($check_processed_stmt->fetchColumn() > 0) {
            $is_processed = true;
        }
    } catch (\PDOException $e) {
        error_log("Could not check processed status: " . $e->getMessage());
    }
    if ($is_processed) {
        $period_date->modify('+1 month');
    }
    $periode_potongan_label = $period_date->format('F Y');
    $indonesian_months = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $periode_potongan_label = str_replace(array_keys($indonesian_months), array_values($indonesian_months), $periode_potongan_label);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Personalia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-green: #10B981;
            --primary-green-dark: #059669;
            --light-green: #ECFDF5; /* Hijau Muda */
            --accent-orange: #F97316;
            --accent-orange-dark: #EA580C;
            --light: #F8FAFC; 
            --dark: #1E293B;
            --border-color: #E2E8F0;
        }
        body { font-family: 'Inter', sans-serif; background-color: white; color: var(--dark); }
        .card { 
            background: white;
            border-radius: 1rem; 
            box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .card-header-light-green {
            background-color: var(--light-green);
            color: var(--primary-green-dark);
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #BBF7D0;
        }
        .btn { 
            padding: 10px 20px; 
            border-radius: 0.75rem; 
            font-weight: 600; 
            transition: all 0.2s ease-in-out; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.5rem; 
            border: none;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        .btn-primary { background-color: var(--primary-green); color: white; }
        .btn-primary:hover { background-color: var(--primary-green-dark); }
        .btn-danger { background-color: #EF4444; color: white; }
        .btn-danger:hover { background-color: #DC2626; }
        .btn-success { background-color: var(--primary-green); color: white; }
        .btn-success:hover { background-color: var(--primary-green-dark); }
        .btn-secondary { background-color: white; color: var(--dark); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background-color: #F8FAFC; }
        
        .input-field { 
            border: 1px solid var(--border-color); 
            border-radius: 0.75rem; 
            padding: 0.75rem 1rem; 
            width: 100%; 
            background-color: white;
            transition: all 0.2s ease-in-out;
        }
        .input-field:focus { 
            border-color: var(--primary-green); 
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2); 
            outline: none; 
        }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .status-active { background-color: #D1FAE5; color: #065F46; }
        .status-inactive { background-color: #FEE2E2; color: #991B1B; }
        .status-pending { background-color: #FEF3C7; color: #92400E; }
        .status-processed { background-color: #D1FAE5; color: #065F46; }

        .modal-overlay { position: fixed; inset: 0; background-color: rgba(30, 41, 59, 0.6); display: flex; align-items: center; justify-content: center; z-index: 50; opacity: 0; visibility: hidden; transition: opacity 0.3s; backdrop-filter: blur(4px); }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background: white; padding: 0; border-radius: 1rem; max-width: 500px; width: 95%; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1); overflow: hidden; }
        .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem 1.5rem; background-color: #F8FAFC; border-top: 1px solid var(--border-color); }

        .excel-table { border-collapse: collapse; width: 100%; font-size: 0.875rem; }
        .excel-table th, .excel-table td { border: 1px solid #D1FAE5; padding: 8px 12px; text-align: center; } /* Data di tengah */
        .excel-table thead th { background-color: #065F46; color: white; font-weight: 600; }
        .excel-table tbody tr:nth-child(even) { background-color: #F0FDF4; }
        .excel-table tbody tr:hover { background-color: #BBF7D0; }
        .excel-table .currency { text-align: right; }
        .excel-table .text-left { text-align: left; }

        .logo-container { position: relative; width: 40px; height: 40px; }
        .logo-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            transition: opacity 0.5s ease-in-out;
        }
    </style>
</head>
<body class="p-4 sm:p-6 md:p-8">
    <div class="max-w-screen-2xl mx-auto">
        <header class="mb-8 flex flex-wrap justify-between items-center gap-4 bg-emerald-500 p-4 rounded-2xl shadow-lg">
            <div class="flex items-center gap-4">
                <div class="logo-container">
                    <img id="logo1" src="picture/logo.png" alt="Logo KPAB" style="opacity: 1;">
                    <img id="logo2" src="picture/pratama.png" alt="Logo Pratama" style="opacity: 0;">
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white">Panel Personalia</h1>
                    <p class="text-emerald-100 mt-1">Sistem Manajemen Status Keanggotaan Karyawan</p>
                </div>
            </div>
            <div class="flex items-center gap-3 bg-white/90 backdrop-blur-sm p-3 rounded-full shadow-sm border border-slate-200">
                 <div class="text-right px-2">
                    <div class="text-sm text-slate-500">Login sebagai</div>
                    <div class="font-bold text-slate-800"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
                <a href="ganti_password.php" class="btn btn-secondary !p-3 !rounded-full" title="Ganti Password"><i class="fas fa-key"></i></a>
                <a href="logout.php" class="btn btn-danger !p-3 !rounded-full" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <!-- Kolom Kiri -->
            <div class="xl:col-span-1 space-y-8">
                <!-- Kontainer Statistik -->
                <div class="card">
                    <div class="card-header-light-green">
                        <h3 class="text-lg font-semibold">Statistik Anggota</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-users fa-lg text-emerald-600"></i>
                            </div>
                            <div>
                                <p class="text-3xl font-bold text-slate-800"><?= $jumlah_anggota_aktif ?></p>
                                <p class="text-sm text-slate-500">Anggota Aktif</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center">
                                <i class="fas fa-user-plus fa-lg text-orange-600"></i>
                            </div>
                            <div>
                                <p class="text-3xl font-bold text-slate-800"><?= $jumlah_anggota_baru ?></p>
                                <p class="text-sm text-slate-500">Anggota Baru (30 hari)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kontainer Riwayat Aktivitas Sistem -->
                <div class="card">
                    <div class="card-header-light-green flex items-center gap-2">
                        <i class="fas fa-history"></i>
                        <h3 class="text-lg font-semibold">Riwayat Aktivitas Sistem</h3>
                    </div>
                    <div class="p-5 space-y-4 max-h-60 overflow-y-auto">
                        <?php if (empty($riwayat_aktivitas_sistem)): ?>
                            <p class="text-center text-slate-500 py-4">Belum ada aktivitas.</p>
                        <?php else: ?>
                            <?php foreach ($riwayat_aktivitas_sistem as $aktivitas): ?>
                            <div class="text-sm flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-user-cog text-slate-500"></i></div>
                                <div>
                                    <p class="text-slate-700"><?= htmlspecialchars($aktivitas['description']) ?></p>
                                    <p class="text-xs text-slate-400">Oleh: <strong><?= htmlspecialchars($aktivitas['username']) ?></strong> - <?= date('d M Y, H:i', strtotime($aktivitas['timestamp'])) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Kontainer Riwayat Update Anggota -->
                <div class="card">
                    <div class="card-header-light-green flex items-center gap-2">
                        <i class="fas fa-user-edit"></i>
                        <h3 class="text-lg font-semibold">Riwayat Update Data</h3>
                    </div>
                    <div class="p-5 space-y-4 max-h-60 overflow-y-auto">
                        <?php if (empty($riwayat_update_anggota)): ?>
                            <p class="text-center text-slate-500 py-4">Belum ada pembaruan data.</p>
                        <?php else: ?>
                            <?php foreach ($riwayat_update_anggota as $aktivitas): ?>
                            <div class="text-sm flex items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0"><i class="fas fa-check text-slate-500"></i></div>
                                <div>
                                    <p class="text-slate-700"><?= htmlspecialchars($aktivitas['description']) ?></p>
                                    <p class="text-xs text-slate-400">Oleh: <strong><?= htmlspecialchars($aktivitas['username']) ?></strong> - <?= date('d M Y, H:i', strtotime($aktivitas['timestamp'])) ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan -->
            <div class="xl:col-span-2 space-y-8">
                <div class="card p-6">
                    <form method="GET" action="dashboard_personalia.php" class="space-y-4">
                        <div>
                            <label for="nik" class="block text-lg font-semibold text-slate-700 mb-2">Cari Karyawan</label>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <input type="text" id="nik" name="nik" class="input-field text-base flex-grow" placeholder="Masukkan NIK Karyawan..." value="<?= htmlspecialchars($nik_search) ?>" required>
                                <button type="submit" class="btn btn-primary w-full sm:w-auto"><i class="fas fa-search"></i> Cari Data</button>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg" role="alert"><p><?= htmlspecialchars($error_message) ?></p></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg" role="alert"><p><?= htmlspecialchars($success_message) ?></p></div>
                <?php endif; ?>

                <?php if ($employee): ?>
                    <div id="employee-data-section" class="space-y-8">
                        <div class="card">
                            <div class="card-header-light-green">
                                <div class="flex flex-wrap justify-between items-center gap-4">
                                    <div>
                                        <h2 class="text-2xl font-bold text-emerald-800"><?= htmlspecialchars($employee['NAMA'] ?? 'N/A') ?></h2>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="status-badge <?= $employee['status'] === 'AKTIF' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($employee['status']) ?></span>
                                        <?php if ($employee['status'] === 'AKTIF'): ?>
                                            <button onclick="openModal('removeMemberModal')" class="btn btn-danger"><i class="fas fa-user-times mr-2"></i>Keluarkan</button>
                                        <?php else: ?>
                                            <button onclick="openModal('activateMemberModal')" class="btn btn-success"><i class="fas fa-user-check mr-2"></i>Aktifkan</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="p-6 space-y-8">
                                <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                                    <h3 class="font-semibold text-slate-800 mb-4">Informasi Pribadi & Keanggotaan</h3>
                                    <dl class="divide-y divide-slate-100">
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">NIK</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['NIK'] ?? 'N/A') ?></dd></div>
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">No. KPAB</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['no_kpab'] ?? 'N/A') ?></dd></div>
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">Departemen</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['DEPARTEMEN'] ?? 'N/A') ?></dd></div>
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">Cost Center</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['cost_center'] ?? 'N/A') ?></dd></div>
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">Alamat</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['ALAMAT'] ?? 'N/A') ?></dd></div>
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">No. Ponsel</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['NO_PONSEL'] ?? 'N/A') ?></dd></div>
                                        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-slate-500">Tgl. Bergabung</dt><dd class="mt-1 text-sm font-medium text-slate-900 sm:col-span-2 sm:mt-0"><?= $join_date_obj ? $join_date_obj->format('d F Y') : 'N/A' ?></dd></div>
                                    </dl>
                                </div>
                                
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                                        <div class="border-b border-slate-200 pb-4 mb-4">
                                            <h3 class="font-semibold text-slate-800">Rincian Estimasi Potongan</h3>
                                            <p class="text-sm text-slate-500">Untuk Periode: <span class="font-bold text-emerald-700"><?= $periode_potongan_label ?></span></p>
                                        </div>
                                        <dl class="space-y-3">
                                            <?php if ($potongan_pokok > 0): ?>
                                            <div class="flex justify-between items-center text-red-600">
                                                <dt class="text-sm font-semibold">Simpanan Pokok (Anggota Baru)</dt>
                                                <dd class="text-sm font-bold">Rp <?= number_format($potongan_pokok, 0, ',', '.') ?></dd>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex justify-between items-center"><dt class="text-sm text-slate-600">Angsuran Elektronik</dt><dd class="text-sm font-medium text-slate-900">Rp <?= number_format($total_angsuran_elektronik, 0, ',', '.') ?></dd></div>
                                            <div class="flex justify-between items-center"><dt class="text-sm text-slate-600">Hutang Sembako (Periode Ini)</dt><dd class="text-sm font-medium text-slate-900">Rp <?= number_format($total_sembako_debt_this_period, 0, ',', '.') ?></dd></div>
                                            <div class="flex justify-between items-center"><dt class="text-sm text-slate-600">Akumulasi Selisih</dt><dd class="text-sm font-medium text-slate-900">Rp <?= number_format($total_akumulasi_selisih, 0, ',', '.') ?></dd></div>
                                            <div class="flex justify-between items-center"><dt class="text-sm text-slate-600">Simpanan Wajib</dt><dd class="text-sm font-medium text-slate-900">Rp <?= number_format($potongan_wajib_preview, 0, ',', '.') ?></dd></div>
                                            <div class="border-t border-slate-200 pt-3 mt-3 flex justify-between items-center"><dt class="text-base font-bold text-slate-800">Total Estimasi</dt><dd class="text-base font-bold text-slate-900">Rp <?= number_format($total_estimasi_potongan, 0, ',', '.') ?></dd></div>
                                        </dl>
                                    </div>
                                    <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
                                        <div class="border-b border-slate-200 pb-4 mb-4"><h3 class="font-semibold text-slate-800">Riwayat Akumulasi Selisih</h3></div>
                                        <table class="w-full text-sm">
                                            <thead><tr class="border-b border-slate-200"><th class="text-left font-semibold text-slate-600 pb-2">Periode</th><th class="text-right font-semibold text-slate-600 pb-2">Jumlah</th></tr></thead>
                                            <tbody>
                                                <?php if (!empty($riwayat_selisih)): foreach($riwayat_selisih as $selisih): ?>
                                                    <tr class="border-b border-slate-100 last:border-b-0"><td class="py-2 text-slate-700"><?= htmlspecialchars($selisih['periode_bulan']) ?></td><td class="py-2 text-right text-slate-900">Rp <?= number_format($selisih['jumlah_selisih'], 0, ',', '.') ?></td></tr>
                                                <?php endforeach; else: ?>
                                                    <tr><td colspan="2" class="text-center py-6 text-slate-500">Tidak ada riwayat selisih.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot><tr class="font-bold"><td class="pt-3 text-slate-800">Total Akumulasi</td><td class="pt-3 text-right text-slate-900">Rp <?= number_format($total_akumulasi_selisih, 0, ',', '.') ?></td></tr></tfoot>
                                        </table>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                                    <div class="bg-red-50 p-4 rounded-xl border border-red-200 shadow-sm"><p class="text-sm text-red-700">Total Sisa Hutang</p><p class="text-2xl font-bold text-red-900">Rp <?= number_format($total_overall_debt, 0, ',', '.') ?></p></div>
                                    <div class="bg-orange-50 p-4 rounded-xl border border-orange-200 shadow-sm"><p class="text-sm text-orange-700">Potongan Koperasi</p><p class="text-2xl font-bold text-orange-900">Rp <?= number_format($potongan_ditampilkan, 0, ',', '.') ?></p><?php if ($kelebihan_potongan > 0): ?><div class="mt-1 text-xs font-semibold text-red-600 bg-red-100 p-1 rounded-md text-center"><i class="fas fa-exclamation-triangle"></i> Akan ada selisih: Rp <?= number_format($kelebihan_potongan, 0, ',', '.') ?></div><?php endif; ?></div>
                                    <div class="bg-emerald-50 p-4 rounded-xl border border-emerald-200 shadow-sm"><p class="text-sm text-emerald-700">Total Simpanan</p><p class="text-2xl font-bold text-emerald-900">Rp <?= number_format($total_savings, 0, ',', '.') ?></p><p class="text-xs text-emerald-600">(Pokok: Rp <?= number_format($principal_saving, 0, ',', '.') ?> + Wajib: Rp <?= number_format($mandatory_savings, 0, ',', '.') ?>)</p></div>
                                </div>

                                <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                                    <h3 class="text-lg font-semibold text-slate-800 mb-4"><i class="fas fa-laptop-house mr-2 text-blue-500"></i>Detail Hutang Elektronik</h3>
                                    <?php if (!empty($electronic_debts)): ?>
                                        <div class="overflow-x-auto">
                                            <!-- [MODIFIKASI] Menambahkan kolom 'Harga' pada tabel header -->
                                            <table class="excel-table">
                                                <thead><tr><th>No.</th><th class="text-left">Nama Barang</th><th>Harga</th><th>Angsuran/Bulan</th><th>Sisa Bulan</th><th>Total Sisa</th></tr></thead>
                                                <tbody>
                                                <?php foreach ($electronic_debts as $index => $debt): ?>
                                                    <tr>
                                                        <td><?= $index + 1 ?></td>
                                                        <td class="font-medium text-left"><?= htmlspecialchars($debt['nama_barang']) ?></td>
                                                        <!-- [BARU] Menambahkan kolom data untuk 'Harga' -->
                                                        <td class="currency">Rp <?= number_format($debt['harga_barang'], 0, ',', '.') ?></td>
                                                        <td class="currency">Rp <?= number_format($debt['angsuran_perbulan'], 0, ',', '.') ?></td>
                                                        <td class="font-bold text-red-600"><?= htmlspecialchars($debt['sisa_angsuran']) ?></td>
                                                        <td class="currency font-bold text-red-700">Rp <?= number_format($debt['total_sisa_hutang_per_item'], 0, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center text-slate-500 py-4">Tidak ada data hutang elektronik yang aktif.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                                    <h3 class="text-lg font-semibold text-slate-800 mb-4"><i class="fas fa-shopping-basket mr-2 text-emerald-500"></i>Detail Hutang Sembako</h3>
                                    <?php if (!empty($sembako_debts)): ?>
                                    <div class="overflow-x-auto">
                                        <table class="excel-table">
                                            <thead><tr><th class="text-left">Nama Barang</th><th>Jumlah</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($sembako_debts as $item): ?>
                                                <tr>
                                                    <td class="font-medium text-left"><?= htmlspecialchars($item['nama_barang']) ?></td>
                                                    <td class="currency font-bold">Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                        <p class="text-center text-slate-500 py-4">Tidak ada data hutang sembako yang aktif.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                                    <h3 class="text-lg font-semibold text-slate-800 mb-4"><i class="fas fa-history mr-2 text-slate-500"></i>Riwayat Potongan Gaji</h3>
                                    <?php if (!empty($semua_riwayat_potongan)): ?>
                                        <div class="overflow-x-auto">
                                            <table class="excel-table">
                                                <thead>
                                                    <tr><th>Periode</th><th>Tgl. Proses</th><th>Total Potongan</th><th>Status</th></tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($semua_riwayat_potongan as $riwayat): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($riwayat['periode_bulan']) ?></td>
                                                        <td><?= $riwayat['tanggal_proses'] ? htmlspecialchars(date('d M Y H:i', strtotime($riwayat['tanggal_proses']))) : '-' ?></td>
                                                        <td class="currency font-bold">Rp <?= number_format($riwayat['total_potongan'], 0, ',', '.') ?></td>
                                                        <td>
                                                            <span class="status-badge <?= strtolower($riwayat['status']) === 'pending' ? 'status-pending' : 'status-processed' ?>">
                                                                <?= htmlspecialchars(ucfirst($riwayat['status'])) ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center text-slate-500 py-4">Tidak ada riwayat potongan gaji.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <?php if ($employee): ?>
    <div id="removeMemberModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3 class="text-xl font-bold">Konfirmasi Penonaktifan</h3></div>
            <div class="modal-body">
                <p class="text-slate-600">Anda yakin ingin menonaktifkan anggota <strong class="text-slate-900"><?= htmlspecialchars($employee['NAMA'] ?? '') ?></strong>? Tindakan ini akan membuat notifikasi ke Superuser.</p>
            </div>
            <form method="POST" action="dashboard_personalia.php?nik=<?= htmlspecialchars($nik_search) ?>">
                <input type="hidden" name="nik" value="<?= htmlspecialchars($employee['NIK'] ?? '') ?>">
                <input type="hidden" name="status" value="NON-AKTIF">
                <input type="hidden" name="action" value="update_status">
                <div class="modal-footer flex justify-end gap-3">
                    <button type="button" onclick="closeModal('removeMemberModal')" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Nonaktifkan</button>
                </div>
            </form>
        </div>
    </div>
    <div id="activateMemberModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h3 class="text-xl font-bold">Konfirmasi Aktivasi</h3></div>
            <div class="modal-body">
                <p class="text-slate-600">Anda yakin ingin mengaktifkan kembali anggota <strong class="text-slate-900"><?= htmlspecialchars($employee['NAMA'] ?? '') ?></strong>?</p>
            </div>
            <form method="POST" action="dashboard_personalia.php?nik=<?= htmlspecialchars($nik_search) ?>">
                <input type="hidden" name="nik" value="<?= htmlspecialchars($employee['NIK'] ?? '') ?>">
                <input type="hidden" name="status" value="AKTIF">
                <input type="hidden" name="action" value="update_status">
                <div class="modal-footer flex justify-end gap-3">
                    <button type="button" onclick="closeModal('activateMemberModal')" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-success">Ya, Aktifkan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(id) { document.getElementById(id)?.classList.add('active'); }
        function closeModal(id) { document.getElementById(id)?.classList.remove('active'); }
        document.querySelectorAll('.modal-overlay').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el) {
                    closeModal(el.id);
                }
            });
        });

        // [BARU] Script untuk logo bergantian
        document.addEventListener('DOMContentLoaded', function() {
            const logo1 = document.getElementById('logo1');
            const logo2 = document.getElementById('logo2');
            let currentLogo = 1;

            setInterval(() => {
                if (currentLogo === 1) {
                    logo1.style.opacity = '0';
                    logo2.style.opacity = '1';
                    currentLogo = 2;
                } else {
                    logo1.style.opacity = '1';
                    logo2.style.opacity = '0';
                    currentLogo = 1;
                }
            }, 4000);
        });
    </script>
</body>
</html>