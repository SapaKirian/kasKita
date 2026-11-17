<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\ClassRoom;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        if (
            !$actor || !in_array($actor->role, [
                'bendahara_angkatan',
                'bendahara_kelas',
            ])
        ) {
            throw ValidationException::withMessages([
                'role' => 'Anda tidak memiliki izin untuk menambahkan pengguna.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor) {
            if ($actor->role === 'bendahara_angkatan') {
                $class = ClassRoom::query()
                    ->where('cohort_id', $actor->cohort_id)
                    ->where('management_type', $actor->management_type)
                    ->whereKey($data['class_id'] ?? null)
                    ->lockForUpdate()
                    ->first();

                if (!$class) {
                    throw ValidationException::withMessages([
                        'class_id' => 'Kelas tidak valid atau bukan bagian dari angkatan Anda.',
                    ]);
                }

                if (
                    $data['role'] === 'bendahara_kelas'
                    && $class->treasurer_id !== null
                ) {
                    throw ValidationException::withMessages([
                        'class_id' => 'Kelas ini sudah memiliki Bendahara Kelas.',
                    ]);
                }
            } else {
                if ($data['role'] !== 'anggota') {
                    throw ValidationException::withMessages([
                        'role' => 'Bendahara Kelas hanya dapat menambahkan Anggota.',
                    ]);
                }

                $class = ClassRoom::query()
                    ->whereKey($actor->class_id)
                    ->where('cohort_id', $actor->cohort_id)
                    ->where('treasurer_id', $actor->id)
                    ->lockForUpdate()
                    ->first();

                if (!$class) {
                    throw ValidationException::withMessages([
                        'role' => 'Kelas Anda tidak valid.',
                    ]);
                }
            }

            $data['cohort_id'] = $actor->cohort_id;
            $data['class_id'] = $class->id;
            $data['management_type'] = $actor->management_type;
            $data['created_by'] = $actor->id;
            $data['is_active'] = true;

            $user = User::create($data);

            if ($user->role === 'bendahara_kelas') {
                $class->update([
                    'treasurer_id' => $user->id,
                ]);
            }

            return $user;
        });
    }
}
