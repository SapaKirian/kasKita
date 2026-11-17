<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'bendahara_angkatan') {
            return $query
                ->where('cohort_id', $user->cohort_id)
                ->whereIn('role', ['bendahara_kelas', 'anggota'])
                ->where('id', '!=', $user->id);
        }

        if ($user->role === 'bendahara_kelas') {
            return $query
                ->where('class_id', $user->class_id)
                ->where('role', 'anggota')
                ->where('id', '!=', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, [
            'bendahara_angkatan',
            'bendahara_kelas',
        ]);
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()?->role, [
            'bendahara_angkatan',
            'bendahara_kelas',
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (!$user || $record->id === $user->id) {
            return false;
        }

        if ($user->role === 'bendahara_angkatan') {
            return (int) $record->cohort_id === (int) $user->cohort_id
                && in_array($record->role, ['bendahara_kelas', 'anggota']);
        }

        if ($user->role === 'bendahara_kelas') {
            return (int) $record->class_id === (int) $user->class_id
                && $record->role === 'anggota';
        }

        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
