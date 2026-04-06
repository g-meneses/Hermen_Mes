$p = 'c:\xampp\htdocs\mes_hermen\modules\inventarios\js\accesorios.js'
$c = Get-Content $p -Encoding UTF8
$f = @'
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

        const url = `${BASE_URL_API}/salidas_acc.php?action=siguiente_numero&tipo=${tipo}`;
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
    const prefijos = { 'PRODUCCION': 'OUT-ACC-P', 'VENTA': 'OUT-ACC-V', 'MUESTRAS': 'OUT-ACC-M', 'AJUSTE': 'OUT-ACC-A', 'DEVOLUCION': 'OUT-ACC-R' };
    const prefijo = prefijos[tipo] || 'OUT-ACC-X';
    const docInput = document.getElementById('salidaDocumento');
    if (docInput) {
        const numero = generarNumeroDoc(prefijo);
        window.numerosSalidaCache[tipo] = numero;
        docInput.value = numero;
        docInput.disabled = false;
        console.warn('Fallback ACC:', numero);
    }
}
'@
# Replacement logic (reconfirming line numbers after first failed attempt)
# c[0..1726] is preserved. New function injected. then c[1800..end]
# Wait, my previous replacement used $c[1790..-1]. But view_file showed the old function ended at 1790.
# My new function+helper had more lines.
# I should re-read the file to be sure where to start the 'after' slice.
$content_string = [string]::Join("`n", $c)
$startIndex = $content_string.IndexOf("async function actualizarNumeroSalida()")
# Find the next function or END of file
$endSearchIndex = $content_string.IndexOf("function agregarLineaSalida()")

if ($startIndex -ge 0 -and $endSearchIndex -gt $startIndex) {
    $before = $content_string.Substring(0, $startIndex)
    $after = $content_string.Substring($endSearchIndex)
    $final = $before + $f + "`n`n" + $after
    $final | Set-Content $p -Encoding UTF8
} else {
    Write-Error "Could not find start/end indices"
}
