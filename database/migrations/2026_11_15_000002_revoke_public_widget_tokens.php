<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Onboarding used to make a Sanctum token with all abilities and store its
 * plain text in a "token" client setting. Host apps put that value in a
 * public script tag, so anyone could read it and call the Public API and
 * /mcp. This migration:
 *
 * 1. Gives each client a publishable key and an identity secret.
 * 2. Deletes the Sanctum token and the plain-text setting.
 *
 * Existing clients keep identity verification off. The owner turns it on
 * after the host app sends user_hash.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $this->giveClientsWidgetKeys();
            $this->revokePublicTokens();
        });
    }

    /**
     * The revoked tokens cannot come back. Their plain text is gone.
     */
    public function down(): void
    {
        //
    }

    private function giveClientsWidgetKeys(): void
    {
        DB::table('clients')
            ->whereNull('publishable_key')
            ->orWhereNull('identity_secret')
            ->orderBy('id')
            ->get(['id', 'publishable_key', 'identity_secret'])
            ->each(function (object $client) {
                DB::table('clients')->where('id', $client->id)->update([
                    'publishable_key' => $client->publishable_key ?? 'pk_'.Str::random(32),
                    'identity_secret' => $client->identity_secret ?? Crypt::encryptString(bin2hex(random_bytes(32))),
                ]);
            });
    }

    private function revokePublicTokens(): void
    {
        DB::table('client_settings')
            ->where('name', 'token')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'client_id', 'value'])
            ->each(function (object $setting) {
                if (is_string($setting->value) && $setting->value !== '') {
                    PersonalAccessToken::findToken($setting->value)?->delete();
                }

                DB::table('client_settings')->where('id', $setting->id)->delete();
            });
    }
};
