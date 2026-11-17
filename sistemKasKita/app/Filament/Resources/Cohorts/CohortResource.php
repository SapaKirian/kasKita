<?php

namespace App\Filament\Resources\Cohorts;

use App\Filament\Resources\Cohorts\Pages\EditCohort;
use App\Filament\Resources\Cohorts\Pages\ListCohorts;
use App\Filament\Resources\Cohorts\Schemas\CohortForm;
use App\Filament\Resources\Cohorts\Tables\CohortsTable;
use App\Models\Cohort;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CohortResource extends Resource
{
    protected static ?string $model = Cohort::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Manajemen Angkatan';

    protected static ?string $modelLabel = 'Angkatan';

    protected static ?string $pluralModelLabel = 'Angkatan';

    protected static string|null|\UnitEnum $navigationGroup = 'Manajemen';

    public static function form(Schema $schema): Schema
    {
        return CohortForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CohortsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCohorts::route('/'),
            'edit' => EditCohort::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (
            !$user ||
            $user->role !== 'bendahara_angkatan' ||
            !$user->cohort_id
        ) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereKey($user->cohort_id);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'bendahara_angkatan';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user
            && $user->role === 'bendahara_angkatan'
            && (int) $record->id === (int) $user->cohort_id;
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
