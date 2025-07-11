<?php
// File: login.php
// Deskripsi: Halaman login untuk pengguna dengan desain baru dan fitur "Ingat Saya".

// Mulai sesi jika belum ada
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memuat konfigurasi database
require_once 'config.php'; 

// Jika pengguna sudah login, alihkan ke halaman yang sesuai
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SESSION['role'] === 'superuser') {
        header("location: index.php");
    } elseif ($_SESSION['role'] === 'personalia') {
        header("location: dashboard_personalia.php");
    }
    exit;
}

$username = "";
$password = "";
$error_message = "";

// Ambil data dari cookies jika ada
$remembered_user = $_COOKIE['remember_user'] ?? '';
$remembered_pass = $_COOKIE['remember_pass'] ?? '';

// Proses form saat data dikirim
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username)) {
        $error_message = "Silakan masukkan username.";
    } elseif (empty($password)) {
        $error_message = "Silakan masukkan password Anda.";
    }

    // Validasi kredensial dengan database
    if (empty($error_message) && isset($pdo)) {
        $sql = "SELECT id, username, password, role FROM users WHERE username = :username";

        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);

            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $db_password = $row["password"];
                        // Memeriksa password (tanpa hash sesuai file asli)
                        if ($password === $db_password) {
                            // Password benar, mulai session baru
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $row["id"];
                            $_SESSION["username"] = $row["username"];
                            $_SESSION["role"] = $row["role"];

                            // Penanganan "Ingat Saya"
                            if (!empty($_POST["remember"])) {
                                // Set cookie selama 30 hari
                                setcookie("remember_user", $username, time() + (86400 * 30), "/");
                                setcookie("remember_pass", $password, time() + (86400 * 30), "/");
                            } else {
                                // Hapus cookie jika tidak dicentang
                                setcookie("remember_user", "", time() - 3600, "/");
                                setcookie("remember_pass", "", time() - 3600, "/");
                            }

                            // Alihkan berdasarkan peran
                            if ($row["role"] === 'superuser') {
                                header("location: index.php");
                            } elseif ($row["role"] === 'personalia') {
                                header("location: dashboard_personalia.php");
                            } else {
                                $error_message = "Role tidak valid.";
                            }
                            exit;
                        } else {
                            // Password tidak cocok
                            $error_message = "Username atau password yang Anda masukkan salah.";
                        }
                    }
                } else {
                    // Username tidak ditemukan
                    $error_message = "Username atau password yang Anda masukkan salah.";
                }
            } else {
                $error_message = "Oops! Terjadi kesalahan. Silakan coba lagi nanti.";
            }
            unset($stmt);
        }
    }
    
    // Cek jika koneksi pdo gagal dari config.php
    if(!isset($pdo) && isset($db_error)) {
        $error_message = $db_error;
    } elseif (!isset($pdo)) {
        $error_message = "Koneksi ke database gagal. Periksa file config.php.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Koperasi Konsumen Pelita Abadi Bersama</title>
    <!-- Memuat Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Memuat Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Menggunakan font Inter sebagai default */
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Style tambahan untuk checkbox agar lebih menarik */
        .form-checkbox {
            appearance: none;
            -webkit-appearance: none;
            height: 1.25rem;
            width: 1.25rem;
            border-radius: 0.25rem;
            border: 1px solid #d1d5db; /* gray-300 */
            background-color: white;
            cursor: pointer;
            display: inline-block;
            position: relative;
        }
        .form-checkbox:checked {
            background-color: #22c55e; /* green-500 */
            border-color: #22c55e; /* green-500 */
        }
        .form-checkbox:checked::after {
            content: '✔';
            font-size: 0.8rem;
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-sm mx-auto bg-white rounded-2xl shadow-xl p-8 space-y-6">
        
        <!-- Bagian Header dengan Logo dan Judul -->
        <div class="text-center space-y-4">
            <!-- Logo telah diperbarui -->
            <img src="picture/logo.png" alt="Logo Koperasi" class="mx-auto h-24 w-24 rounded-full object-cover" onerror="this.onerror=null;this.src='https://placehold.co/96x96/e2e8f0/64748b?text=Logo';">
            <div>
                <p class="text-sm font-semibold text-gray-600 tracking-wider">KOPERASI KONSUMEN</p>
                <h1 class="text-xl font-bold text-green-800">PELITA ABADI BERSAMA</h1>
            </div>
        </div>

        <!-- Menampilkan pesan error jika ada -->
        <?php 
        if(!empty($error_message)){
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert"><span class="block sm:inline">' . htmlspecialchars($error_message) . '</span></div>';
        }
        ?>

        <!-- Form Login -->
        <form class="space-y-6" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <!-- Input Username -->
            <div>
                <label for="username" class="text-sm font-medium text-gray-700">Username</label>
                <input 
                    id="username" 
                    name="username" 
                    type="text" 
                    required 
                    class="mt-1 block w-full px-4 py-2 text-gray-900 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                    value="<?= htmlspecialchars($remembered_user); ?>">
            </div>
            
            <!-- Input Password -->
            <div>
                <label for="password" class="text-sm font-medium text-gray-700">Password</label>
                <input 
                    id="password" 
                    name="password" 
                    type="password" 
                    required 
                    class="mt-1 block w-full px-4 py-2 text-gray-900 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    value="<?= htmlspecialchars($remembered_pass); ?>">
            </div>
            
            <!-- Checkbox "Ingat Saya" -->
            <div class="flex items-center">
                <input 
                    id="remember" 
                    name="remember" 
                    type="checkbox" 
                    class="form-checkbox"
                    <?php if(!empty($remembered_user)) echo 'checked'; ?>>
                <label for="remember" class="ml-2 block text-sm text-gray-900">
                    Ingat Saya
                </label>
            </div>

            <!-- Tombol Login -->
            <div>
                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-500 hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                    Login
                </button>
            </div>
        </form>
    </div>

</body>
</html>
