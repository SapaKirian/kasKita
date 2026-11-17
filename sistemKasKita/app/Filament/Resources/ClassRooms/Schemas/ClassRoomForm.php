<?php

namespace App\Filament\Resources\ClassRooms\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ClassRoomForm
{
    public static function configure(Schema $schema): Schema
    {
        $actor = Auth::user();

        return $schema->components([

            TextInput::make('name')
                ->label('Nama Kelas')
                ->placeholder('Contoh: Informatika C')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),

            Select::make('treasurer_id')
                ->label('Bendahara Kelas')
                ->options(function () use ($actor) {
                    if (!$actor || $actor->role !== 'bendahara_angkatan') {
                        return [];
                    }

                    return User::query()
                        ->where('cohort_id', $actor->cohort_id)
                        ->where('management_type', $actor->management_type)
                        ->where('role', 'bendahara_kelas')
                        ->where(function ($query) {
                            $query->whereNull('class_id')
                                ->orWhereHas('classroom', function ($classQuery) {
                                    $classQuery->whereColumn(
                                        'classes.treasurer_id',
                                        'users.id'
                                    );
                                });
                        })
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->toArray();
                })
                ->searchable()
                ->preload()
                ->nullable()
                ->helperText('Pilih Bendahara Kelas yang akan bertanggung jawab. Kosongkan jika belum ditentukan.'),

        ]);
    }
}
