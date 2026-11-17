<?php

namespace App\Filament\Resources\Bills\Schemas;

use App\Models\Due;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema->components([
            Select::make('dues_id')
                ->label('Pilih Iuran')
                ->options(function () use ($user) {
                    if (!$user) {
                        return [];
                    }

                    $query = Due::query();

                    if ($user->role === 'bendahara_angkatan') {
                        $query->where('cohort_id', $user->cohort_id);
                    } elseif ($user->role === 'bendahara_kelas') {
                        $query->where('class_id', $user->class_id);
                    } else {
                        return [];
                    }

                    return $query
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->helperText('Pilih iuran yang akan ditagihkan.'),

            TextInput::make('title')
                ->label('Judul Tagihan')
                ->required()
                ->maxLength(150)
                ->placeholder('Contoh: Tagihan Kas Bulan November 2026'),

            TextInput::make('period_month')
                ->label('Bulan Periode')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->placeholder('1 - 12')
                ->helperText('Kosongkan jika tagihan tidak menggunakan periode bulanan.'),

            TextInput::make('period_year')
                ->label('Tahun Periode')
                ->numeric()
                ->minValue(2000)
                ->maxValue(2100)
                ->placeholder('Contoh: 2026'),

            TextInput::make('amount')
                ->label('Nominal Tagihan')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->minValue(1)
                ->maxValue(999999999999),

            DatePicker::make('due_date')
                ->label('Tanggal Jatuh Tempo')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y'),
        ]);
    }
}
