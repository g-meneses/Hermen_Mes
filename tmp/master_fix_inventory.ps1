$files = @(
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\accesorios_dinamico.js'; func = 'obtenerSiguienteNumero'; type = 'ACC'; mod = 'ingresos_acc' },
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\colorantes_quimicos.js'; func = 'actualizarNumeroSalida'; type = 'CAQ'; mod = 'salidas_caq' },
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\colorantes_quimicos_dinamico.js'; func = 'obtenerSiguienteNumero'; type = 'CAQ'; mod = 'ingresos_caq' },
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\empaque.js'; func = 'actualizarNumeroSalida'; type = 'EMP'; mod = 'salidas_emp' },
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\empaque_dinamico.js'; func = 'obtenerSiguienteNumero'; type = 'EMP'; mod = 'ingresos_emp' },
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\repuestos.js'; func = 'actualizarNumeroSalida'; type = 'REP'; mod = 'salidas_rep' },
    @{ path = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\repuestos_dinamico.js'; func = 'obtenerSiguienteNumero'; type = 'REP'; mod = 'ingresos_rep' }
)

foreach ($fObj in $files) {
    $p = $fObj.path
    if (!(Test-Path $p)) { continue }
    $c = [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::GetEncoding(1252))
    $isSalida = $fObj.func -eq 'actualizarNumeroSalida'
    
    $jsFunc = ""
    if ($isSalida) {
        $jsFunc = @"
async function actualizarNumeroSalida() {
    const tipo = document.getElementById('salidaTipo').value;
    const docInput = document.getElementById('salidaDocumento');
    const motivoObligatorio = document.getElementById('motivoObligatorio');

    if (!tipo) {
        if (docInput) {
            docInput.value = '';
            docInput.placeholder = 'Seleccione tipo de salida...';
            docInput.disabled = true;
        }
        return;
    }

    if (window.numerosSalidaCache[tipo]) {
        if (docInput) {
            docInput.value = window.numerosSalidaCache[tipo];
            docInput.disabled = false;
        }
        if (motivoObligatorio) {
            motivoObligatorio.style.display = tipo === 'AJUSTE' ? 'inline' : 'none';
        }
        return;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    try {
        if (docInput) {
            docInput.value = '⏳ Generando...';
            docInput.disabled = true;
        }

        const url = `${BASE_URL_API}/$($fObj.mod).php?action=siguiente_numero&tipo=${tipo}`;
        const r = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

        if (r.status === 401) throw new Error('SESIÓN_EXPIRADA');

        const d = await r.json();

        if (d.success) {
            window.numerosSalidaCache[tipo] = d.numero;
            if (docInput) {
                docInput.value = d.numero;
                docInput.disabled = false;
            }
        } else {
            if (d.message && (d.message.includes('autorizado') || d.message.includes('No autorizado'))) throw new Error('SESIÓN_EXPIRADA');
            throw new Error(d.message || 'Error servidor');
        }
    } catch (e) {
        clearTimeout(timeoutId);
        if (e.name === 'AbortError') {
            ejecutarFallbackLocalSalida(tipo, 'Timeout');
        } else if (e.message === 'SESIÓN_EXPIRADA') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Sesión Expirada', text: 'Por favor reingrese.', icon: 'warning' }).then(() => { window.location.href = 'index.php'; });
            }
            if (docInput) { docInput.value = ''; docInput.disabled = true; }
        } else {
            ejecutarFallbackLocalSalida(tipo, e.message);
        }
    } finally {
        if (docInput && docInput.value === '⏳ Generando...') { docInput.value = ''; docInput.disabled = false; }
        if (motivoObligatorio) motivoObligatorio.style.display = tipo === 'AJUSTE' ? 'inline' : 'none';
    }
}

function ejecutarFallbackLocalSalida(tipo, motivo) {
    const prefijos = { 'PRODUCCION': 'OUT-$($fObj.type)-P', 'VENTA': 'OUT-$($fObj.type)-V', 'MUESTRAS': 'OUT-$($fObj.type)-M', 'AJUSTE': 'OUT-$($fObj.type)-A', 'DEVOLUCION': 'OUT-$($fObj.type)-R' };
    const prefijo = prefijos[tipo] || 'OUT-$($fObj.type)-X';
    const docInput = document.getElementById('salidaDocumento');
    if (docInput) {
        const numero = generarNumeroDoc(prefijo);
        window.numerosSalidaCache[tipo] = numero;
        docInput.value = numero;
        docInput.disabled = false;
        console.warn('Fallback $($fObj.type):', numero);
    }
}
"@
    } else {
        # logic for Ingreso
        $jsFunc = @"
async function obtenerSiguienteNumero(tipo = null) {
    if (!tipo) {
        const selectTipo = document.getElementById('ingresoTipoIngreso');
        if (selectTipo && selectTipo.value) {
            const tC = tiposIngresoConfig[selectTipo.value];
            tipo = tC ? tC.codigo : null;
        }
    }
    if (!tipo) return;

    const docInput = document.getElementById('ingresoDocumento');
    if (numerosDocumentoCache[tipo]) {
        if (docInput) {
            docInput.value = numerosDocumentoCache[tipo];
            docInput.disabled = false;
        }
        return;
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    try {
        if (docInput) {
            docInput.value = '⏳ Generando...';
            docInput.disabled = true;
        }

        const url = `${BASE_URL_API}/$($fObj.mod).php?action=siguiente_numero&tipo=${tipo}`;
        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);

        if (response.status === 401) throw new Error('SESIÓN_EXPIRADA');

        const data = await response.json();

        if (data.success && docInput) {
            numerosDocumentoCache[tipo] = data.numero;
            docInput.value = data.numero;
            docInput.disabled = false;
        } else {
            if (data.message && (data.message.includes('autorizado') || data.message.includes('Sesión'))) throw new Error('SESIÓN_EXPIRADA');
            throw new Error(data.message || 'Error servidor');
        }
    } catch (error) {
        clearTimeout(timeoutId);
        if (error.name === 'AbortError') {
            ejecutarFallbackLocalIngreso(tipo, 'Timeout');
        } else if (error.message === 'SESIÓN_EXPIRADA') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Sesión Expirada', text: 'Por favor reingrese.', icon: 'warning' }).then(() => { window.location.href = 'index.php'; });
            }
            if (docInput) { docInput.value = ''; docInput.disabled = true; }
        } else {
            ejecutarFallbackLocalIngreso(tipo, error.message);
        }
    } finally {
        if (docInput && docInput.value === '⏳ Generando...') { docInput.value = ''; docInput.disabled = false; }
    }
}

function ejecutarFallbackLocalIngreso(tipo, motivo) {
    const docInput = document.getElementById('ingresoDocumento');
    if (!docInput) return;
    const codigosMovimiento = { 'COMPRA': 'C', 'INICIAL': 'I', 'DEVOLUCION_PROD': 'R', 'AJUSTE_POS': 'A' };
    const hoy = new Date();
    const fechaStr = hoy.toISOString().split('T')[0].replace(/-/g, '');
    const rand = Math.floor(Math.random() * 100).toString().padStart(2, '0');
    const movCode = codigosMovimiento[tipo] || 'X';
    const numero = 'IN-$($fObj.type)-' + movCode + '-' + fechaStr + '-' + rand;
    numerosDocumentoCache[tipo] = numero;
    docInput.value = numero;
    docInput.disabled = false;
    console.warn('Fallback IN $($fObj.type):', numero);
}
"@
    }

    $startIndex = $c.IndexOf("async function $($fObj.func)(")
    # Find next function or common reset/init function
    $pattern = if ($isSalida) { "function agregarLineaSalida()" } else { "function resetearFormularioIngreso()" }
    $endIndex = $c.IndexOf($pattern)
    
    if ($startIndex -ge 0 -and $endIndex -gt $startIndex) {
        $before = $c.Substring(0, $startIndex)
        $after = $c.Substring($endIndex)
        $final = $before + $jsFunc + "`n`n" + $after
        [System.IO.File]::WriteAllText($p, $final, (New-Object System.Text.UTF8Encoding($false)))
        Write-Host "Fixed $($p)"
    } else {
        Write-Warning "Could not find function for replacement in $($p)"
    }
}
