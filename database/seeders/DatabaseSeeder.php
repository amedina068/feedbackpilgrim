<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ClientSeeder::class,
            StageSeeder::class,
            UserSeeder::class,
            ContactSeeder::class,
            // MessageSeeder::class,
            ClientSettingSeeder::class,
            PostSeeder::class,
            VoteSeeder::class,
            TagSeeder::class,
            CommentSeeder::class,
        ]);
    }
}
