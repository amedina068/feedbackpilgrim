<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;

/**
 * Sets a new password for a user without email, for an instance with no SMTP.
 *
 * The password obeys the same rules as the registration and reset forms. The
 * command then ends the other logins of the user. Feedback Pilgrim has no
 * two-factor authentication, so the command has nothing else to reset.
 */
class UserPasswordCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fp:user:password
        {email : The email of the user}
        {--password= : The new password. The command does not prompt}
        {--generate : Make a random password and show it one time}';

    /**
     * @var string
     */
    protected $description = 'Set a new password for a user, without email';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error("No user has the email {$this->argument('email')}.");

            return self::FAILURE;
        }

        if ($this->option('password') !== null && $this->option('generate')) {
            $this->components->error('Use --password or --generate, not the two together.');

            return self::FAILURE;
        }

        $password = $this->newPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        $this->components->info("The password of {$user->email} changed.");

        if ($this->option('generate')) {
            $this->line("  New password: {$password}");
            $this->components->warn('Keep this password now. The command does not show it again.');
        }

        $this->components->bulletList([
            'Rotated the remember token. All "Remember me" logins ended.',
            $this->endWebSessions($user),
        ]);

        return self::SUCCESS;
    }

    /**
     * Returns null when no password is available. The error is already on the screen.
     */
    private function newPassword(): ?string
    {
        if ($this->option('password') !== null) {
            return $this->option('password');
        }

        if ($this->option('generate')) {
            // No symbols, so that the password is easy to copy from a terminal.
            return Str::password(32, symbols: false);
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Give --password or --generate when the command cannot prompt.');

            return null;
        }

        $password = password('New password', required: true);

        if ($password !== password('Type the new password again', required: true)) {
            $this->components->error('The two passwords are not the same.');

            return null;
        }

        return $password;
    }

    /**
     * The database driver stores the user id of each session, so the command
     * removes those rows. Other drivers cannot find the sessions of one user.
     * With them, a session stays open until it expires or the user logs out.
     */
    private function endWebSessions(User $user): string
    {
        $driver = config('session.driver');

        if ($driver !== 'database') {
            return "The \"{$driver}\" session driver cannot find the sessions of one user. "
                .'An open web session stays open until it expires or the user logs out.';
        }

        $ended = DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $user->id)
            ->delete();

        return "Ended {$ended} web sessions of this user.";
    }
}
