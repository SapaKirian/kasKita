<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use App\Models\BillRecipient;
use App\Models\Due;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = auth()->user();

            if (!$user) {
                throw ValidationException::withMessages([
                    'dues_id' => 'Pengguna tidak ditemukan.',
                ]);
            }

            // Validasi iuran berdasarkan hak akses bendahara.
            $dueQuery = Due::query()
                ->whereKey($data['dues_id'] ?? null)
                ->where('is_active', true);

            if ($user->role === 'bendahara_angkatan') {
                if (!$user->cohort_id) {
                    throw ValidationException::withMessages([
                        'dues_id' => 'Akun Bendahara Angkatan belum memiliki angkatan.',
                    ]);
                }

                $dueQuery
                    ->where('cohort_id', $user->cohort_id)
                    ->where('management_type', 'angkatan');

            } elseif ($user->role === 'bendahara_kelas') {
                if (!$user->class_id) {
                    throw ValidationException::withMessages([
                        'dues_id' => 'Akun Bendahara Kelas belum memiliki kelas.',
                    ]);
                }

                $dueQuery
                    ->where('class_id', $user->class_id)
                    ->where('management_type', $user->management_type);

            } else {
                throw ValidationException::withMessages([
                    'dues_id' => 'Anda tidak memiliki izin membuat tagihan.',
                ]);
            }

            $due = $dueQuery->first();

            if (!$due) {
                throw ValidationException::withMessages([
                    'dues_id' => 'Iuran tidak ditemukan, tidak aktif, atau bukan kewenangan Anda.',
                ]);
            }

            // Nominal tagihan harus sesuai dengan nominal iuran.
            if (
                round((float) $data['amount'], 2) !==
                round((float) $due->amount, 2)
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal tagihan harus sama dengan nominal iuran yang dipilih.',
                ]);
            }

            // Cari anggota sesuai cakupan iuran.
            $memberQuery = User::query()
                ->where('role', 'anggota')
                ->where('is_active', true);

            if ($due->class_id !== null) {
                $memberQuery->where('class_id', $due->class_id);
            } else {
                $memberQuery->where('cohort_id', $due->cohort_id);
            }

            $members = $memberQuery->get();

            if ($members->isEmpty()) {
                throw ValidationException::withMessages([
                    'dues_id' => 'Tidak ada anggota aktif yang menjadi penerima tagihan ini.',
                ]);
            }

            // Simpan tagihan.
            $data['created_by'] = $user->id;

            $bill = Bill::create($data);

            // Buat penerima tagihan secara otomatis.
            foreach ($members as $member) {
                BillRecipient::create([
                    'bill_id' => $bill->id,
                    'user_id' => $member->id,
                    'class_id' => $member->class_id,
                    'bill_amount' => $bill->amount,
                    'paid_amount' => 0,
                    'status' => 'belum_dibayar',
                ]);
            }

            return $bill;
        });
    }
}
