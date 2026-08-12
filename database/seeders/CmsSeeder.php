<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Banner;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contenido inicial del CMS corporativo (REQ-08).
 *
 * Imágenes y títulos de artículo provienen del sitio actual. Los cuerpos de
 * texto están redactados sobre esos mismos temas: los artículos originales son
 * PDFs incrustados, sin texto recuperable. Se reemplazan con el contenido real
 * que entregue ICCE.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBanners();
        $this->seedProjects();
        $this->seedPosts();
    }

    private function seedBanners(): void
    {
        $banners = [
            [
                'eyebrow' => 'Distribuidor autorizado en México',
                'title' => 'Reglas láser Somero para pisos superplanos',
                'subtitle' => 'Nivelación de losas industriales con tolerancias F-number certificadas. Venta y renta con cobertura nacional.',
                'image_path' => 'images/proyectos/Distribuidor-Somero-Mexico.jpg',
                'cta_label' => 'Ver equipos en renta',
                'cta_url' => '/renta/reglas-laser-somero',
                'secondary_cta_label' => 'Solicitar cotización',
                'secondary_cta_url' => '/contacto',
            ],
            [
                'eyebrow' => 'Herramienta profesional',
                'title' => 'Kraft Tool: acabado que aguanta la obra',
                'subtitle' => 'Llanas, flotas, jaladores y aditamentos para colado y acabado de concreto. Existencia inmediata en almacén.',
                'image_path' => 'images/proyectos/KraftTool.jpg',
                'cta_label' => 'Ir al catálogo',
                'cta_url' => '/catalogo/herramientas-para-concreto',
                'secondary_cta_label' => 'Ver marcas',
                'secondary_cta_url' => '/marcas',
            ],
            [
                'eyebrow' => 'Servicio especializado',
                'title' => 'Medición de planicidad y nivelación',
                'subtitle' => 'Verificación de F-numbers en piso terminado con instrumentación Face Construction Technologies.',
                'image_path' => 'images/proyectos/MedicionDePlanicidad.jpg',
                'cta_label' => 'Conocer el servicio',
                'cta_url' => '/servicios',
                'secondary_cta_label' => 'Casos de éxito',
                'secondary_cta_url' => '/proyectos',
            ],
        ];

        foreach ($banners as $order => $banner) {
            Banner::updateOrCreate(
                ['title' => $banner['title']],
                [...$banner, 'is_active' => true, 'sort_order' => $order],
            );
        }
    }

    private function seedProjects(): void
    {
        $projects = [
            [
                'title' => 'Centro de distribución con pasillo angosto (VNA)',
                'client' => 'Operador logístico nacional',
                'location' => 'Apodaca, Nuevo León',
                'year' => 2024,
                'area_m2' => 32000,
                'summary' => 'Losa superplana para racks de pasillo muy angosto, con tolerancias de planicidad definidas por el fabricante del montacargas.',
                'cover_image' => 'images/proyectos/VNA.jpg',
                'services' => ['Piso superplano VNA', 'Regla láser Somero S-940', 'Medición de F-numbers'],
            ],
            [
                'title' => 'Piso industrial para nave de almacenamiento',
                'client' => 'Grupo industrial regiomontano',
                'location' => 'Santa Catarina, Nuevo León',
                'year' => 2024,
                'area_m2' => 18500,
                'summary' => 'Colado continuo con refuerzo de fibras de acero y juntas de contracción con transferencia de carga Diamond Dowel.',
                'cover_image' => 'images/proyectos/Almacen.jpg',
                'services' => ['Fibras Dramix', 'Diamond Dowel', 'Sellado de juntas'],
            ],
            [
                'title' => 'Piso de concreto pulido en planta de manufactura',
                'client' => 'Manufactura automotriz',
                'location' => 'Silao, Guanajuato',
                'year' => 2023,
                'area_m2' => 9800,
                'summary' => 'Desbaste, densificado y abrillantado de losa existente para lograr acabado pulido de alto tránsito.',
                'cover_image' => 'images/proyectos/Polishing.jpg',
                'services' => ['Desbaste Husqvarna', 'Densificado', 'Abrillantado'],
            ],
            [
                'title' => 'Reparación rápida de losa en operación',
                'client' => 'Centro de distribución retail',
                'location' => 'Monterrey, Nuevo León',
                'year' => 2023,
                'area_m2' => 1200,
                'summary' => 'Reparación de juntas y bacheo con morteros CTS Rapid Set para devolver la nave a operación en menos de 24 horas.',
                'cover_image' => 'images/proyectos/CTS_RapidSet.jpg',
                'services' => ['CTS Rapid Set', 'Reparación de juntas', 'Trabajo nocturno'],
            ],
            [
                'title' => 'Losa de piso para planta de fabricación',
                'client' => 'Fabricante de equipo industrial',
                'location' => 'Ramos Arizpe, Coahuila',
                'year' => 2022,
                'area_m2' => 24000,
                'summary' => 'Colado con regla láser y curado con membrana, coordinado con el avance de montaje estructural.',
                'cover_image' => 'images/proyectos/Fabricacion.jpg',
                'services' => ['Regla láser Somero S-240', 'Membrana de curado', 'Barrera de vapor'],
            ],
        ];

        foreach ($projects as $order => $project) {
            Project::updateOrCreate(
                ['slug' => Str::slug($project['title'])],
                [...$project, 'is_featured' => $order < 3, 'sort_order' => $order],
            );
        }
    }

    private function seedPosts(): void
    {
        $author = User::where('role', UserRole::Admin)->first();

        $posts = [
            [
                'title' => 'Diseño y construcción de pisos de concreto reforzados con fibras de acero',
                'topic' => 'Diseño de pisos',
                'excerpt' => 'Cuándo conviene sustituir la malla electrosoldada por fibra de acero, cómo se dosifica y qué esperar del comportamiento a flexión de la losa.',
                'cover_image' => 'images/proyectos/Almacen.jpg',
                'reading_minutes' => 8,
                'days_ago' => 12,
                'featured' => true,
                'body' => <<<'MD'
                La fibra de acero trabaja repartida en todo el espesor de la losa, no en un solo plano
                como la malla electrosoldada. Esa diferencia cambia el modo de falla: en vez de una
                grieta que se abre libremente hasta encontrar el acero, la fibra cose la fisura desde
                que aparece y limita su ancho.

                ## Cuándo conviene
                En pisos industriales con cargas distribuidas y tráfico de montacargas, la fibra
                permite aumentar la separación entre juntas y eliminar el armado de reparto. En losas
                con cargas puntuales muy altas —racks selectivos de gran altura— sigue siendo
                necesario revisar el punzonamiento bajo placa base.

                ## Dosificación
                La dosis se define por el momento resistente residual que exige el diseño, no por
                costumbre. Dosis típicas van de 20 a 45 kg/m³ según el tipo de fibra y la geometría
                del gancho. Una fibra con anclaje mecánico rinde más a igual peso que una fibra recta.

                ## Colado y acabado
                La fibra no debe aflorar en la superficie. Se controla con la secuencia de flotado y
                con el momento de entrada de la allanadora: entrar tarde arrastra fibra hacia arriba.
                MD,
            ],
            [
                'title' => 'Very Narrow Aisle: por qué la planicidad define la altura de tu rack',
                'topic' => 'Pisos superplanos',
                'excerpt' => 'En almacenes VNA el piso deja de ser un acabado y se vuelve parte del equipo. Qué tolerancias pide el montacargas y cómo se verifican.',
                'cover_image' => 'images/proyectos/VNA.jpg',
                'reading_minutes' => 7,
                'days_ago' => 26,
                'featured' => true,
                'body' => <<<'MD'
                En un pasillo muy angosto el montacargas trilateral se eleva 12 metros o más sin
                estabilizadores. Cualquier desnivel en las ruedas se amplifica en la punta del mástil:
                un milímetro abajo se convierte en centímetros arriba.

                ## Tolerancias definidas, no genéricas
                Los F-numbers de losa libre (FF/FL) describen bien un piso convencional, pero en VNA
                la tolerancia se mide a lo largo de las líneas de rodada definidas por el fabricante
                del equipo. Cada marca publica su propia especificación, y el proveedor del montacargas
                debe entregarla antes de colar, no después.

                ## Cómo se logra
                Colado con regla láser, franjas orientadas en el sentido del pasillo y control
                topográfico continuo durante el colado. Corregir después es desbastar, y desbastar
                un piso terminado cuesta varias veces lo que cuesta colarlo bien.

                ## Verificación
                La medición se hace con perfilómetro sobre las líneas de rodada reales, con el
                reporte entregado antes de instalar los racks.
                MD,
            ],
            [
                'title' => 'Preparación, aplicación y funcionamiento de los productos CTS Rapid Set',
                'topic' => 'Reparación',
                'excerpt' => 'Cemento de aluminato de calcio: fraguado en minutos, resistencia de servicio en una hora. Qué cambia respecto a un mortero portland.',
                'cover_image' => 'images/proyectos/CTS_RapidSet.jpg',
                'reading_minutes' => 6,
                'days_ago' => 41,
                'featured' => false,
                'body' => <<<'MD'
                Rapid Set no es un portland acelerado: es un cemento distinto, basado en sulfoaluminato
                de calcio. Eso explica su comportamiento y también los errores más comunes al usarlo.

                ## Tiempo de trabajo real
                Se tienen entre 10 y 15 minutos desde el contacto con el agua. No se puede reamasar:
                una vez iniciado el fraguado, agregar agua destruye la resistencia. Se mezcla solo la
                cantidad que se puede colocar en ese lapso.

                ## Preparación del sustrato
                Sustrato sano, rugoso y saturado superficialmente seco. La superficie debe estar
                húmeda pero sin agua libre encharcada, que diluye la interfaz.

                ## Curado
                Aunque alcance resistencia rápido, necesita curado húmedo durante la primera hora.
                Es el error más frecuente: se abre a tránsito y se deja secar, y aparecen fisuras
                por contracción plástica.

                ## Cuándo usarlo
                Reparaciones en naves en operación, juntas dañadas y bacheo nocturno donde la ventana
                de cierre es de horas, no de días.
                MD,
            ],
            [
                'title' => 'Juntas de control: separación, corte y sellado',
                'topic' => 'Juntas',
                'excerpt' => 'La mayoría de las fisuras en piso industrial no son un problema de concreto, sino de cuándo y a qué profundidad se cortó la junta.',
                'cover_image' => 'images/proyectos/Fabricacion.jpg',
                'reading_minutes' => 5,
                'days_ago' => 58,
                'featured' => false,
                'body' => <<<'MD'
                Una junta de control induce la grieta donde uno decide, en vez de dejar que aparezca
                donde el concreto quiera. Para que funcione tiene que cortarse a tiempo y a la
                profundidad correcta.

                ## Profundidad
                Un cuarto del espesor de la losa es el mínimo habitual. Si el corte es más somero, la
                sección debilitada no gobierna y la grieta se va por otro lado.

                ## Ventana de corte
                Con disco convencional, entre 4 y 12 horas según temperatura. Con equipo de corte
                temprano se puede entrar a la hora o dos. Cortar tarde es cortar una grieta que ya
                ocurrió.

                ## Sellado
                El sellador de junta en piso industrial no impermeabiliza: soporta el borde contra el
                impacto de las ruedas. Por eso se usa material semirrígido de alta dureza, no un
                sellador elástico de fachada.
                MD,
            ],
            [
                'title' => 'Barrera de vapor bajo losa: cuándo es obligatoria',
                'topic' => 'Diseño de pisos',
                'excerpt' => 'Si sobre el piso va a ir un recubrimiento, la humedad que sube del terreno decide si ese recubrimiento dura o se despega.',
                'cover_image' => 'images/proyectos/epoxi.jpg',
                'reading_minutes' => 5,
                'days_ago' => 74,
                'featured' => false,
                'body' => <<<'MD'
                La losa en contacto con el terreno transmite vapor hacia arriba de forma continua. Sin
                barrera, ese vapor se acumula bajo cualquier recubrimiento poco permeable —epóxico,
                poliuretano, vinilo— y termina despegándolo.

                ## Espesor y colocación
                Membrana de al menos 15 mils, traslapes sellados y penetraciones detalladas. El daño
                durante el armado es la causa más común de falla: una barrera perforada no es barrera.

                ## Con o sin capa de arena
                Colocar la losa directamente sobre la membrana reduce el riesgo de curling
                diferencial. La capa de arena intermedia atrapa agua y alarga el tiempo de secado.

                ## Medición previa al recubrimiento
                Antes de aplicar cualquier sistema, medir humedad relativa interna de la losa según
                ASTM F2170. Es una prueba de días, y conviene planearla en el programa de obra.
                MD,
            ],
        ];

        foreach ($posts as $post) {
            Post::updateOrCreate(
                ['slug' => Str::slug($post['title'])],
                [
                    'author_id' => $author?->id,
                    'title' => $post['title'],
                    'topic' => $post['topic'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'cover_image' => $post['cover_image'],
                    'reading_minutes' => $post['reading_minutes'],
                    'is_featured' => $post['featured'],
                    'published_at' => now()->subDays($post['days_ago']),
                    'meta_title' => $post['title'].' | Blog técnico ICCE',
                    'meta_description' => Str::limit($post['excerpt'], 155),
                ],
            );
        }
    }
}
