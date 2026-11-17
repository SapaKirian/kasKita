<?php

namespace App\Filament\Resources\Bills\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Daftar Penerima Tagihan';

    protected static ?string $modelLabel = 'Penerima';

    protected static ?string $pluralModelLabel = 'Penerima Tagihan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nama')
                    ->label('Nama Anggota')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('bill_amount')
                    ->label('Nominal Tagihan')
                    ->formatStateUsing(
                        fn($state): string => 'Rp ' .
                            number_format((float) $state, 0, ',', '.')
                    ),

                TextColumn::make('paid_amount')
                    ->label('Sudah Dibayar')
                    ->formatStateUsing(
                        fn($state): string => 'Rp ' .
                            number_format((float) $state, 0, ',', '.')
                    ),

                TextColumn::make('remaining_amount')
                    ->label('Sisa Tagihan')
                    ->state(
                        fn($record): float => max(
                            0,
                            (float) $record->bill_amount -
                            (float) $record->paid_amount
                        )
                    )
                    ->formatStateUsing(
                        fn($state): string => 'Rp ' .
                            number_format((float) $state, 0, ',', '.')
                    )
                    ->color(
                        fn($state): string =>
                            (float) $state > 0 ? 'danger' : 'success'
                    ),

                TextColumn::make('status')
                    ->label('Status Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'belum_dibayar' => 'Belum Dibayar',
                        'menunggu_verifikasi' => 'Menunggu Verifikasi',
                        'lunas' => 'Lunas',
                        'ditolak' => 'Ditolak',
                        'terlambat' => 'Terlambat',
                        default => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'belum_dibayar' => 'gray',
                        'menunggu_verifikasi' => 'warning',
                        'lunas' => 'success',
                        'ditolak', 'terlambat' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Ditambahkan')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}