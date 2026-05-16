<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            "name" => "administrator",
        ]);

        $user = User::firstOrCreate(
            [
                "email" => "admin@example.com",
            ],
            [
                "name" => "Administrator",
                "password" => Hash::make(
                    env("DEFAULT_ADMIN_PASSWORD", "change-me"),
                ),
            ],
        );

        $user->assignRole($role);
    }

    public function down(): void
    {
        $user = User::where("email", "admin@example.com")->first();

        if ($user) {
            $user->removeRole("administrator");
            $user->delete();
        }

        Role::where("name", "administrator")->delete();
    }
};
