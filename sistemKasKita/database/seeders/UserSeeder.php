<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Bendahara Angkatan
        User::updateOrCreate(
            ['username' => 'bendahara.angkatan'],
            [
                'nama' => 'Bendahara Angkatan',
                'email' => 'angkatan@kaskita.test',
                'password' => 'password123',
                'role' => 'bendahara_angkatan',
                'management_type' => 'angkatan',
                'is_active' => true,
            ]
        );

        // Bendahara Kelas
        User::updateOrCreate(
            ['username' => 'bendahara.kelas'],
            [
                'nama' => 'Bendahara Kelas',
                'email' => 'kelas@kaskita.test',
                'password' => 'password123',
                'role' => 'bendahara_kelas',
                'management_type' => 'kelas_mandiri',
                'is_active' => true,
            ]
        );

        // Anggota
        User::updateOrCreate(
            ['username' => 'anggota01'],
            [
                'nama' => 'Anggota KasKita',
                'email' => 'anggota@kaskita.test',
                'password' => 'password123',
                'role' => 'anggota',
                'management_type' => 'angkatan',
                'is_active' => true,
            ]
        );

        // Membuat 20 anggota dummy
        for ($i = 1; $i <= 20; $i++) {
            User::firstOrCreate(
                ['username' => 'anggota' . str_pad($i + 1, 2, '0', STR_PAD_LEFT)],
                [
                    'nama' => fake()->name(),
                    'email' => 'anggota' . ($i + 1) . '@kaskita.test',
                    'password' => 'password123',
                    'role' => 'anggota',
                    'management_type' => 'angkatan',
                    'is_active' => true,
                ]
            );
        }
    }
}
