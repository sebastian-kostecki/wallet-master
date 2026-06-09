<?php

declare(strict_types=1);

namespace App\Console\Commands\Auth;

use App\Actions\Categories\EnsureUserCategories;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('user:create
    {--name= : Display name}
    {--email= : Login email}
    {--password= : Plain password (omit to prompt securely)}
    {--unverified : Do not set email_verified_at}')]
#[Description('Create a new application user with starter categories.')]
final class CreateUser extends Command
{
    public function handle(): int
    {
        $name = $this->resolveName();
        $email = $this->resolveEmail();
        [$password, $passwordConfirmation] = $this->resolvePassword();

        try {
            $validated = Validator::make(
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'password_confirmation' => $passwordConfirmation,
                ],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                    'password' => ['required', 'confirmed', Password::defaults()],
                ],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        if (! $this->option('unverified')) {
            $user->email_verified_at = now();
            $user->save();
        }

        app(EnsureUserCategories::class)->handle($user);

        $this->info("User #{$user->id} created ({$user->email}).");

        return self::SUCCESS;
    }

    private function resolveName(): string
    {
        $name = $this->option('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return text(
            label: 'Name',
            required: true,
        );
    }

    private function resolveEmail(): string
    {
        $email = $this->option('email');

        if (is_string($email) && $email !== '') {
            return $email;
        }

        return text(
            label: 'Email',
            required: true,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePassword(): array
    {
        $password = $this->option('password');

        if (is_string($password) && $password !== '') {
            return [$password, $password];
        }

        $password = password(
            label: 'Password',
            required: true,
        );

        $confirmation = password(
            label: 'Confirm password',
            required: true,
        );

        return [$password, $confirmation];
    }
}
