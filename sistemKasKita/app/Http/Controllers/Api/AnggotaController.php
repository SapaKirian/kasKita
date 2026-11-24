<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillRecipient;
use Illuminate\Http\Request;

class AnggotaController extends Controller
{
    // GET /anggota/summary : ringkasan tagihan milik anggota yang login
    public function summary(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === 'anggota', 403, 'Hanya anggota yang dapat mengakses ringkasan ini.');

        $recipients = BillRecipient::where('user_id', $user->id)
            ->with('bill:id,title,period_month,period_year,due_date')
            ->get();

        $open = $recipients
            ->filter(fn($r) => $r->status !== 'lunas')
            ->sortBy(fn($r) => (string) ($r->bill->due_date ?? ''))
            ->values()
            ->map(fn($r) => [
                'bill_id' => $r->bill_id,
                'recipient_id' => $r->id,
                'title' => $r->bill->title ?? '-',
                'period_month' => $r->bill->period_month ?? null,
                'period_year' => $r->bill->period_year ?? null,
                'due_date' => $r->bill->due_date ?? null,
                'amount' => (float) $r->bill_amount,
                'dibayar' => (float) $r->paid_amount,
                'sisa' => (float) $r->bill_amount - (float) $r->paid_amount,
                'status' => $r->status,
            ]);

        return response()->json([
            'message' => 'Ringkasan anggota berhasil diambil.',
            'data' => [
                'total_sisa' => (float) $open->sum('sisa'),
                'jumlah_tagihan_terbuka' => $open->count(),
                'jumlah_lunas' => $recipients->where('status', 'lunas')->count(),
                'tagihan_terdekat' => $open->first(),
                'tagihan_terbuka' => $open,
            ],
        ]);
    }
}
