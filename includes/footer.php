            </main>
            
            <!-- Footer -->
            <footer class="footer">
                <div class="footer-content">
                    <p>&copy; <?php echo date('Y'); ?> Hermen Ltda. - Sistema MES de Producción</p>
                    <p>Línea: <strong>Poliamida</strong> | Versión 1.0</p>
                </div>
            </footer>
        </div>
    </div>
    
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    <?php if (isset($extraJS)): ?>
        <?php foreach($extraJS as $js): ?>
            <script src="<?php echo SITE_URL . '/' . $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
