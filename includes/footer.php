</div>
<!-- Fin Page Container -->
</main>
<!-- Fin Main Content -->
</div>
<!-- Fin Layout -->

<!-- Modal Reportes Dinámico (Global) -->
<div class="modal" id="modalReporte">
    <div class="modal-content xlarge">
        <div class="modal-header">
            <h3 id="reporteTitulo"><i class="fas fa-chart-bar"></i> Reporte</h3>
            <button class="modal-close" onclick="cerrarModal('modalReporte')"
                style="background: rgba(255,255,255,0.2); color: white;">&times;</button>
        </div>
        <div class="modal-body">
            <div id="reporteFiltros" class="form-row"
                style="margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
            </div>
            <div id="reporteContenido" style="overflow-x: auto; max-height: 60vh;">
                <p style="text-align:center; padding:40px; color:#667eea;">
                    <i class="fas fa-spinner fa-spin fa-3x"></i><br><br>
                    Generando reporte...
                </p>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between;">
            <div>
                <button class="btn btn-info" onclick="descargarExcelReporte()">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button class="btn btn-danger" onclick="descargarPDFReporte()">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn btn-secondary" onclick="imprimirReporte()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
            <button class="btn btn-dark" onclick="cerrarModalGlobal('modalReporte')">Cerrar</button>
        </div>
    </div>
</div>

<!-- JQuery & Bootstrap 4 scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script src="<?php echo SITE_URL; ?>/modules/inventarios/js/reportes_mp.js?v=<?php echo time(); ?>"></script>
<script>
    // Función global para cerrar modales
    function cerrarModalGlobal(id) {
        document.getElementById(id).classList.remove('show');
    }
    // Mantener compatibilidad si se llama sin 'Global'
    if (typeof cerrarModal === 'undefined') {
        window.cerrarModal = cerrarModalGlobal;
    }

    // Función para toggle de submenús
    function toggleSubmenu(element) {
        const submenu = element.nextElementSibling;
        const arrow = element.querySelector('.menu-arrow');

        if (submenu && submenu.classList.contains('submenu')) {
            submenu.classList.toggle('open');
            if (arrow) {
                arrow.classList.toggle('rotated');
            }
        }
    }

    // Sidebar Toggle Logic
    document.addEventListener('DOMContentLoaded', function () {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const layout = document.querySelector('.layout');

        // Cargar estado guardado
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            layout.classList.add('collapsed');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                layout.classList.toggle('collapsed');
                // Guardar preferencia
                localStorage.setItem('sidebarCollapsed', layout.classList.contains('collapsed'));
            });
        }

        const activeLink = document.querySelector('.menu-link.active');
        if (activeLink) {
            let parent = activeLink.parentElement;
            while (parent) {
                if (parent.classList.contains('submenu')) {
                    parent.classList.add('open');
                    const parentLink = parent.previousElementSibling;
                    if (parentLink) {
                        const arrow = parentLink.querySelector('.menu-arrow');
                        if (arrow) {
                            arrow.classList.add('rotated');
                        }
                    }
                }
                parent = parent.parentElement;
            }
        }
    });
</script>
</body>

</html>