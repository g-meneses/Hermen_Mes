/**
 * App Principal - PWA Mobile Hermen
 * LÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³gica de navegaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n y flujos de pantalla
 */

class MobileApp {
    constructor() {
        this.currentScreen = 'splash';
        this.currentUser = null;
        this.currentPin = '';
        this.receptorUser = null;

        // Estado de la salida actual
        this.salida = {
            tipoInventario: null,
            tipoInventarioId: null,
            tipoInventarioNombre: '',
            tipo: null,
            tipoNombre: '',
            items: [],
            area: null,
            areaNombre: ''
        };

        this.init();
    }

    async init() {
        console.log('[App] Inicializando...');

        // Esperar a que DB estÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© lista
        await localDB.ready;

        // Configurar listeners de conexiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n
        syncManager.onStatusChange((status) => this.updateConnectionUI(status));

        // Cargar catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡logos y mostrar splash
        await this.showSplash();

        // Inicializar event listeners
        this.initEventListeners();
    }

    // =====================================================
    // SPLASH SCREEN
    // =====================================================

    async showSplash() {
        this.showScreen('splash');

        const statusEl = document.getElementById('connection-status');
        const pendingEl = document.getElementById('pending-count');

        try {
            // Verificar conexiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n
            statusEl.querySelector('.status-text').textContent = 'Conectando...';

            // Cargar catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡logos
            const result = await syncManager.loadCatalogs();

            statusEl.classList.add('online');
            statusEl.querySelector('.status-text').textContent = 'Conectado';

        } catch (error) {
            console.log('[App] Modo offline');
            statusEl.classList.remove('online');
            statusEl.classList.add('offline');
            statusEl.querySelector('.status-text').textContent = 'Modo Offline';
        }

        // Mostrar pendientes
        const status = await syncManager.getStatus();
        if (status.pendientes > 0) {
            pendingEl.style.display = 'flex';
            pendingEl.querySelector('span').textContent = `${status.pendientes} pendientes`;
        }

        const savedSession = await localDB.getCurrentSession();

        setTimeout(() => {
            if (savedSession) {
                this.currentUser = savedSession;
                this.showMenu();
                return;
            }

            this.showScreen('login');

            if (!syncManager.isOnline) {
                this.showLoginError('Necesitas conexion para iniciar sesion por primera vez.');
            }
        }, 2000);
    }

    // =====================================================
    // NAVEGACIÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN
    // =====================================================

    showScreen(screenId) {
        document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
        document.getElementById(`screen-${screenId}`).classList.add('active');
        this.currentScreen = screenId;
        console.log('[App] Pantalla:', screenId);
    }

    // =====================================================
    // EVENT LISTENERS
    // =====================================================

    initEventListeners() {
        // ===== LOGIN =====
        document.querySelectorAll('#screen-login .key').forEach(key => {
            key.addEventListener('click', () => this.handleLoginKey(key.dataset.value));
        });

        // ===== MENÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ =====
        document.getElementById('btn-nueva-salida').addEventListener('click', () => {
            this.resetSalida();
            this.showTiposInventario();
        });

        document.getElementById('btn-historial').addEventListener('click', () => this.showHistorial());
        document.getElementById('btn-sincronizar').addEventListener('click', () => this.doSync());
        document.getElementById('btn-salir').addEventListener('click', () => this.logout());

        // ===== TIPO DE INVENTARIO =====
        document.getElementById('btn-back-tipo-inv').addEventListener('click', () => this.showScreen('menu'));

        // ===== TIPO DE SALIDA =====
        document.getElementById('btn-back-tipo').addEventListener('click', () => this.showScreen('tipo-inventario'));

        // ===== CARRITO =====
        document.getElementById('btn-back-carrito').addEventListener('click', () => this.showScreen('tipo'));
        document.getElementById('btn-agregar-producto').addEventListener('click', () => this.showProductoModal());
        document.getElementById('btn-continuar-destino').addEventListener('click', () => this.showDestino());

        // ===== PRODUCTO MODAL =====
        document.getElementById('btn-close-producto').addEventListener('click', () => this.hideProductoModal());
        document.getElementById('producto-search').addEventListener('input', (e) => this.searchProductos(e.target.value));
        document.getElementById('btn-cantidad-minus').addEventListener('click', () => this.adjustCantidad(-1));
        document.getElementById('btn-cantidad-plus').addEventListener('click', () => this.adjustCantidad(1));
        document.getElementById('btn-add-to-cart').addEventListener('click', () => this.addToCart());

        // ===== DESTINO =====
        document.getElementById('btn-back-destino').addEventListener('click', () => this.showScreen('carrito'));

        // ===== FIRMA =====
        document.getElementById('btn-back-firma').addEventListener('click', () => this.showScreen('destino'));
        document.getElementById('btn-validar-firma').addEventListener('click', () => this.validateFirma());

        // ===== CONFIRMACIÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN =====
        document.getElementById('btn-back-confirmacion').addEventListener('click', () => this.showScreen('firma'));
        document.getElementById('btn-cancelar').addEventListener('click', () => this.cancelarSalida());
        document.getElementById('btn-confirmar').addEventListener('click', () => this.confirmarSalida());

        // ===== COMPROBANTE =====
        document.getElementById('btn-nueva').addEventListener('click', () => {
            this.resetSalida();
            this.showTiposInventario();
        });
        document.getElementById('btn-menu').addEventListener('click', () => this.showScreen('menu'));

        // ===== HISTORIAL =====
        document.getElementById('btn-back-historial').addEventListener('click', () => this.showScreen('menu'));

        // ===== MODAL DETALLE SALIDA =====
        document.getElementById('btn-close-detalle').addEventListener('click', () => this.closeDetalleSalida());
        document.getElementById('modal-detalle-salida').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.closeDetalleSalida();
        });
    }

    // =====================================================
    // LOGIN
    // =====================================================

    handleLoginKey(value) {
        const dots = document.querySelectorAll('#screen-login .pin-dot');
        const errorEl = document.getElementById('login-error');

        if (value === 'clear') {
            this.currentPin = this.currentPin.slice(0, -1);
        } else if (value === 'enter') {
            if (this.currentPin.length === 4) {
                this.doLogin();
            }
            return;
        } else if (this.currentPin.length < 4) {
            this.currentPin += value;
        }

        // Actualizar dots
        dots.forEach((dot, i) => {
            dot.classList.toggle('filled', i < this.currentPin.length);
        });

        errorEl.style.display = 'none';

        // Auto-submit cuando hay 4 dÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­gitos
        if (this.currentPin.length === 4) {
            setTimeout(() => this.doLogin(), 200);
        }
    }

    async doLogin() {
        try {
            const result = await syncManager.authenticatePin(this.currentPin);

            if (result.success) {
                this.currentUser = result.user;
                this.currentPin = '';
                document.querySelectorAll('#screen-login .pin-dot').forEach(d => d.classList.remove('filled'));
                this.showLoginError('');
                this.showMenu();
            } else {
                this.showLoginError(result.message || 'PIN incorrecto');
                this.currentPin = '';
                document.querySelectorAll('#screen-login .pin-dot').forEach(d => d.classList.remove('filled'));
            }
        } catch (error) {
            this.showLoginError('Error de autenticaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n');
        }
    }

    async logout() {
        this.currentUser = null;
        this.currentPin = '';
        await localDB.clearCurrentSession();
        this.resetSalida();
        document.querySelectorAll('#screen-login .pin-dot').forEach(d => d.classList.remove('filled'));
        this.showLoginError(syncManager.isOnline ? '' : 'Necesitas conexiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n para iniciar sesiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n.');
        this.showScreen('login');
    }

    showLoginError(message) {
        const errorEl = document.getElementById('login-error');
        errorEl.textContent = message;
        errorEl.style.display = message ? 'block' : 'none';
    }

    // =====================================================
    // MENÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡
    // =====================================================

    showMenu() {
        document.getElementById('user-name').textContent = this.currentUser.nombre;
        document.getElementById('user-role').textContent = this.currentUser.rol;

        this.updateConnectionUI(syncManager.isOnline ? 'online' : 'offline');
        this.updatePendingBadge();

        this.showScreen('menu');
    }

    async updatePendingBadge() {
        const status = await syncManager.getStatus();
        const badge = document.getElementById('sync-badge');

        if (status.pendientes > 0) {
            badge.textContent = status.pendientes;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    updateConnectionUI(status) {
        const badge = document.getElementById('menu-connection');

        if (status === 'online') {
            badge.classList.add('online');
            badge.classList.remove('offline');
        } else {
            badge.classList.remove('online');
            badge.classList.add('offline');
        }
    }

    // =====================================================
    // TIPOS DE INVENTARIO
    // =====================================================

    async showTiposInventario() {
        const container = document.getElementById('tipos-inventario-container');
        let tipos = await localDB.getTiposInventario();

        console.log('[App] Tipos de inventario cargados:', tipos);

        // Excluir Productos en Proceso (WIP)
        tipos = tipos.filter(t => t.codigo !== 'WIP');

        if (tipos.length === 0) {
            // Si no hay tipos, intentar recargar catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡logos
            console.log('[App] No hay tipos de inventario, recargando catÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡logos...');
            await syncManager.loadCatalogs();
            tipos = await localDB.getTiposInventario();
            tipos = tipos.filter(t => t.codigo !== 'WIP');
        }

        container.innerHTML = tipos.map(t => `
            <button class="tipo-btn" data-id="${t.id}" data-codigo="${t.codigo}" data-nombre="${t.nombre}">
                <div class="tipo-icon" style="background: ${t.color}20; color: ${t.color}">
                    <i class="fas ${t.icono}"></i>
                </div>
                <span>${t.nombre}</span>
            </button>
        `).join('');

        container.querySelectorAll('.tipo-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.salida.tipoInventarioId = parseInt(btn.dataset.id);
                this.salida.tipoInventario = btn.dataset.codigo;
                this.salida.tipoInventarioNombre = btn.dataset.nombre;
                this.showTiposSalida();
            });
        });

        this.showScreen('tipo-inventario');
    }

    // =====================================================
    // TIPOS DE SALIDA
    // =====================================================

    async showTiposSalida() {
        // Mostrar badge del tipo de inventario seleccionado
        document.getElementById('tipo-inv-badge').textContent = this.salida.tipoInventario;

        const container = document.getElementById('tipos-salida-container');
        const tipos = await localDB.getTiposSalida();

        container.innerHTML = tipos.map(t => `
            <button class="tipo-btn" data-tipo="${t.id}" data-nombre="${t.nombre}">
                <div class="tipo-icon" style="background: ${t.color}20; color: ${t.color}">
                    <i class="fas ${t.icono}"></i>
                </div>
                <span>${t.nombre}</span>
            </button>
        `).join('');

        container.querySelectorAll('.tipo-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.salida.tipo = btn.dataset.tipo;
                this.salida.tipoNombre = btn.dataset.nombre;
                this.showCarrito();
            });
        });

        this.showScreen('tipo');
    }

    // =====================================================
    // CARRITO
    // =====================================================

    showCarrito() {
        document.getElementById('carrito-tipo-badge').textContent = `${this.salida.tipoInventario} - ${this.salida.tipoNombre}`;
        this.updateCarritoUI();
        this.showScreen('carrito');
    }

    updateCarritoUI() {
        const list = document.getElementById('carrito-list');
        const empty = document.getElementById('carrito-empty');
        const btnContinuar = document.getElementById('btn-continuar-destino');

        if (this.salida.items.length === 0) {
            empty.style.display = 'flex';
            list.innerHTML = '';
            btnContinuar.disabled = true;
        } else {
            empty.style.display = 'none';
            list.innerHTML = this.salida.items.map((item, i) => `
                <li class="carrito-item">
                    <div class="carrito-item-info">
                        <strong>${item.nombre}</strong>
                        <small>${item.codigo}</small>
                    </div>
                    <span class="carrito-item-cantidad">${item.cantidad} ${item.unidad}</span>
                    <button class="carrito-item-remove" data-index="${i}">
                        <i class="fas fa-trash"></i>
                    </button>
                </li>
            `).join('');

            list.querySelectorAll('.carrito-item-remove').forEach(btn => {
                btn.addEventListener('click', () => this.removeFromCart(parseInt(btn.dataset.index)));
            });

            btnContinuar.disabled = false;
        }
    }

    removeFromCart(index) {
        this.salida.items.splice(index, 1);
        this.updateCarritoUI();
    }

    // =====================================================
    // PRODUCTO MODAL
    // =====================================================

    selectedProducto = null;

    showProductoModal() {
        this.selectedProducto = null;
        document.getElementById('producto-search').value = '';
        document.getElementById('productos-list').innerHTML = '';
        document.getElementById('producto-selected').style.display = 'none';
        this.showScreen('producto');
        document.getElementById('producto-search').focus();
    }

    hideProductoModal() {
        this.showScreen('carrito');
    }

    async searchProductos(query) {
        if (query.length < 2) {
            document.getElementById('productos-list').innerHTML = '';
            return;
        }

        // Filtrar por tipo de inventario seleccionado
        const productos = await localDB.searchProductos(query, this.salida.tipoInventarioId);
        const list = document.getElementById('productos-list');

        list.innerHTML = productos.map(p => {
            const stockClass = p.stock <= 0 ? 'critical' : (p.stock <= p.stock_minimo ? 'low' : '');
            return `
                <div class="producto-item" data-id="${p.id}">
                    <div class="producto-item-info">
                        <strong>${p.nombre}</strong>
                        <small>${p.codigo}</small>
                    </div>
                    <div class="producto-stock ${stockClass}">
                        <strong>${p.stock}</strong>
                        <small>${p.unidad || 'uds'}</small>
                    </div>
                </div>
            `;
        }).join('');

        list.querySelectorAll('.producto-item').forEach(item => {
            item.addEventListener('click', () => this.selectProducto(parseInt(item.dataset.id)));
        });
    }

    async selectProducto(id) {
        const producto = await localDB.getProductoById(id);
        if (!producto) return;

        this.selectedProducto = producto;

        document.getElementById('selected-producto-nombre').textContent = producto.nombre;
        document.getElementById('selected-producto-codigo').textContent = producto.codigo;
        document.getElementById('selected-producto-stock').textContent = producto.stock;
        document.getElementById('selected-producto-unidad').textContent = producto.unidad || 'uds';
        document.getElementById('cantidad-unidad').textContent = producto.unidad || 'uds';
        document.getElementById('producto-cantidad').value = '1';

        document.getElementById('productos-list').innerHTML = '';
        document.getElementById('producto-search').value = '';
        document.getElementById('producto-selected').style.display = 'block';

        // Marcar como seleccionado
        document.querySelectorAll('.producto-item').forEach(i => i.classList.remove('selected'));
    }

    adjustCantidad(delta) {
        const input = document.getElementById('producto-cantidad');
        let val = parseFloat(input.value) || 0;
        val = Math.max(0.01, val + delta);
        input.value = val.toFixed(2);
    }

    addToCart() {
        if (!this.selectedProducto) return;

        const cantidad = parseFloat(document.getElementById('producto-cantidad').value) || 0;
        if (cantidad <= 0) {
            this.toast('Ingresa una cantidad vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡lida', 'warning');
            return;
        }

        // Calcular cantidad total en carrito para este producto
        const existenteIdx = this.salida.items.findIndex(i => i.id === this.selectedProducto.id);
        const cantidadEnCarrito = existenteIdx >= 0 ? this.salida.items[existenteIdx].cantidad : 0;
        const cantidadTotal = cantidadEnCarrito + cantidad;
        const stockDisponible = parseFloat(this.selectedProducto.stock) || 0;

        // Validar si excede el stock
        if (cantidadTotal > stockDisponible) {
            const mensaje = cantidadEnCarrito > 0
                ? `ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Stock insuficiente\n\nProducto: ${this.selectedProducto.nombre}\nStock disponible: ${stockDisponible}\nYa en carrito: ${cantidadEnCarrito}\nIntentando agregar: ${cantidad}\nTotal: ${cantidadTotal}\n\nÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Deseas continuar de todos modos?`
                : `ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Stock insuficiente\n\nProducto: ${this.selectedProducto.nombre}\nStock disponible: ${stockDisponible}\nCantidad solicitada: ${cantidad}\n\nÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Deseas continuar de todos modos?`;

            if (!confirm(mensaje)) {
                return;
            }
            // Si confirma, continÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºa pero mostrarÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ advertencia
            this.toast('ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¯ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¸ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Cantidad excede stock disponible', 'warning');
        }

        // Agregar al carrito
        if (existenteIdx >= 0) {
            this.salida.items[existenteIdx].cantidad += cantidad;
        } else {
            this.salida.items.push({
                id: this.selectedProducto.id,
                id_inventario: this.selectedProducto.id,
                codigo: this.selectedProducto.codigo,
                nombre: this.selectedProducto.nombre,
                cantidad: cantidad,
                unidad: this.selectedProducto.unidad || 'uds',
                stock_referencial: this.selectedProducto.stock
            });
        }

        this.toast('Producto agregado', 'success');
        this.hideProductoModal();
        this.updateCarritoUI();
    }

    // =====================================================
    // DESTINO
    // =====================================================

    async showDestino() {
        const container = document.getElementById('areas-container');
        const areas = await localDB.getAreas();

        container.innerHTML = areas.map(a => `
            <button class="area-btn" data-id="${a.id}" data-nombre="${a.nombre}">
                <i class="fas fa-warehouse"></i>
                <span>${a.nombre}</span>
            </button>
        `).join('');

        container.querySelectorAll('.area-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.salida.area = parseInt(btn.dataset.id);
                this.salida.areaNombre = btn.dataset.nombre;
                this.showFirma();
            });
        });

        this.showScreen('destino');
    }

    // =====================================================
    // FIRMA
    // =====================================================

    async showFirma() {
        this.receptorUser = null;
        const errorEl = document.getElementById('firma-error');
        const selectEl = document.getElementById('receptor-select');
        const usuarios = await localDB.getUsuarios();
        const receptores = usuarios.filter(u => u.id !== this.currentUser.id);

        selectEl.innerHTML = `
            <option value="">Selecciona a quien recibe</option>
            ${receptores.map(u => `
                <option value="${u.id}">${u.nombre}${u.area ? ` - ${u.area}` : ''}</option>
            `).join('')}
        `;

        errorEl.style.display = 'none';

        if (receptores.length === 0) {
            errorEl.textContent = 'No hay usuarios sincronizados para confirmar la recepciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n.';
            errorEl.style.display = 'block';
        }

        this.showScreen('firma');
    }

    async validateFirma() {
        const errorEl = document.getElementById('firma-error');
        const receptorId = parseInt(document.getElementById('receptor-select').value, 10);

        if (!receptorId) {
            errorEl.textContent = 'Selecciona quiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â©n recibe el material.';
            errorEl.style.display = 'block';
            return;
        }

        const receptor = await localDB.getUsuarioById(receptorId);

        if (!receptor) {
            errorEl.textContent = 'El usuario seleccionado no estÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ disponible sin conexiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n.';
            errorEl.style.display = 'block';
            return;
        }

        if (receptor.id === this.currentUser.id) {
            errorEl.textContent = 'No puede firmar la misma persona que entrega';
            errorEl.style.display = 'block';
            return;
        }

        this.receptorUser = receptor;
        this.showConfirmacion();
    }

    // =====================================================
    // CONFIRMACIÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN
    // =====================================================

    showConfirmacion() {
        const now = new Date();

        document.getElementById('resumen-tipo').textContent = this.salida.tipoNombre;
        document.getElementById('resumen-destino').textContent = this.salida.areaNombre;
        document.getElementById('resumen-entrega').textContent = this.currentUser.nombre;
        document.getElementById('resumen-recibe').textContent = this.receptorUser.nombre;
        document.getElementById('resumen-hora').textContent = now.toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
        document.getElementById('resumen-total-items').textContent = this.salida.items.length;

        document.getElementById('resumen-productos').innerHTML = this.salida.items.map(item => `
            <li>
                <span>${item.nombre}</span>
                <strong>${item.cantidad} ${item.unidad}</strong>
            </li>
        `).join('');

        this.showScreen('confirmacion');
    }

    cancelarSalida() {
        if (confirm('ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¿Cancelar esta salida?')) {
            this.resetSalida();
            this.showScreen('menu');
        }
    }

    async confirmarSalida() {
        const btnConfirmar = document.getElementById('btn-confirmar');
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        try {
            const result = await syncManager.crearSalida({
                tipo_salida: this.salida.tipo,
                tipo_salida_nombre: this.salida.tipoNombre,
                tipo_inventario: this.salida.tipoInventario,
                tipo_inventario_nombre: this.salida.tipoInventarioNombre,
                id_area_destino: this.salida.area,
                area_nombre: this.salida.areaNombre,
                usuario_entrega: this.currentUser.id,
                usuario_entrega_nombre: this.currentUser.nombre,
                usuario_recibe: this.receptorUser.id,
                usuario_recibe_nombre: this.receptorUser.nombre,
                items: this.salida.items.map(i => ({
                    id_inventario: i.id_inventario,
                    nombre: i.nombre,
                    cantidad: i.cantidad,
                    unidad: i.unidad,
                    stock_referencial: i.stock_referencial
                }))
            });

            this.showComprobante(result);

        } catch (error) {
            console.error('[App] Error al confirmar:', error);
            this.toast('Error al guardar la salida', 'error');
        } finally {
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="fas fa-check"></i> Confirmar Salida';
        }
    }

    // =====================================================
    // COMPROBANTE
    // =====================================================

    showComprobante(result) {
        const now = new Date();

        document.getElementById('comprobante-uuid').textContent = result.uuid;
        document.getElementById('comprobante-fecha').textContent = now.toLocaleString('es-BO');
        document.getElementById('comprobante-items').textContent = `${this.salida.items.length} productos`;

        const estadoBadge = document.getElementById('comprobante-estado');
        if (result.estado === 'SINCRONIZADA') {
            estadoBadge.className = 'estado-badge sincronizada';
            estadoBadge.innerHTML = '<i class="fas fa-check-circle"></i> Sincronizada';
        } else {
            estadoBadge.className = 'estado-badge pendiente';
            estadoBadge.innerHTML = '<i class="fas fa-clock"></i> Pendiente de Sincronizar';
        }

        this.showScreen('comprobante');
        this.updatePendingBadge();
    }

    // =====================================================
    // HISTORIAL
    // =====================================================

    async showHistorial() {
        const list = document.getElementById('historial-list');
        const empty = document.getElementById('historial-empty');

        // Obtener todas las salidas locales
        const salidasLocales = await localDB.getAllSalidas();

        // Obtener del servidor si hay conexiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n
        let salidasServidor = [];
        if (syncManager.isOnline) {
            try {
                salidasServidor = await syncManager.getHistorial();
            } catch (e) {
                console.log('[App] Error obteniendo historial del servidor');
            }
        }

        // Combinar y eliminar duplicados (por UUID)
        const uuidsServidor = new Set(salidasServidor.map(s => s.uuid_local));
        const salidas = [
            ...salidasServidor,
            ...salidasLocales.filter(s => !uuidsServidor.has(s.uuid))
        ];

        // Ordenar por fecha descendente
        salidas.sort((a, b) => {
            const fechaA = new Date(a.fecha_hora_local || a.createdAt || 0);
            const fechaB = new Date(b.fecha_hora_local || b.createdAt || 0);
            return fechaB - fechaA;
        });

        if (!salidas || salidas.length === 0) {
            empty.style.display = 'flex';
            list.innerHTML = '';
        } else {
            empty.style.display = 'none';
            list.innerHTML = salidas.map(s => {
                const fecha = new Date(s.fecha_hora_local || s.createdAt);
                const fechaStr = fecha.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit' });
                const hora = fecha.toLocaleTimeString('es-BO', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const estado = s.estado_sync || s.estado || 'PENDIENTE_SYNC';
                const estadoClass = estado === 'SINCRONIZADA' ? 'sincronizada' :
                    estado === 'OBSERVADA' ? 'observada' :
                        estado === 'RECHAZADA' ? 'rechazada' : 'pendiente';
                const estadoText = estado === 'SINCRONIZADA' ? 'Sincronizada' :
                    estado === 'OBSERVADA' ? 'Observada' :
                        estado === 'RECHAZADA' ? 'Rechazada' : 'Pendiente';

                // Color del icono segÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âºn estado
                const iconColor = estado === 'SINCRONIZADA' ? '#48bb78' :
                    estado === 'OBSERVADA' ? '#f6ad55' :
                        estado === 'RECHAZADA' ? '#fc8181' : '#4facfe';
                const iconBg = estado === 'SINCRONIZADA' ? '#48bb7820' :
                    estado === 'OBSERVADA' ? '#f6ad5520' :
                        estado === 'RECHAZADA' ? '#fc818120' : '#4facfe20';

                // Mostrar motivo de rechazo si existe
                const motivoRechazo = s.motivo_rechazo || s.motivoRechazo || '';
                const motivoHtml = motivoRechazo ?
                    `<div class="historial-motivo">${motivoRechazo}</div>` : '';

                const tipoInvNombre = s.tipo_inventario_nombre || s.tipoInventarioNombre || '';
                const tipoInvCodigo = s.tipo_inventario || s.tipoInventario || '';
                const tipoSalidaLabel = {
                    'PRODUCCION': 'ProducciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n', 'MUESTRA': 'Muestra',
                    'CONSUMO_INTERNO': 'Consumo Interno', 'MERMA': 'Merma', 'AJUSTE': 'Ajuste'
                };
                const tipoSalidaNombre = s.tipo_salida_nombre || s.tipoNombre ||
                    tipoSalidaLabel[s.tipo_salida] || s.tipo_salida || 'Salida';
                const titulo = tipoInvNombre
                    ? `${tipoInvNombre} - ${tipoSalidaNombre}`
                    : tipoSalidaNombre;

                return `
                    <li class="historial-item ${estadoClass}" data-idx="${salidas.indexOf(s)}" style="cursor:pointer">
                        <div class="historial-icon" style="background: ${iconBg}; color: ${iconColor};">
                            <i class="fas ${estado === 'SINCRONIZADA' ? 'fa-check-circle' :
                        estado === 'OBSERVADA' ? 'fa-exclamation-triangle' :
                            estado === 'RECHAZADA' ? 'fa-times-circle' : 'fa-clock'}"></i>
                        </div>
                        <div class="historial-info">
                            <strong>${titulo}</strong>
                            <small>${fechaStr} ${hora} ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ ${s.total_items || s.items?.length || 0} productos</small>
                            ${motivoHtml}
                        </div>
                        <span class="historial-estado ${estadoClass}">${estadoText}</span>
                    </li>
                `;
            }).join('');

            // Click en cada item para ver detalle
            list.querySelectorAll('.historial-item').forEach(li => {
                li.addEventListener('click', () => {
                    const idx = parseInt(li.dataset.idx);
                    this.showDetalleSalida(salidas[idx]);
                });
            });
        }

        this.showScreen('historial');
    }

    // =====================================================
    // DETALLE DE SALIDA (MODAL)
    // =====================================================

    async showDetalleSalida(salida) {
        const modal = document.getElementById('modal-detalle-salida');
        const loading = document.getElementById('det-loading');
        const productosList = document.getElementById('det-productos');

        // Mostrar modal con datos bÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡sicos ya disponibles
        const estado = salida.estado_sync || salida.estado || 'PENDIENTE_SYNC';
        const estadoLabels = {
            'SINCRONIZADA': 'Sincronizada', 'OBSERVADA': 'Observada',
            'RECHAZADA': 'Rechazada', 'PENDIENTE_SYNC': 'Pendiente'
        };
        const estadoClasses = {
            'SINCRONIZADA': 'sincronizada', 'OBSERVADA': 'observada',
            'RECHAZADA': 'rechazada', 'PENDIENTE_SYNC': 'pendiente'
        };

        const fecha = new Date(salida.fecha_hora_local || salida.createdAt);
        const fechaStr = fecha.toLocaleString('es-BO', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        document.getElementById('det-tipo-salida').textContent =
            salida.tipo_salida_nombre || salida.tipoNombre || salida.tipo_salida || 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â';
        document.getElementById('det-tipo-inventario').textContent =
            salida.tipo_inventario_nombre || salida.tipoInventarioNombre ||
            salida.tipo_inventario || salida.tipoInventario || 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â';
        document.getElementById('det-area').textContent =
            salida.area_nombre || salida.areaNombre || salida.area_destino || 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â';
        document.getElementById('det-fecha').textContent = fechaStr;
        document.getElementById('det-entrega').textContent =
            salida.usuario_entrega_nombre || salida.usuario_entrega || 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â';
        document.getElementById('det-recibe').textContent =
            salida.usuario_recibe_nombre || salida.usuario_recibe || 'ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â';

        const estadoEl = document.getElementById('det-estado');
        estadoEl.textContent = estadoLabels[estado] || estado;
        estadoEl.className = `detalle-value detalle-estado ${estadoClasses[estado] || 'pendiente'}`;

        const motivoRow = document.getElementById('det-motivo-row');
        const motivo = salida.motivo_rechazo || salida.motivoRechazo || '';
        if (motivo) {
            document.getElementById('det-motivo').textContent = motivo;
            motivoRow.style.display = 'flex';
        } else {
            motivoRow.style.display = 'none';
        }

        // Mostrar el modal antes de cargar productos
        modal.style.display = 'flex';

        // Mostrar productos: primero los locales si existen
        const itemsLocales = salida.items;
        if (itemsLocales && itemsLocales.length > 0) {
            loading.style.display = 'none';
            productosList.innerHTML = itemsLocales.map(i => `
                <li class="detalle-producto-item">
                    <span class="det-prod-nombre">${i.nombre || 'Producto'}</span>
                    <span class="det-prod-cantidad">${i.cantidad} ${i.unidad || ''}</span>
                </li>
            `).join('');
        } else {
            productosList.innerHTML = '';
            loading.style.display = 'flex';

            // Cargar del servidor
            const serverId = salida.id || salida.id_salida_movil;
            const uuid = salida.uuid_local || salida.uuid;
            if (syncManager.isOnline && (serverId || uuid)) {
                try {
                    const param = serverId ? `id=${serverId}` : `uuid=${uuid}`;
                    const resp = await fetch(`${API_BASE}/salidas.php?action=detalle&${param}`);
                    const data = await resp.json();
                    if (data.success && data.detalle && data.detalle.length > 0) {
                        productosList.innerHTML = data.detalle.map(d => `
                            <li class="detalle-producto-item">
                                <span class="det-prod-nombre">${d.producto_nombre || d.producto_codigo || 'Producto'}</span>
                                <span class="det-prod-cantidad">${parseFloat(d.cantidad)} ${d.unidad || ''}</span>
                            </li>
                        `).join('');
                    } else {
                        productosList.innerHTML = '<li class="det-no-data">Sin detalle disponible</li>';
                    }
                } catch (e) {
                    productosList.innerHTML = '<li class="det-no-data">Error al cargar productos</li>';
                }
            } else {
                productosList.innerHTML = '<li class="det-no-data">Sin conexiÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n para cargar productos</li>';
            }
            loading.style.display = 'none';
        }
    }

    closeDetalleSalida() {
        document.getElementById('modal-detalle-salida').style.display = 'none';
    }

    // =====================================================
    // SINCRONIZACIÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œN MANUAL
    // =====================================================

    async doSync() {
        const btn = document.getElementById('btn-sincronizar');
        btn.disabled = true;
        btn.querySelector('i').classList.add('fa-spin');

        try {
            const result = await syncManager.syncPendientes();

            if (result.synced > 0) {
                this.toast(`${result.synced} salida(s) sincronizada(s)`, 'success');
            } else if (result.failed > 0) {
                this.toast(`${result.failed} error(es) de sincronizaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n`, 'warning');
            } else {
                this.toast('No hay salidas pendientes', 'info');
            }

            this.updatePendingBadge();

        } catch (error) {
            this.toast('Error de sincronizaciÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â³n', 'error');
        } finally {
            btn.disabled = false;
            btn.querySelector('i').classList.remove('fa-spin');
        }
    }

    // =====================================================
    // UTILIDADES
    // =====================================================

    resetSalida() {
        this.salida = {
            tipoInventario: null,
            tipoInventarioId: null,
            tipoInventarioNombre: '',
            tipo: null,
            tipoNombre: '',
            items: [],
            area: null,
            areaNombre: ''
        };
        this.receptorUser = null;
        this.selectedProducto = null;
    }

    toast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' :
                type === 'error' ? 'times-circle' :
                    type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Iniciar app cuando el DOM estÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â© listo
document.addEventListener('DOMContentLoaded', () => {
    window.app = new MobileApp();
});
