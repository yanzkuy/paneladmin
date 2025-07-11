<?php
// File: ganti_password.php
// Deskripsi: Halaman untuk pengguna mengubah password mereka.

require_once 'config.php';

// Pengguna harus login untuk mengakses halaman ini
check_login(); 

$error_message = '';
$success_message = '';

// Cek apakah ada notifikasi berhasil dari URL
if (isset($_GET['status']) && $_GET['status'] == 'changed') {
    $success_message = "Kata sandi Anda telah berhasil diperbarui";
}

// Proses form saat data dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validasi input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Semua field harus diisi.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password baru minimal harus 6 karakter.";
    } else {
        // Ambil password saat ini dari database
        $sql_get_pass = "SELECT password FROM users WHERE username = :username";
        if ($stmt_get = $pdo->prepare($sql_get_pass)) {
            $stmt_get->bindParam(":username", $_SESSION['username'], PDO::PARAM_STR);
            if ($stmt_get->execute()) {
                $user = $stmt_get->fetch();
                // Verifikasi password saat ini (tanpa hash)
                if ($user && $current_password === $user['password']) {
                    // Update password baru ke database
                    $sql_update_pass = "UPDATE users SET password = :password WHERE username = :username";
                    if ($stmt_update = $pdo->prepare($sql_update_pass)) {
                        $stmt_update->bindParam(":password", $new_password, PDO::PARAM_STR);
                        $stmt_update->bindParam(":username", $_SESSION['username'], PDO::PARAM_STR);

                        if ($stmt_update->execute()) {
                            // Redirect dengan status berhasil
                            header("location: ganti_password.php?status=changed");
                            exit();
                        } else {
                            $error_message = "Gagal memperbarui password. Silakan coba lagi.";
                        }
                    }
                } else {
                    $error_message = "Password saat ini yang Anda masukkan salah.";
                }
            }
        }
    }
}

// Tentukan link kembali berdasarkan role
$dashboard_link = ($_SESSION['role'] === 'superuser') ? 'index.php' : 'dashboard_personalia.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-green: #10B981;
            --primary-green-dark: #059669;
        }
        body { font-family: 'Inter', sans-serif; }
        .input-group { position: relative; }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6B7280;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-lg">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Ganti Password</h1>
            <p class="mt-2 text-gray-600">Perbarui password Anda untuk keamanan.</p>
        </div>

        <?php 
        if(!empty($error_message)){
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert"><p>' . htmlspecialchars($error_message) . '</p></div>';
        }
        if(!empty($success_message)){
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md" role="alert"><p>' . htmlspecialchars($success_message) . '</p></div>';
        }
        ?>

        <form class="space-y-4" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div>
                <label for="current_password" class="text-sm font-medium text-gray-700">Password Saat Ini</label>
                <div class="input-group mt-1">
                    <input id="current_password" name="current_password" type="password" required class="block w-full px-4 py-2 text-gray-900 bg-gray-50 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('current_password')"></i>
                </div>
            </div>
            <div>
                <label for="new_password" class="text-sm font-medium text-gray-700">Password Baru</label>
                <div class="input-group mt-1">
                    <input id="new_password" name="new_password" type="password" required class="block w-full px-4 py-2 text-gray-900 bg-gray-50 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')"></i>
                </div>
            </div>
             <div>
                <label for="confirm_password" class="text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                <div class="input-group mt-1">
                    <input id="confirm_password" name="confirm_password" type="password" required class="block w-full px-4 py-2 text-gray-900 bg-gray-50 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                </div>
            </div>
            <div class="flex items-center gap-4 pt-2">
                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700">
                    Ubah Password
                </button>
            </div>
        </form>
         <div class="pt-2">
             <a href="<?= $dashboard_link ?>" class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Kembali ke Dashboard
            </a>
        </div>
    </div>

    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling;
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
