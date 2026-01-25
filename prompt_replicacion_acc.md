# PROMPT MAESTRO PARA REPLICACIÓN EN MÓDULO REPUESTOS (ACC)

**Contexto:**
Hemos finalizado la optimización del módulo "Material de Empaque" (EMP) y ahora debemos replicar exactamente las mismas mejoras visuales, de lógica y correcciones de errores en el módulo de "Repuestos y Accesorios" (ACC).

**Archivos Principales a Intervenir:**
- `modules/inventarios/repuestos.php`
- `modules/inventarios/js/repuestos.js`
- `api/salidas_acc.php` (verificar existencia y estructura)
- `api/ingresos_acc.php` (verificar existencia y estructura)

**Instrucciones Detalladas por Área:**

### 1. Interfaz de Usuario (UI) en `repuestos.php`
- **Título de Página:** Asegurar que el título principal sea descriptivo (ej. "Repuestos y Accesorios") y consistente con el estilo de EMP.
- **Estilo de Modales:**
    - **Modal Ingreso:** Aplicar degradado **VERDE** al header (`background: linear-gradient(135deg, #2e7d32, #66bb6a); color: white;`).
    - **Modal Salida:** Aplicar degradado **ROJO** al header (`background: linear-gradient(135deg, #c62828, #ef5350); color: white;`).
    - **Selectores:** Los divs contenedores de selectores (Tipo de Ingreso/Salida) deben tener un fondo neutro (`#f8f9fa`) y bordes estándar, sin degradados llamativos que compitan con el encabezado.
- **Cache Busting:** Agregar `?v=<?php echo time(); ?>` a todas las importaciones de scripts JS (`repuestos.js`, etc.) para evitar problemas de caché.
- **Limpieza:** Eliminar referencias a scripts conflictivos antiguos si existen (tipo `historial_movimientos.js` genérico) y usar solo los específicos del módulo.

### 2. Lógica JavaScript (`repuestos.js`)
- **Alertas Modernas:** Reemplazar TODOS los `alert()` nativos por `Swal.fire()` (SweetAlert2) para mantener consistencia visual (Iconos de success/error/warning).
- **Historial de Movimientos:**
    - Implementar/Corregir `abrirModalHistorial()` para que inicialice las fechas (últimos 30 días) correctamente.
    - Asegurar que la función `verDetalleDocumento(docNumero)` esté implementada y llame correctamente a la API (probablemente `centro_inventarios.php?action=documento_detalle`).
    - **Importante:** Verificar el mapeo de datos en el detalle. El backend suele devolver `lineas` (array de productos), asegurar que el JS no busque `detalle` incorrectamente (como pasó en EMP).
    - Implementar botón de **Anular Documento** dentro de la vista de detalle.

### 3. Backend / API (`api/salidas_acc.php`)
- **Corrección SQL:** Revisar la sentencia `INSERT INTO documentos_inventario`.
    - **Error Crítico a Prevenir:** Asegurar que NO se intente insertar en la columna `id_tipo_salida` si esta no existe en la base de datos (fue el error principal en EMP).
    - Verificar que los parámetros pasados al `execute()` coincidan exactamente con los placeholders `?`.

### 4. Verificación Final
- Confirmar que el filtrado de historial solo muestre datos de REPUESTOS (ID de inventario correspondiente).
- Probar el flujo completo: Ingreso -> Salida -> Ver Historial -> Ver Detalle.

**Objetivo:**
El módulo de Repuestos debe quedar funcional y visualmente idéntico al de Material de Empaque.
