<?php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Rotación de Inventario - MES Hermen</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .filters {
            padding: 25px 30px;
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
            min-width: 180px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .content {
            padding: 30px;
            min-height: 400px;
        }

        .loading {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .loading i {
            font-size: 3rem;
            color: #667eea;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: #1a237e;
            color: white;
        }

        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-alta {
            background: #4caf50;
            color: white;
        }

        .badge-media {
            background: #ff9800;
            color: white;
        }

        .badge-baja {
            background: #f44336;
            color: white;
        }

        .badge-sin {
            background: #9e9e9e;
            color: white;
        }

        .alert-warning {
            background: #fff3e0;
            color: #e65100;
            font-weight: 600;
        }

        .periodo-info {
            background: #e3f2fd;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #1565c0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transition: all 0.3s;
            margin-bottom: 15px;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <a href="../inventarios/index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
            <h1><i class="fas fa-sync-alt"></i> Reporte de Rotación de Inventario</h1>
            <p>Análisis de rotación y días de stock por producto</p>
        </div>

        <div class="filters">
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Desde</label>
                <input type="date" id="fechaDesde" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Hasta</label>
                <input type="date" id="fechaHasta" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-layer-group"></i> Tipo Inventario</label>
                <select id="tipoInventario">
                    <option value="">Todos los tipos</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-folder"></i> Categoría</label>
                <select id="categoria">
                    <option value="">Todas las categorías</option>
                </select>
            </div>
            <div class="form-group">
                <button class="btn btn-primary" onclick="generarReporte()">
                    <i class="fas fa-chart-line"></i> Generar Reporte
                </button>
            </div>
        </div>

        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-btn active" onclick="cambiarTab('reporte')">
                    <i class="fas fa-chart-line"></i> Reporte
                </button>
                <button class="tab-btn" onclick="cambiarTab('tutorial')">
                    <i class="fas fa-book"></i> Tutorial de Interpretación
                </button>
            </div>
        </div>

        <div class="content" id="contenido-reporte" style="display: block;">
            <div class="loading">
                <i class="fas fa-chart-bar"></i>
                <p style="margin-top: 20px; font-size: 1.1rem;">Seleccione los filtros y presione "Generar Reporte"</p>
            </div>
        </div>

        <div class="content" id="contenido-tutorial" style="display: none;">
            <div class="tutorial-container">
                <h2 style="color: #1a237e; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-graduation-cap"></i> Guía de Interpretación del Reporte de Rotación
                </h2>

                <!-- Sección 1: ¿Qué es la Rotación? -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-question-circle"></i> ¿Qué es la Rotación de Inventario?</h3>
                    <p>La rotación de inventario es un indicador que mide <strong>cuántas veces se renueva el
                            inventario</strong> en un período determinado. Es fundamental para:</p>
                    <ul>
                        <li>✅ Optimizar niveles de stock</li>
                        <li>✅ Reducir costos de almacenamiento</li>
                        <li>✅ Identificar productos de lento movimiento</li>
                        <li>✅ Mejorar el flujo de caja</li>
                    </ul>
                </div>

                <!-- Sección 2: Métricas del Reporte -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-calculator"></i> Métricas Calculadas</h3>

                    <div class="metric-card">
                        <h4>📊 Inventario Promedio</h4>
                        <p class="formula">Fórmula: (Stock Inicial + Stock Final) / 2</p>
                        <p><strong>Qué significa:</strong> El stock promedio que se mantuvo durante el período
                            analizado.</p>
                        <p><strong>Ejemplo:</strong> Si empezó con 100 kg y terminó con 80 kg, el inventario promedio es
                            90 kg.</p>
                    </div>

                    <div class="metric-card">
                        <h4>📉 Consumo Total</h4>
                        <p class="formula">Suma de todas las salidas en el período</p>
                        <p><strong>Qué significa:</strong> Cuánto producto se utilizó o vendió en el período.</p>
                        <p><strong>Ejemplo:</strong> Si hubo salidas de 20 kg, 30 kg y 15 kg, el consumo total es 65 kg.
                        </p>
                    </div>

                    <div class="metric-card">
                        <h4>🔄 Índice de Rotación</h4>
                        <p class="formula">Fórmula: Consumo Total / Inventario Promedio</p>
                        <p><strong>Qué significa:</strong> Cuántas veces se renovó el inventario.</p>
                        <p><strong>Ejemplo:</strong> Si consumió 65 kg con inventario promedio de 90 kg, la rotación es
                            0.72 (se renovó el 72% del inventario).</p>
                    </div>

                    <div class="metric-card">
                        <h4>📅 Días de Stock</h4>
                        <p class="formula">Fórmula: Días del Período / Rotación</p>
                        <p><strong>Qué significa:</strong> Cuántos días durará el inventario actual al ritmo de consumo
                            actual.</p>
                        <p><strong>Ejemplo:</strong> Si la rotación es 0.72 en 30 días, el stock actual durará 42 días
                            (30/0.72).</p>
                    </div>
                </div>

                <!-- Sección 3: Clasificaciones -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-tags"></i> Clasificación de Rotación</h3>

                    <div class="classification-grid">
                        <div class="classification-card alta">
                            <div class="classification-header">
                                <span class="badge badge-alta">ALTA</span>
                                <span class="classification-value">≥ 2.0</span>
                            </div>
                            <p><strong>Significado:</strong> El producto se renueva 2 o más veces en el período.</p>
                            <p><strong>Interpretación:</strong> ✅ Excelente rotación. Producto de alta demanda.</p>
                            <p><strong>Acción:</strong> Mantener stock adecuado para evitar quiebres.</p>
                        </div>

                        <div class="classification-card media">
                            <div class="classification-header">
                                <span class="badge badge-media">MEDIA</span>
                                <span class="classification-value">0.5 - 2.0</span>
                            </div>
                            <p><strong>Significado:</strong> Rotación normal, entre 50% y 200%.</p>
                            <p><strong>Interpretación:</strong> ✅ Nivel de inventario adecuado.</p>
                            <p><strong>Acción:</strong> Monitorear regularmente. No requiere acción inmediata.</p>
                        </div>

                        <div class="classification-card baja">
                            <div class="classification-header">
                                <span class="badge badge-baja">BAJA</span>
                                <span class="classification-value">
                                    < 0.5</span>
                            </div>
                            <p><strong>Significado:</strong> El producto se mueve lentamente.</p>
                            <p><strong>Interpretación:</strong> ⚠️ Posible exceso de inventario.</p>
                            <p><strong>Acción:</strong> Reducir compras futuras. Evaluar demanda real.</p>
                        </div>

                        <div class="classification-card sin">
                            <div class="classification-header">
                                <span class="badge badge-sin">SIN MOVIMIENTO</span>
                                <span class="classification-value">0</span>
                            </div>
                            <p><strong>Significado:</strong> No hubo salidas en el período.</p>
                            <p><strong>Interpretación:</strong> 🔴 Producto obsoleto o sin demanda.</p>
                            <p><strong>Acción:</strong> Evaluar descontinuar o liquidar inventario.</p>
                        </div>
                    </div>
                </div>

                <!-- Sección 4: Alertas -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-exclamation-triangle"></i> Alertas del Sistema</h3>

                    <div class="alert-info">
                        <div class="alert-icon">⚠️</div>
                        <div>
                            <h4>Días de Stock > 180 días</h4>
                            <p>El inventario actual durará más de 6 meses. Esto indica un posible <strong>exceso de
                                    inventario</strong> que genera costos de almacenamiento innecesarios.</p>
                            <p><strong>Recomendación:</strong> Suspender compras hasta reducir el stock a niveles
                                normales.</p>
                        </div>
                    </div>

                    <div class="alert-info">
                        <div class="alert-icon">🔴</div>
                        <div>
                            <h4>Rotación muy baja con consumo</h4>
                            <p>Hay consumo pero la rotación es menor a 0.1. Esto indica que el <strong>inventario es muy
                                    alto</strong> en relación a la demanda.</p>
                            <p><strong>Recomendación:</strong> Ajustar políticas de compra y reducir cantidades de
                                pedido.</p>
                        </div>
                    </div>
                </div>

                <!-- Sección 5: Ejemplos Prácticos -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-lightbulb"></i> Ejemplos Prácticos</h3>

                    <div class="example-card good">
                        <h4>✅ Ejemplo: Rotación Saludable</h4>
                        <table class="example-table">
                            <tr>
                                <td><strong>Producto:</strong></td>
                                <td>Lycra 20</td>
                            </tr>
                            <tr>
                                <td><strong>Inv. Promedio:</strong></td>
                                <td>30 kg</td>
                            </tr>
                            <tr>
                                <td><strong>Consumo:</strong></td>
                                <td>25 kg</td>
                            </tr>
                            <tr>
                                <td><strong>Rotación:</strong></td>
                                <td>0.83</td>
                            </tr>
                            <tr>
                                <td><strong>Días Stock:</strong></td>
                                <td>36 días</td>
                            </tr>
                        </table>
                        <p class="example-interpretation">
                            <strong>Interpretación:</strong> El producto tiene una rotación MEDIA (0.83). El stock
                            actual durará aproximadamente 36 días (poco más de 1 mes), lo cual es un nivel saludable. No
                            requiere acción correctiva.
                        </p>
                    </div>

                    <div class="example-card warning">
                        <h4>⚠️ Ejemplo: Exceso de Inventario</h4>
                        <table class="example-table">
                            <tr>
                                <td><strong>Producto:</strong></td>
                                <td>Spandex 40</td>
                            </tr>
                            <tr>
                                <td><strong>Inv. Promedio:</strong></td>
                                <td>100 kg</td>
                            </tr>
                            <tr>
                                <td><strong>Consumo:</strong></td>
                                <td>5 kg</td>
                            </tr>
                            <tr>
                                <td><strong>Rotación:</strong></td>
                                <td>0.05</td>
                            </tr>
                            <tr>
                                <td><strong>Días Stock:</strong></td>
                                <td>600 días ⚠️</td>
                            </tr>
                        </table>
                        <p class="example-interpretation">
                            <strong>Interpretación:</strong> Rotación BAJA (0.05) y el stock durará 600 días (casi 2
                            años). Hay un claro exceso de inventario.
                            <strong>Acción requerida:</strong> Suspender compras inmediatamente y evaluar si el producto
                            sigue siendo necesario.
                        </p>
                    </div>

                    <div class="example-card excellent">
                        <h4>🌟 Ejemplo: Alta Rotación</h4>
                        <table class="example-table">
                            <tr>
                                <td><strong>Producto:</strong></td>
                                <td>DTY 150/48</td>
                            </tr>
                            <tr>
                                <td><strong>Inv. Promedio:</strong></td>
                                <td>200 kg</td>
                            </tr>
                            <tr>
                                <td><strong>Consumo:</strong></td>
                                <td>450 kg</td>
                            </tr>
                            <tr>
                                <td><strong>Rotación:</strong></td>
                                <td>2.25</td>
                            </tr>
                            <tr>
                                <td><strong>Días Stock:</strong></td>
                                <td>13 días</td>
                            </tr>
                        </table>
                        <p class="example-interpretation">
                            <strong>Interpretación:</strong> Rotación ALTA (2.25). El inventario se renovó más de 2
                            veces en el mes. El stock actual solo durará 13 días.
                            <strong>Acción requerida:</strong> Asegurar reabastecimiento frecuente para evitar quiebres
                            de stock.
                        </p>
                    </div>
                </div>

                <!-- Sección 6: Mejores Prácticas -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-star"></i> Mejores Prácticas</h3>
                    <div class="best-practices">
                        <div class="practice-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Revisar mensualmente:</strong> Genere este reporte cada mes para identificar
                                tendencias.
                            </div>
                        </div>
                        <div class="practice-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Comparar períodos:</strong> Compare la rotación actual con meses anteriores.
                            </div>
                        </div>
                        <div class="practice-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Actuar sobre alertas:</strong> Priorice productos con días de stock > 180.
                            </div>
                        </div>
                        <div class="practice-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Ajustar compras:</strong> Use la rotación para definir cantidades de pedido.
                            </div>
                        </div>
                        <div class="practice-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Meta ideal:</strong> Busque mantener la mayoría de productos en rotación MEDIA o
                                ALTA.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sección 7: Preguntas Frecuentes -->
                <div class="tutorial-section">
                    <h3><i class="fas fa-question"></i> Preguntas Frecuentes</h3>

                    <div class="faq-item">
                        <h4>❓ ¿Qué período debo analizar?</h4>
                        <p>Se recomienda analizar períodos de 30 días (1 mes) para obtener datos representativos.
                            Períodos muy cortos pueden dar resultados engañosos.</p>
                    </div>

                    <div class="faq-item">
                        <h4>❓ ¿Una rotación alta siempre es buena?</h4>
                        <p>Generalmente sí, pero una rotación extremadamente alta (>5) puede indicar que el stock es
                            insuficiente y podría haber quiebres. Lo ideal es mantener un balance.</p>
                    </div>

                    <div class="faq-item">
                        <h4>❓ ¿Qué hago con productos sin movimiento?</h4>
                        <p>Evalúe si el producto sigue siendo necesario. Considere: liquidar inventario, descontinuar el
                            producto, o verificar si hay demanda estacional.</p>
                    </div>

                    <div class="faq-item">
                        <h4>❓ ¿Cómo mejoro la rotación de un producto?</h4>
                        <p>Opciones: reducir el stock mantenido, aumentar las ventas/uso, mejorar la planificación de
                            compras, o considerar descontinuar si no es estratégico.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .tabs {
            background: #f5f5f5;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-buttons {
            display: flex;
            padding: 0 30px;
        }

        .tab-btn {
            padding: 15px 30px;
            border: none;
            background: transparent;
            color: #666;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: #1a237e;
            background: rgba(26, 35, 126, 0.05);
        }

        .tab-btn.active {
            color: #1a237e;
            border-bottom-color: #667eea;
            background: white;
        }

        .tutorial-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .tutorial-section {
            margin-bottom: 40px;
            padding: 25px;
            background: #f9f9f9;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .tutorial-section h3 {
            color: #1a237e;
            margin-bottom: 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tutorial-section ul {
            margin-left: 20px;
            line-height: 1.8;
        }

        .metric-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #4caf50;
        }

        .metric-card h4 {
            color: #2e7d32;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .formula {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #1b5e20;
            margin: 10px 0;
        }

        .classification-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .classification-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }

        .classification-card.alta {
            border-left: 4px solid #4caf50;
        }

        .classification-card.media {
            border-left: 4px solid #ff9800;
        }

        .classification-card.baja {
            border-left: 4px solid #f44336;
        }

        .classification-card.sin {
            border-left: 4px solid #9e9e9e;
        }

        .classification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .classification-value {
            font-weight: 700;
            font-size: 1.2rem;
            color: #333;
        }

        .classification-card p {
            margin: 8px 0;
            line-height: 1.6;
        }

        .alert-info {
            background: #fff3e0;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            gap: 15px;
            border-left: 4px solid #ff9800;
        }

        .alert-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }

        .alert-info h4 {
            color: #e65100;
            margin-bottom: 10px;
        }

        .example-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }

        .example-card.good {
            border-left: 4px solid #4caf50;
        }

        .example-card.warning {
            border-left: 4px solid #ff9800;
        }

        .example-card.excellent {
            border-left: 4px solid #2196f3;
        }

        .example-card h4 {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .example-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }

        .example-table td {
            padding: 8px;
            border-bottom: 1px solid #f0f0f0;
        }

        .example-table td:first-child {
            width: 40%;
            color: #666;
        }

        .example-interpretation {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            line-height: 1.6;
        }

        .best-practices {
            background: white;
            padding: 20px;
            border-radius: 8px;
        }

        .practice-item {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: flex-start;
        }

        .practice-item i {
            color: #4caf50;
            font-size: 1.3rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .faq-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #2196f3;
        }

        .faq-item h4 {
            color: #1565c0;
            margin-bottom: 10px;
        }

        .faq-item p {
            line-height: 1.6;
            color: #555;
        }
    </style>

    <script>
        // IIFE para evitar contaminar el scope global
        (function () {
            const baseUrl = '<?php echo SITE_URL; ?>';

            // Cambiar entre tabs
            window.cambiarTab = function (tab) {
                // Actualizar botones
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                event.target.closest('.tab-btn').classList.add('active');

                // Mostrar/ocultar contenido
                if (tab === 'reporte') {
                    document.getElementById('contenido-reporte').style.display = 'block';
                    document.getElementById('contenido-tutorial').style.display = 'none';
                } else {
                    document.getElementById('contenido-reporte').style.display = 'none';
                    document.getElementById('contenido-tutorial').style.display = 'block';
                }
            };

            // Cargar tipos de inventario
            async function cargarTipos() {
                try {
                    const response = await fetch(`${baseUrl}/api/categorias.php?action=get_tipos`);
                    const data = await response.json();
                    if (data.success) {
                        const select = document.getElementById('tipoInventario');
                        select.innerHTML = '<option value="">Todos los tipos</option>' +
                            data.tipos.map(t => `<option value="${t.id_tipo_inventario}">${t.nombre}</option>`).join('');
                    }
                } catch (e) {
                    console.error('Error cargando tipos:', e);
                }
            }

            // Cargar categorías según tipo
            document.getElementById('tipoInventario').addEventListener('change', async function () {
                const tipoId = this.value;
                const selectCat = document.getElementById('categoria');

                if (!tipoId) {
                    selectCat.innerHTML = '<option value="">Todas las categorías</option>';
                    return;
                }

                try {
                    const response = await fetch(`${baseUrl}/api/categorias.php?action=get_categorias&id_tipo=${tipoId}`);
                    const data = await response.json();
                    if (data.success) {
                        selectCat.innerHTML = '<option value="">Todas las categorías</option>' +
                            data.categorias.map(c => `<option value="${c.id_categoria}">${c.nombre}</option>`).join('');
                    }
                } catch (e) {
                    console.error('Error cargando categorías:', e);
                }
            });

            // Generar reporte
            window.generarReporte = async function () {
                const desde = document.getElementById('fechaDesde').value;
                const hasta = document.getElementById('fechaHasta').value;
                const tipoId = document.getElementById('tipoInventario').value;
                const catId = document.getElementById('categoria').value;

                const contenido = document.getElementById('contenido-reporte');
                contenido.innerHTML = `
                    <div class="loading">
                        <i class="fas fa-spinner"></i>
                        <p style="margin-top: 20px; font-size: 1.1rem;">Generando reporte...</p>
                    </div>
                `;

                try {
                    let url = `${baseUrl}/api/reportes_mp.php?action=rotacion&desde=${desde}&hasta=${hasta}`;
                    if (tipoId) url += `&id_tipo=${tipoId}`;
                    if (catId) url += `&id_categoria=${catId}`;

                    const response = await fetch(url);
                    const data = await response.json();

                    if (data.success) {
                        renderReporte(data);
                    } else {
                        contenido.innerHTML = `<p style="color:red; text-align:center; padding:40px;">${data.message}</p>`;
                    }
                } catch (e) {
                    console.error(e);
                    contenido.innerHTML = `<p style="color:red; text-align:center; padding:40px;">Error de conexión con el servidor</p>`;
                }
            };

            // Renderizar reporte
            function renderReporte(data) {
                const contenido = document.getElementById('contenido-reporte');

                const getColorClasificacion = (clasificacion) => {
                    switch (clasificacion) {
                        case 'ALTA': return '#4caf50';
                        case 'MEDIA': return '#ff9800';
                        case 'BAJA': return '#f44336';
                        case 'SIN_MOVIMIENTO': return '#9e9e9e';
                        default: return '#666';
                    }
                };

                const getBadgeClass = (clasificacion) => {
                    switch (clasificacion) {
                        case 'ALTA': return 'badge-alta';
                        case 'MEDIA': return 'badge-media';
                        case 'BAJA': return 'badge-baja';
                        case 'SIN_MOVIMIENTO': return 'badge-sin';
                        default: return '';
                    }
                };

                const formatNum = (num, decimals = 2) => {
                    return parseFloat(num).toLocaleString('es-BO', {
                        minimumFractionDigits: decimals,
                        maximumFractionDigits: decimals
                    });
                };

                let html = `
                    <div class="periodo-info">
                        <i class="fas fa-calendar-alt"></i>
                        Período: ${new Date(data.periodo.desde).toLocaleDateString('es-BO')} - ${new Date(data.periodo.hasta).toLocaleDateString('es-BO')} (${data.periodo.dias} días)
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th style="text-align:right;">Inv. Promedio</th>
                                <th style="text-align:right;">Consumo</th>
                                <th style="text-align:right;">Rotación</th>
                                <th style="text-align:center;">Días Stock</th>
                                <th style="text-align:center;">Clasificación</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                data.data.forEach(row => {
                    const alertaDias = row.dias_stock > 180 ? ' ⚠️' : '';
                    const alertaRotacion = row.rotacion < 0.1 && row.salidas > 0 ? ' 🔴' : '';
                    const diasClass = row.dias_stock > 180 ? 'alert-warning' : '';

                    html += `
                        <tr>
                            <td>
                                <div style="font-weight:600;">${row.nombre}</div>
                                <div style="font-size:0.85rem; color:#666;">${row.codigo} | ${row.categoria}</div>
                            </td>
                            <td style="text-align:right; font-weight:600;">
                                ${formatNum(row.inventario_promedio, 2)} ${row.unidad}
                            </td>
                            <td style="text-align:right; ${row.salidas > 0 ? 'color:#2e7d32; font-weight:600;' : 'color:#999;'}">
                                ${formatNum(row.salidas, 2)} ${row.unidad}
                            </td>
                            <td style="text-align:right; font-weight:700; color:${getColorClasificacion(row.clasificacion)};">
                                ${formatNum(row.rotacion, 2)}${alertaRotacion}
                            </td>
                            <td style="text-align:center; font-weight:600;" class="${diasClass}">
                                ${row.dias_stock >= 999 ? '∞' : row.dias_stock + ' días'}${alertaDias}
                            </td>
                            <td style="text-align:center;">
                                <span class="badge ${getBadgeClass(row.clasificacion)}">
                                    ${row.clasificacion.replace('_', ' ')}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                        <tfoot>
                            <tr style="background:#f5f5f5;">
                                <td colspan="6" style="padding:15px; text-align:center; color:#666; font-weight:600;">
                                    <i class="fas fa-info-circle"></i> 
                                    Rotación Alta: ≥2 | Media: 0.5-2 | Baja: <0.5 | Sin Movimiento: 0
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                `;

                contenido.innerHTML = html;
            }

            // Inicializar
            document.addEventListener('DOMContentLoaded', function () {
                // Establecer fechas por defecto
                const hoy = new Date();
                const hace30dias = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);

                document.getElementById('fechaHasta').value = hoy.toISOString().split('T')[0];
                document.getElementById('fechaDesde').value = hace30dias.toISOString().split('T')[0];

                // Cargar tipos
                cargarTipos();
            });
        })();
    </script>

<?php require_once '../../includes/footer.php'; ?>
