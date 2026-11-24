<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contact has one vote on each post, release note or testimonial. The
 * public vote route did not check for a duplicate. This migration removes
 * the duplicate votes, keeps the oldest vote (the lowest id), and then adds
 * a unique index.
 */
return new class extends Migration
{
    private const INDEX = 'votes_votable_contact_unique';

    public function up(): void
    {
        $groups = DB::table('votes')
            ->select('votable_type', 'votable_id', 'contact_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('votable_type', 'votable_id', 'contact_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            DB::table('votes')
                ->where('votable_type', $group->votable_type)
                ->where('votable_id', $group->votable_id)
                ->where('contact_id', $group->contact_id)
                ->where('id', '!=', $group->keep_id)
                ->delete();
        }

        Schema::table('votes', function (Blueprint $table) {
            $table->unique(['votable_type', 'votable_id', 'contact_id'], self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }
};
