<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * 一般ユーザー2名と管理者1名を作成する。
     */
    public function run(): void
    {
        collect([
            ['name' => '山田 太郎', 'email' => 'user1@example.com', 'admin_status' => false],
            ['name' => '佐藤 花子', 'email' => 'user2@example.com', 'admin_status' => false],
            ['name' => '鈴木 一郎', 'email' => 'user3@example.com', 'admin_status' => true],
        ])->each(function (array $user): void {
            User::create([
                ...$user,
                'password' => Hash::make('password'),
            ])->forceFill(['email_verified_at' => now()])->save();
        });
    }
}
