<?php
header('Content-Type: application/json');

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
try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Kesalahan Koneksi Database: " . $e->getMessage()]);
    exit;
}

// Get the request body
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? null;

if ($action === 'mark_as_read') {
    $notificationId = $data['id'] ?? null;
    if (!$notificationId) {
        echo json_encode(['success' => false, 'message' => 'ID Notifikasi tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE id = ?");
        $stmt->execute([$notificationId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notifikasi berhasil ditandai sebagai telah dibaca.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notifikasi tidak ditemukan atau sudah dibaca.']);
        }
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak diketahui.']);
}
?>
