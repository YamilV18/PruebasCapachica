<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanDia;
use App\Models\PlanDiaServicio;
use App\Models\PlanInscripcion;
use App\Models\PlanEmprendedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Laravel\Facades\Image;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PlanService
{
    /**
     * Obtener todos los planes paginados con filtros opcionales
     */
    public function getAll(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Plan::with([
            'creadoPor:id,name,email',
            'emprendedor:id,nombre,ubicacion', // Mantener por compatibilidad
            'emprendedores:id,nombre,ubicacion',
            'organizadorPrincipal:id,nombre,ubicacion',
            'dias.servicios:id,nombre,precio_referencial', // CORREGIDO: precio_referencial
            'inscripciones'
        ]);
        
        // Aplicar filtros
        if (!empty($filtros['emprendedor_id'])) {
            $query->deEmprendedor($filtros['emprendedor_id']);
        }
        
        if (!empty($filtros['organizador_id'])) {
            $query->organizadoPor($filtros['organizador_id']);
        }
        
        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        
        if (!empty($filtros['es_publico'])) {
            $query->where('es_publico', $filtros['es_publico']);
        }
        
        if (!empty($filtros['dificultad'])) {
            $query->where('dificultad', $filtros['dificultad']);
        }
        
        if (!empty($filtros['buscar'])) {
            $query->buscar($filtros['buscar']);
        }
        
        if (!empty($filtros['con_cupos'])) {
            $query->conCuposDisponibles();
        }
        
        if (!empty($filtros['duracion_min'])) {
            $query->where('duracion_dias', '>=', $filtros['duracion_min']);
        }
        
        if (!empty($filtros['duracion_max'])) {
            $query->where('duracion_dias', '<=', $filtros['duracion_max']);
        }
        
        if (!empty($filtros['precio_min'])) {
            $query->where('precio_total', '>=', $filtros['precio_min']);
        }
        
        if (!empty($filtros['precio_max'])) {
            $query->where('precio_total', '<=', $filtros['precio_max']);
        }
        
        // Solo mostrar públicos y activos por defecto si no se especifica lo contrario
        if (!isset($filtros['todos']) || !$filtros['todos']) {
            $query->publicos()->activos();
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
    
    /**
     * Obtener un plan por ID con todas sus relaciones
     */
    public function getById(int $id): ?Plan
    {
        return Plan::with([
            'creadoPor:id,name,email',
            'emprendedor:id,nombre,ubicacion,telefono,email', // Legacy
            'emprendedores:id,nombre,ubicacion,telefono,email',
            'emprendedoresOrganizadores:id,nombre,ubicacion,telefono,email',
            'emprendedoresColaboradores:id,nombre,ubicacion,telefono,email',
            'dias' => function($query) {
                $query->ordenadoPorDia()->with([
                    'servicios' => function($subQuery) {
                        $subQuery->select('servicios.id', 'servicios.nombre', 'servicios.precio_referencial') // CORREGIDO
                                ->with('emprendedor:id,nombre')
                                ->orderBy('pivot_orden');
                    }
                ]);
            },
            'inscripciones.usuario:id,name,email',
        ])->find($id);
    }
    
    /**
     * Crear un nuevo plan con múltiples emprendedores
     */
    public function create(array $data): Plan
    {
        try {
            DB::beginTransaction();
            
            // Procesar imágenes
            $imagenes = $this->procesarImagenes($data);
            $data = array_merge($data, $imagenes);
            
            // Extraer datos de días y emprendedores
            $diasData = $data['dias'] ?? [];
            $emprendedoresData = $data['emprendedores'] ?? [];
            unset($data['dias'], $data['emprendedores']);
            
            // Mantener compatibilidad con emprendedor_id único
            if (!empty($emprendedoresData) && empty($data['emprendedor_id'])) {
                // Si hay múltiples emprendedores, usar el organizador principal como emprendedor_id legacy
                $organizadorPrincipal = collect($emprendedoresData)->firstWhere('es_organizador_principal', true);
                if ($organizadorPrincipal) {
                    $data['emprendedor_id'] = $organizadorPrincipal['emprendedor_id'];
                }
            }
            
            // Crear el plan
            $plan = Plan::create($data);
            
            // Agregar emprendedores al plan
            if (!empty($emprendedoresData)) {
                $this->agregarEmprendedoresAPlan($plan->id, $emprendedoresData);
            } elseif (!empty($data['emprendedor_id'])) {
                // Compatibilidad: si solo se envía emprendedor_id, agregarlo como organizador principal
                $this->agregarEmprendedoresAPlan($plan->id, [[
                    'emprendedor_id' => $data['emprendedor_id'],
                    'rol' => 'organizador',
                    'es_organizador_principal' => true
                ]]);
            }
            
            // Crear los días si existen
            if (!empty($diasData)) {
                $this->crearDiasPlan($plan->id, $diasData);
            }
            
            DB::commit();
            return $plan->fresh(['dias.servicios', 'emprendedores']);
            
        } catch (Exception $e) {
            DB::rollBack();
            // Limpiar imágenes subidas en caso de error
            $this->limpiarImagenesError($imagenes ?? []);
            throw $e;
        }
    }
    
    /**
     * Actualizar un plan existente
     */
    public function update(int $id, array $data): ?Plan
    {
        try {
            DB::beginTransaction();
            
            $plan = Plan::find($id);
            if (!$plan) {
                DB::rollBack();
                return null;
            }
            
            // Guardar imágenes antiguas para limpiar si es necesario
            $imagenesAnteriores = [
                'imagen_principal' => $plan->imagen_principal,
                'imagenes_galeria' => $plan->imagenes_galeria
            ];
            
            // Procesar nuevas imágenes
            $imagenes = $this->procesarImagenes($data, $imagenesAnteriores);
            $data = array_merge($data, $imagenes);
            
            // Extraer datos de días y emprendedores
            $diasData = $data['dias'] ?? [];
            $emprendedoresData = $data['emprendedores'] ?? [];
            unset($data['dias'], $data['emprendedores']);
            
            // Actualizar el plan
            $plan->fill($data);
            $plan->save();
            
            // Actualizar emprendedores si se proporcionan
            if (!empty($emprendedoresData)) {
                $this->actualizarEmprendedoresPlan($plan->id, $emprendedoresData);
            }
            
            // Actualizar días si se proporcionan
            if (!empty($diasData)) {
                $this->actualizarDiasPlan($plan->id, $diasData);
            }
            
            // Limpiar imágenes antiguas si se reemplazaron
            $this->limpiarImagenesAnteriores($imagenesAnteriores, $imagenes);
            
            DB::commit();
            return $plan->fresh(['dias.servicios', 'emprendedores']);
            
        } catch (Exception $e) {
            DB::rollBack();
            // Limpiar nuevas imágenes en caso de error
            $this->limpiarImagenesError($imagenes ?? []);
            throw $e;
        }
    }
    
    /**
     * Agregar emprendedores a un plan
     */
    private function agregarEmprendedoresAPlan(int $planId, array $emprendedoresData): void
    {
        foreach ($emprendedoresData as $emprendedorData) {
            $defaults = [
                'rol' => 'colaborador',
                'es_organizador_principal' => false,
                'descripcion_participacion' => null,
                'porcentaje_ganancia' => null,
            ];
            
            $datos = array_merge($defaults, $emprendedorData);
            $emprendedorId = $datos['emprendedor_id'];
            unset($datos['emprendedor_id']);
            
            PlanEmprendedor::create([
                'plan_id' => $planId,
                'emprendedor_id' => $emprendedorId,
                ...$datos
            ]);
        }
    }
    
    /**
     * Actualizar emprendedores de un plan
     */
    private function actualizarEmprendedoresPlan(int $planId, array $emprendedoresData): void
    {
        // Eliminar emprendedores existentes
        PlanEmprendedor::where('plan_id', $planId)->delete();
        
        // Agregar nuevos emprendedores
        $this->agregarEmprendedoresAPlan($planId, $emprendedoresData);
    }
    
    /**
     * Obtener planes de un emprendedor específico con información de rol
     */
    public function getPlanesPorEmprendedor(int $emprendedorId, bool $soloActivos = false): Collection
    {
        $query = Plan::with([
            'dias.servicios', 
            'inscripciones',
            'emprendedores' => function($q) use ($emprendedorId) {
                $q->where('emprendedores.id', $emprendedorId);
            }
        ])->deEmprendedor($emprendedorId);
        
        if ($soloActivos) {
            $query->activos();
        }
        
        $planes = $query->orderBy('created_at', 'desc')->get();
        
        // Agregar información de rol para cada plan
        $planes->each(function($plan) use ($emprendedorId) {
            $emprendedor = $plan->emprendedores->first();
            if ($emprendedor) {
                $plan->rol_emprendedor = $emprendedor->pivot->rol;
                $plan->es_organizador_principal = $emprendedor->pivot->es_organizador_principal;
                $plan->descripcion_participacion = $emprendedor->pivot->descripcion_participacion;
                $plan->porcentaje_ganancia = $emprendedor->pivot->porcentaje_ganancia;
            }
        });
        
        return $planes;
    }
    
    /**
     * Obtener estadísticas de planes por emprendedor con roles
     */
    public function getEstadisticasPorEmprendedor(int $emprendedorId): array
    {
        $emprendedor = \App\Models\Emprendedor::find($emprendedorId);
        
        if (!$emprendedor) {
            return [];
        }
        
        $planesOrganizando = $emprendedor->planesOrganizando;
        $planesColaborando = $emprendedor->planesColaborando;
        $todosLosPlanes = $emprendedor->planesParticipando;
        
        return [
            'planes_organizando' => $planesOrganizando->count(),
            'planes_colaborando' => $planesColaborando->count(),
            'total_planes_participando' => $todosLosPlanes->count(),
            'planes_activos_organizando' => $planesOrganizando->where('estado', 'activo')->count(),
            'planes_activos_colaborando' => $planesColaborando->where('estado', 'activo')->count(),
            'inscripciones_planes_organizando' => $emprendedor->inscripcionesPlanesParticipando()
                ->whereHas('plan', function($q) use ($emprendedorId) {
                    $q->whereHas('emprendedores', function($sq) use ($emprendedorId) {
                        $sq->where('emprendedores.id', $emprendedorId)
                          ->where('plan_emprendedores.rol', 'organizador');
                    });
                })->count(),
            'ingresos_estimados_total' => $emprendedor->inscripcionesPlanesParticipando()
                ->where('estado', 'confirmada')
                ->sum('precio_pagado') ?? 0,
        ];
    }
    
    // ... [resto de métodos permanecen igual: delete, crearDiasPlan, actualizarDiasPlan, etc.]
    
    /**
     * Eliminar un plan
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $plan = Plan::with('dias')->find($id);
            if (!$plan) {
                DB::rollBack();
                return false;
            }
            
            // Guardar rutas de imágenes para eliminar
            $imagenes = [
                'imagen_principal' => $plan->imagen_principal,
                'imagenes_galeria' => $plan->imagenes_galeria
            ];
            
            // Eliminar inscripciones
            $plan->inscripciones()->delete();
            
            // Eliminar relaciones con emprendedores
            $plan->emprendedores()->detach();
            
            // Eliminar días y sus servicios
            foreach ($plan->dias as $dia) {
                $dia->servicios()->detach();
                $dia->delete();
            }
            
            // Eliminar el plan
            $deleted = $plan->delete();
            
            // Eliminar imágenes del storage
            $this->eliminarImagenes($imagenes);
            
            DB::commit();
            return $deleted;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Crear días para un plan
     */
    private function crearDiasPlan(int $planId, array $diasData): void
    {
        foreach ($diasData as $diaData) {
            $serviciosData = $diaData['servicios'] ?? [];
            unset($diaData['servicios']);
            
            $diaData['plan_id'] = $planId;
            $dia = PlanDia::create($diaData);
            
            // Agregar servicios al día
            if (!empty($serviciosData)) {
                $this->agregarServiciosADia($dia->id, $serviciosData);
            }
        }
    }
    
    /**
     * Actualizar días de un plan
     */
    private function actualizarDiasPlan(int $planId, array $diasData): void
    {
        // Obtener IDs de días existentes
        $diasExistentes = PlanDia::where('plan_id', $planId)->pluck('id', 'numero_dia');
        $diasActualizados = [];
        
        foreach ($diasData as $diaData) {
            $numeroDia = $diaData['numero_dia'];
            $serviciosData = $diaData['servicios'] ?? [];
            unset($diaData['servicios']);
            
            if (isset($diasExistentes[$numeroDia])) {
                // Actualizar día existente
                $dia = PlanDia::find($diasExistentes[$numeroDia]);
                $dia->fill($diaData);
                $dia->save();
                $diasActualizados[] = $dia->id;
            } else {
                // Crear nuevo día
                $diaData['plan_id'] = $planId;
                $dia = PlanDia::create($diaData);
                $diasActualizados[] = $dia->id;
            }
            
            // Actualizar servicios del día
            if (!empty($serviciosData)) {
                $this->actualizarServiciosDia($dia->id, $serviciosData);
            }
        }
        
        // Eliminar días que ya no están en la actualización
        PlanDia::where('plan_id', $planId)
               ->whereNotIn('id', $diasActualizados)
               ->delete();
    }
    
    /**
     * Agregar servicios a un día
     */
    private function agregarServiciosADia(int $diaId, array $serviciosData): void
    {
        foreach ($serviciosData as $servicioData) {
            $servicioData['plan_dia_id'] = $diaId;
            PlanDiaServicio::create($servicioData);
        }
    }
    
    /**
     * Actualizar servicios de un día
     */
    private function actualizarServiciosDia(int $diaId, array $serviciosData): void
    {
        // Eliminar servicios existentes del día
        PlanDiaServicio::where('plan_dia_id', $diaId)->delete();
        
        // Agregar nuevos servicios
        $this->agregarServiciosADia($diaId, $serviciosData);
    }
    
    /**
     * Procesar imágenes (convertir a WebP y guardar)
     */
    private function procesarImagenes(array $data, array $imagenesAnteriores = []): array
    {
        $resultado = [];
        
        // Procesar imagen principal
        if (isset($data['imagen_principal']) && $data['imagen_principal'] instanceof UploadedFile) {
            $resultado['imagen_principal'] = $this->guardarImagenWebP(
                $data['imagen_principal'], 
                'planes/principales'
            );
        } elseif (isset($imagenesAnteriores['imagen_principal'])) {
            $resultado['imagen_principal'] = $imagenesAnteriores['imagen_principal'];
        }
        
        // Procesar galería de imágenes
        if (isset($data['imagenes_galeria']) && is_array($data['imagenes_galeria'])) {
            $galeria = [];
            $galeriaAnterior = $imagenesAnteriores['imagenes_galeria'] ?? [];
            
            foreach ($data['imagenes_galeria'] as $index => $imagen) {
                if ($imagen instanceof UploadedFile) {
                    $galeria[] = $this->guardarImagenWebP($imagen, 'planes/galeria');
                } elseif (is_string($imagen) && isset($galeriaAnterior[$index])) {
                    // Mantener imagen existente
                    $galeria[] = $galeriaAnterior[$index];
                }
            }
            
            $resultado['imagenes_galeria'] = $galeria;
        } elseif (isset($imagenesAnteriores['imagenes_galeria'])) {
            $resultado['imagenes_galeria'] = $imagenesAnteriores['imagenes_galeria'];
        }
        
        return $resultado;
    }
    
    /**
     * Guardar imagen convertida a WebP
     */
    private function guardarImagenWebP(UploadedFile $archivo, string $directorio): string
    {
        // Crear directorio si no existe
        $rutaCompleta = storage_path("app/public/{$directorio}");
        if (!file_exists($rutaCompleta)) {
            mkdir($rutaCompleta, 0755, true);
        }

        // Nombre único
        $nombreArchivo = uniqid() . '.webp';
        $rutaArchivo = "{$directorio}/{$nombreArchivo}";
        $rutaCompletaArchivo = storage_path("app/public/{$rutaArchivo}");

        // Leer contenido binario
        $contenido = file_get_contents($archivo->getRealPath());

        // Crear imagen desde binario y redimensionar
        $imagen = Image::read($contenido)->scaleDown(width: 1200);

        // Guardar como WebP
        $imagen->toWebp(quality: 85)->save($rutaCompletaArchivo);

        return $rutaArchivo;
    }
    
    /**
     * Limpiar imágenes en caso de error
     */
    private function limpiarImagenesError(array $imagenes): void
    {
        if (isset($imagenes['imagen_principal'])) {
            Storage::disk('public')->delete($imagenes['imagen_principal']);
        }
        
        if (isset($imagenes['imagenes_galeria'])) {
            foreach ($imagenes['imagenes_galeria'] as $imagen) {
                Storage::disk('public')->delete($imagen);
            }
        }
    }
    
    /**
     * Limpiar imágenes anteriores reemplazadas
     */
    private function limpiarImagenesAnteriores(array $imagenesAnteriores, array $imagenesNuevas): void
    {
        // Limpiar imagen principal si se reemplazó
        if (isset($imagenesAnteriores['imagen_principal']) && 
            isset($imagenesNuevas['imagen_principal']) &&
            $imagenesAnteriores['imagen_principal'] !== $imagenesNuevas['imagen_principal']) {
            Storage::disk('public')->delete($imagenesAnteriores['imagen_principal']);
        }
        
        // Limpiar imágenes de galería reemplazadas
        if (isset($imagenesAnteriores['imagenes_galeria']) && isset($imagenesNuevas['imagenes_galeria'])) {
            $anteriores = $imagenesAnteriores['imagenes_galeria'];
            $nuevas = $imagenesNuevas['imagenes_galeria'];
            
            foreach ($anteriores as $imagenAnterior) {
                if (!in_array($imagenAnterior, $nuevas)) {
                    Storage::disk('public')->delete($imagenAnterior);
                }
            }
        }
    }
    
    /**
     * Eliminar todas las imágenes de un plan
     */
    private function eliminarImagenes(array $imagenes): void
    {
        if (isset($imagenes['imagen_principal'])) {
            Storage::disk('public')->delete($imagenes['imagen_principal']);
        }
        
        if (isset($imagenes['imagenes_galeria']) && is_array($imagenes['imagenes_galeria'])) {
            foreach ($imagenes['imagenes_galeria'] as $imagen) {
                Storage::disk('public')->delete($imagen);
            }
        }
    }
    
    /**
     * Buscar planes
     */
    public function buscar(string $termino, array $filtros = []): Collection
    {
        $query = Plan::with(['emprendedores', 'dias'])
                     ->buscar($termino)
                     ->publicos()
                     ->activos();
        
        // Aplicar filtros adicionales
        if (!empty($filtros['dificultad'])) {
            $query->porDificultad($filtros['dificultad']);
        }
        
        if (!empty($filtros['emprendedor_id'])) {
            $query->deEmprendedor($filtros['emprendedor_id']);
        }
        
        return $query->get();
    }
    
    /**
     * Obtener estadísticas de un plan
     */
    public function getEstadisticas(int $planId): array
    {
        $plan = Plan::with(['inscripciones', 'dias.servicios', 'emprendedores'])->find($planId);
        
        if (!$plan) {
            return [];
        }
        
        $inscripciones = $plan->inscripciones;
        
        return [
            'total_inscripciones' => $inscripciones->count(),
            'inscripciones_confirmadas' => $inscripciones->where('estado', 'confirmada')->count(),
            'inscripciones_pendientes' => $inscripciones->where('estado', 'pendiente')->count(),
            'inscripciones_canceladas' => $inscripciones->where('estado', 'cancelada')->count(),
            'total_participantes' => $inscripciones->where('estado', 'confirmada')->sum('numero_participantes'),
            'ingresos_totales' => $inscripciones->where('estado', 'confirmada')->sum('precio_pagado'),
            'promedio_participantes_por_inscripcion' => $inscripciones->where('estado', 'confirmada')->avg('numero_participantes'),
            'cupos_disponibles' => $plan->cupos_disponibles,
            'porcentaje_ocupacion' => ($plan->capacidad > 0) ? 
                (($inscripciones->where('estado', 'confirmada')->sum('numero_participantes') / $plan->capacidad) * 100) : 0,
            'total_emprendedores' => $plan->emprendedores->count(),
            'organizador_principal' => $plan->organizador_principal?->nombre,
        ];
    }
    /**
     * Obtener planes públicos optimizado para catálogo público
     */
    public function getPlanesPublicos(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Plan::with([
            'emprendedor:id,nombre,ubicacion', // Legacy para compatibilidad
            'emprendedores:id,nombre,ubicacion,telefono', // Múltiples emprendedores
            'organizadorPrincipal:id,nombre,ubicacion,telefono', // Organizador principal
            'dias:id,plan_id,numero_dia', // Removido 'nombre' que no existe
            'inscripciones:id,plan_id,estado,numero_participantes'
        ])
        ->select([
            'id', 'nombre', 'descripcion', 'que_incluye', 'capacidad', 
            'duracion_dias', 'precio_total', 'dificultad', 'imagen_principal', 
            'imagenes_galeria', 'emprendedor_id', 'created_at', 'updated_at'
        ]);
        
        // Aplicar filtros básicos obligatorios
        $query->publicos()->activos();
        
        // Filtro de cupos disponibles
        if (!empty($filtros['con_cupos']) && $filtros['con_cupos'] === 'true') {
            $query->conCuposDisponibles();
        }
        
        // Filtro por emprendedor (cualquier emprendedor participante)
        if (!empty($filtros['emprendedor_id'])) {
            $query->deEmprendedor($filtros['emprendedor_id']);
        }
        
        // Filtro por organizador principal específico
        if (!empty($filtros['organizador_id'])) {
            $query->organizadoPor($filtros['organizador_id']);
        }
        
        // Filtro por dificultad
        if (!empty($filtros['dificultad'])) {
            $query->where('dificultad', $filtros['dificultad']);
        }
        
        // Filtros de duración
        if (!empty($filtros['duracion_min'])) {
            $query->where('duracion_dias', '>=', (int)$filtros['duracion_min']);
        }
        
        if (!empty($filtros['duracion_max'])) {
            $query->where('duracion_dias', '<=', (int)$filtros['duracion_max']);
        }
        
        // Filtros de precio
        if (!empty($filtros['precio_min'])) {
            $query->where('precio_total', '>=', (float)$filtros['precio_min']);
        }
        
        if (!empty($filtros['precio_max'])) {
            $query->where('precio_total', '<=', (float)$filtros['precio_max']);
        }
        
        // Búsqueda por texto
        if (!empty($filtros['buscar'])) {
            $query->buscar($filtros['buscar']);
        }
        
        // Ordenamiento corregido para PostgreSQL
        $query->orderByRaw('
            CASE 
                WHEN capacidad > (
                    SELECT COALESCE(SUM(numero_participantes), 0) 
                    FROM plan_inscripciones 
                    WHERE plan_id = plans.id AND estado = ?
                ) THEN 0 
                ELSE 1 
            END
        ', ['confirmada']) // Parámetro preparado para PostgreSQL
        ->orderBy('created_at', 'desc');
        
        $result = $query->paginate($perPage);
        
        // Agregar información calculada para cada plan
        $result->getCollection()->transform(function ($plan) {
            // Calcular cupos disponibles
            $inscripcionesConfirmadas = $plan->inscripciones
                ->where('estado', 'confirmada')
                ->sum('numero_participantes');
            
            $plan->cupos_ocupados = $inscripcionesConfirmadas;
            $plan->cupos_disponibles = max(0, $plan->capacidad - $inscripcionesConfirmadas);
            $plan->porcentaje_ocupacion = $plan->capacidad > 0 
                ? round(($inscripcionesConfirmadas / $plan->capacidad) * 100, 1)
                : 0;
            
            // Información del organizador principal
            $organizador = $plan->organizador_principal;
            $plan->organizador = $organizador ? [
                'id' => $organizador->id,
                'nombre' => $organizador->nombre,
                'ubicacion' => $organizador->ubicacion,
                'telefono' => $organizador->telefono
            ] : null;
            
            // Total de emprendedores participantes
            $plan->total_emprendedores_participantes = $plan->emprendedores->count();
            
            // URLs de imágenes
            $plan->imagen_principal_url = $plan->imagen_principal_url;
            $plan->imagenes_galeria_urls = $plan->imagenes_galeria_urls;
            
            // Limpiar relaciones innecesarias para reducir payload
            unset($plan->inscripciones);
            
            return $plan;
        });
        
        return $result;
    }
}