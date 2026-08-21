<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {email : Administrator email} {--name= : Name for a new account}';

    protected $description = 'Create or safely promote an administrator account';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $existing->forceFill(['role' => 'admin', 'is_active' => true, 'email_verified_at' => $existing->email_verified_at ?? now()])->save();
            $this->info("Promoted {$existing->email} to active administrator.");

            return self::SUCCESS;
        }
        $name = trim((string) ($this->option('name') ?: $this->ask('Administrator name')));
        $password = (string) $this->secret('Password (12+ characters)');
        $confirmation = (string) $this->secret('Confirm password');
        $validation = Validator::make(compact('email', 'name', 'password', 'confirmation'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'same:confirmation'],
        ]);
        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User;
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin', 'is_active' => true, 'email_verified_at' => now()])->save();
        $this->info("Created active administrator {$email}.");

        return self::SUCCESS;
    }
}
