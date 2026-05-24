<?php
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