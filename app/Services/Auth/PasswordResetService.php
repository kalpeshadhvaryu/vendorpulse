<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Mail\ManagementEmailService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        protected ManagementEmailService $mail
    ) {}

    public function sendResetLink(string $email): void
    {
        $normalized = mb_strtolower(trim($email));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        if (! $user) {
            return;
        }

        $token = Password::broker()->createToken($user);
        $this->mail->sendPasswordResetEmail($user, $token);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function resetPassword(string $email, string $token, string $password): array
    {
        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user, string $newPassword): void {
                $user->forceFill([
                    'password' => $newPassword,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        return [
            'ok' => $status === Password::PASSWORD_RESET,
            'message' => __($status),
        ];
    }
}
