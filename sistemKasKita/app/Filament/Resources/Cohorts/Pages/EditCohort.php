<?php

namespace App\Filament\Resources\Cohorts\Pages;

use App\Filament\Resources\Cohorts\CohortResource;
use App\Models\Cohort;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCohort extends EditRecord
{
    protected static string $resource = CohortResource::class;

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        $user = auth()->user();

        if (
            !$user ||
            $user->role !== 'bendahara_angkatan' ||
            (int) $record->id !== (int) $user->cohort_id
        ) {
            throw ValidationException::withMessages([
                'name' => 'Anda tidak memiliki izin mengubah angkatan ini.',
            ]);
        }

        $record->update([
            'name' => $data['name'],
            'institution_name' => $data['institution_name'] ?? null,
            'academic_year' => $data['academic_year'] ?? null,
        ]);

        return $record->refresh();
    }
}
