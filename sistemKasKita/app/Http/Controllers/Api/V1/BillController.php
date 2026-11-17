<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Due;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    private function getOwnedCohort(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_angkatan',
            403,
            'Anda tidak memiliki akses membuat tagihan angkatan.'
        );

        $cohort = $user->cohort()
            ->where('treasurer_id', $user->id)
            ->first();

        abort_unless(
            $cohort,
            403,
            'Angkatan tidak ditemukan atau bukan milik Anda.'
        );

        return $cohort;
    }

    // Menampilkan daftar tagihan sesuai pengguna
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Bill::query()
            ->with('due:id,name')
            ->orderByDesc('created_at');

        if ($user->role === 'anggota') {
            $query->whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->role === 'bendahara_angkatan') {
            $cohort = $this->getOwnedCohort($request);

            $query->whereHas('due', function ($q) use ($cohort) {
                $q->where('cohort_id', $cohort->id)
                    ->where('management_type', 'angkatan')
                    ->whereNull('class_id');
            })->withCount('recipients');
        } elseif ($user->role === 'bendahara_kelas') {
            abort_unless(
                $user->class_id &&
                $user->classroom()
                    ->where('treasurer_id', $user->id)
                    ->exists(),
                403,
                'Anda bukan bendahara kelas yang berwenang.'
            );

            $query->whereHas('recipients', function ($q) use ($user) {
                $q->where('class_id', $user->class_id);
            })->withCount('recipients');
        } else {
            abort(403, 'Anda tidak memiliki akses melihat tagihan.');
        }

        return response()->json([
            'message' => 'Daftar tagihan berhasil diambil.',
            'data' => $query->paginate(10),
        ]);
    }

    // Membuat tagihan untuk seluruh anggota angkatan
    public function store(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $validated = $request->validate([
            'dues_id' => ['required', 'integer', 'exists:dues,id'],
            'title' => ['required', 'string', 'max:150'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            'due_date' => ['required', 'date'],
        ]);

        $due = Due::where('id', $validated['dues_id'])
            ->where('cohort_id', $cohort->id)
            ->where('management_type', 'angkatan')
            ->where('is_active', true)
            ->first();

        if (!$due) {
            return response()->json([
                'message' => 'Iuran tidak ditemukan atau sudah tidak aktif.',
            ], 404);
        }

        $alreadyExists = Bill::where('dues_id', $due->id)
            ->where('period_month', $validated['period_month'])
            ->where('period_year', $validated['period_year'])
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'message' => 'Tagihan untuk periode tersebut sudah dibuat.',
            ], 422);
        }

        $members = User::where('cohort_id', $cohort->id)
            ->where('role', 'anggota')
            ->whereNotNull('class_id')
            ->get();

        if ($members->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada anggota yang terdaftar dalam angkatan ini.',
            ], 422);
        }

        $bill = DB::transaction(function () use ($validated, $due, $members, $request) {
            $bill = Bill::create([
                'dues_id' => $due->id,
                'title' => $validated['title'],
                'period_month' => $validated['period_month'],
                'period_year' => $validated['period_year'],
                'amount' => $due->amount,
                'due_date' => $validated['due_date'],
                'created_by' => $request->user()->id,
            ]);

            foreach ($members as $member) {
                $bill->recipients()->create([
                    'user_id' => $member->id,
                    'class_id' => $member->class_id,
                    'bill_amount' => $due->amount,
                    'paid_amount' => 0,
                    'status' => 'belum_dibayar',
                ]);
            }

            return $bill;
        });

        return response()->json([
            'message' => 'Tagihan berhasil dibuat.',
            'data' => $bill->loadCount('recipients'),
            'total_anggota' => $members->count(),
        ], 201);
    }

    // Detail tagihan sesuai hak akses pengguna
    public function show(Request $request, int $bill)
    {
        $user = $request->user();

        $query = Bill::query()
            ->with('due', 'recipients.user', 'recipients.classroom');

        if ($user->role === 'anggota') {
            $query->whereHas('recipients', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->role === 'bendahara_angkatan') {
            $cohort = $this->getOwnedCohort($request);

            $query->whereHas('due', function ($q) use ($cohort) {
                $q->where('cohort_id', $cohort->id)
                    ->where('management_type', 'angkatan')
                    ->whereNull('class_id');
            });
        } elseif ($user->role === 'bendahara_kelas') {
            abort_unless(
                $user->class_id &&
                $user->classroom()
                    ->where('treasurer_id', $user->id)
                    ->exists(),
                403,
                'Anda bukan bendahara kelas yang berwenang.'
            );

            $query->whereHas('recipients', function ($q) use ($user) {
                $q->where('class_id', $user->class_id);
            });
        } else {
            abort(403, 'Anda tidak memiliki akses melihat tagihan.');
        }

        $item = $query->find($bill);

        if (!$item) {
            return response()->json([
                'message' => 'Tagihan tidak ditemukan.',
            ], 404);
        }

        if ($user->role === 'anggota') {
            $item->setRelation(
                'recipients',
                $item->recipients->where('user_id', $user->id)->values()
            );
        }

        return response()->json([
            'message' => 'Detail tagihan berhasil diambil.',
            'data' => $item,
        ]);
    }
}
