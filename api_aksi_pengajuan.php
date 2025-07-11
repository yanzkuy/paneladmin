<?php
// Header untuk izin CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

// --- PENGATURAN KONEKSI DATABASE ---
$servername = "103.79.244.233";
$username = "kpabcent_localarea";
$password = "Rianzshon2023";
$dbname = "kpabcent_localarea";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;
$aksi = $data['aksi'] ?? '';

if (empty($id) || empty($aksi)) {
    echo json_encode(['success' => false, 'message' => 'ID atau Aksi tidak valid']);
    $conn->close();
    exit();
}

if ($aksi == 'setuju') {
    // Memulai transaksi untuk memastikan integritas data
    $conn->begin_transaction();

    try {
        // 1. Ambil data lengkap dari tabel pengajuan
        $stmt_get = $conn->prepare("SELECT * FROM db_pengajuankpab WHERE id = ? AND status = 'pending'");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $result = $stmt_get->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Data pengajuan tidak ditemukan atau sudah diproses.");
        }
        $pengajuan = $result->fetch_assoc();
        $stmt_get->close();

        // 2. Masukkan data ke tabel anggota final (db_anggotakpab)
        $stmt_insert = $conn->prepare("
            INSERT INTO db_anggotakpab (NIK, Nama_lengkap, Tanggal_lahir, Alamat_Rumah, No_ponsel, Departemen, date_join, is_panel_registered, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'AKTIF')
        ");
        $date_join = date('Y-m-d'); // Tanggal saat disetujui
        $stmt_insert->bind_param(
            "sssssss",
            $pengajuan['nik'],
            $pengajuan['nama_lengkap'],
            $pengajuan['ttl'],
            $pengajuan['alamat'],
            $pengajuan['no_ponsel'],
            $pengajuan['departemen'],
            $date_join
        );
        $stmt_insert->execute();
        $stmt_insert->close();

        // 3. Update status di tabel pengajuan menjadi 'disetujui'
        $stmt_update = $conn->prepare("UPDATE db_pengajuankpab SET status = 'disetujui' WHERE id = ?");
        $stmt_update->bind_param("i", $id);
        $stmt_update->execute();
        $stmt_update->close();

        // Jika semua berhasil, commit transaksi
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Anggota berhasil disetujui dan data telah dipindahkan.']);

    } catch (Exception $e) {
        // Jika ada error, rollback transaksi
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal menyetujui anggota: ' . $e->getMessage()]);
    }

} elseif ($aksi == 'tolak') {
    // Jika ditolak, cukup update status di tabel pengajuan
    $stmt = $conn->prepare("UPDATE db_pengajuankpab SET status = 'ditolak' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil ditolak.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menolak pengajuan.']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
}

$conn->close();
?>
