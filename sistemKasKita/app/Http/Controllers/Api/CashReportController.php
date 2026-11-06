<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Exports\CashFlowExport;
use Maatwebsite\Excel\Facades\Excel;


class CashReportController extends Controller
{
    private function getOwnedCohort(Request $request): Cohort
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_angkatan',
            403,
            'Hanya Bendahara Angkatan yang dapat mengakses laporan keuangan.'
        );

        return Cohort::where('id', $user->cohort_id)
            ->where('treasurer_id', $user->id)
            ->firstOrFail();
    }

    public function cashFlow(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date'
            ],
        ]);

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        $baseQuery = CashTransaction::where(
            'cohort_id',
            $cohort->id
        );

        // Menghitung saldo sebelum periode laporan.
        $pemasukanSebelumnya = (clone $baseQuery)
            ->where('transaction_type', 'pemasukan')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $pengeluaranSebelumnya = (clone $baseQuery)
            ->where('transaction_type', 'pengeluaran')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $saldoAwal = $pemasukanSebelumnya - $pengeluaranSebelumnya;

        // Query transaksi dalam periode laporan.
        $periodQuery = (clone $baseQuery)
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        $totalPemasukan = (clone $periodQuery)
            ->where('transaction_type', 'pemasukan')
            ->sum('amount');

        $totalPengeluaran = (clone $periodQuery)
            ->where('transaction_type', 'pengeluaran')
            ->sum('amount');

        $saldoAkhir = $saldoAwal
            + $totalPemasukan
            - $totalPengeluaran;

        // Mengambil rincian transaksi.
        $transactions = (clone $periodQuery)
            ->with([
                'classroom:id,name',
                'recorder:id,nama,username',
                'remittance:id,period_month,period_year,status',
            ])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->paginate(20);

        return response()->json([
            'message' => 'Laporan keuangan berhasil diambil.',
            'data' => [
                'cohort_id' => $cohort->id,
                'nama_angkatan' => $cohort->name,
                'periode' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'saldo_awal' => (float) $saldoAwal,
                'total_pemasukan' => (float) $totalPemasukan,
                'total_pengeluaran' => (float) $totalPengeluaran,
                'saldo_akhir' => (float) $saldoAkhir,
                'transaksi' => $transactions,
            ],
        ]);
    }

    public function exportPdf(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date'
            ],
        ]);

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        $baseQuery = CashTransaction::where(
            'cohort_id',
            $cohort->id
        );

        $saldoAwal =
            (clone $baseQuery)
                ->where('transaction_type', 'pemasukan')
                ->whereDate('transaction_date', '<', $startDate)
                ->sum('amount')
            -
            (clone $baseQuery)
                ->where('transaction_type', 'pengeluaran')
                ->whereDate('transaction_date', '<', $startDate)
                ->sum('amount');

        $periodQuery = (clone $baseQuery)
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        $totalPemasukan = (clone $periodQuery)
            ->where('transaction_type', 'pemasukan')
            ->sum('amount');

        $totalPengeluaran = (clone $periodQuery)
            ->where('transaction_type', 'pengeluaran')
            ->sum('amount');

        $saldoAkhir = $saldoAwal
            + $totalPemasukan
            - $totalPengeluaran;

        $transactions = (clone $periodQuery)
            ->with('classroom:id,name')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('reports.cash-flow-pdf', [
            'cohort' => $cohort,
            'user' => $request->user(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'saldoAwal' => $saldoAwal,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'saldoAkhir' => $saldoAkhir,
            'transactions' => $transactions,
        ]);

        $pdf->setPaper('A4', 'landscape');

        $filename = 'laporan-kas-' .
            Carbon::parse($startDate)->format('Ymd') .
            '-' .
            Carbon::parse($endDate)->format('Ymd') .
            '.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        $baseQuery = CashTransaction::where(
            'cohort_id',
            $cohort->id
        );

        // Saldo awal sebelum periode laporan.
        $pemasukanSebelumnya = (clone $baseQuery)
            ->where('transaction_type', 'pemasukan')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $pengeluaranSebelumnya = (clone $baseQuery)
            ->where('transaction_type', 'pengeluaran')
            ->whereDate('transaction_date', '<', $startDate)
            ->sum('amount');

        $saldoAwal = $pemasukanSebelumnya - $pengeluaranSebelumnya;

        // Transaksi dalam periode laporan.
        $periodQuery = (clone $baseQuery)
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate);

        $totalPemasukan = (clone $periodQuery)
            ->where('transaction_type', 'pemasukan')
            ->sum('amount');

        $totalPengeluaran = (clone $periodQuery)
            ->where('transaction_type', 'pengeluaran')
            ->sum('amount');

        $saldoAkhir = $saldoAwal
            + $totalPemasukan
            - $totalPengeluaran;

        $transactions = (clone $periodQuery)
            ->with('classroom:id,name')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $export = new CashFlowExport(
            cohortName: $cohort->name,
            startDate: $startDate,
            endDate: $endDate,
            saldoAwal: (float) $saldoAwal,
            totalPemasukan: (float) $totalPemasukan,
            totalPengeluaran: (float) $totalPengeluaran,
            saldoAkhir: (float) $saldoAkhir,
            transactions: $transactions->all()
        );

        $filename = 'laporan-kas-' .
            Carbon::parse($startDate)->format('Ymd') .
            '-' .
            Carbon::parse($endDate)->format('Ymd') .
            '.xlsx';

        return Excel::download($export, $filename);
    }


}
