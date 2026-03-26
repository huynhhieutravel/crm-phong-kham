<?php
// modules/medical/view_form.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_medical');

$id = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT h.*, p.full_name as patient_name, p.birthday, p.occupation, u.full_name as doctor_name 
    FROM medical_history h 
    JOIN patients p ON h.patient_id = p.id 
    LEFT JOIN users u ON h.created_by = u.id 
    WHERE h.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Hồ sơ không tồn tại.");
}

$data = json_decode($record['history_data'], true);
$type_map_list = [
    'chiropractic'  => 'Theo dõi SOAP',
    'soap_note'     => 'Theo dõi SOAP',
    'dong_y'        => 'Phiếu khám Đông Y',
    'initial_exam'  => 'Khám Chiro (Hệ thống cũ)',
    'chiro_history' => 'Khám tiền sử bệnh Chiropractic',
    'chiro_exam'    => 'Khám bệnh lần đầu Chiropractic'
];
$type_label = $type_map_list[$record['type']] ?? 'Hồ sơ y tế';

$page_title = 'Xem ' . $type_label;
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<div class="card" style="background: white; border: none; box-shadow: var(--shadow-premium); padding: 3rem; border-radius: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 2rem;">
        <div>
            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;">Hồ sơ y khoa - <?php echo $record['type']; ?></div>
            <h1 style="margin: 0; font-size: 2rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;"><?php echo $type_label; ?></h1>
            <div style="display: flex; gap: 1.5rem; margin-top: 1rem; font-size: 0.95rem; color: var(--text-muted); font-weight: 500;">
                <span><i class="fas fa-user" style="color: var(--primary);"></i> <strong><?php echo e($record['patient_name']); ?></strong></span>
                <span><i class="fas fa-user-md" style="color: var(--primary);"></i> <strong><?php echo e($record['doctor_name'] ?: 'N/A'); ?></strong></span>
                <span><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?></span>
            </div>
        </div>
        <div class="no-print">
            <button onclick="window.print()" class="btn" style="background: #0f172a; color: white;">
                <i class="fas fa-print"></i> In hồ sơ
            </button>
        </div>
    </div>

    <?php if ($record['type'] === 'chiro_history'): ?>
        <div class="printable-content">
            <!-- PART 1 -->
            <h3 class="view-header-section" style="color: var(--primary); border-bottom-color: var(--border-color);">
                <i class="fas fa-user-check"></i> PHẦN 1: THÔNG TIN CƠ BẢN & LỐI SỐNG
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div class="info-box">
                    <span class="label">Chiều cao</span>
                    <div class="value"><?php echo ($data['biometrics']['height'] ?? '--'); ?> cm</div>
                </div>
                <div class="info-box">
                    <span class="label">Cân nặng</span>
                    <div class="value"><?php echo ($data['biometrics']['weight'] ?? '--'); ?> kg</div>
                </div>
                <div class="info-box">
                    <span class="label">Huyết áp</span>
                    <div class="value"><?php echo ($data['biometrics']['blood_pressure'] ?? '--'); ?> mmHg</div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div class="info-box" style="background: #f8fafc;">
                    <span class="label">Đặc thù công việc</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                        <?php foreach (($data['lifestyle']['job'] ?? []) as $j): ?>
                            <span class="tag-active" style="background: #64748b;"><?php echo $j; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="info-box" style="background: #f8fafc;">
                    <span class="label">Tần suất vận động</span>
                    <div class="value" style="margin-top: 0.5rem;"><span class="tag-outline"><?php echo ($data['lifestyle']['exercise'] ?? 'N/A'); ?></span></div>
                </div>
            </div>

            <div class="info-box" style="background: #f8fafc; margin-bottom: 2rem;">
                <span class="label">Tiền sử bản thân (Birth History)</span>
                <div class="value" style="margin-top: 0.5rem; font-weight: 700; color: var(--primary);">
                    <i class="fas fa-baby"></i> 
                    <?php 
                    $bh = $data['lifestyle']['birth_history'] ?? 'N/A';
                    if ($bh === 'Khác' && !empty($data['lifestyle']['birth_history_other'])) {
                        $bh .= ' (' . $data['lifestyle']['birth_history_other'] . ')';
                    }
                    echo $bh; 
                    ?>
                </div>
            </div>

            <!-- PART 2 -->
            <h3 class="view-header-section" style="color: var(--primary); border-bottom-color: var(--border-color);">
                <i class="fas fa-file-waveform"></i> PHẦN 2: TÌNH TRẠNG BỆNH LÝ HIỆN TẠI
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <div class="info-box" style="margin-bottom: 1rem;">
                        <span class="label">Mức độ đau (VAS)</span>
                        <div class="value" style="font-size: 1.5rem; font-weight: 800; color: #ef4444;"><?php echo ($data['pathology']['intensity'] ?? '0'); ?>/10</div>
                    </div>
                    <div class="info-box" style="margin-bottom: 1rem;">
                        <span class="label">Thời gian triệu chứng</span>
                        <div class="value" style="font-weight: 700; color: #1e293b;"><?php echo ($data['pathology']['duration'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-box">
                        <span class="label">Vị trí đau chính</span>
                        <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach (($data['pathology']['locations'] ?? []) as $loc): ?>
                                <span class="tag-outline" style="font-size: 0.75rem;">
                                    <?php 
                                    if ($loc === 'Khác' && !empty($data['pathology']['locations_other'])) {
                                        echo 'Khác: ' . $data['pathology']['locations_other'];
                                    } else {
                                        echo $loc;
                                    }
                                    ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="info-box">
                    <span class="label">Mô tả triệu chứng chính</span>
                    <div style="margin-top: 0.5rem; line-height: 1.6; color: #334155;">
                        <strong>Tính chất:</strong> <?php echo implode(', ', ($data['pathology']['nature'] ?? [])); ?><br>
                        <strong>Kích hoạt bởi:</strong> <?php echo implode(', ', ($data['pathology']['triggers'] ?? [])); ?><br>
                        <strong>Nguyên nhân:</strong> <?php echo implode(', ', ($data['pathology']['activating_causes'] ?? [])); ?><br><br>
                        <?php echo nl2br(e($data['pathology']['description'] ?? 'N/A')); ?>
                    </div>
                </div>
            </div>

            <div style="background: #f8fafc; border-radius: 20px; padding: 1.5rem; border: 1px solid #e2e8f0; max-width: 800px; margin: 0 auto 2rem auto;">
                <div style="font-size: 0.8rem; color: var(--primary); font-weight: 800; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="fas fa-map-marker-alt"></i> Sơ đồ điểm đau / Cảnh báo
                </div>
                <div style="position: relative; background: white; border-radius: 12px; border: 1px solid #cbd5e1; overflow: hidden;">
                    <canvas id="history-marking-canvas" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                    <input type="hidden" id="history-marking-data" value='<?php echo json_encode($data['markers'] ?? []); ?>'>
                </div>
            </div>

            <!-- PART 3 -->
            <h3 class="view-header-section" style="color: var(--primary); border-bottom-color: var(--border-color);">
                <i class="fas fa-history"></i> PHẦN 3: TIỀN SỬ Y KHOA & CHẤN THƯƠNG
            </h3>
            <div style="margin-bottom: 2.5rem;">
                 <!-- Emergency Flags -->
                 <?php if (!empty($data['medical_history']['red_flags'])): ?>
                 <div class="info-box" style="background: #fff1f2; border-color: #fecaca; margin-bottom: 1.5rem;">
                    <span class="label" style="color: #991b1b;"><i class="fas fa-exclamation-triangle"></i> DẤU HIỆU CẤP CỨU CẦN LƯU Ý</span>
                    <div style="margin-top: 0.5rem; color: #991b1b; font-weight: 700;">
                        <?php foreach ($data['medical_history']['red_flags'] as $rf): ?>
                            <div>• <?php echo $rf; ?></div>
                        <?php endforeach; ?>
                    </div>
                 </div>
                 <?php endif; ?>

                 <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                    <!-- Causes -->
                    <div class="info-box" style="background: #f8fafc;">
                        <span class="label">Nguyên nhân nghi ngờ</span>
                        <div style="margin-top: 0.5rem;">
                            <?php foreach (($data['medical_history']['causes'] ?? []) as $c): ?>
                                <div style="margin-bottom: 0.5rem;">
                                    <strong><?php echo $c; ?></strong>
                                    <?php if (!empty($data['medical_history']['cause_time'][$c])): ?>
                                        <span style="font-size: 0.8rem; color: #64748b; margin-left: 0.5rem;">(<?php echo $data['medical_history']['cause_time'][$c]; ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Surgery & Fracture (Tiền sử can thiệp) -->
                    <div class="info-box" style="background: #f8fafc;">
                        <span class="label">Tiền sử can thiệp</span>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <?php if ($data['medical_history']['surgery_flag'] ?? false): ?>
                                <div style="margin-bottom: 0.4rem;"><strong>Phẫu thuật:</strong> <?php echo ($data['medical_history']['surgery_area'] ?? ''); ?> (<?php echo ($data['medical_history']['surgery_time'] ?? ''); ?>)</div>
                            <?php endif; ?>
                            <?php if ($data['medical_history']['fracture_flag'] ?? false): ?>
                                <div style="margin-bottom: 0.4rem;"><strong>Gãy xương:</strong> <?php echo ($data['medical_history']['fracture_area'] ?? ''); ?> (<?php echo ($data['medical_history']['fracture_time'] ?? ''); ?>)</div>
                            <?php endif; ?>
                            <?php if ($data['medical_history']['implants'] ?? false): ?>
                                <div style="margin-bottom: 0.4rem;"><strong>Implant/Niềng răng:</strong> <?php echo ($data['medical_history']['implant_time'] ?? 'Có'); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                 </div>

                 <!-- Chronic Groups -->
                 <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div class="info-box" style="background: #fff1f2; border-color: #fecaca;">
                        <span class="label" style="color: #991b1b;">Nhóm bệnh Cơ - Xương - Khớp</span>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.75rem;">
                            <?php foreach (($data['medical_history']['ortho'] ?? []) as $o): ?>
                                <div style="background: #991b1b; color: white; font-size: 0.8rem; padding: 0.5rem 0.75rem; border-radius: 8px; line-height: 1.4;">
                                    <i class="fas fa-check-circle"></i> <?php echo $o; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="info-box" style="background: #eff6ff; border-color: #bfdbfe;">
                        <span class="label" style="color: #1e40af;">Nhóm bệnh Nội khoa & Hệ thống</span>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.75rem;">
                            <?php foreach (($data['medical_history']['internal'] ?? []) as $i): ?>
                                <div style="background: #1e40af; color: white; font-size: 0.8rem; padding: 0.5rem 0.75rem; border-radius: 8px; line-height: 1.4;">
                                    <i class="fas fa-check-circle"></i> <?php echo $i; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                 </div>

                 <!-- Surgical History (Surgical History) - Cột sống & Chấn thương -->
                 <div class="info-box" style="background: #f0fdf4; border-color: #bbf7d0; margin-bottom: 1.5rem;">
                    <span class="label" style="color: #166534; text-decoration: underline;">Tiền sử chấn thương & Can thiệp (Surgical History)</span>
                    <div style="margin-top: 0.75rem; font-size: 0.9rem;">
                        <?php if ($data['surgical_history']['fracture'] ?? false): ?>
                            <div style="margin-bottom: 0.5rem;">• <strong>Gãy xương (Frakturen):</strong> Cột sống/Xương chậu tại: <?php echo ($data['surgical_history']['fracture_area'] ?? 'N/A'); ?></div>
                        <?php endif; ?>
                        <?php if ($data['surgical_history']['spine_surgery'] ?? false): ?>
                            <div style="margin-bottom: 0.5rem;">• <strong>Phẫu thuật cột sống:</strong> Bắt vít/Nẹp/Thay đĩa đệm tại: <?php echo ($data['surgical_history']['spine_surgery_area'] ?? 'N/A'); ?></div>
                        <?php endif; ?>
                        <?php if ($data['surgical_history']['accident'] ?? false): ?>
                            <div style="margin-bottom: 0.5rem;">• <strong>Tai nạn xe cộ/ngã mạnh:</strong> Chấn thương vùng: <?php echo ($data['surgical_history']['accident_area'] ?? 'N/A'); ?></div>
                        <?php endif; ?>
                    </div>
                 </div>

                 <!-- Medications -->
                 <div class="info-box" style="margin-bottom: 1.5rem;">
                    <span class="label">Thuốc/Thực phẩm chức năng (Dùng từ: <?php echo ($data['medical_history']['meds_time'] ?? 'N/A'); ?>)</span>
                    <div style="margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach (($data['medical_history']['meds_common'] ?? []) as $mc): ?>
                            <span class="tag-active" style="background: #334155; font-size: 0.75rem;"><i class="fas fa-pills"></i> <?php echo $mc; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($data['medical_history']['meds_list'])): ?>
                        <div style="margin-top: 0.75rem; font-size: 0.9rem; line-height: 1.6; color: #334155; white-space: pre-wrap; padding-top: 0.75rem; border-top: 1px dashed #cbd5e1;">
                            <strong>Ghi chú / Thuốc khác:</strong><br>
                            <?php echo nl2br(e($data['medical_history']['meds_list'])); ?>
                        </div>
                    <?php endif; ?>
                 </div>

                 <!-- Imaging & Previous Treatments -->
                 <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="info-box">
                        <span class="label">Phim ảnh: X-Ray, MRI/CT</span>
                        <div style="margin-top: 0.5rem;">
                            <?php foreach (($data['medical_history']['imaging'] ?? []) as $img): ?>
                                <span class="tag-outline" style="margin-right: 0.5rem;"><?php echo $img; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="info-box">
                        <span class="label">Đã từng điều trị tại</span>
                        <div style="margin-top: 0.5rem;">
                            <?php foreach (($data['medical_history']['prev_treatments'] ?? []) as $pt): ?>
                                <span class="tag-outline" style="margin-right: 0.5rem;"><?php echo $pt; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                 </div>
            </div>

            <!-- PART 4 -->
            <h3 class="view-header-section" style="color: var(--primary); border-bottom-color: var(--border-color);">
                <i class="fas fa-stethoscope"></i> PHẦN 4: RÀ SOÁT HỆ THỐNG (ROS)
            </h3>
            <div class="info-box" style="background: #f8fafc; margin-bottom: 2.5rem; border-color: #e2e8f0; padding: 1.5rem;">
                <?php 
                $ros_data = $data['medical_history']['ros'] ?? ($data['pathology']['systems'] ?? []);
                $ros_categories = [
                    'Vùng đầu mặt' => ['Đau đầu', 'Chóng mặt', 'Ù tai', 'Vấn đề hàm (Khớp thái dương hàm)', 'Đang niềng răng'],
                    'Cơ quan liên quan' => ['Tê lan xuống ngón tay', 'Đau tức ngực (không do tim)', 'Đau/Tê lan xuống mông/chân', 'Có tiền sử Vẹo cột sống (S-form)'],
                    'Cơ sở hạ tầng (Bàn chân)' => ['Chênh lệch chiều dài chân', 'Hay bị lật sơ mi (bong gân cổ chân)', 'Đang dùng miếng lót giày/đế nâng']
                ];
                
                $found_any = false;
                foreach ($ros_categories as $cat => $items): 
                    $intersect = array_intersect($ros_data, $items);
                    if (!empty($intersect)):
                        $found_any = true;
                ?>
                    <div style="margin-bottom: 1.25rem;">
                        <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 0.6rem; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: #0ea5e9;"></div>
                            <?php echo $cat; ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; padding-left: 1.25rem;">
                            <?php foreach ($intersect as $s): ?>
                                <span class="tag-active" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; font-size: 0.75rem;">
                                    <i class="fas fa-check" style="margin-right: 0.3rem; font-size: 0.65rem;"></i><?php echo $s; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php 
                    endif; 
                endforeach; 

                // Handle items not in specific categories (legacy data)
                $all_cat_items = call_user_func_array('array_merge', array_values($ros_categories));
                $remaining = array_diff($ros_data, $all_cat_items);
                if (!empty($remaining)):
                    $found_any = true;
                ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #e2e8f0;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php foreach ($remaining as $s): ?>
                                <span class="tag-active" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-weight: 600; font-size: 0.75rem;"><?php echo $s; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!$found_any): ?>
                    <div style="color: #94a3b8; font-style: italic; font-size: 0.9rem;">Không có triệu chứng ghi nhận</div>
                <?php endif; ?>
            </div>


            <!-- PART 5 -->
            <h3 class="view-header-section" style="color: var(--primary); border-bottom-color: var(--border-color);">
                <i class="fas fa-bullseye"></i> PHẦN 5: MỤC TIÊU ĐIỀU TRỊ
            </h3>
            <div class="info-box" style="background: #f0fdf4; border-color: #bbf7d0; margin-bottom: 2rem;">
                <div style="font-size: 1.1rem; font-weight: 700; color: #166534; text-align: center;">
                    <i class="fas fa-check-circle"></i> <?php echo ($data['goals'] ?? 'N/A'); ?>
                </div>
            </div>

            <?php if (!empty($data['additional_notes'])): ?>
            <div class="info-box" style="background: #fffbeb; border-color: #fef3c7;">
                <span class="label">Ghi chú bổ sung</span>
                <div style="margin-top: 0.5rem; font-style: italic;"><?php echo nl2br(e($data['additional_notes'])); ?></div>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif ($record['type'] === 'chiro_exam'): ?>
        <!-- MIRROR: Chiro Physical Exam -->
        <div class="printable-content">
            <h3 class="view-header-section" style="color: #7c3aed; border-bottom-color: #ede9fe;">
                <i class="fas fa-bone"></i> PHẦN 1: MA TRẬN CỘT SỐNG (Subluxation)
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <?php 
                $spine_groups = [
                    'Cervical' => ['C1' => 'Atlas', 'C2' => 'Axis', 'C3' => 'C3', 'C4' => 'C4', 'C5' => 'C5', 'C6' => 'C6', 'C7' => 'C7'],
                    'Thoracic' => ['D1' => 'D1', 'D2' => 'D2', 'D3' => 'D3', 'D4' => 'D4', 'D5' => 'D5', 'D6' => 'D6', 'D7' => 'D7', 'D8' => 'D8', 'D9' => 'D9', 'D10' => 'D10', 'D11' => 'D11', 'D12' => 'D12'],
                    'Lumbar' => ['L1' => 'L1', 'L2' => 'L2', 'L3' => 'L3', 'L4' => 'L4', 'L5' => 'L5'],
                    'Others' => ['Sac' => 'Sacrum', 'Coc' => 'Coccyx', 'Rlli' => 'R Ilium', 'Llli' => 'L Ilium']
                ];
                foreach ($spine_groups as $group => $nodes): ?>
                    <div class="info-box" style="padding: 1rem; background: #f8fafc;">
                        <h5 style="font-size: 0.75rem; text-align: center; color: #64748b; margin-top: 0; text-transform: uppercase; margin-bottom: 1rem;"><?php echo $group; ?></h5>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="font-size: 0.65rem; color: #94a3b8; text-align: center;">
                                    <th style="width: 30%;">L</th>
                                    <th style="width: 40%;">ĐỐT</th>
                                    <th style="width: 30%;">R</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nodes as $key => $label): ?>
                                    <tr>
                                        <td style="text-align: center; padding: 3px;">
                                            <?php if(isset($data['spine'][$key]['L'])): ?> <span class="matrix-dot active">L</span> <?php else: ?> <span class="matrix-dot"></span> <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; font-weight: 800; font-size: 0.85rem; color: #334155;"><?php echo $label; ?></td>
                                        <td style="text-align: center; padding: 3px;">
                                            <?php if(isset($data['spine'][$key]['R'])): ?> <span class="matrix-dot active">R</span> <?php else: ?> <span class="matrix-dot"></span> <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
                <div class="info-box" style="background: #f8fafc;">
                    <h4 style="font-size: 0.9rem; margin-bottom: 1.5rem; color: var(--text-muted); text-transform: uppercase; text-align: center;">
                        <i class="fas fa-venus-mars"></i> Vùng Chậu (Becken)
                    </h4>
                    <table style="width: 100%;">
                        <?php 
                        $becken_nodes = ['AS', 'PI', 'IN-Ilium', 'EX-Ilium', 'Up-Slip', 'Down-Slip'];
                        foreach ($becken_nodes as $node): ?>
                            <tr>
                                <td style="text-align: center; padding: 5px; width: 35%;">
                                    <?php if(isset($data['becken'][$node]['L'])): ?> <span class="matrix-dot active">L</span> <?php else: ?> <span class="matrix-dot"></span> <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 0.85rem;"><?php echo $node; ?></td>
                                <td style="text-align: center; padding: 5px; width: 35%;">
                                    <?php if(isset($data['becken'][$node]['R'])): ?> <span class="matrix-dot active">R</span> <?php else: ?> <span class="matrix-dot"></span> <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="info-box" style="background: #f8fafc;">
                    <h4 style="font-size: 0.9rem; margin-bottom: 1.5rem; color: var(--text-muted); text-transform: uppercase; text-align: center;">
                        <i class="fas fa-joint"></i> Khớp ngoại vi
                    </h4>
                    <table style="width: 100%;">
                        <?php 
                        $joint_nodes = ['Khớp vai', 'Khớp khuỷu tay', 'Khớp cổ tay', 'Khớp háng', 'Khớp gối', 'Khớp cổ chân'];
                        foreach ($joint_nodes as $node): ?>
                            <tr>
                                <td style="text-align: center; padding: 5px; width: 35%;">
                                    <?php if(isset($data['joints'][$node]['L'])): ?> <span class="matrix-dot active">L</span> <?php else: ?> <span class="matrix-dot"></span> <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; font-size: 0.85rem;"><?php echo $node; ?></td>
                                <td style="text-align: center; padding: 5px; width: 35%;">
                                    <?php if(isset($data['joints'][$node]['R'])): ?> <span class="matrix-dot active">R</span> <?php else: ?> <span class="matrix-dot"></span> <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Pain Point Marking -->
            <div style="margin-top: 3rem;">
                <h3 class="view-header-section" style="color: #7c3aed; border-bottom-color: #ede9fe;">
                    <i class="fas fa-map-marker-alt"></i> SƠ ĐỒ ĐIỂM ĐAU / CẢNH BÁO
                </h3>
                <div style="background: #f8fafc; border-radius: 20px; padding: 1.5rem; border: 1px solid #e2e8f0; max-width: 800px; margin: 0 auto;">
                    <div style="position: relative; background: white; border-radius: 12px; border: 1px solid #cbd5e1; overflow: hidden;">
                        <canvas id="exam-marking-canvas" width="800" height="800" style="width: 100%; height: auto; display: block;"></canvas>
                        <input type="hidden" id="exam-marking-data" value='<?php echo json_encode($data['markers'] ?? []); ?>'>
                    </div>
                </div>
            </div>

            <div class="info-box" style="margin-top: 2rem; background: #f5f3ff; border-color: #ddd6fe;">
                <span class="label" style="color: #4338ca;">Chẩn đoán & Ghi chú lâm sàng</span>
                <div style="font-style: italic; color: #4338ca; margin-top: 0.75rem; line-height: 1.6;">
                    <?php echo nl2br(e($data['clinical_notes'] ?? 'Chưa ghi nhận chẩn đoán.')); ?>
                </div>
            </div>
        </div>

    <?php elseif ($record['type'] === 'chiropractic'): ?>
        <!-- MIRROR: SOAP Note -->
        <div class="view-section">
            <h3 class="view-header-section" style="border-bottom-color: #dbeafe; color: #3b82f6;">
                <i class="fas fa-notes-medical"></i> PHIẾU THEO DÕI ĐIỀU TRỊ (SOAP)
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div class="info-box" style="border-top: 4px solid #3b82f6;">
                    <h4 style="font-size: 0.9rem; color: #3b82f6; margin-bottom: 1rem; text-transform: uppercase;">PHẦN 1: CHỦ QUAN (Subjective)</h4>
                    <div style="margin-bottom: 0.75rem;"><strong>Tiến triển:</strong> <span class="tag-active" style="background: #3b82f6;"><?php echo ($data['s']['progress'] ?? 'N/A'); ?></span></div>
                    <div style="margin-bottom: 0.75rem;"><strong>Điểm VAS:</strong> <b style="color: #ef4444; font-size: 1.1rem;"><?php echo ($data['s']['vas'] ?? 0); ?>/10</b></div>
                    <div style="font-size: 0.85rem;"><strong>Hoạt động phát sinh:</strong> <?php echo (!empty($data['s']['activities']) ? implode(', ', $data['s']['activities']) : 'N/A'); ?></div>
                </div>

                <div class="info-box" style="border-top: 4px solid #10b981;">
                    <h4 style="font-size: 0.9rem; color: #10b981; margin-bottom: 1rem; text-transform: uppercase;">PHẦN 2: KHÁCH QUAN (Objective)</h4>
                    <div style="margin-bottom: 0.75rem;"><strong>Cơ co thắt:</strong> <?php echo ($data['o']['muscle_tone'] ?? 'N/A'); ?> (<?php echo ($data['o']['severity'] ?? ''); ?>)</div>
                    <div style="margin-bottom: 0.75rem;"><strong>Cứng khớp / Hạn chế:</strong> <?php echo (!empty($data['o']['rom_limit']) ? implode(', ', $data['o']['rom_limit']) : 'Không'); ?></div>
                    <div style="font-size: 0.85rem;"><strong>Ghi chú:</strong> <?php echo ($data['o']['notes'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <div class="info-box" style="border-top: 4px solid #f59e0b; margin-bottom: 2rem;">
                <h4 style="font-size: 0.9rem; color: #f59e0b; margin-bottom: 1rem; text-transform: uppercase;">PHẦN 3: ĐÁNH GIÁ & NẮN CHỈNH (Assessment)</h4>
                <div style="background: #fffbeb; padding: 1rem; border-radius: 12px; border: 1px solid #fef3c7; margin-bottom: 1rem;">
                    <div style="font-size: 0.75rem; color: #92400e; font-weight: 800; margin-bottom: 0.5rem; text-transform: uppercase;">Kỹ thuật Nắn chỉnh Đốt sống:</div>
                    <div style="font-family: monospace; font-size: 0.95rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php 
                        $adj = [];
                        foreach(($data['a']['spine'] ?? []) as $k => $v) { $adj[] = "<span style='background:white; padding: 2px 6px; border-radius: 4px;'>$k (".(isset($v['L'])?'L':'').(isset($v['R'])?'R':'').")</span>"; }
                        echo !empty($adj) ? implode(' ', $adj) : 'Không nắn chỉnh đốt sống.';
                        ?>
                    </div>
                </div>
                <div style="font-size: 0.9rem;">
                    <strong>Vật lý trị liệu:</strong> <?php echo (!empty($data['a']['physiotherapy']) ? implode(', ', $data['a']['physiotherapy']) : 'Không'); ?>
                </div>
            </div>

            <div class="info-box" style="border-top: 4px solid #6366f1; background: #eef2ff; border-color: #e0e7ff;">
                <h4 style="font-size: 0.9rem; color: #4338ca; margin-bottom: 1rem; text-transform: uppercase;">PHẦN 4: KẾ HOẠCH (Plan)</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div><strong>Đánh giá điều trị:</strong> <?php echo ($data['p']['evaluation'] ?? 'N/A'); ?></div>
                    <div><strong>Tần suất nhắc lại:</strong> <?php echo ($data['p']['frequency'] ?? 'N/A'); ?></div>
                </div>
                <div style="margin-top: 1rem; font-style: italic; color: #4338ca; font-size: 0.9rem;">
                    "<?php echo nl2br(e($data['p']['notes'] ?? '')); ?>"
                </div>
            </div>
        </div>

    <?php elseif ($record['type'] === 'dong_y'): ?>
        <?php
            $display_birth_year = $record['birthday'] ? date('Y', strtotime($record['birthday'])) : '--';
            $display_occupation = $record['occupation'] ?: '--';
        ?>
        <div class="printable-content">
            <!-- I. THÔNG TIN CƠ BẢN -->
            <h3 class="view-header-section" style="color: #6366f1; border-bottom-color: #eef2ff;">
                <i class="fas fa-id-card"></i> PHẦN I: THÔNG TIN CƠ BẢN & HUYẾT ÁP
            </h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="info-box">
                    <span class="label">Họ tên</span>
                    <div class="value"><strong><?php echo e($record['patient_name']); ?></strong></div>
                </div>
                <div class="info-box">
                    <span class="label">Năm sinh</span>
                    <div class="value"><strong><?php echo e($display_birth_year); ?></strong></div>
                </div>
                <div class="info-box">
                    <span class="label">Nghề nghiệp</span>
                    <div class="value"><strong><?php echo e($display_occupation); ?></strong></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div class="info-box" style="border-left: 4px solid #6366f1;">
                    <span class="label">Huyết áp Tay Trái</span>
                    <div style="display: flex; justify-content: space-between;">
                        <div>Chỉ số: <strong><?php echo ($data['bp_left'] ?? '--'); ?></strong> <small>mmHg</small></div>
                        <div>Nhịp tim: <strong><?php echo ($data['hr_left'] ?? '--'); ?></strong> <small>lần/phút</small></div>
                    </div>
                </div>
                <div class="info-box" style="border-left: 4px solid #6366f1;">
                    <span class="label">Huyết áp Tay Phải</span>
                    <div style="display: flex; justify-content: space-between;">
                        <div>Chỉ số: <strong><?php echo ($data['bp_right'] ?? '--'); ?></strong> <small>mmHg</small></div>
                        <div>Nhịp tim: <strong><?php echo ($data['hr_right'] ?? '--'); ?></strong> <small>lần/phút</small></div>
                    </div>
                </div>
            </div>

            <div class="info-box" style="margin-bottom: 2.5rem;">
                <span class="label">Lý do đến khám</span>
                <div class="value" style="font-size: 1.1rem; line-height: 1.6;"><?php echo nl2br(e($data['reason'] ?? '--')); ?></div>
            </div>

            <!-- II. VỌNG CHẨN -->
            <h3 class="view-header-section" style="color: #f59e0b; border-bottom-color: #fffbeb;">
                <i class="fas fa-eye"></i> PHẦN II: VỌNG CHẨN (Nhìn)
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div class="info-box">
                    <span class="label">Thần sắc & Sắc mặt</span>
                    <div style="margin-bottom: 0.5rem;">Thần: <strong><?php echo ($data['spirit'] ?? '--'); ?></strong></div>
                    <div>Sắc mặt: <span class="tag-active" style="background: #f59e0b;"><?php echo ($data['face_color'] ?? '--'); ?></span></div>
                </div>
                <div class="info-box">
                    <span class="label">Vọng lưỡi</span>
                    <div style="margin-bottom: 0.5rem;">Chất lưỡi: <span class="tag-active" style="background: #ef4444;"><?php echo ($data['tongue_body'] ?? '--'); ?></span></div>
                    <div style="margin-bottom: 0.5rem;">Hình dáng: <span class="tag-active" style="background: #f43f5e;"><?php echo ($data['tongue_shape'] ?? '--'); ?></span></div>
                    <div style="margin-bottom: 0.75rem;">Đầu lưỡi: <span class="tag-active" style="background: #f87171;"><?php echo ($data['tongue_tip'] ?? '--'); ?></span></div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem;">Rêu lưỡi:</div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                        <?php foreach (($data['tongue_coating'] ?? []) as $v): ?>
                            <span class="tag-outline" style="font-size: 0.8rem; border-color: #fde68a; background: #fffbeb;"><?php echo $v; ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($data['tongue_coating'])) echo '<span class="text-muted">--</span>'; ?>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
                <div class="info-box">
                    <span class="label">Mắt</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <?php foreach (($data['eyes'] ?? []) as $v): ?>
                            <span class="tag-active" style="background: #6b7280;"><?php echo $v; ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($data['eyes'])) echo '<span class="text-muted">--</span>'; ?>
                    </div>
                    <div style="padding-top: 0.5rem; border-top: 1px dashed rgba(0,0,0,0.1);">
                        Mí mắt: <span class="tag-active" style="background: #4b5563;"><?php echo ($data['eyelids'] ?? '--'); ?></span>
                    </div>
                </div>
                <div class="info-box">
                    <span class="label">Niêm mạc môi</span>
                    <div>Tình trạng: <span class="tag-active" style="background: #ec4899;"><?php echo e($data['lips'] ?? '--'); ?></span></div>
                </div>
            </div>

            <!-- III. VĂN CHẨN -->
            <h3 class="view-header-section" style="color: #059669; border-bottom-color: #f0fdf4;">
                <i class="fas fa-volume-up"></i> PHẦN III: VĂN CHẨN (Nghe & Ngửi)
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
                <div class="info-box">
                    <span class="label">Tiếng nói / Hơi thở</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                        <?php foreach (($data['voice_breath'] ?? []) as $v): ?>
                            <span class="tag-active" style="background: #10b981;"><?php echo $v; ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($data['voice_breath'])) echo '<span class="text-muted">--</span>'; ?>
                    </div>
                </div>
                <div class="info-box">
                    <span class="label">Mùi cơ thể</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                        <?php foreach (($data['body_odor'] ?? []) as $v): ?>
                            <span class="tag-active" style="background: #059669;"><?php echo $v; ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($data['body_odor'])) echo '<span class="text-muted">--</span>'; ?>
                    </div>
                </div>
            </div>

            <!-- IV. VẤN CHẨN -->
            <h3 class="view-header-section" style="color: #0284c7; border-bottom-color: #f0f9ff;">
                <i class="fas fa-comments"></i> PHẦN IV: VẤN CHẨN (Hỏi)
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
                <div class="info-box">
                    <span class="label">Tiền sử / Phụ khoa</span>
                    <div class="value"><?php echo ($data['lifestyle_history'] ?? '--'); ?></div>
                </div>
                <div class="info-box">
                    <span class="label">Giấc ngủ</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                        <?php foreach (($data['sleep_quality'] ?? []) as $v): ?>
                            <span class="tag-active" style="background: #3b82f6;"><?php echo e($v); ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($data['sleep_quality'])) echo '<span class="text-muted">--</span>'; ?>
                    </div>
                    <?php if (!empty($data['night_wake_times'])): ?>
                        <div style="margin-top: 0.75rem; font-size: 0.85rem; color: #64748b;">
                            <strong>Tỉnh giấc:</strong> <?php echo implode(', ', $data['night_wake_times']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
                <div class="info-box">
                    <span class="label">Thức dậy & Thói quen</span>
                    <div style="margin-bottom: 0.75rem;">Trạng thái: <strong><?php echo e($data['wake_up_state'] ?? '--'); ?></strong></div>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; padding-top: 0.5rem; border-top: 1px dashed rgba(0,0,0,0.05);">
                        <?php foreach (($data['habits'] ?? []) as $v): ?>
                            <span class="tag-outline" style="border-color: #94a3b8; color: #475569; font-size: 0.8rem;"><?php echo e($v); ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($data['habits'])) echo '<span class="text-muted">--</span>'; ?>
                    </div>
                </div>
                <div class="info-box">
                    <span class="label">Môi trường & Tư thế</span>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <div>Tư thế: <span class="tag-active" style="background: #64748b;"><?php echo e($data['work_posture'] ?? '--'); ?></span></div>
                        <div>Môi trường: <span class="tag-active" style="background: #94a3b8;"><?php echo e($data['living_env'] ?? '--'); ?></span></div>
                    </div>
                </div>
            </div>

            <div class="info-box" style="margin-bottom: 2.5rem; background: #f8fafc; border-left: 4px solid #0891b2;">
                <span class="label" style="color: #0891b2;">Tiêu hóa & Bài tiết</span>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem;">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Ăn uống:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                            <?php foreach (($data['digestion_eating'] ?? []) as $v): ?>
                                <span class="tag-active" style="background: #0891b2; font-size: 0.8rem;"><?php echo e($v); ?></span>
                            <?php endforeach; ?>
                            <?php if (empty($data['digestion_eating'])) echo '<span class="text-muted">--</span>'; ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Đại tiện:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.5rem;">
                            <?php foreach (($data['digestion_excretion'] ?? []) as $v): ?>
                                <span class="tag-active" style="background: #0e7490; font-size: 0.8rem;"><?php echo e($v); ?></span>
                            <?php endforeach; ?>
                            <?php if (empty($data['digestion_excretion'])) echo '<span class="text-muted">--</span>'; ?>
                        </div>
                        <div style="font-size: 0.8rem; color: #475569;">Số lần: <strong><?php echo e($data['excretion_frequency'] ?? '--'); ?></strong></div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1;">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Màu tiểu tiện:</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                            <?php foreach (($data['urine_color'] ?? []) as $v): ?>
                                <span class="tag-active" style="background: #155e75; font-size: 0.8rem;"><?php echo e($v); ?></span>
                            <?php endforeach; ?>
                            <?php if (empty($data['urine_color'])) echo '<span class="text-muted">--</span>'; ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.25rem;">Tiểu đêm:</div>
                        <div class="value">Số lần: <strong><?php echo e($data['night_urine_count'] ?? '--'); ?></strong></div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
                <div class="info-box">
                    <span class="label">Kinh nguyệt (Phụ khoa)</span>
                    <div style="line-height: 1.8;">
                        Chu kỳ: <b><?php echo ($data['menses_regularity'] ?? '--'); ?></b> (<?php echo ($data['menses_days'] ?? '--'); ?> ngày)<br>
                        Đau bụng: <b><?php echo ($data['menses_pain'] ?? '--'); ?></b> | Màu: <b><?php echo ($data['menses_color'] ?? '--'); ?></b><br>
                        Huyết trắng: <b><?php echo ($data['menses_leucorrhoea'] ?? '--'); ?></b>
                    </div>
                </div>
                <div class="info-box">
                    <span class="label">Cảm giác đối với bệnh lý</span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 800; color: #dc2626;">NHIỆT</div>
                            <div style="font-size: 0.85rem;"><?php echo implode(', ', ($data['sensation_heat'] ?? [])); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 800; color: #2563eb;">HÀN</div>
                            <div style="font-size: 0.85rem;"><?php echo implode(', ', ($data['sensation_cold'] ?? [])); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- V. THIẾT CHẨN -->
            <h3 class="view-header-section" style="color: #7c3aed; border-bottom-color: #f5f3ff;">
                <i class="fas fa-hand-holding-heart"></i> PHẦN V: THIẾT CHẨN (Bắt mạch & Sờ nắn)
            </h3>
            <div class="info-box" style="margin-bottom: 2.5rem; background: #faf5ff;">
                <span class="label" style="color: #7c3aed;">Mạch tượng</span>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1rem;">
                    <div style="text-align: center; background: white; padding: 0.75rem; border-radius: 12px; border: 1px solid #e9d5ff;">
                        <div style="font-size: 0.7rem; color: #a855f7; font-weight: 800;">ĐỘ SÂU</div>
                        <div style="font-weight: 700;"><?php echo ($data['pulse_depth'] ?? '--'); ?></div>
                    </div>
                    <div style="text-align: center; background: white; padding: 0.75rem; border-radius: 12px; border: 1px solid #e9d5ff;">
                        <div style="font-size: 0.7rem; color: #a855f7; font-weight: 800;">TỐC ĐỘ</div>
                        <div style="font-weight: 700;"><?php echo ($data['pulse_speed'] ?? '--'); ?></div>
                    </div>
                    <div style="text-align: center; background: white; padding: 0.75rem; border-radius: 12px; border: 1px solid #e9d5ff;">
                        <div style="font-size: 0.7rem; color: #a855f7; font-weight: 800;">HÌNH DẠNG</div>
                        <div style="font-weight: 700;"><?php echo ($data['pulse_texture'] ?? '--'); ?></div>
                    </div>
                    <div style="text-align: center; background: white; padding: 0.75rem; border-radius: 12px; border: 1px solid #e9d5ff;">
                        <div style="font-size: 0.7rem; color: #a855f7; font-weight: 800;">LỰC</div>
                        <div style="font-weight: 700;"><?php echo ($data['pulse_strength'] ?? '--'); ?></div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2.5rem;">
                <div class="info-box">
                    <span class="label">Xúc chẩn (Sờ nắn)</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;">
                        <?php foreach (($data['palpation'] ?? []) as $v): ?>
                            <span class="tag-active" style="background: #9333ea;"><?php echo $v; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="info-box">
                    <span class="label">Cơ bắp & Nhiệt độ</span>
                    <div>Cơ bắp: <strong><?php echo ($data['palpation_muscle'] ?? '--'); ?></strong></div>
                    <div>Thân nhiệt: <strong><?php echo ($data['body_temp'] ?? '--'); ?></strong></div>
                </div>
            </div>

            <!-- VI. TỔNG KẾT -->
            <div class="info-box" style="margin-bottom: 2.5rem; background: #1e293b; color: white; border: none;">
                <span class="label" style="color: #94a3b8; border-bottom-color: #334155;">TỔNG KẾT NHANH (Bát cương)</span>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem;">
                    <?php foreach (($data['bat_cuong'] ?? []) as $v): ?>
                        <span style="background: rgba(255,255,255,0.1); color: #cbd5e1; padding: 0.4rem 1rem; border-radius: 50px; font-weight: 700; border: 1px solid rgba(255,255,255,0.2);">
                            <?php echo $v; ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($data['additional_notes'])): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #334155; line-height: 1.6; color: #e2e8f0; font-style: italic;">
                        "<?php echo nl2br(e($data['additional_notes'])); ?>"
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Generic View (Checklist style) -->
        <div class="printable-content">
            <?php foreach ($data as $section => $options): ?>
                <?php if ($section === 'additional_notes') continue; ?>
                <div style="margin-bottom: 2rem;">
                    <h3 class="view-header-section">
                        <?php echo $section; ?>
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php if (is_array($options)): ?>
                            <?php foreach ($options as $opt): ?>
                                <span class="tag-active" style="background: rgba(99, 102, 241, 0.08); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.2);">
                                    <i class="fas fa-check-circle"></i> <?php echo e($opt); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p><?php echo e($options); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($data['additional_notes'])): ?>
                <div class="view-section">
                    <h4>Ghi chú thêm:</h4>
                    <p><?php echo nl2br(e($data['additional_notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 3rem; display: flex; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 2rem;" class="no-print">
        <a href="../patients/view.php?id=<?php echo $record['patient_id']; ?>" class="btn" style="background: #f1f5f9; color: var(--text-main);">
            <i class="fas fa-arrow-left"></i> Quay lại hồ sơ
        </a>
    </div>
</div>

<style>
.view-section {
    padding: 1.5rem 0;
    margin-bottom: 1.5rem;
}
.view-header-section {
    font-size: 1.1rem;
    font-weight: 800;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 0.75rem;
    text-transform: uppercase;
}
.view-header-section i {
    font-size: 1.25rem;
}
.view-section h4 {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-top: 0;
    margin-bottom: 1rem;
    border-bottom: 1px dashed #e2e8f0;
    padding-bottom: 0.5rem;
}
.view-section.warning {
    background: #fff1f2;
    border-color: #fecaca;
}
.info-box {
    padding: 1.25rem;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
    background: #fff;
}
.info-box .label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 0.5rem;
}
.tag-active {
    background: var(--primary);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    display: inline-block;
}
.tag-outline {
    background: #f8fafc;
    color: var(--text-main);
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 0.8rem;
    font-weight: 700;
    display: inline-block;
}
.matrix-dot {
    display: inline-flex;
    width: 28px;
    height: 28px;
    background: #f1f5f9;
    border-radius: 6px;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 900;
    color: #cbd5e1;
}
.matrix-dot.active {
    background: var(--primary);
    color: white;
}

@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: none !important; padding: 0 !important; }
    body { background: white !important; font-size: 12pt; }
    .printable-content { color: black !important; }
    h3, h4 { color: black !important; border-bottom-color: #000 !important; }
    .view-section { border-color: #ddd !important; }
    .tag-outline { border-color: #ddd !important; }
    [style*="background"] { -webkit-print-color-adjust: exact; }
}
</style>

<script src="../../assets/js/medical_marking.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const imgPath = '../../assets/images/anatomy_4_views_clean.png';
    // History Markers
    if (document.getElementById('history-marking-canvas')) {
        new MedicalMarking(
            'history-marking-canvas', 
            'history-marking-data', 
            imgPath,
            true
        );
    }
    // Exam Markers
    if (document.getElementById('exam-marking-canvas')) {
        new MedicalMarking(
            'exam-marking-canvas', 
            'exam-marking-data', 
            imgPath,
            true
        );
    }
});
</script>

<?php require_once '../../templates/footer.php'; ?>
