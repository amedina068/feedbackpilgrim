<?php

namespace App\Console\Commands;

use App\Actions\Instance\CreateInstanceOwner;
use App\Console\Concerns\AgentExit;
use App\Console\Concerns\InteractsWithAgents;
use App\Models\Client;
use App\Models\User;
use App\Support\ApiTokens;
use App\Support\LoginLink;
use App\Support\McpConnect;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

use function Laravel\Prompts\text;

/**
 * Prepares a new self-hosted instance and creates its owner.
 *
 * The command creates the storage link when it is missing. On an empty
 * database it creates the owner, the first project and the admin contact of
 * the owner. It needs no password: it returns a one-time login link, and the
 * owner sets a password after the first login.
 *
 * The command is idempotent. When the owner exists, it changes nothing,
 * reports `exists` and exits 0. When another user exists, it reports a
 * conflict and exits 3.
 */
class InstallCommand extends Command
{
    use InteractsWithAgents;

    public const DEFAULT_PROJECT = 'My product';

    /**
     * @var string
     */
    protected $signature = 'fp:install
        {--name= : The name of the owner}
        {--email= : The email of the owner}
        {--project= : The name of the first project (default: "My product")}
        {--subdomain= : The subdomain of the first project (default: made from the project name)}
        {--with-token= : Also create an API token with this name, and print the MCP connect commands}
        {--client-name= : Deprecated. Use --project}
        {--password= : Deprecated. The command returns a one-time login link instead}
        {--promote= : Make the existing user with this email an owner, and create no account}
        {--prepare : Create only the storage link, and create no account}
        {--json : Print one JSON object on stdout}';

    /**
     * @var string
     */
    protected $description = 'Create the owner, the first project and its admin contact on a new instance';

    public function handle(CreateInstanceOwner $owners): int
    {
        $this->prepareAgentMode();
        $this->prepareStorageLink();

        if ($this->option('prepare')) {
            return $this->respond(AgentExit::SUCCESS, ['status' => 'prepared']);
        }

        if ($email = $this->option('promote')) {
            return $this->promote($email);
        }

        if (User::query()->exists()) {
            return $this->existing();
        }

        $input = $this->collectInput();

        if (is_int($input)) {
            return $input;
        }

        try {
            $owner = $owners->createFirstOwner($input);
        } catch (ValidationException $exception) {
            return $this->failMany(AgentExit::INVALID, collect($exception->validator->errors()->messages())
                ->flatMap(fn (array $messages, string $field) => array_map(
                    fn (string $message) => AgentExit::error('invalid_'.$field, $message, 'Correct --'.self::optionFor($field).' and run the command again.'),
                    $messages,
                ))
                ->values()
                ->all());
        }

        if ($owner === null) {
            return $this->existing();
        }

        return $this->created($owner, $input['password_set']);
    }

    /**
     * @return array{name: string, email: string, password: string, password_set: bool, client_name: string, subdomain: string}|int
     */
    private function collectInput(): array|int
    {
        $name = $this->option('name') ?? $this->promptText('Owner name');
        $email = $this->option('email') ?? $this->promptText('Owner email');

        $missing = array_keys(array_filter(['name' => $name, 'email' => $email], fn ($value) => blank($value)));

        if ($missing !== []) {
            return $this->failWith(
                AgentExit::INVALID,
                'missing_option',
                'Give '.implode(' and ', array_map(fn ($option) => "--{$option}", $missing)).'.',
                'Run php artisan fp:install --name="Your Name" --email=you@example.com --json',
            );
        }

        $project = $this->option('project') ?? $this->option('client-name') ?? $this->promptText('Project name', self::DEFAULT_PROJECT) ?? self::DEFAULT_PROJECT;
        $subdomain = $this->option('subdomain') ?? $this->promptText('Project subdomain', self::subdomainFor($project)) ?? self::subdomainFor($project);

        $password = $this->option('password');

        if ($password !== null) {
            $this->note('The --password option is deprecated. Use the one-time login link instead.');
        }

        return [
            'name' => (string) $name,
            'email' => (string) $email,
            'password' => $password ?? Str::password(40),
            'password_set' => $password !== null,
            'client_name' => (string) $project,
            'subdomain' => (string) $subdomain,
        ];
    }

    private function created(User $owner, bool $passwordSet): int
    {
        $client = self::firstProjectOf($owner) ?? throw new RuntimeException('The new owner has no project.');
        $expiresAt = now()->addMinutes(LoginLink::INSTALL_MINUTES);
        $loginUrl = LoginLink::make($owner, $expiresAt);

        $token = null;
        $connect = null;

        if (filled($tokenName = $this->option('with-token'))) {
            $newToken = ApiTokens::createProductToken($owner, $client, Str::limit((string) $tokenName, 100, ''));
            $token = array_merge(ApiTokens::describe($newToken->accessToken), ['plain_text' => $newToken->plainTextToken]);
            $connect = McpConnect::lines($newToken->plainTextToken);
        }

        $data = array_merge(['status' => 'created'], $this->summary($owner, $client), [
            'login_url' => $loginUrl,
            'login_url_expires_at' => $expiresAt->toIso8601String(),
            'password_set' => $passwordSet,
            'token' => $token,
            'connect' => $connect,
            'next' => [],
        ]);

        if (! $this->wantsJson()) {
            $this->components->info('The owner, the project and the admin contact are ready.');
            $this->printSummary($data);
        }

        return $this->respond(AgentExit::SUCCESS, $data);
    }

    /**
     * The instance already has users. With the email of an owner, or with no
     * email, this is a second run: report the owner and change nothing.
     */
    private function existing(): int
    {
        $email = $this->option('email');
        $owners = User::query()->where('is_owner', true)->orderBy('id');
        $owner = $email ? (clone $owners)->where('email', $email)->first() : $owners->first();

        if (! $owner) {
            return $this->failWith(
                AgentExit::CONFLICT,
                'instance_has_owner',
                $email
                    ? "This instance already has users, and {$email} is not an owner. fp:install creates no second owner."
                    : 'This instance already has users, but no owner.',
                'To make an existing user an owner, run php artisan fp:install --promote=<email>. To list the users, run php artisan fp:user:list.',
            );
        }

        $client = self::firstProjectOf($owner);
        $expiresAt = now()->addMinutes(LoginLink::INSTALL_MINUTES);

        $data = array_merge(['status' => 'exists'], $this->summary($owner, $client), [
            'login_url' => LoginLink::make($owner, $expiresAt),
            'login_url_expires_at' => $expiresAt->toIso8601String(),
            'password_set' => null,
            'token' => null,
            'connect' => null,
            'next' => [
                "php artisan fp:token:create --user={$owner->email} --name=agent --json",
                'php artisan fp:status --json',
            ],
        ]);

        if (! $this->wantsJson()) {
            $this->components->info('This instance is already installed. Nothing changed.');
            $this->printSummary($data);
            $this->line('  To make an API token, run: '.$data['next'][0]);
        }

        return $this->respond(AgentExit::SUCCESS, $data);
    }

    /**
     * @return array{owner: array{id: int, name: string, email: string}, project: array{id: int, name: string, subdomain: string, public_url: string}|null, app_url: string, dashboard_url: string}
     */
    private function summary(User $owner, ?Client $client): array
    {
        return [
            'owner' => ['id' => $owner->id, 'name' => $owner->name, 'email' => $owner->email],
            'project' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'subdomain' => $client->slug,
                'public_url' => $client->public_site,
            ] : null,
            'app_url' => (string) config('app.url'),
            'dashboard_url' => url('/login'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function printSummary(array $data): void
    {
        $this->components->twoColumnDetail('Dashboard', $data['dashboard_url']);

        if ($data['project']) {
            $this->components->twoColumnDetail('Public site', $data['project']['public_url']);
        }

        $this->components->twoColumnDetail('One-time login link', $data['login_url']);
        $this->line('  The link works one time, for '.LoginLink::INSTALL_MINUTES.' minutes. After the login, set a password on the profile page.');

        if ($data['token']) {
            $this->newLine();
            $this->components->twoColumnDetail('API token', $data['token']['plain_text']);
            $this->components->warn('Keep this token now. The command does not show it again.');
            $this->line('  '.$data['connect']['env']);
            $this->line('  '.$data['connect']['claude']);
            $this->line('  '.$data['connect']['codex']);
        }
    }

    private function promote(string $email): int
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return $this->failWith(AgentExit::INVALID, 'user_not_found', "No user has the email {$email}.", 'Run php artisan fp:user:list to see the users.');
        }

        $user->forceFill(['is_owner' => true])->save();

        if (! $this->wantsJson()) {
            $this->components->info("{$user->email} is now an owner of this instance.");
        }

        return $this->respond(AgentExit::SUCCESS, ['status' => 'promoted', 'email' => $user->email]);
    }

    /**
     * The media library stores the uploads on the public disk. The web server
     * serves them through this link.
     */
    private function prepareStorageLink(): void
    {
        if (! file_exists(public_path('storage'))) {
            $this->callSilently('storage:link');
        }
    }

    private function promptText(string $label, string $default = ''): ?string
    {
        if (! $this->input->isInteractive()) {
            return null;
        }

        return text(label: $label, default: $default, required: true);
    }

    private static function firstProjectOf(User $user): ?Client
    {
        return Client::query()
            ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
            ->orderBy('id')
            ->first();
    }

    public static function subdomainFor(string $project): string
    {
        $subdomain = Str::slug($project) ?: 'feedback';

        return in_array($subdomain, Client::RESERVED_SLUGS, true) ? $subdomain.'-board' : $subdomain;
    }

    private static function optionFor(string $field): string
    {
        return match ($field) {
            'client_name' => 'project',
            default => $field,
        };
    }
}
