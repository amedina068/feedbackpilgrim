<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Billing is removed. Before, a user that created a client stopped at the
 * "pricing" onboarding step, and checkout created the admin contact. This
 * migration moves these users to "completed" and creates the missing admin
 * contact for each client of each user.
 *
 * The migration does not drop the old billing columns or tables. An instance
 * that ran the Cashier migrations keeps them, and the code does not read them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $users = DB::table('users')->where('onboarding', 'pricing')->get(['id', 'name', 'email']);

            foreach ($users as $user) {
                $clientIds = DB::table('client_user')->where('user_id', $user->id)->pluck('client_id');

                foreach ($clientIds as $clientId) {
                    $this->createAdminContact($user, $clientId);
                }

                DB::table('users')->where('id', $user->id)->update([
                    'onboarding' => 'completed',
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * The pricing step does not exist anymore, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }

    private function createAdminContact(object $user, int $clientId): void
    {
        $exists = DB::table('contacts')
            ->where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('contacts')->insert([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'client_id' => $clientId,
            'name' => $user->name,
            'email' => $user->email,
            'metadata' => json_encode(['role' => 'admin']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
