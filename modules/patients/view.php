<?php
// modules/patients/view.php
require_once '../../includes/db.php';
$page_title = 'Chi tiết Bệnh nhân';
$current_page = 'patients';
require_once '../../templates/header.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT p.*, u.full_name as consultant_name 
    FROM patients p 
    LEFT JOIN users u ON p.consultant_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    echo "<div class='card'>Bệnh nhân không tồn tại.</div>";
    require_once '../../templates/footer.php';
    exit;
}

// Fetch medical sessions
$stmt = $db->prepare("SELECT s.*, u.full_name as doctor_name FROM medical_sessions s JOIN users u ON s.doctor_id = u.id WHERE s.patient_id = ? ORDER BY s.session_date DESC");
$stmt->execute([$id]);
$sessions = $stmt->fetchAll();

// Fetch medical history summary
$stmt = $db->prepare("SELECT h.*, u.full_name as doctor_name FROM medical_history h LEFT JOIN users u ON h.created_by = u.id WHERE h.patient_id = ? ORDER BY h.created_at DESC");
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
<div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%;">
    <!-- Top Banner Card -->
    <div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: var(--shadow-premium); background: white;">
        <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding: 2.5rem; color: white; display: flex; align-items: center; gap: 2.5rem; position: relative;">
            <div style="width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 3rem; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                <i class="fas fa-user-astronaut" style="color: var(--primary-light);"></i>
            </div>
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                    <h1 style="margin: 0; font-size: 2.25rem; font-weight: 800; letter-spacing: -0.02em;"><?php echo e($patient['full_name']); ?></h1>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 800; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 50px; text-transform: uppercase; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                            <i class="fas fa-crown"></i> Premium
                        </span>
                        
                        <?php if (!empty($patient['label'])): ?>
                            <span style="font-size: 0.75rem; font-weight: 800; background: #fee2e2; color: #b91c1c; padding: 0.25rem 0.75rem; border-radius: 50px; text-transform: uppercase; border: 1px solid #fecaca;">
                                <i class="fas fa-tag"></i> <?php echo e($patient['label']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; gap: 1.5rem; font-size: 1rem; font-weight: 500; opacity: 0.8;">
                    <span><i class="fas fa-fingerprint" style="color: var(--primary-light); margin-right: 0.4rem;"></i> <?php echo e($patient['customer_id'] ?: 'BN-' . $patient['id']); ?></span>
                    <span><i class="fas fa-phone-alt" style="color: var(--primary-light); margin-right: 0.4rem;"></i> <?php echo e($patient['phone']); ?></span>
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                         <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></span> 
                         Đang hoạt động
                    </span>
                </div>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="../appointments/add.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="background: #db2777; border: none; box-shadow: 0 4px 12px rgba(219, 39, 119, 0.4);">
                    <i class="fas fa-calendar-plus"></i> Đặt lịch hẹn
                </a>
                <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(5px);">
                    <i class="fas fa-user-edit"></i> Chỉnh sửa
                </a>
            </div>
        </div>

        <!-- Info Grid -->
        <div style="padding: 2.5rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 2.5rem; border-bottom: 1px solid var(--border-color);">
            <div class="info-group">
                <h4 class="info-section-title"><i class="fas fa-info-circle"></i> Hành chính</h4>
                <div class="info-item"><span>Sinh nhật:</span> <strong><?php echo $patient['birthday'] ? date('d/m/Y', strtotime($patient['birthday'])) : '—'; ?></strong></div>
                <div class="info-item"><span>Giới tính:</span> <strong><?php echo $patient['gender'] === 'male' ? 'Nam' : ($patient['gender'] === 'female' ? 'Nữ' : 'Khác'); ?></strong></div>
                <div class="info-item"><span>Nghề nghiệp:</span> <strong><?php echo e($patient['occupation'] ?: '—'); ?></strong></div>
            </div>
            <div class="info-group">
                <h4 class="info-section-title"><i class="fas fa-map-marker-alt"></i> Liên hệ</h4>
                <div class="info-item"><span>Địa chỉ:</span> <strong><?php echo e($patient['address'] ?: '—'); ?></strong></div>
                <div class="info-item"><span>Chi nhánh:</span> <strong><?php echo e($patient['branch'] ?: 'Trụ sở chính'); ?></strong></div>
                <div class="info-item"><span>Nguồn:</span> <strong style="color: var(--primary); font-weight: 800;"><?php echo e($patient['source'] ?: '—'); ?></strong></div>
            </div>
            <div class="info-group">
                <h4 class="info-section-title"><i class="fas fa-user-tie"></i> Phụ trách</h4>
                <div class="info-item"><span>Sale/CSKH:</span> <strong style="color: #6366f1;"><?php echo e($patient['consultant_name'] ?: '—'); ?></strong></div>
                <div class="info-item"><span>ID Hồ sơ:</span> <strong><?php echo e($patient['customer_id'] ?: '—'); ?></strong></div>
                <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                    <?php if ($patient['zalo_number']): ?>
                        <a href="https://zalo.me/<?php echo $patient['zalo_number']; ?>" target="_blank" class="social-icon" style="background: #0068ff;"><i class="fas fa-comment"></i></a>
                    <?php endif; ?>
                    <?php if ($patient['facebook_link']): ?>
                        <a href="<?php echo $patient['facebook_link']; ?>" target="_blank" class="social-icon" style="background: #1877f2;"><i class="fab fa-facebook-f"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="padding: 1.5rem 2.5rem; background: #f8fafc; display: flex; align-items: center; gap: 1.5rem;">
            <i class="fas fa-quote-left" style="color: #cbd5e1; font-size: 1.5rem;"></i>
            <div style="font-size: 0.95rem; color: var(--text-muted); font-style: italic; font-weight: 500; flex: 1;">
                <?php echo nl2br(e($patient['notes'] ?: 'Chưa có ghi chú đặc biệt cho bệnh nhân này.')); ?>
            </div>
            <?php if ($patient['guardian_name']): ?>
                <div style="background: white; padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid #fed7aa; display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 32px; height: 32px; background: #fff7ed; color: #f97316; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #ea580c; text-transform: uppercase;">Người giám hộ</div>
                        <div style="font-size: 0.85rem; font-weight: 700; color: #9a3412;"><?php echo e($patient['guardian_name']); ?> <small>(<?php echo e($patient['guardian_phone']); ?>)</small></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <style>
        .info-row { border-bottom: 1px solid #f8fafc; padding: 0.6rem 0; display: flex; justify-content: space-between; }
        .info-label { color: var(--text-muted); font-size: 0.85rem; }
        .info-value { font-weight: 600; font-size: 0.9rem; color: var(--text-main); }
        .btn-edit-top:hover { background: rgba(255,255,255,0.2) !important; transform: scale(1.1); }
        </style>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Left: Medical Timeline -->
        <div class="card" style="border: none; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                <h3 style="margin: 0; font-weight: 800;"><i class="fas fa-stream" style="color: var(--primary);"></i> Lịch sử thăm khám & Điều trị</h3>
                <a href="../medical/session_add.php?patient_id=<?php echo $id; ?>" class="btn btn-primary shadow-sm" style="border-radius: 50px; font-size: 0.85rem;">
                    <i class="fas fa-plus"></i> Buổi khám mới
                </a>
            </div>

            <div class="timeline-visual" style="position: relative; padding-left: 2rem;">
                <div style="position: absolute; left: 0.25rem; top: 0; bottom: 0; width: 2px; background: #f1f5f9;"></div>
                
                <?php 
                // Grouping Logic: Combine Sessions and Orphan items
                $timeline_items = [];
                
                // 1. Add Sessions (Only those belonging to THIS patient)
                foreach ($sessions as $session) {
                    $session_parts = [];
                    // Find all history records for THIS session
                    foreach ($histories as $h) {
                        if ($h['session_id'] == $session['id']) {
                            $session_parts[] = $h;
                        }
                    }
                    // Find all treatments for THIS session
                    foreach ($treatments as $t) {
                        if ($t['session_id'] == $session['id']) {
                            $session_parts[] = $t;
                        }
                    }
                    
                    $timeline_items[] = [
                        'type' => 'session',
                        'data' => $session,
                        'parts' => $session_parts,
                        'date' => $session['session_date']
                    ];
                }
                
                // 2. Add Orphan Histories (or those belonging to non-existent/wrong sessions)
                $session_ids = array_column($sessions, 'id');
                foreach ($histories as $h) {
                    if (!$h['session_id'] || !in_array($h['session_id'], $session_ids)) {
                        $timeline_items[] = [
                            'type' => 'history',
                            'data' => $h,
                            'date' => $h['created_at']
                        ];
                    }
                }
                
                // 3. Add Orphan Treatments
                foreach ($treatments as $t) {
                    if (!$t['session_id'] || !in_array($t['session_id'], $session_ids)) {
                        $timeline_items[] = [
                            'type' => 'treatment',
                            'data' => $t,
                            'date' => $t['treatment_date']
                        ];
                    }
                }
                
                // 4. Sort all by date
                usort($timeline_items, function($a, $b) {
                    return strtotime($b['date']) <=> strtotime($a['date']);
                });
                ?>

                <?php if (empty($timeline_items)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 2rem;">Chưa có dữ liệu lịch sử.</p>
                <?php else: ?>
                    <style>
                        .session-card {
                            background: white;
                            border: 1px solid #e2e8f0;
                            border-radius: 20px;
                            padding: 1.5rem;
                            margin-bottom: 2rem;
                            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
                            transition: all 0.3s ease;
                            border-left: 6px solid var(--primary);
                        }
                        .session-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
                        .part-badge {
                            font-size: 0.7rem;
                            font-weight: 800;
                            padding: 0.25rem 0.6rem;
                            border-radius: 50px;
                            background: #f1f5f9;
                            color: #64748b;
                            display: inline-flex;
                            align-items: center;
                            gap: 0.25rem;
                            margin-right: 0.5rem;
                            margin-top: 0.5rem;
                        }
                    </style>
                    <div id="timeline-container">
                    <?php foreach ($timeline_items as $item): ?>
                        
                        <?php if ($item['type'] === 'session'): 
                            $s = $item['data'];
                        ?>
                            <div class="session-card timeline-node" data-type="session">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <?php echo date('d/m/Y - H:i', strtotime($s['session_date'])); ?>
                                        </div>
                                        <h4 style="margin: 0.25rem 0; font-size: 1.25rem; font-weight: 800; color: #1e293b;">
                                            Buổi khám tổng quát
                                        </h4>
                                    </div>
                                    <a href="../medical/session_view.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline" style="border-radius: 50px;">
                                        Chi tiết <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>

                                <?php if ($s['assessment'] || $s['treatment_plan']): ?>
                                    <div style="background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; border: 1px solid #f1f5f9;">
                                        <?php if ($s['assessment']): ?>
                                            <div style="margin-bottom: 0.75rem;">
                                                <small style="color: var(--primary); font-weight: 800; text-transform: uppercase; font-size: 0.65rem;">Đánh giá:</small>
                                                <div style="margin-top: 0.25rem; font-size: 0.9rem; line-height: 1.4; color: #475569;"><?php echo $s['assessment']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($s['treatment_plan']): ?>
                                            <div>
                                                <small style="color: #10b981; font-weight: 800; text-transform: uppercase; font-size: 0.65rem;">Kế hoạch:</small>
                                                <div style="margin-top: 0.25rem; font-size: 0.9rem; line-height: 1.4; color: #475569;"><?php echo $s['treatment_plan']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; flex-wrap: wrap;">
                                    <?php foreach ($item['parts'] as $part): 
                                        $p_is_tr = isset($part['session_data']);
                                        $p_type = $p_is_tr ? 'treatment' : $part['type'];
                                        $p_label = [
                                            'chiro_history' => 'Tiền sử',
                                            'chiro_exam' => 'Khám',
                                            'chiropractic' => 'SOAP',
                                            'dong_y' => 'Đông Y',
                                            'treatment' => 'KTV'
                                        ][$p_type] ?? 'Khác';
                                        $p_icon = [
                                            'chiro_history' => 'fa-history',
                                            'chiro_exam' => 'fa-stethoscope',
                                            'chiropractic' => 'fa-notes-medical',
                                            'dong_y' => 'fa-leaf',
                                            'treatment' => 'fa-hand-holding-medical'
                                        ][$p_type] ?? 'fa-file';
                                    ?>
                                        <span class="part-badge">
                                            <i class="fas <?php echo $p_icon; ?>"></i> <?php echo $p_label; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                        <?php else: 
                            // Render orphan history/treatment as a simple node
                            $i = $item['data'];
                            $is_tr = ($item['type'] === 'treatment');
                            $type_label = $is_tr ? 'Điều trị KTV' : [
                                'chiro_exam' => 'Khám bệnh lần đầu (Chiro)',
                                'chiro_history' => 'Khám tiền sử bệnh Chiropractic',
                                'chiropractic' => 'Theo dõi SOAP',
                                'soap_note' => 'Theo dõi SOAP',
                                'dong_y' => 'Đông Y'
                            ][$i['type']] ?? 'Y tế';
                            $color = $is_tr ? '#10b981' : '#6366f1';
                        ?>
                            <div class="timeline-node" data-type="<?php echo $item['type']; ?>" style="position: relative; margin-bottom: 2rem; padding-left: 1rem;">
                                <div style="position: absolute; left: -2.15rem; top: 0; width: 28px; height: 28px; background: white; border: 2px solid <?php echo $color; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 2;">
                                    <i class="fas fa-file-medical" style="color: <?php echo $color; ?>; font-size: 0.7rem;"></i>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800;"><?php echo date('d/m/Y', strtotime($item['date'])); ?></div>
                                <div style="font-weight: 700; color: #1e293b;"><?php echo $type_label; ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $is_tr ? 'Ghi nhận điều trị' : 'Kết quả ghi nhận y tế'; ?></div>
                                <a href="<?php echo $is_tr ? '#' : '../medical/view_form.php?id='.$i['id']; ?>" style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 700;">Xem lại</a>
                            </div>
                        <?php endif; ?>

                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

            </div>
        </div>

        <!-- Right: Actions & Financial Info -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Financial Card -->
            <div class="card" style="padding: 2rem; border: none; background: white; box-shadow: var(--shadow-sm);">
                <h4 style="margin: 0 0 1.5rem 0; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; letter-spacing: 1px;">Tài chính & Gói dịch vụ</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="background: #f0fdf4; padding: 1rem; border-radius: 12px; border: 1px solid #dcfce7; text-align: center;">
                        <div style="font-size: 0.65rem; font-weight: 800; color: #166534; text-transform: uppercase;">Đã thanh toán</div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #15803d; margin-top: 0.25rem;">0</div>
                    </div>
                    <div style="background: #fef2f2; padding: 1rem; border-radius: 12px; border: 1px solid #fee2e2; text-align: center;">
                        <div style="font-size: 0.65rem; font-weight: 800; color: #991b1b; text-transform: uppercase;">Còn nợ</div>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #b91c1c; margin-top: 0.25rem;">0</div>
                    </div>
                </div>

                <!-- Package Progress -->
                <?php if (empty($packages)): ?>
                    <div style="padding: 1.5rem; text-align: center; background: #f8fafc; border-radius: 16px; border: 1px dashed #e2e8f0; margin-bottom: 1.5rem;">
                        <i class="fas fa-shopping-basket" style="font-size: 1.5rem; color: #cbd5e1; margin-bottom: 0.75rem;"></i>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Chưa mua gói dịch vụ nào.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($packages as $pkg): ?>
                        <div style="padding: 1rem; background: #fffcf0; border: 1px solid #fde68a; border-radius: 12px; margin-bottom: 0.75rem;">
                            <div style="font-weight: 800; font-size: 0.9rem; color: #92400e;"><?php echo e($pkg['package_name']); ?></div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-top: 0.25rem;">
                                <span>Còn: <strong><?php echo $pkg['remaining_sessions']; ?>/<?php echo $pkg['total_sessions']; ?></strong></span>
                                <span style="color: var(--text-muted);">Hết hạn: <?php echo date('d/m/y', strtotime($pkg['expiry_date'])); ?></span>
                            </div>
                            <div style="margin-top: 0.5rem; background: #fef3c7; height: 4px; border-radius: 2px; overflow: hidden;">
                                <div style="background: #f59e0b; height: 100%; width: <?php echo ($pkg['remaining_sessions'] / $pkg['total_sessions']) * 100; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <a href="../sales/add_package.php?patient_id=<?php echo $id; ?>" class="btn shadow-sm" style="width: 100%; justify-content: center; background: #0f172a; color: white;">
                    <i class="fas fa-cart-plus"></i> Đăng ký gói mới
                </a>
            </div>

            <!-- Re-exam & Follow-up Section -->
            <div class="card" style="padding: 2rem; border: none; background: #fffbeb; box-shadow: var(--shadow-sm); border-left: 4px solid #f59e0b;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h4 style="margin: 0; font-size: 0.75rem; font-weight: 800; color: #92400e; text-transform: uppercase;">Kế hoạch tái khám</h4>
                    <button onclick="toggleReexamForm()" class="btn btn-sm" style="background: #fef3c7; color: #92400e; border-radius: 50% !important; width: 28px; height: 28px; padding: 0; justify-content: center;"><i class="fas fa-plus"></i></button>
                </div>
                
                <div id="reexamForm" style="display: none; margin-bottom: 1.5rem; padding: 1.25rem; background: white; border-radius: 16px; border: 1px solid #fde68a;">
                    <form action="manage_reexam.php" method="POST">
                        <input type="hidden" name="patient_id" value="<?php echo $id; ?>">
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Dịch vụ / Ghi chú</label>
                            <input type="text" name="service_name" class="form-input" placeholder="Tái khám Chiro..." required style="padding: 0.5rem; font-size: 0.85rem;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Định kỳ (tháng)</label>
                                <select name="frequency" class="form-input" style="padding: 0.5rem; font-size: 0.85rem;">
                                    <option value="1">1 tháng</option>
                                    <option value="3" selected>3 tháng</option>
                                    <option value="6">6 tháng</option>
                                    <option value="12">1 năm</option>
                                    <option value="0">Chỉ một lần</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">Ngày bắt đầu</label>
                                <input type="date" name="first_date" class="form-input" value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>" style="padding: 0.4rem; font-size: 0.85rem;">
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                             <button type="submit" class="btn btn-sm btn-primary" style="background: #d97706; border: none; font-weight: 700;">Thiết lập</button>
                             <button type="button" onclick="toggleReexamForm()" class="btn btn-sm" style="background: #f1f5f9; font-weight: 700;">Hủy</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($rules)): ?>
                    <p style="font-size: 0.85rem; color: #92400e; opacity: 0.7;">Chưa thiết lập nhắc nhở.</p>
                <?php else: ?>
                    <?php foreach ($rules as $rule): ?>
                        <div style="background: white; padding: 1rem; border-radius: 12px; margin-bottom: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;"><?php echo e($rule['service_name']); ?></div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: <?php echo strtotime($rule['next_due_at']) <= time() ? '#dc2626' : '#ea580c'; ?>; margin-top: 0.25rem;">
                                    Hẹn: <?php echo date('d/m/Y', strtotime($rule['next_due_at'])); ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <a href="../appointments/add.php?contact_id=patient:<?php echo $id; ?>&type=re_exam&reexam_rule_id=<?php echo $rule['id']; ?>" class="btn btn-sm" style="background: #fef3c7; color: #92400e; font-size: 0.7rem; padding: 0.25rem 0.5rem;">Đặt lịch</a>
                                <form action="manage_reexam.php" method="POST" style="margin: 0;" onsubmit="return confirm('Xóa nhắc nhở này?')">
                                    <input type="hidden" name="patient_id" value="<?php echo $id; ?>">
                                    <input type="hidden" name="id" value="<?php echo $rule['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" style="background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 0.25rem;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.info-section-title { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 800; letter-spacing: 1px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
.info-section-title i { color: var(--primary-light); font-size: 0.9rem; }
.info-item { display: flex; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f8fafc; font-size: 0.9rem; }
.info-item span { color: var(--text-muted); }
.social-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; text-decoration: none; transition: all 0.2s; }
.social-icon:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.timeline-node:last-child { margin-bottom: 0 !important; }

/* Filter Pill Styles */
.filter-pill {
    padding: 0.4rem 1rem;
    border-radius: 50px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.filter-pill:hover { background: #e2e8f0; }
.filter-pill.active {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}
</style>

<script>
function toggleReexamForm() {
    const form = document.getElementById('reexamForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function filterTimeline(type, event) {
    // Update active pill
    document.querySelectorAll('.filter-pill').forEach(pill => {
        pill.classList.remove('active');
    });
    
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    const nodes = document.querySelectorAll('.timeline-node');
    nodes.forEach(node => {
        if (type === 'all' || node.getAttribute('data-type') === type) {
            node.style.display = 'block';
        } else {
            node.style.display = 'none';
        }
    });
}
</script>

<?php require_once '../../templates/footer.php'; ?>
