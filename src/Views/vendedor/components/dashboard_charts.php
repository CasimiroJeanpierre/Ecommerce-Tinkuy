<?php
/**
 * Componente de gráficos del dashboard del vendedor.
 * Renderiza un gráfico de líneas Chart.js mostrando la evolución de ingresos
 * de los últimos 7 días. Los datos se inyectan como JSON en variables JavaScript
 * para ser consumidos por Chart.js sin llamadas AJAX adicionales.
 *
 * Variables esperadas desde el scope del dashboard (src/Views/vendedor/dashboard.php):
 *   $json_labels (string) - JSON array de etiquetas de fecha para el eje X (p.ej. ["19/6","20/6",...])
 *   $json_data   (string) - JSON array de totales de ventas diarias para el eje Y (float[])
 */
// Vista parcial para los gráficos del dashboard vendedor
?>
<div class="row mb-4">
    <!-- Gráfico de Ventas -->
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title">Ventas Últimos 30 Días</h5>
                </div>
                <canvas id="graficoVentas"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico de ventas (datos pasados desde PHP)
const ctx = document.getElementById('graficoVentas').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [{
            label: 'Ventas',
            data: <?= json_encode($ventas_diarias) ?>,
            fill: true,
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [2]
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});