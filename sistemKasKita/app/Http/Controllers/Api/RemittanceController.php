<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Remittance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemittanceController extends Controller
{
    /**
     * Bendahara Kelas mengajukan setoran
     * berdasarkan pembayaran anggota yang sudah disetujui.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['required', 'integer', 'distinct', 'exists:payments,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();

        if ($user->role !== 'bendahara_kelas') {
            return response()->json([
                'message' => 'Hanya Bendahara Kelas yang dapat mengajukan setoran.',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $user) {

            // Pastikan kelas benar-benar dikelola oleh bendahara ini.
            $classRoom = ClassRoom::query()
                ->where('id', $user->class_id)
                ->where('treasurer_id', $user->id)
                ->where('management_type', 'angkatan')
                ->first();

            if (!$classRoom) {
                throw ValidationException::withMessages([
                    'class_id' => 'Kelas tidak ditemukan atau bukan tanggung jawabmu.',
                ]);
            }

            // Ambil dan kunci pembayaran agar tidak dipakai
            // oleh dua pengajuan setoran secara bersamaan.
            $payments = Payment::query()
                ->whereIn('id', $validated['payment_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->with('billRecipient.bill')
                ->get();

            if ($payments->count() !== count($validated['payment_ids'])) {
                throw ValidationException::withMessages([
                    'payment_ids' => 'Sebagian pembayaran tidak ditemukan.',
                ]);
            }

            foreach ($payments as $payment) {

                // Hanya pembayaran yang sudah disetujui.
                if ($payment->status !== 'disetujui') {
                    throw ValidationException::withMessages([
                        'payment_ids' => "Pembayaran ID {$payment->id} belum disetujui.",
                    ]);
                }

                // Pastikan pembayaran berasal dari anggota kelas ini.
                if (
                    !$payment->billRecipient ||
                    (int) $payment->billRecipient->class_id !== (int) $classRoom->id
                ) {
                    throw ValidationException::withMessages([
                        'payment_ids' => "Pembayaran ID {$payment->id} bukan milik kelasmu.",
                    ]);
                }

                // Pastikan periode pembayaran sesuai dengan periode setoran.
                $bill = $payment->billRecipient->bill;

                if (
                    !$bill ||
                    (int) $bill->period_month !== (int) $validated['period_month'] ||
                    (int) $bill->period_year !== (int) $validated['period_year']
                ) {
                    throw ValidationException::withMessages([
                        'payment_ids' => "Pembayaran ID {$payment->id} tidak sesuai periode setoran.",
                    ]);
                }

                // Pembayaran tidak boleh berada dalam setoran
                // yang masih menunggu atau sudah disetujui.
                $alreadyRemitted = $payment->remittanceItems()
                    ->whereHas('remittance', function ($query) {
                        $query->whereIn('status', ['menunggu', 'disetujui']);
                    })
                    ->exists();

                if ($alreadyRemitted) {
                    throw ValidationException::withMessages([
                        'payment_ids' => "Pembayaran ID {$payment->id} sudah masuk dalam setoran lain.",
                    ]);
                }
            }

            // Hitung total dari pembayaran yang dipilih.
            $totalAmount = $payments->sum(
                fn($payment) => (float) $payment->amount
            );

            // Cari cohort milik kelas.
            if (!$classRoom->cohort_id) {
                throw ValidationException::withMessages([
                    'class_id' => 'Kelas ini belum terhubung dengan angkatan.',
                ]);
            }

            // Buat data setoran.
            $remittance = Remittance::create([
                'class_id' => $classRoom->id,
                'cohort_id' => $classRoom->cohort_id,
                'amount' => $totalAmount,
                'period_month' => $validated['period_month'],
                'period_year' => $validated['period_year'],
                'status' => 'menunggu',
                'submitted_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Simpan rincian pembayaran ke remittance_items.
            foreach ($payments as $payment) {
                $remittance->items()->create([
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                ]);
            }

            return response()->json([
                'message' => 'Pengajuan setoran berhasil dikirim.',
                'data' => $remittance->load([
                    'items.payment',
                    'classroom',
                ]),
            ], 201);
        });
    }

    /**
     * Bendahara Angkatan melihat seluruh setoran
     * dari kelas-kelas dalam angkatannya.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'bendahara_angkatan') {
            return response()->json([
                'message' => 'Hanya Bendahara Angkatan yang dapat melihat daftar setoran.',
            ], 403);
        }

        $remittances = Remittance::query()
            ->where('cohort_id', $user->cohort_id)
            ->with([
                'classroom',
                'submitter',
                'verifier',
                'items.payment.payer',
            ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar setoran berhasil diambil.',
            'data' => $remittances,
        ]);
    }

    /**
     * Bendahara Angkatan melihat detail satu setoran.
     */
    public function show(Request $request, Remittance $remittance)
    {
        $user = $request->user();

        if (
            $user->role !== 'bendahara_angkatan' ||
            (int) $remittance->cohort_id !== (int) $user->cohort_id
        ) {
            return response()->json([
                'message' => 'Kamu tidak memiliki akses ke setoran ini.',
            ], 403);
        }

        return response()->json([
            'message' => 'Detail setoran berhasil diambil.',
            'data' => $remittance->load([
                'classroom',
                'submitter',
                'verifier',
                'items.payment.payer',
                'items.payment.billRecipient.bill',
            ]),
        ]);
    }

    /**
     * Bendahara Angkatan menyetujui atau menolak setoran.
     */
    public function verify(Request $request, Remittance $remittance)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:disetujui,ditolak'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();

        if ($user->role !== 'bendahara_angkatan') {
            return response()->json([
                'message' => 'Hanya Bendahara Angkatan yang dapat memverifikasi setoran.',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $user, $remittance) {

            // Kunci data setoran.
            $remittance = Remittance::query()
                ->lockForUpdate()
                ->findOrFail($remittance->id);

            // Pastikan setoran milik angkatan yang dikelola.
            if (
                (int) $remittance->cohort_id !== (int) $user->cohort_id
            ) {
                return response()->json([
                    'message' => 'Setoran ini bukan milik angkatanmu.',
                ], 403);
            }

            // Pastikan bendahara memang pengelola angkatan.
            $ownsCohort = DB::table('cohorts')
                ->where('id', $user->cohort_id)
                ->where('treasurer_id', $user->id)
                ->exists();

            if (!$ownsCohort) {
                return response()->json([
                    'message' => 'Kamu bukan Bendahara Angkatan yang bertanggung jawab.',
                ], 403);
            }

            if ($remittance->status !== 'menunggu') {
                throw ValidationException::withMessages([
                    'status' => 'Setoran ini sudah pernah diverifikasi.',
                ]);
            }

            $remittance->update([
                'status' => $validated['status'],
                'verified_by' => $user->id,
                'verified_at' => now(),
                'notes' => $validated['notes'] ?? $remittance->notes,
            ]);

            return response()->json([
                'message' => $validated['status'] === 'disetujui'
                    ? 'Setoran berhasil disetujui.'
                    : 'Setoran berhasil ditolak.',
                'data' => $remittance->fresh()->load([
                    'classroom',
                    'submitter',
                    'verifier',
                    'items.payment',
                ]),
            ]);
        });
    }
}
