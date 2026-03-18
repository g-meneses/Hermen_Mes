<?php
// modules/configuracion/index.php
require_once '../../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

$pageTitle = "Centro de Configuración Global";
$currentPage = 'configuracion';
include '../../includes/header.php';
?>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: { primary: "#4f46e5" }
            }
        }
    }
</script>
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"
    rel="stylesheet" />

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc;
    }

    .config-card {
        background: white;
        border-radius: 1rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .config-header {
        background: linear-gradient(to right, #1e293b, #0f172a);
        padding: 1.5rem;
        color: white;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .config-body {
        padding: 2rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        border-radius: 0.5rem;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .form-input:focus {
        border-color: #4f46e5;
        outline: none;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        background: white;
    }

    .btn-save {
        background: #4f46e5;
        color: white;
        font-weight: 600;
        padding: 0.75rem 2rem;
        border-radius: 0.5rem;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    .btn-save:hover {
        background: #4338ca;
    }
</style>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="mb-8 pl-2">
        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Centro de Configuración</h1>
        <p class="text-slate-500 mt-1">Administración central de parámetros globales del sistema, documentos e
            impuestos.</p>
    </div>

    <form id="formConfiguracion" onsubmit="guardarConfiguracion(event)">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Panel: Datos de la Empresa -->
            <div class="config-card">
                <div class="config-header">
                    <span class="material-symbols-outlined text-3xl text-indigo-300">domain</span>
                    <div>
                        <h2 class="text-xl font-bold m-0">Datos de la Empresa</h2>
                        <p class="text-indigo-200 text-xs mt-1">Información impresa en todas las Órdenes de Compra y
                            Solicitudes PDF</p>
                    </div>
                </div>
                <div class="config-body">
                    <div class="form-group">
                        <label class="form-label">Razón Social / Nombre Comercial</label>
                        <input type="text" id="empresa_nombre" class="form-input" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4 form-group">
                        <div>
                            <label class="form-label">NIT / Tax ID</label>
                            <input type="text" id="empresa_nit" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Teléfono Principal</label>
                            <input type="text" id="empresa_telefono" class="form-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dirección Legal</label>
                        <input type="text" id="empresa_direccion" class="form-input">
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">Correo de Ventas / Compras</label>
                        <input type="email" id="empresa_email" class="form-input">
                    </div>
                </div>
            </div>

            <!-- Panel: Parámetros Fiscales -->
            <div class="config-card">
                <div class="config-header">
                    <span class="material-symbols-outlined text-3xl text-indigo-300">request_quote</span>
                    <div>
                        <h2 class="text-xl font-bold m-0">Parámetros Fiscales</h2>
                        <p class="text-indigo-200 text-xs mt-1">Impuestos e índices base aplicados en el Kardex y
                            Almacenes</p>
                    </div>
                </div>
                <div class="config-body">
                    <div
                        class="form-group p-4 bg-orange-50 border border-orange-100 rounded-xl relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 opacity-10">
                            <span class="material-symbols-outlined" style="font-size: 100px;">account_balance</span>
                        </div>
                        <label class="form-label text-orange-800">Impuesto al Valor Agregado (IVA)</label>
                        <p class="text-xs text-orange-600 mb-3 pr-12">
                            Aplica al despiece de precios base cuando se ingresan facturas en Almacenes e Internaciones.
                            Debe estar expresado en decimales (ej: 0.13 equivale al 13%). Si la ley impositiva cambia,
                            simplemente modifica este valor.
                        </p>
                        <div class="relative w-1/2">
                            <input type="number" step="0.01" min="0" max="1" id="impuesto_iva"
                                class="form-input font-bold text-lg text-slate-800" required>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="submit" class="btn-save" id="btnGuardar">
                        <span class="material-symbols-outlined">save</span>
                        Guardar Cambios Universales
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        cargarConfiguracion();
    });

    function cargarConfiguracion() {
        Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        fetch('../../api/configuracion.php?action=get')
            .then(res => res.json())
            .then(data => {
                Swal.close();
                if (data.success && data.config) {
                    // Mapear llaves a los inputs correspondientes
                    const config = data.config;
                    if (config.empresa_nombre) document.getElementById('empresa_nombre').value = config.empresa_nombre.valor;
                    if (config.empresa_nit) document.getElementById('empresa_nit').value = config.empresa_nit.valor;
                    if (config.empresa_direccion) document.getElementById('empresa_direccion').value = config.empresa_direccion.valor;
                    if (config.empresa_telefono) document.getElementById('empresa_telefono').value = config.empresa_telefono.valor;
                    if (config.empresa_email) document.getElementById('empresa_email').value = config.empresa_email.valor;
                    if (config.impuesto_iva) document.getElementById('impuesto_iva').value = config.impuesto_iva.valor;
                } else {
                    Swal.fire('Atención', 'No se pudieron cargar las configuraciones o están vacías.', 'warning');
                }
            })
            .catch(err => {
                Swal.close();
                console.error(err);
                Swal.fire('Error', 'Error de conexión al leer parámetros', 'error');
            });
    }

    function guardarConfiguracion(e) {
        e.preventDefault();

        const payload = {
            empresa_nombre: document.getElementById('empresa_nombre').value,
            empresa_nit: document.getElementById('empresa_nit').value,
            empresa_direccion: document.getElementById('empresa_direccion').value,
            empresa_telefono: document.getElementById('empresa_telefono').value,
            empresa_email: document.getElementById('empresa_email').value,
            impuesto_iva: parseFloat(document.getElementById('impuesto_iva').value)
        };

        // Validación del lado cliente (asegurar que el IVA sea menor a 1)
        if (payload.impuesto_iva >= 1 || payload.impuesto_iva < 0) {
            Swal.fire('Aviso', 'El IVA debe ser un número decimal entre 0 y 0.99 (ej. 0.13)', 'warning');
            return;
        }

        const btn = document.getElementById('btnGuardar');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">refresh</span> Guardando...';

        fetch('../../api/configuracion.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined">save</span> Guardar Cambios Universales';

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Actualizado!',
                        text: 'Las configuraciones se han aplicado a nivel mundial de inmediato.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', data.message || 'No se pudo guardar la configuración', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-symbols-outlined">save</span> Guardar Cambios Universales';
                console.error(err);
                Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
            });
    }
</script>

<?php include '../../includes/footer.php'; ?>