# Sistema MES Hermen Ltda - Módulo de Tejeduría

## 📋 Descripción
Sistema de Gestión de Producción (MES) para la línea de producción de Poliamida de Hermen Ltda., enfocado inicialmente en el módulo de Tejeduría.

## 🚀 Instalación

### Requisitos Previos
- XAMPP instalado (Apache + MySQL + PHP 7.4 o superior)
- Navegador web moderno (Chrome, Firefox, Edge)

### Paso 1: Copiar Archivos

1. Copia la carpeta `mes_hermen` a la carpeta `htdocs` de tu instalación de XAMPP
   - Ruta típica: `C:\xampp\htdocs\mes_hermen`

### Paso 2: Configurar Base de Datos

1. Inicia XAMPP y asegúrate de que Apache y MySQL estén corriendo

2. Abre phpMyAdmin en tu navegador:
   ```
   http://localhost/phpmyadmin
   ```

3. Ejecuta el script SQL:
   - Abre el archivo `mes_hermen_db.sql`
   - Cópialo completamente
   - En phpMyAdmin, ve a la pestaña "SQL"
   - Pega el contenido y haz clic en "Ejecutar"

4. Verifica que la base de datos `mes_hermen` se haya creado correctamente

### Paso 3: Configurar Conexión (si es necesario)

Si tu MySQL tiene contraseña, edita el archivo `config/database.php`:

```php
define('DB_PASS', ''); // Cambia '' por tu contraseña si la tienes
```

### Paso 4: Acceder al Sistema

1. Abre tu navegador y ve a:
   ```
   http://localhost/mes_hermen
   ```

2. Credenciales de acceso inicial:
   - **Usuario:** admin
   - **Contraseña:** admin123

   ⚠️ **IMPORTANTE:** Cambia esta contraseña después del primer login

## 📁 Estructura del Proyecto

```
mes_hermen/
├── api/                      # APIs REST
│   ├── login.php
│   ├── logout.php
│   ├── dashboard-stats.php
│   └── maquinas.php
├── assets/                   # Recursos estáticos
│   ├── css/
│   │   ├── login.css
│   │   └── main.css
│   ├── js/
│   │   ├── login.js
│   │   └── main.js
│   └── img/
├── config/                   # Configuración
│   └── database.php
├── includes/                 # Plantillas comunes
│   ├── header.php
│   └── footer.php
├── modules/                  # Módulos del sistema
│   └── tejido/
│       ├── maquinas.php
│       ├── productos.php
│       ├── insumos.php
│       ├── plan_generico.php
│       ├── produccion.php
│       └── inventario.php
├── index.php                 # Página de login
└── dashboard.php             # Dashboard principal
```

## 🎯 Módulos Implementados

### ✅ Completados en esta fase:

1. **Sistema de Login**
   - Autenticación de usuarios
   - Gestión de sesiones
   - Control de acceso por roles

2. **Dashboard**
   - Estadísticas en tiempo real
   - Gráficos de producción
   - Estado de máquinas

3. **Gestión de Máquinas**
   - CRUD completo de máquinas
   - Filtrado y búsqueda
   - Control de estados

### 📝 Próximos pasos:

4. **Productos Tejidos** - Catálogo de productos
5. **Hilos e Insumos** - Gestión de materias primas
6. **Plan Genérico** - Asignación máquina-producto
7. **Registro de Producción** - Producción por turno
8. **Inventario Intermedio** - Control de stock

## 👥 Roles de Usuario

- **admin:** Acceso completo al sistema
- **coordinador:** Planificación y supervisión
- **gerencia:** Visualización de reportes
- **tejedor:** Registro de producción
- **revisor:** Control de calidad
- **tintorero:** Proceso de teñido

## 🔒 Seguridad

- Contraseñas encriptadas con bcrypt
- Protección contra SQL Injection
- Sesiones seguras con HttpOnly cookies
- Validación de entrada de datos

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL 5.7+
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Librerías:**
  - Chart.js (gráficos)
  - Font Awesome (iconos)

## 📊 Base de Datos

La base de datos incluye:
- 20+ tablas relacionadas
- Datos de ejemplo pre-cargados
- Índices optimizados
- Relaciones con integridad referencial

### Tablas Principales:
- usuarios
- maquinas
- productos_tejidos
- insumos
- planes_semanales
- lotes_produccion
- produccion_tejeduria
- inventario_intermedio

## 🐛 Solución de Problemas

### Error de conexión a base de datos
- Verifica que MySQL esté corriendo en XAMPP
- Revisa las credenciales en `config/database.php`
- Asegúrate de que la base de datos `mes_hermen` exista

### No carga el CSS/JS
- Verifica que la carpeta esté en `htdocs/mes_hermen`
- Revisa la consola del navegador (F12)
- Verifica permisos de lectura en los archivos

### Error 404
- Asegúrate de acceder a `http://localhost/mes_hermen` (no `mes_hermen/index.html`)
- Verifica que Apache esté corriendo

## 📞 Soporte

Para preguntas o problemas durante el desarrollo, documentar en el chat del proyecto.

## 📝 Notas de Desarrollo

- Sistema desarrollado de forma didáctica para facilitar el aprendizaje
- Código comentado en español
- Arquitectura modular para fácil extensión
- Preparado para agregar más módulos (Costura, Teñido, etc.)

## ⚙️ Configuración Adicional (Opcional)

### Cambiar URL base
En `config/database.php`, modifica:
```php
define('SITE_URL', 'http://localhost/mes_hermen');
```

### Cambiar zona horaria
En `config/database.php`, modifica:
```php
define('TIMEZONE', 'America/La_Paz');
```

---

**Versión:** 1.0  
**Última actualización:** Noviembre 2025  
**Desarrollado para:** Hermen Ltda.
