# Sistema de Numeración de Documentos - MES Hermen Ltda.

## ⚠️ ADVERTENCIA CRÍTICA
**NO MODIFICAR** este sistema sin consultar esta documentación. Cualquier cambio puede causar:
- Doble consumo de secuencias
- Números de documento saltados
- Errores 500 en la generación de números

---

## Arquitectura del Sistema

### 1. API Centralizada: `obtener_siguiente_numero.php`

**Ubicación:** `api/obtener_siguiente_numero.php`

**Función principal:** Generar números de documento únicos para todos los módulos de inventario.

**Parámetros requeridos:**
- `tipo_inventario`: ID del tipo de inventario (1=MP, 2=CAQ, 3=EMP, 4=ACC, 7=REP)
- `operacion`: INGRESO o SALIDA
- `tipo_movimiento`: COMPRA, PRODUCCION, VENTA, etc.
- `modo`: **preview** (no consume) o **commit** (consume secuencia)

**Ejemplo de llamada:**
```
GET /api/obtener_siguiente_numero.php?tipo_inventario=2&operacion=SALIDA&tipo_movimiento=PRODUCCION&modo=preview
```

**Respuesta esperada:**
```json
{
  "success": true,
  "numero": "OUT-CAQ-P-202601-0002",
  "prefijo": "OUT-CAQ-P",
  "modo": "preview"
}
```

---

## 2. Modo Preview vs Commit

### Modo Preview (NO consume secuencia)
- Se usa al **cargar el formulario**
- Muestra el siguiente número disponible
- **NO actualiza** la tabla `secuencias_documento`
- Permite al usuario ver el número antes de guardar

### Modo Commit (SÍ consume secuencia)
- Se usa al **guardar el documento**
- Actualiza la tabla `secuencias_documento`
- Incrementa el contador
- Solo se ejecuta una vez por documento

---

## 3. Estructura de Archivos

### Archivos que usan el sistema (CORRECTAMENTE CONFIGURADOS):

#### Ingresos:
- ✅ `api/ingresos_mp.php` (Materias Primas)
- ✅ `api/ingresos_caq.php` (Colorantes)
- ✅ `api/ingresos_emp.php` (Empaque)

#### Salidas:
- ✅ `api/salidas_mp.php` (Materias Primas)
- ✅ `api/salidas_caq.php` (Colorantes)
- ✅ `api/salidas_emp.php` (Empaque)

---

## 4. Patrón de Implementación

### En archivos de Ingresos/Salidas (PHP):

```php
case 'siguiente_numero':
    // REDIRIGIR a API centralizada con modo preview usando include
    $tipo = $_GET['tipo'] ?? 'COMPRA';
    
    // Configurar parámetros para la API centralizada
    $_GET['tipo_inventario'] = '2'; // ID del inventario
    $_GET['operacion'] = 'INGRESO'; // o 'SALIDA'
    $_GET['tipo_movimiento'] = $tipo;
    $_GET['modo'] = 'preview';
    
    // No usar ob_clean aquí, el archivo incluido lo maneja
    include 'obtener_siguiente_numero.php';
    exit();
    break;
```

### En archivos JavaScript:

```javascript
async function actualizarNumeroIngreso() {
    const tipo = document.getElementById('ingresoTipo').value;
    
    try {
        // API CENTRALIZADA - MODO PREVIEW (no consume secuencia)
        const url = `${BASE_URL_API}/obtener_siguiente_numero.php?tipo_inventario=2&operacion=INGRESO&tipo_movimiento=${tipo}&modo=preview`;
        
        const r = await fetch(url);
        const d = await r.json();
        
        if (d.success && d.numero) {
            document.getElementById('ingresoDocumento').value = d.numero;
        }
    } catch (e) {
        console.error('Error al obtener número:', e);
    }
}
```

---

## 5. Puntos Críticos - NO MODIFICAR

### ❌ NO usar `file_get_contents()` para llamadas internas
**Razón:** No funciona en XAMPP local, causa errores 500

### ✅ SÍ usar `include` con variables `$_GET`
**Razón:** Funciona en local y es más rápido

### ❌ NO llamar a `generarNumeroDocumento()` directamente en el frontend
**Razón:** Consumiría la secuencia dos veces (preview + guardado)

### ✅ SÍ usar siempre `modo=preview` en el frontend
**Razón:** Evita doble consumo de secuencias

### ❌ NO modificar la función `generarNumeroDocumento()` en `obtener_siguiente_numero.php`
**Razón:** Está protegida con `if (!function_exists())` para evitar redeclaraciones

### ✅ SÍ mantener la función ANTES del bloque `try-catch`
**Razón:** Debe estar disponible cuando se llama en la línea 90

---

## 6. Tabla de Base de Datos

### Tabla: `secuencias_documento`

```sql
CREATE TABLE secuencias_documento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento VARCHAR(20),  -- 'INGRESO', 'SALIDA', 'MOVIMIENTO'
    prefijo VARCHAR(20),          -- 'IN-CAQ-C', 'OUT-MP-P', etc.
    anio INT,
    mes INT,
    ultimo_numero INT,
    UNIQUE KEY (tipo_documento, prefijo, anio, mes)
);
```

---

## 7. Formato de Números Generados

### Ingresos:
- `IN-{INVENTARIO}-{TIPO}-{AÑO}{MES}-{SECUENCIA}`
- Ejemplo: `IN-CAQ-C-202601-0001`

### Salidas:
- `OUT-{INVENTARIO}-{TIPO}-{AÑO}{MES}-{SECUENCIA}`
- Ejemplo: `OUT-MP-P-202601-0015`

### Códigos de Inventario:
- MP = Materias Primas (1)
- CAQ = Colorantes y Aux. Químicos (2)
- EMP = Empaque (3)
- ACC = Accesorios (4)
- REP = Repuestos (7)

### Códigos de Tipo:
- C = Compra
- P = Producción
- V = Venta
- M = Muestras
- A = Ajuste
- R = Devolución
- I = Inicial

---

## 8. Solución de Problemas

### Error: "Unexpected token '<'"
**Causa:** PHP está devolviendo HTML en lugar de JSON
**Solución:** Verificar que `display_errors = 0` en el archivo PHP

### Error: "Call to undefined function generarNumeroDocumento()"
**Causa:** La función está definida después del `try-catch`
**Solución:** Mover la definición ANTES del `try-catch` (línea 23)

### Error: "Cannot redeclare function"
**Causa:** La función se está definiendo dos veces
**Solución:** Verificar que esté envuelta en `if (!function_exists())`

### Números saltados (1, 3, 5, 7...)
**Causa:** El frontend está llamando sin `modo=preview`
**Solución:** Agregar `&modo=preview` a la URL del fetch

---

## 9. Checklist de Implementación

Al agregar un nuevo módulo de inventario:

- [ ] Agregar ID en `$codigosInventario` en `obtener_siguiente_numero.php`
- [ ] Crear case `siguiente_numero` en `ingresos_*.php`
- [ ] Crear case `siguiente_numero` en `salidas_*.php`
- [ ] Usar `include 'obtener_siguiente_numero.php'` (NO `file_get_contents`)
- [ ] Configurar `$_GET['modo'] = 'preview'` en el case
- [ ] En JavaScript, usar `&modo=preview` en el fetch
- [ ] Probar que el número se genera sin consumir
- [ ] Probar que al guardar, usa el mismo número

---

## 10. Historial de Cambios

### 2026-01-18
- ✅ Implementado modo preview para evitar doble consumo
- ✅ Cambiado de `file_get_contents()` a `include` para compatibilidad local
- ✅ Protegida función con `if (!function_exists())`
- ✅ Movida definición de función antes del `try-catch`

### 2026-01-19
- ✅ Corregidos conflictos de headers con `headers_sent()`
- ✅ Eliminados `ob_clean()` que causaban conflictos
- ✅ Desactivado `display_errors` para producción
- ✅ Sistema completamente funcional en CAQ y MP

---

**Última actualización:** 2026-01-19
**Responsable:** Sistema MES Hermen Ltda.
**Estado:** ✅ PRODUCCIÓN - NO MODIFICAR SIN AUTORIZACIÓN
