<?php

// --- [MODIFIED] Handle AJAX Requests & Full Page Views ---

// [BARU] Fungsi untuk mencatat riwayat aktivitas
function log_activity($pdo, $action_type, $description) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO riwayat_aktivitas2 (user_id, username, action_type, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['username'], $action_type, $description]);
        } catch (PDOException $e) {
            // Abaikan error jika tabel belum ada atau masalah lain, agar tidak menghentikan proses utama
            error_log("Gagal mencatat aktivitas: " . $e->getMessage());
        }
    }
}


// Check for special full-page requests first
if (isset($_GET['page'])) {
    require_once 'config.php';
    check_login();

    if (!$pdo) {
        die("Database connection failed.");
    }

    $page = $_GET['page'];
    $nik = $_GET['nik'] ?? '';

    // --- [NEW] PIN Input Page ---
    if ($page === 'pin_input' && !empty($nik)) {
        // Ensure there is a pending loan in the session
        if (empty($_SESSION['pending_sembako_loan']) || $_SESSION['pending_sembako_loan']['nik'] !== $nik) {
            die('Sesi tidak valid atau telah berakhir. Silakan mulai lagi.');
        }
        $employee_name = $_SESSION['pending_sembako_loan']['nama'] ?? 'Anggota';
        $total_amount = $_SESSION['pending_sembako_loan']['total'] ?? 0;
?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verifikasi PIN</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; }
                .pin-container { max-width: 400px; margin: 8rem auto; background: white; padding: 2.5rem; border-radius: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; }
                .pin-input { font-size: 2rem; letter-spacing: 1rem; text-align: center; max-width: 200px; margin: 1.5rem auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; }
                .btn-primary { background: #2e7d32; color: white; padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: background-color: 0.3s; cursor: pointer; border: none; }
                .btn-primary:hover { background: #1b5e20; }
            </style>
        </head>
        <body>
            <div class="pin-container">
                <h1 class="text-2xl font-bold text-gray-800">Verifikasi Transaksi</h1>
                <p class="text-gray-600 mt-2">Masukkan PIN Anda untuk mengkonfirmasi pengajuan hutang sembako untuk <strong><?= htmlspecialchars($employee_name) ?></strong> sebesar <strong>Rp <?= number_format($total_amount, 0, ',', '.') ?></strong>.</p>
                <form method="POST" action="index.php?action=process_sembako_submission">
                    <input type="hidden" name="nik" value="<?= htmlspecialchars($nik) ?>">
                    <input type="password" name="pin" class="pin-input" maxlength="6" required autocomplete="off">
                    <button type="submit" class="btn-primary w-full">Konfirmasi & Ajukan</button>
                    <a href="index.php?tab=manajemen_hutang&nik_manajemen=<?= htmlspecialchars($nik) ?>" class="block mt-4 text-gray-500 hover:text-gray-700">Batalkan</a>
                </form>
            </div>
        </body>
        </html>
<?php
        exit;
    }

    // --- [NEW] Submission Success Page ---
    if ($page === 'submission_success' && !empty($nik)) {
        $stmt = $pdo->prepare("SELECT Nama_lengkap, No_ponsel FROM db_anggotakpab WHERE NIK = ?");
        $stmt->execute([$nik]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = $_GET['total'] ?? 0;
        $items_str = $_GET['items'] ?? 'Tidak ada rincian';
        $period_label = $_GET['period'] ?? 'bulan depan';

        $wa_message = "Yth. Bapak/Ibu " . ($member['Nama_lengkap'] ?? '') . ",\n\n";
        $wa_message .= "Kami informasikan bahwa permintaan pembelian sembako Bapak/Ibu telah kami terima. Berikut rincian pesanan:\n\n";
        $wa_message .= "逃 Jenis Barang: " . $items_str . "\n";
        $wa_message .= "腸 Jumlah: Rp " . number_format($total, 0, ',', '.') . "\n";
        $wa_message .= "套 Rencana Pembayaran: Gajian bulan " . $period_label . "\n\n";
        $wa_message .= "Saat ini pesanan masih dalam tahap konfirmasi. Mohon menunggu informasi lebih lanjut dari kami terkait persetujuan dan proses selanjutnya.\n\n";
        $wa_message .= "Terima kasih atas pengajuan dan kerja samanya.\n\n";
        $wa_message .= "Hormat kami,\nKPAB Menegement";

        $phone_number = $member['No_ponsel'] ?? '';
        if (substr($phone_number, 0, 1) === '0') {
            $phone_number = '62' . substr($phone_number, 1);
        }
        $whatsapp_url = 'https://api.whatsapp.com/send?phone=' . urlencode($phone_number) . '&text=' . urlencode($wa_message);
?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Pengajuan Berhasil</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; }
                .success-container { max-width: 500px; margin: 8rem auto; background: white; padding: 2.5rem; border-radius: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; }
                .icon-success { font-size: 4rem; color: #4caf50; }
                .btn-whatsapp { background: #25D366; color: white; padding: 12px 24px; border-radius: 10px; font-weight: 600; transition: background-color: 0.3s; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 0.5rem; }
                .btn-whatsapp:hover { background: #128C7E; }
            </style>
        </head>
        <body>
            <div class="success-container">
                <i class="fas fa-check-circle icon-success"></i>
                <h1 class="text-2xl font-bold text-gray-800 mt-4">PENGAJUAN SUDAH TERKIRIM</h1>
                <p class="text-gray-600 mt-2">Mohon tunggu 3x24 jam untuk info lebih lanjut. Klik tombol di bawah untuk mengirim notifikasi ke anggota.</p>
                <div class="mt-8">
                    <a href="<?= htmlspecialchars($whatsapp_url) ?>" target="_blank" class="btn-whatsapp">
                        <i class="fab fa-whatsapp"></i> Kirim Notifikasi WhatsApp
                    </a>
                </div>
                <a href="index.php?tab=manajemen_hutang&nik_manajemen=<?= htmlspecialchars($nik) ?>" class="block mt-6 text-gray-500 hover:text-gray-700">Kembali ke Panel</a>
            </div>
        </body>
        </html>
<?php
        exit;
    }
}


if (isset($_GET['action'])) {
    require_once 'config.php'; // Ensure config is loaded for AJAX
    check_login(); // Ensure user is logged in for all actions
    header('Content-Type: application/json');

    if (!$pdo) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    $action = $_GET['action'];

    try {
        switch ($action) {
            case 'check_notifications':
                $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifikasi WHERE dibaca = 0");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['unread_count' => (int)$result['unread_count']]);
                break;

            case 'mark_as_read':
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
                    $notificationId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    if ($notificationId) {
                        $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE id = ?");
                        $stmt->execute([$notificationId]);
                        if ($stmt->rowCount() > 0) {
                             log_activity($pdo, 'UPDATE', "Menandai notifikasi #{$notificationId} sebagai sudah dibaca.");
                        }
                        echo json_encode(['success' => $stmt->rowCount() > 0]);
                    } else {
                        throw new Exception('Invalid ID.');
                    }
                } else {
                    throw new Exception('Invalid request method.');
                }
                break;
            
            case 'check_pratama_nik':
                if (isset($_GET['nik'])) {
                    $nik_to_check = trim($_GET['nik']);
                    
                    $stmt_anggota = $pdo->prepare("SELECT NIK FROM db_anggotakpab WHERE NIK = ?");
                    $stmt_anggota->execute([$nik_to_check]);
                    if ($stmt_anggota->fetch()) {
                        echo json_encode(['status' => 'exists', 'message' => 'NIK ini sudah terdaftar sebagai anggota.']);
                        exit;
                    }

                    $stmt_pratama = $pdo->prepare("SELECT NIK, NAMA, DEPARTEMEN FROM db_pratama WHERE NIK = ?");
                    $stmt_pratama->execute([$nik_to_check]);
                    $data = $stmt_pratama->fetch(PDO::FETCH_ASSOC);

                    if ($data) {
                        echo json_encode(['status' => 'found', 'data' => $data]);
                    } else {
                        echo json_encode(['status' => 'not_found', 'message' => 'NIK tidak ditemukan di database master.']);
                    }
                } else {
                     throw new Exception('NIK tidak disediakan.');
                }
                break;
            
                case 'verify_admin_password':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                        // PERBAIKAN FINAL: Mencari user berdasarkan username dari session, bukan ID.
                        // Ini lebih andal jika ID tidak tersimpan dengan benar.
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ?");
                        $stmt->execute([$_SESSION['username']]);
                        $user = $stmt->fetch();
                        
                        ob_clean(); 
    
                        // Mengembalikan logika perbandingan password yang benar dengan trim()
                        if ($user && trim($_POST['password']) === trim($user['password'])) {
                            log_activity($pdo, 'AUTH', "Verifikasi password berhasil untuk edit data.");
                            echo json_encode(['success' => true]);
                        } else {
                            log_activity($pdo, 'AUTH_FAIL', "Verifikasi password gagal untuk edit data.");
                            echo json_encode(['success' => false, 'message' => 'Password salah.']);
                        }
                    } else {
                        ob_clean();
                        throw new Exception('Password tidak disediakan.');
                    }
                    break;    
       
            
            // [NEW] Prepare Sembako Submission and store in session
            case 'prepare_sembako_submission':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $data = json_decode(file_get_contents('php://input'), true);
                    if (!isset($data['nik']) || !isset($data['items']) || !isset($data['total']) || !isset($data['nama'])) {
                         throw new Exception('Data tidak lengkap.');
                    }
                    $_SESSION['pending_sembako_loan'] = [
                        'nik' => $data['nik'],
                        'nama' => $data['nama'],
                        'items' => $data['items'],
                        'total' => $data['total'],
                        'timestamp' => time() // Add a timestamp to expire session later if needed
                    ];
                    echo json_encode(['success' => true]);

                } else {
                    throw new Exception('Invalid request method.');
                }
                break;

            // [MODIFIED] Handle approval and rejection of new members with KPAB number generation
            case 'handle_pengajuan':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $id = $input['id'] ?? null;
                    $aksi = $input['aksi'] ?? null;
                    $jenis_kpab = $input['jenis_kpab'] ?? null; // [NEW] Get KPAB type

                    if (!$id || !$aksi) {
                        throw new Exception('ID atau Aksi tidak valid.');
                    }
                    
                    $pdo->beginTransaction();

                    if ($aksi === 'setuju') {
                        if (!$jenis_kpab || !in_array($jenis_kpab, ['PAB', 'PABL'])) {
                            throw new Exception('Jenis nomor keanggotaan (PAB/PABL) harus dipilih.');
                        }
                        
                        // 1. Get data from pengajuan
                        $stmt = $pdo->prepare("SELECT * FROM db_pengajuankpab WHERE id = ? AND status = 'pending'");
                        $stmt->execute([$id]);
                        $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$pengajuan) {
                            throw new Exception('Data pengajuan tidak ditemukan atau sudah diproses.');
                        }

                        // 2. Generate new KPAB number
                        $new_kpab_id = generate_new_kpab_number($pdo, $jenis_kpab);

                        // 3. Insert into db_anggotakpab
                        $sql_member = "INSERT INTO db_anggotakpab (date_join, no_kpab, NIK, Nama_lengkap, Tanggal_lahir, Alamat_Rumah, No_ponsel, Departemen, status, SIMPANAN_wajib, SIMPANAN_pokok) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_member = $pdo->prepare($sql_member);
                        
                        $status_anggota = 'AKTIF';
                        $date_join = date('Y-m-d');
                        $simpanan_wajib = 25000;
                        $simpanan_pokok = 50000;

                        $stmt_member->execute([
                            $date_join, $new_kpab_id, $pengajuan['nik'], $pengajuan['nama_lengkap'], 
                            $pengajuan['ttl'], // Assuming ttl field contains date of birth
                            $pengajuan['alamat'], $pengajuan['no_ponsel'], $pengajuan['departemen'], 
                            $status_anggota, $simpanan_wajib, $simpanan_pokok
                        ]);

                        // 4. Insert into db_hutangsembako for simpanan pokok
                        $sql_debt = "INSERT INTO db_hutangsembako (nik, nama, nama_barang, jumlah, tanggal_ambil_barang) VALUES (?, ?, ?, ?, ?)";
                        $stmt_debt = $pdo->prepare($sql_debt);
                        $stmt_debt->execute([$pengajuan['nik'], $pengajuan['nama_lengkap'], 'HITUNGAN POKOK', $simpanan_pokok, $date_join]);

                        // 5. Create user login (assuming password is provided during submission or a default one is set)
                        $default_password = 'password123'; // You should have a more secure way to handle this
                        $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                        $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password=VALUES(password), role=VALUES(role)");
                        $stmt_user->execute([$pengajuan['nik'], $hashed_password, 'anggota']);

                        // 6. Update status in db_pengajuankpab
                        $stmt_update = $pdo->prepare("UPDATE db_pengajuankpab SET status = 'disetujui' WHERE id = ?");
                        $stmt_update->execute([$id]);
                        
                        log_activity($pdo, 'CREATE', "Menyetujui anggota baru: {$pengajuan['nama_lengkap']} (NIK: {$pengajuan['nik']}) dengan No. KPAB {$new_kpab_id}.");
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'Anggota berhasil disetujui.', 'data' => ['nama' => $pengajuan['nama_lengkap'], 'no_kpab' => $new_kpab_id]]);

                    } elseif ($aksi === 'tolak') {
                        $stmt_pengajuan_nama = $pdo->prepare("SELECT nama_lengkap, nik FROM db_pengajuankpab WHERE id = ?");
                        $stmt_pengajuan_nama->execute([$id]);
                        $pengajuan_info = $stmt_pengajuan_nama->fetch(PDO::FETCH_ASSOC);

                        $stmt = $pdo->prepare("UPDATE db_pengajuankpab SET status = 'ditolak' WHERE id = ?");
                        $stmt->execute([$id]);
                        log_activity($pdo, 'DELETE', "Menolak pengajuan anggota: {$pengajuan_info['nama_lengkap']} (NIK: {$pengajuan_info['nik']}).");
                        $pdo->commit();
                        echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil ditolak.']);
                    } else {
                        throw new Exception('Aksi tidak dikenal.');
                    }
                } else {
                    throw new Exception('Metode request tidak valid.');
                }
                break;
            
            // [BARU] Handle selisih payment
            case 'handle_selisih_payment':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $pdo->beginTransaction();
                    
                    $nik = $_POST['nik'] ?? null;
                    $nama = $_POST['nama'] ?? null;
                    $status_pembayaran = $_POST['status_pembayaran'] ?? null;

                    if (!$nik || !$nama || !$status_pembayaran) {
                        throw new Exception("Data NIK, nama, dan status pembayaran tidak lengkap.");
                    }

                    if ($status_pembayaran === 'akumulasikan') {
                        log_activity($pdo, 'INFO', "Admin mengkonfirmasi untuk mengakumulasi selisih untuk {$nama} (NIK: {$nik}).");
                        $message = "Opsi untuk mengakumulasi selisih telah dicatat.";
                    } elseif ($status_pembayaran === 'lunaskan') {
                        $jumlah_dibayar = clean_currency($_POST['jumlah_dibayar'] ?? '0');
                        $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'CASH';

                        if ($jumlah_dibayar <= 0) {
                            throw new Exception("Jumlah yang dibayarkan harus lebih besar dari nol.");
                        }

                        $keterangan = "PEMBAYARAN SELISIH VIA " . strtoupper($metode_pembayaran);
                        $jumlah_negatif = -1 * $jumlah_dibayar;

                        // Insert as a negative debt to offset future calculations
                        $stmt = $pdo->prepare("INSERT INTO db_hutangsembako (nik, nama, nama_barang, jumlah, tanggal_ambil_barang) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$nik, $nama, $keterangan, $jumlah_negatif, date('Y-m-d H:i:s')]);
                        
                        log_activity($pdo, 'CREATE', "Mencatat pembayaran selisih untuk {$nama} (NIK: {$nik}) sebesar Rp " . number_format($jumlah_dibayar) . " via {$metode_pembayaran}.");
                        $message = "Pembayaran selisih berhasil dicatat.";
                    } else {
                        throw new Exception("Status pembayaran tidak valid.");
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => $message]);

                } else {
                    throw new Exception('Metode request tidak valid.');
                }
                break;

            default:
                throw new Exception('Aksi tidak valid.');
        }
    } catch (Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// File: index.php (Superuser Panel)
// Deskripsi: Halaman utama untuk role Superuser.
// Modifikasi: Menambah fitur edit data anggota dan non-aktif.

// Memuat konfigurasi dan memulai session
require_once 'config.php';

// Memeriksa apakah pengguna sudah login dan memiliki role 'superuser'
check_login('superuser');

// Jika koneksi database gagal, tampilkan error dan hentikan eksekusi
if (!$pdo) {
    die($db_error);
}

// --- INITIALIZE VARIABLES ---
$employee = null;
$electronic_debts = [];
$sembako_debts = [];
$semua_riwayat_potongan = []; 
$items_list = [];
$error_message = '';
$success_message = '';
$nik_search = '';
$active_tab = $_GET['tab'] ?? 'dashboard';
$join_date_obj = null;
$total_electronic_debt = 0;
$total_sembako_debt_overall = 0; 
$total_overall_debt = 0;
$mandatory_savings = 0;
$principal_saving = 50000;
$total_savings = 0;
$limit_potongan = 950000;
$total_angsuran_elektronik = 0;
$all_members_data = []; 
$all_accounts = [];
$unread_personalia_count = 0; // [FIXED] Initialize variable
$unread_pengajuan_count = 0; // [NEW] Initialize variable for new applications
$payroll_data = []; // [NEW] For Payroll Database
$payroll_summary = ['total_simpanan_wajib' => 0, 'total_hutang' => 0, 'total_selisih' => 0, 'total_simpanan_pokok' => 0]; // [MODIFIKASI] For Payroll Summary
$periode_payroll_bulan_ini = $_GET['periode_payroll'] ?? date('Y-m'); // [FIX] MOVED HERE

// Initialize variables for deduction calculation
$potongan_ditampilkan = 0;
$kelebihan_potongan = 0;
$total_estimasi_potongan = 0;
$potongan_wajib_preview = 25000;
$riwayat_selisih = [];
$total_akumulasi_selisih = 0;
$potongan_pokok = 0;
// [BARU] Variabel untuk hutang sembako periode ini saja
$total_sembako_debt_this_period = 0;

// --- [MODIFIED] More robust function to generate new member ID for PAB and PABL ---
function generate_new_kpab_number($pdo, $type) {
    $type = strtoupper($type);
    if (!in_array($type, ['PAB', 'PABL'])) {
        throw new Exception("Jenis keanggotaan tidak valid. Harus PAB atau PABL.");
    }

    $prefix = $type;
    $start_number = ($type === 'PAB') ? 737 : 20;
    $pad_length = ($type === 'PAB') ? 5 : 4;

    $sql = "SELECT MAX(no_kpab) as max_id FROM db_anggotakpab WHERE no_kpab LIKE ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$prefix . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $number = $start_number;
    if ($result && $result['max_id'] !== null) {
        $last_num_str = substr($result['max_id'], strlen($prefix));
        if (is_numeric($last_num_str)) {
            $number = (int)$last_num_str + 1;
        }
    }

    return $prefix . str_pad($number, $pad_length, '0', STR_PAD_LEFT);
}


// --- [NEW & MODIFIED] Handle POST Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    // For regular form posts, not AJAX, so begin transaction here
    if ($action !== 'process_sembako_submission') {
        $pdo->beginTransaction();
    }
    try {
        switch($action) {
            case 'add_account':
                if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['role'])) {
                    throw new Exception("Username, password, dan role harus diisi.");
                }
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['username'], $hashed_password, $_POST['role']]);
                log_activity($pdo, 'CREATE', "Membuat akun baru: {$_POST['username']} dengan role {$_POST['role']}.");
                $success_message = "Akun '{$_POST['username']}' berhasil dibuat.";
                $_GET['tab'] = 'account_management';
                break;

            case 'delete_account':
                 if (isset($_POST['id'])) {
                    $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt_user->execute([$_POST['id']]);
                    $user_to_delete = $stmt_user->fetchColumn();

                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    log_activity($pdo, 'DELETE', "Menghapus akun: {$user_to_delete}.");
                    $success_message = "Akun berhasil dihapus.";
                }
                $_GET['tab'] = 'account_management';
                break;
            
            case 'edit_account':
                if (empty($_POST['id']) || empty($_POST['username']) || empty($_POST['role'])) {
                    throw new Exception("ID, Username, dan Role harus diisi.");
                }
                $log_msg = "Memperbarui akun '{$_POST['username']}'. Role diubah menjadi {$_POST['role']}.";
                if (!empty($_POST['password'])) {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->execute([$_POST['username'], $hashed_password, $_POST['role'], $_POST['id']]);
                    $log_msg .= " Password juga diubah.";
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                    $stmt->execute([$_POST['username'], $_POST['role'], $_POST['id']]);
                }
                log_activity($pdo, 'UPDATE', $log_msg);
                $success_message = "Akun '{$_POST['username']}' berhasil diperbarui.";
                $_GET['tab'] = 'account_management';
                break;

            case 'add_member':
                if (empty($_POST['nik']) || empty($_POST['nama_lengkap']) || empty($_POST['login_password']) || empty($_POST['jenis_keanggotaan'])) {
                    throw new Exception("Data NIK, Nama, Password Login, dan Jenis Keanggotaan tidak boleh kosong.");
                }

                $new_kpab_id = generate_new_kpab_number($pdo, $_POST['jenis_keanggotaan']);

                $sql_member = "INSERT INTO db_anggotakpab (date_join, no_kpab, NIK, Nama_lengkap, Tanggal_lahir, Alamat_Rumah, No_ponsel, Departemen, status, SIMPANAN_wajib, SIMPANAN_pokok) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_member = $pdo->prepare($sql_member);
                
                $status = 'AKTIF';
                $date_join = date('Y-m-d');
                $simpanan_wajib = 25000;
                $simpanan_pokok = 50000;

                $stmt_member->execute([
                    $date_join, $new_kpab_id, $_POST['nik'], $_POST['nama_lengkap'], $_POST['tanggal_lahir'], $_POST['alamat_rumah'], $_POST['no_ponsel'], $_POST['departemen'], $status, $simpanan_wajib, $simpanan_pokok
                ]);

                $sql_debt = "INSERT INTO db_hutangsembako (nik, nama, nama_barang, jumlah, tanggal_ambil_barang) VALUES (?, ?, ?, ?, ?)";
                $stmt_debt = $pdo->prepare($sql_debt);
                $stmt_debt->execute([$_POST['nik'], $_POST['nama_lengkap'], 'HITUNGAN POKOK', $simpanan_pokok, $date_join]);

                $hashed_password = password_hash($_POST['login_password'], PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt_user->execute([$_POST['nik'], $hashed_password, 'anggota']);
                
                log_activity($pdo, 'CREATE', "Menambahkan anggota baru secara manual: {$_POST['nama_lengkap']} (NIK: {$_POST['nik']}) dengan No. KPAB {$new_kpab_id}.");
                $success_message = "PENDAFTARAN BERHASIL. Anggota baru '{$_POST['nama_lengkap']}' ({$new_kpab_id}) telah ditambahkan.";
                $_GET['tab'] = 'database_anggota';
                break;

            case 'deactivate_member':
                if (empty($_POST['nik'])) {
                    throw new Exception("NIK anggota tidak valid.");
                }
                $stmt = $pdo->prepare("UPDATE db_anggotakpab SET status = 'NON-AKTIF' WHERE NIK = ?");
                $stmt->execute([$_POST['nik']]);
                log_activity($pdo, 'UPDATE', "Menonaktifkan anggota dengan NIK {$_POST['nik']}.");
                $success_message = "Anggota dengan NIK {$_POST['nik']} telah dinonaktifkan.";
                $_GET['nik'] = $_POST['nik'];
                $_GET['tab'] = 'search';
                break;

            // [NEW] Process sembako submission after PIN
            case 'process_sembako_submission':
                $nik = $_POST['nik'] ?? '';
                $pin = $_POST['pin'] ?? '';

                if (empty($nik) || empty($pin)) {
                    // Redirect back with error
                    header('Location: index.php?page=pin_input&nik='.$nik.'&error=1');
                    exit;
                }
                // In a real scenario, you would validate the PIN against a stored value.
                // For now, we just check if it's not empty.
                
                if (empty($_SESSION['pending_sembako_loan']) || $_SESSION['pending_sembako_loan']['nik'] !== $nik) {
                    die('Sesi pengajuan tidak ditemukan atau tidak cocok. Silakan ulangi.');
                }

                $loan_data = $_SESSION['pending_sembako_loan'];
                
                $pdo->beginTransaction();
                
                $sql = "INSERT INTO db_hutangsembako (nik, nama, nama_barang, jumlah, tanggal_ambil_barang) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $today = date('Y-m-d');
                $item_names = [];
                
                foreach ($loan_data['items'] as $item) {
                    $stmt->execute([
                        $loan_data['nik'],
                        $loan_data['nama'],
                        $item['name'],
                        $item['price'],
                        $today
                    ]);
                    $item_names[] = $item['name'];
                }

                // [MODIFIKASI] Tambahkan notifikasi untuk pengajuan cicilan sembako
                $pesan_notif = "Pengajuan Cicilan Sembako baru dari {$loan_data['nama']} (NIK: {$loan_data['nik']}) sejumlah Rp " . number_format($loan_data['total'], 0, ',', '.');
                $stmt_notif = $pdo->prepare("INSERT INTO notifikasi (pesan, tanggal, dibaca) VALUES (?, NOW(), 0)");
                $stmt_notif->execute([$pesan_notif]);
                
                log_activity($pdo, 'CREATE', "Mengajukan hutang sembako untuk {$loan_data['nama']} (NIK: {$loan_data['nik']}) sejumlah Rp " . number_format($loan_data['total'], 0, ',', '.'));

                $pdo->commit();
                
                // Clear the session data
                unset($_SESSION['pending_sembako_loan']);

                // Prepare for redirect
                $items_for_url = implode(', ', $item_names);
                
                // Get period label
                $today_dt = new DateTime();
                $cutoff_day = 6;
                if ((int)$today_dt->format('j') < $cutoff_day) {
                    $period_date = new DateTime('first day of this month');
                } else {
                    $period_date = new DateTime('first day of next month');
                }
                $indonesian_months_simple = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                $period_label = $indonesian_months_simple[(int)$period_date->format('n') - 1] . ' ' . $period_date->format('Y');

                $redirect_url = sprintf(
                    "index.php?page=submission_success&nik=%s&total=%s&items=%s&period=%s",
                    urlencode($loan_data['nik']),
                    urlencode($loan_data['total']),
                    urlencode($items_for_url),
                    urlencode($period_label)
                );
                
                header('Location: ' . $redirect_url);
                exit;
        }
        if ($action !== 'process_sembako_submission') {
            $pdo->commit();
        }
    } catch (PDOException $e) {
        if ($action !== 'process_sembako_submission') $pdo->rollBack();
        if ($e->getCode() == 23000) { 
            $error_message = "Gagal: Username atau NIK mungkin sudah ada.";
        } else {
            $error_message = "Database Error: " . $e->getMessage();
        }
    } catch (Exception $e) {
        if ($action !== 'process_sembako_submission') $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}


// --- [MODIFIKASI] Dashboard Data Fetching ---
$stats = [
    'active_members' => 0,
    'inactive_members' => 0,
    'new_members' => 0,
];
$newest_members = [];
$recent_transactions = [];
$combined_notifications = []; // Gabungan semua notifikasi untuk dashboard
$hutang_hampir_lunas = []; // Anggota dengan cicilan elektronik sisa 1-3 bulan
$riwayat_aktivitas_terbaru = []; // [BARU] Untuk log aktivitas
$anggota_dengan_selisih = []; // [BARU] Untuk manajemen selisih

try {
    // Statistik Anggota
    $stats['active_members'] = $pdo->query("SELECT COUNT(*) FROM db_anggotakpab WHERE status = 'AKTIF'")->fetchColumn();
    $stats['inactive_members'] = $pdo->query("SELECT COUNT(*) FROM db_anggotakpab WHERE status = 'NON-AKTIF'")->fetchColumn();
    
    // Anggota Baru
    $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
    $stats['new_members'] = $pdo->query("SELECT COUNT(*) FROM db_anggotakpab WHERE date_join >= '$thirty_days_ago'")->fetchColumn();

    // [BARU] Hutang Hampir Lunas
    $stmt_hutang_lunas = $pdo->query("
        SELECT a.Nama_lengkap, a.NIK, h.SISA_BULAN, h.JENIS_BARANG 
        FROM db_hutangelectronik h 
        JOIN db_anggotakpab a ON h.NIK = a.NIK 
        WHERE h.SISA_BULAN BETWEEN 1 AND 3 AND a.status = 'AKTIF' 
        ORDER BY h.SISA_BULAN ASC 
        LIMIT 5
    ");
    $hutang_hampir_lunas = $stmt_hutang_lunas->fetchAll(PDO::FETCH_ASSOC);

    // [MODIFIKASI] Menggabungkan Notifikasi untuk Dashboard
    // 1. Ambil Notifikasi Personalia & Cicilan (dari tabel notifikasi)
    $stmt_personalia_notif = $pdo->query("SELECT id, pesan, tanggal, dibaca FROM notifikasi ORDER BY tanggal DESC LIMIT 10");
    foreach ($stmt_personalia_notif->fetchAll(PDO::FETCH_ASSOC) as $notif) {
        $type = 'PERSONELIA'; // Default
        if (strpos(strtolower($notif['pesan']), 'cicilan sembako') !== false) {
            $type = 'CICILAN SEMBAKO';
        } else if (strpos(strtolower($notif['pesan']), 'cicilan elektronik') !== false) {
            $type = 'CICILAN ELEKTRONIK';
        }
        $combined_notifications[] = [
            'id' => 'p' . $notif['id'],
            'pesan' => $notif['pesan'],
            'tanggal' => $notif['tanggal'],
            'is_new' => $notif['dibaca'] == 0,
            'type' => $type,
            'link' => 'index.php?tab=personalia'
        ];
    }
    
    // 2. Ambil Notifikasi Pengajuan KPAB (dari tabel pengajuan)
    $stmt_pengajuan_notif = $pdo->query("SELECT id, nik, nama_lengkap, tanggal_pengajuan FROM db_pengajuankpab WHERE status = 'pending' ORDER BY tanggal_pengajuan DESC LIMIT 5");
    foreach ($stmt_pengajuan_notif->fetchAll(PDO::FETCH_ASSOC) as $notif) {
        $combined_notifications[] = [
            'id' => 'kpab' . $notif['id'],
            'pesan' => "Pengajuan anggota baru: {$notif['nama_lengkap']} (NIK: {$notif['nik']})",
            'tanggal' => $notif['tanggal_pengajuan'],
            'is_new' => true, // Pengajuan selalu dianggap baru
            'type' => 'DAFTAR KPAB',
            'link' => 'index.php?tab=pengajuan_anggota_baru'
        ];
    }

    // 3. Urutkan semua notifikasi berdasarkan tanggal
    usort($combined_notifications, function($a, $b) {
        return strtotime($b['tanggal']) - strtotime($a['tanggal']);
    });
    // Ambil 5 notifikasi terbaru setelah diurutkan
    $combined_notifications = array_slice($combined_notifications, 0, 5);
    
    $unread_pengajuan_count = $pdo->query("SELECT COUNT(*) FROM db_pengajuankpab WHERE status = 'pending'")->fetchColumn();

    // [BARU] Ambil riwayat aktivitas terbaru
    $stmt_aktivitas = $pdo->query("SELECT username, description, timestamp FROM riwayat_aktivitas2 ORDER BY timestamp DESC LIMIT 5");
    $riwayat_aktivitas_terbaru = $stmt_aktivitas->fetchAll(PDO::FETCH_ASSOC);
    
    // [BARU] Kalkulasi untuk menemukan anggota dengan selisih bulan ini
    $periode_selisih_saat_ini = date('Y-m');
    $limit_payroll_selisih = 950000;
    $start_date_selisih = $periode_selisih_saat_ini . '-01';
    $end_date_selisih = date("Y-m-t", strtotime($start_date_selisih));

    $sql_selisih = "
        SELECT
            a.NIK, a.Nama_lengkap, a.date_join,
            COALESCE(elec.total_angsuran, 0) AS angsuran_elektronik,
            COALESCE(semb.total_sembako, 0) AS hutang_sembako_periode,
            COALESCE(sel.total_selisih, 0) AS akumulasi_selisih
        FROM db_anggotakpab a
        LEFT JOIN (
            SELECT NIK, SUM(ANGSURAN_PERBULAN) as total_angsuran FROM db_hutangelectronik WHERE SISA_BULAN > 0 GROUP BY NIK
        ) AS elec ON a.NIK = elec.NIK
        LEFT JOIN (
            SELECT NIK, SUM(jumlah) as total_sembako FROM db_hutangsembako WHERE nama_barang != 'HITUNGAN POKOK' AND tanggal_ambil_barang BETWEEN ? AND ? GROUP BY NIK
        ) AS semb ON a.NIK = semb.NIK
        LEFT JOIN (
            SELECT nik, SUM(jumlah_selisih) as total_selisih FROM riwayat_selisih WHERE status = 'terakumulasi' GROUP BY nik
        ) AS sel ON a.NIK = sel.nik
        WHERE a.status = 'AKTIF'";
        
    $stmt_selisih_calc = $pdo->prepare($sql_selisih);
    $stmt_selisih_calc->execute([$start_date_selisih, $end_date_selisih]);
    $calon_selisih_data = $stmt_selisih_calc->fetchAll(PDO::FETCH_ASSOC);

    foreach ($calon_selisih_data as $member) {
        $simpanan_wajib_potongan = 25000;
        $simpanan_pokok_potongan = (date('Y-m', strtotime($member['date_join'])) === $periode_selisih_saat_ini) ? 50000 : 0;
        
        $total_potongan_koperasi = $simpanan_wajib_potongan + $simpanan_pokok_potongan + (float)$member['angsuran_elektronik'] + (float)$member['hutang_sembako_periode'] + (float)$member['akumulasi_selisih'];
        
        if ($total_potongan_koperasi > $limit_payroll_selisih) {
            $selisih = $total_potongan_koperasi - $limit_payroll_selisih;
            $anggota_dengan_selisih[] = [
                'nik' => $member['NIK'],
                'nama_lengkap' => $member['Nama_lengkap'],
                'selisih' => $selisih
            ];
        }
    }


} catch (\PDOException $e) {
    $error_message = "Gagal mengambil statistik dasbor: " . $e->getMessage();
}

// --- [MODIFIED] Logic for Member Database Tab with new data, search, and sorting ---
if ($active_tab === 'database_anggota') {
    try {
        // [NEW] Get search parameters
        $search_nik_db = $_GET['search_nik_db'] ?? '';
        $search_nama_db = $_GET['search_nama_db'] ?? '';
        $search_kpab_db = $_GET['search_kpab_db'] ?? '';

        $sql_members = "
            SELECT 
                a.NIK, a.no_kpab, a.Nama_lengkap, a.Departemen, a.cost_center, a.status,
                (a.SIMPANAN_pokok + a.SIMPANAN_wajib) as total_simpanan,
                (SELECT SUM(h.ANGSURAN_PERBULAN * h.SISA_BULAN) FROM db_hutangelectronik h WHERE h.NIK = a.NIK AND h.SISA_BULAN > 0) as cicilan_elektronik,
                (SELECT SUM(s.jumlah) FROM db_hutangsembako s WHERE s.NIK = a.NIK AND s.nama_barang != 'HITUNGAN POKOK') as cicilan_sembako
            FROM db_anggotakpab a
            WHERE 1=1";
        
        $params_db = [];
        if (!empty($search_nik_db)) {
            $sql_members .= " AND a.NIK LIKE :nik";
            $params_db[':nik'] = '%' . $search_nik_db . '%';
        }
        if (!empty($search_nama_db)) {
            $sql_members .= " AND a.Nama_lengkap LIKE :nama";
            $params_db[':nama'] = '%' . $search_nama_db . '%';
        }
        if (!empty($search_kpab_db)) {
            $sql_members .= " AND a.no_kpab LIKE :kpab";
            $params_db[':kpab'] = '%' . $search_kpab_db . '%';
        }
        
        // [MODIFIED] Sorting by no_kpab
        $sql_members .= " ORDER BY a.no_kpab ASC";
        
        $stmt_members = $pdo->prepare($sql_members);
        $stmt_members->execute($params_db);
        $all_members_data = $stmt_members->fetchAll(PDO::FETCH_ASSOC);
        
        // Pisahkan data aktif dan non-aktif untuk tab
        $active_members_list = array_filter($all_members_data, function($member) {
            return $member['status'] === 'AKTIF';
        });
        $inactive_members_list = array_filter($all_members_data, function($member) {
            return $member['status'] === 'NON-AKTIF';
        });

    } catch (\PDOException $e) {
        $error_message .= " Gagal memuat database anggota: " . $e->getMessage();
    }
}


// --- [MODIFIKASI TOTAL] Logic for Payroll Database Tab ---
if (isset($_GET['tab']) && $_GET['tab'] === 'payroll_database') {
    try {
        // [MODIFIKASI] Optimasi Kueri untuk Mencegah Timeout
        // Menggabungkan beberapa kueri menjadi satu untuk efisiensi
        
        $limit_payroll = 950000;
        $start_date = $periode_payroll_bulan_ini . '-01';
        $end_date = date("Y-m-t", strtotime($start_date));

        $sql = "
            SELECT
                a.NIK, a.no_kpab, a.Nama_lengkap, a.Departemen, a.date_join,
                COALESCE(elec.total_angsuran, 0) AS angsuran_elektronik,
                COALESCE(semb.total_sembako, 0) AS hutang_sembako_periode,
                COALESCE(sel.total_selisih, 0) AS akumulasi_selisih
            FROM
                db_anggotakpab a
            LEFT JOIN (
                SELECT NIK, SUM(ANGSURAN_PERBULAN) as total_angsuran
                FROM db_hutangelectronik
                WHERE SISA_BULAN > 0
                GROUP BY NIK
            ) AS elec ON a.NIK = elec.NIK
            LEFT JOIN (
                SELECT NIK, SUM(jumlah) as total_sembako
                FROM db_hutangsembako
                WHERE nama_barang != 'HITUNGAN POKOK'
                  AND tanggal_ambil_barang BETWEEN :start_date AND :end_date
                GROUP BY NIK
            ) AS semb ON a.NIK = semb.NIK
            LEFT JOIN (
                SELECT nik, SUM(jumlah_selisih) as total_selisih
                FROM riwayat_selisih
                WHERE status = 'terakumulasi'
                GROUP BY nik
            ) AS sel ON a.NIK = sel.nik
            WHERE
                a.status = 'AKTIF'
            ORDER BY
                a.no_kpab ASC;
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
        $all_members_payroll_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_members_payroll_data as $member) {
            // a. Simpanan Wajib (selalu ada)
            $simpanan_wajib_potongan = 25000;

            // b. Simpanan Pokok (hanya jika baru bergabung di periode ini)
            $simpanan_pokok_potongan = 0;
            $join_date_month = date('Y-m', strtotime($member['date_join']));
            if ($join_date_month === $periode_payroll_bulan_ini) {
                $simpanan_pokok_potongan = 50000;
            }

            // Data dari kueri gabungan
            $hutang_sembako_potongan = (float)$member['hutang_sembako_periode'];
            $hutang_elektronik_potongan = (float)$member['angsuran_elektronik'];
            $akumulasi_selisih_potongan = (float)$member['akumulasi_selisih'];

            // Kalkulasi Total
            $hutang_koperasi_total_bulan_ini = $hutang_sembako_potongan + $hutang_elektronik_potongan;
            $total_potongan_koperasi = $simpanan_wajib_potongan + $simpanan_pokok_potongan + $hutang_koperasi_total_bulan_ini + $akumulasi_selisih_potongan;
            $total_potongan_payroll = min($total_potongan_koperasi, $limit_payroll);
            $selisih_bulan_ini = max(0, $total_potongan_koperasi - $total_potongan_payroll);

            $payroll_data[] = [
                'date_join' => $member['date_join'],
                'no_kpab' => $member['no_kpab'],
                'nik' => $member['NIK'],
                'nama_lengkap' => $member['Nama_lengkap'],
                'departemen' => $member['Departemen'],
                'simpanan_pokok' => $simpanan_pokok_potongan,
                'simpanan_wajib' => $simpanan_wajib_potongan,
                'hutang_koperasi' => $hutang_koperasi_total_bulan_ini,
                'total_potongan_koperasi' => $total_potongan_koperasi,
                'total_potongan_payroll' => $total_potongan_payroll,
                'selisih' => $selisih_bulan_ini,
            ];

            // Akumulasi untuk summary
            $payroll_summary['total_simpanan_pokok'] += $simpanan_pokok_potongan;
            $payroll_summary['total_simpanan_wajib'] += $simpanan_wajib_potongan;
            $payroll_summary['total_hutang'] += $hutang_koperasi_total_bulan_ini;
            $payroll_summary['total_selisih'] += $selisih_bulan_ini;
        }

    } catch (\PDOException $e) {
        $error_message .= " Gagal memuat database payroll: " . $e->getMessage();
    }
}


// [NEW] Logic for Account Management Tab
try {
    $stmt_accounts = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
    $all_accounts = $stmt_accounts->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
     $error_message .= " Gagal memuat data akun: " . $e->getMessage();
}

// [NEW] Logic for Pengajuan Anggota Baru Tab
$pending_applications = [];
$approved_members_for_tab = [];
$rejected_applications = [];
// [FIX] Initialize search variables globally to prevent notices on other tabs
$search_approved_nik = $_GET['search_approved_nik'] ?? '';
$search_approved_nama = $_GET['search_approved_nama'] ?? '';
$search_approved_kpab = $_GET['search_approved_kpab'] ?? '';
$search_approved_date = $_GET['search_approved_date'] ?? '';

if ($active_tab === 'pengajuan_anggota_baru') {
    try {
        // Fetch pending
        $stmt_pending = $pdo->query("SELECT * FROM db_pengajuankpab WHERE status = 'pending' ORDER BY tanggal_pengajuan DESC");
        $pending_applications = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

        // Fetch rejected
        $stmt_rejected = $pdo->query("SELECT * FROM db_pengajuankpab WHERE status = 'ditolak' ORDER BY tanggal_pengajuan DESC");
        $rejected_applications = $stmt_rejected->fetchAll(PDO::FETCH_ASSOC);

        // Fetch approved with filters
        $sql_approved = "SELECT NIK, Nama_lengkap, no_kpab, date_join, Departemen FROM db_anggotakpab WHERE 1=1";
        $params_approved = [];
        
        if (!empty($search_approved_nik)) {
            $sql_approved .= " AND NIK LIKE :nik";
            $params_approved[':nik'] = '%' . $search_approved_nik . '%';
        }
        if (!empty($search_approved_nama)) {
            $sql_approved .= " AND Nama_lengkap LIKE :nama";
            $params_approved[':nama'] = '%' . $search_approved_nama . '%';
        }
        if (!empty($search_approved_kpab)) {
            $sql_approved .= " AND no_kpab LIKE :kpab";
            $params_approved[':kpab'] = '%' . $search_approved_kpab . '%';
        }
        if (!empty($search_approved_date)) {
            $sql_approved .= " AND date_join = :date_join";
            $params_approved[':date_join'] = $search_approved_date;
        }
        $sql_approved .= " ORDER BY date_join DESC";

        $stmt_approved = $pdo->prepare($sql_approved);
        $stmt_approved->execute($params_approved);
        $approved_members_for_tab = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);

    } catch (\PDOException $e) {
        $error_message .= " Gagal memuat data pengajuan: " . $e->getMessage();
    }
}


// Handle Form Submissions for Loan Management
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_electronic_loan') {
    // ... (Kode untuk menambah hutang elektronik tetap sama)
}

// Fetch items list for the modal
try {
    $items_list = $pdo->query("SELECT name, price FROM items ORDER BY name ASC")->fetchAll();
} catch (\PDOException $e) {
    error_log("Could not fetch items list: " . $e->getMessage());
}

// [MODIFIED] Logic for search forms
if ((isset($_GET['nik']) && !empty($_GET['nik'])) || (isset($_GET['nik_manajemen']) && !empty($_GET['nik_manajemen']))) {
    $nik_search = isset($_GET['nik']) ? trim($_GET['nik']) : trim($_GET['nik_manajemen']);
    if (empty($active_tab) || !in_array($active_tab, ['search', 'manajemen_hutang'])) {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'search';
    }

    try {
        // [MODIFIED] Added no_kpab to the query
        $stmt = $pdo->prepare("SELECT NIK, no_kpab, Nama_lengkap AS NAMA, Departemen AS DEPARTEMEN, Alamat_Rumah AS ALAMAT, No_ponsel AS NO_PONSEL, date_join AS TGL_MASUK, SIMPANAN_wajib, SIMPANAN_pokok, status FROM db_anggotakpab WHERE NIK = ?");
        $stmt->execute([$nik_search]);
        $employee = $stmt->fetch();

        if ($employee) {
            $join_date_obj = parse_db_date($employee['TGL_MASUK']);
            $principal_saving = clean_currency($employee['SIMPANAN_pokok'] ?? 50000);

            // [MODIFIKASI] Periode Potongan Dinamis
            $current_period_start = date('Y-m-01');
            $current_period_end = date('Y-m-t');

            // Fetch electronic debts
            $stmt_elec = $pdo->prepare("SELECT JENIS_BARANG, TOTAL_HUTANG, SISA_BULAN, ANGSURAN_PERBULAN FROM db_hutangelectronik WHERE NIK = ? AND SISA_BULAN > 0");
            $stmt_elec->execute([$nik_search]);
            foreach ($stmt_elec->fetchAll() as $debt) {
                $angsuran_perbulan = clean_currency($debt['ANGSURAN_PERBULAN']);
                $sisa_bulan = (int)$debt['SISA_BULAN'];
                $harga_barang = clean_currency($debt['TOTAL_HUTANG']); // [BARU] Mengambil data harga
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
            
            // [MODIFIKASI] Fetch sembako debts (total keseluruhan dan periode ini)
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
            
            // [BARU] Hitung hutang sembako untuk periode ini saja
            $stmt_sem_period = $pdo->prepare("SELECT SUM(jumlah) FROM db_hutangsembako WHERE NIK = ? AND nama_barang != 'HITUNGAN POKOK' AND tanggal_ambil_barang BETWEEN ? AND ?");
            $stmt_sem_period->execute([$nik_search, $current_period_start, $current_period_end]);
            $total_sembako_debt_this_period = $stmt_sem_period->fetchColumn() ?: 0;


            // Fetch accumulated balance history
            $stmt_selisih = $pdo->prepare("SELECT periode_bulan, jumlah_selisih FROM riwayat_selisih WHERE nik = ? AND status = 'terakumulasi' ORDER BY periode_bulan ASC");
            $stmt_selisih->execute([$nik_search]);
            $riwayat_selisih = $stmt_selisih->fetchAll();
            foreach($riwayat_selisih as $selisih) {
                $total_akumulasi_selisih += $selisih['jumlah_selisih'];
            }
            
            // [MODIFIKASI] Hitung potongan pokok hanya jika anggota baru di bulan ini
            $potongan_pokok = 0;
            if ($join_date_obj && $join_date_obj->format('Y-m') === date('Y-m')) {
                $stmt_pokok = $pdo->prepare("SELECT jumlah FROM db_hutangsembako WHERE NIK = ? AND nama_barang = 'HITUNGAN POKOK'");
                $stmt_pokok->execute([$nik_search]);
                $potongan_pokok = $stmt_pokok->fetchColumn() ?: 0;
            }

            // Calculate total debt and savings
            $total_overall_debt = $total_electronic_debt + $total_sembako_debt_overall;
            
            if ($join_date_obj && isset($employee['SIMPANAN_wajib'])) {
                $interval = $join_date_obj->diff(new DateTime());
                $months_joined = ($interval->y * 12) + $interval->m + 1;
                $monthly_saving_amount = clean_currency($employee['SIMPANAN_wajib']);
                $mandatory_savings = $months_joined * $monthly_saving_amount;
            }
            $total_savings = $mandatory_savings + $principal_saving;

            // [MODIFIKASI] Calculate estimated total deduction based on new rules
            $total_estimasi_potongan = $total_angsuran_elektronik + $total_sembako_debt_this_period + $total_akumulasi_selisih + $potongan_wajib_preview + $potongan_pokok;
            $potongan_ditampilkan = min($total_estimasi_potongan, $limit_potongan);
            $kelebihan_potongan = $total_estimasi_potongan - $potongan_ditampilkan;
            
            // [MODIFIKASI] Fetch all deduction history (pending and processed)
            $stmt_semua_riwayat = $pdo->prepare("SELECT * FROM laporan_potongan_gaji WHERE nik = ? ORDER BY periode_bulan DESC");
            $stmt_semua_riwayat->execute([$employee['NIK']]);
            $semua_riwayat_potongan = $stmt_semua_riwayat->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $error_message = "Karyawan dengan NIK '{$nik_search}' tidak ditemukan.";
            $employee = null;
        }
    } catch (\PDOException $e) {
        $error_message = "Kesalahan Pengambilan Data: " . $e->getMessage();
    }
}


// --- [START] Personalia & Dashboard Notification Feature ---
$personalia_notifications = [];
$dashboard_notifications = [];

try {
    $query_personalia = "SELECT n.id, n.pesan, n.tanggal, n.dibaca, a.nik AS anggota_nik, a.Nama_lengkap AS anggota_nama, a.Departemen AS anggota_departemen FROM notifikasi n LEFT JOIN db_anggotakpab a ON a.NIK = SUBSTRING_INDEX(SUBSTRING_INDEX(n.pesan, 'NIK: ', -1), ')', 1) ORDER BY n.tanggal DESC";
    $stmt_personalia = $pdo->prepare($query_personalia);
    $stmt_personalia->execute();
    $results_personalia = $stmt_personalia->fetchAll();

    foreach ($results_personalia as $row) {
        $nik_from_message = preg_match('/NIK:?\s*([^\s\)]+)/', $row['pesan'], $matches) ? $matches[1] : null;
        $personalia_notifications[] = ['id' => $row['id'], 'pesan' => $row['pesan'], 'tanggal' => $row['tanggal'], 'dibaca' => $row['dibaca'], 'nik' => $row['anggota_nik'] ?? $nik_from_message, 'nama' => $row['anggota_nama'] ?? 'N/A', 'departemen' => $row['anggota_departemen'] ?? 'N/A'];
    }
    $unread_personalia_count = count(array_filter($personalia_notifications, function($item) { return $item['dibaca'] == 0; }));


    $query_dashboard = "
        SELECT n.id, n.pesan, n.tanggal, n.dibaca, a.Nama_lengkap, a.NIK, a.Departemen
        FROM notifikasi n
        LEFT JOIN db_anggotakpab a ON a.NIK = SUBSTRING_INDEX(SUBSTRING_INDEX(n.pesan, 'NIK: ', -1), ')', 1)
        WHERE n.dibaca = 0
        ORDER BY n.tanggal DESC LIMIT 5
    ";
    $stmt_dashboard = $pdo->query($query_dashboard);
    $dashboard_notifications = $stmt_dashboard->fetchAll();

} catch (\PDOException $e) {
    $error_message = "Gagal mengambil notifikasi: " . $e->getMessage();
}

// [MODIFIKASI] Dynamic Period Label Logic based on 6th to 5th cycle
$today = new DateTime();
$cutoff_day = 6; // The new period starts on the 6th.

// Explanation: The period is determined based on the execution date (the 6th).
// If today is before the 6th (e.g., July 5th), we are still in the previous month's period (June period),
// as the deduction for June has not been processed yet.
if ((int)$today->format('j') < $cutoff_day) {
    // We are in the period of the previous month.
    $period_date = new DateTime('first day of last month');
} else {
    // On or after the 6th, we are in the new month's period.
    $period_date = new DateTime('first day of this month');
}

// Check if a payment has already been processed for the determined period.
// This is crucial for automatically showing the *next* period after execution.
$is_processed = false;
if (isset($employee['NIK'])) {
    try {
        $check_processed_stmt = $pdo->prepare("SELECT COUNT(*) FROM laporan_potongan_gaji WHERE nik = ? AND periode_bulan = ? AND status = 'processed'");
        $check_processed_stmt->execute([$employee['NIK'], $period_date->format('Y-m')]);
        if ($check_processed_stmt->fetchColumn() > 0) {
            $is_processed = true;
        }
    } catch (\PDOException $e) {
        error_log("Could not check processed status: " . $e->getMessage());
    }
}

// If the period's report has been processed, advance the label to the next month.
if ($is_processed) {
    $period_date->modify('+1 month');
}

$periode_potongan_label = $period_date->format('F Y');

$indonesian_months = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$periode_potongan_label = str_replace(array_keys($indonesian_months), array_values($indonesian_months), $periode_potongan_label);


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPAB Panel Superuser</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- [NEW] jsPDF Libraries for Pengajuan KPAB feature -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root {
            --primary: #2e7d32; --primary-dark: #1b5e20; --primary-light: #4caf50;
            --secondary: #ffd600; --secondary-dark: #ffab00; --light: #f5f5f5;
            --dark: #263238; --success: #4caf50; --warning: #ff9800;
            --danger: #f44336; --info: #0288d1; --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--light); color: var(--dark); }
        .header-gradient { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .card { background: white; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 20px; border-radius: 10px; font-weight: 600; transition: all 0.3s ease; cursor: pointer; border: none; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15); }
        .btn:disabled { background: #d1d5db; color: #6b7280; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; }
        .btn-secondary { background-color: #e2e8f0; color: #1e293b; }
        .btn-secondary:hover { background-color: #cbd5e1; }
        .btn-danger { background: linear-gradient(135deg, var(--danger) 0%, #d32f2f 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, var(--success) 0%, #388e3c 100%); color: white; }
        .btn-info { background: linear-gradient(135deg, var(--info) 0%, #01579b 100%); color: white; }
        .btn-sm { padding: 4px 10px; font-size: 0.875rem; border-radius: 0.5rem; }
        .input-field { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem; font-size: 1rem; transition: all 0.3s ease; width: 100%; }
        .input-field:focus { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2); outline: none; }
        .table-container { border-radius: 12px; overflow: hidden; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05); }
        .table-container th { background-color: var(--primary); color: white; padding: 12px 15px; text-align: left; font-weight: 600; font-size: 0.875rem; }
        .table-container td { padding: 12px 15px; border-bottom: 1px solid #eeeeee; font-size: 0.875rem; }
        .table-container tr:hover { background-color: #f5f5f5; }
        .table-container tr.new-data-row { background-color: #fffbe6; font-weight: 500;}
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background: white; border-radius: 1rem; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); width: 90%; transform: translateY(20px); transition: all 0.4s ease; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500; }
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-inactive { background-color: #fee2e2; color: #991b1b; }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-processed { background-color: #dcfce7; color: #166534; }
        .status-approved { background-color: #dcfce7; color: #166534; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-new { background-color: #ffcdd2; color: #c62828; }
        .badge-read { background-color: #e0e0e0; color: #616161; }
        .badge-type { padding: 2px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; margin-right: 8px; }
        .badge-personalia { background-color: #e3f2fd; color: #1565c0; }
        .badge-kpab { background-color: #fff3e0; color: #e65100; }
        .badge-elektronik { background-color: #ede7f6; color: #4527a0; }
        .badge-sembako { background-color: #e8f5e9; color: #2e7d32; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .sidebar { transition: background-color: 0.3s ease; }
        .sidebar-button { display: flex; align-items: center; width: 100%; padding: 1rem; font-weight: 600; color: #4a5568; border-left: 4px solid transparent; transition: all 0.3s ease; position: relative; }
        .sidebar-button:hover { background-color: #e8f5e9; color: var(--primary-dark); }
        .sidebar-button.active { background-color: #dcfce7; color: var(--primary-dark); border-left-color: var(--primary); }
        .notification-dot { height: 8px; width: 8px; background-color: #ef4444; border-radius: 50%; position: absolute; right: 1rem; top: 1rem; }
        .pdf-preview-container { background: #525659; padding: 20px; height: 80vh; display: flex; justify-content: center; align-items: center; }
        .pdf-preview-iframe { width: 100%; height: 100%; border: 1px solid #ccc; box-shadow: 0 0 15px rgba(0,0,0,0.5); }
        .step-container { display: none; }
        .step-container.active { display: block; }
        /* [NEW] Excel-like table style */
        .excel-table { border-collapse: collapse; width: 100%; font-size: 0.8rem; table-layout: auto; }
        .excel-table th, .excel-table td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; white-space: nowrap; }
        .excel-table thead th { background-color: #f2f2f2; font-weight: bold; text-align: center; vertical-align: middle; }
        .excel-table .sub-header th { background-color: #f2f2f2; }
        .excel-table .currency { text-align: right; }
        .excel-table .total-row td { font-weight: bold; background-color: #f8f9fa; }
        .pengajuan-tab-btn, .db-anggota-tab-btn {
            border-bottom: 2px solid transparent;
            padding: 1rem 0.25rem;
            margin-right: 2rem;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.2s;
        }
        .pengajuan-tab-btn:hover, .db-anggota-tab-btn:hover {
            color: var(--primary);
            border-bottom-color: var(--primary-light);
        }
        .pengajuan-tab-btn.active, .db-anggota-tab-btn.active {
            color: var(--primary-dark);
            border-bottom-color: var(--primary);
        }
        .pengajuan-tab-content, .db-anggota-tab-content { display: none; }
        .pengajuan-tab-content.active, .db-anggota-tab-content.active { display: block; animation: fadeIn 0.5s; }
        
        /* [NEW] Blinking animation for notification badge */
        @keyframes pulse {
          0%, 100% {
            opacity: 1;
            transform: scale(1);
          }
          50% {
            opacity: 0.7;
            transform: scale(1.1);
          }
        }
        .blinking-badge {
          animation: pulse 1.5s infinite;
        }

    </style>
</head>
<body>
    <audio id="notification-sound" src="sound/notification.mp3" preload="auto"></audio>
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white shadow-lg sidebar flex-shrink-0 flex flex-col">
            <div>
                <!-- [MODIFIED] Header with Logo -->
                <div class="header-gradient p-5 flex items-center gap-3">
                    <div class="bg-white p-1 rounded-lg">
                        <img src="picture/logo.png" alt="Logo KPAB" class="h-10 w-10 object-contain">
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-white">KPAB Panel</h1>
                        <p class="text-sm text-green-200">Superuser</p>
                    </div>
                </div>
                <nav class="mt-6 flex flex-col gap-2">
                    <button class="sidebar-button <?= $active_tab === 'dashboard' ? 'active' : '' ?>" onclick="switchTab('dashboard')">
                        <i class="fas fa-tachometer-alt fa-fw mr-3"></i>Dashboard
                    </button>
                    
                    <!-- [MODIFIED] Cari Karyawan moved here -->
                    <button class="sidebar-button <?= $active_tab === 'search' ? 'active' : '' ?>" onclick="switchTab('search')">
                        <i class="fas fa-search fa-fw mr-3"></i>Cari Karyawan
                    </button>

                    <!-- [MODIFIED] Pengajuan KPAB Dropdown Menu with Blinking Notification -->
                    <div x-data="{ open: <?= strpos($active_tab, 'pengajuan_') === 0 ? 'true' : 'false' ?> }">
                        <button @click="open = !open" class="sidebar-button w-full flex justify-between items-center">
                            <span class="flex items-center">
                                <i class="fas fa-user-check fa-fw mr-3"></i>Pengajuan KPAB
                            </span>
                            <div class="flex items-center">
                                <?php if ($unread_pengajuan_count > 0): ?>
                                    <span class="mr-2 inline-flex items-center justify-center h-6 w-6 text-xs font-bold text-red-100 bg-red-600 rounded-full blinking-badge"><?= $unread_pengajuan_count ?></span>
                                <?php endif; ?>
                                <i class="fas transition-transform duration-300" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                            </div>
                        </button>
                        <div x-show="open" x-transition class="pl-4 transition-all duration-300">
                            <a href="?tab=pengajuan_anggota_baru" class="sidebar-button <?= $active_tab === 'pengajuan_anggota_baru' ? 'active' : '' ?>">
                                <i class="fas fa-user-plus fa-fw mr-3"></i>Anggota Baru
                            </a>
                        </div>
                    </div>

                    <!-- [MODIFIED] Database Anggota Dropdown Menu -->
                    <div x-data="{ open: <?= in_array($active_tab, ['database_anggota', 'payroll_database', 'tambah_anggota']) ? 'true' : 'false' ?> }">
                        <button @click="open = !open" class="sidebar-button w-full flex justify-between items-center">
                            <span class="flex items-center">
                                <i class="fas fa-database fa-fw mr-3"></i>Database Anggota
                            </span>
                            <i class="fas transition-transform duration-300" :class="open ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                        </button>
                        <div x-show="open" x-transition class="pl-4 transition-all duration-300">
                            <a href="?tab=database_anggota" class="sidebar-button <?= $active_tab === 'database_anggota' ? 'active' : '' ?>">
                                <i class="fas fa-table fa-fw mr-3"></i>Data Anggota
                            </a>
                            <a href="#" onclick="openModal('addMemberFlowModal')" class="sidebar-button">
                                <i class="fas fa-user-plus fa-fw mr-3"></i>Tambah Anggota Baru
                            </a>
                             <a href="?tab=payroll_database" class="sidebar-button <?= $active_tab === 'payroll_database' ? 'active' : '' ?>">
                                <i class="fas fa-file-invoice-dollar fa-fw mr-3"></i>Payroll Database
                            </a>
                        </div>
                    </div>
                    
                    <button class="sidebar-button <?= $active_tab === 'manajemen_hutang' ? 'active' : '' ?>" onclick="switchTab('manajemen_hutang')">
                        <i class="fas fa-hand-holding-usd fa-fw mr-3"></i>Management Hutang
                    </button>
                    <button class="sidebar-button <?= $active_tab === 'personalia' ? 'active' : '' ?>" onclick="switchTab('personalia')">
                        <i class="fas fa-bell fa-fw mr-3"></i>Notifikasi Personalia
                        <?php if ($unread_personalia_count > 0): ?>
                            <span id="sidebar-notif-badge" class="ml-auto inline-flex items-center justify-center h-6 w-6 text-xs font-bold text-red-100 bg-red-600 rounded-full"><?= $unread_personalia_count ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="sidebar-button <?= $active_tab === 'account_management' ? 'active' : '' ?>" onclick="switchTab('account_management')">
                        <i class="fas fa-users-cog fa-fw mr-3"></i>Account Management
                    </button>
                </nav>
            </div>
            <div class="mt-auto p-4 space-y-2">
                 <div class="text-center text-sm text-gray-600">Login sebagai: <br><strong class="font-bold"><?= htmlspecialchars($_SESSION['username']) ?></strong></div>
                 <a href="ganti_password.php" class="sidebar-button !text-yellow-700 !border-yellow-500 hover:!bg-yellow-100">
                    <i class="fas fa-key fa-fw mr-3"></i>Ganti Password
                </a>
                <a href="logout.php" class="sidebar-button !text-red-700 !border-red-500 hover:!bg-red-100">
                    <i class="fas fa-sign-out-alt fa-fw mr-3"></i>Logout
                </a>
                <div class="p-4 mt-auto text-center text-xs text-gray-500">
                    <p>Koperasi Pelita Abadi &copy; <?= date('Y') ?></p>
                    <p>JI. Raya Serpong Km. 7, Pakualam, Serpong Utara, Tangerang Selatan, Banten</p>
                </div>
            </div>
        </aside>

        <main class="flex-grow p-6">
            <div class="max-w-full mx-auto">
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert"><p><?= htmlspecialchars($error_message) ?></p></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-6" role="alert"><p><?= htmlspecialchars($success_message) ?></p></div>
                <?php endif; ?>

                <div id="tab-dashboard" class="tab-panel <?= $active_tab === 'dashboard' ? 'active' : '' ?>">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">Dashboard Utama</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
                        <div class="card p-6 flex items-center gap-4 bg-green-50 border-l-4 border-green-500">
                            <i class="fas fa-users fa-3x text-green-600"></i>
                            <div>
                                <p class="text-sm text-gray-500">Anggota Aktif</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['active_members'] ?></p>
                            </div>
                        </div>
                        <div class="card p-6 flex items-center gap-4 bg-red-50 border-l-4 border-red-500">
                            <i class="fas fa-user-slash fa-3x text-red-600"></i>
                            <div>
                                <p class="text-sm text-gray-500">Anggota Non-Aktif</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['inactive_members'] ?></p>
                            </div>
                        </div>
                        <div class="card p-6 flex items-center gap-4 bg-blue-50 border-l-4 border-blue-500">
                            <i class="fas fa-user-plus fa-3x text-blue-600"></i>
                            <div>
                                <p class="text-sm text-gray-500">Anggota Baru (30 Hari)</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['new_members'] ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- [MODIFIKASI] Penataan Ulang Kontainer Dashboard -->
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="card lg:col-span-1">
                                <div class="p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-bell"></i>Notifikasi Terbaru</h3>
                                    <div class="space-y-2 max-h-96 overflow-y-auto pr-2">
                                        <?php if (empty($combined_notifications)): ?>
                                            <p class="text-center text-gray-500 py-6">Tidak ada notifikasi baru.</p>
                                        <?php else: ?>
                                            <?php foreach ($combined_notifications as $notif): 
                                                $type_class = 'badge-personalia';
                                                if ($notif['type'] == 'DAFTAR KPAB') $type_class = 'badge-kpab';
                                                if ($notif['type'] == 'CICILAN ELEKTRONIK') $type_class = 'badge-elektronik';
                                                if ($notif['type'] == 'CICILAN SEMBAKO') $type_class = 'badge-sembako';
                                            ?>
                                            <a href="<?= $notif['link'] ?>" class="block p-3 rounded-lg hover:bg-gray-50 border border-gray-100">
                                                <div class="flex justify-between items-start">
                                                    <div class="flex-grow">
                                                        <div class="flex items-center mb-1">
                                                            <span class="badge-type <?= $type_class ?>"><?= htmlspecialchars($notif['type']) ?></span>
                                                            <?php if ($notif['is_new']): ?>
                                                                <span class="badge badge-new">BARU</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-sm text-gray-700"><?= htmlspecialchars($notif['pesan']) ?></p>
                                                    </div>
                                                    <span class="text-xs text-gray-400 flex-shrink-0 ml-2"><?= date('d M, H:i', strtotime($notif['tanggal'])) ?></span>
                                                </div>
                                            </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card lg:col-span-2">
                                <div class="p-4">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-history"></i>Riwayat Aktivitas</h3>
                                    <div class="overflow-x-auto">
                                        <!-- [MODIFIKASI] Tabel gaya Excel -->
                                        <table class="min-w-full border-collapse border border-slate-400">
                                            <thead>
                                                <tr class="bg-slate-50">
                                                    <th class="border border-slate-300 px-4 py-2 text-left font-semibold">Pengguna</th>
                                                    <th class="border border-slate-300 px-4 py-2 text-left font-semibold">Deskripsi</th>
                                                    <th class="border border-slate-300 px-4 py-2 text-left font-semibold">Waktu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($riwayat_aktivitas_terbaru)): ?>
                                                    <tr>
                                                        <td colspan="3" class="border border-slate-300 p-4 text-center text-gray-500">Belum ada aktivitas tercatat.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($riwayat_aktivitas_terbaru as $aktivitas): ?>
                                                    <tr>
                                                        <td class="border border-slate-300 px-4 py-2"><?= htmlspecialchars($aktivitas['username']) ?></td>
                                                        <td class="border border-slate-300 px-4 py-2"><?= htmlspecialchars($aktivitas['description']) ?></td>
                                                        <td class="border border-slate-300 px-4 py-2 text-sm text-gray-600"><?= date('d M Y, H:i', strtotime($aktivitas['timestamp'])) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kontainer Manajemen Selisih dipindahkan ke sini -->
                        <div class="card">
                            <div class="p-4">
                                <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                    <i class="fas fa-exclamation-triangle text-orange-500"></i>Manajemen Selisih Potongan (Periode <?= htmlspecialchars(date('F Y')) ?>)
                                </h3>
                                <p class="text-sm text-gray-500 mb-4">Anggota berikut diproyeksikan memiliki selisih (kekurangan bayar) karena total potongan melebihi limit payroll.</p>
                                <div class="overflow-x-auto">
                                    <!-- [MODIFIKASI] Tabel gaya Excel -->
                                    <table class="min-w-full border-collapse border border-slate-400">
                                        <thead>
                                            <tr class="bg-slate-50">
                                                <th class="border border-slate-300 px-4 py-2 text-left font-semibold">NIK</th>
                                                <th class="border border-slate-300 px-4 py-2 text-left font-semibold">Nama Lengkap</th>
                                                <th class="border border-slate-300 px-4 py-2 text-left font-semibold">Jumlah Selisih</th>
                                                <th class="border border-slate-300 px-4 py-2 text-center font-semibold">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($anggota_dengan_selisih)): ?>
                                                <tr><td colspan="4" class="border border-slate-300 text-center p-6 text-gray-500">
                                                    <i class="fas fa-check-circle text-green-500 fa-2x mb-2"></i><br>
                                                    Tidak ada anggota dengan proyeksi selisih untuk periode ini.
                                                </td></tr>
                                            <?php else: ?>
                                                <?php foreach($anggota_dengan_selisih as $selisih_data): ?>
                                                <tr>
                                                    <td class="border border-slate-300 px-4 py-2 font-medium"><?= htmlspecialchars($selisih_data['nik']) ?></td>
                                                    <td class="border border-slate-300 px-4 py-2"><?= htmlspecialchars($selisih_data['nama_lengkap']) ?></td>
                                                    <td class="border border-slate-300 px-4 py-2 font-bold text-red-600">Rp <?= number_format($selisih_data['selisih'], 0, ',', '.') ?></td>
                                                    <td class="border border-slate-300 px-4 py-2 text-center">
                                                        <button onclick='openSelisihModal(<?= json_encode($selisih_data, ENT_QUOTES) ?>)' class="btn btn-info btn-sm">
                                                            <i class="fas fa-cash-register mr-1"></i> Proses
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- [MODIFIED] Pengajuan Anggota Baru Page with Tabs -->
                <div id="tab-pengajuan_anggota_baru" class="tab-panel <?= $active_tab === 'pengajuan_anggota_baru' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-gray-800 mb-4">Manajemen Pengajuan Anggota Baru</h3>
                            
                            <!-- Tab Buttons -->
                            <div class="border-b border-gray-200 mb-6">
                                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                    <button onclick="switchPengajuanSubTab('pending')" id="btn-pengajuan-pending" class="pengajuan-tab-btn active">
                                        Pengajuan Baru <span class="ml-2 bg-yellow-200 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full"><?= count($pending_applications) ?></span>
                                    </button>
                                    <button onclick="switchPengajuanSubTab('approved')" id="btn-pengajuan-approved" class="pengajuan-tab-btn">
                                        Disetujui
                                    </button>
                                    <button onclick="switchPengajuanSubTab('rejected')" id="btn-pengajuan-rejected" class="pengajuan-tab-btn">
                                        Ditolak
                                    </button>
                                </nav>
                            </div>

                            <!-- Tab Content: Pengajuan Baru -->
                            <div id="content-pengajuan-pending" class="pengajuan-tab-content active">
                                <div class="overflow-x-auto table-container">
                                    <table class="min-w-full" id="tabel-pengajuan">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Tanggal</th>
                                                <th>NIK</th>
                                                <th>Nama Lengkap</th>
                                                <th>No. Ponsel</th>
                                                <th>Departemen</th>
                                                <th class="text-center">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php if (empty($pending_applications)): ?>
                                                <tr><td colspan="7" class="text-center p-6 text-gray-500">Tidak ada pengajuan baru.</td></tr>
                                            <?php else: ?>
                                                <?php foreach($pending_applications as $p): ?>
                                                <tr>
                                                    <td class="text-center"><span class="status-badge status-pending">Pending</span></td>
                                                    <td><?= htmlspecialchars(date('d M Y', strtotime($p['tanggal_pengajuan']))) ?></td>
                                                    <td class="font-medium"><?= htmlspecialchars($p['nik']) ?></td>
                                                    <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($p['no_ponsel']) ?></td>
                                                    <td><?= htmlspecialchars($p['departemen']) ?></td>
                                                    <td class="text-center space-x-1">
                                                        <button onclick='openPdfPopup(<?= $p['id'] ?>)' class="btn btn-info btn-sm" title="Lihat Formulir PDF"><i class="fas fa-file-pdf"></i></button>
                                                        <button onclick="showApprovalConfirmation(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_lengkap'], ENT_QUOTES) ?>')" class="btn btn-success btn-sm" title="Setujui Pengajuan"><i class="fas fa-check"></i></button>
                                                        <button onclick="processRejection(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_lengkap'], ENT_QUOTES) ?>')" class="btn btn-danger btn-sm" title="Tolak Pengajuan"><i class="fas fa-times"></i></button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab Content: Disetujui -->
                            <div id="content-pengajuan-approved" class="pengajuan-tab-content">
                                <form method="GET" class="mb-4 p-4 bg-gray-50 rounded-lg border">
                                    <input type="hidden" name="tab" value="pengajuan_anggota_baru">
                                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Tanggal Gabung</label>
                                            <input type="date" name="search_approved_date" class="input-field mt-1" value="<?= htmlspecialchars($search_approved_date) ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">NIK</label>
                                            <input type="text" name="search_approved_nik" class="input-field mt-1" placeholder="Cari NIK..." value="<?= htmlspecialchars($search_approved_nik) ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">No. KPAB</label>
                                            <input type="text" name="search_approved_kpab" class="input-field mt-1" placeholder="Cari No. KPAB..." value="<?= htmlspecialchars($search_approved_kpab) ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Nama</label>
                                            <input type="text" name="search_approved_nama" class="input-field mt-1" placeholder="Cari Nama..." value="<?= htmlspecialchars($search_approved_nama) ?>">
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="btn btn-primary w-full"><i class="fas fa-filter mr-2"></i>Filter</button>
                                            <a href="?tab=pengajuan_anggota_baru" class="btn btn-secondary w-full" title="Reset Filter"><i class="fas fa-redo"></i></a>
                                        </div>
                                    </div>
                                </form>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full excel-table">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tgl Gabung</th>
                                                <th>No. KPAB</th>
                                                <th>NIK</th>
                                                <th>Nama Lengkap</th>
                                                <th>Departemen</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($approved_members_for_tab)): ?>
                                                <tr><td colspan="7" class="text-center py-4">Tidak ada data anggota yang disetujui.</td></tr>
                                            <?php else: ?>
                                                <?php foreach($approved_members_for_tab as $i => $member): ?>
                                                <tr>
                                                    <td class="text-center"><?= $i + 1 ?></td>
                                                    <td class="text-center"><?= htmlspecialchars(date('d-m-Y', strtotime($member['date_join']))) ?></td>
                                                    <td><?= htmlspecialchars($member['no_kpab']) ?></td>
                                                    <td><?= htmlspecialchars($member['NIK']) ?></td>
                                                    <td><?= htmlspecialchars($member['Nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($member['Departemen']) ?></td>
                                                    <td class="text-center">
                                                        <a href="?tab=search&nik=<?= htmlspecialchars($member['NIK']) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> Detail</a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab Content: Ditolak -->
                            <div id="content-pengajuan-rejected" class="pengajuan-tab-content">
                                <div class="overflow-x-auto table-container">
                                    <table class="min-w-full">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Tanggal Pengajuan</th>
                                                <th>NIK</th>
                                                <th>Nama Lengkap</th>
                                                <th>Departemen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($rejected_applications)): ?>
                                                <tr><td colspan="5" class="text-center p-6 text-gray-500">Tidak ada pengajuan yang ditolak.</td></tr>
                                            <?php else: ?>
                                                <?php foreach($rejected_applications as $p): ?>
                                                <tr>
                                                    <td class="text-center"><span class="status-badge status-rejected">Ditolak</span></td>
                                                    <td><?= htmlspecialchars(date('d M Y', strtotime($p['tanggal_pengajuan']))) ?></td>
                                                    <td><?= htmlspecialchars($p['nik']) ?></td>
                                                    <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($p['departemen']) ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- [MODIFIKASI] Halaman Payroll Database -->
                <div id="tab-payroll_database" class="tab-panel <?= $active_tab === 'payroll_database' ? 'active' : '' ?>">
                    <div class="card p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-file-invoice-dollar mr-2"></i>Payroll Database</h3>
                                <p class="text-gray-500">Tampilan data berdasarkan perhitungan untuk periode berjalan.</p>
                            </div>
                            <button class="btn btn-success"><i class="fas fa-file-excel mr-2"></i>Export ke Excel</button>
                        </div>
                        
                        <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                            <form method="GET">
                                <input type="hidden" name="tab" value="payroll_database">
                                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                                    <div>
                                        <label for="periode_payroll" class="font-bold text-gray-700">Periode Bulan:</label>
                                        <input type="month" id="periode_payroll" name="periode_payroll" class="input-field mt-1" value="<?= htmlspecialchars($periode_payroll_bulan_ini) ?>">
                                    </div>
                                    <div class="bg-blue-100 p-3 rounded-lg text-center">
                                        <p class="text-sm text-blue-800 font-semibold">Total Simpanan Pokok</p>
                                        <p class="text-lg font-bold text-blue-900">Rp <?= number_format($payroll_summary['total_simpanan_pokok'], 0, ',', '.') ?></p>
                                    </div>
                                    <div class="bg-green-100 p-3 rounded-lg text-center">
                                        <p class="text-sm text-green-800 font-semibold">Total Simpanan Wajib</p>
                                        <p class="text-lg font-bold text-green-900">Rp <?= number_format($payroll_summary['total_simpanan_wajib'], 0, ',', '.') ?></p>
                                    </div>
                                    <div class="bg-yellow-100 p-3 rounded-lg text-center">
                                        <p class="text-sm text-yellow-800 font-semibold">Total Hutang Koperasi</p>
                                        <p class="text-lg font-bold text-yellow-900">Rp <?= number_format($payroll_summary['total_hutang'], 0, ',', '.') ?></p>
                                    </div>
                                    <div class="bg-red-100 p-3 rounded-lg text-center">
                                        <p class="text-sm text-red-800 font-semibold">Total Selisih</p>
                                        <p class="text-lg font-bold text-red-900">Rp <?= number_format($payroll_summary['total_selisih'], 0, ',', '.') ?></p>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="excel-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">NO</th>
                                        <th rowspan="2">TGL MASUK ANGGOTA</th>
                                        <th rowspan="2">No. K.PAB</th>
                                        <th rowspan="2">NIK</th>
                                        <th rowspan="2">NAMA</th>
                                        <th rowspan="2">DEPARTEMEN</th>
                                        <th colspan="3">RINCIAN POTONGAN KOPERASI</th>
                                        <th rowspan="2">POTONGAN KOPERASI</th>
                                        <th rowspan="2">POTONGAN PAYROLL (Max.950rb)</th>
                                        <th rowspan="2">SELISIH</th>
                                    </tr>
                                    <tr class="sub-header">
                                        <th>SIMPANAN POKOK</th>
                                        <th>SIMPANAN WAJIB</th>
                                        <th>HUTANG KOPERASI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payroll_data)): ?>
                                        <tr><td colspan="12" class="text-center py-4">Tidak ada data untuk ditampilkan.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($payroll_data as $index => $row): ?>
                                        <tr>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['date_join']))) ?></td>
                                            <td><?= htmlspecialchars($row['no_kpab']) ?></td>
                                            <td><?= htmlspecialchars($row['nik']) ?></td>
                                            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($row['departemen']) ?></td>
                                            <td class="currency"><?= $row['simpanan_pokok'] > 0 ? 'Rp ' . number_format($row['simpanan_pokok'], 0, ',', '.') : 'Rp 0' ?></td>
                                            <td class="currency">Rp <?= number_format($row['simpanan_wajib'], 0, ',', '.') ?></td>
                                            <td class="currency">Rp <?= number_format($row['hutang_koperasi'], 0, ',', '.') ?></td>
                                            <td class="currency">Rp <?= number_format($row['total_potongan_koperasi'], 0, ',', '.') ?></td>
                                            <td class="currency font-bold">Rp <?= number_format($row['total_potongan_payroll'], 0, ',', '.') ?></td>
                                            <td class="currency text-red-600"><?= $row['selisih'] > 0 ? 'Rp ' . number_format($row['selisih'], 0, ',', '.') : 'Rp 0' ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="total-row">
                                    <tr>
                                        <td colspan="6" class="text-right font-bold">TOTAL</td>
                                        <td class="currency">Rp <?= number_format($payroll_summary['total_simpanan_pokok'], 0, ',', '.') ?></td>
                                        <td class="currency">Rp <?= number_format($payroll_summary['total_simpanan_wajib'], 0, ',', '.') ?></td>
                                        <td class="currency">Rp <?= number_format($payroll_summary['total_hutang'], 0, ',', '.') ?></td>
                                        <td class="currency">Rp <?= number_format(array_sum(array_column($payroll_data, 'total_potongan_koperasi')), 0, ',', '.') ?></td>
                                        <td class="currency">Rp <?= number_format(array_sum(array_column($payroll_data, 'total_potongan_payroll')), 0, ',', '.') ?></td>
                                        <td class="currency">Rp <?= number_format($payroll_summary['total_selisih'], 0, ',', '.') ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>


                <div id="tab-search" class="tab-panel <?= $active_tab === 'search' ? 'active' : '' ?>">
                     <div class="card p-6 mb-6">
                        <form method="GET" action="" class="space-y-4">
                            <input type="hidden" name="tab" value="search">
                            <div>
                                <label for="nik" class="block text-lg font-medium text-gray-700 mb-2">Cari Data Karyawan</label>
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <input type="text" id="nik" name="nik" class="input-field text-lg" placeholder="Masukkan NIK Karyawan..." value="<?= htmlspecialchars($nik_search) ?>">
                                    <button type="submit" class="btn btn-primary w-full sm:w-auto"><i class="fas fa-search mr-2"></i>Cari Data</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if ($employee && $active_tab === 'search'): ?>
                        <div id="employee-data-section" class="space-y-6">
                            <div class="card overflow-hidden">
                                <div class="bg-gray-50 p-6 border-b border-gray-200">
                                    <div class="flex flex-wrap justify-between items-center gap-4">
                                        <div>
                                            <h2 class="text-2xl font-bold text-gray-900"><?= strtoupper(htmlspecialchars($employee['NAMA'] ?? 'N/A')) ?></h2>
                                            <p class="text-gray-500">NIK: <?= htmlspecialchars($employee['NIK'] ?? 'N/A') ?> | No. KPAB: <?= htmlspecialchars($employee['no_kpab'] ?? 'N/A') ?></p>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span class="status-badge <?= $employee['status'] === 'AKTIF' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($employee['status']) ?></span>
                                            <button onclick="openModal('passwordVerifyModal')" class="btn btn-secondary"><i class="fas fa-edit mr-2"></i>Edit Data</button>
                                            <?php if ($employee['status'] === 'AKTIF'): ?>
                                            <form method="POST" action="" onsubmit="return confirm('Anda yakin ingin menonaktifkan anggota ini?');">
                                                <input type="hidden" name="action" value="deactivate_member">
                                                <input type="hidden" name="nik" value="<?= htmlspecialchars($employee['NIK']) ?>">
                                                <button type="submit" class="btn btn-danger"><i class="fas fa-user-slash mr-2"></i>Non-aktif</button>
                                            </form>
                                            <?php endif; ?>
                                            <button onclick="openModal('pdfPreviewModal')" class="btn btn-secondary"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-6 space-y-6">
                                    <?php
                                        $limit_bulanan = 950000;
                                        $total_cicilan_bulanan = $total_angsuran_elektronik + $total_sembako_debt_this_period;
                                        $sisa_limit_bulanan = $limit_bulanan - $total_cicilan_bulanan;
                                        $persentase_terpakai_bulanan = ($limit_bulanan > 0) ? ($total_cicilan_bulanan / $limit_bulanan) * 100 : 0;
                                        if ($persentase_terpakai_bulanan > 100) $persentase_terpakai_bulanan = 100;

                                        $progress_color_class = 'bg-green-500';
                                        if ($persentase_terpakai_bulanan > 50) $progress_color_class = 'bg-yellow-500';
                                        if ($persentase_terpakai_bulanan > 85 || $sisa_limit_bulanan < 0) $progress_color_class = 'bg-red-500';
                                    ?>
                                    <div class="card border border-gray-200 p-5 rounded-xl mb-6">
                                        <div class="flex justify-between items-center mb-2">
                                            <h4 class="font-semibold text-gray-800">Limit Cicilan per Bulan</h4>
                                            <?php if ($sisa_limit_bulanan < 0): ?>
                                                <span class="status-badge status-inactive">OVER LIMIT</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-2xl font-bold text-gray-900">Rp <?= number_format($sisa_limit_bulanan, 0, ',', '.') ?></p>
                                        <p class="text-sm text-gray-500 mb-3">Sisa Limit Bulan Ini</p>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="<?= $progress_color_class ?> h-2.5 rounded-full" style="width: <?= $persentase_terpakai_bulanan ?>%"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                                            <span>Terpakai: Rp <?= number_format($total_cicilan_bulanan, 0, ',', '.') ?></span>
                                            <span>Limit: Rp <?= number_format($limit_bulanan, 0, ',', '.') ?></span>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 class="font-semibold text-gray-800 mb-4">Informasi Pribadi & Keuangan</h3>
                                        <dl class="divide-y divide-gray-100">
                                            <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-gray-500">Departemen</dt><dd class="mt-1 text-sm font-medium text-gray-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['DEPARTEMEN'] ?? 'N/A') ?></dd></div>
                                            <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-gray-500">Alamat</dt><dd class="mt-1 text-sm font-medium text-gray-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['ALAMAT'] ?? 'N/A') ?></dd></div>
                                            <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-gray-500">No. Ponsel</dt><dd class="mt-1 text-sm font-medium text-gray-900 sm:col-span-2 sm:mt-0"><?= htmlspecialchars($employee['NO_PONSEL'] ?? 'N/A') ?></dd></div>
                                            <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4"><dt class="text-sm text-gray-500">Tgl. Bergabung</dt><dd class="mt-1 text-sm font-medium text-gray-900 sm:col-span-2 sm:mt-0"><?= $join_date_obj ? $join_date_obj->format('d F Y') : 'N/A' ?></dd></div>
                                        </dl>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <!-- [MODIFIKASI] Kartu Rincian Estimasi Potongan -->
                                        <div class="card border border-gray-200">
                                            <div class="p-5 border-b">
                                                <h3 class="font-semibold text-gray-800">Rincian Estimasi Potongan</h3>
                                                <p class="text-sm text-gray-500">Untuk Periode Potongan: <span class="font-bold text-green-700"><?= $periode_potongan_label ?></span></p>
                                            </div>
                                            <div class="p-5">
                                                <dl class="space-y-3">
                                                    <?php if ($potongan_pokok > 0): ?>
                                                    <div class="flex justify-between items-center text-red-600">
                                                        <dt class="text-sm font-semibold">Simpanan Pokok (Anggota Baru)</dt>
                                                        <dd class="text-sm font-bold">Rp <?= number_format($potongan_pokok, 0, ',', '.') ?></dd>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="flex justify-between items-center"><dt class="text-sm text-gray-600">Angsuran Elektronik</dt><dd class="text-sm font-medium text-gray-900">Rp <?= number_format($total_angsuran_elektronik, 0, ',', '.') ?></dd></div>
                                                    <div class="flex justify-between items-center"><dt class="text-sm text-gray-600">Total Hutang Sembako (Periode Ini)</dt><dd class="text-sm font-medium text-gray-900">Rp <?= number_format($total_sembako_debt_this_period, 0, ',', '.') ?></dd></div>
                                                    <div class="flex justify-between items-center"><dt class="text-sm text-gray-600">Akumulasi Selisih</dt><dd class="text-sm font-medium text-gray-900">Rp <?= number_format($total_akumulasi_selisih, 0, ',', '.') ?></dd></div>
                                                    <div class="flex justify-between items-center"><dt class="text-sm text-gray-600">Simpanan Wajib</dt><dd class="text-sm font-medium text-gray-900">Rp <?= number_format($potongan_wajib_preview, 0, ',', '.') ?></dd></div>
                                                    <div class="border-t pt-3 mt-3 flex justify-between items-center"><dt class="text-base font-bold text-gray-800">Total Estimasi</dt><dd class="text-base font-bold text-gray-900">Rp <?= number_format($total_estimasi_potongan, 0, ',', '.') ?></dd></div>
                                                </dl>
                                            </div>
                                        </div>
                                        <div class="card border border-gray-200">
                                            <div class="p-5 border-b">
                                                <h3 class="font-semibold text-gray-800">Riwayat Akumulasi Selisih</h3>
                                            </div>
                                            <div class="p-5">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="border-b">
                                                            <th class="text-left font-semibold text-gray-600 pb-2">Periode</th>
                                                            <th class="text-right font-semibold text-gray-600 pb-2">Jumlah Selisih</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($riwayat_selisih)): ?>
                                                            <?php foreach($riwayat_selisih as $selisih): ?>
                                                                <tr class="border-b last:border-b-0">
                                                                    <td class="py-2 text-gray-700"><?= htmlspecialchars($selisih['periode_bulan']) ?></td>
                                                                    <td class="py-2 text-right text-gray-900">Rp <?= number_format($selisih['jumlah_selisih'], 0, ',', '.') ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <tr><td colspan="2" class="text-center py-6 text-gray-500">Tidak ada riwayat selisih.</td></tr>
                                                        <?php endif; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="font-bold">
                                                            <td class="pt-3 text-gray-800">Total Akumulasi</td>
                                                            <td class="pt-3 text-right text-gray-900">Rp <?= number_format($total_akumulasi_selisih, 0, ',', '.') ?></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">
                                        <div class="bg-red-50 p-4 rounded-lg">
                                            <p class="text-sm text-red-700">Total Sisa Hutang</p>
                                            <p class="text-2xl font-bold text-red-900">Rp <?= number_format($total_overall_debt, 0, ',', '.') ?></p>
                                        </div>
                                        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                            <p class="text-sm text-yellow-800">Potongan Koperasi</p>
                                            <p class="text-2xl font-bold text-yellow-900">Rp <?= number_format($potongan_ditampilkan, 0, ',', '.') ?></p>
                                            <?php if ($kelebihan_potongan > 0): ?>
                                                <div class="mt-1 text-xs font-semibold text-red-600 bg-red-100 p-1 rounded-md text-center">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    Akan ada selisih: Rp <?= number_format($kelebihan_potongan, 0, ',', '.') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="bg-green-50 p-4 rounded-lg">
                                            <p class="text-sm text-green-700">Total Simpanan</p>
                                            <p class="text-2xl font-bold text-green-900">Rp <?= number_format($total_savings, 0, ',', '.') ?></p>
                                            <p class="text-xs text-green-600">(Pokok: Rp <?= number_format($principal_saving, 0, ',', '.') ?> + Wajib: Rp <?= number_format($mandatory_savings, 0, ',', '.') ?>)</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="card">
                                    <div class="p-6">
                                        <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-laptop-house mr-2 text-blue-500"></i>Detail Hutang Elektronik</h3>
                                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 flex justify-between items-center">
                                            <p class="font-bold text-blue-800">Total Sisa Hutang Elektronik</p>
                                            <p class="text-2xl font-bold text-blue-900">Rp <?= number_format($total_electronic_debt, 0, ',', '.') ?></p>
                                        </div>
                                        <?php if (!empty($electronic_debts)): ?>
                                            <div class="overflow-x-auto table-container">
                                                <table class="min-w-full">
                                                    <thead><tr><th>No.</th><th>Nama Barang</th><th>Harga</th><th>Angsuran/Bulan</th><th>Sisa Bulan</th><th>Total Sisa</th></tr></thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($electronic_debts as $index => $debt): ?>
                                                        <tr>
                                                            <td class="px-4 py-4 text-center"><?= $index + 1 ?></td>
                                                            <td class="px-4 py-4 font-medium"><?= htmlspecialchars($debt['nama_barang']) ?></td>
                                                            <td class="currency">Rp <?= number_format($debt['harga_barang'], 0, ',', '.') ?></td>
                                                            <td class="px-4 py-4">Rp <?= number_format($debt['angsuran_perbulan'], 0, ',', '.') ?></td>
                                                            <td class="px-4 py-4 font-bold text-center text-red-600"><?= htmlspecialchars($debt['sisa_angsuran']) ?></td>
                                                            <td class="px-4 py-4 font-bold text-red-700">Rp <?= number_format($debt['total_sisa_hutang_per_item'], 0, ',', '.') ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-center text-gray-500 py-4">Tidak ada data hutang elektronik yang aktif.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="p-6">
                                        <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-shopping-basket mr-2 text-green-500"></i>Detail Seluruh Hutang Sembako</h3>
                                        <div class="bg-green-50 border border-green-100 rounded-xl p-4 mb-6 flex justify-between items-center">
                                            <div>
                                                <p class="font-bold text-green-800">Total Seluruh Hutang Sembako</p>
                                                <p class="text-xs text-green-600 mt-1">(Tidak termasuk Hitungan Pokok)</p>
                                            </div>
                                            <p class="text-2xl font-bold text-green-900">Rp <?= number_format($total_sembako_debt_overall, 0, ',', '.') ?></p>
                                        </div>
                                        <?php if (!empty($sembako_debts)): ?>
                                        <div class="overflow-x-auto table-container">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50"><tr><th>Nama Barang</th><th>Jumlah</th></tr></thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($sembako_debts as $item): ?>
                                                    <tr>
                                                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($item['nama_barang']) ?></td>
                                                        <td class="px-4 py-3 font-bold">Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <?php else: ?>
                                            <p class="text-center text-gray-500 py-4">Tidak ada data hutang sembako yang aktif.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="p-6">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-history mr-2 text-gray-500"></i>Riwayat Potongan Gaji</h3>
                                    <?php if (!empty($semua_riwayat_potongan)): ?>
                                        <div class="overflow-x-auto table-container">
                                            <table class="min-w-full">
                                                <thead>
                                                    <tr>
                                                        <th>Periode</th>
                                                        <th>Tgl. Proses</th>
                                                        <th>Total Potongan</th>
                                                        <th>Status</th>
                                                        <th>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    <?php foreach ($semua_riwayat_potongan as $riwayat): ?>
                                                    <tr>
                                                        <td class="px-4 py-4"><?= htmlspecialchars($riwayat['periode_bulan']) ?></td>
                                                        <td class="px-4 py-4"><?= $riwayat['tanggal_proses'] ? htmlspecialchars(date('d F Y H:i', strtotime($riwayat['tanggal_proses']))) : '-' ?></td>
                                                        <td class="px-4 py-4 font-bold">Rp <?= number_format($riwayat['total_potongan'], 0, ',', '.') ?></td>
                                                        <td class="px-4 py-4">
                                                            <span class="status-badge <?= strtolower($riwayat['status']) === 'pending' ? 'status-pending' : 'status-processed' ?>">
                                                                <?= htmlspecialchars(ucfirst($riwayat['status'])) ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <?php if (strtolower($riwayat['status']) === 'processed'): ?>
                                                                <button onclick='showDetailPotongan(<?= htmlspecialchars(json_encode($riwayat), ENT_QUOTES, 'UTF-8') ?>)' class='btn btn-secondary btn-sm'>
                                                                    <i class="fas fa-eye mr-1"></i> Detail
                                                                </button>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center text-gray-500 py-4">Tidak ada riwayat potongan gaji.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- [MODIFIED] Database Anggota page with Tabs and Excel-like table -->
                <div id="tab-database_anggota" class="tab-panel <?= $active_tab === 'database_anggota' ? 'active' : '' ?>">
                    <div class="card p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">Database Anggota Koperasi</h3>
                        
                        <!-- [NEW] Search Form -->
                        <form method="GET" class="mb-6 p-4 bg-gray-50 rounded-lg border">
                            <input type="hidden" name="tab" value="database_anggota">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">NIK</label>
                                    <input type="text" name="search_nik_db" class="input-field mt-1" placeholder="Cari NIK..." value="<?= htmlspecialchars($_GET['search_nik_db'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Nama</label>
                                    <input type="text" name="search_nama_db" class="input-field mt-1" placeholder="Cari Nama..." value="<?= htmlspecialchars($_GET['search_nama_db'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">No. KPAB</label>
                                    <input type="text" name="search_kpab_db" class="input-field mt-1" placeholder="Cari No. KPAB..." value="<?= htmlspecialchars($_GET['search_kpab_db'] ?? '') ?>">
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-primary w-full"><i class="fas fa-search mr-2"></i>Cari</button>
                                    <a href="?tab=database_anggota" class="btn btn-secondary w-full" title="Reset Pencarian"><i class="fas fa-redo"></i></a>
                                </div>
                            </div>
                        </form>

                        <!-- Tab Buttons -->
                        <div class="border-b border-gray-200 mb-6">
                            <nav class="-mb-px flex" aria-label="Tabs">
                                <button onclick="switchDbAnggotaSubTab('aktif')" id="btn-db-anggota-aktif" class="db-anggota-tab-btn active">
                                    ANGGOTA AKTIF <span class="ml-2 bg-green-200 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full"><?= count($active_members_list) ?></span>
                                </button>
                                <button onclick="switchDbAnggotaSubTab('nonaktif')" id="btn-db-anggota-nonaktif" class="db-anggota-tab-btn">
                                    ANGGOTA NON-AKTIF <span class="ml-2 bg-red-200 text-red-800 text-xs font-semibold px-2.5 py-0.5 rounded-full"><?= count($inactive_members_list) ?></span>
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content: Anggota Aktif -->
                        <div id="content-db-anggota-aktif" class="db-anggota-tab-content active">
                            <div class="overflow-x-auto">
                                <table class="excel-table w-full">
                                    <thead>
                                        <tr>
                                            <th>NO KPAB</th>
                                            <th>NIK</th>
                                            <th>NAMA</th>
                                            <th>DEPARTEMEN</th>
                                            <th>COST CENTER</th>
                                            <th>TOTAL SIMPANAN</th>
                                            <th>CICILAN ELEKTRONIK</th>
                                            <th>CICILAN SEMBAKO</th>
                                            <th>AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($active_members_list)): ?>
                                            <tr><td colspan="9" class="text-center p-4">Tidak ada anggota aktif yang cocok dengan kriteria pencarian.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($active_members_list as $member): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($member['no_kpab']) ?></td>
                                                <td><?= htmlspecialchars($member['NIK']) ?></td>
                                                <td><?= htmlspecialchars($member['Nama_lengkap']) ?></td>
                                                <td><?= htmlspecialchars($member['Departemen']) ?></td>
                                                <td><?= htmlspecialchars($member['cost_center']) ?></td>
                                                <td class="currency">Rp <?= number_format($member['total_simpanan'] ?? 0, 0, ',', '.') ?></td>
                                                <td class="currency">Rp <?= number_format($member['cicilan_elektronik'] ?? 0, 0, ',', '.') ?></td>
                                                <td class="currency">Rp <?= number_format($member['cicilan_sembako'] ?? 0, 0, ',', '.') ?></td>
                                                <td><a href="?tab=search&nik=<?= htmlspecialchars($member['NIK']) ?>" class="btn btn-info btn-sm">DETAIL</a></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Tab Content: Anggota Non-Aktif -->
                        <div id="content-db-anggota-nonaktif" class="db-anggota-tab-content">
                             <div class="overflow-x-auto">
                                <table class="excel-table w-full">
                                    <thead>
                                        <tr>
                                            <th>NO KPAB</th>
                                            <th>NIK</th>
                                            <th>NAMA</th>
                                            <th>DEPARTEMEN</th>
                                            <th>COST CENTER</th>
                                            <th>TOTAL SIMPANAN</th>
                                            <th>CICILAN ELEKTRONIK</th>
                                            <th>CICILAN SEMBAKO</th>
                                            <th>AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($inactive_members_list)): ?>
                                            <tr><td colspan="9" class="text-center p-4">Tidak ada anggota non-aktif yang cocok dengan kriteria pencarian.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($inactive_members_list as $member): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($member['no_kpab']) ?></td>
                                                <td><?= htmlspecialchars($member['NIK']) ?></td>
                                                <td><?= htmlspecialchars($member['Nama_lengkap']) ?></td>
                                                <td><?= htmlspecialchars($member['Departemen']) ?></td>
                                                <td><?= htmlspecialchars($member['cost_center']) ?></td>
                                                <td class="currency">Rp <?= number_format($member['total_simpanan'] ?? 0, 0, ',', '.') ?></td>
                                                <td class="currency">Rp <?= number_format($member['cicilan_elektronik'] ?? 0, 0, ',', '.') ?></td>
                                                <td class="currency">Rp <?= number_format($member['cicilan_sembako'] ?? 0, 0, ',', '.') ?></td>
                                                <td><a href="?tab=search&nik=<?= htmlspecialchars($member['NIK']) ?>" class="btn btn-info btn-sm">DETAIL</a></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="tab-manajemen_hutang" class="tab-panel <?= $active_tab === 'manajemen_hutang' ? 'active' : '' ?>">
                    <div class="card p-6 mb-6">
                        <form method="GET" action="" class="space-y-4">
                            <input type="hidden" name="tab" value="manajemen_hutang">
                            <div>
                                <label for="nik_manajemen" class="block text-lg font-medium text-gray-700 mb-2">Cari Karyawan untuk Transaksi Baru</label>
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <input type="text" id="nik_manajemen" name="nik_manajemen" class="input-field text-lg" placeholder="Masukkan NIK Karyawan..." value="<?= htmlspecialchars($nik_search) ?>">
                                    <button type="submit" class="btn btn-primary w-full sm:w-auto"><i class="fas fa-search mr-2"></i>Cari Karyawan</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <?php if ($employee && $active_tab === 'manajemen_hutang'): ?>
                        <div class="my-6 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                            <h3 class="text-xl font-bold text-indigo-800"><?= strtoupper(htmlspecialchars($employee['NAMA'])) ?></h3>
                            <p class="text-indigo-600">NIK: <?= htmlspecialchars($employee['NIK']) ?> | No. KPAB: <?= htmlspecialchars($employee['no_kpab']) ?></p>
                        </div>

                        <!-- [MODIFIKASI] Indikator Limit Cicilan Ditambahkan di Sini -->
                        <?php
                            $limit_bulanan = 950000;
                            // Pastikan variabel ini sudah dihitung sebelumnya saat pencarian NIK
                            $total_cicilan_bulanan = ($total_angsuran_elektronik ?? 0) + ($total_sembako_debt_this_period ?? 0);
                            $sisa_limit_bulanan = $limit_bulanan - $total_cicilan_bulanan;
                            $persentase_terpakai_bulanan = ($limit_bulanan > 0) ? ($total_cicilan_bulanan / $limit_bulanan) * 100 : 0;
                            if ($persentase_terpakai_bulanan > 100) $persentase_terpakai_bulanan = 100;

                            $progress_color_class = 'bg-green-500';
                            if ($persentase_terpakai_bulanan > 50) $progress_color_class = 'bg-yellow-500';
                            if ($persentase_terpakai_bulanan > 85 || $sisa_limit_bulanan < 0) $progress_color_class = 'bg-red-500';
                        ?>
                        <div id="limit-indicator-manajemen" class="card border border-gray-200 p-5 rounded-xl mb-6">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-semibold text-gray-800">Limit Cicilan per Bulan</h4>
                                <span id="limit-status-badge" class="status-badge <?= $sisa_limit_bulanan < 0 ? 'status-inactive' : 'hidden' ?>"><?= $sisa_limit_bulanan < 0 ? 'OVER LIMIT' : '' ?></span>
                            </div>
                            <p id="sisa-limit-display" class="text-2xl font-bold text-gray-900">Rp <?= number_format($sisa_limit_bulanan, 0, ',', '.') ?></p>
                            <p class="text-sm text-gray-500 mb-3">Sisa Limit Bulan Ini</p>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div id="limit-progress-bar" class="<?= $progress_color_class ?> h-2.5 rounded-full" style="width: <?= number_format($persentase_terpakai_bulanan, 2) ?>%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span id="terpakai-display">Terpakai: Rp <?= number_format($total_cicilan_bulanan, 0, ',', '.') ?></span>
                                <span>Limit: Rp <?= number_format($limit_bulanan, 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <div class="card">
                            <div class="border-b border-gray-200 px-6">
                                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                    <button id="tab-btn-elektronik" onclick="switchHutangTab('elektronik')" class="hutang-tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        <i class="fas fa-mobile-alt mr-2"></i>Cicilan Elektronik
                                    </button>
                                    <button id="tab-btn-sembako" onclick="switchHutangTab('sembako')" class="hutang-tab-btn whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        <i class="fas fa-shopping-basket mr-2"></i>Cicilan Sembako
                                    </button>
                                </nav>
                            </div>

                            <div>
                                <div id="tab-panel-elektronik" class="hutang-tab-panel">
                                    <form id="electronicLoanForm" method="POST" action="">
                                        <input type="hidden" name="action" value="add_electronic_loan">
                                        <input type="hidden" name="nik" value="<?= htmlspecialchars($employee['NIK']) ?>">
                                        <div class="p-6">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Formulir Pengajuan Cicilan Elektronik</h3>
                                            <div class="space-y-4">
                                                <div class="flex gap-2">
                                                    <button type="button" onclick="openModal('itemListModal')" class="btn btn-secondary w-full"><i class="fas fa-list mr-2"></i>List Barang</button>
                                                    <button type="button" onclick="openModal('requestItemModal')" class="btn btn-secondary w-full"><i class="fas fa-plus mr-2"></i>Request Barang</button>
                                                </div>
                                                <div>
                                                    <label for="nama_barang_elektronik" class="block text-sm font-medium text-gray-700">Nama Barang</label>
                                                    <input type="text" id="nama_barang_elektronik" name="nama_barang_elektronik" class="input-field mt-1" required>
                                                </div>
                                                <div>
                                                    <label for="harga_barang_elektronik" class="block text-sm font-medium text-gray-700">Harga Barang</label>
                                                    <input type="text" id="harga_barang_elektronik" name="harga_barang_elektronik" class="input-field mt-1" onkeyup="formatAndCalculate(this)" required>
                                                </div>
                                                <div>
                                                    <label for="tenor" class="block text-sm font-medium text-gray-700">Tenor (Bulan)</label>
                                                    <select id="tenor" name="tenor" class="input-field mt-1" onchange="calculateInstallment()">
                                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                                            <option value="<?= $i ?>"><?= $i ?> Bulan</option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="angsuran_per_bulan" class="block text-sm font-medium text-gray-700">Angsuran per Bulan</label>
                                                    <input type="text" id="angsuran_per_bulan" name="angsuran_per_bulan" class="input-field mt-1 bg-gray-200" readonly>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 px-6 py-3 text-right rounded-b-xl flex items-center justify-end gap-4">
                                            <span id="elektronik-limit-warning" class="text-red-600 font-semibold text-sm hidden"><i class="fas fa-exclamation-triangle mr-2"></i>Angsuran melebihi sisa limit!</span>
                                            <button type="submit" id="submit-elektronik-loan-btn" class="btn btn-primary">Simpan Cicilan</button>
                                        </div>
                                    </form>
                                </div>

                                <div id="tab-panel-sembako" class="hutang-tab-panel">
                                    <div class="flex flex-col h-full">
                                        <div class="p-6 flex-grow">
                                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Formulir Pengajuan Cicilan Sembako</h3>
                                            <div class="space-y-4">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="new_sembako_item_name" class="block text-sm font-medium text-gray-700">Nama Barang</label>
                                                        <input type="text" id="new_sembako_item_name" class="input-field mt-1" placeholder="cth: Beras 5kg">
                                                    </div>
                                                    <div>
                                                        <label for="new_sembako_item_price" class="block text-sm font-medium text-gray-700">Jumlah (Harga)</label>
                                                        <input type="text" id="new_sembako_item_price" class="input-field mt-1" placeholder="cth: 75000" onkeyup="this.value = formatCurrency(this.value.replace(/[,.]/g, ''))">
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <button type="button" id="add-sembako-item-btn" class="btn btn-secondary"><i class="fas fa-plus mr-2"></i>Tambah ke Daftar</button>
                                                </div>
                                                <hr>
                                                <h4 class="font-medium text-gray-700">Daftar Pengajuan:</h4>
                                                <div id="sembako-item-list" class="space-y-2 max-h-40 overflow-y-auto pr-2">
                                                    <p id="sembako-list-placeholder" class="text-center text-gray-400 py-4">Belum ada barang yang ditambahkan.</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 px-6 py-3 mt-auto flex justify-between items-center rounded-b-xl">
                                            <div>
                                                <span class="font-bold">Total Pengajuan:</span>
                                                <span id="sembako-total-display" class="font-bold text-xl ml-2 text-green-700">Rp 0</span>
                                            </div>
                                            <button type="button" id="submit-sembako-loan-btn" class="btn btn-primary" onclick="openSembakoConfirmModal()" disabled><i class="fas fa-paper-plane mr-2"></i>Ajukan Hutang</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>
                </div>

                <div id="tab-personalia" class="tab-panel <?= $active_tab === 'personalia' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-bell mr-2"></i>Notifikasi Riwayat Personalia</h3>
                            <form method="GET" action="" class="mb-6"><input type="hidden" name="tab" value="personalia"><div class="flex items-center gap-3"><div class="relative flex-grow"><span class="absolute inset-y-0 left-0 flex items-center pl-3"><i class="fas fa-search text-gray-400"></i></span><input type="text" name="search_nik_personalia" placeholder="Cari NIK..." class="pl-10 input-field" value=""></div><button type="submit" class="btn btn-primary"><i class="fas fa-search mr-2"></i>Cari</button><a href="?tab=personalia" class="btn btn-secondary"><i class="fas fa-redo mr-2"></i>Reset</a></div></form>
                            <div class="table-container">
                                <table>
                                    <thead><tr><th>Karyawan</th><th>Pesan</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($personalia_notifications)): ?>
                                            <tr><td colspan="5" class="text-center py-8 text-gray-500">Tidak ada notifikasi.</td></tr>
                                        <?php else: foreach ($personalia_notifications as $notif): ?>
                                            <tr><td><div class="font-bold"><?= htmlspecialchars($notif['nama']) ?></div><div class="text-gray-500 text-xs">NIK: <?= htmlspecialchars($notif['nik'] ?? 'N/A') ?></div></td><td class="text-xs"><?= htmlspecialchars($notif['pesan']) ?></td><td class="text-xs"><?= date('d M Y, H:i', strtotime($notif['tanggal'])) ?></td><td><span class="badge <?= $notif['dibaca'] == 0 ? 'badge-new' : 'badge-read' ?>"><?= $notif['dibaca'] == 0 ? 'Baru' : 'Dilihat' ?></span></td><td><?php if ($notif['dibaca'] == 0): ?><button onclick="markAsRead(<?= $notif['id'] ?>, this)" class="btn btn-success text-xs py-1 px-2"><i class="fas fa-check mr-1"></i> Tandai Dibaca</button><?php endif; ?></td></tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tab-account_management" class="tab-panel <?= $active_tab === 'account_management' ? 'active' : '' ?>">
                    <div class="card">
                        <div class="p-6">
                             <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                                <h3 class="text-xl font-bold text-gray-800"><i class="fas fa-users-cog mr-2"></i>Manajemen Akun</h3>
                                <button onclick="openAddAccountModal()" class="btn btn-success w-full sm:w-auto"><i class="fas fa-plus mr-2"></i>Buat Akun Baru</button>
                            </div>

                            <div class="overflow-x-auto table-container">
                                <table class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Password</th>
                                            <th>Role</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (!empty($all_accounts)): ?>
                                            <?php foreach($all_accounts as $account): ?>
                                                <tr>
                                                    <td class="font-medium"><?= htmlspecialchars($account['username']) ?></td>
                                                    <td>*****</td>
                                                    <td><span class="font-semibold uppercase"><?= htmlspecialchars($account['role']) ?></span></td>
                                                    <td class="flex gap-2">
                                                        <button onclick='openEditAccountModal(<?= json_encode($account) ?>)' class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></button>
                                                        <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?');">
                                                            <input type="hidden" name="action" value="delete_account">
                                                            <input type="hidden" name="id" value="<?= $account['id'] ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center py-6 text-gray-500">Tidak ada data akun.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Modals -->
    
    <!-- [NEW] PDF Preview Modal for Pengajuan -->
    <div id="pdfPengajuanModal" class="modal-overlay">
        <div class="modal-content" style="width: 90%; height: 90%; max-width: 1000px; display: flex; flex-direction: column;">
            <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                <h3 class="text-xl font-bold text-gray-800">Preview Formulir Pengajuan</h3>
                <button onclick="closeModal('pdfPengajuanModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="flex-grow bg-gray-400">
                <iframe id="pdfPengajuanFrame" src="about:blank" style="width: 100%; height: 100%; border: none;"></iframe>
            </div>
        </div>
    </div>


    <div id="sembakoConfirmModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-5 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                <h3 class="text-xl font-bold text-gray-800">Konfirmasi Pengajuan Sembako</h3>
                <button type="button" onclick="closeModal('sembakoConfirmModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <p class="mb-4 text-gray-600">Mohon periksa kembali rincian pengajuan hutang sembako berikut:</p>
                <div id="sembako-confirm-list" class="space-y-2 border-y py-3 mb-3 max-h-60 overflow-y-auto"></div>
                <div class="flex justify-between items-center text-lg font-bold">
                    <span>Total Keseluruhan</span>
                    <span id="sembako-confirm-total">Rp 0</span>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 rounded-b-xl">
                <button type="button" onclick="closeModal('sembakoConfirmModal')" class="btn btn-secondary">Batal</button>
                <button type="button" id="sembako-continue-btn" onclick="processSembakoSubmission()" class="btn btn-success">Lanjutkan ke Verifikasi PIN</button>
            </div>
        </div>
    </div>

    <div id="addMemberFlowModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 id="addMemberFlowTitle" class="text-xl font-bold">Tambah Anggota Baru (Langkah 1 dari 3)</h3>
                <button type="button" onclick="closeModal('addMemberFlowModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            
            <form id="addMemberForm" method="POST" action="">
                <input type="hidden" name="action" value="add_member">
                <input type="hidden" id="confirmed_nik" name="nik">
                <input type="hidden" id="confirmed_nama_lengkap" name="nama_lengkap">
                <input type="hidden" id="confirmed_departemen" name="departemen">

                <div id="step1_check_nik" class="step-container active p-6 space-y-4">
                    <label for="nik_to_check" class="block text-sm font-medium text-gray-700">Masukkan NIK Karyawan dari Database Master</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="nik_to_check" class="input-field" placeholder="Ketik NIK...">
                        <button type="button" id="checkNikBtn" class="btn btn-primary whitespace-nowrap"><i class="fas fa-search mr-2"></i>Cek NIK</button>
                    </div>
                    <div id="nikCheckResult" class="mt-2 text-sm"></div>
                </div>

                <div id="step2_confirmation" class="step-container p-6 space-y-4">
                    <h4 class="font-semibold">Konfirmasi Data Karyawan</h4>
                    <div id="nikConfirmData" class="space-y-2"></div>
                    <div class="bg-gray-50 px-6 py-4 flex justify-between">
                        <button type="button" id="backToStep1" class="btn btn-secondary">Kembali</button>
                        <button type="button" id="goToStep3" class="btn btn-success">Data Benar, Lanjutkan</button>
                    </div>
                </div>

                <div id="step3_input_form" class="step-container p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- [MODIFIED] Added KPAB Type selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Jenis Keanggotaan</label>
                        <div class="mt-2 flex gap-4">
                            <label class="flex items-center">
                                <input type="radio" name="jenis_keanggotaan" value="PAB" class="form-radio h-4 w-4 text-green-600" checked>
                                <span class="ml-2 text-gray-700">PAB</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="jenis_keanggotaan" value="PABL" class="form-radio h-4 w-4 text-green-600">
                                <span class="ml-2 text-gray-700">PABL</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">No. KPAB</label>
                        <input type="text" class="input-field mt-1 bg-gray-200" value="(Akan dibuat otomatis)" readonly>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">NIK</label>
                            <input type="text" id="form_nik" class="input-field mt-1 bg-gray-200" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                            <input type="text" id="form_nama_lengkap" class="input-field mt-1 bg-gray-200" readonly>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Departemen</label>
                        <input type="text" id="form_departemen" class="input-field mt-1 bg-gray-200" readonly>
                    </div>
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="form_tanggal_lahir" class="block text-sm font-medium text-gray-700">Tanggal Lahir</label>
                            <input type="date" id="form_tanggal_lahir" name="tanggal_lahir" class="input-field mt-1">
                        </div>
                        <div>
                            <label for="form_no_ponsel" class="block text-sm font-medium text-gray-700">No. Ponsel</label>
                            <input type="text" id="form_no_ponsel" name="no_ponsel" class="input-field mt-1">
                        </div>
                    </div>
                    <div>
                        <label for="form_alamat_rumah" class="block text-sm font-medium text-gray-700">Alamat Rumah</label>
                        <textarea id="form_alamat_rumah" name="alamat_rumah" rows="3" class="input-field mt-1"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Simpanan Pokok</label>
                            <input type="text" class="input-field mt-1 bg-gray-200" value="Rp 50.000" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Simpanan Wajib</label>
                            <input type="text" class="input-field mt-1 bg-gray-200" value="Rp 25.000" readonly>
                        </div>
                    </div>
                    <div class="pt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="agreement_checkbox" class="form-checkbox h-5 w-5 text-green-600">
                            <span class="ml-2 text-gray-700">Saya setuju untuk mendaftarkan anggota ini.</span>
                        </label>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex justify-between">
                        <button type="button" id="backToStep2" class="btn btn-secondary">Kembali</button>
                        <button type="button" id="openPasswordModalBtn" class="btn btn-success" disabled>Setuju & Buat Password</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div id="passwordModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px;">
            <div class="p-5 border-b">
                <h3 class="text-xl font-bold">Buat Password Login</h3>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600">Buat password untuk anggota baru. Password ini akan digunakan untuk login ke sistem.</p>
                <div>
                    <label for="login_password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="login_password" name="login_password" class="input-field mt-1" required>
                </div>
                 <div>
                    <label for="confirm_login_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                    <input type="password" id="confirm_login_password" class="input-field mt-1" required>
                </div>
                <div id="password-match-error" class="text-red-500 text-sm hidden">Password tidak cocok!</div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="closeModal('passwordModal')" class="btn btn-secondary">Batal</button>
                <button type="button" id="submitRegistrationBtn" class="btn btn-success">Daftarkan Anggota</button>
            </div>
        </div>
    </div>

    <div id="accountModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <form id="accountForm" method="POST" action="">
                <input type="hidden" name="action" id="account_action">
                <input type="hidden" name="id" id="account_id">
                <div class="p-5 border-b">
                    <h3 class="text-xl font-bold" id="accountModalTitle"></h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="account_username" class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" id="account_username" name="username" class="input-field mt-1" required>
                    </div>
                    <div>
                        <label for="account_password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="account_password" name="password" class="input-field mt-1">
                        <p id="passwordHelp" class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah password.</p>
                    </div>
                    <div>
                        <label for="account_role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select id="account_role" name="role" class="input-field mt-1" required>
                            <option value="superuser">Superuser</option>
                            <option value="anggota">Anggota</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="closeModal('accountModal')" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="passwordVerifyModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 400px;">
            <div class="p-5 border-b">
                <h3 class="text-xl font-bold">Verifikasi Akses</h3>
            </div>
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600">Untuk mengubah data, masukkan password login Anda.</p>
                <div>
                    <label for="admin_password_verify" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="admin_password_verify" class="input-field mt-1" required>
                </div>
                <div id="passwordVerifyError" class="text-red-500 text-sm"></div>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                <button type="button" onclick="closeModal('passwordVerifyModal')" class="btn btn-secondary">Batal</button>
                <button type="button" onclick="verifyPasswordAndOpenEditor()" class="btn btn-primary">Verifikasi</button>
            </div>
        </div>
    </div>
    
    <div id="pdfPreviewModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 90vw; width: 900px;">
            <div class="bg-blue-600 text-white p-4 rounded-t-xl flex justify-between items-center">
                <h3 class="text-xl font-bold"><i class="fas fa-file-pdf mr-2"></i>Preview Dokumen PDF</h3>
                <button onclick="closeModal('pdfPreviewModal')" class="text-white text-2xl leading-none hover:text-gray-300">&times;</button>
            </div>
            <div class="pdf-preview-container">
                <iframe class="pdf-preview-iframe" src="export_pdf.php?nik=<?= urlencode($employee['NIK'] ?? '') ?>" title="PDF Preview"></iframe>
            </div>
            <div class="p-4 bg-gray-100 rounded-b-xl flex justify-end">
                <a href="export_pdf.php?nik=<?= urlencode($employee['NIK'] ?? '') ?>&download=true" target="_blank" class="btn btn-secondary"><i class="fas fa-download mr-2"></i>Download PDF</a>
            </div>
        </div>
    </div>
    
    <div id="itemListModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold">Pilih Barang Elektronik</h3>
                <button onclick="closeModal('itemListModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="p-5 h-96 overflow-y-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">Nama Barang</th>
                            <th class="px-4 py-2 text-right">Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items_list as $item): ?>
                        <tr class="cursor-pointer hover:bg-gray-100" onclick="selectItem('<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>', '<?= $item['price'] ?>')">
                            <td class="border-b p-2"><?= htmlspecialchars($item['name']) ?></td>
                            <td class="border-b p-2 text-right">Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="requestItemModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold">Request Barang Baru</h3>
                <button onclick="closeModal('requestItemModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="p-5 space-y-4">
                 <div>
                    <label for="request_nama_barang" class="block text-sm font-medium text-gray-700">Nama Barang</label>
                    <input type="text" id="request_nama_barang" class="input-field mt-1">
                </div>
                 <div>
                    <label for="request_harga_barang" class="block text-sm font-medium text-gray-700">Harga Barang</label>
                    <input type="text" id="request_harga_barang" class="input-field mt-1" onkeyup="this.value = formatCurrency(this.value.replace(/[,.]/g, ''))">
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3 text-right">
                <button type="button" onclick="applyRequestItem()" class="btn btn-primary">Gunakan Barang Ini</button>
            </div>
        </div>
    </div>

    <div id="detailPotonganModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-5 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                <h3 class="text-xl font-bold text-gray-800">Detail Potongan Gaji</h3>
                <button type="button" onclick="closeModal('detailPotonganModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <dl id="detailPotonganContent" class="space-y-3"></dl>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-xl">
                <button type="button" onclick="closeModal('detailPotonganModal')" class="btn btn-secondary">Tutup</button>
            </div>
        </div>
    </div>
    
    <div id="detailPengajuanModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <div class="p-5 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                <h3 class="text-xl font-bold text-gray-800">Detail Pengajuan Anggota</h3>
                <button type="button" onclick="closeModal('detailPengajuanModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="detailPengajuanContent" class="p-6 max-h-[70vh] overflow-y-auto"></div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-xl">
                <button type="button" onclick="closeModal('detailPengajuanModal')" class="btn btn-secondary">Tutup</button>
            </div>
        </div>
    </div>

    <div id="konfirmasiSetujuModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Konfirmasi Persetujuan Anggota</h3>
                <button type="button" onclick="closeModal('konfirmasiSetujuModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="p-6">
                <p class="mb-2">Anda akan menyetujui keanggotaan untuk:</p>
                <p class="font-bold text-lg mb-4" id="konfirmasiNamaAnggota"></p>
                
                <!-- [NEW] KPAB Type selection on approval -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Pilih Jenis Nomor Keanggotaan</label>
                    <div class="mt-2 flex gap-4" id="approval_kpab_type_selector">
                        <label class="flex items-center">
                            <input type="radio" name="approval_jenis_keanggotaan" value="PAB" class="form-radio h-4 w-4 text-green-600" checked>
                            <span class="ml-2 text-gray-700">PAB</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="approval_jenis_keanggotaan" value="PABL" class="form-radio h-4 w-4 text-green-600">
                            <span class="ml-2 text-gray-700">PABL</span>
                        </label>
                    </div>
                </div>

                <p class="text-sm text-gray-600">Setelah disetujui, nomor anggota akan dibuat otomatis dan data akan dipindahkan ke database anggota.</p>
            </div>
            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 rounded-b-xl">
                <button type="button" onclick="closeModal('konfirmasiSetujuModal')" class="btn btn-secondary">Batal</button>
                <button type="button" id="lanjutkanPersetujuanBtn" class="btn btn-success">Ya, Setujui & Pindahkan Data</button>
            </div>
        </div>
    </div>
    
    <!-- [BARU] Modal untuk Pembayaran Selisih -->
    <div id="selisihModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 550px;">
            <form id="selisihForm">
                <div class="p-5 border-b flex justify-between items-center bg-gray-50 rounded-t-xl">
                    <h3 class="text-xl font-bold text-gray-800">Proses Pembayaran Selisih</h3>
                    <button type="button" onclick="closeModal('selisihModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="selisih_nik" name="nik">
                    <input type="hidden" id="selisih_nama" name="nama">
                    <div>
                        <p class="text-sm text-gray-600">Anggota</p>
                        <p id="selisih_nama_display" class="font-bold text-lg text-gray-800"></p>
                        <p id="selisih_nik_display" class="text-sm text-gray-500"></p>
                    </div>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-800 p-3 rounded-md">
                        <p class="text-sm">Jumlah Selisih Periode Ini:</p>
                        <p id="selisih_jumlah_display" class="font-bold text-2xl"></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Tindakan</label>
                        <div class="flex flex-col space-y-2">
                            <label class="p-3 border rounded-lg flex items-center cursor-pointer has-[:checked]:bg-blue-50 has-[:checked]:border-blue-400">
                                <input type="radio" name="status_pembayaran" value="lunaskan" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <span class="ml-3 text-sm font-medium text-gray-900">Bayar Selisih Sekarang</span>
                            </label>
                            <label class="p-3 border rounded-lg flex items-center cursor-pointer has-[:checked]:bg-yellow-50 has-[:checked]:border-yellow-400">
                                <input type="radio" name="status_pembayaran" value="akumulasikan" class="h-4 w-4 text-yellow-600 border-gray-300 focus:ring-yellow-500" checked>
                                <span class="ml-3 text-sm font-medium text-gray-900">Akumulasikan ke Bulan Depan</span>
                            </label>
                        </div>
                    </div>

                    <div id="payment_details_container" class="hidden space-y-4 pt-4 border-t">
                        <div>
                            <div class="flex justify-between items-center">
                                <label for="selisih_jumlah_dibayar" class="block text-sm font-medium text-gray-700">Jumlah Dibayar</label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" id="selisih_bayar_lunas" class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500 mr-2">
                                    Bayar Lunas
                                </label>
                            </div>
                            <input type="text" id="selisih_jumlah_dibayar" name="jumlah_dibayar" class="input-field mt-1" onkeyup="this.value = formatCurrency(this.value.replace(/[,.]/g, ''))">
                        </div>
                        <div>
                            <label for="selisih_metode_pembayaran" class="block text-sm font-medium text-gray-700">Metode Pembayaran</label>
                            <select id="selisih_metode_pembayaran" name="metode_pembayaran" class="input-field mt-1">
                                <option value="CASH">CASH</option>
                                <option value="NON-CASH/TRANSFER">NON-CASH/TRANSFER</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 rounded-b-xl">
                    <button type="button" onclick="closeModal('selisihModal')" class="btn btn-secondary">Batal</button>
                    <button type="button" onclick="submitSelisihPayment()" class="btn btn-success">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- AlpineJS for simple dropdown -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        const { jsPDF } = window.jspdf;
        let currentUnreadCount = <?= $unread_personalia_count ?>;
        let confirmedNikData = null;
        let sembakoItems = [];
        const currentEmployee = <?= $employee ? json_encode($employee) : 'null' ?>;
        
        // [MODIFIKASI] Variabel global untuk data limit dari PHP
        const limitBulananTotal = <?= $limit_potongan ?? 0 ?>;
        const cicilanBulananAwal = <?= $total_cicilan_bulanan ?? 0 ?>;
        let sisaLimitBulananSaatIni = <?= $sisa_limit_bulanan ?? 0 ?>;

        // [MODIFIED] Function to handle tab switching
        function switchTab(tabId) {
            const url = new URL(window.location.href);
            url.searchParams.forEach((v, k) => {
                if(k !== 'tab' && k !== 'periode_payroll') url.searchParams.delete(k);
            });
            url.searchParams.set('tab', tabId);
            window.location.href = url.toString();
        }
        
        // [BARU & DIPERBAIKI] Listener untuk filter periode payroll dengan null check
        const periodeInput = document.getElementById('periode_payroll');
        if(periodeInput) {
            periodeInput.addEventListener('change', function() {
                this.form.submit();
            });
        }

        // [NEW] Function to switch sub-tabs in Pengajuan page
        function switchPengajuanSubTab(tabName) {
            document.querySelectorAll('.pengajuan-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.pengajuan-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById('content-pengajuan-' + tabName).classList.add('active');
            document.getElementById('btn-pengajuan-' + tabName).classList.add('active');
        }

        // [NEW] Function to switch sub-tabs in Database Anggota page
        function switchDbAnggotaSubTab(tabName) {
            document.querySelectorAll('.db-anggota-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.db-anggota-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById('content-db-anggota-' + tabName).classList.add('active');
            document.getElementById('btn-db-anggota-' + tabName).classList.add('active');
        }


        // [MODIFIED] Function to set up the initial view on page load
        function setInitialView() {
            const params = new URLSearchParams(window.location.search);
            let tab = params.get('tab') || 'dashboard';
            
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            
            const initialTabPanel = document.getElementById('tab-' + tab);

            if (initialTabPanel) {
                initialTabPanel.classList.add('active');
            } else {
                document.getElementById('tab-dashboard').classList.add('active');
            }
            
            // Specific setup for certain tabs
            if (document.getElementById('tab-manajemen_hutang')?.classList.contains('active')) {
                switchHutangTab('elektronik');
                updateSembakoListAndTotal();
            }
            if (document.getElementById('tab-database_anggota')?.classList.contains('active')) {
                // Check if any search filter is active to determine which tab to show
                const hasSearch = params.has('search_nik_db') || params.has('search_nama_db') || params.has('search_kpab_db');
                const activeSubTab = params.get('sub_tab') || 'aktif'; // Default to 'aktif'
                switchDbAnggotaSubTab(activeSubTab);
            }
        }

        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('active');
                if (id === 'addMemberFlowModal') {
                    resetAddMemberFlow();
                }
            }
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
        });

        function cleanCurrencyValue(value) {
            if (typeof value !== 'string') return 0;
            return parseFloat(value.replace(/[^0-9]/g, '')) || 0;
        }

        function formatCurrency(numStr) {
            if (!numStr) return '';
            let number = cleanCurrencyValue(String(numStr));
            return new Intl.NumberFormat('id-ID').format(number);
        }

        function formatAndCalculate(input) {
            input.value = formatCurrency(input.value);
            calculateInstallment();
        }

        // [MODIFIKASI] Kalkulasi cicilan elektronik dengan validasi limit
        function calculateInstallment() {
            const priceEl = document.getElementById('harga_barang_elektronik');
            const tenorEl = document.getElementById('tenor');
            const angsuranEl = document.getElementById('angsuran_per_bulan');
            const submitBtn = document.getElementById('submit-elektronik-loan-btn');
            const warningEl = document.getElementById('elektronik-limit-warning');

            let price = cleanCurrencyValue(priceEl.value);
            let tenor = parseInt(tenorEl.value);

            if (!price || !tenor || price <= 0 || tenor <= 0) {
                angsuranEl.value = '';
                return;
            }

            const interestRate = 0.0201; 
            let totalInterest = price * interestRate * tenor;
            let totalLoan = price + totalInterest;
            let monthlyInstallment = Math.ceil(totalLoan / tenor);

            angsuranEl.value = "Rp " + formatCurrency(String(monthlyInstallment));
            
            // Validasi limit
            if (submitBtn && warningEl) {
                if (monthlyInstallment > sisaLimitBulananSaatIni) {
                    submitBtn.disabled = true;
                    warningEl.classList.remove('hidden');
                } else {
                    submitBtn.disabled = false;
                    warningEl.classList.add('hidden');
                }
            }
        }

        function selectItem(name, price) {
            document.getElementById('nama_barang_elektronik').value = name;
            const priceInput = document.getElementById('harga_barang_elektronik');
            priceInput.value = formatCurrency(price);
            calculateInstallment();
            closeModal('itemListModal');
        }

        function applyRequestItem() {
            const name = document.getElementById('request_nama_barang').value;
            const price = document.getElementById('request_harga_barang').value;
            if (name && price) {
                document.getElementById('nama_barang_elektronik').value = name;
                document.getElementById('harga_barang_elektronik').value = price;
                calculateInstallment();
                closeModal('requestItemModal');
            } else {
                alert('Nama barang dan harga harus diisi.');
            }
        }

        async function markAsRead(notificationId, buttonElement) {
            try {
                const formData = new FormData();
                formData.append('id', notificationId);

                const response = await fetch('index.php?action=mark_as_read', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const row = buttonElement.closest('tr');
                    const badge = row.querySelector('.badge');
                    badge.classList.remove('badge-new');
                    badge.classList.add('badge-read');
                    badge.textContent = 'Dilihat';
                    buttonElement.remove();
                    
                    currentUnreadCount--;
                    const notifBadge = document.getElementById('sidebar-notif-badge');
                    if (notifBadge) {
                        if (currentUnreadCount > 0) {
                            notifBadge.textContent = currentUnreadCount;
                        } else {
                            notifBadge.remove();
                        }
                    }
                } else {
                    console.error('Failed to mark as read:', result.message);
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }
        
        async function checkNewNotifications() {
            try {
                const response = await fetch('index.php?action=check_notifications');
                const data = await response.json();

                if (data.error) {
                    console.error('Notification check failed:', data.error);
                    return;
                }
                
                const newCount = data.unread_count;
                const notifBadge = document.getElementById('sidebar-notif-badge');

                if (newCount > currentUnreadCount) {
                    const sound = document.getElementById('notification-sound');
                    if (sound) sound.play().catch(e => console.error("Error playing sound:", e));
                    if (document.getElementById('tab-dashboard')?.classList.contains('active')) {
                        location.reload();
                    }
                }
                
                currentUnreadCount = newCount;

                if (notifBadge) {
                    if (currentUnreadCount > 0) {
                        notifBadge.textContent = currentUnreadCount;
                        notifBadge.style.display = 'inline-flex';
                    } else {
                        notifBadge.style.display = 'none';
                    }
                } else if (currentUnreadCount > 0) {
                    const personaliaButton = document.querySelector('.sidebar-button[onclick="switchTab(\'personalia\')"]');
                    if(personaliaButton) {
                        const newBadge = document.createElement('span');
                        newBadge.id = 'sidebar-notif-badge';
                        newBadge.className = 'ml-auto inline-flex items-center justify-center h-6 w-6 text-xs font-bold text-red-100 bg-red-600 rounded-full';
                        newBadge.textContent = currentUnreadCount;
                        personaliaButton.appendChild(newBadge);
                    }
                }

            } catch (error) {
                console.error('Error checking for notifications:', error);
            }
        }

        function switchHutangTab(tabName) {
            document.querySelectorAll('.hutang-tab-panel').forEach(panel => {
                panel.style.display = 'none';
            });
            document.querySelectorAll('.hutang-tab-btn').forEach(btn => {
                btn.classList.remove('border-green-500', 'text-green-600');
                btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            });

            const activePanel = document.getElementById('tab-panel-' + tabName);
            if(activePanel) activePanel.style.display = 'block';

            const activeBtn = document.getElementById('tab-btn-' + tabName);
            if(activeBtn) {
                activeBtn.classList.add('border-green-500', 'text-green-600');
                activeBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            }
        }

        // [MODIFIKASI] Update daftar sembako dan validasi limit
        function updateSembakoListAndTotal() {
            const listContainer = document.getElementById('sembako-item-list');
            const placeholder = document.getElementById('sembako-list-placeholder');
            const totalDisplay = document.getElementById('sembako-total-display');
            const submitBtn = document.getElementById('submit-sembako-loan-btn');

            if (!listContainer) return;

            listContainer.innerHTML = '';
            let totalSembakoSaatIni = 0;

            if (sembakoItems.length === 0) {
                if(placeholder) listContainer.appendChild(placeholder);
                if(placeholder) placeholder.style.display = 'block';
                if(submitBtn) submitBtn.disabled = true;
            } else {
                if(placeholder) placeholder.style.display = 'none';
                sembakoItems.forEach((item, index) => {
                    totalSembakoSaatIni += item.price;
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'flex justify-between items-center bg-gray-50 p-2 rounded-lg';
                    itemDiv.innerHTML = `
                        <div>
                            <span class="font-medium text-gray-800">${item.name}</span>
                            <span class="text-sm text-gray-500 ml-2">Rp ${formatCurrency(item.price)}</span>
                        </div>
                        <button type="button" onclick="removeSembakoItem(${index})" class="text-red-500 hover:text-red-700">&times;</button>
                    `;
                    listContainer.appendChild(itemDiv);
                });
                // Validasi final untuk tombol ajukan
                if(submitBtn) {
                    if (totalSembakoSaatIni > sisaLimitBulananSaatIni) {
                         submitBtn.disabled = true;
                    } else {
                         submitBtn.disabled = false;
                    }
                }
            }
            if(totalDisplay) totalDisplay.textContent = `Rp ${formatCurrency(totalSembakoSaatIni)}`;
            
            // Update indikator limit utama
            updateLimitIndicator(totalSembakoSaatIni);
        }

        // [BARU] Fungsi untuk mengupdate tampilan indikator limit secara dinamis
        function updateLimitIndicator(totalSembakoBaru) {
            const sisaLimitDisplay = document.getElementById('sisa-limit-display');
            const terpakaiDisplay = document.getElementById('terpakai-display');
            const progressBar = document.getElementById('limit-progress-bar');
            const statusBadge = document.getElementById('limit-status-badge');

            if (!sisaLimitDisplay) return; // Hanya berjalan jika indikator ada di halaman

            const totalCicilanBaru = cicilanBulananAwal + totalSembakoBaru;
            const sisaLimitBaru = limitBulananTotal - totalCicilanBaru;
            let persentaseTerpakaiBaru = (limitBulananTotal > 0) ? (totalCicilanBaru / limitBulananTotal) * 100 : 0;
            if (persentaseTerpakaiBaru > 100) persentaseTerpakaiBaru = 100;

            sisaLimitDisplay.textContent = `Rp ${formatCurrency(sisaLimitBaru)}`;
            terpakaiDisplay.textContent = `Terpakai: Rp ${formatCurrency(totalCicilanBaru)}`;
            progressBar.style.width = `${persentaseTerpakaiBaru.toFixed(2)}%`;

            // Update warna progress bar dan status badge
            progressBar.classList.remove('bg-green-500', 'bg-yellow-500', 'bg-red-500');
            if (persentaseTerpakaiBaru > 85 || sisaLimitBaru < 0) {
                progressBar.classList.add('bg-red-500');
            } else if (persentaseTerpakaiBaru > 50) {
                progressBar.classList.add('bg-yellow-500');
            } else {
                progressBar.classList.add('bg-green-500');
            }

            if (sisaLimitBaru < 0) {
                statusBadge.textContent = 'OVER LIMIT';
                statusBadge.classList.remove('hidden', 'status-active');
                statusBadge.classList.add('status-inactive');
            } else {
                statusBadge.classList.add('hidden');
            }
        }


        function removeSembakoItem(index) {
            sembakoItems.splice(index, 1);
            updateSembakoListAndTotal();
        }

        // [MODIFIKASI & DIPERBAIKI] Event listener untuk tombol tambah sembako dengan validasi limit dan null check
        const addSembakoBtn = document.getElementById('add-sembako-item-btn');
        if (addSembakoBtn) {
            addSembakoBtn.addEventListener('click', () => {
                const nameInput = document.getElementById('new_sembako_item_name');
                const priceInput = document.getElementById('new_sembako_item_price');
                const name = nameInput.value.trim();
                const price = cleanCurrencyValue(priceInput.value);

                if (!name || price <= 0) {
                    alert('Nama barang dan harga harus diisi dengan benar.');
                    return;
                }

                const totalSembakoDiList = sembakoItems.reduce((sum, item) => sum + item.price, 0);
                
                if ((totalSembakoDiList + price) > sisaLimitBulananSaatIni) {
                    alert('Gagal Menambahkan! Total pengajuan sembako akan melebihi sisa limit bulanan anggota.');
                    return;
                }

                sembakoItems.push({ name, price });
                updateSembakoListAndTotal();

                nameInput.value = '';
                priceInput.value = '';
                nameInput.focus();
            });
        }

        function openSembakoConfirmModal() {
            const confirmList = document.getElementById('sembako-confirm-list');
            const confirmTotal = document.getElementById('sembako-confirm-total');
            if (!confirmList || !confirmTotal) return;
            
            confirmList.innerHTML = '';
            let total = 0;
            
            sembakoItems.forEach(item => {
                total += item.price;
                const itemDiv = document.createElement('div');
                itemDiv.className = 'flex justify-between items-center text-sm';
                itemDiv.innerHTML = `
                    <span class="text-gray-700">${item.name}</span>
                    <span class="font-medium text-gray-800">Rp ${formatCurrency(item.price)}</span>
                `;
                confirmList.appendChild(itemDiv);
            });
            
            confirmTotal.textContent = `Rp ${formatCurrency(total)}`;
            openModal('sembakoConfirmModal');
        }

        async function processSembakoSubmission() {
            const continueBtn = document.getElementById('sembako-continue-btn');
            if (!continueBtn) return;
            continueBtn.disabled = true;
            continueBtn.innerHTML = 'Memproses...';

            let total = sembakoItems.reduce((sum, item) => sum + item.price, 0);

            const payload = {
                nik: currentEmployee.NIK,
                nama: currentEmployee.NAMA,
                items: sembakoItems,
                total: total
            };

            try {
                const response = await fetch('index.php?action=prepare_sembako_submission', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (result.success) {
                    window.location.href = `index.php?page=pin_input&nik=${currentEmployee.NIK}`;
                } else {
                    throw new Error(result.message || 'Gagal menyiapkan pengajuan.');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                continueBtn.disabled = false;
                continueBtn.innerHTML = 'Lanjutkan ke Verifikasi PIN';
            }
        }

        function openAddAccountModal() {
            document.getElementById('accountForm').reset();
            document.getElementById('accountModalTitle').textContent = 'Buat Akun Baru';
            document.getElementById('account_action').value = 'add_account';
            document.getElementById('account_id').value = '';
            document.getElementById('passwordHelp').style.display = 'none';
            document.getElementById('account_password').setAttribute('required', 'required');
            openModal('accountModal');
        }

        function openEditAccountModal(account) {
            document.getElementById('accountForm').reset();
            document.getElementById('accountModalTitle').textContent = 'Edit Akun';
            document.getElementById('account_action').value = 'edit_account';
            document.getElementById('account_id').value = account.id;
            document.getElementById('account_username').value = account.username;
            document.getElementById('account_role').value = account.role;
            document.getElementById('passwordHelp').style.display = 'block';
            document.getElementById('account_password').removeAttribute('required');
            openModal('accountModal');
        }

        function resetAddMemberFlow() {
            confirmedNikData = null;
            document.getElementById('addMemberForm').reset();
            document.getElementById('nik_to_check').value = '';
            document.getElementById('nikCheckResult').innerHTML = '';
            document.getElementById('agreement_checkbox').checked = false;
            document.getElementById('openPasswordModalBtn').disabled = true;
            
            document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
            document.getElementById('step1_check_nik').classList.add('active');
            document.getElementById('addMemberFlowTitle').textContent = 'Tambah Anggota Baru (Langkah 1 dari 3)';
        }
        
        document.getElementById('checkNikBtn').addEventListener('click', async function() {
            const nik = document.getElementById('nik_to_check').value;
            const resultDiv = document.getElementById('nikCheckResult');
            if (!nik) {
                resultDiv.innerHTML = `<p class="text-red-500">NIK tidak boleh kosong.</p>`;
                return;
            }
            resultDiv.innerHTML = `<p class="text-gray-500">Mengecek...</p>`;

            try {
                const response = await fetch(`index.php?action=check_pratama_nik&nik=${nik}`);
                const result = await response.json();

                if (response.ok) {
                    if (result.status === 'found') {
                        confirmedNikData = result.data;
                        resultDiv.innerHTML = `<p class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-2"></i>NIK TERVERIFIKASI</p>`;
                        document.getElementById('nikConfirmData').innerHTML = `
                            <dl class="space-y-2">
                                <div class="flex justify-between"><dt class="font-semibold text-gray-600">NIK:</dt><dd class="text-gray-800">${confirmedNikData.NIK}</dd></div>
                                <div class="flex justify-between"><dt class="font-semibold text-gray-600">Nama:</dt><dd class="text-gray-800">${confirmedNikData.NAMA}</dd></div>
                                <div class="flex justify-between"><dt class="font-semibold text-gray-600">Departemen:</dt><dd class="text-gray-800">${confirmedNikData.DEPARTEMEN}</dd></div>
                            </dl>
                        `;
                        document.getElementById('step1_check_nik').classList.remove('active');
                        document.getElementById('step2_confirmation').classList.add('active');
                        document.getElementById('addMemberFlowTitle').textContent = 'Tambah Anggota Baru (Langkah 2 dari 3)';
                    } else {
                        resultDiv.innerHTML = `<p class="text-red-500"><i class="fas fa-times-circle mr-2"></i> ${result.message}</p>`;
                    }
                } else {
                    throw new Error(result.message || 'Terjadi kesalahan pada server.');
                }
            } catch (error) {
                resultDiv.innerHTML = `<p class="text-red-500">Error: ${error.message}</p>`;
            }
        });

        document.getElementById('backToStep1').addEventListener('click', () => {
            document.getElementById('step1_check_nik').classList.add('active');
            document.getElementById('step2_confirmation').classList.remove('active');
            document.getElementById('addMemberFlowTitle').textContent = 'Tambah Anggota Baru (Langkah 1 dari 3)';
        });

        document.getElementById('goToStep3').addEventListener('click', () => {
            document.getElementById('form_nik').value = confirmedNikData.NIK;
            document.getElementById('form_nama_lengkap').value = confirmedNikData.NAMA;
            document.getElementById('form_departemen').value = confirmedNikData.DEPARTEMEN;
            document.getElementById('confirmed_nik').value = confirmedNikData.NIK;
            document.getElementById('confirmed_nama_lengkap').value = confirmedNikData.NAMA;
            document.getElementById('confirmed_departemen').value = confirmedNikData.DEPARTEMEN;

            document.getElementById('step2_confirmation').classList.remove('active');
            document.getElementById('step3_input_form').classList.add('active');
            document.getElementById('addMemberFlowTitle').textContent = 'Tambah Anggota Baru (Langkah 3 dari 3)';
        });

        document.getElementById('backToStep2').addEventListener('click', () => {
            document.getElementById('step2_confirmation').classList.add('active');
            document.getElementById('step3_input_form').classList.remove('active');
            document.getElementById('addMemberFlowTitle').textContent = 'Tambah Anggota Baru (Langkah 2 dari 3)';
        });

        document.getElementById('agreement_checkbox').addEventListener('change', function() {
            document.getElementById('openPasswordModalBtn').disabled = !this.checked;
        });

        document.getElementById('openPasswordModalBtn').addEventListener('click', () => {
            openModal('passwordModal');
        });

        document.getElementById('submitRegistrationBtn').addEventListener('click', function() {
            const password = document.getElementById('login_password');
            const confirmPassword = document.getElementById('confirm_login_password');
            const errorDiv = document.getElementById('password-match-error');

            if (password.value === '' || confirmPassword.value === '') {
                errorDiv.textContent = 'Password tidak boleh kosong!';
                errorDiv.classList.remove('hidden');
                return;
            }

            if (password.value !== confirmPassword.value) {
                errorDiv.textContent = 'Password tidak cocok!';
                errorDiv.classList.remove('hidden');
                return;
            }
            
            errorDiv.classList.add('hidden');
            
            // Remove existing hidden password if any, then add new one
            const existingPasswordInput = document.getElementById('addMemberForm').querySelector('input[name="login_password"]');
            if (existingPasswordInput) {
                existingPasswordInput.remove();
            }
            const passwordInput = document.createElement('input');
            passwordInput.type = 'hidden';
            passwordInput.name = 'login_password';
            passwordInput.value = password.value;
            document.getElementById('addMemberForm').appendChild(passwordInput);
            
            document.getElementById('addMemberForm').submit();
        });

        async function verifyPasswordAndOpenEditor() {
    const password = document.getElementById('admin_password_verify').value;
    const errorDiv = document.getElementById('passwordVerifyError');
    errorDiv.textContent = '';

    if (!password) {
        errorDiv.textContent = 'Password tidak boleh kosong.';
        return;
    }

    const formData = new FormData();
    formData.append('password', password);

    try {
        const response = await fetch('index.php?action=verify_admin_password', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            // Verifikasi berhasil, arahkan ke halaman edit_anggota.php
            if (currentEmployee && currentEmployee.NIK) {
                window.location.href = `edit_anggota.php?nik=${currentEmployee.NIK}`;
            } else {
                errorDiv.textContent = 'Tidak ada data anggota yang dipilih.';
            }
        } else {
            errorDiv.textContent = result.message || 'Verifikasi gagal.';
        }
    } catch (err) {
        errorDiv.textContent = 'Terjadi kesalahan. Coba lagi.';
    }
}


        function showDetailPotongan(data) {
            const content = document.getElementById('detailPotonganContent');
            
            const formatRupiah = (number) => {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number || 0);
            };
            
            const formatDate = (dateStr) => {
                if (!dateStr) return 'N/A';
                const options = { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' };
                return new Date(dateStr).toLocaleDateString('id-ID', options);
            };

            const periodeBulanFormatted = new Date(data.periode_bulan + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });

            let html = `
                <div class="flex justify-between"><dt class="font-semibold text-gray-600">Periode:</dt><dd class="text-gray-800 font-medium">${periodeBulanFormatted}</dd></div>
                <div class="flex justify-between"><dt class="font-semibold text-gray-600">Tanggal Proses:</dt><dd class="text-gray-800 font-medium">${formatDate(data.tanggal_proses)}</dd></div>
                <hr class="my-3">
                <div class="flex justify-between"><dt class="text-sm text-gray-600">Pot. Simpanan Wajib:</dt><dd class="text-sm text-gray-900">${formatRupiah(data.potongan_simpanan_wajib)}</dd></div>
                <div class="flex justify-between"><dt class="text-sm text-gray-600">Pot. Elektronik:</dt><dd class="text-sm text-gray-900">${formatRupiah(data.potongan_elektronik)}</dd></div>
                <div class="flex justify-between"><dt class="text-sm text-gray-600">Pot. Sembako:</dt><dd class="text-sm text-gray-900">${formatRupiah(data.potongan_sembako)}</dd></div>
                <div class="border-t pt-3 mt-3 flex justify-between items-center">
                    <dt class="text-base font-bold text-gray-800">Total Potongan</dt>
                    <dd class="text-base font-bold text-gray-900">${formatRupiah(data.total_potongan)}</dd>
                </div>
            `;
            
            content.innerHTML = html;
            openModal('detailPotonganModal');
        }

        function openPdfPopup(id) {
            const iframe = document.getElementById('pdfPengajuanFrame');
            // [MODIFIED] Point to the new PDF generation file
            iframe.src = `generate_formulir_pdf.php?id=${id}`;
            openModal('pdfPengajuanModal');
        }

        function showApprovalConfirmation(id, nama) {
            document.getElementById('konfirmasiNamaAnggota').textContent = nama;
            const btn = document.getElementById('lanjutkanPersetujuanBtn');
            btn.onclick = () => processApproval(id, nama); // Pass name for success message
            openModal('konfirmasiSetujuModal');
        }

        async function processApproval(id, nama) {
            const btn = document.getElementById('lanjutkanPersetujuanBtn');
            const kpabType = document.querySelector('input[name="approval_jenis_keanggotaan"]:checked').value;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Memproses...';

            try {
                const response = await fetch('index.php?action=handle_pengajuan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, aksi: 'setuju', jenis_kpab: kpabType })
                });
                const result = await response.json();
                if (result.success) {
                    closeModal('konfirmasiSetujuModal');
                    alert(`BERHASIL: Pengajuan untuk ${nama} telah disetujui dan data telah dipindahkan ke database anggota dengan No. KPAB: ${result.data.no_kpab}.`);
                    // Reload the page to reflect changes across all tabs
                    window.location.href = 'index.php?tab=pengajuan_anggota_baru';
                } else {
                    throw new Error(result.message || 'Gagal memproses persetujuan.');
                }
            } catch (error) {
                alert('Gagal memproses aksi: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Ya, Setujui & Pindahkan Data';
            }
        }

        async function processRejection(id, nama) {
             if (!confirm(`Anda yakin ingin MENOLAK pengajuan untuk ${nama}? Tindakan ini tidak dapat dibatalkan.`)) {
                 return;
             }
             try {
                const response = await fetch('index.php?action=handle_pengajuan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, aksi: 'tolak' })
                });
                const result = await response.json();
                if (result.success) {
                    alert(`Pengajuan untuk ${nama} berhasil ditolak.`);
                    window.location.href = 'index.php?tab=pengajuan_anggota_baru';
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                alert('Gagal menolak pengajuan: ' + error.message);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setInitialView(); 
            // Hentikan pengecekan otomatis untuk sementara waktu untuk debugging
            // setInterval(checkNewNotifications, 15000); 
        });
    </script>
</body>
</html>