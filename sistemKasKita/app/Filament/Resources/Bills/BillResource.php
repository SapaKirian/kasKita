<?php

namespace App\Filament\Resources\Bills;

use App\Filament\Resources\Bills\Pages\CreateBill;
use App\Filament\Resources\Bills\Pages\EditBill;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\Bills\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\Bills\Schemas\BillForm;
use App\Filament\Resources\Bills\Tables\BillsTable;
use App\Models\Bill;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillResource extends Resource
{
    protected static ?string $model = Bill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Manajemen Tagihan';

    protected static ?string $modelLabel = 'Tagihan';

    protected static ?string $pluralModelLabel = 'Tagihan';

    protected static UnitEnum|string|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return BillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->with(['due', 'creator'])
            ->withCount('recipients');

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'bendahara_angkatan') {
            if (!$user->cohort_id) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('due', function (Builder $q) use ($user) {
                $q->where('cohort_id', $user->cohort_id);
            });
        }

        if ($user->role === 'bendahara_kelas') {
            if (!$user->class_id) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('due', function (Builder $q) use ($user) {
                $q->where('class_id', $user->class_id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function canViewAny(): bool
    {
        return in_array(auth()->user()?->role, [
            'bendahara_angkatan',
            'bendahara_kelas',
        ]);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'bendahara_angkatan') {
            return $user->cohort_id !== null
                && (int) $record->due?->cohort_id === (int) $user->cohort_id;
        }

        if ($user->role === 'bendahara_kelas') {
            return $user->class_id !== null
                && (int) $record->due?->class_id === (int) $user->class_id;
        }

        return false;
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
        return static::canView($record);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBills::route('/'),
            'create' => CreateBill::route('/create'),
            'view' => ViewBill::route('/{record}'),
            'edit' => EditBill::route('/{record}/edit'),
        ];
    }
}