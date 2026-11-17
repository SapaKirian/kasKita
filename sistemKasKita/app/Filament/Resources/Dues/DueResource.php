<?php

namespace App\Filament\Resources\Dues;

use App\Filament\Resources\Dues\Pages\CreateDue;
use App\Filament\Resources\Dues\Pages\EditDue;
use App\Filament\Resources\Dues\Pages\ListDues;
use App\Filament\Resources\Dues\Schemas\DueForm;
use App\Filament\Resources\Dues\Tables\DuesTable;
use App\Models\Due;
use UnitEnum;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DueResource extends Resource
{
    protected static ?string $model = Due::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Manajemen Iuran';

    protected static ?string $modelLabel = 'Iuran';

    protected static ?string $pluralModelLabel = 'Iuran';

    protected static UnitEnum|string|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DueForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DuesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'bendahara_angkatan') {
            if (!$user->cohort_id) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('cohort_id', $user->cohort_id);
        }

        if ($user->role === 'bendahara_kelas') {
            if (!$user->class_id) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('class_id', $user->class_id);
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

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'bendahara_angkatan') {
            return $user->cohort_id !== null
                && $record->cohort_id === $user->cohort_id;
        }

        if ($user->role === 'bendahara_kelas') {
            return $user->class_id !== null
                && $record->class_id === $user->class_id;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDues::route('/'),
            'create' => CreateDue::route('/create'),
            'edit' => EditDue::route('/{record}/edit'),
        ];
    }
}
