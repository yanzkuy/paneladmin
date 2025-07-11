<?php
require_once 'config.php';
check_login('superuser');

// --- FUNCTION DEFINITION TO FIX THE ERROR ---
/**
 * Logs an activity to the database.
 * Assumes a table 'db_logs' exists and a user identifier is in $_SESSION['username'].
 *
 * @param PDO $pdo The database connection object.
 * @param string $action The action performed (e.g., 'UPDATE', 'DELETE').
 * @param string $description A detailed description of the activity.
 */
if (!function_exists('log_activity')) {
    function log_activity($pdo, $action, $description) {
        // You might need to adjust 'username' to the key you use for the logged-in user, e.g., 'user_id'
        $user_identifier = $_SESSION['username'] ?? 'SYSTEM'; 
        try {
            // Assumes you have a table named `db_logs` with these columns.
            // If your table has a different name or structure, adjust the SQL query.
            $stmt = $pdo->prepare(
                "INSERT INTO db_logs (user_identifier, action, description, log_time) VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$user_identifier, $action, $description]);
        } catch (Exception $e) {
            // To prevent a logging failure from stopping the main operation,
            // you can log this error to a file instead of letting it halt the script.
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}


if (!$pdo) {
    die("Koneksi database gagal.");
}

$nik = $_GET['nik'] ?? null;
if (!$nik) {
    die("NIK anggota tidak valid.");
}

$error_message = '';
$success_message = '';

// --- Handle POST Request (Update Data) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        // 1. Update Informasi Pribadi (db_anggotakpab)
        $stmt_pribadi = $pdo->prepare(
            "UPDATE db_anggotakpab SET Nama_lengkap = ?, Tanggal_lahir = ?, Alamat_Rumah = ?, No_ponsel = ?, Departemen = ? WHERE NIK = ?"
        );
        $stmt_pribadi->execute([
            $_POST['nama_lengkap'],
            $_POST['tanggal_lahir'],
            $_POST['alamat_rumah'],
            $_POST['no_ponsel'],
            $_POST['departemen'],
            $nik
        ]);

        // 2. Update Hutang Elektronik (db_hutangelectronik)
        if (isset($_POST['electronic_id'])) {
            foreach ($_POST['electronic_id'] as $id) {
                $is_lunas = isset($_POST['lunas_elektronik'][$id]) && $_POST['lunas_elektronik'][$id] == '1';
                if ($is_lunas) {
                    $stmt_elec = $pdo->prepare("UPDATE db_hutangelectronik SET SISA_BULAN = 0 WHERE id = ?");
                    $stmt_elec->execute([$id]);
                } else {
                    $stmt_elec = $pdo->prepare("UPDATE db_hutangelectronik SET SISA_BULAN = ?, ANGSURAN_PERBULAN = ? WHERE id = ?");
                    $stmt_elec->execute([
                        $_POST['tenor'][$id],
                        clean_currency($_POST['angsuran_perbulan'][$id]),
                        $id
                    ]);
                }
            }
        }

        // 3. Update Hutang Sembako (db_hutangsembako)
        if (isset($_POST['sembako_id'])) {
            foreach ($_POST['sembako_id'] as $id) {
                 $is_lunas = isset($_POST['lunas_sembako'][$id]) && $_POST['lunas_sembako'][$id] == '1';
                 if ($is_lunas) {
                     $stmt_sembako = $pdo->prepare("DELETE FROM db_hutangsembako WHERE id = ?");
                     $stmt_sembako->execute([$id]);
                 } else {
                     $stmt_sembako = $pdo->prepare("UPDATE db_hutangsembako SET nama_barang = ?, jumlah = ? WHERE id = ?");
                     $stmt_sembako->execute([
                         $_POST['nama_barang_sembako'][$id],
                         clean_currency($_POST['jumlah_sembako'][$id]),
                         $id
                     ]);
                 }
            }
        }
        
        // This line will now work correctly
        log_activity($pdo, 'UPDATE', "Memperbarui data lengkap untuk anggota NIK: {$nik}.");
        
        $pdo->commit();
        $_SESSION['update_success'] = "Data untuk NIK {$nik} berhasil diperbarui.";
        header("Location: edit_anggota.php?nik=" . urlencode($nik));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Gagal memperbarui data: " . $e->getMessage();
    }
}

// --- Fetch Data for Display ---
$anggota = null;
$hutang_elektronik = [];
$hutang_sembako = [];

try {
    $stmt_anggota = $pdo->prepare("SELECT * FROM db_anggotakpab WHERE NIK = ?");
    $stmt_anggota->execute([$nik]);
    $anggota = $stmt_anggota->fetch(PDO::FETCH_ASSOC);

    if (!$anggota) {
        die("Anggota tidak ditemukan.");
    }

    $stmt_elektronik = $pdo->prepare("SELECT * FROM db_hutangelectronik WHERE NIK = ? AND SISA_BULAN > 0 ORDER BY id DESC");
    $stmt_elektronik->execute([$nik]);
    $hutang_elektronik = $stmt_elektronik->fetchAll(PDO::FETCH_ASSOC);

    $stmt_sembako = $pdo->prepare("SELECT * FROM db_hutangsembako WHERE NIK = ? AND nama_barang != 'HITUNGAN POKOK' ORDER BY tanggal_ambil_barang DESC");
    $stmt_sembako->execute([$nik]);
    $hutang_sembako = $stmt_sembako->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Gagal mengambil data: " . $e->getMessage());
}

if (isset($_SESSION['update_success'])) {
    $success_message = $_SESSION['update_success'];
    unset($_SESSION['update_success']);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Anggota - <?= htmlspecialchars($anggota['Nama_lengkap']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --primary-green: #16a34a; 
            --primary-green-dark: #15803d; 
            --primary-green-light: #22c55e;
            --accent-orange: #ea580c;
            --accent-orange-dark: #dc2626;
            --accent-orange-light: #f97316;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --excel-border: #d4d4d4;
            --excel-header: #f2f2f2;
            --excel-selected: #cce7ff;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #fff7ed 100%);
            min-height: 100vh;
        }
        
        .main-container {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--gray-200);
        }
        
        .section-card { 
            background: var(--white); 
            border-radius: 0.75rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-light) 100%);
            color: var(--white);
            padding: 1.25rem 1.5rem;
            border-bottom: 3px solid var(--primary-green-dark);
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-icon {
            width: 2rem;
            height: 2rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .input-field { 
            background: var(--white); 
            border: 1px solid var(--gray-300); 
            border-radius: 0.375rem; 
            padding: 0.75rem 1rem; 
            font-size: 0.875rem; 
            transition: all 0.2s ease; 
            width: 100%;
            color: var(--gray-800);
        }
        
        .input-field:focus { 
            border-color: var(--primary-green); 
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1); 
            outline: none; 
        }
        
        .input-field:disabled { 
            background-color: var(--gray-100); 
            color: var(--gray-500);
            cursor: not-allowed; 
        }
        
        .btn { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0.625rem 1.25rem; 
            border-radius: 0.375rem; 
            font-weight: 600; 
            font-size: 0.875rem;
            transition: all 0.2s ease; 
            cursor: pointer; 
            border: none;
            text-decoration: none;
        }
        
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-light) 100%); 
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.25);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-green-dark) 0%, var(--primary-green) 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -1px rgba(22, 163, 74, 0.35);
        }
        
        .btn-secondary { 
            background: var(--gray-100); 
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
        }
        
        .btn-orange {
            background: linear-gradient(135deg, var(--accent-orange) 0%, var(--accent-orange-light) 100%);
            color: var(--white);
            box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.25);
        }
        
        .btn-orange:hover {
            background: linear-gradient(135deg, var(--accent-orange-dark) 0%, var(--accent-orange) 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 12px -1px rgba(234, 88, 12, 0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-light) 100%);
            color: var(--white);
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, var(--primary-green-dark) 0%, var(--primary-green) 100%);
            transform: translateY(-1px);
        }
        
        /* Excel-like Table Styles */
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
        }
        
        .excel-table th {
            background: linear-gradient(135deg, var(--excel-header) 0%, #e8e8e8 100%);
            border: 1px solid var(--excel-border);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            position: relative;
        }
        
        .excel-table th:first-child {
            border-left: 2px solid var(--primary-green);
        }
        
        .excel-table th::after {
            content: '';
            position: absolute;
            right: 0;
            top: 25%;
            height: 50%;
            width: 1px;
            background: var(--gray-300);
        }
        
        .excel-table td {
            border: 1px solid var(--excel-border);
            padding: 0.5rem;
            vertical-align: middle;
            background: var(--white);
            transition: all 0.2s ease;
        }
        
        .excel-table td:first-child {
            border-left: 2px solid var(--primary-green);
            font-weight: 500;
        }
        
        .excel-table tr:hover td {
            background: #f8fcf8;
        }
        
        .excel-table tr:nth-child(even) td {
            background: var(--gray-50);
        }
        
        .excel-table tr:nth-child(even):hover td {
            background: #f0f9f0;
        }
        
        .excel-table input,
        .excel-table select {
            width: 100%;
            border: 1px solid var(--gray-300);
            border-radius: 0.25rem;
            padding: 0.375rem 0.5rem;
            font-size: 0.8125rem;
            background: var(--white);
        }
        
        .excel-table input:focus,
        .excel-table select:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.1);
            outline: none;
        }
        
        .lunas-row td { 
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%) !important;
            text-decoration: line-through;
            color: var(--gray-500) !important;
        }
        
        .lunas-row td:first-child {
            border-left: 2px solid var(--primary-green-light);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-light) 50%, var(--accent-orange) 100%);
            color: var(--white);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(22, 163, 74, 0.2);
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 500;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 1rem;
        }
        
        .alert {
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left-color: #ef4444;
            color: #991b1b;
        }
        
        .modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0, 0, 0, 0.6); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            z-index: 1000; 
            opacity: 0; 
            visibility: hidden; 
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active { 
            opacity: 1; 
            visibility: visible; 
        }
        
        .modal-content { 
            background: var(--white); 
            border-radius: 1rem; 
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); 
            width: 90%; 
            max-width: 600px; 
            transform: translateY(20px) scale(0.95); 
            transition: all 0.4s ease;
            overflow: hidden;
        }
        
        .modal-overlay.active .modal-content { 
            transform: translateY(0) scale(1); 
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-light) 100%);
            color: var(--white);
            padding: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .modal-body {
            padding: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            background: var(--gray-50);
            padding: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            background: linear-gradient(135deg, var(--accent-orange) 0%, var(--accent-orange-light) 100%);
            color: var(--white);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
            background: var(--gray-50);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }
        
        .table-container {
            background: var(--white);
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid var(--excel-border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
            
            .excel-table {
                font-size: 0.75rem;
            }
            
            .excel-table th,
            .excel-table td {
                padding: 0.5rem 0.25rem;
            }
        }
    </style>
</head>
<body class="min-h-screen py-8 px-4">
    <div class="max-w-7xl mx-auto">
        <div class="page-header">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="flex-1">
                    <h1 class="page-title">
                        <i class="fas fa-user-edit mr-3"></i>
                        Edit Data Anggota
                    </h1>
                    <p class="page-subtitle">
                        <span class="inline-flex items-center gap-2">
                            <i class="fas fa-id-card"></i>
                            NIK: <span class="font-bold"><?= htmlspecialchars($anggota['NIK']) ?></span>
                        </span>
                        <span class="mx-3">•</span>
                        <span class="inline-flex items-center gap-2">
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($anggota['Nama_lengkap']) ?>
                        </span>
                    </p>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                        <i class="fas fa-chevron-right text-xs"></i>
                        <span>Anggota</span>
                        <i class="fas fa-chevron-right text-xs"></i>
                        <span>Edit Data</span>
                    </div>
                </div>
                <div class="mt-4 lg:mt-0">
                    <a href="index.php?tab=search&nik=<?= htmlspecialchars($nik) ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="main-container p-6">
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                        <div>
                            <h4 class="font-semibold">Terjadi Kesalahan</h4>
                            <p><?= htmlspecialchars($error_message) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <form id="editForm" method="POST" action="edit_anggota.php?nik=<?= htmlspecialchars($nik) ?>" class="space-y-6">
                
                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <div class="section-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            Informasi Pribadi
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="input-label">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="input-field" value="<?= htmlspecialchars($anggota['Nama_lengkap']) ?>" required>
                            </div>
                            <div class="input-group">
                                <label class="input-label">Departemen</label>
                                <input type="text" name="departemen" class="input-field" value="<?= htmlspecialchars($anggota['Departemen']) ?>">
                            </div>
                            <div class="input-group">
                                <label class="input-label">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="input-field" value="<?= htmlspecialchars($anggota['Tanggal_lahir']) ?>">
                            </div>
                            <div class="input-group">
                                <label class="input-label">No. Ponsel</label>
                                <input type="text" name="no_ponsel" class="input-field" value="<?= htmlspecialchars($anggota['No_ponsel']) ?>">
                            </div>
                            <div class="input-group lg:col-span-2">
                                <label class="input-label">Alamat Rumah</label>
                                <textarea name="alamat_rumah" rows="3" class="input-field" placeholder="Masukkan alamat lengkap..."><?= htmlspecialchars($anggota['Alamat_Rumah']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <div class="section-icon">
                                <i class="fas fa-laptop"></i>
                            </div>
                            Hutang Elektronik
                            <?php if (!empty($hutang_elektronik)): ?>
                                <span class="status-badge ml-auto">
                                    <i class="fas fa-circle text-xs"></i>
                                    <?= count($hutang_elektronik) ?> item aktif
                                </span>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="table-container">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th>
                                        <i class="fas fa-laptop-code mr-2"></i>
                                        Jenis Barang
                                    </th>
                                    <th>
                                        <i class="fas fa-money-bill-wave mr-2"></i>
                                        Angsuran/Bulan
                                    </th>
                                    <th>
                                        <i class="fas fa-calendar-alt mr-2"></i>
                                        Tenor (Bulan)
                                    </th>
                                    <th>
                                        <i class="fas fa-cogs mr-2"></i>
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($hutang_elektronik)): ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <h3 class="text-lg font-semibold mb-2">Tidak Ada Hutang Elektronik</h3>
                                            <p>Anggota ini tidak memiliki hutang elektronik yang aktif.</p>
                                        </td>
                                    </tr>
                                <?php else: foreach ($hutang_elektronik as $h): ?>
                                    <tr id="elec-row-<?= $h['id'] ?>">
                                        <td>
                                            <div class="font-semibold text-gray-800 flex items-center">
                                                <i class="fas fa-microchip mr-2 text-green-600"></i>
                                                <?= htmlspecialchars($h['JENIS_BARANG']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="angsuran_perbulan[<?= $h['id'] ?>]" 
                                                   value="<?= number_format($h['ANGSURAN_PERBULAN'], 0, ',', '.') ?>" 
                                                   onkeyup="this.value = formatCurrency(this.value)"
                                                   placeholder="0">
                                        </td>
                                        <td>
                                            <input type="hidden" name="electronic_id[]" value="<?= $h['id'] ?>">
                                            <select name="tenor[<?= $h['id'] ?>]">
                                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                                    <option value="<?= $i ?>" <?= $h['SISA_BULAN'] == $i ? 'selected' : '' ?>><?= $i ?> Bulan</option>
                                                <?php endfor; ?>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="lunas_elektronik[<?= $h['id'] ?>]" id="lunas-elec-hidden-<?= $h['id'] ?>" value="0">
                                            <button type="button" onclick="markAsLunas('elec', <?= $h['id'] ?>)" class="btn btn-success">
                                                <i class="fas fa-check mr-1"></i>Lunas
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <h2 class="section-title">
                            <div class="section-icon">
                                <i class="fas fa-shopping-basket"></i>
                            </div>
                            Hutang Sembako
                            <?php if (!empty($hutang_sembako)): ?>
                                <span class="status-badge ml-auto">
                                    <i class="fas fa-circle text-xs"></i>
                                    <?= count($hutang_sembako) ?> item
                                </span>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="table-container">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th>
                                        <i class="fas fa-shopping-cart mr-2"></i>
                                        Nama Barang
                                    </th>
                                    <th>
                                        <i class="fas fa-money-bill-wave mr-2"></i>
                                        Jumlah (Harga)
                                    </th>
                                    <th>
                                        <i class="fas fa-calendar-check mr-2"></i>
                                        Tanggal Ambil
                                    </th>
                                    <th>
                                        <i class="fas fa-cogs mr-2"></i>
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($hutang_sembako)): ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
                                            <i class="fas fa-shopping-cart"></i>
                                            <h3 class="text-lg font-semibold mb-2">Tidak Ada Hutang Sembako</h3>
                                            <p>Anggota ini tidak memiliki hutang sembako yang tercatat.</p>
                                        </td>
                                    </tr>
                                <?php else: foreach ($hutang_sembako as $s): ?>
                                    <tr id="sembako-row-<?= $s['id'] ?>">
                                        <td>
                                            <input type="hidden" name="sembako_id[]" value="<?= $s['id'] ?>">
                                            <input type="text" name="nama_barang_sembako[<?= $s['id'] ?>]" 
                                                   value="<?= htmlspecialchars($s['nama_barang']) ?>"
                                                   placeholder="Nama barang">
                                        </td>
                                        <td>
                                            <input type="text" name="jumlah_sembako[<?= $s['id'] ?>]" 
                                                   value="<?= number_format($s['jumlah'], 0, ',', '.') ?>" 
                                                   onkeyup="this.value = formatCurrency(this.value)"
                                                   placeholder="0">
                                        </td>
                                        <td>
                                            <div class="text-sm text-gray-600 font-medium">
                                                <i class="fas fa-calendar-alt mr-2 text-orange-600"></i>
                                                <?= htmlspecialchars(date('d M Y', strtotime($s['tanggal_ambil_barang']))) ?>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="lunas_sembako[<?= $s['id'] ?>]" id="lunas-sembako-hidden-<?= $s['id'] ?>" value="0">
                                            <button type="button" onclick="markAsLunas('sembako', <?= $s['id'] ?>)" class="btn btn-success">
                                                <i class="fas fa-check mr-1"></i>Lunas
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 justify-end pt-6 border-t-2 border-gray-200">
                    <a href="index.php?tab=search&nik=<?= htmlspecialchars($nik) ?>" class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i>Batal
                    </a>
                    <button type="button" onclick="showConfirmation()" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirmationModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Konfirmasi Perubahan
                </h3>
            </div>
            <div class="modal-body">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-br from-orange-100 to-orange-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-orange-600"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">Konfirmasi Penyimpanan Data</h4>
                    <p class="text-gray-600">Apakah Anda yakin ingin menyimpan semua perubahan yang telah dibuat? Tindakan ini akan langsung memperbarui database dan tidak dapat dibatalkan.</p>
                </div>
                <div class="bg-gradient-to-r from-green-50 to-orange-50 rounded-lg p-4 border border-gray-200">
                    <h5 class="font-semibold text-gray-700 mb-2">
                        <i class="fas fa-info-circle mr-2 text-green-600"></i>Informasi Penting:
                    </h5>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Data pribadi anggota akan diperbarui</li>
                        <li>• Status hutang elektronik dan sembako akan disinkronkan</li>
                        <li>• Item yang ditandai "Lunas" akan diproses secara otomatis</li>
                        <li>• Perubahan akan tercatat dalam log sistem</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('confirmationModal')" class="btn btn-secondary">
                    <i class="fas fa-times mr-2"></i>Batal
                </button>
                <button type="button" onclick="submitForm()" class="btn btn-primary">
                    <i class="fas fa-check mr-2"></i>Ya, Simpan Perubahan
                </button>
            </div>
        </div>
    </div>

    <div id="successModal" class="modal-overlay <?= $success_message ? 'active' : '' ?>">
        <div class="modal-content">
            <div class="modal-body text-center py-12">
                <div class="w-20 h-20 bg-gradient-to-br from-green-100 to-green-200 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-3xl text-green-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Data Berhasil Diperbarui!</h3>
                <p class="text-gray-600 mb-8"><?= htmlspecialchars($success_message) ?></p>
                <button onclick="closeModal('successModal')" class="btn btn-primary">
                    <i class="fas fa-check mr-2"></i>Tutup
                </button>
            </div>
        </div>
    </div>

<script>
    function cleanCurrencyValue(value) {
        if (typeof value !== 'string') return 0;
        return parseFloat(value.replace(/[^0-9]/g, '')) || 0;
    }

    function formatCurrency(numStr) {
        if (!numStr) return '';
        let number = cleanCurrencyValue(String(numStr));
        return new Intl.NumberFormat('id-ID').format(number);
    }

    function openModal(id) {
        document.getElementById(id).classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    function markAsLunas(type, id) {
        const row = document.getElementById(`${type}-row-${id}`);
        const hiddenInput = document.getElementById(`lunas-${type}-hidden-${id}`);
        
        row.classList.toggle('lunas-row');
        const isLunas = row.classList.contains('lunas-row');
        hiddenInput.value = isLunas ? '1' : '0';

        row.querySelectorAll('input, select').forEach(input => {
            if (input !== hiddenInput) {
                input.disabled = isLunas;
            }
        });

        // Update button text and style
        const button = row.querySelector('button[onclick*="markAsLunas"]');
        if (isLunas) {
            button.innerHTML = '<i class="fas fa-undo mr-1"></i>Batal Lunas';
            button.className = 'btn btn-orange';
        } else {
            button.innerHTML = '<i class="fas fa-check mr-1"></i>Lunas';
            button.className = 'btn btn-success';
        }
    }

    function showConfirmation() {
        openModal('confirmationModal');
    }

    function submitForm() {
        // Add loading state
        const submitBtn = document.querySelector('[onclick="submitForm()"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan...';
        submitBtn.disabled = true;
        
        // Submit form
        document.getElementById('editForm').submit();
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            closeModal(e.target.id);
        }
    });

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });

    // Auto-format currency inputs on page load
    document.addEventListener('DOMContentLoaded', function() {
        const currencyInputs = document.querySelectorAll('input[onkeyup*="formatCurrency"]');
        currencyInputs.forEach(input => {
            input.addEventListener('blur', function() {
                this.value = formatCurrency(this.value);
            });
        });
    });
</script>
</body>
</html>