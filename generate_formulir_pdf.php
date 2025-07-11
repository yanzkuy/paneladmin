<?php
require_once 'config.php';
// Pastikan Anda telah mengunduh FPDF dan meletakkannya di folder 'fpdf'
require('fpdf/fpdf.php'); 

// Memeriksa apakah pengguna sudah login dan memiliki role yang sesuai
check_login('superuser');

// Periksa apakah ID pengajuan ada
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID Pengajuan tidak valid.');
}
$id_pengajuan = $_GET['id'];

// Ambil data pengajuan dari database
$stmt = $pdo->prepare("SELECT * FROM db_pengajuankpab WHERE id = ?");
$stmt->execute([$id_pengajuan]);
$pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pengajuan) {
    die('Data pengajuan tidak ditemukan.');
}

// Buat class PDF kustom untuk header dan footer
class PDF extends FPDF
{
    // Page header
    function Header()
    {
        // Logo
        if (file_exists('picture/logo.png')) {
            $this->Image('picture/logo.png', 15, 12, 25);
        }
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 7, 'KOPERASI KONSUMEN', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, 'PELITA ABADI BERSAMA', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Jl. Raya Serpong KM.7, Pakualam, Serpong Utara, Tangerang Selatan Banten', 0, 1, 'C');
        
        // Garis bawah
        $this->Line(10, 38, 200, 38);
        $this->Ln(10);
    }

    // Page footer
    function Footer()
    {
        // Posisi di 1.5 cm dari bawah
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        // Nomor halaman
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Fungsi untuk membuat baris data dengan format: Label : Isi
    function DataRow($label, $data)
    {
        $this->SetFont('Arial', '', 11);
        $this->Cell(50, 7, $label, 0, 0);
        $this->Cell(5, 7, ':', 0, 0);
        $this->MultiCell(0, 7, $data, 0, 'L');
    }
}

// =================================================================================
// --- PEMBUATAN DOKUMEN PDF ---
// =================================================================================

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();

// --- HALAMAN 1: FORMULIR PENDAFTARAN ---
$pdf->AddPage();

// Judul Formulir
$pdf->SetFont('Arial', 'BU', 12);
$pdf->Cell(0, 7, 'FORMULIR PENDAFTARAN ANGGOTA', 0, 1, 'C');
$pdf->Ln(5);

// Status Anggota
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, 'STATUS *) :', 0, 1);
$pdf->Cell(5);
$pdf->Cell(0, 7, '( X )  1. ANGGOTA BIASA', 0, 1);
$pdf->Cell(5);
$pdf->Cell(0, 7, '(    )  2. ANGGOTA LUAR BIASA', 0, 1);
$pdf->Ln(5);

// Data Diri
$pdf->Cell(0, 7, 'Yang bertanda tangan di bawah ini:', 0, 1);
$pdf->Ln(2);
$pdf->DataRow('Nama lengkap', $pengajuan['nama_lengkap']);
$pdf->DataRow('NIK', $pengajuan['nik']);
$pdf->DataRow('Tempat / Tanggal Lahir', $pengajuan['ttl']);
$pdf->DataRow('Alamat rumah', $pengajuan['alamat']);
$pdf->DataRow('No. Telp. / HP', $pengajuan['no_ponsel']);
$pdf->DataRow('Departemen', $pengajuan['departemen']);
$pdf->Ln(5);

// Pernyataan Pendaftaran
$pdf->MultiCell(0, 6, 'Secara sukarela mendaftarkan diri menjadi Anggota KOPERASI PELITA ABADI BERSAMA ( KPAB ) mulai :', 0, 'L');
$pdf->Ln(2);
$pdf->DataRow('Tanggal', date('d F Y', strtotime($pengajuan['tanggal_pengajuan'])));
$pdf->DataRow('Nomor Anggota', '( diisi KPAB )');
$pdf->DataRow('Simpanan Pokok', 'Rp. 50.000,-');
$pdf->DataRow('Simpanan Wajib', 'Rp. 25.000,- / bulan');
$pdf->Ln(5);

// Cara Pembayaran
$pdf->MultiCell(0, 6, 'Pembayaran Simpanan Pokok Rp. 50.000,- dan Simpanan Wajib pertama kali Rp. 25.000, dilaksanakan dengan cara*):', 0, 'L');
$pdf->Cell(5);
$pdf->Cell(0, 7, '(    )  1. Tunai bersama surat ini', 0, 1);
$pdf->Cell(5);
$pdf->Cell(0, 7, '( X )  2. Potong gaji oleh payroll bulan berikutnya pada saat penggajian', 0, 1);
$pdf->Ln(5);

// Pernyataan Kesanggupan
$pdf->MultiCell(0, 6, 'Setelah menjadi anggota KPAB, saya menyatakan diri sanggup :', 0, 'L');
$pdf->Cell(5);
$pdf->MultiCell(0, 6, "1. Menaati Anggaran Dasar KPAB;\n" .
                       "2. Menaati Anggaran Rumah Tangga KPAB;\n" .
                       "3. Menaati Peraturan Khusus KPAB;\n" .
                       "4. Secara aktif memajukan KPAB;", 0, 'L');
$pdf->Ln(5);

// Tanda Tangan
$pdf->Cell(120);
$pdf->Cell(0, 6, 'Tangerang Selatan, ' . date('d F Y'), 0, 1, 'L');
$pdf->Ln(20);
$pdf->Cell(120);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, '( ' . $pengajuan['nama_lengkap'] . ' )', 0, 1, 'L');
$pdf->Cell(120);
$pdf->Cell(0, 6, 'NIK. ' . $pengajuan['nik'], 0, 1, 'L');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, '*) lingkari salah satu', 0, 1);

// --- HALAMAN 2: SURAT KUASA ---
$pdf->AddPage();

// Judul Surat Kuasa
$pdf->SetFont('Arial', 'BU', 12);
$pdf->Cell(0, 7, 'SURAT KUASA PEMOTONGAN GAJI', 0, 1, 'C');
$pdf->Ln(10);

// Isi Surat Kuasa
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 6, "Berkaitan dengan adanya simpanan wajib bagi anggota Koperasi dan adanya Fasilitas Pinjaman yang diterima dari KOPERASI PELITA ABADI BERSAMA, Kami memberi KUASA sepenuhnya kepada KOPERASI PELITA ABADI BERSAMA untuk:", 0, 'L');
$pdf->Ln(5);

$pdf->Cell(10);
$pdf->MultiCell(0, 6, "1. Memotong Gaji/Honor/pendapatan lain setiap bulannya sejumlah yang ditentukan sesuai dengan kewajiban kepada KOPERASI PELITA ABADI BERSAMA sampai dengan Pinjaman tersebut dinyatakan lunas oleh KOPERASI PELITA ABADI BERSAMA", 0, 'L');
$pdf->Ln(3);

$pdf->Cell(10);
$pdf->MultiCell(0, 6, "2. Memotong uang Pesangon ataupun hak-hak lainnya dari pemberi kuasa yang diterima dari PT. PRATAMA ABADI INDUSTRI dalam hal PEMUTUSAN HUBUNGAN KERJA (PHK), Meninggal Dunia atau hal lainnya sampai Pinjaman tersebut dinyatakan lunas oleh KOPERASI PELITA ABADI BERSAMA.", 0, 'L');
$pdf->Ln(3);

$pdf->Cell(10);
$pdf->MultiCell(0, 6, "3. Memotong Gaji/Honor/pendapatan karyawan anggota koperasi oleh payroll perusahaan terkait dengan adanya pemotongan gaji berkaitan adanya simpanan wajib bagi anggota Koperasi", 0, 'L');
$pdf->Ln(5);

$pdf->MultiCell(0, 6, "Demikian Surat Kuasa ini kami buat dengan sebenar - benarnya dan diberikan Hak Substitusi untuk dapat dipergunakan sebagaimana mestinya.", 0, 'L');
$pdf->Ln(5);

$pdf->MultiCell(0, 6, "Surat Kuasa ini berlaku terus, termasuk dalam hal terjadi perubahan jumlah angsuran apabila ada, dan kuasa ini tidak akan kami tarik kembali, maupun akan batal oleh sebab-sebab yang tercantum dalam Pasal 1813, Pasal 1816 KUH Perdata dan sebab-sebab apapun sampai kewajiban Pemberi Kuasa Lunas.", 0, 'L');
$pdf->Ln(10);

// Tanda Tangan Surat Kuasa
$current_year = date('Y');
$pdf->Cell(0, 6, '................................., .......................................... ' . $current_year, 0, 1, 'R');
$pdf->Ln(5);

// Kolom Tanda Tangan
$x_pos = $pdf->GetX();
$y_pos = $pdf->GetY();

$pdf->Cell(95, 7, 'PEMOHON,', 0, 0, 'C');
$pdf->Cell(95, 7, 'PENERIMA KUASA,', 0, 1, 'C');
$pdf->Ln(5);

$pdf->Cell(95, 7, 'Materai 10,000', 0, 0, 'C');
$pdf->Ln(20);

$pdf->SetFont('Arial', 'U', 11);
$pdf->Cell(95, 7, '( ' . $pengajuan['nama_lengkap'] . ' )', 0, 0, 'C');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(95, 7, '( Koperasi Pelita Abadi Bersama )', 0, 1, 'C');


// Output PDF ke browser
$pdf->Output('I', 'Formulir-Pendaftaran-'.$pengajuan['nik'].'.pdf');
