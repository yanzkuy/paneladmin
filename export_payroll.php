<?php

// Sertakan autoloader Composer
require 'vendor/autoload.php';
require_once('config.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Periksa login
check_login('superuser');

if (!$pdo) {
    die("Koneksi database gagal.");
}

// Logika pengambilan dan perhitungan data payroll (SAMA SEPERTI DI index.php)
$payroll_data = [];
$limit_payroll = 950000;
try {
    $stmt_payroll = $pdo->query("SELECT NIK, date_join, no_kpab, Nama_lengkap, Departemen, SIMPANAN_pokok, SIMPANAN_wajib FROM db_anggotakpab WHERE status = 'AKTIF'");
    $all_active_members = $stmt_payroll->fetchAll(PDO::FETCH_ASSOC);

    $stmt_elec_bulanan = $pdo->prepare("SELECT SUM(ANGSURAN_PERBULAN) FROM db_hutangelectronik WHERE NIK = ? AND SISA_BULAN > 0");
    $stmt_sembako_bulanan = $pdo->prepare("SELECT SUM(jumlah) FROM db_hutangsembako WHERE NIK = ? AND nama_barang != 'HITUNGAN POKOK'");

    foreach ($all_active_members as $member) {
        $stmt_elec_bulanan->execute([$member['NIK']]);
        $hutang_elektronik_bulan_ini = (float)$stmt_elec_bulanan->fetchColumn();

        $stmt_sembako_bulanan->execute([$member['NIK']]);
        $hutang_sembako_bulan_ini = (float)$stmt_sembako_bulanan->fetchColumn();

        $simpanan_wajib_bulan_ini = (float)($member['SIMPANAN_wajib'] ?? 0);
        $hutang_koperasi_bulan_ini = $hutang_elektronik_bulan_ini + $hutang_sembako_bulan_ini;
        
        $total_potongan_koperasi = $simpanan_wajib_bulan_ini + $hutang_koperasi_bulan_ini;
        $total_potongan_payroll = min($total_potongan_koperasi, $limit_payroll);
        $selisih = $total_potongan_koperasi > $limit_payroll ? $total_potongan_koperasi - $limit_payroll : 0;

        $payroll_data[] = [
            'date_join' => date('d-m-Y', strtotime($member['date_join'])),
            'no_kpab' => $member['no_kpab'],
            'nik' => $member['NIK'],
            'nama_lengkap' => $member['Nama_lengkap'],
            'departemen' => $member['Departemen'],
            'simpanan_pokok' => (float)$member['SIMPANAN_pokok'],
            'simpanan_wajib' => $simpanan_wajib_bulan_ini,
            'hutang_koperasi' => $hutang_koperasi_bulan_ini,
            'total_potongan_koperasi' => $total_potongan_koperasi,
            'total_potongan_payroll' => $total_potongan_payroll,
            'selisih' => $selisih,
        ];
    }
} catch (\PDOException $e) {
    die("Gagal mengambil data payroll: " . $e->getMessage());
}

// Membuat objek Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Payroll Koperasi');

// Definisi Style
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9E9E9']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

// Header Utama
$sheet->mergeCells('A1:F1');
$sheet->mergeCells('G1:I1')->setCellValue('G1', 'RINCIAN POTONGAN BULAN INI');
$sheet->mergeCells('A2:A3')->setCellValue('A2', 'NO');
$sheet->mergeCells('B2:B3')->setCellValue('B2', 'TGL MASUK');
$sheet->mergeCells('C2:C3')->setCellValue('C2', 'No. KPAB');
$sheet->mergeCells('D2:D3')->setCellValue('D2', 'NIK');
$sheet->mergeCells('E2:E3')->setCellValue('E2', 'NAMA');
$sheet->mergeCells('F2:F3')->setCellValue('F2', 'DEPARTEMEN');
$sheet->mergeCells('J2:J3')->setCellValue('J2', 'TOTAL POTONGAN KOPERASI');
$sheet->mergeCells('K2:K3')->setCellValue('K2', 'TOTAL POTONGAN PAYROLL (Max.950rb)');
$sheet->mergeCells('L2:L3')->setCellValue('L2', 'SELISIH');

// Sub-header
$sheet->setCellValue('G2', 'SIMPANAN POKOK');
$sheet->setCellValue('H2', 'SIMPANAN WAJIB');
$sheet->setCellValue('I2', 'HUTANG KOPERASI');

// Terapkan style ke header
$sheet->getStyle('A1:L3')->applyFromArray($headerStyle);

// Isi data
$row = 4;
$no = 1;
foreach ($payroll_data as $data) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $data['date_join']);
    $sheet->setCellValue('C' . $row, $data['no_kpab']);
    $sheet->setCellValue('D' . $row, $data['nik']);
    $sheet->setCellValue('E' . $row, $data['nama_lengkap']);
    $sheet->setCellValue('F' . $row, $data['departemen']);
    $sheet->setCellValue('G' . $row, $data['simpanan_pokok']);
    $sheet->setCellValue('H' . $row, $data['simpanan_wajib']);
    $sheet->setCellValue('I' . $row, $data['hutang_koperasi']);
    $sheet->setCellValue('J' . $row, $data['total_potongan_koperasi']);
    $sheet->setCellValue('K' . $row, $data['total_potongan_payroll']);
    $sheet->setCellValue('L' . $row, $data['selisih']);
    $row++;
}

// Format currency
$currencyFormat = '"Rp"#,##0';
$sheet->getStyle('G4:L' . ($row - 1))->getNumberFormat()->setFormatCode($currencyFormat);

// Auto-size kolom
foreach (range('A', 'L') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set header untuk download
$filename = 'laporan_payroll_koperasi_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Tulis file ke output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

