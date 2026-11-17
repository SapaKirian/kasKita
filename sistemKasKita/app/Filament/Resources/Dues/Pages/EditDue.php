<?php

namespace App\Filament\Resources\Dues\Pages;

use App\Filament\Resources\Dues\DueResource;
use App\Models\ClassRoom;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditDue extends EditRecord
{
    protected static string $resource = DueResource::class;

    protected function getHeaderActions(): array
    {
        // Tidak menyediakan aksi Delete.
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = auth()->user();

        if ($user?->role === 'bendahara_angkatan') {
            $data['management_scope'] = $data['class_id'] === null
                ? 'angkatan'
                : 'kelas';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        $record = $this->getRecord();

        if (!$user) {
            throw ValidationException::withMessages([
                'name' => 'Pengguna tidak ditemukan.',
            ]);
        }

        if ($user->role === 'bendahara_angkatan') {
            if (
                !$user->cohort_id ||
                (int) $record->cohort_id !== (int) $user->cohort_id
            ) {
                throw ValidationException::withMessages([
                    'name' => 'Anda tidak memiliki akses ke iuran ini.',
                ]);
            }

            $scope = $data['management_scope'] ?? null;

            $data['management_type'] = 'angkatan';
            $data['cohort_id'] = $user->cohort_id;

            if ($scope === 'angkatan') {
                $data['class_id'] = null;
            } elseif ($scope === 'kelas') {
                $classroom = ClassRoom::query()
                    ->whereKey($data['class_id'] ?? null)
                    ->where('cohort_id', $user->cohort_id)
                    ->where('management_type', 'angkatan')
                    ->first();

                if (!$classroom) {
                    throw ValidationException::withMessages([
                        'class_id' => 'Kelas tidak valid atau bukan bagian dari angkatan Anda.',
                    ]);
                }

                $data['class_id'] = $classroom->id;
            } else {
                throw ValidationException::withMessages([
                    'management_scope' => 'Silakan pilih cakupan iuran.',
                ]);
            }
        } elseif ($user->role === 'bendahara_kelas') {
            if (
                !$user->class_id ||
                (int) $record->class_id !== (int) $user->class_id
            ) {
                throw ValidationException::withMessages([
                    'name' => 'Anda tidak memiliki akses ke iuran ini.',
                ]);
            }

            $classroom = ClassRoom::query()
                ->whereKey($user->class_id)
                ->where('management_type', $user->management_type)
                ->first();

            if (!$classroom) {
                throw ValidationException::withMessages([
                    'name' => 'Data kelas Bendahara tidak valid.',
                ]);
            }

            $data['management_type'] = $user->management_type;
            $data['cohort_id'] = $user->management_type === 'angkatan'
                ? $user->cohort_id
                : null;

            $data['class_id'] = $classroom->id;
        } else {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak memiliki izin mengedit iuran.',
            ]);
        }

        unset($data['management_scope'], $data['created_by']);

        return $data;
    }
}
