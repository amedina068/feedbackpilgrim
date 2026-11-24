<?php

namespace App\Console\Commands;

use App\Actions\Instance\CreateInstanceOwner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Prepares a new self-hosted instance and creates its owner.
 *
 * The command creates the storage link when it is missing. On an empty
 * database it creates the owner, the first project and the admin contact of
 * the owner. It creates no second owner.
 */
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fp:install
        {--name= : The name of the owner}
        {--email= : The email of the owner}
        {--password= : The password of the owner (8 characters or more)}
        {--client-name= : The name of the first project}
        {--subdomain= : The subdomain of the first project}
        {--promote= : Make the existing user with this email an owner, and create no account}
        {--prepare : Create only the storage link, and create no account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the owner, the first project and its admin contact on a new instance';

    /**
     * Execute the console command.
     */
    public function handle(CreateInstanceOwner $owners): int
    {
        $this->prepareStorageLink();

        if ($this->option('prepare')) {
            return self::SUCCESS;
        }

        if ($email = $this->option('promote')) {
            return $this->promote($email);
        }

        if (User::query()->exists()) {
            $this->components->error('This instance already has users. fp:install only runs on an empty database.');

            return self::FAILURE;
        }

        $input = [
            'name' => $this->option('name') ?? $this->promptText('Owner name'),
            'email' => $this->option('email') ?? $this->promptText('Owner email'),
            'password' => $this->option('password') ?? $this->promptSecret('Owner password'),
            'client_name' => $this->option('client-name') ?? $this->promptText('Project name'),
            'subdomain' => $this->option('subdomain') ?? $this->promptText('Project subdomain'),
        ];

        try {
            $owner = $owners->create($input);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $client = $owner->clients()->firstOrFail();

        $this->components->info('The owner, the project and the admin contact are ready.');
        $this->components->twoColumnDetail('Dashboard', url('/login'));
        $this->components->twoColumnDetail('Public site', $client->public_site);

        return self::SUCCESS;
    }

    private function promote(string $email): int
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->components->error("No user has the email {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['is_owner' => true])->save();
        $this->components->info("{$user->email} is now an owner of this instance.");

        return self::SUCCESS;
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

    private function promptText(string $label): ?string
    {
        if (! $this->input->isInteractive()) {
            return null;
        }

        return text(label: $label, required: true);
    }

    private function promptSecret(string $label): ?string
    {
        if (! $this->input->isInteractive()) {
            return null;
        }

        return password(label: $label, required: true, hint: '8 characters or more.');
    }
}
