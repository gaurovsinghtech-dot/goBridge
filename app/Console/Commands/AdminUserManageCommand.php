<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminUserManageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:user
                            {action=create : Action to perform (create, reset, list)}
                            {--email= : Admin email address}
                            {--password= : Admin password}
                            {--name=Super Admin : Admin full name}
                            {--status=ACTIVE : Admin status (ACTIVE/INACTIVE)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Growbridge super-admin accounts (create, reset password, list)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = strtolower($this->argument('action'));

        return match ($action) {
            'list' => $this->listAdmins(),
            'reset' => $this->resetPassword(),
            default => $this->createOrUpdateAdmin(),
        };
    }

    private function listAdmins(): int
    {
        $admins = AdminUser::with('roles')->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin accounts found in the database.');
            return self::SUCCESS;
        }

        $rows = $admins->map(fn (AdminUser $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'email' => $a->email,
            'status' => $a->status,
            'roles' => $a->roles->pluck('name')->join(', ') ?: 'None',
            'created_at' => $a->created_at?->toDateTimeString(),
        ]);

        $this->table(['ID', 'Name', 'Email', 'Status', 'Roles', 'Created At'], $rows);

        return self::SUCCESS;
    }

    private function createOrUpdateAdmin(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Enter admin email', 'admin@growbridge.com'))));
        $name = (string) ($this->option('name') ?: $this->ask('Enter admin name', 'Super Admin'));
        $password = (string) ($this->option('password') ?: $this->secret('Enter admin password (min 8 chars)'));

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return self::FAILURE;
        }

        $admin = AdminUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => AdminUser::STATUS_ACTIVE,
            ]
        );

        $superAdminRole = Role::where('key', Role::KEY_SUPER_ADMIN)->first();
        if ($superAdminRole && ! $admin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id);
        }

        $this->info("Admin account [{$email}] successfully configured as active Super Admin.");

        return self::SUCCESS;
    }

    private function resetPassword(): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Enter existing admin email'))));

        $admin = AdminUser::where('email', $email)->first();
        if (! $admin) {
            $this->error("No admin account found with email: {$email}");
            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Enter new password (min 8 chars)'));
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return self::FAILURE;
        }

        $admin->update([
            'password' => Hash::make($password),
            'status' => AdminUser::STATUS_ACTIVE,
        ]);

        $this->info("Password for [{$email}] has been successfully updated.");

        return self::SUCCESS;
    }
}
