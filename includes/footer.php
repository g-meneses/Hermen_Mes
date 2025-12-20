</div>
            <!-- Fin Page Container -->
        </main>
        <!-- Fin Main Content -->
    </div>
    <!-- Fin Layout -->
    
    <script>
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

    // Abrir submenús del ítem activo al cargar
    document.addEventListener('DOMContentLoaded', function() {
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