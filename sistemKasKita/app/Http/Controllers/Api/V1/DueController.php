<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\Due;
use Illuminate\Http\Request;

class DueController extends Controller
{
    /**
     * Memastikan bendahara memiliki angkatan.
     */
    private function getOwnedCohort(Request $request): Cohort
    {
        $user = $request->user();

        abort_unless(
            $user->role === 'bendahara_angkatan',
            403,
            'Anda tidak memiliki akses mengelola iuran angkatan.'
        );

        $cohort = Cohort::where('id', $user->cohort_id)
            ->where('treasurer_id', $user->id)
            ->first();

        abort_unless(
            $cohort,
            403,
            'Angkatan tidak ditemukan atau bukan milik Anda.'
        );

        return $cohort;
    }

    /**
     * Menampilkan daftar iuran angkatan.
     */
    public function index(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $dues = Due::where('cohort_id', $cohort->id)
            ->where('management_type', 'angkatan')
            ->withCount('bills')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar iuran berhasil diambil.',
            'data' => $dues,
        ]);
    }

    /**
     * Membuat iuran bulanan angkatan.
     */
    public function store(Request $request)
    {
        $cohort = $this->getOwnedCohort($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $due = Due::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'amount' => 10000,
            'period_type' => 'bulanan',
            'management_type' => 'angkatan',
            'cohort_id' => $cohort->id,
            'class_id' => null,
            'created_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Iuran angkatan berhasil dibuat.',
            'data' => $due,
        ], 201);
    }

    /**
     * Menampilkan detail iuran.
     */
    public function show(Request $request, int $due)
    {
        $cohort = $this->getOwnedCohort($request);

        $item = Due::where('cohort_id', $cohort->id)
            ->where('management_type', 'angkatan')
            ->withCount('bills')
            ->find($due);

        if (!$item) {
            return response()->json([
                'message' => 'Iuran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail iuran berhasil diambil.',
            'data' => $item,
        ]);
    }

    /**
     * Mengubah nama dan deskripsi iuran.
     */
    public function update(Request $request, int $due)
    {
        $cohort = $this->getOwnedCohort($request);

        $item = Due::where('cohort_id', $cohort->id)
            ->where('management_type', 'angkatan')
            ->find($due);

        if (!$item) {
            return response()->json([
                'message' => 'Iuran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Iuran berhasil diperbarui.',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Mengaktifkan atau menonaktifkan iuran.
     */
    public function updateStatus(Request $request, int $due)
    {
        $cohort = $this->getOwnedCohort($request);

        $item = Due::where('cohort_id', $cohort->id)
            ->where('management_type', 'angkatan')
            ->find($due);

        if (!$item) {
            return response()->json([
                'message' => 'Iuran tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $item->update([
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'message' => 'Status iuran berhasil diperbarui.',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Menghapus iuran jika belum memiliki tagihan.
     */
    public function destroy(Request $request, int $due)
    {
        $cohort = $this->getOwnedCohort($request);

        $item = Due::where('cohort_id', $cohort->id)
            ->where('management_type', 'angkatan')
            ->find($due);

        if (!$item) {
            return response()->json([
                'message' => 'Iuran tidak ditemukan.',
            ], 404);
        }

        if ($item->bills()->exists()) {
            return response()->json([
                'message' => 'Iuran tidak dapat dihapus karena sudah memiliki tagihan.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'message' => 'Iuran berhasil dihapus.',
        ]);
    }
}
