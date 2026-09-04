<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $primaryEmail = env('ADMIN_SEED_EMAIL', 'admin@growbridge.com');
        $primaryPassword = env('ADMIN_SEED_PASSWORD', '12345678');
        $primaryName = env('ADMIN_SEED_NAME', 'Super Admin');

        $superAdminRole = Role::where('key', Role::KEY_SUPER_ADMIN)->first();

        $adminEmails = array_unique(array_filter([
            strtolower(trim((string) $primaryEmail)),
            'admin@growbridge.com',
            'admin@spagreen.net',
        ]));

        foreach ($adminEmails as $email) {
            $admin = AdminUser::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $primaryName,
                    'password' => Hash::make($primaryPassword),
                    'status' => AdminUser::STATUS_ACTIVE,
                ]
            );

            // Ensure valid active status
            if ($admin->status !== AdminUser::STATUS_ACTIVE) {
                $admin->update(['status' => AdminUser::STATUS_ACTIVE]);
            }

            if ($superAdminRole && ! $admin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
                $admin->roles()->attach($superAdminRole->id);
            }
        }
    }
}
