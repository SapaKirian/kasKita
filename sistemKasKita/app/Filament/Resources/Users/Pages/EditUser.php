<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        $allowedData = [
            'nama' => $data['nama'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'is_active' => $data['is_active'] ?? $record->is_active,
        ];

        if (filled($data['password'] ?? null)) {
            $allowedData['password'] = $data['password'];
        }

        $record->update($allowedData);

        return $record;
    }
}
