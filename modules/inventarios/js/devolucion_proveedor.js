// ========== MODAL DEVOLUCIÓN A PROVEEDOR ==========

let ingresoSeleccionado = null;
let lineasDevolucion = [];

async function abrirModalDevolucion() {
    // Reset
    ingresoSeleccionado = null;
    lineasDevolucion = [];
    
    // Generar número
    document.getElementById('devolucionDocumento').value = generarNumeroDoc('SMP-DV');
    
    // Fecha actual
    document.getElementById('devolucionFecha').value = new Date().toISOString().split('T')[0];
    
    // Reset campos
    document.getElementById('devolucionReferencia').value = '';
    document.getElementById('devolucionObservaciones').value = '';
    
    // Cargar ingresos disponibles
    await cargarIngresosDisponibles();
    
    // Mostrar modal
    document.getElementById('modalDevolucion').classList.add('show');
}

async function cargarIngresosDisponibles() {
    try {
        const r = await fetch(`${baseUrl}/api/salidas_mp.php?action=ingresos_devolucion&limit=20`);
        const d = await r.json();
        
        if (d.success && d.ingresos) {
            renderIngresosDisponibles(d.ingresos);
        } else {
            document.getElementById('ingresosDisponibles').innerHTML = 
                '<p style="padding:20px; text-align:center; color:#dc3545;">No hay ingresos disponibles</p>';
        }
    } catch (e) {
        console.error('Error cargando ingresos:', e);
        alert('Error al cargar ingresos disponibles');
    }
}

function renderIngresosDisponibles(ingresos) {
    const container = document.getElementById('ingresosDisponibles');
    
    if (ingresos.length === 0) {
        container.innerHTML = '<p style="padding:20px; text-align:center;">No hay ingresos para devolver</p>';
        return;
    }
    
    container.innerHTML = ingresos.map(ing => {
        const fecha = new Date(ing.fecha_documento).toLocaleDateString('es-BO');
        const proveedor = ing.proveedor_comercial || ing.proveedor_nombre;
        const conFactura = parseInt(ing.con_factura) === 1;
        
        return `
            <div class="ingreso-card" onclick="seleccionarIngreso(${ing.id_documento})" 
                 data-id="${ing.id_documento}">
                <div class="ingreso-header">
                    <div>
                        <div class="ingreso-numero">${ing.numero_documento}</div>
                        <div class="ingreso-fecha">${fecha}</div>
                    </div>
                    <div>
                        ${conFactura ? 
                            '<span class="badge-factura con">CON FACTURA</span>' : 
                            '<span class="badge-factura sin">SIN FACTURA</span>'}
                    </div>
                </div>
                <div class="ingreso-proveedor">
                    <i class="fas fa-building"></i> ${proveedor}
                </div>
                <div class="ingreso-total">
                    Total: <strong>Bs. ${formatNum(parseFloat(ing.total), 2)}</strong>
                </div>
            </div>`;
    }).join('');
}

async function seleccionarIngreso(idDocumento) {
    try {
        // Marcar como seleccionado visualmente
        document.querySelectorAll('.ingreso-card').forEach(card => {
            card.classList.remove('selected');
        });
        document.querySelector(`.ingreso-card[data-id="${idDocumento}"]`)?.classList.add('selected');
        
        // Cargar detalle
        const r = await fetch(`${baseUrl}/api/salidas_mp.php?action=detalle_ingreso&id=${idDocumento}`);
        const d = await r.json();
        
        if (d.success) {
            ingresoSeleccionado = d.documento;
            lineasDevolucion = d.detalle.map(linea => ({
                id_detalle: linea.id_detalle,
                id_inventario: linea.id_inventario,
                producto_codigo: linea.producto_codigo,
                producto_nombre: linea.producto_nombre,
                unidad: linea.unidad,
                cantidad_original: parseFloat(linea.cantidad_original),
                cantidad_devuelta: parseFloat(linea.cantidad_devuelta),
                cantidad_disponible: parseFloat(linea.cantidad_disponible),
                cantidad_a_devolver: 0,
                costo_unitario: parseFloat(linea.costo_unitario),
                costo_con_iva: parseFloat(linea.costo_con_iva),
                tenia_iva: parseInt(ingresoSeleccionado.con_factura) === 1
            }));
            
            // Mostrar sección de líneas
            document.getElementById('seccionLineasDevolucion').style.display = 'block';
            renderLineasDevolucion();
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al cargar detalle del ingreso');
    }
}

function renderLineasDevolucion() {
    const tbody = document.getElementById('devolucionLineasBody');
    const conFactura = ingresoSeleccionado && parseInt(ingresoSeleccionado.con_factura) === 1;
    
    tbody.innerHTML = lineasDevolucion.map((linea, i) => {
        const cantidad = linea.cantidad_a_devolver || 0;
        const costoUnit = linea.costo_unitario;
        const subtotalNeto = cantidad * costoUnit;
        
        let iva = 0;
        let subtotalBruto = subtotalNeto;
        
        if (conFactura) {
            // Calcular IVA: del neto al bruto
            iva = subtotalNeto / 0.87 * 0.13;
            subtotalBruto = subtotalNeto / 0.87;
        }
        
        return `
            <tr>
                <td style="font-size:0.85rem;">
                    <strong>${linea.producto_codigo}</strong><br>
                    <span style="color:#6c757d;">${linea.producto_nombre}</span>
                </td>
                <td style="text-align:right; font-weight:600; color:#6c757d;">
                    ${formatNum(linea.cantidad_original, 2)}
                </td>
                <td style="text-align:right; font-weight:600; color:#dc3545;">
                    ${formatNum(linea.cantidad_devuelta, 2)}
                </td>
                <td style="text-align:right; font-weight:700; color:#28a745;">
                    ${formatNum(linea.cantidad_disponible, 2)}
                </td>
                <td style="text-align:center; font-weight:600;">${linea.unidad}</td>
                <td>
                    <input type="number" id="devCant_${i}" value="${cantidad || ''}" 
                           step="0.01" max="${linea.cantidad_disponible}"
                           style="width:100%; padding:6px; background:#fff3cd; text-align:right;"
                           onchange="calcularLineaDevolucion(${i})" placeholder="0.00">
                </td>
                <td style="background:#f8f9fa; text-align:right; padding-right:10px; font-weight:500;">
                    Bs. ${formatNum(costoUnit, 4)}
                </td>
                ${conFactura ? `
                    <td style="background:#fff9e6; text-align:right; padding-right:10px; font-weight:500;">
                        Bs. ${formatNum(iva, 2)}
                    </td>
                    <td style="background:#d4edda; text-align:right; padding-right:10px; font-weight:700; color:#155724;">
                        Bs. ${formatNum(subtotalBruto, 2)}
                    </td>
                ` : `
                    <td style="background:#d4edda; text-align:right; padding-right:10px; font-weight:700; color:#155724;">
                        Bs. ${formatNum(subtotalNeto, 2)}
                    </td>
                `}
            </tr>`;
    }).join('');
    
    recalcularDevolucion();
}

function calcularLineaDevolucion(index) {
    const cantidad = toNum(document.getElementById(`devCant_${index}`).value);
    const disponible = lineasDevolucion[index].cantidad_disponible;
    
    if (cantidad > disponible) {
        alert(`⚠️ No puede devolver más de lo disponible: ${formatNum(disponible, 2)}`);
        document.getElementById(`devCant_${index}`).value = disponible;
        lineasDevolucion[index].cantidad_a_devolver = disponible;
    } else {
        lineasDevolucion[index].cantidad_a_devolver = cantidad;
    }
    
    renderLineasDevolucion();
}

function recalcularDevolucion() {
    const conFactura = ingresoSeleccionado && parseInt(ingresoSeleccionado.con_factura) === 1;
    let totalNeto = 0;
    let totalIVA = 0;
    let totalBruto = 0;
    
    lineasDevolucion.forEach(linea => {
        const cantidad = linea.cantidad_a_devolver || 0;
        const costoUnit = linea.costo_unitario;
        const subtotalNeto = cantidad * costoUnit;
        
        totalNeto += subtotalNeto;
        
        if (conFactura) {
            const iva = subtotalNeto / 0.87 * 0.13;
            const bruto = subtotalNeto / 0.87;
            totalIVA += iva;
            totalBruto += bruto;
        } else {
            totalBruto += subtotalNeto;
        }
    });
    
    document.getElementById('devolucionTotalNeto').textContent = 'Bs. ' + formatNum(totalNeto, 2);
    
    if (conFactura) {
        document.getElementById('rowDevIVA').style.display = 'flex';
        document.getElementById('devolucionIVA').textContent = 'Bs. ' + formatNum(totalIVA, 2);
    } else {
        document.getElementById('rowDevIVA').style.display = 'none';
    }
    
    document.getElementById('devolucionTotal').textContent = 'Bs. ' + formatNum(totalBruto, 2);
}

async function guardarDevolucion() {
    // Validaciones
    if (!ingresoSeleccionado) {
        alert('⚠️ Seleccione un ingreso para devolver');
        return;
    }
    
    const lineasConCantidad = lineasDevolucion.filter(l => (l.cantidad_a_devolver || 0) > 0);
    
    if (lineasConCantidad.length === 0) {
        alert('⚠️ Ingrese al menos una cantidad a devolver');
        return;
    }
    
    // Validar que no exceda disponible
    for (let linea of lineasConCantidad) {
        if (linea.cantidad_a_devolver > linea.cantidad_disponible) {
            alert(`⚠️ ${linea.producto_nombre}: No puede devolver más de lo disponible`);
            return;
        }
    }
    
    const conFactura = parseInt(ingresoSeleccionado.con_factura) === 1;
    
    const data = {
        action: 'crear',
        tipo_salida: 'DEVOLUCION',
        fecha: document.getElementById('devolucionFecha').value,
        id_proveedor: ingresoSeleccionado.id_proveedor,
        id_documento_origen: ingresoSeleccionado.id_documento,
        referencia: document.getElementById('devolucionReferencia').value,
        observaciones: document.getElementById('devolucionObservaciones').value,
        lineas: lineasConCantidad.map(l => ({
            id_inventario: l.id_inventario,
            id_detalle_origen: l.id_detalle,
            cantidad: l.cantidad_a_devolver,
            cantidad_original: l.cantidad_original,
            costo_unitario: l.costo_unitario,
            costo_adquisicion: l.costo_unitario,
            tenia_iva: conFactura
        }))
    };
    
    console.log('Guardando devolución:', data);
    
    try {
        const r = await fetch(`${baseUrl}/api/salidas_mp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const d = await r.json();
        console.log('Respuesta:', d);
        
        if (d.success) {
            alert('✅ ' + d.message);
            cerrarModal('modalDevolucion');
            cargarDatos();
        } else {
            alert('❌ ' + d.message);
        }
    } catch (e) {
        console.error('Error:', e);
        alert('Error al guardar la devolución');
    }
}

// ========== ESTILOS PARA MODAL DEVOLUCIÓN ==========
const estilosDevolucion = `
<style>
.ingreso-card {
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.ingreso-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.15);
    transform: translateY(-2px);
}

.ingreso-card.selected {
    border-color: #28a745;
    background: #f0f9f4;
    box-shadow: 0 4px 12px rgba(40,167,69,0.2);
}

.ingreso-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.ingreso-numero {
    font-weight: 700;
    font-size: 1rem;
    color: #1a1a2e;
}

.ingreso-fecha {
    font-size: 0.85rem;
    color: #6c757d;
}

.badge-factura {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
}

.badge-factura.con {
    background: #d4edda;
    color: #155724;
}

.badge-factura.sin {
    background: #fff3cd;
    color: #856404;
}

.ingreso-proveedor {
    font-size: 0.9rem;
    color: #495057;
    margin-bottom: 8px;
}

.ingreso-total {
    font-size: 0.95rem;
    color: #6c757d;
    padding-top: 8px;
    border-top: 1px solid #e9ecef;
}

.ingresos-container {
    max-height: 400px;
    overflow-y: auto;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}
</style>
`;

console.log('✅ Módulo de Devoluciones cargado');