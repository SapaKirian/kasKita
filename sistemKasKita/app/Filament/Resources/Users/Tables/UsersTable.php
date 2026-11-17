<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bendahara_angkatan' => 'success',
                        'bendahara_kelas' => 'info',
                        'anggota' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->placeholder('-'),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role Pengguna')
                    ->options([
                        'bendahara_angkatan' => 'Bendahara Angkatan',
                        'bendahara_kelas' => 'Bendahara Kelas',
                        'anggota' => 'Anggota',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Status Akun')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->placeholder('Semua status'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}