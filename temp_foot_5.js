
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

        // =====================================================
        // SISTEMA DE NOTIFICACIONES
        // =====================================================
        initNotificationSystem();
    });

    function initNotificationSystem() {
        const btn = document.getElementById('notificationBtn');
        const dropdown = document.getElementById('notificationDropdown');
        const badge = document.getElementById('notificationBadge');
        const list = document.getElementById('notificationList');
        const markAllBtn = document.getElementById('markAllRead');

        if (!btn || !dropdown) return;

        // Toggle dropdown
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && e.target !== btn) {
                dropdown.classList.remove('show');
            }
        });

        // Marcar todas como leídas
        if (markAllBtn) {
            markAllBtn.addEventListener('click', async function (e) {
                e.preventDefault();
                const items = list.querySelectorAll('.notification-item.unread');
                for (const item of items) {
                    const id = item.dataset.id;
                    await markAsRead(id);
                    item.classList.remove('unread');
                }
                updateBadge(0);
            });
        }

        // Cargar count inicial
        loadNotificationCount();

        // Actualizar cada 2 minutos
        setInterval(loadNotificationCount, 120000);
    }

    async function loadNotificationCount() {
        try {
            const response = await fetch(`${window.baseUrl}/api/notificaciones.php?action=pendientes`);
            const data = await response.json();

            if (data.success) {
                updateBadge(data.total);
            }
        } catch (error) {
            console.log('[Notif] Error cargando conteo:', error);
        }
    }

    async function loadNotifications() {
        const list = document.getElementById('notificationList');

        try {
            const response = await fetch(`${window.baseUrl}/api/notificaciones.php?action=pendientes`);
            const data = await response.json();

            if (data.success && data.notificaciones.length > 0) {
                list.innerHTML = data.notificaciones.map(n => renderNotificationItem(n)).join('');

                // Agregar click handlers
                list.querySelectorAll('.notification-item').forEach(item => {
                    item.addEventListener('click', async function () {
                        const id = this.dataset.id;
                        await markAsRead(id);
                        this.classList.remove('unread');
                        loadNotificationCount();
                    });
                });
            } else {
                list.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No hay notificaciones nuevas</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('[Notif] Error:', error);
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error cargando notificaciones</p>
                </div>
            `;
        }
    }

    function renderNotificationItem(n) {
        const iconClass = n.tipo === 'ERROR' ? 'error' :
            n.tipo === 'ALERTA' ? 'alerta' : 'info';
        const iconName = n.tipo === 'ERROR' ? 'fa-times-circle' :
            n.tipo === 'ALERTA' ? 'fa-exclamation-triangle' : 'fa-info-circle';

        return `
            <div class="notification-item unread" data-id="${n.id_notificacion}">
                <div class="notification-icon ${iconClass}">
                    <i class="fas ${iconName}"></i>
                </div>
                <div class="notification-content">
                    <h5>${escapeHtml(n.titulo)}</h5>
                    <p>${n.modulo}</p>
                    <div class="notification-time">
                        <i class="fas fa-clock"></i> ${n.fecha}
                    </div>
                </div>
            </div>
        `;
    }

    function updateBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    async function markAsRead(id) {
        try {
            await fetch(`${window.baseUrl}/api/notificaciones.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'marcar_leida', id: id })
            });
        } catch (error) {
            console.log('[Notif] Error marcando leída:', error);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

