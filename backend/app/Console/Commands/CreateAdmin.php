<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'remi:admin {email? : Owner login email for interactive setup} {--from-env : Read REMI_OWNER_EMAIL and REMI_OWNER_PASSWORD securely from the environment}';

    protected $description = 'Create an administrator interactively without default credentials';

    public function handle(): int
    {
        if (User::where('owner_slot', 1)->exists()) {
            $this->error('The salon owner account already exists. Use password change or recovery; additional accounts are not permitted.');

            return self::FAILURE;
        }
        if ($this->option('from-env')) {
            $email = getenv('REMI_OWNER_EMAIL') ?: env('REMI_OWNER_EMAIL');
            $password = getenv('REMI_OWNER_PASSWORD') ?: env('REMI_OWNER_PASSWORD');
            $name = 'Salon owner';
        } else {
            if ($this->option('no-interaction')) {
                $this->error('Run this command in an interactive local terminal to choose your password.');

                return self::FAILURE;
            }
            $email = $this->argument('email') ?: (getenv('REMI_OWNER_EMAIL') ?: env('REMI_OWNER_EMAIL')) ?: $this->ask('Owner login email');
            $name = $this->ask('Name', 'Remi Touch owner');
            $password = $this->secret('Password (12+ chars, mixed case and a number)', false);
            $confirmation = $this->secret('Confirm password', false);
            if (! is_string($password) || ! is_string($confirmation) || ! hash_equals($password, $confirmation)) {
                $this->error('Passwords do not match. No account was created.');

                return self::FAILURE;
            }
        }
        $data = ['email' => strtolower(trim((string) $email)), 'name' => $name, 'password' => $password, 'role' => 'owner'];
        $validator = validator($data, ['email' => 'required|email|unique:users,email', 'name' => 'required|string|max:120', 'password' => ['required', Password::min(12)->mixedCase()->numbers()], 'role' => 'required|in:owner']);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $created = DB::transaction(function () use ($data): bool {
            DB::table('platform_mutexes')->where('name', 'owner')->lockForUpdate()->firstOrFail();
            if (User::where('owner_slot', 1)->exists()) {
                return false;
            }
            $user = new User;
            $user->forceFill($data);
            $user->save();

            return true;
        });
        if (! $created) {
            $this->error('The owner account was initialized by another request.');

            return self::FAILURE;
        }
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
