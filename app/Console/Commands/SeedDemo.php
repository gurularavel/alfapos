<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:seed-demo {--domain=alfapos.app : İstifadəçi emaillərinin domeni}')]
#[Description('Demo biznes və istifadəçiləri təsadüfi güclü parollarla yaradır; parolları bir dəfə göstərir')]
class SeedDemo extends Command
{
    public function handle(): int
    {
        DemoSeeder::$domain = $this->option('domain');
        DemoSeeder::$passwords = collect(DemoSeeder::USERS)->map(fn () => Str::password(14, symbols: false))->all();

        if (\App\Models\User::where('email', 'demo@'.DemoSeeder::$domain)->exists()) {
            $this->error('Demo məlumatlar artıq var.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->table(['Rol', 'Email', 'Parol'], collect(DemoSeeder::USERS)->map(fn ($role, $key) => [
            $role, $key.'@'.DemoSeeder::$domain, DemoSeeder::$passwords[$key],
        ])->values()->all());

        return self::SUCCESS;
    }
}
