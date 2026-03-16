<?php
// modules/medical/view_form.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

$id = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("
    SELECT h.*, p.full_name as patient_name, u.full_name as doctor_name 
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
$type_label = [
    'chiropractic' => 'Theo dõi Chiro (SOAP)',
    'dong_y' => 'Phiếu Đông Y',
    'initial_exam' => 'Khám Chiro (Hệ thống cũ)',
    'chiro_history' => 'Tiền sử bệnh Chiropractic',
    'chiro_exam' => 'Khám thực thể Chiropractic'
][$record['type']] ?? 'Hồ sơ y tế';

$page_title = 'Xem ' . $type_label;
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<div class="card" style="background: var(--glass-bg); backdrop-filter: blur(20px);">
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem;">
        <div>
            <h2 style="margin: 0; color: var(--primary);"><?php echo $type_label; ?></h2>
            <p style="color: var(--text-muted);">
                Bệnh nhân: <strong><?php echo e($record['patient_name']); ?></strong> | 
                Ngày tạo: <?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?> | 
                Bác sĩ khám: <strong style="color: var(--primary)"><?php echo e($record['doctor_name'] ?: 'N/A'); ?></strong>
            </p>
        </div>
        <a href="javascript:window.print()" class="btn btn-sm" style="background: #f1f5f9;">
            <i class="fas fa-print"></i> In hồ sơ
        </a>
    </div>

    <?php if ($record['type'] === 'initial_exam' || $record['type'] === 'chiro_history' || $record['type'] === 'chiro_exam'): ?>
        <!-- Initial Exam / History / Exam View -->
        <div class="printable-content">
            <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 2rem; color: var(--primary); font-weight: 800; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-stethoscope"></i> 
                <?php 
                if ($record['type'] === 'chiro_history') echo 'TIỀN SỬ BỆNH CHIROPRACTIC';
                elseif ($record['type'] === 'chiro_exam') echo 'KẾT QUẢ KHÁM BỆNH CHIROPRACTIC';
                else echo 'KẾT QUẢ KHÁM LẦN ĐẦU';
                ?>
            </h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Column 1: History & Bio -->
                <div>
                    <!-- Bio -->
                    <?php if(isset($data['biometrics'])): ?>
                    <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px dashed #cbd5e1; padding-bottom: 0.5rem;">Thông số & Lối sống</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div><small style="color: grey;">Cao/Nặng:</small><br><strong><?php echo e($data['biometrics']['height'] ?? '--'); ?>cm / <?php echo e($data['biometrics']['weight'] ?? '--'); ?>kg</strong></div>
                            <div><small style="color: grey;">Huyết áp:</small><br><strong><?php echo e($data['biometrics']['blood_pressure'] ?? '--'); ?></strong></div>
                        </div>
                        <div style="margin-top: 1rem; font-size: 0.85rem;">
                            <i class="fas fa-briefcase"></i> <?php echo e(is_array($data['lifestyle']['job'] ?? null) ? implode(', ', $data['lifestyle']['job']) : ($data['lifestyle']['job'] ?? 'N/A')); ?><br>
                            <i class="fas fa-running"></i> <?php echo e($data['lifestyle']['exercise'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- History -->
                    <?php if(isset($data['history'])): ?>
                    <div style="background: #fdf2f8; padding: 1.5rem; border-radius: 16px; border: 1px solid #fbcfe8; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.8rem; color: #be185d; text-transform: uppercase; margin-bottom: 1rem;">Tiền sử & Can thiệp</h4>
                        <div style="font-size: 0.9rem; line-height: 1.6;">
                            <strong>Trauma:</strong> <?php echo is_array($data['history']['trauma'] ?? null) ? implode(', ', $data['history']['trauma']) : 'N/A'; ?><br>
                            <strong>PT:</strong> <?php echo e($data['history']['surgery'] ?: 'Không'); ?><br>
                            <strong>Gãy xương:</strong> <?php echo e($data['history']['fracture'] ?: 'Không'); ?><br>
                            <?php if(!empty($data['history']['implants'])): ?><span class="badge" style="background: #be185d; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem;">Implant nha khoa</span><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Column 2: Pathology & Warnings -->
                <div>
                    <!-- Warnings -->
                    <?php if(!empty($data['history']['warnings'])): ?>
                    <div style="background: #fff1f2; padding: 1.5rem; border-radius: 16px; border: 2px solid #fecaca; margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.8rem; color: #dc2626; text-transform: uppercase; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-exclamation-triangle"></i> Cảnh báo Lâm sàng
                        </h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                            <?php foreach($data['history']['warnings'] as $w): ?>
                                <span style="background: #dc2626; color: white; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 700;"><?php echo e($w); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pain & ROS -->
                    <?php if(isset($data['pathology'])): ?>
                    <div style="background: #eff6ff; padding: 1.5rem; border-radius: 16px; border: 1px solid #bfdbfe;">
                        <h4 style="font-size: 0.8rem; color: #1e40af; text-transform: uppercase; margin-bottom: 1rem;">Triệu chứng & Rà soát hệ thống</h4>
                        <div style="margin-bottom: 1rem;">
                            <span style="font-size: 1.5rem; font-weight: 800; color: #ef4444;"><?php echo e($data['pathology']['intensity'] ?? 0); ?>/10</span>
                            <span style="color: grey; margin-left: 0.5rem;">(<?php echo e($data['pathology']['duration'] ?? 'N/A'); ?>)</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                            <?php 
                            $tags = array_merge(
                                is_array($data['pathology']['location'] ?? null) ? $data['pathology']['location'] : [],
                                is_array($data['pathology']['character'] ?? null) ? $data['pathology']['character'] : [],
                                is_array($data['pathology']['systems'] ?? null) ? $data['pathology']['systems'] : []
                            );
                            foreach($tags as $tag): 
                            ?>
                                <span style="background: white; border: 1px solid #3b82f6; color: #1d4ed8; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;"><?php echo e($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($record['type'] === 'initial_exam' || $record['type'] === 'chiro_exam'): ?>
            <!-- PHYSICAL EXAM -->
            <h4 style="font-size: 0.9rem; color: var(--primary); text-transform: uppercase; margin: 2rem 0 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-bone"></i> Khám Thực Thể (Subluxation)
            </h4>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <?php 
                $spine_groups = [
                    'Cervical' => ['C1','C2','C3','C4','C5','C6','C7'],
                    'Thoracic' => ['D1','D2','D3','D4','D5','D6','D7','D8','D9','D10','D11','D12'],
                    'Lumbar' => ['L1','L2','L3','L4','L5'],
                    'Others' => ['Sac','Coc','Rlli','Llli']
                ];
                foreach ($spine_groups as $group => $nodes): ?>
                    <div style="padding: 0.75rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <h5 style="font-size: 0.75rem; text-align: center; color: #64748b; margin-top: 0;"><?php echo $group; ?></h5>
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <?php foreach ($nodes as $node): ?>
                                <?php if(isset($data['spine'][$node])): ?>
                                <tr style="text-align: center;">
                                    <td style="width: 33%;"><?php echo isset($data['spine'][$node]['L']) ? '<b style="color:var(--primary)">L</b>' : ''; ?></td>
                                    <td style="font-weight: 700; padding: 2px;"><?php echo $node; ?></td>
                                    <td style="width: 33%;"><?php echo isset($data['spine'][$node]['R']) ? '<b style="color:var(--primary)">R</b>' : ''; ?></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Becken & Joints -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                <div style="padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h5 style="font-size: 0.75rem; text-align: center; color: #64748b; margin-top: 0;">Pelvis</h5>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center;">
                        <?php foreach($data['becken'] ?? [] as $k => $v): ?>
                            <span style="font-size: 0.8rem;"><b><?php echo $k; ?>:</b> <?php echo isset($v['L']) ? 'L' : ''; ?><?php echo isset($v['R']) ? 'R' : ''; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h5 style="font-size: 0.75rem; text-align: center; color: #64748b; margin-top: 0;">Joints</h5>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; justify-content: center;">
                        <?php foreach($data['joints'] ?? [] as $k => $v): ?>
                            <span style="font-size: 0.8rem;"><b><?php echo $k; ?>:</b> <?php echo isset($v['L']) ? 'L' : ''; ?><?php echo isset($v['R']) ? 'R' : ''; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php endif; ?>

            <!-- Goals & Notes -->
            <?php if ($record['type'] === 'initial_exam' || $record['type'] === 'chiro_history' || $record['type'] === 'chiro_exam'): ?>
            <div style="background: #fdf2ff; border-radius: 12px; padding: 1.5rem; border-left: 5px solid #a855f7; margin-top: 2rem;">
                <h4 style="margin-top: 0; font-size: 0.9rem; color: #7e22ce;">Mục tiêu & Chẩn đoán:</h4>
                <?php if(isset($data['goals'])): ?>
                    <div style="margin-bottom: 0.5rem;"><b style="color: #7e22ce;">Mục tiêu:</b> <?php echo e($data['goals']); ?></div>
                <?php endif; ?>
                <p style="margin-bottom: 0; font-style: italic;">
                    <?php 
                    echo nl2br(e(
                        $data['doctor_notes'] ?? 
                        $data['clinical_notes'] ?? 
                        $data['additional_notes'] ?? 
                        ''
                    )); 
                    ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif ($record['type'] === 'chiropractic' && isset($data['s'])): ?>
        <!-- SOAP Note View (Follow-up sessions) -->
        <div class="printable-content">
            <h3 style="border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 2rem; color: #4f46e5; font-weight: 800; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-notes-medical"></i> PHIẾU THEO DÕI ĐIỀU TRỊ (SOAP NOTE)
            </h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Section S: Subjective -->
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 0.9rem; color: var(--primary); text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px dashed #cbd5e1; padding-bottom: 0.5rem;">1. Chủ quan (S)</h4>
                    <div style="margin-bottom: 1rem;">
                        <span style="background: var(--primary); color: white; padding: 2px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;"><?php echo e($data['s']['progress'] ?? 'No data'); ?></span>
                        <span style="background: #ef4444; color: white; padding: 2px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; margin-left: 0.5rem;">VAS: <?php echo e($data['s']['vas'] ?? 0); ?>/10</span>
                    </div>
                    <div style="font-size: 0.85rem; line-height: 1.6;">
                        <i class="fas fa-clock"></i> <b>Tần suất:</b> <?php echo e($data['s']['frequency'] ?? 'N/A'); ?><br>
                        <i class="fas fa-exclamation-circle"></i> <b>Đau khi:</b> <?php echo is_array($data['s']['activities'] ?? null) ? implode(', ', $data['s']['activities']) : 'N/A'; ?>
                    </div>
                </div>

                <!-- Section O: Objective -->
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0;">
                    <h4 style="font-size: 0.9rem; color: #10b981; text-transform: uppercase; margin-bottom: 1rem; border-bottom: 1px dashed #cbd5e1; padding-bottom: 0.5rem;">2. Khách quan (O)</h4>
                    <div style="font-size: 0.85rem; line-height: 1.6;">
                        <b>Cơ (Hypertonicity):</b> <?php echo e($data['o']['muscle_tone'] ?: 'N/A'); ?> (<?php echo e($data['o']['severity'] ?? ''); ?>)<br>
                        <b>Hạn chế ROM:</b> <?php echo is_array($data['o']['rom_limit'] ?? null) ? implode(', ', $data['o']['rom_limit']) : 'Không'; ?><br>
                        <b>Ghi chú:</b> <?php echo e($data['o']['notes'] ?: 'Trống'); ?>
                    </div>
                </div>
            </div>

            <!-- Section A: Assessment & Adjustments -->
            <div style="background: #fffbeb; padding: 1.5rem; border-radius: 16px; border: 1px solid #fef3c7; margin-bottom: 2rem;">
                <h4 style="font-size: 0.9rem; color: #b45309; text-transform: uppercase; margin-bottom: 1rem;">3. Đánh giá & Nắn chỉnh (A)</h4>
                
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; font-family: monospace; background: white; padding: 1rem; border-radius: 8px;">
                    <?php 
                    $adj_list = [];
                    foreach($data['a']['spine'] ?? [] as $k => $v) {
                        $sides = [];
                        if(isset($v['L'])) $sides[] = 'L';
                        if(isset($v['R'])) $sides[] = 'R';
                        $adj_list[] = "<span style='color:#b45309'>DR</span> <b>$k</b>(".implode('/',$sides).")";
                    }
                    echo count($adj_list) > 0 ? implode(' | ', $adj_list) : '<i style="color:grey">Không có nắn chỉnh đốt sống.</i>';
                    ?>
                </div>

                <div style="border-top: 1px dashed #fcd34d; padding-top: 1rem; font-size: 0.9rem;">
                    <b>VLTL / PHCN:</b> <?php echo is_array($data['a']['physiotherapy'] ?? null) ? implode(', ', $data['a']['physiotherapy']) : 'Không'; ?>
                </div>
            </div>

            <!-- Section P: Plan -->
            <div style="background: #eef2ff; padding: 1.5rem; border-radius: 16px; border: 1px solid #c7d2fe;">
                <h4 style="font-size: 0.9rem; color: #3730a3; text-transform: uppercase; margin-bottom: 1rem;">4. Kế hoạch (P)</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1rem;">
                    <div><b>Đánh giá:</b> <?php echo e($data['p']['evaluation'] ?? 'N/A'); ?></div>
                    <div><b>Tần suất:</b> <?php echo e($data['p']['frequency'] ?? 'N/A'); ?></div>
                </div>
                <p style="margin-bottom: 0; font-size: 0.9rem; font-style: italic; background: white; padding: 0.75rem; border-radius: 8px;"><b>Notes:</b> <?php echo e($data['p']['notes'] ?: 'N/A'); ?></p>
            </div>
        </div>

    <?php else: ?>
        <!-- Standard Questionnaire View -->
        <div class="printable-content">
            <?php foreach ($data as $section => $options): ?>
                <?php if ($section === 'additional_notes') continue; ?>
                <div style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1rem; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                        <?php echo $section; ?>
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php if (is_array($options)): ?>
                            <?php foreach ($options as $opt): ?>
                                <span style="background: rgba(99, 102, 241, 0.08); color: var(--primary); padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; border: 1px solid rgba(99, 102, 241, 0.2);">
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
                <div style="margin-top: 2rem; padding: 1rem; background: #f8fafc; border-radius: 12px; border: 1px dashed #e2e8f0;">
                    <h4 style="margin-top: 0; color: var(--text-muted);">Ghi chú thêm:</h4>
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
@media print {
    .no-print { display: none !important; }
    .card { box-shadow: none !important; border: none !important; }
    body { background: white !important; }
}
</style>

<?php require_once '../../templates/footer.php'; ?>
