<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-superadmin {email} {--name=Super Admin}')]
#[Description('Platforma superadmini yaradır və ya mövcud istifadəçinin parolunu yeniləyir (parol interaktiv soruşulur)')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->secret('Parol (min. 8 simvol)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8)],
        ]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        if ($existing && ! $existing->isSuperAdmin()) {
            $this->error('Bu email bir biznesin istifadəçisinə məxsusdur.');

            return self::FAILURE;
        }

        User::updateOrCreate(['email' => $email], [
            'name' => $this->option('name'),
            'password' => $password,
            'role' => 'superadmin',
            'tenant_id' => null,
            'is_active' => true,
        ]);

        $this->info("Superadmin hazırdır: {$email}");

        return self::SUCCESS;
    }
}
