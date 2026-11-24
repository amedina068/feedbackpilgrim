<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Lists the users of the instance, for an operator who recovers access.
 */
class UserListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fp:user:list
        {--search= : Show only the users whose name or email contains this text}';

    /**
     * @var string
     */
    protected $description = 'List the users of this instance';

    public function handle(): int
    {
        $search = trim((string) $this->option('search'));

        $users = User::query()
            ->with(['clients' => fn ($query) => $query->select('clients.id', 'clients.name')->orderBy('clients.name')])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';

                $query->where(fn ($query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->components->info('No user matches.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Projects', 'Owner', 'Status', 'Created'],
            $users->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->clients->pluck('name')->join(', '),
                $user->isOwner() ? 'yes' : 'no',
                $user->isDeactivated() ? 'deactivated' : 'active',
                $user->created_at?->toDateString(),
            ]),
        );

        return self::SUCCESS;
    }
}
