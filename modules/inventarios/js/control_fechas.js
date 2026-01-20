/**
 * Control de Fechas - Prevención de Fechas Futuras
 * Sistema MES Hermen Ltda.
 * 
 * Este script se ejecuta automáticamente para:
 * 1. Establecer la fecha actual en todos los inputs de fecha
 * 2. Hacer los campos de fecha de solo lectura
 * 3. Establecer max="fecha_actual" para prevenir fechas futuras
 */

(function () {
    'use strict';

    /**
     * Obtener fecha actual del servidor
     */
    function getFechaActual() {
        // Usar la fecha del servidor inyectada desde PHP
        if (window.FECHA_SERVIDOR) {
            return window.FECHA_SERVIDOR;
        }

        // Fallback: usar fecha local del navegador
        const hoy = new Date();
        const año = hoy.getFullYear();
        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
        const dia = String(hoy.getDate()).padStart(2, '0');
        return `${año}-${mes}-${dia}`;
    }

    /**
     * Configurar un input de fecha
     */
    function configurarInputFecha(input) {
        const fechaActual = getFechaActual();

        // Establecer fecha actual si está vacío
        if (!input.value) {
            input.value = fechaActual;
        }

        // Establecer máximo a fecha actual (previene fechas futuras)
        input.setAttribute('max', fechaActual);

        // Hacer el campo de solo lectura (opcional, comentar si se quiere permitir fechas pasadas)
        input.setAttribute('readonly', 'readonly');
        input.style.backgroundColor = '#e9ecef';
        input.style.cursor = 'not-allowed';

        // Agregar tooltip explicativo
        input.setAttribute('title', 'La fecha se establece automáticamente a la fecha actual');

        console.log(`✅ Campo de fecha configurado: ${input.id || input.name || 'sin-id'}`);
    }

    /**
     * Buscar y configurar todos los inputs de fecha
     */
    function configurarTodosLosCamposFecha() {
        // Buscar todos los inputs de tipo date
        const inputsFecha = document.querySelectorAll('input[type="date"]');

        if (inputsFecha.length === 0) {
            console.log('⚠️ No se encontraron inputs de tipo date en el DOM actual');
            return;
        }

        console.log(`🔍 Encontrados ${inputsFecha.length} campos de fecha`);

        inputsFecha.forEach(input => {
            configurarInputFecha(input);
        });
    }

    /**
     * Observador de mutaciones para detectar nuevos campos de fecha
     * (útil para modales que se cargan dinámicamente)
     */
    function iniciarObservador() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        // Buscar inputs de fecha en el nodo agregado
                        if (node.tagName === 'INPUT' && node.type === 'date') {
                            configurarInputFecha(node);
                        }

                        // Buscar inputs de fecha dentro del nodo
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

        // Observar cambios en el body
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('👁️ Observador de mutaciones iniciado para detectar nuevos campos de fecha');
    }

    /**
     * Inicialización
     */
    function init() {
        console.log('🚀 Iniciando control de fechas...');

        // Configurar campos existentes
        configurarTodosLosCamposFecha();

        // Iniciar observador para campos dinámicos
        iniciarObservador();

        // Reconfigurar cada 2 segundos por seguridad (para modales que se abren)
        setInterval(configurarTodosLosCamposFecha, 2000);
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // También ejecutar cuando la página esté completamente cargada
    window.addEventListener('load', configurarTodosLosCamposFecha);

})();
