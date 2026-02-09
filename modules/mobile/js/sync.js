/**
 * Sync Manager - PWA Mobile Hermen
 * Gestión de sincronización offline/online
 */

const API_BASE = '/mes_hermen/api/mobile';
const AUTO_SYNC_INTERVAL = 20 * 60 * 1000; // 20 minutos en milisegundos

class SyncManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        this.listeners = [];
        this.autoSyncTimer = null;

        this.init();
    }

    init() {
        // Escuchar cambios de conexión
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Escuchar mensajes del Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', event => {
                if (event.data.type === 'SYNC_REQUIRED') {
                    this.syncPendientes();
                }
            });
        }

        // Iniciar sincronización automática cada 20 minutos
        this.startAutoSync();
    }

    // =====================================================
    // SINCRONIZACIÓN AUTOMÁTICA CADA 20 MINUTOS
    // =====================================================

    startAutoSync() {
        // Limpiar timer anterior si existe
        if (this.autoSyncTimer) {
            clearInterval(this.autoSyncTimer);
        }

        console.log('[Sync] Auto-sync configurado cada 20 minutos');

        this.autoSyncTimer = setInterval(async () => {
            // Solo sincronizar si hay conexión y hay pendientes
            if (!this.isOnline) {
                console.log('[AutoSync] Sin conexión, omitiendo...');
                return;
            }

            if (this.syncInProgress) {
                console.log('[AutoSync] Sincronización en progreso, omitiendo...');
                return;
            }

            // Verificar si hay pendientes antes de sincronizar
            const pendientes = await localDB.getSalidasPendientes();
            if (pendientes.length === 0) {
                console.log('[AutoSync] Todo sincronizado, nada que hacer');
                return;
            }

            console.log(`[AutoSync] ${pendientes.length} pendientes, sincronizando...`);
            await this.syncPendientes();
        }, AUTO_SYNC_INTERVAL);
    }

    stopAutoSync() {
        if (this.autoSyncTimer) {
            clearInterval(this.autoSyncTimer);
            this.autoSyncTimer = null;
            console.log('[Sync] Auto-sync detenido');
        }
    }

    handleOnline() {
        console.log('[Sync] Conexión restaurada');
        this.isOnline = true;
        this.notifyListeners('online');

        // Auto-sync al volver online
        setTimeout(() => this.syncPendientes(), 1000);
    }

    handleOffline() {
        console.log('[Sync] Sin conexión');
        this.isOnline = false;
        this.notifyListeners('offline');
    }

    onStatusChange(callback) {
        this.listeners.push(callback);
    }

    notifyListeners(status) {
        this.listeners.forEach(cb => cb(status));
    }

    // =====================================================
    // CARGAR CATÁLOGOS DESDE SERVIDOR
    // =====================================================

    async loadCatalogs() {
        try {
            const response = await fetch(`${API_BASE}/catalogos.php?action=all`);

            if (!response.ok) {
                throw new Error('Error cargando catálogos');
            }

            const data = await response.json();

            if (data.success) {
                // Guardar en IndexedDB
                await localDB.saveUsuarios(data.catalogs.usuarios);
                await localDB.saveProductos(data.catalogs.productos);
                await localDB.saveAreas(data.catalogs.areas);
                await localDB.saveTiposInventario(data.catalogs.tipos_inventario || []);
                await localDB.saveTiposSalida(data.catalogs.tipos_salida);
                await localDB.setConfig('catalogos_version', data.version);

                console.log('[Sync] Catálogos cargados:', data.totals);
                return data.totals;
            }

            throw new Error(data.message || 'Error en respuesta');

        } catch (error) {
            console.error('[Sync] Error cargando catálogos:', error);

            // Verificar si hay catálogos locales
            const version = await localDB.getConfig('catalogos_version');
            if (version) {
                console.log('[Sync] Usando catálogos locales de:', version);
                return { cached: true, version };
            }

            throw error;
        }
    }

    // =====================================================
    // AUTENTICACIÓN
    // =====================================================

    async authenticatePin(pin) {
        // Intentar online primero
        if (this.isOnline) {
            try {
                const response = await fetch(`${API_BASE}/auth.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pin })
                });

                const data = await response.json();

                if (data.success) {
                    return { success: true, user: data.user, mode: 'online' };
                }

                return { success: false, message: data.message };

            } catch (error) {
                console.log('[Sync] Auth online falló, intentando offline');
            }
        }

        // Fallback offline
        const user = await localDB.getUsuarioByPin(pin);

        if (user) {
            return {
                success: true,
                user: {
                    id: user.id,
                    codigo: user.codigo,
                    nombre: user.nombre,
                    rol: user.rol,
                    area: user.area
                },
                mode: 'offline'
            };
        }

        return { success: false, message: 'PIN no encontrado' };
    }

    // =====================================================
    // CREAR SALIDA
    // =====================================================

    async crearSalida(salidaData) {
        const uuid = localDB.generateUUID();
        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

        const salida = {
            uuid,
            ...salidaData,
            fecha_hora_local: now,
            estado: 'PENDIENTE_SYNC',
            dispositivo_info: navigator.userAgent
        };

        // Guardar localmente primero
        await localDB.saveSalidaPendiente(salida);
        console.log('[Sync] Salida guardada localmente:', uuid);

        // Intentar sincronizar si hay conexión
        if (this.isOnline) {
            await this.syncSalida(salida);
        }

        return { uuid, estado: salida.estado };
    }

    // =====================================================
    // SINCRONIZAR UNA SALIDA
    // =====================================================

    async syncSalida(salida) {
        try {
            const response = await fetch(`${API_BASE}/salidas.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'crear',
                    uuid_local: salida.uuid,
                    tipo_salida: salida.tipo_salida,
                    id_area_destino: salida.id_area_destino,
                    observaciones: salida.observaciones,
                    usuario_entrega: salida.usuario_entrega,
                    usuario_recibe: salida.usuario_recibe,
                    fecha_hora_local: salida.fecha_hora_local,
                    items: salida.items,
                    dispositivo_info: salida.dispositivo_info
                })
            });

            const data = await response.json();

            if (data.success) {
                // Usar el estado que devuelve el servidor (puede ser SINCRONIZADA, OBSERVADA, RECHAZADA)
                const estadoFinal = data.estado_sync || 'SINCRONIZADA';

                await localDB.updateSalidaEstado(salida.uuid, estadoFinal, {
                    id_servidor: data.id_salida_movil,
                    sync_resultado: data.sync_resultado
                });

                console.log(`[Sync] Salida ${salida.uuid} -> ${estadoFinal}`);

                // Considerar exitoso aunque sea OBSERVADA (ya está en servidor)
                return estadoFinal !== 'RECHAZADA';
            }

            console.error('[Sync] Error al sincronizar:', data.message);
            return false;

        } catch (error) {
            console.error('[Sync] Error de red al sincronizar:', error);
            return false;
        }
    }

    // =====================================================
    // SINCRONIZAR TODAS LAS PENDIENTES
    // =====================================================

    async syncPendientes() {
        if (this.syncInProgress || !this.isOnline) {
            return { synced: 0, failed: 0 };
        }

        this.syncInProgress = true;
        console.log('[Sync] Iniciando sincronización de pendientes...');

        try {
            const pendientes = await localDB.getSalidasPendientes();

            if (pendientes.length === 0) {
                console.log('[Sync] No hay salidas pendientes');
                return { synced: 0, failed: 0 };
            }

            console.log(`[Sync] ${pendientes.length} salidas pendientes`);

            let synced = 0;
            let failed = 0;

            for (const salida of pendientes) {
                const success = await this.syncSalida(salida);
                if (success) {
                    synced++;
                } else {
                    failed++;
                }
            }

            console.log(`[Sync] Resultado: ${synced} sincronizadas, ${failed} fallidas`);
            this.notifyListeners('sync-complete', { synced, failed });

            return { synced, failed };

        } finally {
            this.syncInProgress = false;
        }
    }

    // =====================================================
    // OBTENER ESTADO
    // =====================================================

    async getStatus() {
        const pendientes = await localDB.getSalidasPendientes();

        return {
            online: this.isOnline,
            pendientes: pendientes.length,
            syncInProgress: this.syncInProgress
        };
    }

    // =====================================================
    // HISTORIAL
    // =====================================================

    async getHistorial(fecha = null) {
        let serverSalidas = [];
        let localPendientes = [];

        // Siempre obtener las pendientes locales
        localPendientes = await localDB.getSalidasPendientes();
        console.log('[Sync] Pendientes locales:', localPendientes.length);

        // Obtener del servidor si online
        if (this.isOnline) {
            try {
                const params = fecha ? `?fecha=${fecha}` : '';
                const response = await fetch(`${API_BASE}/salidas.php?action=historial${params}`);
                const data = await response.json();

                if (data.success) {
                    serverSalidas = data.salidas || [];
                }
            } catch (error) {
                console.log('[Sync] Error obteniendo historial online, usando solo datos locales');
            }
        } else {
            // Si offline, usar todas las salidas locales
            const allLocal = await localDB.getAllSalidas();
            if (fecha) {
                return allLocal.filter(s => s.fecha_hora_local.startsWith(fecha));
            }
            return allLocal;
        }

        // Convertir pendientes locales al formato del historial
        const pendientesFormateadas = localPendientes.map(s => ({
            id: s.uuid,
            uuid_local: s.uuid,
            tipo_salida: s.tipo_salida,
            fecha_hora_local: s.fecha_hora_local,
            estado_sync: s.estado || 'PENDIENTE_SYNC',
            total_items: s.items?.length || 0,
            items: s.items,
            // Marcar como local para identificación
            _isLocal: true
        }));

        // Filtrar del servidor las que ya están en pendientes (evitar duplicados)
        const uuidsLocales = new Set(localPendientes.map(s => s.uuid));
        const serverFiltradas = serverSalidas.filter(s => !uuidsLocales.has(s.uuid_local));

        // Combinar: pendientes primero, luego las del servidor
        const combined = [...pendientesFormateadas, ...serverFiltradas];

        // Ordenar por fecha descendente
        combined.sort((a, b) =>
            new Date(b.fecha_hora_local) - new Date(a.fecha_hora_local)
        );

        // Filtrar por fecha si se especifica
        if (fecha) {
            return combined.filter(s => s.fecha_hora_local.startsWith(fecha));
        }

        return combined;
    }
}

// Instancia global
const syncManager = new SyncManager();
