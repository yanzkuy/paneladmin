<?php
// Pastikan file ini di-include setelah config.php

/**
 * Fungsi untuk mencatat aktivitas pengguna ke dalam database.
 *
 * @param mysqli $conn Koneksi database.
 * @param string $user Username pengguna yang melakukan aksi.
 * @param string $aksi Deskripsi aksi yang dilakukan.
 * @return void
 */
function log_activity($conn, $user, $aksi) {
    // Siapkan query SQL untuk menyisipkan log
    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user, aksi) VALUES (?, ?)");
    if ($stmt === false) {
        // Handle error jika prepare gagal
        error_log("Prepare failed: " . $conn->error);
        return;
    }

    // Bind parameter ke query
    $stmt->bind_param("ss", $user, $aksi);

    // Eksekusi query
    $stmt->execute();

    // Tutup statement
    $stmt->close();
}
?>
