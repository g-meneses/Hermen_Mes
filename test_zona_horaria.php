<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Test de Zona Horaria - Bolivia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }

        .test-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        .result {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }

        .info {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <h1>🕐 Test de Zona Horaria - Bolivia (UTC-4)</h1>

    <div class="test-box">
        <h2>1. Fecha/Hora del Navegador</h2>
        <div class="result" id="fechaNavegador"></div>
        <div class="info" id="offsetNavegador"></div>
    </div>

    <div class="test-box">
        <h2>2. Fecha/Hora en Bolivia (UTC-4)</h2>
        <div class="result" id="fechaBolivia"></div>
        <div class="info">Calculada con offset UTC-4</div>
    </div>

    <div class="test-box">
        <h2>3. Fecha para Input (YYYY-MM-DD)</h2>
        <div class="result" id="fechaInput"></div>
        <input type="date" id="testInput" style="font-size: 1.2rem; padding: 10px; margin-top: 10px;">
    </div>

    <div class="test-box">
        <h2>4. Fecha/Hora del Servidor PHP</h2>
        <div class="result">
            <?php
            date_default_timezone_set('America/La_Paz');
            echo date('Y-m-d H:i:s');
            ?>
        </div>
        <div class="info">Zona horaria:
            <?php echo date_default_timezone_get(); ?>
        </div>
    </div>

    <script>
        function getFechaBolivia() {
            // El navegador ya está en Bolivia (UTC-4), usar fecha local directamente
            return new Date();
        }
        
        function formatearFecha(fecha) {
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            const hora = String(fecha.getHours()).padStart(2, '0');
            const min = String(fecha.getMinutes()).padStart(2, '0');
            const seg = String(fecha.getSeconds()).padStart(2, '0');
            
            return `${año}-${mes}-${dia} ${hora}:${min}:${seg}`;
        }
        
        function formatearFechaInput(fecha) {
            const año = fecha.getFullYear();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }
        
        // Actualizar cada segundo
        function actualizar() {
            const ahora = new Date();
            const fechaBolivia = getFechaBolivia();
            
            // Fecha del navegador
            document.getElementById('fechaNavegador').textContent = formatearFecha(ahora);
            document.getElementById('offsetNavegador').textContent = 
                `Offset: ${ahora.getTimezoneOffset()} minutos (UTC${ahora.getTimezoneOffset() > 0 ? '-' : '+'}${Math.abs(ahora.getTimezoneOffset() / 60)})`;
            
            // Fecha Bolivia (es la misma que la local)
            document.getElementById('fechaBolivia').textContent = formatearFecha(fechaBolivia);
            
            // Fecha para input
            const fechaInput = formatearFechaInput(fechaBolivia);
            document.getElementById('fechaInput').textContent = fechaInput;
            document.getElementById('testInput').value = fechaInput;
            document.getElementById('testInput').max = fechaInput;
        }
        
        actualizar();
        setInterval(actualizar, 1000);
    </script>
</body>

</html>