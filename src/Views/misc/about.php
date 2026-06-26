<?php
/**
 * Vista de la página "Acerca de" / "Quiénes somos" de Tinkuy.
 * Página estática que presenta la misión, visión y valores de la empresa,
 * junto con información del equipo fundador y la historia de la marca.
 * No requiere autenticación ni datos dinámicos de BD.
 *
 * Variables esperadas (disponibles por haber sido incluido desde public/index.php):
 *   $base_url  (string) - URL base del proyecto para construir enlaces de navegación y assets
 */
// src/Views/about.php
// Esta Vista es llamada por public/index.php, por lo que BASE_PATH ya existe.

// Definimos la ruta base para todos los enlaces HTML (css, js, img)
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
$base_url = defined('PUBLIC_URL') ? PUBLIC_URL : ($project_root . "/public");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nosotros | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css"> 
</head>

<body>
    <?php 
    // Define la página actual para que el navbar la resalte
    $pagina_actual = 'nosotros';
    // RUTA NAVBAR CORREGIDA
    include BASE_PATH . '/src/Views/components/navbar.php'; 
    ?>

    <section class="container mt-5">
        <div class="bg-dark text-white p-5 rounded shadow">
            <h1 class="text-center mb-4">Sobre Tinkuy</h1>
            <p class="lead text-center mb-5">
                Tinkuy nació con el sueño de ofrecer productos artesanales hechos con amor y dedicación, utilizando
                lana de alpaca bebé de la más alta calidad. Trabajamos directamente con comunidades de artesanas rurales
                y mujeres de la cárcel de Jauja, brindándoles la oportunidad de generar ingresos dignos mientras reviven
                técnicas ancestrales de tejido.
            </p>
            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <img src="<?= $project_root ?>/public/img/muchastelas.jpeg" alt="Artesanas trabajando" class="img-fluid rounded shadow">
                </div>
                <div class="col-md-6">
                    <h3 class="text-uppercase">Nuestra Historia</h3>
                    <p>
                        En Tinkuy, nos inspiramos en el arte ancestral de los pueblos andinos, donde cada prenda tiene
                        una historia que contar. Comenzamos con un pequeño grupo de mujeres de la cárcel de Jauja, a
                        quienes les enseñamos las técnicas de tejido con lana de alpaca bebé, un material suave y de gran
                        calidad. Con el tiempo, nuestro trabajo fue creciendo, integrando más comunidades rurales de
                        diferentes regiones de Perú.
                        Hoy en día, somos una red de apoyo que proporciona empleo y nuevas oportunidades para cientos de
                        mujeres.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="container my-5">
        <div class="row">
            <div class="col-md-6 mb-4 mb-md-0">
                <h3 class="text-uppercase">Nuestra Misión</h3>
                <p>
                    Nuestra misión es generar un impacto positivo en las comunidades peruanas, especialmente en aquellas
                    más vulnerables. Queremos ser un puente entre la tradición artesanal y el mercado global, promoviendo
                    la sostenibilidad, la inclusión social y el comercio justo. Cada prenda que elaboramos representa el
                    esfuerzo, la pasión y la dedicación de las personas que las crean.
                </p>
            </div>
            <div class="col-md-6">
                <h3 class="text-uppercase">Nuestra Visión</h3>
                <p>
                    Ser reconocidos como una marca líder en productos artesanales de calidad, ofreciendo al mundo la
                    posibilidad de adquirir artículos únicos que cuenten una historia de transformación social y
                    cultural. Queremos seguir creciendo para ofrecer más oportunidades a más artesanas y hacer de Tinkuy un
                    referente de ética empresarial.
                </p>
            </div>
        </div>
    </section>

    <section class="container my-5">
        <h3 class="text-center mb-4">¿Cómo Trabajamos?</h3>
        <div class="row align-items-center">
            <div class="col-md-6 mb-4 mb-md-0">
                <img src="<?= $project_root ?>/public/img/trabajadores.jpeg" alt="Proceso de trabajo artesanal" class="img-fluid rounded shadow">
            </div>
            <div class="col-md-6">
                <p>
                    En Tinkuy, nuestro proceso de trabajo está basado en principios de respeto, sostenibilidad y
                    justicia social. Colaboramos con artesanas de diversas regiones del Perú, brindándoles materiales de
                    alta calidad y herramientas para que puedan crear piezas únicas y auténticas. A través de nuestro
                    modelo de comercio justo, las mujeres reciben un pago justo por su trabajo, lo que les permite
                    mejorar la calidad de vida de sus familias.
                </p>
                <p>
                    Además, trabajamos con técnicas ancestrales de tejido, lo que le da a cada prenda un toque único que
                    la convierte en una pieza especial. Desde el diseño hasta la entrega, cada producto refleja nuestra
                    pasión por la artesanía peruana.
                </p>
            </div>
        </div>
    </section>

    <?php 
    // RUTA FOOTER CORREGIDA
    include BASE_PATH . '/src/Views/components/footer.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>