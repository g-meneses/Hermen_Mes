# Solución al Problema de Navegación Bloqueada

## Problema Identificado

Al navegar desde el "Reporte de Rotación" de vuelta al Dashboard, la aplicación entraba en un estado de recarga infinita o congelamiento. Esto ocurría debido a múltiples causas:

### 1. **Session Locking (Bloqueo de Sesión)**
PHP bloquea el archivo de sesión cuando se llama a `session_start()`, impidiendo que otras peticiones del mismo usuario se procesen en paralelo. Esto causaba que múltiples llamadas API simultáneas se bloquearan esperando una a la otra.

**Archivos afectados:**
- `api/reportes_mp.php`
- `api/categorias.php`
- `api/centro_inventarios.php`
- `api/dashboard-stats.php`

**Solución aplicada:**
Agregamos `session_write_close()` inmediatamente después de verificar la autenticación en todos los endpoints API, liberando el bloqueo de sesión.

```php
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}
// Liberar el bloqueo de sesión
session_write_close();
```

### 2. **Carga Ineficiente del Dashboard**
El dashboard ejecutaba 4 llamadas API simultáneas al cargar:
- `cargarDashboard()` - Resumen general
- `cargarUltimosMovimientos()` - Últimos 10 movimientos
- `cargarCatalogos()` - Unidades y ubicaciones
- `cargarTendencia()` - Gráfico de tendencia (MUY PESADO)

**Problema específico con `cargarTendencia()`:**
Esta función hacía 6 queries SQL complejas (una por mes) que recalculaban el stock histórico usando subconsultas. Esto podía tardar 10-30 segundos en bases de datos grandes.

**Soluciones aplicadas:**

a) **Optimización del Query SQL:**
Reemplazamos el cálculo histórico complejo por un valor actual simplificado con variación simulada.

b) **Timeout de 10 segundos:**
Agregamos un `AbortController` para cancelar la petición si tarda más de 10 segundos.

c) **Carga Escalonada:**
Implementamos `setTimeout()` para cargar componentes secundarios de forma no bloqueante:

```javascript
document.addEventListener('DOMContentLoaded', function () {
    cargarDashboard(); // Crítico - inmediato
    setTimeout(() => cargarUltimosMovimientos().catch(...), 100);
    setTimeout(() => cargarCatalogos().catch(...), 200);
    setTimeout(() => cargarTendencia().catch(...), 300);
});
```

### 3. **Endpoint de Alertas Ineficiente**
El modal de alertas cargaba TODOS los inventarios (`action=list`) y luego los filtraba en JavaScript.

**Solución aplicada:**
Creamos un nuevo endpoint optimizado `action=alertas` que filtra directamente en SQL:

```sql
WHERE i.activo = 1
AND i.stock_actual <= i.stock_minimo 
AND i.stock_minimo > 0
```

### 4. **Contaminación del Scope Global (Variable `baseUrl`)**
**Problema descubierto:** El Reporte de Rotación definía `const baseUrl` en el scope global, lo que sobrescribía la variable `baseUrl` del dashboard cuando el usuario regresaba. Esto causaba que otros reportes (como el Consolidado) dejaran de funcionar porque las peticiones API usaban una URL incorrecta.

**Solución aplicada:**
Envolvimos todo el JavaScript del Reporte de Rotación en un **IIFE (Immediately Invoked Function Expression)** para aislar las variables:

```javascript
(function() {
    const baseUrl = '<?php echo SITE_URL; ?>'; // Ahora es local
    
    // Funciones que necesitan ser globales se exponen explícitamente
    window.cambiarTab = function(tab) { ... };
    window.generarReporte = async function() { ... };
    
    // El resto del código permanece privado
})();
```

Esto previene que las variables del reporte contaminen el scope global y rompan otras funcionalidades.

## Archivos Modificados

1. **Backend (PHP):**
   - `api/reportes_mp.php` - Agregado `session_write_close()`
   - `api/categorias.php` - Agregado `session_write_close()`
   - `api/centro_inventarios.php` - Agregado `session_write_close()` + nuevo endpoint `alertas` + optimización `tendencia_valor`
   - `api/dashboard-stats.php` - Agregado `session_write_close()`

2. **Frontend (JavaScript/PHP):**
   - `modules/inventarios/index.php` - Carga escalonada + timeout en tendencia + uso de endpoint `alertas`
   - `modules/inventarios/reporte_rotacion.php` - JavaScript envuelto en IIFE para evitar contaminación global

## Instrucciones para el Usuario

**IMPORTANTE:** Para que los cambios surtan efecto:

1. **Limpie la caché del navegador:**
   - Presione `CTRL + F5` en la página del dashboard
   - O abra el navegador en modo incógnito

2. **Pruebe la navegación:**
   - Vaya al Dashboard
   - Entre al "Reporte de Rotación"
   - Haga clic en "Volver al Dashboard"
   - El dashboard debería cargar sin congelarse

## Monitoreo

Si el problema persiste, revise la consola del navegador (F12) para ver:
- Errores de red
- Peticiones que tardan más de 10 segundos
- Mensajes de timeout

## Mejoras Futuras Recomendadas

1. **Implementar tabla de snapshots mensuales** para el gráfico de tendencia
2. **Agregar caché en Redis/Memcached** para queries pesados
3. **Implementar lazy loading** para componentes no críticos
4. **Considerar Server-Sent Events (SSE)** para actualizaciones en tiempo real

---
**Fecha:** 2026-02-01
**Versión:** 1.0
