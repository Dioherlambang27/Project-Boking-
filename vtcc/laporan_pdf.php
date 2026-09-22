<?php
/**
 * FILE: laporan_pdf.php
 * VTCC - Cetak Laporan PDF menggunakan FPDF
 */
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'libs/fpdf.php';
require_login();

$bulan = clean($_GET['bulan'] ?? date('Y-m'));
$arr   = explode('-', $bulan);
$y     = $arr[0] ?? date('Y');
$m     = $arr[1] ?? date('m');
$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// Statistik
$stmt = $koneksi->prepare(
    "SELECT COUNT(*) total, COALESCE(SUM(total_bayar),0) pendapatan,
     SUM(CASE WHEN status='Selesai' THEN 1 ELSE 0 END) selesai,
     SUM(CASE WHEN status='Dibatalkan' THEN 1 ELSE 0 END) batal
     FROM booking WHERE YEAR(tanggal_booking)=? AND MONTH(tanggal_booking)=?"
);
$stmt->bind_param('ii', $y, $m); $stmt->execute();
$stat = $stmt->get_result()->fetch_assoc();

// Detail booking
$stmt2 = $koneksi->prepare(
    "SELECT b.kode_booking, b.tanggal_booking, b.jam_booking,
            m.nama_mobil, p.nama_pelanggan, p.no_hp,
            b.estimasi_km, b.total_bayar, b.status,
            pm.metode, pm.status_bayar
     FROM booking b
     JOIN mobil_towing m ON m.id_mobil = b.id_mobil
     JOIN pelanggan p ON p.id_pelanggan = b.id_pelanggan
     LEFT JOIN pembayaran pm ON pm.id_booking = b.id_booking
     WHERE YEAR(b.tanggal_booking)=? AND MONTH(b.tanggal_booking)=?
     ORDER BY b.tanggal_booking ASC, b.jam_booking ASC"
);
$stmt2->bind_param('ii', $y, $m); $stmt2->execute();
$rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// ==================== FPDF ====================
class PDF extends FPDF {
    public $teal = [0, 128, 128];
    public $yellow = [250, 204, 21];
    public $dark = [15, 23, 42];

    function Header() {
        // Header bar teal
        $this->SetFillColor($this->teal[0], $this->teal[1], $this->teal[2]);
        $this->Rect(0, 0, 210, 30, 'F');
        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','B',18);
        $this->SetXY(10, 8);
        $this->Cell(0, 10, 'VTCC - Vehicle Testing And Certification Center', 0, 1, 'L');
        $this->SetFont('Arial','',10);
        $this->SetXY(10, 19);
        $this->Cell(0, 6, 'Laporan Booking & Pendapatan', 0, 1, 'L');
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
        $this->Ln(8);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFillColor($this->teal[0], $this->teal[1], $this->teal[2]);
        $this->Rect(0, $this->GetY()-2, 210, 20, 'F');
        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 8, 'VTCC - Dicetak pada: ' . date('d/m/Y H:i') . ' WIB  |  Hal. ' . $this->PageNo(), 0, 0, 'C');
        $this->SetTextColor($this->dark[0], $this->dark[1], $this->dark[2]);
    }
}

$pdf = new PDF('L','mm','A4'); // Landscape
$pdf->AddPage();
$pdf->SetMargins(10, 38, 10);
$pdf->SetAutoPageBreak(true, 20);

// Judul Periode
$pdf->SetFont('Arial','B',14);
$pdf->SetTextColor($pdf->teal[0], $pdf->teal[1], $pdf->teal[2]);
$pdf->Cell(0, 8, 'Periode: ' . $nama_bulan[(int)$m] . ' ' . $y, 0, 1, 'C');
$pdf->SetTextColor(15, 23, 42);
$pdf->Ln(4);

// Kotak statistik
$pdf->SetFont('Arial','B',10);
$stats_data = [
    ['Total Booking', $stat['total']],
    ['Selesai', $stat['selesai']],
    ['Dibatalkan', $stat['batal']],
    ['Total Pendapatan', 'Rp '.number_format($stat['pendapatan'],0,',','.')],
];
$box_w = 65;
$pdf->SetXY(10, $pdf->GetY());
foreach ($stats_data as $i => $sd) {
    $x = 10 + $i * ($box_w + 3);
    // Kotak
    $pdf->SetFillColor(239, 246, 255);
    $pdf->Rect($x, $pdf->GetY(), $box_w, 22, 'F');
    $pdf->SetFillColor($pdf->yellow[0], $pdf->yellow[1], $pdf->yellow[2]);
    $pdf->Rect($x, $pdf->GetY(), $box_w, 2, 'F');
    $pdf->SetXY($x, $pdf->GetY() + 4);
    $pdf->SetFont('Arial','',8);
    $pdf->SetTextColor(100,116,139);
    $pdf->Cell($box_w, 5, strtoupper($sd[0]), 0, 0, 'C');
    $pdf->SetXY($x, $pdf->GetY() + 6);
    $pdf->SetFont('Arial','B',13);
    $pdf->SetTextColor(15,23,42);
    $pdf->Cell($box_w, 8, (string)$sd[1], 0, 0, 'C');
}
$pdf->SetTextColor(15,23,42);
$pdf->Ln(28);

// Tabel header
$cols = [
    ['Kode Booking', 32],
    ['Tanggal', 22],
    ['Pelanggan', 38],
    ['Armada', 42],
    ['Km', 14],
    ['Total', 30],
    ['Status', 26],
    ['Bayar', 22],
    ['Metode', 24],
];

$pdf->SetFillColor($pdf->teal[0], $pdf->teal[1], $pdf->teal[2]);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',9);
foreach ($cols as $c) {
    $pdf->Cell($c[1], 8, $c[0], 0, 0, 'C', true);
}
$pdf->Ln();

// Tabel body
$pdf->SetTextColor(15,23,42);
$pdf->SetFont('Arial','',8);
$fill  = false;
$total_baris = 0;
foreach ($rows as $r) {
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 246 : 255, $fill ? 255 : 255);
    $pdf->Cell(32, 7, $r['kode_booking'], 0, 0, 'L', $fill);
    $pdf->Cell(22, 7, date('d/m/Y', strtotime($r['tanggal_booking'])), 0, 0, 'C', $fill);
    $pdf->Cell(38, 7, mb_substr($r['nama_pelanggan'], 0, 18), 0, 0, 'L', $fill);
    $pdf->Cell(42, 7, mb_substr($r['nama_mobil'], 0, 22), 0, 0, 'L', $fill);
    $pdf->Cell(14, 7, number_format($r['estimasi_km'],0).' km', 0, 0, 'C', $fill);
    $pdf->Cell(30, 7, 'Rp '.number_format($r['total_bayar'],0,',','.'), 0, 0, 'R', $fill);
    $pdf->Cell(26, 7, $r['status'], 0, 0, 'C', $fill);
    $pdf->Cell(22, 7, $r['status_bayar'] ?? '-', 0, 0, 'C', $fill);
    $pdf->Cell(24, 7, $r['metode'] ?? '-', 0, 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
    $total_baris++;
}

// Baris total
$pdf->SetFillColor($pdf->teal[0], $pdf->teal[1], $pdf->teal[2]);
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(32+22+38+42+14, 7, 'TOTAL (' . $total_baris . ' booking)', 0, 0, 'L', true);
$pdf->Cell(30, 7, 'Rp '.number_format($stat['pendapatan'],0,',','.'), 0, 0, 'R', true);
$pdf->Cell(26+22+24, 7, '', 0, 1, 'C', true);

// Output PDF
$pdf->Output('D', 'Laporan_VTCC_' . $bulan . '.pdf');
exit;
