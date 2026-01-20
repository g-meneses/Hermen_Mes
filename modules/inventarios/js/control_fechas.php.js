/**
 * Control de Fechas - Versión 2.0
 * Usa la fecha del servidor PHP en lugar de JavaScript del navegador
 * Sistema MES Hermen Ltda.
 */

(function () {
    'use strict';

    // Obtener fecha actual del servidor (pasada desde PHP)
    const FECHA_SERVIDOR = '<?php echo date("Y-m-d"); ?>';

    console.log('📅 Fecha del servidor:', FECHA_SERVIDOR);

    /**
     * Configurar un input de fecha
     */
    function configurarInputFecha(input) {
        // Establecer fecha del servidor
        if (!input.value) {
            input.value = FECHA_SERVIDOR;
        }

        // Establecer máximo a fecha del servidor
        input.setAttribute('max', FECHA_SERVIDOR);

        // Hacer el campo de solo lectura
        input.setAttribute('readonly', 'readonly');
        input.style.backgroundColor = '#e9ecef';
        input.style.cursor = 'not-allowed';

        // Agregar tooltip
        input.setAttribute('title', 'La fecha se establece automáticamente a la fecha actual del servidor');

        console.log(`✅ Campo de fecha configurado: ${input.id || input.name || 'sin-id'} = ${FECHA_SERVIDOR}`);
    }

    /**
     * Buscar y configurar todos los inputs de fecha
     */
    function configurarTodosLosCamposFecha() {
        const inputsFecha = document.querySelectorAll('input[type="date"]');

        if (inputsFecha.length === 0) {
            return;
        }

        console.log(`🔍 Encontrados ${inputsFecha.length} campos de fecha`);

        inputsFecha.forEach(input => {
            configurarInputFecha(input);
        });
    }

    /**
     * Observador de mutaciones para detectar nuevos campos de fecha
     */
    function iniciarObservador() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'INPUT' && node.type === 'date') {
                            configurarInputFecha(node);
                        }

                        const inputsFecha = node.querySelectorAll?.('input[type="date"]');
                        if (inputsFecha && inputsFecha.length > 0) {
                            inputsFecha.forEach(input => {
                                configurarInputFecha(input);
                            });
                        }
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('👁️ Observador de mutaciones iniciado');
    }

    /**
     * Inicialización
     */
    function init() {
        console.log('🚀 Iniciando control de fechas v2.0 (fecha del servidor)...');

        configurarTodosLosCamposFecha();
        iniciarObservador();

        // Reconfigurar cada 2 segundos
        setInterval(configurarTodosLosCamposFecha, 2000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('load', configurarTodosLosCamposFecha);

})();
