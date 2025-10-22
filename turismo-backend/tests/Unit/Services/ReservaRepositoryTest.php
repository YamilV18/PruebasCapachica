<?php

namespace Tests\Unit\Services;

use App\Models\Reserva;
use App\Models\ReservaServicio;
use App\Models\User;
use App\Models\Servicio;
use App\Models\Emprendedor;
use App\Models\Asociacion;
use App\Models\Municipalidad;
use App\Repository\ReservaRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReservaRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ReservaRepository $repository;
    protected User $usuario;
    protected Emprendedor $emprendedor;
    protected Servicio $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ReservaRepository(new Reserva());

        // Crear estructura básica
        $this->usuario = User::factory()->create();

        $municipalidad = Municipalidad::factory()->create();
        $asociacion = Asociacion::factory()->create(['municipalidad_id' => $municipalidad->id]);
        $this->emprendedor = Emprendedor::factory()->create(['asociacion_id' => $asociacion->id]);
        $this->servicio = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);
    }

    #[Test]
    public function puede_obtener_todas_las_reservas_excluyendo_carrito()
    {
        // Arrange
        $reservasNormales = Reserva::factory()->count(3)->pendiente()->create(['usuario_id' => $this->usuario->id]);

        // CORRECCIÓN: Eliminar 'codigo_reserva' para que la factoría genere uno único para cada una.
        $reservasCarrito = Reserva::factory()->count(2)->enCarrito()
            ->sequence(
                ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
                ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
            )
            ->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->getAll();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        foreach ($result as $reserva) {
            $this->assertNotEquals(Reserva::ESTADO_EN_CARRITO, $reserva->estado);
        }

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('usuario'));
        $this->assertTrue($result->first()->relationLoaded('servicios'));
    }

    #[Test]
    public function puede_obtener_reservas_paginadas()
    {
        Reserva::factory()->count(25)->pendiente()->create(['usuario_id' => $this->usuario->id]);
// Esto funciona si la Factoría tiene el código de reserva y el usuario_id (o relación) bien definidos.
        Reserva::factory()->count(2)->enCarrito()
            ->sequence(
                ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
                ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
            )
            ->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->getPaginated(10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(25, $result->total()); // Solo las no-carrito
        $this->assertCount(10, $result->items());

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->items()[0]->relationLoaded('usuario'));
        $this->assertTrue($result->items()[0]->relationLoaded('servicios'));
    }

    #[Test]
    public function puede_encontrar_reserva_por_id()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->findById($reserva->id);

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertEquals($reserva->id, $result->id);
        $this->assertEquals($reserva->codigo_reserva, $result->codigo_reserva);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->relationLoaded('usuario'));
        $this->assertTrue($result->relationLoaded('servicios'));
    }

    #[Test]
    public function retorna_null_cuando_reserva_no_existe()
    {
        // Act
        $result = $this->repository->findById(999);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_obtener_carrito_por_usuario()
    {
        // Arrange
        $carritoUsuario = Reserva::factory()->enCarrito()->sequence(
            ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
            ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
        )
            ->create(['usuario_id' => $this->usuario->id]);
        $reservaNormal = Reserva::factory()->pendiente()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->getCarritoByUsuario($this->usuario->id);

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertEquals($carritoUsuario->id, $result->id);
        $this->assertEquals(Reserva::ESTADO_EN_CARRITO, $result->estado);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->relationLoaded('servicios'));
    }

    #[Test]
    public function retorna_null_si_usuario_no_tiene_carrito()
    {
        // Arrange
        Reserva::factory()->pendiente()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->getCarritoByUsuario($this->usuario->id);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_crear_carrito_si_no_existe()
    {
        // Act
        $result = $this->repository->getOrCreateCarrito($this->usuario->id);

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertEquals($this->usuario->id, $result->usuario_id);
        $this->assertEquals(Reserva::ESTADO_EN_CARRITO, $result->estado);
        $this->assertNotNull($result->codigo_reserva);

        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_EN_CARRITO
        ]);
    }

    #[Test]
    public function retorna_carrito_existente_si_ya_existe()
    {
        // Arrange
        $carritoExistente = Reserva::factory()->enCarrito()->create([
            'usuario_id' => $this->usuario->id,
            'codigo_reserva' => 'TEMP' . $this->usuario->id . time(),
        ]);

        // Act
        $result = $this->repository->getOrCreateCarrito($this->usuario->id);

        // Assert
        $this->assertEquals($carritoExistente->id, $result->id);
        $this->assertEquals($carritoExistente->codigo_reserva, $result->codigo_reserva);

        // Verificar que no se creó otro carrito
        $carritos = Reserva::where('usuario_id', $this->usuario->id)
                           ->where('estado', Reserva::ESTADO_EN_CARRITO)
                           ->count();
        $this->assertEquals(1, $carritos);
    }

    #[Test]
    public function puede_crear_reserva_sin_servicios()
    {
        // Arrange
        $data = [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas' => 'Reserva de prueba'
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertEquals($data['usuario_id'], $result->usuario_id);
        $this->assertEquals($data['estado'], $result->estado);
        $this->assertEquals($data['notas'], $result->notas);
        $this->assertNotNull($result->codigo_reserva);

        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE
        ]);
    }

    #[Test]
    public function puede_crear_reserva_con_servicios()
    {
        // Arrange
        $data = [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
            'notas' => 'Reserva con servicios'
        ];

        $servicios = [
            [
                'servicio_id' => $this->servicio->id,
                'emprendedor_id' => $this->emprendedor->id,
                'fecha_inicio' => '2024-08-15',
                'hora_inicio' => '09:00:00',
                'hora_fin' => '17:00:00',
                'duracion_minutos' => 480,
                'cantidad' => 2,
                'precio' => 150.00,
                'estado' => ReservaServicio::ESTADO_PENDIENTE
            ]
        ];

        // Act
        $result = $this->repository->create($data, $servicios);

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertCount(1, $result->servicios);

        $this->assertDatabaseHas('reserva_servicios', [
            'reserva_id' => $result->id,
            'servicio_id' => $this->servicio->id,
            'cantidad' => 2,
            'precio' => 150.00
        ]);
    }

    #[Test]
    public function puede_actualizar_reserva_sin_servicios()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);

        $data = [
            'estado' => Reserva::ESTADO_CONFIRMADA,
            'notas' => 'Notas actualizadas'
        ];

        // Act
        $result = $this->repository->update($reserva->id, $data);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'estado' => Reserva::ESTADO_CONFIRMADA,
            'notas' => 'Notas actualizadas'
        ]);
    }

    #[Test]
    public function puede_actualizar_reserva_con_nuevos_servicios()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);

        $data = ['notas' => 'Reserva actualizada'];
        $servicios = [
            [
                'servicio_id' => $this->servicio->id,
                'emprendedor_id' => $this->emprendedor->id,
                'fecha_inicio' => '2024-08-20',
                'hora_inicio' => '10:00:00',
                'hora_fin' => '16:00:00',
                'duracion_minutos' => 360,
                'cantidad' => 1,
                'precio' => 100.00,
                'estado' => ReservaServicio::ESTADO_PENDIENTE
            ]
        ];

        // Act
        $result = $this->repository->update($reserva->id, $data, $servicios);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('reserva_servicios', [
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id
        ]);
    }

    #[Test]
    public function puede_actualizar_servicios_existentes()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);
        $servicioExistente = ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id,
            'precio' => 100.00
        ]);

        $data = ['notas' => 'Actualizada'];
        $servicios = [
            [
                'id' => $servicioExistente->id,
                'precio' => 150.00, // Precio actualizado
                'cantidad' => 3
            ]
        ];

        // Act
        $result = $this->repository->update($reserva->id, $data, $servicios);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('reserva_servicios', [
            'id' => $servicioExistente->id,
            'precio' => 150.00,
            'cantidad' => 3
        ]);
    }

    #[Test]
    public function retorna_false_al_actualizar_reserva_inexistente()
    {
        // Act
        $result = $this->repository->update(999, ['notas' => 'Test']);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_eliminar_reserva_existente()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);
        $servicio = ReservaServicio::factory()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->delete($reserva->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
        $this->assertDatabaseMissing('reserva_servicios', ['id' => $servicio->id]);
    }

    #[Test]
    public function retorna_false_al_eliminar_reserva_inexistente()
    {
        // Act
        $result = $this->repository->delete(999);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_obtener_reservas_por_usuario()
    {
        // Arrange
        $otroUsuario = User::factory()->create();

        $reservasUsuario1 = Reserva::factory()->count(3)->pendiente()->create(['usuario_id' => $this->usuario->id]);
        $reservasUsuario2 = Reserva::factory()->count(2)->confirmada()->create(['usuario_id' => $otroUsuario->id]);
        $carritoUsuario1 = Reserva::factory()->enCarrito()->sequence(
            ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
            ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
        )
            ->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->getByUsuario($this->usuario->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result); // Excluye carrito

        foreach ($result as $reserva) {
            $this->assertEquals($this->usuario->id, $reserva->usuario_id);
            $this->assertNotEquals(Reserva::ESTADO_EN_CARRITO, $reserva->estado);
        }

        // Verificar ordenamiento por fecha de creación descendente
        $fechas = $result->pluck('created_at')->toArray();
        $fechasOrdenadas = collect($fechas)->sort()->reverse()->toArray();
        $this->assertEquals($fechasOrdenadas, $fechas);
    }

    #[Test]
    public function puede_obtener_reservas_por_estado()
    {
        // Arrange
        $reservasPendientes = Reserva::factory()->count(3)->pendiente()->create(['usuario_id' => $this->usuario->id]);
        $reservasConfirmadas = Reserva::factory()->count(2)->confirmada()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $pendientes = $this->repository->getByEstado(Reserva::ESTADO_PENDIENTE);
        $confirmadas = $this->repository->getByEstado(Reserva::ESTADO_CONFIRMADA);

        // Assert
        $this->assertCount(3, $pendientes);
        $this->assertCount(2, $confirmadas);

        foreach ($pendientes as $reserva) {
            $this->assertEquals(Reserva::ESTADO_PENDIENTE, $reserva->estado);
        }

        foreach ($confirmadas as $reserva) {
            $this->assertEquals(Reserva::ESTADO_CONFIRMADA, $reserva->estado);
        }
    }

    #[Test]
    public function puede_obtener_reservas_por_emprendedor()
    {
        // Arrange
        $otroEmprendedor = Emprendedor::factory()->create(['asociacion_id' => $this->emprendedor->asociacion_id]);

        $reserva1 = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);
        $reserva2 = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);

        ReservaServicio::factory()->create([
            'reserva_id' => $reserva1->id,
            'emprendedor_id' => $this->emprendedor->id,
            'servicio_id' => $this->servicio->id
        ]);

        ReservaServicio::factory()->create([
            'reserva_id' => $reserva2->id,
            'emprendedor_id' => $otroEmprendedor->id,
            'servicio_id' => $this->servicio->id
        ]);

        // Act
        $result = $this->repository->getByEmprendedor($this->emprendedor->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($reserva1->id, $result->first()->id);

        // Verificar que las relaciones están cargadas
        $this->assertTrue($result->first()->relationLoaded('usuario'));
        $this->assertTrue($result->first()->relationLoaded('servicios'));
    }

    #[Test]
    public function puede_obtener_reservas_por_servicio()
    {
        // Arrange
        $otroServicio = Servicio::factory()->create(['emprendedor_id' => $this->emprendedor->id]);

        $reserva1 = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);
        $reserva2 = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);

        ReservaServicio::factory()->create([
            'reserva_id' => $reserva1->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        ReservaServicio::factory()->create([
            'reserva_id' => $reserva2->id,
            'servicio_id' => $otroServicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->getByServicio($this->servicio->id);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertEquals($reserva1->id, $result->first()->id);
    }

    #[Test]
    public function puede_cambiar_estado_de_reserva()
    {
        // Arrange
        $reserva = Reserva::factory()->pendiente()->create(['usuario_id' => $this->usuario->id]);
        $servicio = ReservaServicio::factory()->pendiente()->create([
            'reserva_id' => $reserva->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->cambiarEstado($reserva->id, Reserva::ESTADO_CONFIRMADA);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'estado' => Reserva::ESTADO_CONFIRMADA
        ]);
        $this->assertDatabaseHas('reserva_servicios', [
            'id' => $servicio->id,
            'estado' => ReservaServicio::ESTADO_CONFIRMADO
        ]);
    }

    #[Test]
    public function retorna_false_al_cambiar_estado_de_reserva_inexistente()
    {
        // Act
        $result = $this->repository->cambiarEstado(999, Reserva::ESTADO_CONFIRMADA);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function puede_confirmar_carrito_con_servicios()
    {
        // Arrange
        $carrito = Reserva::factory()->enCarrito()->sequence(
            ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
            ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
        )
            ->create(['usuario_id' => $this->usuario->id]);
        $servicio = ReservaServicio::factory()->enCarrito()->create([
            'reserva_id' => $carrito->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->confirmarCarrito($carrito->id, 'Notas de confirmación');

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertEquals(Reserva::ESTADO_PENDIENTE, $result->estado);
        $this->assertEquals('Notas de confirmación', $result->notas);

        $this->assertDatabaseHas('reserva_servicios', [
            'id' => $servicio->id,
            'estado' => ReservaServicio::ESTADO_PENDIENTE
        ]);
    }

    #[Test]
    public function retorna_null_al_confirmar_carrito_vacio()
    {
        // Arrange
        $carrito = Reserva::factory()->enCarrito()->sequence(
            ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
            ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
        )
            ->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->confirmarCarrito($carrito->id);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function retorna_null_al_confirmar_reserva_no_carrito()
    {
        // Arrange
        $reserva = Reserva::factory()->pendiente()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->confirmarCarrito($reserva->id);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function puede_vaciar_carrito()
    {
        // Arrange
        $carrito = Reserva::factory()->enCarrito()->sequence(
            ['codigo_reserva' => 'A8JF83NV75NF902NJ'], // Primer código
            ['codigo_reserva' => 'B9GK94OW86OG013OK'], // Segundo código
        )
            ->create(['usuario_id' => $this->usuario->id]);
        $servicios = ReservaServicio::factory()->count(3)->create([
            'reserva_id' => $carrito->id,
            'servicio_id' => $this->servicio->id,
            'emprendedor_id' => $this->emprendedor->id
        ]);

        // Act
        $result = $this->repository->vaciarCarrito($carrito->id);

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('reservas', ['id' => $carrito->id]); // Carrito sigue existiendo

        foreach ($servicios as $servicio) {
            $this->assertDatabaseMissing('reserva_servicios', ['id' => $servicio->id]);
        }
    }

    #[Test]
    public function retorna_false_al_vaciar_reserva_no_carrito()
    {
        // Arrange
        $reserva = Reserva::factory()->pendiente()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $result = $this->repository->vaciarCarrito($reserva->id);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_creacion()
    {
        // Arrange
        $data = [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE
        ];

        // Act
        DB::beginTransaction();
        $result = $this->repository->create($data);
        DB::commit();

        // Assert
        $this->assertInstanceOf(Reserva::class, $result);
        $this->assertDatabaseHas('reservas', [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_actualizacion()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);
        $data = ['estado' => Reserva::ESTADO_CONFIRMADA];

        // Act
        DB::beginTransaction();
        $result = $this->repository->update($reserva->id, $data);
        DB::commit();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->id,
            'estado' => Reserva::ESTADO_CONFIRMADA
        ]);
    }

    #[Test]
    public function maneja_transacciones_correctamente_en_eliminacion()
    {
        // Arrange
        $reserva = Reserva::factory()->create(['usuario_id' => $this->usuario->id]);

        // Act
        DB::beginTransaction();
        $result = $this->repository->delete($reserva->id);
        DB::commit();

        // Assert
        $this->assertTrue($result);
        $this->assertDatabaseMissing('reservas', ['id' => $reserva->id]);
    }

    #[Test]
    public function puede_usar_paginacion_con_diferentes_tamaños()
    {
        // Arrange
        Reserva::factory()->count(30)->pendiente()->create(['usuario_id' => $this->usuario->id]);

        // Act
        $resultados5 = $this->repository->getPaginated(5);
        $resultados10 = $this->repository->getPaginated(10);
        $resultados15 = $this->repository->getPaginated(); // Default

        // Assert
        $this->assertEquals(5, $resultados5->perPage());
        $this->assertEquals(10, $resultados10->perPage());
        $this->assertEquals(15, $resultados15->perPage());

        $this->assertEquals(30, $resultados5->total());
        $this->assertEquals(30, $resultados10->total());
        $this->assertEquals(30, $resultados15->total());
    }

    #[Test]
    public function genera_codigo_reserva_automaticamente_si_no_se_proporciona()
    {
        // Arrange
        $data = [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result->codigo_reserva);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}\d{6}$/', $result->codigo_reserva);
    }

    #[Test]
    public function respeta_codigo_reserva_proporcionado()
    {
        // Arrange
        $codigoPersonalizado = 'CUSTOM123456';
        $data = [
            'usuario_id' => $this->usuario->id,
            'estado' => Reserva::ESTADO_PENDIENTE,
            'codigo_reserva' => $codigoPersonalizado
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertEquals($codigoPersonalizado, $result->codigo_reserva);
    }
}
