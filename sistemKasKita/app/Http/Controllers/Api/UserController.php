<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Menampilkan daftar pengguna
    public function index(Request $request)
    {
        $actor = $request->user();

        if ($actor->role === 'bendahara_angkatan') {

            if (!$actor->cohort_id) {
                return response()->json([
                    'message' => 'Akun bendahara belum terhubung dengan angkatan.'
                ], 422);
            }

            $query = User::where('cohort_id', $actor->cohort_id)
                ->where('id', '!=', $actor->id);

        } elseif ($actor->role === 'bendahara_kelas') {

            if (!$actor->class_id) {
                return response()->json([
                    'message' => 'Akun bendahara belum terhubung dengan kelas.'
                ], 422);
            }

            $query = User::where('class_id', $actor->class_id)
                ->where('role', 'anggota')
                ->where('id', '!=', $actor->id);

        } else {
            return response()->json([
                'message' => 'Anda tidak memiliki akses.'
            ], 403);
        }

        $users = $query->latest()->paginate(10);

        $users->getCollection()->makeHidden(['password']);

        return response()->json([
            'message' => 'Daftar pengguna berhasil diambil.',
            'data' => $users,
        ]);
    }

    // Menambahkan pengguna

    // Menambahkan pengguna
    public function store(Request $request)
    {
        $actor = $request->user();

        // Pastikan hanya bendahara yang dapat membuat pengguna
        if (
            !in_array($actor->role, [
                'bendahara_angkatan',
                'bendahara_kelas'
            ])
        ) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses.'
            ], 403);
        }

        // Pastikan bendahara angkatan sudah memiliki angkatan
        if ($actor->role === 'bendahara_angkatan' && !$actor->cohort_id) {
            return response()->json([
                'message' => 'Akun bendahara belum terhubung dengan angkatan.'
            ], 422);
        }

        // Pastikan bendahara kelas sudah memiliki kelas
        if ($actor->role === 'bendahara_kelas' && !$actor->class_id) {
            return response()->json([
                'message' => 'Akun bendahara belum terhubung dengan kelas.'
            ], 422);
        }

        // Validasi input
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['bendahara_kelas', 'anggota'])],
        ]);

        // Pengaturan berdasarkan role pembuat
        if ($actor->role === 'bendahara_angkatan') {

            // Bendahara angkatan dapat membuat bendahara kelas dan anggota
            $validated['cohort_id'] = $actor->cohort_id;
            $validated['class_id'] = null;

        } elseif ($actor->role === 'bendahara_kelas') {

            // Bendahara kelas hanya dapat membuat anggota
            if ($validated['role'] !== 'anggota') {
                return response()->json([
                    'message' => 'Bendahara kelas hanya dapat menambahkan anggota.'
                ], 403);
            }

            // Anggota otomatis masuk ke kelas bendahara
            $validated['cohort_id'] = $actor->cohort_id;
            $validated['class_id'] = $actor->class_id;
        }

        // Data tambahan pengguna
        $validated['management_type'] = $actor->management_type;
        $validated['created_by'] = $actor->id;
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        // Simpan pengguna
        $newUser = User::create($validated);

        // Sembunyikan password dari response
        $newUser->makeHidden(['password']);

        return response()->json([
            'message' => 'Pengguna berhasil ditambahkan.',
            'data' => $newUser,
        ], 201);
    }


    // Melihat detail pengguna
    public function show(Request $request, User $user)
    {
        if (!$this->canManage($request->user(), $user)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses.'
            ], 403);
        }

        return response()->json([
            'data' => $user,
        ]);
    }

    // Mengubah data pengguna
    public function update(Request $request, User $user)
    {
        if (!$this->canManage($request->user(), $user)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses.'
            ], 403);
        }

        $validated = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui.',
            'data' => $user,
        ]);
    }

    // Mengaktifkan atau menonaktifkan akun
    public function toggleStatus(Request $request, User $user)
    {
        if (!$this->canManage($request->user(), $user)) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses.'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message' => 'Status akun berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    // Memeriksa apakah pengguna boleh mengelola target
    private function canManage(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($actor->role === 'bendahara_angkatan') {
            return $target->cohort_id === $actor->cohort_id
                && in_array($target->role, ['bendahara_kelas', 'anggota']);
        }

        if ($actor->role === 'bendahara_kelas') {
            return $target->class_id === $actor->class_id
                && $target->role === 'anggota';
        }

        return false;
    }
}