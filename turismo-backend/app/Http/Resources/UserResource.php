<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use \Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $userData = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => (bool) $this->active,
            'foto_perfil' => $this->getFotoPerfilUrl(),
            'country' => $this->country,
            'birth_date' => $this->birth_date ? $this->birth_date->format('Y-m-d') : null,
            'address' => $this->address,
            'gender' => $this->gender,
            'preferred_language' => $this->preferred_language,
            'last_login' => $this->last_login ? $this->last_login->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
        
        // Incluir información de roles si la relación está cargada
        if ($this->relationLoaded('roles')) {
            $userData['roles'] = $this->getRoleNames();
            $userData['is_admin'] = $this->hasRole('admin');
            
            // Incluir información más detallada sobre los roles
            $userData['roles_info'] = $this->roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => ucfirst($role->name),
                    'permissions_count' => $role->relationLoaded('permissions') ? $role->permissions->count() : null,
                ];
            });
        }
        
        // Incluir información de permisos si la relación está cargada
        if ($this->relationLoaded('permissions')) {
            $userData['permissions'] = $this->getAllPermissions()->pluck('name');
            $userData['has_permissions'] = $this->getAllPermissions()->isNotEmpty();
        }
        
        // Incluir información de emprendimientos si la relación está cargada
        if ($this->relationLoaded('emprendimientos')) {
            $userData['administra_emprendimientos'] = $this->administraEmprendimientos();
            $userData['emprendimientos_count'] = $this->emprendimientos->count();
            
            $userData['emprendimientos'] = $this->emprendimientos->map(function($emprendimiento) {
                return [
                    'id' => $emprendimiento->id,
                    'nombre' => $emprendimiento->nombre,
                    'es_principal' => $emprendimiento->pivot->es_principal,
                    'rol' => $emprendimiento->pivot->rol,
                ];
            });
        }
        
        return $userData;
    }
    
    /**
     * Get the profile photo URL
     * 
     * @return string|null
     */
    protected function getFotoPerfilUrl(): ?string
    {
        if (!$this->foto_perfil) {
            return null;
        }
        
        // Check if it's already a URL
        if (filter_var($this->foto_perfil, FILTER_VALIDATE_URL)) {
            return $this->foto_perfil;
        }
        
        // Generate URL for stored images
        return Storage::disk('public')->url($this->foto_perfil);
    }
}