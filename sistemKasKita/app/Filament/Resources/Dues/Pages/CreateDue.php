<?php

namespace App\Filament\Resources\Dues\Pages;

use App\Filament\Resources\Dues\DueResource;
use App\Models\ClassRoom;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateDue extends CreateRecord
{
    protected static string $resource = DueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if (!$user) {
            throw ValidationException::withMessages([
                'name' => 'Pengguna tidak ditemukan.',
            ]);
        }

        // Bendahara Angkatan
        if ($user->role === 'bendahara_angkatan') {
            if (!$user->cohort_id) {
                throw ValidationException::withMessages([
                    'name' => 'Akun Bendahara Angkatan belum memiliki angkatan.',
                ]);
            }

            $scope = $data['management_scope'] ?? null;

            $data['management_type'] = 'angkatan';
            $data['cohort_id'] = $user->cohort_id;

            if ($scope === 'angkatan') {
                $data['class_id'] = null;
            } elseif ($scope === 'kelas') {
                $classId = $data['class_id'] ?? null;

                $classroom = ClassRoom::query()
                    ->whereKey($classId)
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
        }

        // Bendahara Kelas
        elseif ($user->role === 'bendahara_kelas') {
            if (!$user->class_id) {
                throw ValidationException::withMessages([
                    'name' => 'Akun Bendahara Kelas belum memiliki kelas.',
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

            // Kelas angkatan tetap menggunakan management_type angkatan.
            // Kelas mandiri menggunakan management_type kelas_mandiri.
            $data['management_type'] = $user->management_type;
            $data['cohort_id'] = $user->management_type === 'angkatan'
                ? $user->cohort_id
                : null;
            $data['class_id'] = $classroom->id;
        } else {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak memiliki izin membuat iuran.',
            ]);
        }

        // Kolom ini hanya digunakan untuk kebutuhan form.
        unset($data['management_scope']);

        // Identitas pembuat ditentukan oleh akun yang sedang login.
        $data['created_by'] = $user->id;

        return $data;
    }
}
