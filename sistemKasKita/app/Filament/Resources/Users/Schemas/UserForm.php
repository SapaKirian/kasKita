<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\ClassRoom;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $actor = Auth::user();

        $roleOptions = match ($actor?->role) {
            'bendahara_angkatan' => [
                'bendahara_kelas' => 'Bendahara Kelas',
                'anggota' => 'Anggota',
            ],

            'bendahara_kelas' => [
                'anggota' => 'Anggota',
            ],

            default => [],
        };

        return $schema->components([

            TextInput::make('nama')
                ->label('Nama Lengkap')
                ->required()
                ->maxLength(100),

            TextInput::make('username')
                ->label('Username')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(100)
                ->nullable()
                ->unique(ignoreRecord: true),

            Select::make('role')
                ->label('Role Pengguna')
                ->options($roleOptions)
                ->default('anggota')
                ->required()
                ->disabledOn('edit')
                ->dehydrated(),

            Select::make('class_id')
                ->label('Penempatan Kelas')
                ->options(function () use ($actor) {
                    if (
                        !$actor ||
                        $actor->role !== 'bendahara_angkatan'
                    ) {
                        return [];
                    }

                    return ClassRoom::query()
                        ->where('cohort_id', $actor->cohort_id)
                        ->where('management_type', $actor->management_type)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->searchable()
                ->preload()
                ->required(
                    fn(string $operation) =>
                        $operation === 'create'
                        && $actor?->role === 'bendahara_angkatan'
                )
                ->visible(
                    fn(string $operation) =>
                        $operation === 'create'
                        && $actor?->role === 'bendahara_angkatan'
                )
                ->helperText('Pilih kelas tempat pengguna akan ditempatkan.'),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->required(
                    fn(string $operation) =>
                        $operation === 'create'
                )
                ->rule(Password::min(8))
                ->dehydrated(fn($state) => filled($state))
                ->helperText(
                    'Minimal 8 karakter. Kosongkan saat edit jika tidak ingin mengganti password.'
                ),

            Toggle::make('is_active')
                ->label('Akun Aktif')
                ->default(true)
                ->visibleOn('edit'),

        ]);
    }
}
