<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CohortController extends Controller
{
    /**
     * Menampilkan angkatan milik bendahara yang sedang login.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $cohort = Cohort::with('classes')
            ->where('treasurer_id', $user->id)
            ->first();

        return response()->json([
            'message' => 'Data angkatan berhasil diambil.',
            'data' => $cohort,
        ]);
    }

    /**
     * Membuat angkatan baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Satu bendahara hanya mengelola satu angkatan.
        if ($user->cohort_id !== null ||
            Cohort::where('treasurer_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'Anda sudah memiliki angkatan.',
            ], 409);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'institution_name' => ['nullable', 'string', 'max:150'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);

        $cohort = DB::transaction(function () use ($validated, $user) {
            $cohort = Cohort::create([
                'name' => $validated['name'],
                'institution_name' => $validated['institution_name'] ?? null,
                'academic_year' => $validated['academic_year'] ?? null,
                'treasurer_id' => $user->id,
            ]);

            $user->update([
                'cohort_id' => $cohort->id,
            ]);

            return $cohort;
        });

        return response()->json([
            'message' => 'Angkatan berhasil dibuat.',
            'data' => $cohort,
        ], 201);
    }

    /**
     * Menampilkan detail angkatan.
     */
    public function show(Request $request, int $cohort)
    {
        $data = Cohort::with(['classes', 'users'])
            ->where('treasurer_id', $request->user()->id)
            ->find($cohort);

        if (!$data) {
            return response()->json([
                'message' => 'Angkatan tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail angkatan berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Memperbarui data angkatan.
     */
    public function update(Request $request, int $cohort)
    {
        $data = Cohort::where('treasurer_id', $request->user()->id)
            ->find($cohort);

        if (!$data) {
            return response()->json([
                'message' => 'Angkatan tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'institution_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'academic_year' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $data->update($validated);

        return response()->json([
            'message' => 'Data angkatan berhasil diperbarui.',
            'data' => $data->fresh(),
        ]);
    }

    /**
     * Menghapus angkatan jika belum memiliki data terkait.
     */
    public function destroy(Request $request, int $cohort)
    {
        $data = Cohort::where('treasurer_id', $request->user()->id)
            ->find($cohort);

        if (!$data) {
            return response()->json([
                'message' => 'Angkatan tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        $hasRelatedData =
            $data->classes()->exists() ||
            $data->users()->where('id', '!=', $request->user()->id)->exists() ||
            $data->dues()->exists() ||
            $data->remittances()->exists() ||
            $data->cashTransactions()->exists();

        if ($hasRelatedData) {
            return response()->json([
                'message' => 'Angkatan tidak dapat dihapus karena masih memiliki data terkait.',
            ], 409);
        }

        DB::transaction(function () use ($data, $request) {
            $request->user()->update([
                'cohort_id' => null,
            ]);

            $data->delete();
        });

        return response()->json([
            'message' => 'Angkatan berhasil dihapus.',
        ]);
    }
}
