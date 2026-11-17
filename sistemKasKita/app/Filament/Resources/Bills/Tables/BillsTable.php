<?php

namespace App\Filament\Resources\Bills\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul Tagihan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('due.name')
                    ->label('Sumber Iuran')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(
                        fn($state): string => 'Rp ' .
                            number_format((float) $state, 0, ',', '.')
                    )
                    ->sortable(),

                TextColumn::make('period_month')
                    ->label('Bulan')
                    ->formatStateUsing(fn($state) => $state
                        ? date('F', mktime(0, 0, 0, (int) $state, 1))
                        : '-'),

                TextColumn::make('period_year')
                    ->label('Tahun')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('recipients_count')
                    ->label('Jumlah Penerima')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('creator.nama')
                    ->label('Dibuat Oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('period_year')
                    ->label('Tahun')
                    ->options(
                        fn() => \App\Models\Bill::query()
                            ->whereNotNull('period_year')
                            ->distinct()
                            ->orderByDesc('period_year')
                            ->pluck('period_year', 'period_year')
                            ->toArray()
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail'),

                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}