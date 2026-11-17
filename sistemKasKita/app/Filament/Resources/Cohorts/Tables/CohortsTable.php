<?php

namespace App\Filament\Resources\Cohorts\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CohortsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Angkatan')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('institution_name')
                    ->label('Institusi')
                    ->placeholder('Belum diisi')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('academic_year')
                    ->label('Tahun Akademik')
                    ->placeholder('Belum diisi')
                    ->sortable(),

                TextColumn::make('treasurer.nama')
                    ->label('Bendahara Angkatan')
                    ->placeholder('Belum ditentukan')
                    ->searchable(),

                TextColumn::make('classes_count')
                    ->label('Jumlah Kelas')
                    ->counts('classes')
                    ->formatStateUsing(fn($state) => $state . ' Kelas')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label('Jumlah Pengguna')
                    ->counts('users')
                    ->formatStateUsing(fn($state) => $state . ' Orang')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
