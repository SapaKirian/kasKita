<?php

namespace App\Filament\Resources\ClassRooms\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ClassRoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('name')
                    ->label('Nama Kelas')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('treasurer.nama')
                    ->label('Bendahara Kelas')
                    ->placeholder('Belum ditentukan')
                    ->searchable(),

                TextColumn::make('users_count')
                    ->label('Jumlah Anggota')
                    ->counts('users')
                    ->formatStateUsing(fn($state) => $state . ' Orang')
                    ->sortable(),

                TextColumn::make('management_type')
                    ->label('Jenis Pengelolaan')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'angkatan' => 'Angkatan',
                        'kelas_mandiri' => 'Kelas Mandiri',
                        default => $state,
                    })
                    ->color(fn(string $state) => match ($state) {
                        'angkatan' => 'success',
                        'kelas_mandiri' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->date('d M Y')
                    ->sortable(),

            ])
            ->filters([
                SelectFilter::make('management_type')
                    ->label('Jenis Pengelolaan')
                    ->options([
                        'angkatan' => 'Angkatan',
                        'kelas_mandiri' => 'Kelas Mandiri',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
