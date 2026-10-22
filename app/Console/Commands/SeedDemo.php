<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('app:seed-demo {--domain=alfapos.app : İstifadəçi emaillərinin domeni}')]
#[Description('Demo biznes və istifadəçiləri təsadüfi güclü parollar və PIN-lərlə yaradır; onları bir dəfə göstərir')]
class SeedDemo extends Command
{
    public function handle(): int
    {
        DemoSeeder::$domain = $this->option('domain');
        DemoSeeder::$passwords = collect(DemoSeeder::USERS)->map(fn () => Str::password(14, symbols: false))->all();
        DemoSeeder::$pins = $this->uniquePins(array_keys(DemoSeeder::PIN_USERS));

        if (\App\Models\User::where('email', 'demo@'.DemoSeeder::$domain)->exists()) {
            $this->error('Demo məlumatlar artıq var.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $keys = array_unique([...array_keys(DemoSeeder::USERS), ...array_keys(DemoSeeder::PIN_USERS)]);
        $this->table(['İstifadəçi', 'Email', 'Parol', 'PIN'], array_map(fn ($key) => [
            DemoSeeder::USERS[$key] ?? ucfirst($key),
            isset(DemoSeeder::USERS[$key]) ? $key.'@'.DemoSeeder::$domain : '— (yalnız PIN)',
            DemoSeeder::$passwords[$key] ?? '—',
            DemoSeeder::$pins[$key] ?? '—',
        ], $keys));

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    public static function uniquePins(array $keys): array
    {
        $pins = [];
        foreach ($keys as $key) {
            do {
                $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            } while (in_array($pin, $pins, true) || preg_match('/^(\d)\1{3}$|^0123|^1234/', $pin));
            $pins[$key] = $pin;
        }

        return $pins;
    }
}
