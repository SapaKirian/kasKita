<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Cohort;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassRoomController extends Controller
{
    /**
     * Memastikan bendahara memiliki angkatan.
     */
    private function getOwnedCohort(User $user): Cohort
    {
        if (!$user->cohort_id) {
            abort(422, 'Akun Anda belum terhubung dengan angkatan.');
        }

        $cohort = Cohort::where('id', $user->cohort_id)
            ->where('treasurer_id', $user->id)
            ->first();

        if (!$cohort) {
            abort(403, 'Anda tidak memiliki akses ke angkatan ini.');
        }

        return $cohort;
    }

    /**
     * Memastikan bendahara kelas layak ditugaskan.
     */
    private function validateTreasurer(
        ?int $treasurerId,
        int $cohortId,
        ?int $currentClassId = null
    ): ?User {
        if (!$treasurerId) {
            return null;
        }

        $treasurer = User::where('id', $treasurerId)
            ->where('role', 'bendahara_kelas')
            ->where('management_type', 'angkatan')
            ->where('cohort_id', $cohortId)
            ->where('is_active', true)
            ->first();

        if (!$treasurer) {
            throw ValidationException::withMessages([
                'treasurer_id' => [
                    'Bendahara kelas tidak valid atau bukan bagian dari angkatan Anda.',
                ],
            ]);
        }

        // Bendahara tidak boleh bertanggung jawab atas kelas lain.
        $alreadyAssigned = ClassRoom::where('treasurer_id', $treasurer->id)
            ->when(
                $currentClassId,
                fn($query) => $query->where('id', '!=', $currentClassId)
            )
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'treasurer_id' => [
                    'Bendahara tersebut sudah ditugaskan ke kelas lain.',
                ],
            ]);
        }

        // Akun bendahara tidak boleh sudah menjadi anggota kelas lain.
        if (
            $treasurer->class_id !== null &&
            (int) $treasurer->class_id !== $currentClassId
        ) {
            throw ValidationException::withMessages([
                'treasurer_id' => [
                    'Akun bendahara sudah terhubung dengan kelas lain.',
                ],
            ]);
        }

        return $treasurer;
    }

    /**
     * Menampilkan daftar kelas dalam angkatan.
     */
    public function index(Request $request)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $classes = ClassRoom::where('cohort_id', $cohort->id)
            ->with('treasurer:id,nama,username,email')
            ->withCount([
                'users as members_count' => function ($query) {
                    $query->where('role', 'anggota');
                },
            ])
            ->latest()
            ->paginate(10);

        return response()->json([
            'message' => 'Daftar kelas berhasil diambil.',
            'data' => $classes,
        ]);
    }

    /**
     * Membuat kelas baru.
     */
    public function store(Request $request)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'treasurer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $treasurer = $this->validateTreasurer(
            isset($validated['treasurer_id'])
            ? (int) $validated['treasurer_id']
            : null,
            $cohort->id
        );

        $class = DB::transaction(function () use ($validated, $cohort, $treasurer) {
            $class = ClassRoom::create([
                'cohort_id' => $cohort->id,
                'name' => $validated['name'],
                'management_type' => 'angkatan',
                'treasurer_id' => $treasurer?->id,
            ]);

            if ($treasurer) {
                $treasurer->update([
                    'class_id' => $class->id,
                ]);
            }

            return $class;
        });

        return response()->json([
            'message' => 'Kelas berhasil dibuat.',
            'data' => $class->load('treasurer:id,nama,username'),
        ], 201);
    }

    /**
     * Menampilkan detail kelas.
     */
    public function show(Request $request, int $classRoom)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $class = ClassRoom::where('cohort_id', $cohort->id)
            ->with('treasurer:id,nama,username,email')
            ->withCount([
                'users as members_count' => function ($query) {
                    $query->where('role', 'anggota');
                },
            ])
            ->find($classRoom);

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail kelas berhasil diambil.',
            'data' => $class,
        ]);
    }

    /**
     * Memperbarui data kelas dan bendaharanya.
     */
    public function update(Request $request, int $classRoom)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $class = ClassRoom::where('cohort_id', $cohort->id)
            ->find($classRoom);

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'treasurer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $newTreasurerId = array_key_exists('treasurer_id', $validated)
            ? ($validated['treasurer_id'] !== null
                ? (int) $validated['treasurer_id']
                : null)
            : $class->treasurer_id;

        $newTreasurer = $this->validateTreasurer(
            $newTreasurerId,
            $cohort->id,
            $class->id
        );

        DB::transaction(function () use ($class, $validated, $newTreasurer) {
            // Lepaskan bendahara sebelumnya jika diganti.
            if (
                $class->treasurer_id &&
                (int) $class->treasurer_id !== $newTreasurer?->id
            ) {
                User::where('id', $class->treasurer_id)
                    ->where('class_id', $class->id)
                    ->update(['class_id' => null]);
            }

            $class->update([
                'name' => $validated['name'] ?? $class->name,
                'treasurer_id' => $newTreasurer?->id,
            ]);

            if ($newTreasurer) {
                $newTreasurer->update([
                    'class_id' => $class->id,
                ]);
            }
        });

        return response()->json([
            'message' => 'Data kelas berhasil diperbarui.',
            'data' => $class->fresh()->load('treasurer:id,nama,username'),
        ]);
    }

    /**
     * Menetapkan bendahara kelas ke kelas tertentu.
     */
    public function assignTreasurer(Request $request, int $classRoom)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $class = ClassRoom::where('cohort_id', $cohort->id)
            ->find($classRoom);

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'treasurer_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $treasurer = $this->validateTreasurer(
            (int) $validated['treasurer_id'],
            $cohort->id,
            $class->id
        );

        DB::transaction(function () use ($class, $treasurer) {

            // Lepaskan bendahara sebelumnya.
            if (
                $class->treasurer_id &&
                (int) $class->treasurer_id !== $treasurer->id
            ) {
                User::where('id', $class->treasurer_id)
                    ->where('class_id', $class->id)
                    ->update([
                        'class_id' => null,
                    ]);
            }

            // Hubungkan bendahara dengan kelas.
            $class->update([
                'treasurer_id' => $treasurer->id,
            ]);

            $treasurer->update([
                'class_id' => $class->id,
            ]);
        });

        return response()->json([
            'message' => 'Bendahara kelas berhasil ditugaskan.',
            'data' => $class->fresh()
                ->load('treasurer:id,nama,username,email'),
        ]);
    }


    /**
     * Menghapus kelas yang belum memiliki data terkait.
     */
    public function destroy(Request $request, int $classRoom)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $class = ClassRoom::where('cohort_id', $cohort->id)
            ->find($classRoom);

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        $hasRelatedData =
            $class->users()->exists() ||
            $class->dues()->exists() ||
            $class->billRecipients()->exists() ||
            $class->remittances()->exists();

        if ($hasRelatedData) {
            return response()->json([
                'message' => 'Kelas tidak dapat dihapus karena masih memiliki anggota atau data transaksi.',
            ], 409);
        }

        $class->delete();

        return response()->json([
            'message' => 'Kelas berhasil dihapus.',
        ]);
    }

    /**
     * Menampilkan daftar bendahara kelas yang belum ditugaskan.
     */
    public function availableTreasurers(Request $request, int $classRoom)
    {
        $cohort = $this->getOwnedCohort($request->user());

        $class = ClassRoom::where('cohort_id', $cohort->id)
            ->find($classRoom);

        if (!$class) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan.',
            ], 404);
        }

        $treasurers = User::where('cohort_id', $cohort->id)
            ->where('role', 'bendahara_kelas')
            ->where('management_type', 'angkatan')
            ->where('is_active', true)
            ->where(function ($query) use ($class) {
                $query->whereNull('class_id')
                    ->orWhere('class_id', $class->id);
            })
            ->where(function ($query) use ($class) {
                $query->whereDoesntHave('classroom')
                    ->orWhere('class_id', $class->id);
            })
            ->select('id', 'nama', 'username', 'email', 'class_id')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'message' => 'Daftar bendahara kelas berhasil diambil.',
            'data' => $treasurers,
        ]);
    }

}
