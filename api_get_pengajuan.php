<?php
// Header untuk mengizinkan akses dari mana saja (CORS)
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

// --- PENGATURAN KONEKSI DATABASE ---
// Pastikan detail ini sudah sesuai dengan database Anda
$servername = "103.79.244.233";
$username = "kpabcent_localarea";
$password = "Rianzshon2023";
$dbname = "kpabcent_localarea";

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    // Mengirim response error dalam format JSON jika koneksi gagal
    echo json_encode(['error' => 'Koneksi database gagal: ' . $conn->connect_error]);
    exit();
}

// Mengambil semua pengajuan yang statusnya 'pending' untuk disetujui admin
$sql = "SELECT id, nik, nama_lengkap, no_ponsel, departemen, tanggal_pengajuan FROM db_pengajuankpab WHERE status = 'pending' ORDER BY tanggal_pengajuan ASC";
$result = $conn->query($sql);

$pengajuan = array();
if ($result && $result->num_rows > 0) {
    // Ambil setiap baris data dan masukkan ke dalam array
    while($row = $result->fetch_assoc()) {
        $pengajuan[] = $row;
    }
}

// Tutup koneksi database
$conn->close();

// Kembalikan data dalam format JSON, bahkan jika kosong
echo json_encode($pengajuan);
?>
