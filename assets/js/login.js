document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('error-message');
    
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const usuario = document.getElementById('usuario').value;
        const password = document.getElementById('password').value;
        
        // Ocultar mensaje de error previo
        errorMessage.style.display = 'none';
        
        // Deshabilitar botón
        const submitBtn = loginForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Iniciando sesión...';
        
        try {
            const response = await fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ usuario, password })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Redirigir al dashboard
                window.location.href = 'dashboard.php';
            } else {
                // Mostrar error
                errorMessage.textContent = data.message;
                errorMessage.style.display = 'block';
                
                // Rehabilitar botón
                submitBtn.disabled = false;
                submitBtn.textContent = 'Iniciar Sesión';
            }
        } catch (error) {
            errorMessage.textContent = 'Error de conexión. Por favor, intente nuevamente.';
            errorMessage.style.display = 'block';
            
            // Rehabilitar botón
            submitBtn.disabled = false;
            submitBtn.textContent = 'Iniciar Sesión';
        }
    });
});
