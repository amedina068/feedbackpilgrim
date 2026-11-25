<?php

namespace App\Console\Commands;

use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
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
    use InteractsWithAgents;

    /**
     * @var string
     */
    protected $signature = 'fp:user:password
        {email : The email of the user}
        {--password= : The new password. The command does not prompt}
        {--generate : Make a random password and show it one time}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Set a new password for a user, without email';

    public function handle(): int
    {
        $this->prepareAgentMode();

        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            return $this->failWith(AgentExit::INVALID, 'user_not_found', "No user has the email {$this->argument('email')}.", 'Run php artisan fp:user:list to see the users.');
        }

        if ($this->option('password') !== null && $this->option('generate')) {
            return $this->failWith(AgentExit::INVALID, 'conflicting_options', 'Use --password or --generate, not the two together.');
        }

        $password = $this->newPassword();

        if (is_int($password)) {
            return $password;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            return $this->failMany(AgentExit::INVALID, array_map(
                fn (string $message) => AgentExit::error('invalid_password', $message),
                $validator->errors()->all(),
            ));
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        $sessions = [
            'Rotated the remember token. All "Remember me" logins ended.',
            $this->endWebSessions($user),
        ];

        if (! $this->wantsJson()) {
            $this->components->info("The password of {$user->email} changed.");

            if ($this->option('generate')) {
                $this->line("  New password: {$password}");
                $this->components->warn('Keep this password now. The command does not show it again.');
            }

            $this->components->bulletList($sessions);
        }

        return $this->respond(AgentExit::SUCCESS, [
            'email' => $user->email,
            'password' => $this->option('generate') ? $password : null,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Returns the exit code when no password is available. The error is
     * already reported.
     */
    private function newPassword(): string|int
    {
        if ($this->option('password') !== null) {
            return $this->option('password');
        }

        if ($this->option('generate')) {
            // No symbols, so that the password is easy to copy from a terminal.
            return Str::password(32, symbols: false);
        }

        if (! $this->input->isInteractive()) {
            return $this->failWith(AgentExit::INVALID, 'missing_option', 'Give --password or --generate when the command cannot prompt.');
        }

        $password = password('New password', required: true);

        if ($password !== password('Type the new password again', required: true)) {
            return $this->failWith(AgentExit::INVALID, 'password_mismatch', 'The two passwords are not the same.');
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
