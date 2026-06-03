<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Utils\EmpresaUtil;

class DefenderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(EmpresaUtil::class)->ensureDefaultPermissionsInDatabase();
        $this->command->info('Default permissions ensured in database.');
        $this->createRoles();
    }

    private function createRoles()
    {
        $superadmin = Role::firstOrCreate([
            'name' => 'gestor_plataforma'
        ], [
            'description' => 'Gestor Plataforma',
            'type_user' => 1
        ]);
        $superadmin->permissions()->sync(Permission::all());

        $this->command->info('Superadmin will have full rights');

        $admin = Role::firstOrCreate([
            'name' => 'admin',
        ], [
            'description' => 'Admin',
            'type_user' => 2
        ]);
        $admin->permissions()->sync(Permission::all());

    }
}
