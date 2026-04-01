<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Temple;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TempleService
{
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $data['temple_code'] = Temple::generateTempleCode();

            $temple = Temple::create($data);

            $role = $this->createSuperAdminRole($temple);

            $password = $this->generatePassword();

            $user = $this->createSuperAdminUser($temple, $role, $password);

            return [
                'temple' => $temple,
                'user' => $user,
                'password' => $password,
            ];
        });
    }

    private function createSuperAdminRole(Temple $temple): Role
    {
        $role = Role::create([
            'temple_id' => $temple->id,
            'role_name' => 'Super Admin',
            'description' => 'Temple super administrator with full access',
            'is_system_role' => true,
        ]);

        // Super Admin gets ALL permissions EXCEPT temples module
        // Temples are managed exclusively by Platform Admin
        $allPermissionsExceptTemples = Permission::where('module_key', '!=', 'temples')
            ->pluck('id')
            ->toArray();
        $role->syncPermissions($allPermissionsExceptTemples);

        return $role;
    }

    private function createSuperAdminUser(Temple $temple, Role $role, string $password): User
    {
        return User::create([
            'temple_id' => $temple->id,
            'user_type' => 'temple_user',
            'name' => $temple->contact_person_name,
            'contact_number' => $temple->contact_number,
            'email' => $temple->email,
            'role_id' => $role->id,
            'password' => Hash::make($password),
            'must_reset_password' => true,
            'is_active' => true,
        ]);
    }

    private function generatePassword(): string
    {
        return Str::random(8) . rand(10, 99);
    }

    public function update(Temple $temple, array $data): Temple
    {
        $temple->update($data);
        return $temple->fresh();
    }

    public function delete(Temple $temple): bool
    {
        return $temple->delete();
    }
}
