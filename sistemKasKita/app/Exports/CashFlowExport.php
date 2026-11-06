<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashFlowExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        private string $cohortName,
        private string $startDate,
        private string $endDate,
        private float $saldoAwal,
        private float $totalPemasukan,
        private float $totalPengeluaran,
        private float $saldoAkhir,
        private array $transactions
    ) {
    }

    public function array(): array
    {
        $rows = [
            ['LAPORAN KEUANGAN KAS'],
            ['KasKita - ' . $this->cohortName],
            ['Periode', $this->startDate, 's.d.', $this->endDate],
            [],
            ['RINGKASAN KEUANGAN'],
            ['Saldo Awal', $this->saldoAwal, 'Total Pemasukan', $this->totalPemasukan],
            ['Total Pengeluaran', $this->totalPengeluaran, 'Saldo Akhir', $this->saldoAkhir],
            [],
            [
                'No.',
                'Tanggal',
                'Kategori',
                'Jenis Transaksi',
                'Kelas',
                'Jumlah',
            ],
        ];

        foreach ($this->transactions as $index => $transaction) {
            $rows[] = [
                $index + 1,
                $transaction->transaction_date->format('d/m/Y H:i'),
                $transaction->category,
                ucfirst($transaction->transaction_type),
                $transaction->classroom?->name ?? '-',
                (float) $transaction->amount,
            ];
        }

        if (count($this->transactions) === 0) {
            $rows[] = [
                '',
                '',
                'Tidak ada transaksi pada periode ini.',
                '',
                '',
                0,
            ];
        }

        return $rows;
    }


    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Judul laporan
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');

        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);

        $sheet->getStyle('A2:F2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);

        // Ringkasan keuangan
        $sheet->getStyle('A4:D6')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        $sheet->getStyle('A4:D4')->getFont()->setBold(true);
        $sheet->getStyle('A5:D6')->getFont()->setBold(true);

        // Format nominal ringkasan
        $currencyFormat = '"Rp" #,##0;[Red]-"Rp" #,##0;"Rp" 0';

        $sheet->getStyle('B5:B6')
            ->getNumberFormat()
            ->setFormatCode($currencyFormat);

        $sheet->getStyle('D5:D6')
            ->getNumberFormat()
            ->setFormatCode($currencyFormat);

        // Header tabel transaksi
        $sheet->getStyle('A7:F7')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => '1B2A6B'],
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center',
            ],
        ]);

        // Border tabel
        if ($lastRow >= 7) {
            $sheet->getStyle('A7:F' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => 'thin',
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
        }

        // Format nominal transaksi
        if ($lastRow >= 8) {
            $sheet->getStyle('F8:F' . $lastRow)
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0');
        }

        // Pengaturan tabel
        $sheet->getStyle('A7:F' . $lastRow)
            ->getAlignment()
            ->setVertical('center');

        $sheet->getStyle('A7:F7')
            ->getAlignment()
            ->setHorizontal('center');

        $sheet->freezePane('A8');

        return [];
    }


    public function title(): string
    {
        return 'Laporan Keuangan';
    }
}
