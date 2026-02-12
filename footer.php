</div> <!-- ปิด container-fluid -->
</div> <!-- ปิด content -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom JavaScript -->
<script>
    // Toggle Sidebar (Desktop)
    document.getElementById('sidebarToggle').addEventListener('click', function () {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const overlay = document.getElementById('sidebarOverlay');

        // เช็คว่าเป็นมือถือหรือไม่
        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('mobile-show');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('mobile-show') ? 'hidden' : '';
        } else {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('expanded');
        }
    });

    // Mobile Toggle
    document.getElementById('mobileToggle').addEventListener('click', function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.toggle('mobile-show');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('mobile-show') ? 'hidden' : '';
    });

    // ปิด sidebar เมื่อกดที่ overlay
    document.getElementById('sidebarOverlay').addEventListener('click', function () {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.remove('mobile-show');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    });

    // ปิด sidebar เมื่อคลิกเมนู (มือถือ)
    document.querySelectorAll('.sidebar .menu-item').forEach(function (item) {
        item.addEventListener('click', function () {
            if (window.innerWidth <= 992) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');

                sidebar.classList.remove('mobile-show');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });

    // ปิด sidebar เมื่อ resize จากมือถือเป็น desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.remove('mobile-show');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Initialize DataTables if exists
    if ($.fn.DataTable && document.querySelector('.datatable')) {
        $('.datatable').DataTable({
            "language": {
                "lengthMenu": "แสดง _MENU_ รายการ",
                "zeroRecords": "ไม่พบข้อมูล",
                "info": "แสดงหน้า _PAGE_ จาก _PAGES_",
                "infoEmpty": "ไม่มีข้อมูล",
                "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
                "search": "ค้นหา:",
                "paginate": {
                    "first": "หน้าแรก",
                    "last": "หน้าสุดท้าย",
                    "next": "ถัดไป",
                    "previous": "ก่อนหน้า"
                }
            },
            "responsive": true,
            "pageLength": 10,
            "scrollX": true,
            "autoWidth": false
        });
    }

    // Auto-close alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const closeButton = alert.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            }
        }, 5000);
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // File Input Customization
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function (e) {
            const fileName = this.files[0]?.name || 'เลือกไฟล์';
            const label = this.nextElementSibling;
            if (label) {
                label.textContent = fileName;
            }
        });
    });
</script>