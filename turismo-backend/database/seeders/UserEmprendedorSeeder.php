<?php

namespace Database\Seeders;

use App\Models\Emprendedor;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserEmprendedorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener el usuario emprendedor creado en DatabaseSeeder
        $emprendedorUser = User::where('email', 'emprendedor@example.com')->first();
        
        if (!$emprendedorUser) {
            $this->command->info('Usuario emprendedor no encontrado. Creando uno nuevo...');
            
            $emprendedorUser = User::create([
                'name' => 'Emprendedor Local',
                'first_name' => 'Juan',
                'last_name' => 'Mamani',
                'email' => 'emprendedor@example.com',
                'password' => bcrypt('password'),
                'phone' => '555444333',
                'active' => true
            ]);
            
            $emprendedorUser->assignRole('emprendedor');
        }
        
        // Obtener los emprendimientos
        $emprendimientos = Emprendedor::all();
        
        // Asignar el primer emprendimiento al usuario emprendedor como principal
        if ($emprendimientos->isNotEmpty()) {
            $emprendedorUser->emprendimientos()->attach($emprendimientos->first()->id, [
                'es_principal' => true,
                'rol' => 'administrador'
            ]);
            
            $this->command->info('Se asignó el emprendimiento "' . $emprendimientos->first()->nombre . '" al usuario emprendedor.');
        }
        
        // Asignar el segundo emprendimiento (si existe) al usuario admin como colaborador
        if ($emprendimientos->count() > 1) {
            $adminUser = User::where('email', 'admin@example.com')->first();
            
            if ($adminUser) {
                $adminUser->emprendimientos()->attach($emprendimientos->skip(1)->first()->id, [
                    'es_principal' => true,
                    'rol' => 'administrador'
                ]);
                
                $this->command->info('Se asignó el emprendimiento "' . $emprendimientos->skip(1)->first()->nombre . '" al usuario admin.');
            }
        }
        
        // Asociar múltiples administradores a un emprendimiento si hay más de 2
        if ($emprendimientos->count() > 2) {
            // Usuario normal como colaborador del tercer emprendimiento
            $normalUser = User::where('email', 'user@example.com')->first();
            
            if ($normalUser) {
                $normalUser->emprendimientos()->attach($emprendimientos->skip(2)->first()->id, [
                    'es_principal' => false,
                    'rol' => 'colaborador'
                ]);
                
                // También agregar al emprendedor como colaborador del mismo emprendimiento
                $emprendedorUser->emprendimientos()->attach($emprendimientos->skip(2)->first()->id, [
                    'es_principal' => true,
                    'rol' => 'administrador'
                ]);
                
                $this->command->info('Se configuraron múltiples administradores para el emprendimiento "' . $emprendimientos->skip(2)->first()->nombre . '".');
            }
        }
    }
}