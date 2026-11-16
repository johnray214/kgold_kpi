<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('member')->after('password'); // admin | member
            $t->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('page_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'page_id']);
        });

        // Seed initial Admin account if no users exist yet
        if (! User::query()->exists()) {
            User::create([
                'name' => 'John Ray (Admin)',
                'email' => 'admin@kgold.com',
                'password' => Hash::make('Kgold2026!'),
                'role' => 'admin',
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_user');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['role', 'is_active']);
        });
    }
};
