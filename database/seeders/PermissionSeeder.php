<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Modules that use role-based permissions
        // NOTE: Dashboard, Calendar, Reports don't need permissions (available to all temple users)
        // NOTE: Temples is platform admin only
        $modules = [
            'users' => 'User Management',
            'roles' => 'Role Management',
            'deities' => 'Deity Management',
            'poojas' => 'Pooja Management',
            'bookings' => 'Booking Management',
            'daily_poojas' => 'Daily Pooja Operations',
            'purchases' => 'Purchase Management',
            'expenses' => 'Expense Management',
            'donations' => 'Donation Management',
            'employees' => 'Employee Management',
            'accounts' => 'Accounts Management',
            'ledger' => 'Ledger Management',
        ];

        $actions = ['create', 'read', 'update', 'delete'];

        foreach ($modules as $key => $name) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    [
                        'module_key' => $key,
                        'action' => $action,
                    ],
                    [
                        'module_name' => $name,
                    ]
                );
            }
        }

        // IMPORTANT: Add all permissions to Super Admin (system) roles
        $this->assignPermissionsToSystemRoles();

        $this->command->info('Permissions seeded successfully!');
    }

    /**
     * Assign ALL permissions to system roles (Super Admin).
     * Super Admin must have all permissions to all modules.
     */
    private function assignPermissionsToSystemRoles(): void
    {
        $allPermissionIds = Permission::pluck('id')->toArray();
        $systemRoles = Role::where('is_system_role', true)->get();

        foreach ($systemRoles as $role) {
            $role->permissions()->syncWithoutDetaching($allPermissionIds);
        }

        if ($systemRoles->count() > 0) {
            $this->command->info("Assigned all permissions to {$systemRoles->count()} system role(s).");
        }
    }
}
