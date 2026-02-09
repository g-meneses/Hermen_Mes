/**
 * IndexedDB Wrapper - PWA Mobile Hermen
 * Gestión de almacenamiento local offline
 */

const DB_NAME = 'HermenMobileDB';
const DB_VERSION = 2; // Incrementado para agregar tipos_inventario

class LocalDB {
    constructor() {
        this.db = null;
        this.ready = this.init();
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);

            request.onsuccess = () => {
                this.db = request.result;
                console.log('[DB] Base de datos abierta, version:', this.db.version);

                // Verificar si necesita actualización de stores
                if (this.needsUpgrade()) {
                    console.warn('[DB] Base de datos desactualizada, eliminando y recreando...');
                    this.db.close();
                    this.resetDatabase().then(() => {
                        // Reintentar apertura
                        const retryRequest = indexedDB.open(DB_NAME, DB_VERSION);
                        retryRequest.onsuccess = () => {
                            this.db = retryRequest.result;
                            resolve(this);
                        };
                        retryRequest.onerror = () => reject(retryRequest.error);
                        retryRequest.onupgradeneeded = (e) => this.handleUpgrade(e);
                    });
                } else {
                    resolve(this);
                }
            };

            request.onupgradeneeded = (event) => this.handleUpgrade(event);
        });
    }

    handleUpgrade(event) {
        const db = event.target.result;

        // Store: Usuarios (para validación offline de PIN)
        if (!db.objectStoreNames.contains('usuarios')) {
            const userStore = db.createObjectStore('usuarios', { keyPath: 'id' });
            userStore.createIndex('pin', 'pin', { unique: false });
        }

        // Store: Productos (catálogo)
        if (!db.objectStoreNames.contains('productos')) {
            const prodStore = db.createObjectStore('productos', { keyPath: 'id' });
            prodStore.createIndex('codigo', 'codigo', { unique: true });
            prodStore.createIndex('nombre', 'nombre', { unique: false });
            prodStore.createIndex('id_tipo_inventario', 'id_tipo_inventario', { unique: false });
        }

        // Store: Áreas
        if (!db.objectStoreNames.contains('areas')) {
            db.createObjectStore('areas', { keyPath: 'id' });
        }

        // Store: Tipos de inventario
        if (!db.objectStoreNames.contains('tipos_inventario')) {
            db.createObjectStore('tipos_inventario', { keyPath: 'id' });
        }

        // Store: Tipos de salida
        if (!db.objectStoreNames.contains('tipos_salida')) {
            db.createObjectStore('tipos_salida', { keyPath: 'id' });
        }

        // Store: Salidas pendientes (offline)
        if (!db.objectStoreNames.contains('salidas_pendientes')) {
            const salidaStore = db.createObjectStore('salidas_pendientes', { keyPath: 'uuid' });
            salidaStore.createIndex('estado', 'estado', { unique: false });
            salidaStore.createIndex('fecha', 'fecha_hora_local', { unique: false });
        }

        // Store: Configuración
        if (!db.objectStoreNames.contains('config')) {
            db.createObjectStore('config', { keyPath: 'key' });
        }

        console.log('[DB] Esquema actualizado a versión', DB_VERSION);
    }

    needsUpgrade() {
        const requiredStores = ['usuarios', 'productos', 'areas', 'tipos_inventario', 'tipos_salida', 'salidas_pendientes', 'config'];
        for (const store of requiredStores) {
            if (!this.db.objectStoreNames.contains(store)) {
                console.warn('[DB] Store faltante:', store);
                return true;
            }
        }
        return false;
    }

    async resetDatabase() {
        return new Promise((resolve, reject) => {
            console.log('[DB] Eliminando base de datos...');
            const deleteRequest = indexedDB.deleteDatabase(DB_NAME);
            deleteRequest.onsuccess = () => {
                console.log('[DB] Base de datos eliminada');
                resolve();
            };
            deleteRequest.onerror = () => reject(deleteRequest.error);
            deleteRequest.onblocked = () => {
                console.warn('[DB] Eliminación bloqueada, reintentando...');
                setTimeout(resolve, 500);
            };
        });
    }


    // =====================================================
    // USUARIOS
    // =====================================================

    async saveUsuarios(usuarios) {
        await this.ready;
        const tx = this.db.transaction('usuarios', 'readwrite');
        const store = tx.objectStore('usuarios');

        // Limpiar y guardar nuevos
        await this._clearStore(store);
        for (const u of usuarios) {
            store.put(u);
        }

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(usuarios.length);
            tx.onerror = () => reject(tx.error);
        });
    }

    async getUsuarioByPin(pin) {
        await this.ready;
        const tx = this.db.transaction('usuarios', 'readonly');
        const store = tx.objectStore('usuarios');
        const index = store.index('pin');

        return new Promise((resolve, reject) => {
            const request = index.get(pin);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    async getUsuarioById(id) {
        await this.ready;
        const tx = this.db.transaction('usuarios', 'readonly');
        const store = tx.objectStore('usuarios');

        return new Promise((resolve, reject) => {
            const request = store.get(id);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // PRODUCTOS
    // =====================================================

    async saveProductos(productos) {
        await this.ready;
        const tx = this.db.transaction('productos', 'readwrite');
        const store = tx.objectStore('productos');

        await this._clearStore(store);
        for (const p of productos) {
            store.put(p);
        }

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(productos.length);
            tx.onerror = () => reject(tx.error);
        });
    }

    async searchProductos(query, idTipoInventario = null) {
        await this.ready;
        const tx = this.db.transaction('productos', 'readonly');
        const store = tx.objectStore('productos');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => {
                let all = request.result;

                // Filtrar por tipo de inventario si se especifica
                if (idTipoInventario) {
                    all = all.filter(p => p.id_tipo_inventario == idTipoInventario);
                }

                const q = query.toLowerCase();
                const filtered = all.filter(p =>
                    p.nombre.toLowerCase().includes(q) ||
                    p.codigo.toLowerCase().includes(q)
                ).slice(0, 50);
                resolve(filtered);
            };
            request.onerror = () => reject(request.error);
        });
    }

    async getProductoByCodigo(codigo) {
        await this.ready;
        const tx = this.db.transaction('productos', 'readonly');
        const store = tx.objectStore('productos');
        const index = store.index('codigo');

        return new Promise((resolve, reject) => {
            const request = index.get(codigo);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    async getProductoById(id) {
        await this.ready;
        const tx = this.db.transaction('productos', 'readonly');
        const store = tx.objectStore('productos');

        return new Promise((resolve, reject) => {
            const request = store.get(id);
            request.onsuccess = () => resolve(request.result || null);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // ÁREAS
    // =====================================================

    async saveAreas(areas) {
        await this.ready;
        const tx = this.db.transaction('areas', 'readwrite');
        const store = tx.objectStore('areas');

        await this._clearStore(store);
        for (const a of areas) {
            store.put(a);
        }

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(areas.length);
            tx.onerror = () => reject(tx.error);
        });
    }

    async getAreas() {
        await this.ready;
        const tx = this.db.transaction('areas', 'readonly');
        const store = tx.objectStore('areas');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // TIPOS DE INVENTARIO
    // =====================================================

    async saveTiposInventario(tipos) {
        await this.ready;

        // Verificar si el store existe
        if (!this.db.objectStoreNames.contains('tipos_inventario')) {
            console.warn('[DB] Store tipos_inventario no existe - ignorando save');
            return 0;
        }

        const tx = this.db.transaction('tipos_inventario', 'readwrite');
        const store = tx.objectStore('tipos_inventario');

        await this._clearStore(store);
        for (const t of tipos) {
            store.put(t);
        }

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(tipos.length);
            tx.onerror = () => reject(tx.error);
        });
    }

    async getTiposInventario() {
        await this.ready;

        // Verificar si el store existe
        if (!this.db.objectStoreNames.contains('tipos_inventario')) {
            console.warn('[DB] Store tipos_inventario no existe, requiere actualización de DB');
            return [];
        }

        const tx = this.db.transaction('tipos_inventario', 'readonly');
        const store = tx.objectStore('tipos_inventario');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // TIPOS DE SALIDA
    // =====================================================

    async saveTiposSalida(tipos) {
        await this.ready;
        const tx = this.db.transaction('tipos_salida', 'readwrite');
        const store = tx.objectStore('tipos_salida');

        await this._clearStore(store);
        for (const t of tipos) {
            store.put(t);
        }

        return new Promise((resolve, reject) => {
            tx.oncomplete = () => resolve(tipos.length);
            tx.onerror = () => reject(tx.error);
        });
    }

    async getTiposSalida() {
        await this.ready;
        const tx = this.db.transaction('tipos_salida', 'readonly');
        const store = tx.objectStore('tipos_salida');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // SALIDAS PENDIENTES
    // =====================================================

    async saveSalidaPendiente(salida) {
        await this.ready;
        const tx = this.db.transaction('salidas_pendientes', 'readwrite');
        const store = tx.objectStore('salidas_pendientes');

        return new Promise((resolve, reject) => {
            const request = store.put(salida);
            request.onsuccess = () => resolve(salida.uuid);
            request.onerror = () => reject(request.error);
        });
    }

    async getSalidasPendientes() {
        await this.ready;
        const tx = this.db.transaction('salidas_pendientes', 'readonly');
        const store = tx.objectStore('salidas_pendientes');
        const index = store.index('estado');

        return new Promise((resolve, reject) => {
            const request = index.getAll('PENDIENTE_SYNC');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getAllSalidas() {
        await this.ready;
        const tx = this.db.transaction('salidas_pendientes', 'readonly');
        const store = tx.objectStore('salidas_pendientes');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => {
                // Ordenar por fecha descendente
                const sorted = request.result.sort((a, b) =>
                    new Date(b.fecha_hora_local) - new Date(a.fecha_hora_local)
                );
                resolve(sorted);
            };
            request.onerror = () => reject(request.error);
        });
    }

    async updateSalidaEstado(uuid, estado, extras = {}) {
        await this.ready;
        const tx = this.db.transaction('salidas_pendientes', 'readwrite');
        const store = tx.objectStore('salidas_pendientes');

        return new Promise((resolve, reject) => {
            const request = store.get(uuid);
            request.onsuccess = () => {
                const salida = request.result;
                if (salida) {
                    salida.estado = estado;
                    Object.assign(salida, extras);
                    store.put(salida);
                }
                resolve(salida);
            };
            request.onerror = () => reject(request.error);
        });
    }

    async deleteSalida(uuid) {
        await this.ready;
        const tx = this.db.transaction('salidas_pendientes', 'readwrite');
        const store = tx.objectStore('salidas_pendientes');

        return new Promise((resolve, reject) => {
            const request = store.delete(uuid);
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // CONFIGURACIÓN
    // =====================================================

    async getConfig(key) {
        await this.ready;
        const tx = this.db.transaction('config', 'readonly');
        const store = tx.objectStore('config');

        return new Promise((resolve, reject) => {
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result?.value);
            request.onerror = () => reject(request.error);
        });
    }

    async setConfig(key, value) {
        await this.ready;
        const tx = this.db.transaction('config', 'readwrite');
        const store = tx.objectStore('config');

        return new Promise((resolve, reject) => {
            const request = store.put({ key, value });
            request.onsuccess = () => resolve(true);
            request.onerror = () => reject(request.error);
        });
    }

    // =====================================================
    // UTILIDADES
    // =====================================================

    async _clearStore(store) {
        return new Promise((resolve, reject) => {
            const request = store.clear();
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
}

// Instancia global
const localDB = new LocalDB();
