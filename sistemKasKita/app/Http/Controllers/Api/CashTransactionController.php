<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashTransactionController extends Controller
{
    /**
     * Memastikan bendahara angkatan hanya mengakses kas
     * angkatan yang memang dikelolanya.
     */
    private function getOwnedCohort(Request $request): Cohort
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_angkatan',
            403,
            'Hanya Bendahara Angkatan yang dapat mengakses buku kas.'
        );

        return Cohort::where('id', $user->cohort_id)
            ->where('treasurer_id', $user->id)
            ->firstOrFail();
    }

    /**
     * Menampilkan daftar transaksi kas.
     */
    public function index(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $query = CashTransaction::query()
            ->where('cohort_id', $cohort->id)
            ->with([
                'classroom:id,name',
                'recorder:id,nama,username',
                'remittance:id,period_month,period_year,status',
            ]);

        // Filter berdasarkan tipe transaksi.
        if ($request->filled('transaction_type')) {
            $request->validate([
                'transaction_type' => [
                    'in:pemasukan,pengeluaran',
                ],
            ]);

            $query->where(
                'transaction_type',
                $request->transaction_type
            );
        }

        // Filter berdasarkan kategori.
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter berdasarkan rentang tanggal.
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        if ($request->filled('start_date')) {
            $query->whereDate(
                'transaction_date',
                '>=',
                $request->start_date
            );
        }

        if ($request->filled('end_date')) {
            $query->whereDate(
                'transaction_date',
                '<=',
                $request->end_date
            );
        }

        $transactions = $query
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar transaksi kas berhasil diambil.',
            'data' => $transactions,
        ]);
    }

    /**
     * Ringkasan pemasukan, pengeluaran, dan saldo kas.
     */
    public function summary(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $totalPemasukan = CashTransaction::where(
            'cohort_id',
            $cohort->id
        )
            ->where('transaction_type', 'pemasukan')
            ->sum('amount');

        $totalPengeluaran = CashTransaction::where(
            'cohort_id',
            $cohort->id
        )
            ->where('transaction_type', 'pengeluaran')
            ->sum('amount');

        $saldo = $totalPemasukan - $totalPengeluaran;

        return response()->json([
            'message' => 'Ringkasan kas berhasil diambil.',
            'data' => [
                'cohort_id' => $cohort->id,
                'nama_angkatan' => $cohort->name,
                'total_pemasukan' => (float) $totalPemasukan,
                'total_pengeluaran' => (float) $totalPengeluaran,
                'saldo' => (float) $saldo,
            ],
        ]);
    }

    /**
     * Mencatat pengeluaran kas.
     */
    public function store(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
                'max:100',
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'transaction_date' => [
                'required',
                'date',
            ],
        ]);

        $transaction = DB::transaction(function () use ($validated, $cohort, $request) {
            return CashTransaction::create([
                'cohort_id' => $cohort->id,
                'class_id' => null,
                'transaction_type' => 'pengeluaran',
                'category' => $validated['category'],
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'payment_id' => null,
                'remittance_id' => null,
                'recorded_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Pengeluaran kas berhasil dicatat.',
            'data' => $transaction->load('recorder:id,nama,username'),
        ], 201);
    }
}
