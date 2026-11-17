<?php

namespace App\Filament\Resources\Cohorts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CohortForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama Angkatan')
                ->placeholder('Contoh: Angkatan 2026')
                ->required()
                ->maxLength(100)
                ->unique(ignoreRecord: true),

            TextInput::make('institution_name')
                ->label('Nama Institusi')
                ->placeholder('Contoh: Universitas Siliwangi')
                ->maxLength(150)
                ->nullable(),

            TextInput::make('academic_year')
                ->label('Tahun Akademik')
                ->placeholder('Contoh: 2026-2027')
                ->maxLength(20)
                ->nullable(),
        ]);
    }
}
