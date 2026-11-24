<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillRecipient;
use App\Models\CashTransaction;
use App\Models\ClassRoom;
use App\Models\Due;
use App\Models\Payment;
use App\Models\Remittance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint khusus Bendahara Kelas: ringkasan kas kelas, anggota, buku kas,
 * pencatatan pembayaran tunai, setoran ke angkatan, laporan, dan (kelas mandiri) pembuatan tagihan.
 */
class KelasController extends Controller
{
    private function ownedClass(Request $request): ClassRoom
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_kelas' && $user->class_id,
            403,
            'Hanya bendahara kelas yang dapat mengakses fitur ini.'
        );

        $class = ClassRoom::where('id', $user->class_id)
            ->where('treasurer_id', $user->id)
            ->first();

        abort_unless($class, 403, 'Anda bukan bendahara kelas yang berwenang.');

        return $class;
    }

    /**
     * Buku kas kelas = iuran disetujui (masuk) - pengeluaran kelas - setoran ke angkatan.
     * Setoran yang ditolak tidak dihitung.
     */
    private function ledger(ClassRoom $class)
    {
        $payments = Payment::where('status', 'disetujui')
            ->whereHas('billRecipient', fn($q) => $q->where('class_id', $class->id))
            ->with('payer:id,nama', 'billRecipient.bill:id,title')
            ->get()
            ->map(fn($p) => [
                'tipe' => 'masuk',
                'jenis' => 'iuran',
                'judul' => 'Iuran ' . ($p->payer->nama ?? '-'),
                'keterangan' => $p->billRecipient->bill->title ?? null,
                'nominal' => (float) $p->amount,
                'tanggal' => Carbon::parse($p->payment_date)->toDateTimeString(),
            ]);

        $expenses = CashTransaction::where('class_id', $class->id)
            ->where('transaction_type', 'pengeluaran')
            ->get()
            ->map(fn($t) => [
                'tipe' => 'keluar',
                'jenis' => 'pengeluaran',
                'judul' => $t->category,
                'keterangan' => $t->description,
                'nominal' => (float) $t->amount,
                'tanggal' => Carbon::parse($t->transaction_date)->toDateTimeString(),
            ]);

        $remittances = Remittance::where('class_id', $class->id)
            ->whereIn('status', ['menunggu', 'disetujui'])
            ->get()
            ->map(fn($r) => [
                'tipe' => 'keluar',
                'jenis' => 'setoran',
                'judul' => 'Setoran ke angkatan',
                'keterangan' => 'Periode ' . $r->period_month . '/' . $r->period_year . ' (' . $r->status . ')',
                'nominal' => (float) $r->amount,
                'tanggal' => Carbon::parse($r->created_at)->toDateTimeString(),
            ]);

        return $payments->concat($expenses)->concat($remittances)->sortByDesc('tanggal')->values();
    }

    private function totals($ledger): array
    {
        $masuk = (float) $ledger->where('tipe', 'masuk')->sum('nominal');
        $pengeluaran = (float) $ledger->where('jenis', 'pengeluaran')->sum('nominal');
        $setoran = (float) $ledger->where('jenis', 'setoran')->sum('nominal');

        return [$masuk, $pengeluaran, $setoran, $masuk - $pengeluaran - $setoran];
    }

    // GET /kelas/summary
    public function summary(Request $request)
    {
        $class = $this->ownedClass($request);
        [$masuk, $pengeluaran, $setoran, $saldo] = $this->totals($this->ledger($class));

        $anggota = User::where('class_id', $class->id)->where('role', 'anggota')->count();

        $pending = Payment::where('status', 'menunggu_verifikasi')
            ->whereHas('billRecipient', fn($q) => $q->where('class_id', $class->id))
            ->count();

        $latestBillId = BillRecipient::where('class_id', $class->id)->max('bill_id');
        $latestBill = $latestBillId
            ? Bill::select('id', 'title', 'period_month', 'period_year', 'due_date')->find($latestBillId)
            : null;

        $recipients = $latestBillId
            ? BillRecipient::where('bill_id', $latestBillId)->where('class_id', $class->id)->get()
            : collect();

        return response()->json([
            'message' => 'Ringkasan kelas berhasil diambil.',
            'data' => [
                'kelas' => [
                    'id' => $class->id,
                    'name' => $class->name,
                    'management_type' => $class->management_type,
                ],
                'saldo' => $saldo,
                'total_pemasukan' => $masuk,
                'total_pengeluaran' => $pengeluaran,
                'total_setoran' => $setoran,
                'anggota_total' => $anggota,
                'pembayaran_menunggu' => $pending,
                'tagihan_terakhir' => $latestBill,
                'lunas' => $recipients->where('status', 'lunas')->count(),
                'penerima' => $recipients->count(),
            ],
        ]);
    }

    // GET /kelas/members : anggota + status tagihan terakhir
    public function members(Request $request)
    {
        $class = $this->ownedClass($request);

        $latestBillId = BillRecipient::where('class_id', $class->id)->max('bill_id');
        $statusByUser = $latestBillId
            ? BillRecipient::where('bill_id', $latestBillId)->where('class_id', $class->id)->pluck('status', 'user_id')
            : collect();

        $members = User::where('class_id', $class->id)
            ->where('role', 'anggota')
            ->orderBy('nama')
            ->get(['id', 'nama', 'username', 'email', 'is_active'])
            ->map(fn($u) => [
                'id' => $u->id,
                'nama' => $u->nama,
                'username' => $u->username,
                'email' => $u->email,
                'is_active' => (bool) $u->is_active,
                'status' => $statusByUser->get($u->id, 'belum_ada_tagihan'),
            ])
            ->values();

        return response()->json([
            'message' => 'Daftar anggota kelas berhasil diambil.',
            'data' => [
                'total' => $members->count(),
                'lunas' => $members->where('status', 'lunas')->count(),
                'menunggu' => $members->where('status', 'menunggu_verifikasi')->count(),
                'belum' => $members->filter(fn($m) => !in_array($m['status'], ['lunas', 'menunggu_verifikasi']))->count(),
                'members' => $members,
            ],
        ]);
    }

    // GET /kelas/transactions?tipe=masuk|keluar
    public function transactions(Request $request)
    {
        $class = $this->ownedClass($request);
        $ledger = $this->ledger($class);
        [$masuk, $pengeluaran, $setoran, $saldo] = $this->totals($ledger);

        if (in_array($request->query('tipe'), ['masuk', 'keluar'], true)) {
            $ledger = $ledger->where('tipe', $request->query('tipe'))->values();
        }

        return response()->json([
            'message' => 'Transaksi kas kelas berhasil diambil.',
            'data' => [
                'saldo' => $saldo,
                'pemasukan' => $masuk,
                'pengeluaran' => $pengeluaran + $setoran,
                'transaksi' => $ledger->take(100)->values(),
            ],
        ]);
    }

    // POST /kelas/expenses {category, amount, description, transaction_date}
    public function storeExpense(Request $request)
    {
        $class = $this->ownedClass($request);

        $v = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
        ]);

        $trx = DB::transaction(function () use ($v, $class, $request) {
            [, , , $saldo] = $this->totals($this->ledger($class));

            if ((float) $v['amount'] > $saldo) {
                abort(422, 'Saldo kas kelas tidak mencukupi.');
            }

            return CashTransaction::create([
                'cohort_id' => $class->cohort_id,
                'class_id' => $class->id,
                'transaction_type' => 'pengeluaran',
                'category' => $v['category'],
                'amount' => $v['amount'],
                'description' => $v['description'] ?? null,
                'transaction_date' => $v['transaction_date'],
                'recorded_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Pengeluaran kelas berhasil dicatat.',
            'data' => $trx,
        ], 201);
    }

    // GET /kelas/report?start_date=&end_date=
    public function report(Request $request)
    {
        $class = $this->ownedClass($request);

        $v = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($v['start_date'])->startOfDay()->toDateTimeString();
        $end = Carbon::parse($v['end_date'])->endOfDay()->toDateTimeString();

        $ledger = $this->ledger($class);
        $before = $ledger->filter(fn($e) => $e['tanggal'] < $start);
        $period = $ledger->filter(fn($e) => $e['tanggal'] >= $start && $e['tanggal'] <= $end)->values();

        [, , , $saldoAwal] = $this->totals($before);
        [$masuk, $pengeluaran, $setoran, $periodNet] = $this->totals($period);

        return response()->json([
            'message' => 'Laporan kas kelas berhasil diambil.',
            'data' => [
                'kelas' => $class->name,
                'periode' => ['start_date' => $v['start_date'], 'end_date' => $v['end_date']],
                'saldo_awal' => $saldoAwal,
                'total_pemasukan' => $masuk,
                'total_pengeluaran' => $pengeluaran,
                'total_setoran' => $setoran,
                'saldo_akhir' => $saldoAwal + $periodNet,
                'transaksi' => $period->sortBy('tanggal')->values(),
            ],
        ]);
    }

    // GET /kelas/payments?status=disetujui|ditolak|menunggu_verifikasi  (riwayat verifikasi kelas)
    public function payments(Request $request)
    {
        $class = $this->ownedClass($request);

        $request->validate([
            'status' => ['nullable', 'in:menunggu_verifikasi,disetujui,ditolak'],
        ]);

        $query = Payment::query()
            ->whereHas('billRecipient', fn($q) => $q->where('class_id', $class->id))
            ->with(['payer', 'billRecipient.bill.due', 'billRecipient.classroom', 'proofs'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'message' => 'Riwayat pembayaran kelas berhasil diambil.',
            'data' => $query->paginate(15),
        ]);
    }

    // POST /kelas/payments/cash {bill_recipient_id, amount} : bendahara mencatat iuran tunai (langsung lunas/disetujui)
    public function recordCash(Request $request)
    {
        $class = $this->ownedClass($request);

        $v = $request->validate([
            'bill_recipient_id' => ['required', 'integer', 'exists:bill_recipients,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $payment = DB::transaction(function () use ($v, $class, $request) {
            $recipient = BillRecipient::whereKey($v['bill_recipient_id'])
                ->where('class_id', $class->id)
                ->lockForUpdate()
                ->first();

            if (!$recipient) {
                abort(404, 'Tagihan anggota tidak ditemukan di kelasmu.');
            }

            $remaining = (float) $recipient->bill_amount - (float) $recipient->paid_amount;

            if ($remaining <= 0) {
                abort(422, 'Tagihan ini sudah lunas.');
            }

            if ((float) $v['amount'] > $remaining) {
                abort(422, 'Nominal melebihi sisa tagihan.');
            }

            if ($recipient->payments()->where('status', 'menunggu_verifikasi')->exists()) {
                abort(422, 'Anggota ini masih punya pembayaran yang menunggu verifikasi. Verifikasi dulu.');
            }

            $payment = Payment::create([
                'bill_recipient_id' => $recipient->id,
                'payer_id' => $recipient->user_id,
                'recorded_by' => $request->user()->id,
                'amount' => $v['amount'],
                'payment_method' => 'tunai',
                'status' => 'disetujui',
                'payment_date' => now(),
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);

            $recipient->paid_amount = (float) $recipient->paid_amount + (float) $v['amount'];
            $recipient->status = (float) $recipient->paid_amount >= (float) $recipient->bill_amount
                ? 'lunas'
                : 'belum_dibayar';
            $recipient->save();

            return $payment;
        });

        return response()->json([
            'message' => 'Pembayaran tunai berhasil dicatat.',
            'data' => $payment,
        ], 201);
    }

    // GET /kelas/approved-payments?period_month=&period_year= : pembayaran disetujui yang belum disetor
    public function approvedPayments(Request $request)
    {
        $class = $this->ownedClass($request);

        abort_unless(
            $class->management_type === 'angkatan',
            422,
            'Kelas mandiri tidak menyetor ke angkatan.'
        );

        $request->validate([
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'period_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
        ]);

        $query = Payment::where('status', 'disetujui')
            ->whereHas('billRecipient', fn($q) => $q->where('class_id', $class->id))
            ->whereDoesntHave('remittanceItems', function ($q) {
                $q->whereHas('remittance', fn($r) => $r->whereIn('status', ['menunggu', 'disetujui']));
            })
            ->with('payer:id,nama', 'billRecipient.bill:id,title,period_month,period_year');

        if ($request->filled('period_month') && $request->filled('period_year')) {
            $month = (int) $request->query('period_month');
            $year = (int) $request->query('period_year');

            $query->whereHas('billRecipient.bill', function ($q) use ($month, $year) {
                $q->where('period_month', $month)->where('period_year', $year);
            });
        }

        $items = $query->orderBy('id')->get();

        return response()->json([
            'message' => 'Pembayaran yang siap disetor berhasil diambil.',
            'data' => [
                'total' => (float) $items->sum('amount'),
                'items' => $items,
            ],
        ]);
    }

    // GET /kelas/remittances : riwayat setoran kelas ke angkatan
    public function remittances(Request $request)
    {
        $class = $this->ownedClass($request);

        $items = Remittance::where('class_id', $class->id)
            ->withCount('items')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Riwayat setoran kelas berhasil diambil.',
            'data' => $items,
        ]);
    }

    // POST /kelas/bills {title, amount, period_month, period_year, due_date} : khusus kelas mandiri
    public function storeBill(Request $request)
    {
        $class = $this->ownedClass($request);

        abort_unless(
            $class->management_type === 'kelas_mandiri',
            403,
            'Hanya kelas mandiri yang dapat membuat tagihan sendiri. Tagihan kelas angkatan dibuat oleh Bendahara Angkatan.'
        );

        $v = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            'due_date' => ['required', 'date'],
        ]);

        $members = User::where('class_id', $class->id)->where('role', 'anggota')->get();

        if ($members->isEmpty()) {
            return response()->json(['message' => 'Belum ada anggota di kelas ini.'], 422);
        }

        $bill = DB::transaction(function () use ($v, $class, $members, $request) {
            $due = Due::create([
                'name' => $v['title'],
                'amount' => $v['amount'],
                'period_type' => 'sekali',
                'management_type' => 'kelas_mandiri',
                'cohort_id' => $class->cohort_id,
                'class_id' => $class->id,
                'created_by' => $request->user()->id,
                'is_active' => true,
            ]);

            $bill = Bill::create([
                'dues_id' => $due->id,
                'title' => $v['title'],
                'period_month' => $v['period_month'],
                'period_year' => $v['period_year'],
                'amount' => $v['amount'],
                'due_date' => $v['due_date'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($members as $member) {
                $bill->recipients()->create([
                    'user_id' => $member->id,
                    'class_id' => $class->id,
                    'bill_amount' => $v['amount'],
                    'paid_amount' => 0,
                    'status' => 'belum_dibayar',
                ]);
            }

            return $bill;
        });

        return response()->json([
            'message' => 'Tagihan kelas berhasil dibuat.',
            'data' => $bill->loadCount('recipients'),
        ], 201);
    }
}
