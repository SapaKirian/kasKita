<?php

namespace App\Filament\Resources\ClassRooms;

use App\Filament\Resources\ClassRooms\Pages\CreateClassRoom;
use App\Filament\Resources\ClassRooms\Pages\EditClassRoom;
use App\Filament\Resources\ClassRooms\Pages\ListClassRooms;
use App\Filament\Resources\ClassRooms\Schemas\ClassRoomForm;
use App\Filament\Resources\ClassRooms\Tables\ClassRoomsTable;
use App\Models\ClassRoom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClassRoomResource extends Resource
{
    protected static ?string $model = ClassRoom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Manajemen Kelas';

    protected static ?string $modelLabel = 'Kelas';

    protected static ?string $pluralModelLabel = 'Kelas';

    protected static string|null|\UnitEnum $navigationGroup = 'Manajemen';

    public static function form(Schema $schema): Schema
    {
        return ClassRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassRoomsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassRooms::route('/'),
            'create' => CreateClassRoom::route('/create'),
            'edit' => EditClassRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery();

        if (!$user || $user->role !== 'bendahara_angkatan') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('cohort_id', $user->cohort_id)
            ->where('management_type', $user->management_type);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'bendahara_angkatan';
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role === 'bendahara_angkatan';
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user
            && $user->role === 'bendahara_angkatan'
            && (int) $record->cohort_id === (int) $user->cohort_id
            && $record->management_type === $user->management_type;
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
