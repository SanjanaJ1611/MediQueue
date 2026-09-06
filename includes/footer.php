<?php
/**
 * MediQueue - Global Footer & Script Attachments
 */
?>
<?php if (is_logged_in()): ?>
        </main>
        
        <!-- App Bottom Footer -->
        <footer class="py-3 px-4 bg-white border-top text-muted small d-flex flex-column flex-sm-row justify-content-between align-items-center mt-auto">
            <div>
                &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. All rights reserved.
            </div>
            <div class="mt-2 mt-sm-0">
                <span class="badge bg-light text-muted border">v2.4 Pro</span>
                <span class="ms-2">Hospital Appointment & Virtual Queue System</span>
            </div>
        </footer>
    </div><!-- /.app-main -->
</div><!-- /.app-wrapper -->
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle (Popper included) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (for analytics and dashboards) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom MediQueue App Script -->
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>

</body>
</html>
