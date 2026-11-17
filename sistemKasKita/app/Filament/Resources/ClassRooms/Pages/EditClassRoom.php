<?php

namespace App\Filament\Resources\ClassRooms\Pages;

use App\Filament\Resources\ClassRooms\ClassRoomResource;
use App\Models\ClassRoom;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditClassRoom extends EditRecord
{
    protected static string $resource = ClassRoomResource::class;

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        $actor = Auth::user();

        if (
            !$actor ||
            $actor->role !== 'bendahara_angkatan' ||
            (int) $record->cohort_id !== (int) $actor->cohort_id ||
            $record->management_type !== $actor->management_type
        ) {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak memiliki izin mengubah kelas ini.',
            ]);
        }

        return DB::transaction(function () use ($record, $data, $actor) {
            $class = ClassRoom::query()
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTreasurerId = $class->treasurer_id;
            $newTreasurerId = $data['treasurer_id'] ?? null;

            if ($newTreasurerId) {
                $newTreasurer = User::query()
                    ->whereKey($newTreasurerId)
                    ->where('cohort_id', $actor->cohort_id)
                    ->where('management_type', $actor->management_type)
                    ->where('role', 'bendahara_kelas')
                    ->lockForUpdate()
                    ->first();

                if (!$newTreasurer) {
                    throw ValidationException::withMessages([
                        'treasurer_id' => 'Bendahara Kelas tidak valid.',
                    ]);
                }

                if (
                    (int) $newTreasurer->id !== (int) $oldTreasurerId &&
                    $newTreasurer->class_id !== null
                ) {
                    throw ValidationException::withMessages([
                        'treasurer_id' => 'Bendahara Kelas tersebut sudah ditugaskan ke kelas lain.',
                    ]);
                }

                $alreadyAssigned = ClassRoom::query()
                    ->where('treasurer_id', $newTreasurer->id)
                    ->where('id', '!=', $class->id)
                    ->exists();

                if ($alreadyAssigned) {
                    throw ValidationException::withMessages([
                        'treasurer_id' => 'Bendahara Kelas tersebut sudah memiliki kelas lain.',
                    ]);
                }
            }

            // Lepaskan penugasan bendahara sebelumnya.
            if ($oldTreasurerId && (int) $oldTreasurerId !== (int) $newTreasurerId) {
                User::query()
                    ->whereKey($oldTreasurerId)
                    ->where('class_id', $class->id)
                    ->update([
                        'class_id' => null,
                    ]);
            }

            // Perbarui data kelas.
            $class->update([
                'name' => $data['name'],
                'treasurer_id' => $newTreasurerId,
            ]);

            // Sinkronisasi bendahara baru.
            if ($newTreasurerId) {
                $newTreasurer->update([
                    'class_id' => $class->id,
                ]);
            }

            return $class->refresh();
        });
    }
}
