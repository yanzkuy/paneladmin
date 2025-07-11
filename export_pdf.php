<?php
// Aktifkan error reporting untuk debugging jika terjadi halaman putih
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Menggunakan __DIR__ untuk path yang absolut dan andal
require_once __DIR__ . '/config.php';
// require_once __DIR__ . '/log_activity.php'; // Jika file log_activity.php ada dan digunakan
require_once __DIR__ . '/fpdf/fpdf.php'; // Pastikan path ini benar

// --- Memeriksa Login & Koneksi Database ---
check_login('superuser');
if (!$pdo) {
    die($db_error);
}

// --- Validasi Input NIK ---
if (!isset($_GET['nik']) || empty($_GET['nik'])) {
    die('Error: NIK karyawan tidak disediakan.');
}
$nik_search = trim($_GET['nik']);

// --- Pengambilan Data dari Database (Logika dari sistem yang ada) ---
$employee = null;
$total_electronic_debt = 0;
$total_sembako_debt_overall = 0;
$total_akumulasi_selisih = 0;
$potongan_pokok = 0;
$mandatory_savings = 0;
$principal_saving = 0;

try {
    // Ambil data utama anggota
    $stmt = $pdo->prepare("SELECT NIK, no_kpab, Nama_lengkap AS NAMA, Departemen AS DEPARTEMEN, date_join AS TGL_MASUK, simpanan_pokok, SIMPANAN_wajib FROM db_anggotakpab WHERE NIK = ?");
    $stmt->execute([$nik_search]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        die("Error: Karyawan dengan NIK '{$nik_search}' tidak ditemukan.");
    }

    $join_date_obj = parse_db_date($employee['TGL_MASUK']);
    $principal_saving = clean_currency($employee['simpanan_pokok'] ?? 50000);

    // Ambil hutang elektronik
    $stmt_elec = $pdo->prepare("SELECT SISA_BULAN, ANGSURAN_PERBULAN FROM db_hutangelectronik WHERE NIK = ? AND SISA_BULAN > 0");
    $stmt_elec->execute([$nik_search]);
    foreach ($stmt_elec->fetchAll(PDO::FETCH_ASSOC) as $debt) {
        $total_electronic_debt += clean_currency($debt['ANGSURAN_PERBULAN']) * (int)$debt['SISA_BULAN'];
    }

    // Ambil hutang sembako dan pisahkan hitungan pokok
    $stmt_sem_all = $pdo->prepare("SELECT nama_barang, jumlah FROM db_hutangsembako WHERE NIK = ?");
    $stmt_sem_all->execute([$nik_search]);
    foreach($stmt_sem_all->fetchAll(PDO::FETCH_ASSOC) as $item) {
        if(!empty(trim($item['jumlah']))) {
            $jumlah_cleaned = clean_currency($item['jumlah']);
            if (strtoupper(trim($item['nama_barang'])) === 'HITUNGAN POKOK') {
                $potongan_pokok += $jumlah_cleaned;
            } else {
                $total_sembako_debt_overall += $jumlah_cleaned;
            }
        }
    }

    // Ambil riwayat selisih
    $stmt_selisih = $pdo->prepare("SELECT jumlah_selisih FROM riwayat_selisih WHERE nik = ? AND status = 'terakumulasi'");
    $stmt_selisih->execute([$nik_search]);
    foreach($stmt_selisih->fetchAll(PDO::FETCH_ASSOC) as $selisih) {
        $total_akumulasi_selisih += $selisih['jumlah_selisih'];
    }

    // Hitung total simpanan wajib
    if ($join_date_obj && isset($employee['SIMPANAN_wajib'])) {
        $interval = $join_date_obj->diff(new DateTime());
        $months_joined = ($interval->y * 12) + $interval->m + 1;
        $monthly_saving_amount = clean_currency($employee['SIMPANAN_wajib']);
        $mandatory_savings = $months_joined * $monthly_saving_amount;
    }
    
    // Kalkulasi Total
    $total_savings = $mandatory_savings + $principal_saving;
    $total_hutang_lainnya = $total_sembako_debt_overall + $potongan_pokok + $total_akumulasi_selisih;
    $total_overall_debt = $total_electronic_debt + $total_hutang_lainnya;

} catch (\PDOException $e) {
    die("Kesalahan Database: " . $e->getMessage());
}

// --- Kelas PDF untuk SURAT PERNYATAAN (Diadopsi dari kode Anda) ---
class PDF_Pernyataan extends FPDF
{
    function Header() {
        // Path ke gambar logo (pastikan folder 'picture' ada dan berisi logo.png)
        // Tanda @ digunakan untuk menekan error jika file tidak ditemukan
        @$this->Image('picture/logo.png', 10, 8, 25);
        
        $this->SetFont('Times', 'B', 14);
        $this->Cell(0, 7, 'KOPERASI KONSUMEN', 0, 1, 'C');
        $this->SetFont('Times', 'B', 18);
        $this->Cell(0, 9, 'PELITA ABADI BERSAMA', 0, 1, 'C');
        
        $this->SetFont('Times', '', 10);
        $this->Cell(0, 5, 'Jl. Raya Serpong KM.7, Pakualam, Serpong Utara, Tangerang Selatan - Banten', 0, 1, 'C');

        $this->SetLineWidth(1);
        $this->Line(10, 36, 200, 36);
        $this->SetLineWidth(0.2);
        $this->Ln(12);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Dokumen ini dicetak melalui sistem pada ' . date('d F Y, H:i:s'), 0, 0, 'L');
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
    }

    function DataRow($label, $value, $is_currency = false) {
        $this->SetFont('Times', '', 12);
        $this->Cell(10); 
        $this->Cell(50, 7, $label, 0, 0, 'L');
        $this->Cell(5, 7, ':', 0, 0, 'C');
        $this->SetFont('Times', 'B', 12);
        if($is_currency) { $value = 'Rp ' . number_format($value, 0, ',', '.'); }
        $this->MultiCell(0, 7, $value, 0, 'L');
    }

    function TtdTable($nama_anggota) {
        $this->Ln(10);
        $this->SetFont('Times', 'B', 11);
        $this->Cell(63.3, 7, 'PEMOHON', 1, 0, 'C');
        $this->Cell(63.3, 7, 'ADMIN', 1, 0, 'C');
        $this->Cell(63.3, 7, 'KETUA', 1, 1, 'C');
        $this->Cell(63.3, 30, '', 'LR', 0);
        $this->Cell(63.3, 30, '', 'LR', 0);
        $this->Cell(63.3, 30, '', 'LR', 1);
        $this->SetFont('Times', 'U', 11);
        $this->Cell(63.3, 7, strtoupper($nama_anggota), 'LBR', 0, 'C');
        $this->SetFont('Times', '', 11);
        $this->Cell(63.3, 7, '(.........................)', 'LBR', 0, 'C');
        $this->Cell(63.3, 7, '(.........................)', 'LBR', 1, 'C');
    }
}

// --- PEMBUATAN PDF ---
$pdf = new PDF_Pernyataan('P', 'mm', 'A4');
$pdf->AddPage();

// Judul Surat
$pdf->SetFont('Times', 'BU', 14);
$pdf->Cell(0, 7, 'SURAT PERNYATAAN PENYELESAIAN', 0, 1, 'C');
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(0, 7, 'Nomor: ....../SPP/KPAB/' . date('m/Y'), 0, 1, 'C');
$pdf->Ln(8);

// Pembuka
$pdf->SetFont('Times', '', 12);
$pdf->MultiCell(0, 6, 'Saya yang bertanda tangan di bawah ini:', 0, 'L');
$pdf->Ln(2);

// Data Anggota
$pdf->DataRow('NIK', $employee['NIK']);
$pdf->DataRow('No. KPAB', $employee['no_kpab']);
$pdf->DataRow('Nama', $employee['NAMA']);
$pdf->DataRow('Departemen', $employee['DEPARTEMEN']);
$pdf->DataRow('Tanggal Daftar', date('d F Y', strtotime($employee['TGL_MASUK'])));
$pdf->Ln(5);

// Paragraf Pernyataan
$pdf->SetFont('Times', '', 12);
$pdf->MultiCell(0, 6, 'Dengan ini menyatakan bahwa seluruh data keuangan saya sebagai anggota Koperasi Konsumen Pelita Abadi Bersama adalah sebagai berikut:', 0, 'L');
$pdf->Ln(2);

// Rincian Keuangan
$pdf->DataRow('Hutang Sembako & Lainnya', $total_hutang_lainnya, true);
$pdf->DataRow('Hutang Elektronik', $total_electronic_debt, true);

// Total Hutang (dengan warna)
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(10); $pdf->Cell(50, 7, 'Total Hutang', 0, 0, 'L'); $pdf->Cell(5, 7, ':', 0, 0, 'C'); $pdf->SetTextColor(220,53,69); // Merah
$pdf->MultiCell(0, 7, 'Rp ' . number_format($total_overall_debt, 0, ',', '.'), 0, 'L');
$pdf->SetTextColor(0,0,0);

// Total Simpanan (dengan warna)
$pdf->SetFont('Times', 'B', 12);
$pdf->Cell(10); $pdf->Cell(50, 7, 'Total Simpanan', 0, 0, 'L'); $pdf->Cell(5, 7, ':', 0, 0, 'C'); $pdf->SetTextColor(25,135,84); // Hijau
$pdf->MultiCell(0, 7, 'Rp ' . number_format($total_savings, 0, ',', '.'), 0, 'L');
$pdf->SetTextColor(0,0,0);
$pdf->Ln(5);

// Paragraf Penutup
$pdf->SetFont('Times', '', 12);
$pdf->MultiCell(0, 6, "Dengan ini saya menyatakan bahwa data yang tercantum di atas adalah benar dan dapat dipertanggungjawabkan. Saya menyetujui rincian tersebut untuk digunakan sebagai dasar penyelesaian hak dan kewajiban saya sebagai anggota.", 0, 'J');
$pdf->Ln(5);
$pdf->MultiCell(0, 6, "Demikian surat pernyataan ini saya buat dengan sadar dan tanpa ada paksaan dari pihak manapun.", 0, 'J');
$pdf->Ln(8);

// Tabel Tanda Tangan
$pdf->TtdTable($employee['NAMA']);

// --- OUTPUT PDF ---
$filename = "Surat_Pernyataan_" . $nik_search . "_" . date('Ymd') . ".pdf";
$output_type = isset($_GET['download']) ? 'D' : 'I';
$pdf->Output($output_type, $filename);
?>
