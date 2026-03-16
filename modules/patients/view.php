<?php
// modules/patients/view.php
require_once '../../includes/db.php';
$page_title = 'Chi tiết Bệnh nhân';
$current_page = 'patients';
require_once '../../templates/header.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    echo "<div class='card'>Bệnh nhân không tồn tại.</div>";
    require_once '../../templates/footer.php';
    exit;
}

// Fetch medical history summary
$stmt = $db->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$histories = $stmt->fetchAll();

// Fetch treatments summary
$stmt = $db->prepare("SELECT t.*, u.full_name as technician_name FROM treatments t JOIN users u ON t.technician_id = u.id WHERE t.patient_id = ? ORDER BY t.treatment_date DESC");
$stmt->execute([$id]);
$treatments = $stmt->fetchAll();
?>

<div style="display: flex; gap: 1.5rem;">
    <!-- Left Column: Patient Info -->
    <div style="flex: 1;">
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                <h2 style="margin: 0;"><?php echo e($patient['full_name']); ?></h2>
                <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm" style="background: #f1f5f9;"><i class="fas fa-edit"></i> Sửa</a>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.95rem;">
                <div>
                    <span style="color: var(--text-muted);">SĐT:</span> <strong><?php echo e($patient['phone']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted);">Giới tính:</span> <?php echo $patient['gender'] === 'male' ? 'Nam' : ($patient['gender'] === 'female' ? 'Nữ' : 'Khác'); ?>
                </div>
                <div>
                    <span style="color: var(--text-muted);">Ngày sinh:</span> <?php echo $patient['birthday'] ? date('d/m/Y', strtotime($patient['birthday'])) : '—'; ?>
                </div>
                <div>
                    <span style="color: var(--text-muted);">Nguồn:</span> <?php echo e($patient['source'] ?: '—'); ?>
                </div>
            </div>
            
            <div style="margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <div style="margin-bottom: 0.5rem;"><span style="color: var(--text-muted);">Địa chỉ:</span> <?php echo e($patient['address'] ?: '—'); ?></div>
                <div><span style="color: var(--text-muted);">Ghi chú:</span> <?php echo e($patient['notes'] ?: '—'); ?></div>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin:0;">Lịch sử điều trị</h3>
                <a href="../medical/add_treatment.php?patient_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Thêm buổi khám
                </a>
            </div>
            
            <?php if (empty($treatments)): ?>
                <p style="text-align: center; color: var(--text-muted); padding: 1rem;">Chưa có lịch sử điều trị.</p>
            <?php else: ?>
                <div class="treatment-list">
                    <?php foreach ($treatments as $t): ?>
                        <div style="padding: 1rem; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <strong><?php echo date('d/m/Y H:i', strtotime($t['treatment_date'])); ?></strong>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">KTV: <?php echo e($t['technician_name']); ?></span>
                            </div>
                            <p style="font-size: 0.9rem;"><?php echo e($t['session_data']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Medical History Forms -->
    <div style="width: 350px;">
        <div class="card" style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1.25rem;"><i class="fas fa-file-medical-alt" style="color: var(--primary);"></i> Hồ sơ Bệnh án</h3>
            
            <!-- New Exam Actions -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 2rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 1.5rem;">
                <!-- Tiền sử Chiro -->
                <div class="dual-btn" style="position: relative;">
                    <div class="filter-btn" data-type="chiro_history" style="cursor: pointer; background: #f5f3ff; color: #7c3aed; padding: 0.75rem 0.25rem; font-size: 0.75rem; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 0.25rem; transition: all 0.2s; border: 1px solid transparent;">
                        <i class="fas fa-history"></i>
                        <span style="font-weight: 600;">Tiền sử Chiro</span>
                    </div>
                    <a href="../medical/chiro_history.php?patient_id=<?php echo $id; ?>" class="add-shortcut" title="Tạo phiếu tiền sử" style="position: absolute; top: -5px; right: -5px; width: 22px; height: 22px; background: #7c3aed; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>

                <!-- Khám bệnh Chiro -->
                <div class="dual-btn" style="position: relative;">
                    <div class="filter-btn" data-type="chiro_exam" style="cursor: pointer; background: #eff6ff; color: #2563eb; padding: 0.75rem 0.25rem; font-size: 0.75rem; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 0.25rem; transition: all 0.2s; border: 1px solid transparent;">
                        <i class="fas fa-stethoscope"></i>
                        <span style="font-weight: 600;">Khám bệnh Chiro</span>
                    </div>
                    <a href="../medical/chiro_exam.php?patient_id=<?php echo $id; ?>" class="add-shortcut" title="Tạo phiếu khám mới" style="position: absolute; top: -5px; right: -5px; width: 22px; height: 22px; background: #2563eb; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>

                <!-- Theo dõi Chiro (SOAP) -->
                <div class="dual-btn" style="position: relative;">
                    <div class="filter-btn" data-type="chiropractic" style="cursor: pointer; background: #eef2ff; color: #4f46e5; padding: 0.75rem 0.25rem; font-size: 0.75rem; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 0.25rem; transition: all 0.2s; border: 1px solid transparent;">
                        <i class="fas fa-notes-medical"></i>
                        <span style="font-weight: 600;">Theo dõi Chiro</span>
                    </div>
                    <a href="../medical/follow_up.php?patient_id=<?php echo $id; ?>" class="add-shortcut" title="Tạo phiếu theo dõi SOAP" style="position: absolute; top: -5px; right: -5px; width: 22px; height: 22px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>

                <!-- Đông Y -->
                <div class="dual-btn" style="position: relative;">
                    <div class="filter-btn" data-type="dong_y" style="cursor: pointer; background: #fdf2f2; color: #dc2626; padding: 0.75rem 0.25rem; font-size: 0.75rem; border-radius: 12px; display: flex; flex-direction: column; align-items: center; gap: 0.25rem; transition: all 0.2s; border: 1px solid transparent;">
                        <i class="fas fa-leaf"></i>
                        <span style="font-weight: 600;">Đông Y</span>
                    </div>
                    <a href="../medical/form.php?patient_id=<?php echo $id; ?>&type=dong_y" class="add-shortcut" title="Tạo phiếu Đông Y mới" style="position: absolute; top: -5px; right: -5px; width: 22px; height: 22px; background: #dc2626; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); z-index: 10;">
                        <i class="fas fa-plus"></i>
                    </a>
                </div>
            </div>

            <!-- History List -->
            <div style="margin-top: 1rem;">
                <h4 style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 1rem; letter-spacing: 0.5px;">Lịch sử thăm khám</h4>
                <?php
                // Fetch full history with creator name
                $hist_stmt = $db->prepare("
                    SELECT mh.*, u.full_name as doctor_name 
                    FROM medical_history mh 
                    LEFT JOIN users u ON mh.created_by = u.id 
                    WHERE mh.patient_id = ? 
                    ORDER BY mh.created_at DESC
                ");
                $hist_stmt->execute([$id]);
                $all_histories = $hist_stmt->fetchAll();
                ?>

                <?php if (empty($all_histories)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 1rem;">Chưa có dữ liệu lịch sử.</p>
                <?php else: ?>
                    <div id="history-list-container" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($all_histories as $h): ?>
                            <div class="history-item" data-type="<?php echo $h['type']; ?>" style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: #fafafa; border-radius: 12px; border: 1px solid #f1f5f9; transition: all 0.3s ease;">
                                <div>
                                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                                        <?php 
                                        if ($h['type'] === 'chiropractic') {
                                            echo '<i class="fas fa-notes-medical" style="color: #4f46e5;"></i> Theo dõi Chiro (SOAP)';
                                        } elseif ($h['type'] === 'chiro_history') {
                                            echo '<i class="fas fa-history" style="color: #7c3aed;"></i> Tiền sử Chiro';
                                        } elseif ($h['type'] === 'chiro_exam') {
                                            echo '<i class="fas fa-stethoscope" style="color: #2563eb;"></i> Khám bệnh Chiro';
                                        } elseif ($h['type'] === 'initial_exam') {
                                            echo '<i class="fas fa-file-medical" style="color: #64748b;"></i> Khám Chiro (Cũ)';
                                        } else {
                                            echo '<i class="fas fa-leaf" style="color: #10b981;"></i> Phiếu Đông Y'; 
                                        }
                                        ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($h['created_at'])); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--primary); font-weight: 500;">
                                        <i class="fas fa-user-md"></i> <?php echo e($h['doctor_name'] ?: 'N/A'); ?>
                                    </div>
                                </div>
                                <a href="../medical/view_form.php?id=<?php echo $h['id']; ?>" class="btn btn-sm" style="background: white; border: 1px solid #e2e8f0; width: 36px; height: 36px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px;" title="Xem chi tiết">
                                    <i class="fas fa-eye" style="color: var(--text-muted);"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 1rem;">Gói dịch vụ</h3>
            <?php
            $pkg_stmt = $db->prepare("
                SELECT pp.*, p.name as package_name 
                FROM patient_packages pp 
                JOIN packages p ON pp.package_id = p.id 
                WHERE pp.patient_id = ? AND pp.status = 'active'
            ");
            $pkg_stmt->execute([$id]);
            $packages = $pkg_stmt->fetchAll();
            ?>
            <?php if (empty($packages)): ?>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Bệnh nhân chưa mua gói dịch vụ nào.</p>
            <?php else: ?>
                <?php foreach ($packages as $pkg): ?>
                    <div style="padding: 1rem; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 12px; margin-bottom: 0.75rem;">
                        <div style="font-weight: 700; margin-bottom: 0.5rem; color: #065f46;"><?php echo e($pkg['package_name']); ?></div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                            <span>Còn lại: <strong><?php echo $pkg['remaining_sessions']; ?> / <?php echo $pkg['total_sessions']; ?></strong></span>
                            <span style="color: var(--text-muted);">Hạn: <?php echo date('d/m/Y', strtotime($pkg['expiry_date'])); ?></span>
                        </div>
                        <div style="margin-top: 0.75rem; background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: #10b981; height: 100%; width: <?php echo ($pkg['remaining_sessions'] / $pkg['total_sessions']) * 100; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <a href="../sales/add_package.php?patient_id=<?php echo $id; ?>" class="btn" style="width: 100%; justify-content: center; margin-top: 0.5rem; font-size: 0.85rem; background: #f1f5f9;">
                <i class="fas fa-shopping-basket"></i> Mua thêm gói
            </a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.getAttribute('data-type');
        const container = document.getElementById('history-list-container');
        const items = container.querySelectorAll('.history-item');
        
        // Toggle active state on button
        const isActive = this.classList.contains('active');
        document.querySelectorAll('.filter-btn').forEach(b => {
            b.classList.remove('active');
            b.style.borderColor = 'transparent';
            b.style.boxShadow = 'none';
        });

        if (isActive) {
            // Show all if clicking the same active button (reset)
            items.forEach(item => {
                item.style.display = 'flex';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            });
        } else {
            this.classList.add('active');
            this.style.borderColor = 'var(--primary)';
            this.style.boxShadow = '0 0 0 3px rgba(99, 102, 241, 0.1)';
            
            // Filter list
            items.forEach(item => {
                if (item.getAttribute('data-type') === type) {
                    item.style.display = 'flex';
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        item.style.display = 'none';
                    }, 300);
                }
            });
        }
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
