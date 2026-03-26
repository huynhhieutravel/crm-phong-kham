<?php
// modules/guide/index.php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

if (!is_logged_in()) {
    redirect($base_url . 'login.php');
}

$section = isset($_GET['section']) ? $_GET['section'] : 'overview';
$page_title = __('menu.guide') . ' - ' . __('guide.' . $section);

include __DIR__ . '/../../templates/header.php';

$roles_permissions = $GLOBALS['_role_permissions'];
$all_permissions = get_all_permissions();
?>
<style>
    .guide-img {
        width: 100%;
        max-width: 900px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin: 1rem 0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .guide-steps {
        margin-top: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .step-item {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }
    .step-num {
        width: 28px;
        height: 28px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .guide-workflow {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 1.5rem 0;
        justify-content: center;
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 16px;
    }
    .workflow-box {
        background: white;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        width: 140px;
        text-align: center;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .workflow-box i { font-size: 1.25rem; color: var(--text-muted); }
    .workflow-box.success { border-color: #10b981; color: #059669; }
    .workflow-box.success i { color: #10b981; }
    .workflow-arrow { color: #94a3b8; }
    .guide-note {
        background: #fffbeb;
        border-left: 4px solid #f59e0b;
        padding: 1rem;
        border-radius: 0 8px 8px 0;
        font-size: 0.9rem;
    }
    .guide-note i { color: #f59e0b; margin-right: 0.5rem; }
</style>

<div class="guide-header-box" style="margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
    <h1 style="font-size: 1.75rem; color: var(--text-main);"><i class="fas fa-book-reader text-primary"></i> <?php echo __('guide.' . $section); ?></h1>
    <p style="color: var(--text-muted); margin-top: 0.25rem;"><?php echo __('guide.subtitle'); ?></p>
</div>

<div class="guide-container">
    <div class="guide-content" style="max-width: 1000px;">
        <!-- Section: Overview -->
        <?php if ($section === 'overview'): ?>
        <section id="overview" class="guide-section active">
            <h2><i class="fas fa-info-circle text-primary"></i> 1. Tổng quan hệ thống (System Overview)</h2>
            <div class="card">
                <p>Chào mừng bạn đến với <strong>Simon Center</strong>. Hệ thống được thiết kế để tối ưu hóa quy trình quản lý phòng khám Chiropractic & Đông Y, từ khâu tiếp nhận Marketing đến điều trị chuyên sâu.</p>
                
                <div class="workflow-steps">
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-bullhorn"></i></div>
                        <div class="step-text">
                            <strong>BƯỚC 1: TIẾP NHẬN (Marketing)</strong>
                            <p>Lead được đổ về từ Facebook, Zalo... và được Tư vấn viên chăm sóc.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-calendar-plus"></i></div>
                        <div class="step-text">
                            <strong>BƯỚC 2: ĐẶT LỊCH (Booking)</strong>
                            <p>Tư vấn viên đặt lịch hẹn trên hệ thống. Trạng thái Lead chuyển sang "Đã đặt lịch".</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-user-check"></i></div>
                        <div class="step-text">
                            <strong>BƯỚC 3: TIẾP ĐÓN (Reception)</strong>
                            <p>Bệnh nhân đến phòng khám, Lễ tân thực hiện Check-in. Lead tự động chuyển thành Bệnh nhân chính thức.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-stethoscope"></i></div>
                        <div class="step-text">
                            <strong>BƯỚC 4: ĐIỀU TRỊ (Clinical)</strong>
                            <p>Bác sĩ thực hiện khám, ghi nhận hồ sơ Chiropractic/Đông Y. KTV ghi nhận liệu trình điều trị.</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="step-text">
                            <strong>BƯỚC 5: KẾT THÚC (Accounting)</strong>
                            <p>Bệnh nhân thanh toán, hệ thống ghi nhận doanh thu và xuất báo cáo Financial.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section: Roles -->
        <?php if ($section === 'roles'): ?>
        <section id="roles" class="guide-section active">
            <h2><i class="fas fa-user-shield text-primary"></i> 2. Vai trò & Quyền hạn (Roles & Permissions)</h2>
            <div class="card">
                <p>Mỗi nhân sự được cấp một tài khoản với các quyền truy cập khác nhau để đảm bảo an toàn dữ liệu:</p>
                <table class="guide-table">
                    <thead>
                        <tr>
                            <th>Vai trò (Role)</th>
                            <th>Quyền hạn chính</th>
                            <th>Trách nhiệm chính</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-admin">Admin</span></td>
                            <td>Toàn quyền hệ thống</td>
                            <td>Quản lý người dùng, cấu hình hệ thống, xem báo cáo tài chính tổng, xóa dữ liệu nhạy cảm.</td>
                        </tr>
                        <tr>
                            <td><span class="badge" style="background: #fdf2f8; color: #9d174d; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem;">Quản lý (Manager)</span></td>
                            <td>Điều hành & Quản trị trung gian</td>
                            <td>Tương tự Admin nhưng không được phép can thiệp vào Cài đặt hệ thống (Settings) và các tính năng bảo mật dành riêng cho Admin.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-doctor">Bác sĩ (Doctor)</span></td>
                            <td>Quản lý Bệnh nhân & Bệnh án</td>
                            <td>Thực hiện khám bệnh (Chiropractic Exam, SOAP), ra phác đồ, xem lịch sử bệnh nhân.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-receptionist">Lễ tân (Receptionist)</span></td>
                            <td>Lịch hẹn & Tiếp đón</td>
                            <td>Trực hotline, đặt lịch cho Lead/Bệnh nhân, Check-in khách đến, tạo hóa đơn bán hàng.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-therapist">KTV (Therapist)</span></td>
                            <td>Thực hiện điều trị</td>
                            <td>Xem phác đồ bác sĩ chỉ định, ghi nhận buổi điều trị thực tế cho bệnh nhân.</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-consultant">Tư vấn viên (Consultant)</span></td>
                            <td>Marketing & Lead</td>
                            <td>Quản lý danh sách Lead, gọi điện tư vấn, cập nhật trạng thái chăm sóc, đặt lịch hẹn lần đầu.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section: Leads -->
        <?php if ($section === 'leads'): ?>
        <section id="leads" class="guide-section active">
            <h2><i class="fas fa-filter text-primary"></i> 3. Marketing & Quản lý Leads</h2>
            
            <div class="card">
                <h3>Tổng quan Dashboard</h3>
                <p>Giao diện Marketing giúp bạn theo dõi toàn bộ phễu khách hàng từ khi mới tiếp cận đến khi đặt lịch hẹn thành công.</p>
                <img src="assets/leads_dashboard.png" alt="Leads Dashboard" class="guide-img">
                
                <div class="guide-steps">
                    <div class="step-item">
                        <div class="step-num">1</div>
                        <div class="step-text">
                            <strong>Chỉ số nhanh:</strong> Theo dõi số lượng Lead Mới, Đã liên hệ và Đã đặt lịch ngay ở đầu trang.
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-num">2</div>
                        <div class="step-text">
                            <strong>Bộ lọc thông minh:</strong> Tìm kiếm Lead theo Tên, SĐT, Nguồn (Facebook, TikTok, Website...) hoặc Nhóm bệnh.
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-num">3</div>
                        <div class="step-text">
                            <strong>Thêm nhanh (Quick Add):</strong> Sử dụng dòng màu xanh <strong><i class="fas fa-bolt"></i> Nhanh</strong> ở đầu bảng để nhập dữ liệu ngay lập tức mà không cần chuyển trang.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <h3>Quy trình Chăm sóc & Chuyển đổi</h3>
                <p>Đây là các bước quan trọng để biến một Lead thành Bệnh nhân tại phòng khám:</p>
                
                <div class="guide-workflow">
                    <div class="workflow-box">
                        <i class="fas fa-user-plus"></i>
                        <span>Tiếp nhận Lead mới</span>
                    </div>
                    <i class="fas fa-chevron-right workflow-arrow"></i>
                    <div class="workflow-box">
                        <i class="fas fa-phone-alt"></i>
                        <span>Gọi điện tư vấn</span>
                    </div>
                    <i class="fas fa-chevron-right workflow-arrow"></i>
                    <div class="workflow-box success">
                        <i class="fas fa-calendar-check"></i>
                        <span>Đặt lịch hẹn (BOOK)</span>
                    </div>
                </div>

                <h4 class="mt-4">Cách chuyển đổi sang Lịch hẹn:</h4>
                <p>Khi khách hàng đồng ý đến khám, hãy nhấn nút <strong><i class="fas fa-calendar-plus"></i> Đặt lịch</strong> ở cột Thao tác. Hệ thống sẽ tự động chuyển sang giao diện <strong>Lên Lịch Thông Minh</strong>.</p>
                <img src="assets/leads_conversion.png" alt="Smart Scheduling" class="guide-img">
                
                <div class="guide-tip mt-3">
                    <i class="fas fa-magic"></i> <strong>Tính năng thông minh:</strong> Hệ thống sẽ tự động gợi ý các khung giờ vắng khách (ví dụ: 10:00 & 15:30) để bạn tư vấn cho khách hàng, giúp tối ưu công suất phòng khám.
                </div>
            </div>

            <div class="card mt-4">
                <h3>Các trạng thái Lead & Ghi chú:</h3>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="guide-list">
                            <li><strong>Mới:</strong> Leads vừa được nhập vào, chưa liên hệ.</li>
                            <li><strong>Đã liên hệ:</strong> Đã gọi hoặc nhắn tin chăm sóc.</li>
                            <li><strong>Hẹn gọi lại:</strong> Khách bận, cần gọi lại sau (có hiển thị thời gian).</li>
                            <li><strong>Đã đặt lịch:</strong> Khách đã chốt ngày giờ (Status: BOOK).</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="guide-note">
                            <i class="fas fa-sticky-note"></i>
                            <strong>Ghi chú nhanh:</strong> Di chuột vào biểu tượng tờ giấy cạnh tên Lead để xem nhanh nhật ký tư vấn gần nhất mà không cần mở chi tiết.
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section: Appointments -->
        <?php if ($section === 'appointments'): ?>
        <section id="appointments" class="guide-section active">
            <h2><i class="fas fa-calendar-check text-primary"></i> 4. Lịch hẹn & Tiếp đón</h2>
            <div class="card">
                <h3>Sử dụng Timeline (Lịch tuần):</h3>
                <p>Kéo thả hoặc click vào ô trống trong Timeline để đặt lịch. Timeline giúp bạn nhìn thấy các khung giờ trống của từng Bác sĩ/KTV để tư vấn cho khách.</p>
                
                <h3>Quy trình Check-in:</h3>
                <ol>
                    <li>Tìm lịch hẹn của khách trong mục "Danh sách lịch hẹn hôm nay".</li>
                    <li>Nhấn nút <strong>CHECK-IN</strong>.</li>
                    <li>Nếu là Lead, hệ thống sẽ yêu cầu hoàn thiện thông tin để tạo hồ sơ Bệnh nhân chính thức.</li>
                </ol>

                <h3>Màu sắc lịch hẹn:</h3>
                <div class="color-legend-simple">
                    <div class="legend-item"><span class="color-box" style="background:#add8e6"></span> Đã chữa</div>
                    <div class="legend-item"><span class="color-box" style="background:#1e40af"></span> Đã trả tiền</div>
                    <div class="legend-item"><span class="color-box" style="background:#fef08a"></span> Bệnh nhân báo không đến</div>
                    <div class="legend-item"><span class="color-box" style="background:#f87171"></span> Bác sĩ/KTV nghỉ bệnh</div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section: Medical -->
        <?php if ($section === 'medical'): ?>
        <section id="medical" class="guide-section active">
            <h2><i class="fas fa-file-medical text-primary"></i> 5. Chuyên môn & Hồ sơ bệnh án</h2>
            <div class="card">
                <p>Hồ sơ bệnh án được chia thành các BUỔI KHÁM (Sessions). Mỗi buổi khám có thể chứa nhiều loại phiếu khác nhau:</p>
                <ul>
                    <li><strong>Khám lần đầu:</strong> Ghi nhận tình trạng ma trận cột sống, sơ đồ điểm đau.</li>
                    <li><strong>Tiền sử bệnh:</strong> Ghi nhận thói quen sinh hoạt, bệnh lý nền.</li>
                    <li><strong>Theo dõi SOAP:</strong> (Subjective, Objective, Assessment, Plan) cho từng buổi điều trị Chiropractic.</li>
                    <li><strong>Phiếu Đông Y:</strong> Ghi nhận Vọng - Văn - Vấn - Thiết.</li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Quan trọng:</strong> Sau khi nhập xong, hãy nhấn nút <strong>Hoàn tất Buổi khám</strong> để khóa hồ sơ, đảm bảo tính pháp lý và bảo mật.
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Section: Sales -->
        <?php if ($section === 'sales'): ?>
        <section id="sales" class="guide-section active">
            <h2><i class="fas fa-shopping-cart text-primary"></i> 6. Tài chính & Báo cáo</h2>
            <div class="card">
                <h3>Thu/Chi:</h3>
                <p>Mọi giao dịch thanh toán của bệnh nhân nên được ghi nhận trong mục "Bán hàng" để tạo Hóa đơn (Receipts). Admin có thể theo dõi biến động dòng tiền trong mục <strong>Báo cáo</strong>.</p>
                
                <h3>Nhật ký hệ thống (Audit Logs):</h3>
                <p>Mọi hành động Sửa hoặc Xóa các thông tin quan trọng đều được hệ thống ghi lại (Ai sửa, Sửa lúc nào, Sửa cái gì từ cũ sang mới). Điều này để đảm bảo tính minh bạch trong quản lý.</p>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<style>
.guide-container {
    display: block;
    margin-top: 1rem;
}

.guide-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.guide-section h2 {
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.workflow-steps {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.step {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
}

.step-icon {
    flex: 0 0 40px;
    height: 40px;
    background: #eff6ff;
    color: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.step-text strong {
    display: block;
    color: var(--text-main);
    margin-bottom: 0.25rem;
}

.step-text p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
}

.guide-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.guide-table th, .guide-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #f1f5f9;
}

.guide-table th {
    background: #f8fafc;
    font-size: 0.85rem;
    text-transform: uppercase;
    color: #64748b;
}

.guide-tip {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1.5rem;
    font-size: 0.95rem;
}

.color-legend-simple {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-top: 1rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.color-box {
    width: 24px;
    height: 12px;
    border-radius: 4px;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
