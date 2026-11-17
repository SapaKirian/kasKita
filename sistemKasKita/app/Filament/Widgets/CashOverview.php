<?php

namespace App\Filament\Widgets;

use App\Models\CashTransaction;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Remittance;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, [
            'bendahara_angkatan',
            'bendahara_kelas',
        ]);
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $transactionQuery = CashTransaction::query();

        if ($user->role === 'bendahara_angkatan') {
            if (!$user->cohort_id) {
                return [];
            }

            $transactionQuery->where('cohort_id', $user->cohort_id);

            $jumlahKelas = ClassRoom::query()
                ->where('cohort_id', $user->cohort_id)
                ->count();

            $jumlahPengguna = User::query()
                ->where('cohort_id', $user->cohort_id)
                ->count();

            $menungguVerifikasi = Remittance::query()
                ->where('cohort_id', $user->cohort_id)
                ->where('status', 'menunggu')
                ->count();

            $labelMenunggu = 'Setoran Kelas Menunggu';

        } elseif ($user->role === 'bendahara_kelas') {
            if (!$user->class_id) {
                return [];
            }

            $transactionQuery->where('class_id', $user->class_id);

            $jumlahAnggota = User::query()
                ->where('class_id', $user->class_id)
                ->where('role', 'anggota')
                ->count();

            $menungguVerifikasi = Payment::query()
                ->where('status', 'menunggu_verifikasi')
                ->whereHas('billRecipient', function ($query) use ($user) {
                    $query->where('class_id', $user->class_id);
                })
                ->count();

            $labelMenunggu = 'Pembayaran Menunggu';

        } else {
            return [];
        }

        $totalPemasukan = (clone $transactionQuery)
            ->where('transaction_type', 'pemasukan')
            ->sum('amount');

        $totalPengeluaran = (clone $transactionQuery)
            ->where('transaction_type', 'pengeluaran')
            ->sum('amount');

        $saldo = $totalPemasukan - $totalPengeluaran;

        $formatRupiah = fn($amount) =>
            'Rp ' . number_format((float) $amount, 0, ',', '.');

        $stats = [];

        if ($user->role === 'bendahara_angkatan') {
            $stats[] = Stat::make(
                'Jumlah Kelas',
                $jumlahKelas . ' Kelas'
            )
                ->description('Total kelas dalam angkatan')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info');

            $stats[] = Stat::make(
                'Jumlah Pengguna',
                $jumlahPengguna . ' Orang'
            )
                ->description('Total pengguna dalam angkatan')
                ->descriptionIcon('heroicon-m-users')
                ->color('info');

        } else {
            $stats[] = Stat::make(
                'Jumlah Anggota',
                $jumlahAnggota . ' Orang'
            )
                ->description('Total anggota kelas Anda')
                ->descriptionIcon('heroicon-m-users')
                ->color('info');
        }

        $stats[] = Stat::make(
            'Total Pemasukan',
            $formatRupiah($totalPemasukan)
        )
            ->description('Akumulasi seluruh pemasukan')
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color('success');

        $stats[] = Stat::make(
            'Total Pengeluaran',
            $formatRupiah($totalPengeluaran)
        )
            ->description('Akumulasi seluruh pengeluaran')
            ->descriptionIcon('heroicon-m-arrow-trending-down')
            ->color('danger');

        $stats[] = Stat::make(
            'Saldo Kas',
            $formatRupiah($saldo)
        )
            ->description('Pemasukan dikurangi pengeluaran')
            ->descriptionIcon('heroicon-m-wallet')
            ->color($saldo >= 0 ? 'primary' : 'danger');

        $stats[] = Stat::make(
            $labelMenunggu,
            $menungguVerifikasi . ' transaksi'
        )
            ->description('Perlu dilakukan verifikasi')
            ->descriptionIcon('heroicon-m-clock')
            ->color($menungguVerifikasi > 0 ? 'warning' : 'success');

        return $stats;
    }
}
