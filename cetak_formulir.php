<?php
require_once('config.php');
require_once('tcpdf/tcpdf.php'); // Pastikan path ke TCPDF benar

check_login('superuser');

if (!$pdo) {
    die("Koneksi database gagal.");
}

// Ambil ID dari URL
$id_pengajuan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_pengajuan <= 0) {
    die("ID Pengajuan tidak valid.");
}

// Ambil data pengajuan dari database
$stmt = $pdo->prepare("SELECT * FROM db_pengajuankpab WHERE id = ?");
$stmt->execute([$id_pengajuan]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Data pengajuan tidak ditemukan.");
}

// --- Custom TCPDF Class dengan Header ---
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        // Logo
        // Path gambar relatif dari file ini. Sesuaikan jika perlu.
        $image_file = 'picture/logo.png'; 
        $this->Image($image_file, 15, 10, 25, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Set font
        $this->SetFont('helvetica', 'B', 14);
        // Judul
        $this->Cell(0, 5, 'KOPERASI PELITA ABADI BERSAMA', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(6);
        
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 5, 'Jl. Pelita Jaya I No. 1, RT.001/RW.001, Kel. Jatake, Kec. Jatiuwung, Kota Tangerang, Banten 15136', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(4);
        $this->Cell(0, 5, 'Telp: (021) 5901772 | Email: kpab@pelita-abr.com', 0, false, 'C', 0, '', 0, false, 'M', 'M');

        // Garis bawah header
        $this->Line(15, 30, $this->getPageWidth() - 15, 30);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Halaman '.$this->getAliasNumPage().' dari '.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat dokumen PDF baru
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set informasi dokumen
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Superuser KPAB');
$pdf->SetTitle('Formulir Permohonan Anggota - ' . $data['nama_lengkap']);
$pdf->SetSubject('Formulir Permohonan Anggota KPAB');

// Set header dan footer
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set margin
$pdf->SetMargins(PDF_MARGIN_LEFT, 35, PDF_MARGIN_RIGHT); // Margin atas 35mm untuk ruang header
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Tambah halaman 1
$pdf->AddPage();

// --- KONTEN HALAMAN 1 ---

// Judul Formulir
$pdf->SetFont('helvetica', 'BU', 12);
$pdf->Cell(0, 10, 'FORMULIR PERMOHONAN MENJADI ANGGOTA', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->Write(5, 'Yang bertanda tangan di bawah ini:', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(2);

// Fungsi untuk membuat baris data
function createDataRow($pdf, $label, $value) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 6, $label, 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'C');
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->MultiCell(0, 6, $value, 0, 'L', false, 1);
}

createDataRow($pdf, 'Nama Lengkap', $data['nama_lengkap']);
createDataRow($pdf, 'NIK', $data['nik']);
createDataRow($pdf, 'Tempat, Tanggal Lahir', $data['ttl']);
createDataRow($pdf, 'Jenis Kelamin', $data['jenis_kelamin']);
createDataRow($pdf, 'Agama', $data['agama']);
createDataRow($pdf, 'Pendidikan Terakhir', $data['pendidikan']);
createDataRow($pdf, 'Alamat Rumah', $data['alamat']);
createDataRow($pdf, 'No. Telp / HP', $data['no_ponsel']);
createDataRow($pdf, 'Departemen', $data['departemen']);
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->Write(5, 'Dengan ini mengajukan permohonan untuk menjadi Anggota Koperasi Pelita Abadi Bersama (KPAB), serta bersedia mematuhi Anggaran Dasar, Anggaran Rumah Tangga dan ketentuan-ketentuan lain yang berlaku.', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(2);
$pdf->Write(5, 'Sebagai pemenuhan kewajiban, bersama ini saya sertakan:', '', 0, 'L', true, 0, false, false, 0);

// Simpanan
createDataRow($pdf, 'Simpanan Pokok', 'Rp. 50.000,- (Lima Puluh Ribu Rupiah)');
createDataRow($pdf, 'Simpanan Wajib', 'Rp. 25.000,- (Dua Puluh Lima Ribu Rupiah) / Bulan');
$pdf->Ln(5);

$pdf->Write(5, 'Demikian permohonan ini saya buat dengan data yang sebenar-benarnya dan untuk dipergunakan sebagaimana mestinya.', '', 0, 'L', true, 0, false, false, 0);
$pdf->Ln(10);

// Tanda Tangan
$pdf->Cell(120, 5, '', 0, 0);
$pdf->Cell(0, 5, 'Tangerang, ' . date('d F Y'), 0, 1, 'C');
$pdf->Ln(1);
$pdf->Cell(120, 5, '', 0, 0);
$pdf->Cell(0, 5, 'Hormat saya,', 0, 1, 'C');
$pdf->Ln(20);
$pdf->Cell(120, 5, '', 0, 0);
$pdf->SetFont('helvetica', 'BU', 10);
$pdf->Cell(0, 5, '( ' . $data['nama_lengkap'] . ' )', 0, 1, 'C');


// Tambah halaman 2
$pdf->AddPage();

// --- KONTEN HALAMAN 2 ---

$pdf->SetFont('helvetica', 'BU', 12);
$pdf->Cell(0, 10, 'PERNYATAAN DAN PERSETUJUAN', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$html = '
<p>Saya yang bertanda tangan di bawah ini:</p>
<table cellpadding="2">
    <tr>
        <td width="150">Nama Lengkap</td>
        <td width="10">:</td>
        <td width="350"><b>' . htmlspecialchars($data['nama_lengkap']) . '</b></td>
    </tr>
    <tr>
        <td>NIK</td>
        <td>:</td>
        <td><b>' . htmlspecialchars($data['nik']) . '</b></td>
    </tr>
</table>
<br><br>
<p>Dengan ini menyatakan dengan sadar dan tanpa paksaan dari pihak manapun, bahwa saya setuju untuk:</p>
<ol>
    <li>Memberikan kuasa penuh kepada Koperasi Pelita Abadi Bersama untuk melakukan pemotongan gaji saya setiap bulannya melalui bagian Personalia PT. Pelita Abadi Internusa, sesuai dengan jumlah tagihan yang ada di Koperasi.</li>
    <li>Menyelesaikan seluruh kewajiban/hutang saya di Koperasi Pelita Abadi Bersama apabila saya mengundurkan diri atau terkena Pemutusan Hubungan Kerja (PHK) dari PT. Pelita Abadi Internusa.</li>
    <li>Apabila saya tidak dapat melunasi kewajiban saya, maka saya bersedia jika sisa hutang tersebut dipotong dari uang pesangon atau hak-hak lainnya yang akan saya terima dari perusahaan.</li>
    <li>Apabila uang pesangon atau hak-hak lainnya tidak mencukupi untuk melunasi sisa hutang, maka saya bersedia untuk melunasinya secara pribadi dengan cara dicicil sesuai kesepakatan dengan pengurus Koperasi.</li>
    <li>Bersedia mematuhi dan melaksanakan semua peraturan yang telah ditetapkan oleh Koperasi Pelita Abadi Bersama.</li>
    <li>Apabila di kemudian hari saya mengingkari pernyataan ini, maka saya bersedia dituntut sesuai dengan hukum yang berlaku di Negara Republik Indonesia.</li>
</ol>
<br>
<p>Demikian surat pernyataan ini saya buat dengan sebenarnya dalam keadaan sadar, sehat jasmani dan rohani, serta tanpa ada paksaan dari pihak manapun.</p>
';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(10);

// Tanda Tangan
$pdf->Cell(120, 5, '', 0, 0);
$pdf->Cell(0, 5, 'Tangerang, ' . date('d F Y'), 0, 1, 'C');
$pdf->Ln(1);
$pdf->Cell(120, 5, '', 0, 0);
$pdf->Cell(0, 5, 'Pemohon,', 0, 1, 'C');
$pdf->Ln(20);
$pdf->Cell(120, 5, '', 0, 0);
$pdf->SetFont('helvetica', 'BU', 10);
$pdf->Cell(0, 5, '( ' . $data['nama_lengkap'] . ' )', 0, 1, 'C');


// Tutup dan output PDF
$pdf->Output('Formulir_KPAB_' . $data['nik'] . '.pdf', 'I');
