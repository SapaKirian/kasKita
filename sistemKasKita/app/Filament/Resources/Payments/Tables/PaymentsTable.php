<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\BillRecipient;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payer.nama')
                    ->label('Anggota')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('billRecipient.bill.title')
                    ->label('Tagihan')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('billRecipient.classroom.name')
                    ->label('Kelas')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(
                        fn($state): string => 'Rp ' .
                            number_format((float) $state, 0, ',', '.')
                    )
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(
                        fn($state) => $state === 'transfer'
                            ? 'Transfer'
                            : 'Tunai'
                    )
                    ->color(
                        fn($state) => $state === 'transfer'
                            ? 'info'
                            : 'success'
                    ),

                TextColumn::make('payment_date')
                    ->label('Tanggal Pembayaran')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn() => 'Menunggu Verifikasi'
                    )
                    ->color('warning'),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'tunai' => 'Tunai',
                        'transfer' => 'Transfer',
                    ]),
            ])
            ->recordActions([
                Action::make('lihat_bukti')
                    ->label('Bukti')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(
                        fn(Payment $record): bool =>
                            $record->payment_method === 'transfer'
                            && $record->proofs()->exists()
                    )
                    ->url(
                        fn(Payment $record): string =>
                            route('payment-proofs.download', $record->proofs()->firstOrFail()->id)
                    )
                    ->openUrlInNewTab(),

                Action::make('setujui')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Setujui Pembayaran')
                    ->modalDescription(
                        'Apakah Anda yakin ingin menyetujui pembayaran ini?'
                    )
                    ->action(function (Payment $record): void {
                        abort_unless(
                            PaymentResource::canView($record),
                            403
                        );

                        DB::transaction(function () use ($record) {
                            $recipient = BillRecipient::query()
                                ->whereKey($record->bill_recipient_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $payment = Payment::query()
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if (
                                $payment->status !==
                                'menunggu_verifikasi'
                            ) {
                                throw ValidationException::withMessages([
                                    'status' =>
                                        'Pembayaran sudah diverifikasi.',
                                ]);
                            }

                            $newPaidAmount =
                                (float) $recipient->paid_amount
                                + (float) $payment->amount;

                            if (
                                $newPaidAmount >
                                (float) $recipient->bill_amount
                            ) {
                                throw ValidationException::withMessages([
                                    'amount' =>
                                        'Pembayaran melebihi total tagihan.',
                                ]);
                            }

                            $recipient->paid_amount = $newPaidAmount;

                            $payment->status = 'disetujui';
                            $payment->rejection_reason = null;
                            $payment->verified_by = auth()->id();
                            $payment->verified_at = now();
                            $payment->save();

                            $hasPending = $recipient->payments()
                                ->where(
                                    'status',
                                    'menunggu_verifikasi'
                                )
                                ->exists();

                            if ($hasPending) {
                                $recipient->status =
                                    'menunggu_verifikasi';
                            } elseif (
                                (float) $recipient->paid_amount >=
                                (float) $recipient->bill_amount
                            ) {
                                $recipient->status = 'lunas';
                            } else {
                                $recipient->status = 'belum_dibayar';
                            }

                            $recipient->save();
                        });

                        Notification::make()
                            ->title('Pembayaran berhasil disetujui')
                            ->success()
                            ->send();
                    }),

                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->modalHeading('Tolak Pembayaran')
                    ->modalDescription(
                        'Masukkan alasan mengapa pembayaran ini ditolak.'
                    )
                    ->modalSubmitActionLabel('Tolak Pembayaran')
                    ->action(function (Payment $record, array $data): void {
                        abort_unless(
                            PaymentResource::canView($record),
                            403
                        );

                        DB::transaction(function () use ($record, $data) {
                            $recipient = BillRecipient::query()
                                ->whereKey($record->bill_recipient_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $payment = Payment::query()
                                ->whereKey($record->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if (
                                $payment->status !==
                                'menunggu_verifikasi'
                            ) {
                                throw ValidationException::withMessages([
                                    'status' =>
                                        'Pembayaran sudah diverifikasi.',
                                ]);
                            }

                            $payment->status = 'ditolak';
                            $payment->rejection_reason =
                                $data['rejection_reason'];
                            $payment->verified_by = auth()->id();
                            $payment->verified_at = now();
                            $payment->save();

                            $hasPending = $recipient->payments()
                                ->where(
                                    'status',
                                    'menunggu_verifikasi'
                                )
                                ->exists();

                            if ($hasPending) {
                                $recipient->status =
                                    'menunggu_verifikasi';
                            } elseif (
                                (float) $recipient->paid_amount >=
                                (float) $recipient->bill_amount
                            ) {
                                $recipient->status = 'lunas';
                            } else {
                                $recipient->status = 'belum_dibayar';
                            }

                            $recipient->save();
                        });

                        Notification::make()
                            ->title('Pembayaran berhasil ditolak')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('payment_date', 'asc')
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }
}
