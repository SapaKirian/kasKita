<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\ClassRoom;
use App\Models\Cohort;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Manajemen Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static UnitEnum|string|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->with([
                'payer',
                'billRecipient.bill.due',
                'billRecipient.classroom',
                'proofs',
            ])
            ->where('status', 'menunggu_verifikasi');

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role === 'bendahara_kelas') {
            if (!$user->class_id) {
                return $query->whereRaw('1 = 0');
            }

            $authorized = ClassRoom::query()
                ->whereKey($user->class_id)
                ->where('treasurer_id', $user->id)
                ->exists();

            if (!$authorized) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas(
                'billRecipient',
                fn(Builder $q) => $q->where('class_id', $user->class_id)
            );
        }

        if ($user->role === 'bendahara_angkatan') {
            if (!$user->cohort_id) {
                return $query->whereRaw('1 = 0');
            }

            $authorized = Cohort::query()
                ->whereKey($user->cohort_id)
                ->where('treasurer_id', $user->id)
                ->exists();

            if (!$authorized) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas(
                'billRecipient.bill.due',
                function (Builder $q) use ($user) {
                    $q
                        ->where('management_type', 'angkatan')
                        ->where('cohort_id', $user->cohort_id)
                        ->whereNull('class_id');
                }
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->role === 'bendahara_kelas') {
            return $user->class_id !== null
                && ClassRoom::query()
                    ->whereKey($user->class_id)
                    ->where('treasurer_id', $user->id)
                    ->exists();
        }

        if ($user->role === 'bendahara_angkatan') {
            return $user->cohort_id !== null
                && Cohort::query()
                    ->whereKey($user->cohort_id)
                    ->where('treasurer_id', $user->id)
                    ->exists();
        }

        return false;
    }

    public static function canView($record): bool
    {
        return static::getEloquentQuery()
            ->whereKey($record->getKey())
            ->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
