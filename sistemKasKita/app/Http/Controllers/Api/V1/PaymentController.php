<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillRecipient;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\ClassRoom;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    // Anggota melihat riwayat pembayaran miliknya.
    public function myPayments(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'anggota',
            403,
            'Hanya anggota yang dapat melihat riwayat pembayaran.'
        );

        $payments = Payment::query()
            ->where('payer_id', $user->id)
            ->with(['proofs', 'billRecipient.bill.due'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Riwayat pembayaran berhasil diambil.',
            'data' => $payments,
        ]);
    }

    // Anggota mengajukan pembayaran.
    public function store(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'anggota',
            403,
            'Hanya anggota yang dapat mengajukan pembayaran.'
        );

        $validated = $request->validate([
            'bill_recipient_id' => [
                'required',
                'integer',
                'exists:bill_recipients,id',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => [
                'required',
                Rule::in(['tunai', 'transfer']),
            ],
            'proof' => [
                'required_if:payment_method,transfer',
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $recipient = BillRecipient::query()
            ->whereKey($validated['bill_recipient_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$recipient) {
            return response()->json([
                'message' => 'Tagihan tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        $proofPath = null;

        try {
            $payment = DB::transaction(function () use ($validated, $recipient, $request, &$proofPath) {
                $recipient = BillRecipient::query()
                    ->whereKey($recipient->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $paid = (float) $recipient->paid_amount;

                $pending = (float) $recipient->payments()
                    ->where('status', 'menunggu_verifikasi')
                    ->sum('amount');

                $remaining = (float) $recipient->bill_amount
                    - $paid
                    - $pending;

                if ($remaining <= 0) {
                    abort(
                        422,
                        'Tagihan sudah lunas atau seluruh sisanya sedang menunggu verifikasi.'
                    );
                }

                if ((float) $validated['amount'] > $remaining) {
                    abort(422, 'Nominal pembayaran melebihi sisa tagihan.');
                }

                $payment = Payment::create([
                    'bill_recipient_id' => $recipient->id,
                    'payer_id' => $request->user()->id,
                    'recorded_by' => null,
                    'amount' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'status' => 'menunggu_verifikasi',
                    'payment_date' => now(),
                ]);

                if ($request->hasFile('proof')) {
                    $proofPath = Storage::disk('local')->putFile(
                        'payment-proofs',
                        $request->file('proof')
                    );

                    if (!$proofPath) {
                        abort(500, 'Bukti pembayaran gagal disimpan.');
                    }

                    PaymentProof::create([
                        'payment_id' => $payment->id,
                        'file_path' => $proofPath,
                        'uploaded_by' => $request->user()->id,
                        'uploaded_at' => now(),
                    ]);
                }

                $recipient->update([
                    'status' => 'menunggu_verifikasi',
                ]);

                return $payment;
            });
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Pengajuan pembayaran berhasil dikirim dan menunggu verifikasi.',
            'data' => $payment->load('proofs'),
        ], 201);
    }

    // Bendahara melihat pembayaran sesuai kewenangannya.
    public function pendingPayments(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'bendahara_kelas') {
            abort_unless(
                $user->class_id &&
                ClassRoom::query()
                    ->whereKey($user->class_id)
                    ->where('treasurer_id', $user->id)
                    ->exists(),
                403,
                'Anda tidak memiliki akses ke pembayaran kelas.'
            );

            $query = Payment::query()
                ->whereHas('billRecipient', function ($query) use ($user) {
                    $query->where('class_id', $user->class_id);
                });
        } elseif ($user->role === 'bendahara_angkatan') {
            abort_unless(
                $user->cohort_id &&
                Cohort::query()
                    ->whereKey($user->cohort_id)
                    ->where('treasurer_id', $user->id)
                    ->exists(),
                403,
                'Anda tidak memiliki akses ke pembayaran angkatan.'
            );

            $query = Payment::query()
                ->whereHas('billRecipient.bill.due', function ($query) use ($user) {
                    $query
                        ->where('management_type', 'angkatan')
                        ->where('cohort_id', $user->cohort_id)
                        ->whereNull('class_id');
                });
        } else {
            abort(403, 'Anda tidak memiliki akses melihat pembayaran.');
        }

        $payments = $query
            ->where('status', 'menunggu_verifikasi')
            ->with([
                'payer',
                'billRecipient.bill.due',
                'billRecipient.classroom',
                'proofs',
            ])
            ->orderBy('created_at')
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar pembayaran menunggu verifikasi berhasil diambil.',
            'data' => $payments,
        ]);
    }

    // Bendahara menyetujui atau menolak pembayaran.
    public function verify(Request $request, int $payment)
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['disetujui', 'ditolak']),
            ],
            'rejection_reason' => [
                'required_if:status,ditolak',
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if ($user->role === 'bendahara_kelas') {
            abort_unless(
                $user->class_id &&
                ClassRoom::query()
                    ->whereKey($user->class_id)
                    ->where('treasurer_id', $user->id)
                    ->exists(),
                403,
                'Anda tidak memiliki akses verifikasi pembayaran kelas.'
            );

            $paymentQuery = Payment::query()
                ->whereKey($payment)
                ->whereHas('billRecipient', function ($query) use ($user) {
                    $query->where('class_id', $user->class_id);
                });
        } elseif ($user->role === 'bendahara_angkatan') {
            abort_unless(
                $user->cohort_id &&
                Cohort::query()
                    ->whereKey($user->cohort_id)
                    ->where('treasurer_id', $user->id)
                    ->exists(),
                403,
                'Anda tidak memiliki akses verifikasi pembayaran angkatan.'
            );

            $paymentQuery = Payment::query()
                ->whereKey($payment)
                ->whereHas('billRecipient.bill.due', function ($query) use ($user) {
                    $query
                        ->where('management_type', 'angkatan')
                        ->where('cohort_id', $user->cohort_id)
                        ->whereNull('class_id');
                });
        } else {
            abort(403, 'Anda tidak memiliki akses verifikasi pembayaran.');
        }

        $item = $paymentQuery->first();

        if (!$item) {
            return response()->json([
                'message' => 'Pembayaran tidak ditemukan dalam wilayah tanggung jawab Anda.',
            ], 404);
        }

        $result = DB::transaction(function () use ($item, $validated, $user) {
            $recipient = BillRecipient::query()
                ->whereKey($item->bill_recipient_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'menunggu_verifikasi') {
                abort(422, 'Pembayaran ini sudah pernah diverifikasi.');
            }

            if ($validated['status'] === 'disetujui') {
                $newPaidAmount = (float) $recipient->paid_amount
                    + (float) $payment->amount;

                if ($newPaidAmount > (float) $recipient->bill_amount) {
                    abort(422, 'Pembayaran melebihi total tagihan.');
                }

                $recipient->paid_amount = $newPaidAmount;

                $payment->status = 'disetujui';
                $payment->rejection_reason = null;
            } else {
                $payment->status = 'ditolak';
                $payment->rejection_reason =
                    $validated['rejection_reason'] ?? null;
            }

            $payment->verified_by = $user->id;
            $payment->verified_at = now();
            $payment->save();

            $hasPending = $recipient->payments()
                ->where('status', 'menunggu_verifikasi')
                ->exists();

            if ($hasPending) {
                $recipient->status = 'menunggu_verifikasi';
            } elseif (
                (float) $recipient->paid_amount >=
                (float) $recipient->bill_amount
            ) {
                $recipient->status = 'lunas';
            } else {
                $recipient->status = 'belum_dibayar';
            }

            $recipient->save();

            return $payment->fresh()->load([
                'proofs',
                'payer',
                'billRecipient.bill',
            ]);
        });

        return response()->json([
            'message' => $validated['status'] === 'disetujui'
                ? 'Pembayaran berhasil disetujui.'
                : 'Pembayaran berhasil ditolak.',
            'data' => $result,
        ]);
    }

    // Download bukti pembayaran dengan pemeriksaan kewenangan.
    public function downloadProof(Request $request, int $proof)
    {
        $user = $request->user();

        $proofRecord = PaymentProof::query()
            ->with([
                'payment.billRecipient.bill.due',
            ])
            ->find($proof);

        abort_unless($proofRecord, 404);

        $payment = $proofRecord->payment;
        $recipient = $payment?->billRecipient;
        $due = $recipient?->bill?->due;

        abort_unless($payment && $recipient, 404);

        $authorized = false;

        if ($user->role === 'anggota') {
            $authorized = (int) $payment->payer_id === (int) $user->id;
        } elseif ($user->role === 'bendahara_kelas') {
            $authorized = $user->class_id
                && ClassRoom::query()
                    ->whereKey($user->class_id)
                    ->where('treasurer_id', $user->id)
                    ->exists()
                && (int) $recipient->class_id === (int) $user->class_id;
        } elseif ($user->role === 'bendahara_angkatan') {
            $authorized = $user->cohort_id
                && Cohort::query()
                    ->whereKey($user->cohort_id)
                    ->where('treasurer_id', $user->id)
                    ->exists()
                && $due
                && $due->management_type === 'angkatan'
                && (int) $due->cohort_id === (int) $user->cohort_id
                && $due->class_id === null;
        }

        abort_unless($authorized, 404);

        abort_unless(
            Storage::disk('local')->exists($proofRecord->file_path),
            404
        );

        return Storage::disk('local')->response(
            $proofRecord->file_path
        );
    }

}
