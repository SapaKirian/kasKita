<?php

namespace App\Filament\Widgets;

use App\Models\CashTransaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentTransactions extends TableWidget
{
    protected static ?string $heading = 'Aktivitas Transaksi Terbaru';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, [
            'bendahara_angkatan',
            'bendahara_kelas',
        ]);
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        $query = CashTransaction::query()
            ->with('classroom')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        // Bendahara Angkatan hanya melihat transaksi angkatannya.
        if ($user?->role === 'bendahara_angkatan' && $user->cohort_id) {
            $query->where('cohort_id', $user->cohort_id);
        }
        // Bendahara Kelas hanya melihat transaksi kelasnya.
        elseif ($user?->role === 'bendahara_kelas' && $user->class_id) {
            $query->where('class_id', $user->class_id);
        } else {
            $query->whereRaw('1 = 0');
        }

        $columns = [
            TextColumn::make('transaction_date')
                ->label('Tanggal')
                ->dateTime('d M Y H:i')
                ->sortable(),

            TextColumn::make('category')
                ->label('Kategori')
                ->searchable(),

            TextColumn::make('description')
                ->label('Keterangan')
                ->placeholder('Tidak ada keterangan')
                ->limit(50)
                ->wrap(),

            TextColumn::make('transaction_type')
                ->label('Jenis Transaksi')
                ->badge()
                ->formatStateUsing(fn(string $state): string => match ($state) {
                    'pemasukan' => 'Pemasukan',
                    'pengeluaran' => 'Pengeluaran',
                    default => $state,
                })
                ->color(fn(string $state): string => match ($state) {
                    'pemasukan' => 'success',
                    'pengeluaran' => 'danger',
                    default => 'gray',
                }),

            TextColumn::make('amount')
                ->label('Nominal')
                ->formatStateUsing(
                    fn($state): string => 'Rp ' . number_format((float) $state, 0, ',', '.')
                )
                ->sortable(),
        ];

        // Nama kelas hanya ditampilkan untuk Bendahara Angkatan.
        if ($user?->role === 'bendahara_angkatan') {
            $columns[] = TextColumn::make('classroom.name')
                ->label('Kelas')
                ->placeholder('Tidak ditentukan');
        }

        return $table
            ->query(fn(): Builder => $query)
            ->columns($columns)
            ->defaultSort('transaction_date', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}
