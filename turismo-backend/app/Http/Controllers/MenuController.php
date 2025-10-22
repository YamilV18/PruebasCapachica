<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    /**
     * Obtiene el menú dinámico basado en los permisos del usuario autenticado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenu(Request $request)
    {
        // Obtener el usuario actual
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => []
            ], 401);
        }
        
        // Obtener todos los permisos del usuario (incluyendo los heredados a través de roles)
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        
        // Verificar si el usuario administra emprendimientos
        $administraEmprendimientos = $user->administraEmprendimientos();
        
        // Definir la estructura del menú completo
        $fullMenu = $this->getFullMenuStructure($administraEmprendimientos);
        
        // Filtrar el menú según los permisos del usuario
        $filteredMenu = $this->filterMenuByPermissions($fullMenu, $permissions);
        
        return response()->json([
            'success' => true,
            'data' => $filteredMenu
        ]);
    }
    
    /**
     * Define la estructura completa del menú
     *
     * @param bool $incluyeMenuEmprendedor Si es true, incluye opciones específicas para emprendedores
     * @return array
     */
    private function getFullMenuStructure($incluyeMenuEmprendedor = false)
    {
        $menu = [
            [
                'id' => 'dashboard',
                'title' => 'Dashboard',
                'icon' => 'dashboard',
                'path' => '/dashboard',
                'permissions' => ['user_read'], // Permisos mínimos para ver el dashboard
            ],
            [
                'id' => 'users',
                'title' => 'Usuarios',
                'icon' => 'users',
                'path' => '/admin/users',
                'permissions' => ['user_create'],
                'children' => [
                    [
                        'id' => 'user-list',
                        'title' => 'Gestión de Usuarios',
                        'path' => '/admin/users',
                        'permissions' => ['user_read'],
                    ],
                    [
                        'id' => 'roles',
                        'title' => 'Roles',
                        'path' => '/admin/roles',
                        'permissions' => ['role_read'],
                    ],
                    [
                        'id' => 'permissions',
                        'title' => 'Permisos',
                        'path' => '/admin/permissions',
                        'permissions' => ['permission_read'],
                    ],
                ]
            ],
            [
                'id' => 'municipalidad',
                'title' => 'Municipalidad',
                'icon' => 'building',
                'path' => '/admin/municipalidad',
                'permissions' => ['municipalidad_update'],
            ],
            [
                'id' => 'evento',
                'title' => 'Evento',
                'icon' => 'events',
                'path' => '/admin/evento',
                'permissions' => ['user_create'],
            ],
            [
                'id' => 'emprendedores',
                'title' => 'Emprendedores',
                'icon' => 'store',
                'path' => '/admin/emprendedores',
                'permissions' => ['emprendedor_create'],
                'children' => [
                    [
                        'id' => 'emprendedor-list',
                        'title' => 'Gestión de Emprendedores',
                        'path' => '/admin/emprendedores',
                        'permissions' => ['emprendedor_read'],
                    ],
                    [
                        'id' => 'asociacion-list',
                        'title' => 'Gestión de Asociaciones',
                        'path' => '/admin/asociaciones',
                        'permissions' => ['asociacion_read'],
                    ],
                ]
            ],
            [
                'id' => 'servicios',
                'title' => 'Servicios',
                'icon' => 'briefcase',
                'path' => '/admin/servicios',
                'permissions' => ['servicio_create'],
                'children' => [
                    [
                        'id' => 'servicio-list',
                        'title' => 'Gestión de Servicios',
                        'path' => '/admin/servicios',
                        'permissions' => ['servicio_read'],
                    ],
                    [
                        'id' => 'categorias',
                        'title' => 'Categorías',
                        'path' => '/admin/categorias',
                        'permissions' => ['categoria_read'],
                    ],
                ]
            ],
            [
                'id' => 'reservas',
                'title' => 'Reservas',
                'icon' => 'calendar',
                'path' => '/admin/reservas',
                'permissions' => ['user_read'], // Asumiendo que cualquier usuario puede ver reservas
                'children' => [
                    [
                        'id' => 'reserva-list',
                        'title' => 'Gestion de Reservas',
                        'path' => '/admin/reservas',
                        'permissions' => ['reserva_update'],
                    ],
                    [
                        'id' => 'mis-reservas',
                        'title' => 'Mis Reservas',
                        'path' => '/admin/reservas/mis-reservas',
                        'permissions' => ['user_read'],
                    ],
                    [
                        'id' => 'mis-inscripciones',
                        'title' => 'Mis Inscripciones',
                        'path' => '/admin/reservas/mis-inscripciones',
                        'permissions' => ['user_read'],
                    ], 
                ]
            ],
            [
                'id' => 'planes',
                'title' => 'Gestionar planes',
                'icon' => 'chart',
                'path' => '/admin/planes',
                'permissions' => ['plan_update'], 
            ],
            [
                'id' => 'profile',
                'title' => 'Mi Perfil',
                'icon' => 'user',
                'path' => '/admin/profile',
                'permissions' => ['user_read'], // Todos los usuarios pueden ver su perfil
            ],
            
        ];
        
    
        
        return $menu;
    }
    
    /**
     * Filtra el menú según los permisos del usuario
     *
     * @param array $menu
     * @param array $userPermissions
     * @return array
     */
    private function filterMenuByPermissions($menu, $userPermissions)
    {
        $filteredMenu = [];
        
        foreach ($menu as $item) {
            // Verificar si el usuario tiene al menos uno de los permisos requeridos para este elemento
            $hasPermission = count(array_intersect($item['permissions'], $userPermissions)) > 0;
            
            // Si tiene permiso, procesar este ítem del menú
            if ($hasPermission) {
                $menuItem = [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'icon' => $item['icon'],
                    'path' => $item['path'],
                ];
                
                // Si tiene hijos, filtrarlos también
                if (isset($item['children']) && !empty($item['children'])) {
                    $filteredChildren = [];
                    
                    foreach ($item['children'] as $child) {
                        $hasChildPermission = count(array_intersect($child['permissions'], $userPermissions)) > 0;
                        
                        if ($hasChildPermission) {
                            $filteredChildren[] = [
                                'id' => $child['id'],
                                'title' => $child['title'],
                                'path' => $child['path'],
                            ];
                        }
                    }
                    
                    // Solo añadir children si hay elementos
                    if (!empty($filteredChildren)) {
                        $menuItem['children'] = $filteredChildren;
                    }
                }
                
                $filteredMenu[] = $menuItem;
            }
        }
        
        return $filteredMenu;
    }
}