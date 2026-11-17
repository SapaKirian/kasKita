<?php

namespace App\Filament\Resources\Dues\Schemas;

use App\Models\ClassRoom;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DueForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Iuran')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('Contoh: Iuran Kas Bulanan'),

                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->maxLength(65535)
                    ->placeholder('Keterangan tambahan iuran'),

                TextInput::make('amount')
                    ->label('Nominal Iuran')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(1)
                    ->maxValue(999999999999),

                Select::make('period_type')
                    ->label('Periode Iuran')
                    ->options([
                        'mingguan' => 'Mingguan',
                        'bulanan' => 'Bulanan',
                        'tahunan' => 'Tahunan',
                        'sekali' => 'Sekali Bayar',
                    ])
                    ->required()
                    ->native(false),

                Select::make('management_scope')
                    ->label('Cakupan Iuran')
                    ->options([
                        'angkatan' => 'Seluruh Angkatan',
                        'kelas' => 'Kelas Tertentu',
                    ])
                    ->default('angkatan')
                    ->visible(fn() => $user?->role === 'bendahara_angkatan')
                    ->live()
                    ->required(),

                Select::make('class_id')
                    ->label('Pilih Kelas')
                    ->options(fn() => ClassRoom::query()
                        ->where('cohort_id', $user?->cohort_id)
                        ->where('management_type', 'angkatan')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->visible(
                        fn(Get $get) =>
                            $user?->role === 'bendahara_angkatan'
                            && $get('management_scope') === 'kelas'
                    )
                    ->required(
                        fn(Get $get) =>
                            $user?->role === 'bendahara_angkatan'
                            && $get('management_scope') === 'kelas'
                    )
                    ->native(false)
                    ->searchable()
                    ->placeholder('Pilih kelas'),

                Toggle::make('is_active')
                    ->label('Status Aktif')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger'),
            ]);
    }
}
