<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Categoria;
use App\Models\Municipalidad;
use App\Models\Asociacion;
use App\Models\Servicio;
use App\Models\ServicioHorario;
use App\Models\Reserva;
use App\Models\ReservaServicio;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\Emprendedor;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear roles y permisos
        $this->createRolesAndPermissions();

        // Crear usuarios admin y usuario normal
        $this->createUsers();

        // Crear datos de municipalidad
        $this->createMunicipalidad();

        // Crear categorías
        $this->createCategorias();

        // Crear asociaciones
        $this->createAsociaciones();

        // Crear emprendedores
        $this->createEmprendedores();

        // Crear servicios y sus horarios
        $this->createServicios();

        // Crear reservas de ejemplo
        $this->createReservas();

        // Ejecutar el seeder para asociar usuarios con emprendimientos
        $this->call(UserEmprendedorSeeder::class);
    }

    private function createRolesAndPermissions()
    {
        // Crear permisos
        $permissions = [
            // Gestión de usuarios
            'user_create', 'user_read', 'user_update', 'user_delete',

            // Gestión de roles
            'role_create', 'role_read', 'role_update', 'role_delete',

            // Gestión de permisos
            'permission_read', 'permission_assign',

            // Gestión de emprendedores
            'emprendedor_create', 'emprendedor_read', 'emprendedor_update', 'emprendedor_delete',

            // Gestión de servicios
            'servicio_create', 'servicio_read', 'servicio_update', 'servicio_delete',

            // Gestión de categorías
            'categoria_create', 'categoria_read', 'categoria_update', 'categoria_delete',

            // Gestión de asociaciones
            'asociacion_create', 'asociacion_read', 'asociacion_update', 'asociacion_delete',

            // Gestión de municipalidades
            'municipalidad_create', 'municipalidad_read', 'municipalidad_update', 'municipalidad_delete',

            // Gestión de reservas
            'reserva_create', 'reserva_read', 'reserva_update', 'reserva_delete',

            // Gestión de sliders
            'slider_create', 'slider_read', 'slider_update', 'slider_delete',

            // Gestión de eventos
            'evento_create', 'evento_read', 'evento_update', 'evento_delete',

            // Gestión de planes
            'plan_create', 'plan_read', 'plan_update', 'plan_delete',
            'plan_manage_emprendedores', 'plan_manage_inscripciones',

            // Gestión de inscripciones a planes
            'inscripcion_create', 'inscripcion_read', 'inscripcion_update', 'inscripcion_delete',
            'inscripcion_confirmar', 'inscripcion_cancelar',

            // Dashboard y reportes
            'dashboard_read', 'estadisticas_read',

            // Administración avanzada
            'admin_planes_read', 'admin_inscripciones_read'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Crear roles
        $adminRole = Role::create(['name' => 'admin']);
        $userRole = Role::create(['name' => 'user']);
        $emprendedorRole = Role::create(['name' => 'emprendedor']);
        $moderadorRole = Role::create(['name' => 'moderador']);

        // Asignar todos los permisos al rol admin
        $adminRole->givePermissionTo(Permission::all());

        // Asignar permisos limitados al rol user (turista/cliente)
        $userRole->givePermissionTo([
            // Lectura de contenido público
            'emprendedor_read', 'servicio_read', 'categoria_read',
            'asociacion_read', 'municipalidad_read', 'evento_read',
            'plan_read', 'slider_read',

            // Gestión de sus propias reservas
            'reserva_create', 'reserva_read', 'reserva_update',

            // Gestión de sus propias inscripciones a planes
            'inscripcion_create', 'inscripcion_read', 'inscripcion_update',
            'inscripcion_cancelar',

            // Su propio perfil
            'user_read'
        ]);

        // Asignar permisos de emprendedor
        $emprendedorRole->givePermissionTo([
            // Lectura general
            'emprendedor_read', 'servicio_read', 'categoria_read',
            'asociacion_read', 'municipalidad_read', 'evento_read',
            'plan_read', 'slider_read', 'user_read',

            // Gestión de sus servicios
            'servicio_create', 'servicio_update', 'servicio_delete',

            // Gestión de sus eventos
            'evento_create', 'evento_update', 'evento_delete',

            // Gestión de sus planes
            'plan_create', 'plan_read', 'plan_update', 'plan_delete',
            'plan_manage_emprendedores', 'plan_manage_inscripciones',

            // Gestión de inscripciones a sus planes
            'inscripcion_read', 'inscripcion_update', 'inscripcion_confirmar',
            'inscripcion_cancelar',

            // Gestión de reservas (ver las de sus servicios)
            'reserva_read', 'reserva_update',

            // Sus propias inscripciones como cliente
            'inscripcion_create',

            // Gestión básica de sliders para su contenido
            'slider_create', 'slider_update', 'slider_delete',

            // Acceso a estadísticas básicas
            'estadisticas_read'
        ]);

        // Nuevo rol: Moderador (para administrar contenido sin permisos de sistema)
        $moderadorRole->givePermissionTo([
            // Lectura completa
            'user_read', 'emprendedor_read', 'servicio_read', 'categoria_read',
            'asociacion_read', 'municipalidad_read', 'evento_read', 'plan_read',
            'slider_read', 'reserva_read', 'inscripcion_read',

            // Gestión de contenido
            'emprendedor_update', 'servicio_update', 'evento_update',
            'plan_update', 'slider_update',

            // Gestión de asociaciones y municipalidades
            'asociacion_create', 'asociacion_update', 'asociacion_delete',
            'municipalidad_create', 'municipalidad_update', 'municipalidad_delete',

            // Gestión de categorías
            'categoria_create', 'categoria_update', 'categoria_delete',

            // Moderación de reservas e inscripciones
            'reserva_update', 'inscripcion_update', 'inscripcion_confirmar',
            'inscripcion_cancelar',

            // Acceso a dashboard y estadísticas
            'dashboard_read', 'estadisticas_read'
        ]);
    }

    private function createUsers()
    {
        // Admin user
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-15',
            'address' => 'Plaza Principal s/n, Capachica, Puno',
            'gender' => 'male',
            'preferred_language' => 'es',
            'active' => true,
            'last_login' => now()->subDays(1)

        ]);
        $admin->assignRole('admin');

        // Normal user
        $user = User::create([
            'name' => 'Nate',
            'email' => 'nate@gmail.com',
            'password' => Hash::make('123456'),
            'email_verified_at' => now(),
            'phone' => '987654321',
            'country' => 'Perú',
            'birth_date' => '1995-06-20',
            'address' => 'Av. Arequipa 123, Lima',
            'gender' => 'male',
            'preferred_language' => 'es',
            'active' => true,
            'last_login' => now()->subDays(3)
        ]);
        $user->assignRole('user');

        // Entrepreneur user
        $emprendedor = User::create([
            'name' => 'Emprendedor Local',
            'email' => 'emprendedor@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone' => '555444333',
            'country' => 'Perú',
            'birth_date' => '1985-12-10',
            'address' => 'Comunidad Llachón, Capachica, Puno',
            'gender' => 'male',
            'preferred_language' => 'es',
            'active' => true,
            'last_login' => now()->subHours(12)
        ]);
        $emprendedor->assignRole('emprendedor');
    }

    private function createMunicipalidad()
    {
        Municipalidad::create([
            'nombre' => 'Municipalidad Distrital de Capachica',
            'descripcion' => 'La Municipalidad Distrital de Capachica es una institución pública que vela por el desarrollo sostenible del distrito a través de la promoción del turismo, conservación del medio ambiente y mejora de la calidad de vida de sus pobladores.',
            'red_facebook' => 'https://facebook.com/municipalidadcapachica',
            'red_instagram' => 'https://instagram.com/municapachica',
            'red_youtube' => 'https://youtube.com/municapachica',
            'coordenadas_x' => -15.6425,
            'coordenadas_y' => -69.8330,
            'frase' => '¡Bienvenidos a Capachica, Paraíso Turístico del Lago Titicaca!',
            'comunidades' => 'Capachica cuenta con 16 comunidades: Llachón, Cotos, Siale, Hilata, Isañura, San Cristóbal, Escallani, Chillora, Yapura, Collasuyo, Miraflores, Villa Lago, Capano, Ccotos, Yancaco y Central.',
            'historiafamilias' => 'Las familias de Capachica han mantenido sus tradiciones ancestrales por generaciones, dedicándose principalmente a la agricultura, pesca y ahora al turismo vivencial.',
            'historiacapachica' => 'Capachica es una península situada en el lago Titicaca con una rica historia preinca e inca. Durante la colonia española, se establecieron haciendas que luego dieron paso a comunidades campesinas que hoy preservan su cultura y patrimonio.',
            'comite' => 'El comité de desarrollo turístico de Capachica está formado por representantes de las comunidades, emprendedores locales y autoridades municipales.',
            'mision' => 'Promover el desarrollo sostenible del distrito mediante la gestión eficiente de recursos, servicios públicos de calidad y promoción del turismo vivencial, respetando la identidad cultural y el medio ambiente.',
            'vision' => 'Al 2030, ser un distrito modelo en turismo sostenible, con infraestructura adecuada, servicios de calidad y una población con mejor calidad de vida, preservando su identidad cultural y recursos naturales.',
            'valores' => 'Honestidad, Transparencia, Respeto al medio ambiente, Identidad cultural, Trabajo en equipo, Compromiso social',
            'ordenanzamunicipal' => 'Ordenanza Municipal N° 015-2023-MDP que regula la actividad turística y establece estándares para la prestación de servicios turísticos en el distrito.',
            'alianzas' => 'Trabajamos en alianza con MINCETUR, DIRCETUR Puno, Programa TRC, PNUD, GIZ, y diversas universidades para la capacitación de emprendedores y promoción del turismo.',
            'correo' => 'informes@municapachica.gob.pe',
            'horariodeatencion' => 'Lunes a Viernes: 8:00 am - 4:00 pm'
        ]);
    }

    private function createCategorias()
    {
        $categorias = [
            [
                'nombre' => 'Alojamiento',
                'descripcion' => 'Diferentes opciones de hospedaje, desde casas rurales hasta ecolodges.',
                'icono_url' => 'icons/alojamiento.svg'
            ],
            [
                'nombre' => 'Alimentación',
                'descripcion' => 'Restaurantes y servicios de comida tradicional de la región.',
                'icono_url' => 'icons/alimentacion.svg'
            ],
            [
                'nombre' => 'Artesanía',
                'descripcion' => 'Productos hechos a mano por artesanos locales.',
                'icono_url' => 'icons/artesania.svg'
            ],
            [
                'nombre' => 'Transporte',
                'descripcion' => 'Servicios de transporte terrestre y lacustre.',
                'icono_url' => 'icons/transporte.svg'
            ],
            [
                'nombre' => 'Actividades',
                'descripcion' => 'Experiencias y actividades turísticas.',
                'icono_url' => 'icons/actividades.svg'
            ],
            [
                'nombre' => 'Guiado',
                'descripcion' => 'Servicios de guías turísticos locales.',
                'icono_url' => 'icons/guiado.svg'
            ]
        ];

        foreach ($categorias as $categoria) {
            Categoria::create($categoria);
        }
    }

    private function createAsociaciones()
    {
        $asociaciones = [
            [
                'nombre'           => 'Asociación de Turismo Vivencial Llachón',
                'descripcion'      => 'Grupo de familias que ofrecen servicios de turismo vivencial en la comunidad de Llachón.',
                'latitud'          => -15.6450,
                'longitud'         => -69.8345,
                'telefono'         => '951234567',
                'email'            => 'turvivllachon@gmail.com',
                'municipalidad_id' => 1,
                'imagen'           => 'asociaciones/llachon.jpg',
            ],
            [
                'nombre'           => 'Asociación de Artesanos de Capachica',
                'descripcion'      => 'Reúne a artesanos tradicionales que elaboran textiles, cerámica y otros productos artesanales.',
                'latitud'          => -15.6425,
                'longitud'         => -69.8330,
                'telefono'         => '951987654',
                'email'            => 'artesanoscapachica@gmail.com',
                'municipalidad_id' => 1,
                'imagen'           => 'asociaciones/artesanos.jpg',
            ],
            [
                'nombre'           => 'Asociación de Turismo Rural Comunitario Isla Ticonata',
                'descripcion'      => 'Familias que ofrecen servicios turísticos en la isla Ticonata.',
                'latitud'          => -15.6410,
                'longitud'         => -69.8320,
                'telefono'         => '952345678',
                'email'            => 'ticonata.trc@gmail.com',
                'municipalidad_id' => 1,
                'imagen'           => 'asociaciones/ticonata.jpg',
            ],
        ];

        foreach ($asociaciones as $data) {
            Asociacion::create($data);
        }
    }


    private function createEmprendedores()
    {
        $emprendedores = [
            [
                'nombre' => 'Casa Hospedaje Samary',
                'tipo_servicio' => 'Alojamiento',
                'descripcion' => 'Casa hospedaje familiar que ofrece habitaciones cómodas con vista al lago Titicaca y experiencia de turismo vivencial.',
                'ubicacion' => 'Comunidad Llachón, a 200m del muelle principal',
                'telefono' => '951222333',
                'email' => 'samary.llachon@gmail.com',
                'pagina_web' => 'https://samaryllachon.com',
                'horario_atencion' => 'Todos los días: 7:00 am - 10:00 pm',
                'precio_rango' => 'S/. 50 - S/. 100',
                'metodos_pago' => json_encode(['Efectivo', 'Transferencia', 'Yape']),
                'capacidad_aforo' => 12,
                'numero_personas_atiende' => 3,
                'comentarios_resenas' => 'Excelente servicio, habitaciones limpias y comida deliciosa. Muy recomendado.',
                'imagenes' => json_encode(['samary1.jpg', 'samary2.jpg', 'samary3.jpg']),
                'categoria' => 'Alojamiento',
                'certificaciones' => 'TRC MINCETUR',
                'idiomas_hablados' => 'Español, Inglés básico, Quechua',
                'opciones_acceso' => 'A pie, en bote',
                'facilidades_discapacidad' => true,
                'asociacion_id' => 1,
                'estado' => true
            ],
            [
                'nombre' => 'Restaurante Sumaq Mijuna',
                'tipo_servicio' => 'Alimentación',
                'descripcion' => 'Restaurante que ofrece platos típicos de la región elaborados con productos locales y orgánicos.',
                'ubicacion' => 'Plaza principal de Capachica',
                'telefono' => '954333222',
                'email' => 'sumaqmijuna@gmail.com',
                'pagina_web' => null,
                'horario_atencion' => 'Lunes a Domingo: 8:00 am - 8:00 pm',
                'precio_rango' => 'S/. 15 - S/. 35',
                'metodos_pago' => json_encode(['Efectivo', 'Yape']),
                'capacidad_aforo' => 30,
                'numero_personas_atiende' => 5,
                'comentarios_resenas' => 'La trucha frita es espectacular. Ambiente familiar y precios accesibles.',
                'imagenes' => json_encode(['sumaq1.jpg', 'sumaq2.jpg']),
                'categoria' => 'Alimentación',
                'certificaciones' => 'Restaurante Saludable Municipal',
                'idiomas_hablados' => 'Español, Quechua',
                'opciones_acceso' => 'A pie, transporte público, taxi',
                'facilidades_discapacidad' => false,
                'asociacion_id' => null,
                'estado' => true
            ],
            [
                'nombre' => 'Artesanías Titicaca',
                'tipo_servicio' => 'Artesanía',
                'descripcion' => 'Taller y tienda de artesanía textil que utiliza técnicas ancestrales y tintes naturales.',
                'ubicacion' => 'Comunidad Escallani, a 500m de la plaza',
                'telefono' => '957888999',
                'email' => 'artesanias.titicaca@gmail.com',
                'pagina_web' => null,
                'horario_atencion' => 'Lunes a Sábado: 9:00 am - 6:00 pm',
                'precio_rango' => 'S/. 10 - S/. 200',
                'metodos_pago' => json_encode(['Efectivo', 'Transferencia']),
                'capacidad_aforo' => 10,
                'numero_personas_atiende' => 2,
                'comentarios_resenas' => 'Hermosos trabajos textiles. Ofrecen demostraciones del proceso de tejido.',
                'imagenes' => json_encode(['artesania1.jpg', 'artesania2.jpg']),
                'categoria' => 'Artesanía',
                'certificaciones' => 'Marca Perú',
                'idiomas_hablados' => 'Español, Quechua, Aymara',
                'opciones_acceso' => 'A pie',
                'facilidades_discapacidad' => false,
                'asociacion_id' => 2,
                'estado' => true
            ],
            [
                'nombre' => 'Transportes Lacustres Titicaca',
                'tipo_servicio' => 'Transporte',
                'descripcion' => 'Servicio de transporte en bote para visitar las islas y comunidades del lago Titicaca.',
                'ubicacion' => 'Muelle principal de Capachica',
                'telefono' => '956777888',
                'email' => 'lacustrestiticaca@gmail.com',
                'pagina_web' => 'https://transportestiticaca.com',
                'horario_atencion' => 'Todos los días: 6:00 am - 5:00 pm',
                'precio_rango' => 'S/. 30 - S/. 150',
                'metodos_pago' => json_encode(['Efectivo', 'Transferencia', 'Yape', 'Tarjeta']),
                'capacidad_aforo' => 15,
                'numero_personas_atiende' => 2,
                'comentarios_resenas' => 'Botes en buen estado y guías conocedores. Muy seguro y puntual.',
                'imagenes' => json_encode(['transporte1.jpg', 'transporte2.jpg']),
                'categoria' => 'Transporte',
                'certificaciones' => 'MTC, Capitanía de Puertos',
                'idiomas_hablados' => 'Español, Inglés básico',
                'opciones_acceso' => 'A pie',
                'facilidades_discapacidad' => true,
                'asociacion_id' => null,
                'estado' => true
            ],
            [
                'nombre' => 'Aventuras Titicaca',
                'tipo_servicio' => 'Actividades',
                'descripcion' => 'Empresa que ofrece kayak, bicicleta, trekking y otras actividades al aire libre.',
                'ubicacion' => 'Comunidad Cotos, a 1km del centro',
                'telefono' => '959666777',
                'email' => 'aventuras@titicaca.pe',
                'pagina_web' => 'https://aventurastiticaca.pe',
                'horario_atencion' => 'Lunes a Domingo: 7:00 am - 6:00 pm',
                'precio_rango' => 'S/. 40 - S/. 120',
                'metodos_pago' => json_encode(['Efectivo', 'Transferencia', 'Tarjeta']),
                'capacidad_aforo' => 20,
                'numero_personas_atiende' => 4,
                'comentarios_resenas' => 'Increíble experiencia de kayak al amanecer. Equipos en buen estado y guías profesionales.',
                'imagenes' => json_encode(['aventuras1.jpg', 'aventuras2.jpg', 'aventuras3.jpg']),
                'categoria' => 'Actividades',
                'certificaciones' => 'DIRCETUR, Primeros Auxilios',
                'idiomas_hablados' => 'Español, Inglés, Francés básico',
                'opciones_acceso' => 'A pie, transporte público, taxi',
                'facilidades_discapacidad' => false,
                'asociacion_id' => null,
                'estado' => true
            ]
        ];

        foreach ($emprendedores as $emprendedor) {
            Emprendedor::create($emprendedor);
        }
    }

    private function createServicios()
    {
        $servicios = [
            // Servicios para Casa Hospedaje Samary
            [
                'nombre' => 'Habitación Matrimonial',
                'descripcion' => 'Habitación con cama matrimonial, baño privado y vista al lago.',
                'precio_referencial' => 80.00,
                'emprendedor_id' => 1,
                'latitud' => -15.6428,
                'longitud' => -69.8334,
                'ubicacion_referencia' => 'A 200m del muelle principal de Llachón',
                'categorias' => [1],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'domingo',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ]
                ]
            ],
            [
                'nombre' => 'Habitación Familiar',
                'descripcion' => 'Habitación amplia con una cama matrimonial y dos individuales, baño privado.',
                'precio_referencial' => 120.00,
                'emprendedor_id' => 1,
                'latitud' => -15.6428,
                'longitud' => -69.8334,
                'ubicacion_referencia' => 'A 200m del muelle principal de Llachón',
                'categorias' => [1],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'domingo',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ]
                ]
            ],
            [
                'nombre' => 'Experiencia cultural',
                'descripcion' => 'Participación en actividades tradicionales como agricultura, pesca y cocina local.',
                'precio_referencial' => 50.00,
                'emprendedor_id' => 1,
                'latitud' => -15.6430,
                'longitud' => -69.8336,
                'ubicacion_referencia' => 'En las chacras de la comunidad de Llachón',
                'categorias' => [5],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '16:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '16:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '16:00:00',
                        'activo' => true
                    ]
                ]
            ],

            // Servicios para Restaurante Sumaq Mijuna
            [
                'nombre' => 'Almuerzo típico',
                'descripcion' => 'Incluye sopa, plato principal (trucha o cordero), postre y mate de hierbas.',
                'precio_referencial' => 25.00,
                'emprendedor_id' => 2,
                'latitud' => -15.6420,
                'longitud' => -69.8325,
                'ubicacion_referencia' => 'Plaza principal de Capachica',
                'categorias' => [2],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'domingo',
                        'hora_inicio' => '12:00:00',
                        'hora_fin' => '15:00:00',
                        'activo' => true
                    ]
                ]
            ],
            [
                'nombre' => 'Cena de pachamanca',
                'descripcion' => 'Tradicional pachamanca preparada con piedras calientes. Mínimo 4 personas.',
                'precio_referencial' => 35.00,
                'emprendedor_id' => 2,
                'latitud' => -15.6420,
                'longitud' => -69.8325,
                'ubicacion_referencia' => 'Plaza principal de Capachica',
                'categorias' => [2, 5],
                'horarios' => [
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '18:00:00',
                        'hora_fin' => '21:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '18:00:00',
                        'hora_fin' => '21:00:00',
                        'activo' => true
                    ]
                ]
            ],

            // Servicios para Artesanías Titicaca
            [
                'nombre' => 'Chullo tradicional',
                'descripcion' => 'Gorro tradicional tejido a mano con lana de alpaca y diseños típicos.',
                'precio_referencial' => 45.00,
                'emprendedor_id' => 3,
                'latitud' => -15.6415,
                'longitud' => -69.8340,
                'ubicacion_referencia' => 'Comunidad Escallani, a 500m de la plaza',
                'categorias' => [3],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '18:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '18:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '18:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '18:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '18:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '09:00:00',
                        'hora_fin' => '18:00:00',
                        'activo' => true
                    ]
                ]
            ],
            [
                'nombre' => 'Taller de tejido',
                'descripcion' => 'Taller de 2 horas donde se enseña técnicas básicas de tejido andino.',
                'precio_referencial' => 30.00,
                'emprendedor_id' => 3,
                'latitud' => -15.6415,
                'longitud' => -69.8340,
                'ubicacion_referencia' => 'Comunidad Escallani, a 500m de la plaza',
                'categorias' => [3, 5],
                'horarios' => [
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '10:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '10:00:00',
                        'hora_fin' => '12:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '16:00:00',
                        'activo' => true
                    ]
                ]
            ],

            // Servicios para Transportes Lacustres Titicaca
            [
                'nombre' => 'Tour a Isla Ticonata',
                'descripcion' => 'Viaje en bote a la isla Ticonata con guiado incluido. Duración: 6 horas.',
                'precio_referencial' => 70.00,
                'emprendedor_id' => 4,
                'latitud' => -15.6410,
                'longitud' => -69.8320,
                'ubicacion_referencia' => 'Muelle principal de Capachica',
                'categorias' => [4, 6],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '14:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '14:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '14:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'domingo',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '14:00:00',
                        'activo' => true
                    ]
                ]
            ],
            [
                'nombre' => 'Transporte entre comunidades',
                'descripcion' => 'Servicio de transporte acuático entre las comunidades de la península.',
                'precio_referencial' => 30.00,
                'emprendedor_id' => 4,
                'latitud' => -15.6410,
                'longitud' => -69.8320,
                'ubicacion_referencia' => 'Muelle principal de Capachica',
                'categorias' => [4],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '06:00:00',
                        'hora_fin' => '17:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '06:00:00',
                        'hora_fin' => '17:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '06:00:00',
                        'hora_fin' => '17:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '06:00:00',
                        'hora_fin' => '17:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '06:00:00',
                        'hora_fin' => '17:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '06:00:00',
                        'hora_fin' => '17:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'domingo',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '16:00:00',
                        'activo' => true
                    ]
                ]
            ],

            // Servicios para Aventuras Titicaca
            [
                'nombre' => 'Kayak al amanecer',
                'descripcion' => 'Paseo en kayak para ver el amanecer en el lago Titicaca. Incluye equipamiento y guía.',
                'precio_referencial' => 60.00,
                'emprendedor_id' => 5,
                'latitud' => -15.6405,
                'longitud' => -69.8310,
                'ubicacion_referencia' => 'Comunidad Cotos, a 1km del centro',
                'categorias' => [5],
                'horarios' => [
                    [
                        'dia_semana' => 'lunes',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'miercoles',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'viernes',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'domingo',
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'activo' => true
                    ]
                ]
            ],
            [
                'nombre' => 'Trekking Mirador Capachica',
                'descripcion' => 'Caminata de 4 horas hasta el mirador con vistas de la península y el lago.',
                'precio_referencial' => 40.00,
                'emprendedor_id' => 5,
                'latitud' => -15.6405,
                'longitud' => -69.8310,
                'ubicacion_referencia' => 'Comunidad Cotos, a 1km del centro',
                'categorias' => [5, 6],
                'horarios' => [
                    [
                        'dia_semana' => 'martes',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '13:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'jueves',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '13:00:00',
                        'activo' => true
                    ],
                    [
                        'dia_semana' => 'sabado',
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '13:00:00',
                        'activo' => true
                    ]
                ]
            ]
        ];

        foreach ($servicios as $servicio) {
            $categorias = $servicio['categorias'];
            $horarios = $servicio['horarios'];

            unset($servicio['categorias']);
            unset($servicio['horarios']);

            $nuevoServicio = Servicio::create($servicio);

            // Asociar categorías
            $nuevoServicio->categorias()->attach($categorias);

            // Crear horarios para el servicio
            foreach ($horarios as $horario) {
                $horario['servicio_id'] = $nuevoServicio->id;
                ServicioHorario::create($horario);
            }
        }
    }

    private function createReservas()
    {
        // Crear algunas reservas de ejemplo
        $reservas = [
            [
                'usuario_id' => 2, // Usuario normal
                'codigo_reserva' => 'RES' . date('Ymd') . '001',
                'estado' => 'confirmada',
                'notas' => 'Primera visita a Capachica, muy emocionado por conocer la zona.',
                'servicios' => [
                    [
                        'servicio_id' => 1, // Habitación Matrimonial
                        'emprendedor_id' => 1,
                        'fecha_inicio' => date('Y-m-d', strtotime('+5 days')),
                        'fecha_fin' => date('Y-m-d', strtotime('+7 days')),
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'duracion_minutos' => 1320, // 22 horas
                        'cantidad' => 1,
                        'precio' => 80.00,
                        'estado' => 'confirmado',
                        'notas_cliente' => 'Preferimos una habitación con vista al lago si es posible.'
                    ],
                    [
                        'servicio_id' => 8, // Tour a Isla Ticonata
                        'emprendedor_id' => 4,
                        'fecha_inicio' => date('Y-m-d', strtotime('+6 days')),
                        'fecha_fin' => null,
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '14:00:00',
                        'duracion_minutos' => 360, // 6 horas
                        'cantidad' => 2,
                        'precio' => 140.00,
                        'estado' => 'confirmado',
                        'notas_cliente' => 'Somos dos personas, uno vegetariano.'
                    ]
                ]
            ],
            [
                'usuario_id' => 3, // Usuario emprendedor
                'codigo_reserva' => 'RES' . date('Ymd') . '002',
                'estado' => 'pendiente',
                'notas' => 'Viaje familiar, necesitamos servicios para niños también.',
                'servicios' => [
                    [
                        'servicio_id' => 2, // Habitación Familiar
                        'emprendedor_id' => 1,
                        'fecha_inicio' => date('Y-m-d', strtotime('+10 days')),
                        'fecha_fin' => date('Y-m-d', strtotime('+12 days')),
                        'hora_inicio' => '14:00:00',
                        'hora_fin' => '12:00:00',
                        'duracion_minutos' => 1320, // 22 horas
                        'cantidad' => 1,
                        'precio' => 120.00,
                        'estado' => 'pendiente',
                        'notas_cliente' => 'Familia con dos niños pequeños, necesitamos cuna.'
                    ],
                    [
                        'servicio_id' => 5, // Cena de pachamanca
                        'emprendedor_id' => 2,
                        'fecha_inicio' => date('Y-m-d', strtotime('+11 days')),
                        'fecha_fin' => null,
                        'hora_inicio' => '18:00:00',
                        'hora_fin' => '21:00:00',
                        'duracion_minutos' => 180, // 3 horas
                        'cantidad' => 4,
                        'precio' => 140.00,
                        'estado' => 'pendiente',
                        'notas_cliente' => 'Dos adultos y dos niños. Un adulto no come carne de res.'
                    ],
                    [
                        'servicio_id' => 10, // Kayak al amanecer
                        'emprendedor_id' => 5,
                        'fecha_inicio' => date('Y-m-d', strtotime('+12 days')),
                        'fecha_fin' => null,
                        'hora_inicio' => '05:00:00',
                        'hora_fin' => '08:00:00',
                        'duracion_minutos' => 180, // 3 horas
                        'cantidad' => 2,
                        'precio' => 120.00,
                        'estado' => 'pendiente',
                        'notas_cliente' => 'Solo los adultos participarán en esta actividad.'
                    ]
                ]
            ]
        ];

        foreach ($reservas as $reservaData) {
            $servicios = $reservaData['servicios'];
            unset($reservaData['servicios']);

            $reserva = Reserva::create($reservaData);

            // Crear servicios para la reserva
            foreach ($servicios as $servicioData) {
                $servicioData['reserva_id'] = $reserva->id;
                ReservaServicio::create($servicioData);
            }
        }
    }
}
