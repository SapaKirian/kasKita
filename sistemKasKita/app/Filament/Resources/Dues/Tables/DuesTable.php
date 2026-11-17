<?php

namespace App\Filament\Resources\Dues\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Iuran')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(
                        fn($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')
                    )
                    ->sortable(),

                TextColumn::make('period_type')
                    ->label('Periode')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'mingguan' => 'Mingguan',
                        'bulanan' => 'Bulanan',
                        'tahunan' => 'Tahunan',
                        'sekali' => 'Sekali Bayar',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('management_type')
                    ->label('Jenis Pengelolaan')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'angkatan' => 'Angkatan',
                        'kelas_mandiri' => 'Kelas Mandiri',
                        default => $state,
                    })
                    ->badge(),

                TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->placeholder('Seluruh Angkatan')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('period_type')
                    ->label('Periode')
                    ->options([
                        'mingguan' => 'Mingguan',
                        'bulanan' => 'Bulanan',
                        'tahunan' => 'Tahunan',
                        'sekali' => 'Sekali Bayar',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Aktif',
                        0 => 'Tidak Aktif',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}
