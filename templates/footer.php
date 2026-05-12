<?php
// templates/footer.php
?>
            </div> <!-- End content-body -->
        </main>
    </div> <!-- End app-container -->
    <?php include __DIR__ . '/tasks_drawer.php'; ?>
    <?php include __DIR__ . '/leave_modal.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>
    <script src="/assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr("input[type=date]", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                locale: "<?php echo get_current_lang() === 'en' ? 'en' : 'vn'; ?>",
                disableMobile: "true",
                allowInput: true
            });
        }
    });
    </script>
</body>
</html>
