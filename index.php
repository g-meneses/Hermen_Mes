<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MES Hermen Ltda.</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="assets/img/logo.png" alt="Hermen Logo" class="logo" onerror="this.style.display='none'">
                <h1>MES Hermen Ltda.</h1>
                <p>Sistema de Gestión de Producción</p>
            </div>
            
            <form id="loginForm" class="login-form">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-login">Iniciar Sesión</button>
                </div>
                
                <div id="error-message" class="error-message" style="display: none;"></div>
            </form>
            
            <div class="login-footer">
                <p>Línea de Producción: <strong>Poliamida</strong></p>
                <p class="version">Versión 1.0</p>
            </div>
        </div>
    </div>
    
    <script src="assets/js/login.js"></script>
</body>
</html>
