<?php

namespace App\Providers;

use App\Models\ClassRoom;
use App\Models\Cohort;
use App\Models\Payment;
use App\Models\Remittance;
use App\Support\Notifier;
use Illuminate\Support\ServiceProvider;

/**
 * Membuat notifikasi otomatis saat pembayaran / setoran dibuat atau berubah status,
 * tanpa perlu mengubah controller yang sudah ada.
 */
class NotificationEventsProvider extends ServiceProvider
{
    private static function rupiah($amount): string
    {
        return 'Rp' . number_format((float) $amount, 0, ',', '.');
    }

    public function boot(): void
    {
        Payment::created(function (Payment $payment) {
            $payment->loadMissing('billRecipient.bill', 'payer');
            $title = $payment->billRecipient->bill->title ?? 'tagihan';

            if ($payment->status === 'menunggu_verifikasi') {
                $treasurerId = ClassRoom::where('id', $payment->billRecipient->class_id ?? 0)
                    ->value('treasurer_id');

                Notifier::send(
                    $treasurerId,
                    'Pembayaran baru',
                    ($payment->payer->nama ?? 'Anggota') . ' mengajukan pembayaran ' . $title
                        . ' sebesar ' . self::rupiah($payment->amount) . '.',
                    'pembayaran_baru',
                    $payment->id
                );
            } elseif ($payment->status === 'disetujui') {
                Notifier::send(
                    $payment->payer_id,
                    'Pembayaran dicatat',
                    'Pembayaran ' . $title . ' sebesar ' . self::rupiah($payment->amount)
                        . ' telah dicatat lunas oleh bendahara.',
                    'pembayaran_disetujui',
                    $payment->id
                );
            }
        });

        Payment::updated(function (Payment $payment) {
            if (!$payment->wasChanged('status')) {
                return;
            }

            $payment->loadMissing('billRecipient.bill');
            $title = $payment->billRecipient->bill->title ?? 'tagihan';

            if ($payment->status === 'disetujui') {
                Notifier::send(
                    $payment->payer_id,
                    'Pembayaran disetujui',
                    'Pembayaran ' . $title . ' sebesar ' . self::rupiah($payment->amount) . ' sudah diverifikasi.',
                    'pembayaran_disetujui',
                    $payment->id
                );
            } elseif ($payment->status === 'ditolak') {
                Notifier::send(
                    $payment->payer_id,
                    'Pembayaran ditolak',
                    'Pembayaran ' . $title . ' ditolak.'
                        . ($payment->rejection_reason ? ' Alasan: ' . $payment->rejection_reason : ''),
                    'pembayaran_ditolak',
                    $payment->id
                );
            }
        });

        Remittance::created(function (Remittance $remittance) {
            $remittance->loadMissing('classroom');
            $treasurerId = Cohort::where('id', $remittance->cohort_id)->value('treasurer_id');

            Notifier::send(
                $treasurerId,
                'Setoran kelas baru',
                'Kelas ' . ($remittance->classroom->name ?? '-') . ' mengajukan setoran '
                    . self::rupiah($remittance->amount) . ' periode '
                    . $remittance->period_month . '/' . $remittance->period_year . '.',
                'setoran_baru',
                $remittance->id
            );
        });

        Remittance::updated(function (Remittance $remittance) {
            if (!$remittance->wasChanged('status')) {
                return;
            }

            $approved = $remittance->status === 'disetujui';

            Notifier::send(
                $remittance->submitted_by,
                $approved ? 'Setoran diterima' : 'Setoran ditolak',
                'Setoran ' . self::rupiah($remittance->amount) . ' periode '
                    . $remittance->period_month . '/' . $remittance->period_year
                    . ($approved ? ' telah diterima bendahara angkatan.' : ' ditolak bendahara angkatan.'),
                $approved ? 'setoran_disetujui' : 'setoran_ditolak',
                $remittance->id
            );
        });
    }
}
