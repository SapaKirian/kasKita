<?php

namespace App\Filament\Widgets;

use App\Models\CashTransaction;
use Filament\Widgets\ChartWidget;

class CashFlowChart extends ChartWidget
{
    protected ?string $heading = 'Grafik Arus Kas';

    protected int|string|array $columnSpan = 'full';

    protected ?string $description = 'Perbandingan pemasukan dan pengeluaran selama satu tahun.';

    public static function canView(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'bendahara_angkatan') {
            return $user->cohort_id !== null;
        }

        if ($user->role === 'bendahara_kelas') {
            return $user->class_id !== null;
        }

        return false;
    }

    protected function getData(): array
    {
        $user = auth()->user();

        $labels = [
            'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember',
        ];

        $pemasukan = array_fill(0, 12, 0);
        $pengeluaran = array_fill(0, 12, 0);

        if (!$user) {
            return [
                'datasets' => [],
                'labels' => $labels,
            ];
        }

        $selectedYear = (int) ($this->filter ?? now()->year);

        $query = CashTransaction::query()
            ->whereYear('transaction_date', $selectedYear);

        if ($user->role === 'bendahara_angkatan') {
            if (!$user->cohort_id) {
                return [
                    'datasets' => [],
                    'labels' => $labels,
                ];
            }

            $query->where('cohort_id', $user->cohort_id);

        } elseif ($user->role === 'bendahara_kelas') {
            if (!$user->class_id) {
                return [
                    'datasets' => [],
                    'labels' => $labels,
                ];
            }

            $query->where('class_id', $user->class_id);

        } else {
            return [
                'datasets' => [],
                'labels' => $labels,
            ];
        }

        $transactions = $query
            ->selectRaw('
                MONTH(transaction_date) as bulan,
                transaction_type,
                SUM(amount) as total
            ')
            ->groupByRaw('MONTH(transaction_date), transaction_type')
            ->get();

        foreach ($transactions as $transaction) {
            $index = (int) $transaction->bulan - 1;

            if ($index < 0 || $index > 11) {
                continue;
            }

            if ($transaction->transaction_type === 'pemasukan') {
                $pemasukan[$index] = (float) $transaction->total;
            }

            if ($transaction->transaction_type === 'pengeluaran') {
                $pengeluaran[$index] = (float) $transaction->total;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => $pemasukan,
                    'borderColor' => '#16A34A',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => $pengeluaran,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        $currentYear = now()->year;

        $filters = [];

        for ($year = $currentYear; $year >= $currentYear - 4; $year--) {
            $filters[$year] = (string) $year;
        }

        return $filters;
    }
}
