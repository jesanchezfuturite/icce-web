<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas de revisión del entorno local. NO se ejecuta en producción: las
 * contraseñas son conocidas y sirven para recorrer los tres perfiles del
 * sistema (visitante, cliente registrado y administrador).
 */
class UserSeeder extends Seeder
{
    public const REVIEW_PASSWORD = 'Icce2026';

    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrador ICCE',
                'email' => 'admin@icce.com',
                'role' => UserRole::Admin,
                'phone' => '81 8100 0000',
            ],
            [
                'name' => 'Agente de Ventas',
                'email' => 'ventas@icce.com',
                'role' => UserRole::Sales,
                'phone' => '81 8100 0001',
            ],
            [
                'name' => 'Constructora del Norte S.A. de C.V.',
                'email' => 'registrado@icce.com',
                'role' => UserRole::Client,
                'phone' => '81 8100 0002',
                'company' => 'Constructora del Norte S.A. de C.V.',
                'rfc' => 'CNO980415AB2',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'phone' => $user['phone'],
                    'company' => $user['company'] ?? null,
                    'rfc' => $user['rfc'] ?? null,
                    'password' => Hash::make(self::REVIEW_PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
        }

        $this->command?->warn(
            'Usuarios de revisión creados con la contraseña "'.self::REVIEW_PASSWORD.'" — solo entorno local.',
        );
    }
}
