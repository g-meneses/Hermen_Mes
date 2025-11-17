<?php
/**
 * Herramienta para generar contraseñas hasheadas
 * 
 * USO:
 * 1. Coloca este archivo en: C:\xampp\htdocs\mes_hermen\
 * 2. Accede desde: http://localhost/mes_hermen/generar_password.php
 * 3. Ingresa la contraseña que quieres hashear
 * 4. Copia el hash generado
 * 5. Úsalo en la base de datos para crear/actualizar usuarios
 * 
 * ⚠️ IMPORTANTE: Elimina este archivo después de usarlo por seguridad
 */

$password_hasheado = '';
$password_ingresado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_ingresado = $_POST['password'] ?? '';
    if (!empty($password_ingresado)) {
        $password_hasheado = password_hash($password_ingresado, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Contraseñas - MES Hermen</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .warning strong {
            color: #856404;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #4a5568;
        }
        
        input[type="text"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Courier New', monospace;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .result {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .result h3 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .copy-btn {
            background: #48bb78;
            padding: 8px 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .instructions {
            background: #e6fffa;
            border-left: 4px solid #38b2ac;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .instructions h3 {
            color: #234e52;
            margin-bottom: 10px;
        }
        
        .instructions ol {
            margin-left: 20px;
        }
        
        .instructions li {
            margin: 5px 0;
            color: #2c7a7b;
        }
        
        code {
            background: #edf2f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: #e53e3e;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Generador de Contraseñas</h1>
        <p style="color: #718096; margin-bottom: 20px;">Sistema MES Hermen Ltda.</p>
        
        <div class="warning">
            <strong>⚠️ ADVERTENCIA DE SEGURIDAD:</strong><br>
            Elimina este archivo después de usarlo. No lo dejes en producción.
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="password">Ingresa la contraseña a hashear:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Ingresa la contraseña...">
            </div>
            
            <button type="submit">Generar Hash</button>
        </form>
        
        <?php if (!empty($password_hasheado)): ?>
        <div class="result">
            <h3>✅ Contraseña hasheada:</h3>
            <textarea id="hashResult" readonly><?php echo $password_hasheado; ?></textarea>
            <button class="copy-btn" onclick="copyHash()">📋 Copiar al portapapeles</button>
        </div>
        
        <div class="instructions">
            <h3>📝 Cómo usar este hash:</h3>
            <ol>
                <li>Copia el hash de arriba</li>
                <li>Ve a phpMyAdmin: <code>http://localhost/phpmyadmin</code></li>
                <li>Selecciona la base de datos <code>mes_hermen</code></li>
                <li>Abre la tabla <code>usuarios</code></li>
                <li>Para crear un usuario nuevo, usa "Insertar" y pega el hash en el campo <code>password</code></li>
                <li>Para actualizar un usuario existente, edita la fila y pega el hash en el campo <code>password</code></li>
            </ol>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
            <strong>Contraseña ingresada:</strong> 
            <code><?php echo htmlspecialchars($password_ingresado); ?></code>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; text-align: center; color: #718096; font-size: 14px;">
            <p>Después de generar tus contraseñas, elimina este archivo:</p>
            <code style="color: #e53e3e;">C:\xampp\htdocs\mes_hermen\generar_password.php</code>
        </div>
    </div>
    
    <script>
        function copyHash() {
            const hashText = document.getElementById('hashResult');
            hashText.select();
            document.execCommand('copy');
            
            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '✅ Copiado!';
            
            setTimeout(() => {
                btn.textContent = originalText;
            }, 2000);
        }
    </script>
</body>
</html>
