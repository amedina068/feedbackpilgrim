<?php

namespace App\Actions\Instance;

use App\Actions\Clients\CreateClientForOwner;
use App\Models\User;
use App\Support\InstanceSetup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Creates the owner of a self-hosted instance, the first project of the owner
 * and the admin contact of the owner on that project.
 *
 * The `fp:install` command and the web setup page both use this action. The
 * password obeys the same rules as the registration form.
 */
class CreateInstanceOwner
{
    public function __construct(private CreateClientForOwner $createClient) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Password::defaults()],
            'client_name' => ['required', 'string', 'max:255'],
            'subdomain' => CreateClientForOwner::subdomainRules(),
        ];
    }

    /**
     * @param  array{name: string, email: string, password: string, client_name: string, subdomain: string}  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        return DB::transaction(fn () => $this->createOwner($input));
    }

    /**
     * Create the owner only when the instance has no user yet. Two requests
     * can pass the first check at the same time. The lock and the second
     * check inside the transaction make sure that only one owner is created.
     * Returns null when a user already exists.
     *
     * @param  array{name: string, email: string, password: string, client_name: string, subdomain: string}  $input
     *
     * @throws ValidationException
     */
    public function createFirstOwner(array $input): ?User
    {
        return Cache::lock('feedbackpilgrim:instance-setup', 30)->block(10, function () use ($input) {
            return DB::transaction(function () use ($input) {
                if (! InstanceSetup::isNeeded()) {
                    return null;
                }

                return $this->createOwner($input);
            });
        });
    }

    /**
     * @param  array{name: string, email: string, password: string, client_name: string, subdomain: string}  $input
     */
    private function createOwner(array $input): User
    {
        $input = Validator::make($input, self::rules())->validate();

        $owner = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'onboarding' => User::ONBOARDING_CLIENT,
        ]);
        $owner->forceFill(['is_owner' => true, 'email_verified_at' => now()])->save();

        ($this->createClient)($owner, $input['client_name'], $input['subdomain']);

        return $owner->refresh();
    }
}
