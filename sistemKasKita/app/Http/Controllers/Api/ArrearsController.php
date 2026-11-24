<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillRecipient;
use App\Models\ClassRoom;
use App\Models\Cohort;
use App\Support\Notifier;
use Illuminate\Http\Request;

class ArrearsController extends Controller
{
    private function ownedCohort(Request $request): Cohort
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_angkatan',
            403,
            'Hanya Bendahara Angkatan yang dapat melihat tunggakan.'
        );

        return Cohort::where('id', $user->cohort_id)
            ->where('treasurer_id', $user->id)
            ->firstOrFail();
    }

    private function perClass(Cohort $cohort, int $month, int $year)
    {
        $rows = BillRecipient::query()
            ->whereHas('bill', function ($q) use ($cohort, $month, $year) {
                $q->where('period_month', $month)
                    ->where('period_year', $year)
                    ->whereHas('due', function ($d) use ($cohort) {
                        $d->where('cohort_id', $cohort->id)
                            ->where('management_type', 'angkatan')
                            ->whereNull('class_id');
                    });
            })
            ->where('status', '!=', 'lunas')
            ->with('classroom:id,name,treasurer_id')
            ->get();

        return $rows->groupBy('class_id')->map(function ($group) {
            $menunggu = $group->filter(fn($r) => $r->status === 'menunggu_verifikasi');
            $belum = $group->filter(fn($r) => $r->status !== 'menunggu_verifikasi');
            $first = $group->first();

            return [
                'class_id' => $first->class_id,
                'kelas' => $first->classroom->name ?? 'Tanpa kelas',
                'treasurer_id' => $first->classroom->treasurer_id ?? null,
                'jumlah_belum' => $belum->count(),
                'nominal_belum' => (float) $belum->sum(fn($r) => (float) $r->bill_amount - (float) $r->paid_amount),
                'jumlah_menunggu' => $menunggu->count(),
                'nominal_menunggu' => (float) $menunggu->sum(fn($r) => (float) $r->bill_amount - (float) $r->paid_amount),
            ];
        })->values();
    }

    // GET /arrears?period_month=&period_year=
    public function index(Request $request)
    {
        $cohort = $this->ownedCohort($request);

        $v = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $kelas = $this->perClass($cohort, (int) $v['period_month'], (int) $v['period_year']);

        return response()->json([
            'message' => 'Data tunggakan berhasil diambil.',
            'data' => [
                'total_belum' => (float) $kelas->sum('nominal_belum'),
                'total_menunggu' => (float) $kelas->sum('nominal_menunggu'),
                'jumlah_mahasiswa_belum' => (int) $kelas->sum('jumlah_belum'),
                'kelas' => $kelas,
            ],
        ]);
    }

    // POST /arrears/remind {period_month, period_year}
    public function remind(Request $request)
    {
        $cohort = $this->ownedCohort($request);

        $v = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $sent = 0;

        foreach ($this->perClass($cohort, (int) $v['period_month'], (int) $v['period_year']) as $row) {
            if ($row['jumlah_belum'] > 0 && $row['treasurer_id']) {
                Notifier::send(
                    (int) $row['treasurer_id'],
                    'Pengingat iuran',
                    'Masih ada ' . $row['jumlah_belum'] . ' anggota kelas ' . $row['kelas']
                        . ' yang belum membayar iuran periode '
                        . $v['period_month'] . '/' . $v['period_year'] . '.',
                    'pengingat_iuran',
                    (int) $row['class_id']
                );
                $sent++;
            }
        }

        return response()->json([
            'message' => $sent > 0
                ? "Pengingat dikirim ke {$sent} bendahara kelas."
                : 'Tidak ada kelas yang perlu diingatkan.',
            'data' => ['terkirim' => $sent],
        ]);
    }
}
