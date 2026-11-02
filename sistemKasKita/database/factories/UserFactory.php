<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class UserFactory extends Factory
{
    use HasApiTokens, HasFactory, Notifiable;
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'role' => fake()->randomElement([
                'bendahara_angkatan',
                'bendahara_kelas',
                'anggota',
            ]),
            'management_type' => fake()->randomElement([
                'angkatan',
                'kelas_mandiri',
            ]),
            'cohort_id' => null,
            'class_id' => null,
            'created_by' => null,
            'is_active' => true,
        ];
    }

    public function bendaharaAngkatan(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'bendahara_angkatan',
            'management_type' => 'angkatan',
        ]);
    }

    public function bendaharaKelas(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'bendahara_kelas',
            'management_type' => 'kelas_mandiri',
        ]);
    }

    public function anggota(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'anggota',
        ]);
    }
}
