<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillRecipient;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    // Anggota mengajukan pembayaran
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
                Rule::in(['cash', 'transfer', 'qris']),
            ],
            'proof' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $recipient = BillRecipient::where('id', $validated['bill_recipient_id'])
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
                $recipient = BillRecipient::whereKey($recipient->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Hitung pembayaran yang sudah disetujui
                $paid = (float) $recipient->paid_amount;

                // Hitung pembayaran yang masih menunggu verifikasi
                $menunggu_verifikasi = (float) $recipient->payments()
                    ->where('status', 'menunggu_verifikasi')
                    ->sum('amount');

                $remaining = (float) $recipient->bill_amount
                    - $paid
                    - $menunggu_verifikasi;

                if ((float) $validated['amount'] > $remaining) {
                    abort(422, 'Nominal pembayaran melebihi sisa tagihan.');
                }

                if ($remaining <= 0) {
                    abort(422, 'Tagihan sudah lunas atau seluruh sisanya sedang diverifikasi.');
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

                // Simpan bukti pembayaran
                $proofPath = Storage::disk('public')->putFile(
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

                $recipient->update([
                    'status' => 'menunggu_verifikasi',
                ]);

                return $payment;
            });
        } catch (\Throwable $e) {
            if ($proofPath) {
                Storage::disk('public')->delete($proofPath);
            }

            throw $e;
        }

        return response()->json([
            'message' => 'Pengajuan pembayaran berhasil dikirim dan menunggu verifikasi.',
            'data' => $payment->load('proofs'),
        ], 201);
    }

    // Menampilkan pengajuan pembayaran anggota
    public function myPayments(Request $request)
    {
        $payments = Payment::where('payer_id', $request->user()->id)
            ->with(['billRecipient.bill', 'proofs'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'message' => 'Riwayat pembayaran berhasil diambil.',
            'data' => $payments,
        ]);
    }

    // Bendahara Kelas melihat pengajuan pembayaran kelasnya
    public function pendingPayments(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_kelas' && $user->class_id,
            403,
            'Anda tidak memiliki akses melihat pembayaran kelas.'
        );

        $payments = Payment::where('status', 'menunggu_verifikasi')
            ->whereHas('billRecipient', function ($query) use ($user) {
                $query->where('class_id', $user->class_id);
            })
            ->with(['payer', 'billRecipient.bill', 'proofs'])
            ->orderBy('created_at')
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar pembayaran menunggu verifikasi berhasil diambil.',
            'data' => $payments,
        ]);
    }

    // Bendahara Kelas menyetujui atau menolak pembayaran
    public function verify(Request $request, int $payment)
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_kelas' && $user->class_id,
            403,
            'Anda tidak memiliki akses verifikasi pembayaran.'
        );

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

        $item = Payment::whereKey($payment)
            ->whereHas('billRecipient', function ($query) use ($user) {
                $query->where('class_id', $user->class_id);
            })
            ->first();

        if (!$item) {
            return response()->json([
                'message' => 'Pembayaran tidak ditemukan di kelas Anda.',
            ], 404);
        }

        if ($item->status !== 'menunggu_verifikasi') {
            return response()->json([
                'message' => 'Pembayaran ini sudah pernah diverifikasi.',
            ], 422);
        }

        $result = DB::transaction(function () use ($item, $validated, $user) {
            $recipient = BillRecipient::whereKey($item->bill_recipient_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'menunggu_verifikasi') {
                abort(422, 'Pembayaran sudah pernah diverifikasi.');
            }

            if ($validated['status'] === 'disetujui') {
                $newPaidAmount = (float) $recipient->paid_amount
                    + (float) $payment->amount;

                if ($newPaidAmount > (float) $recipient->bill_amount) {
                    abort(422, 'Pembayaran melebihi total tagihan.');
                }

                $recipient->paid_amount = $newPaidAmount;

                $recipient->status = $newPaidAmount >= (float) $recipient->bill_amount
                    ? 'lunas'
                    : 'belum_dibayar';

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

            // Periksa apakah masih ada pengajuan pembayaran lain
            if ($payment->status === 'ditolak') {
                $hasPending = $recipient->payments()
                    ->where('status', 'menunggu_verifikasi')
                    ->exists();

                if ($hasPending) {
                    $recipient->status = 'menunggu_verifikasi';
                } elseif ((float) $recipient->paid_amount >= (float) $recipient->bill_amount) {
                    $recipient->status = 'lunas';
                } else {
                    $recipient->status = 'belum_dibayar';
                }
            }

            $recipient->save();

            return $payment->fresh()->load('proofs', 'payer');
        });

        return response()->json([
            'message' => $validated['status'] === 'disetujui'
                ? 'Pembayaran berhasil disetujui.'
                : 'Pembayaran berhasil ditolak.',
            'data' => $result,
        ]);
    }
}
