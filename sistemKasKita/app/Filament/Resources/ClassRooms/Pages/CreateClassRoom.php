<?php

namespace App\Filament\Resources\ClassRooms\Pages;

use App\Filament\Resources\ClassRooms\ClassRoomResource;
use App\Models\ClassRoom;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateClassRoom extends CreateRecord
{
    protected static string $resource = ClassRoomResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = Auth::user();

        if (
            !$actor ||
            $actor->role !== 'bendahara_angkatan' ||
            !$actor->cohort_id
        ) {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak memiliki izin untuk membuat kelas.',
            ]);
        }

        return DB::transaction(function () use ($data, $actor) {
            $treasurerId = $data['treasurer_id'] ?? null;

            $treasurer = null;

            if ($treasurerId) {
                $treasurer = User::query()
                    ->whereKey($treasurerId)
                    ->where('cohort_id', $actor->cohort_id)
                    ->where('management_type', $actor->management_type)
                    ->where('role', 'bendahara_kelas')
                    ->whereNull('class_id')
                    ->lockForUpdate()
                    ->first();

                if (!$treasurer) {
                    throw ValidationException::withMessages([
                        'treasurer_id' => 'Bendahara Kelas tidak valid atau sudah ditugaskan ke kelas lain.',
                    ]);
                }

                $alreadyAssigned = ClassRoom::query()
                    ->where('treasurer_id', $treasurer->id)
                    ->exists();

                if ($alreadyAssigned) {
                    throw ValidationException::withMessages([
                        'treasurer_id' => 'Bendahara Kelas tersebut sudah memiliki kelas.',
                    ]);
                }
            }

            $class = ClassRoom::create([
                'name' => $data['name'],
                'cohort_id' => $actor->cohort_id,
                'management_type' => $actor->management_type,
                'treasurer_id' => null,
            ]);

            if ($treasurer) {
                $treasurer->update([
                    'class_id' => $class->id,
                ]);

                $class->update([
                    'treasurer_id' => $treasurer->id,
                ]);
            }

            return $class;
        });
    }
}
