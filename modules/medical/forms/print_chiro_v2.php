<?php
// modules/medical/forms/print_chiro_v2.php
// $data is available here
?>
<div class="section-title"><?php echo __('medical.v2.print_title'); ?></div>
<div style="font-size: 13px; line-height: 1.6; margin-bottom: 10px;">
    <strong><?php echo __('medical.v2.print_date'); ?></strong> <?php echo e(__($data['exam_date'] ?? date('Y-m-d'))); ?> | 
    <strong><?php echo __('medical.v2.exam_session'); ?>:</strong> <?php echo e(__($data['exam_session_number'] ?? '1')); ?>
</div>

<div class="rich-content" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; background: #f8fafc; font-size: 13px;">
    
    <!-- S - Chủ quan -->
    <h4 style="margin:0 0 10px 0; color: #4f46e5; font-size: 13px;"><?php echo __('medical.v2.subj_title'); ?></h4>
    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #cbd5e1;">
        <?php if(!empty($data['s_progress'])): ?>
            <div><strong><?php echo __('medical.v2.subj_progress'); ?></strong> <?php echo e($data['s_progress']); ?></div>
        <?php endif; ?>
        <?php if(($data['new_injury_status'] ?? '') === 'Có'): ?>
            <div><strong><?php echo __('medical.v2.subj_new_injury'); ?></strong> <?php echo __('common.yes'); ?> (<?php echo __('medical.v2.exam_date'); ?>: <?php echo e(__($data['new_injury_date'] ?? '')); ?>)</div>
        <?php endif; ?>
        <?php if(!empty($data['s_frequency'])): ?>
            <div><strong><?php echo __('medical.v2.subj_freq'); ?></strong> <?php echo e($data['s_frequency']); ?></div>
        <?php endif; ?>
        <?php if(!empty($data['s_activities'])): ?>
            <div><strong><?php echo __('medical.v2.subj_act'); ?></strong> <?php echo implode(', ', array_map('__', $data['s_activities'])); ?></div>
        <?php endif; ?>
        <?php if(!empty($data['s_vas'])): ?>
            <div><strong><?php echo __('medical.v2.subj_vas_total'); ?></strong> <span style="color:red; font-weight:800;"><?php echo e($data['s_vas']); ?>/10</span></div>
        <?php endif; ?>
        <br>
        <?php if(!empty($data['pain_locations'])): ?>
            <ul style="margin: 5px 0 0 0; padding-left: 20px;">
            <?php foreach($data['pain_locations'] as $pl): if(empty($pl['name'])) continue; ?>
                <li>
                    <strong><?php echo __($pl['name']); ?></strong> - VAS: <?php echo __($pl['vas'] ?? 0); ?>/10 
                    (<em><?php echo __($pl['trend'] ?? 'N/A'); ?></em>)
                    <br><?php echo __('medical.v2.pain_symptoms'); ?>: <?php echo implode(', ', array_map('__', $pl['symptoms'] ?? [])); ?>. 
                    <?php echo __('Ghi chú:'); ?> <?php echo e($pl['notes'] ?? ''); ?>

                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- O - Khách quan -->
    <h4 style="margin:0 0 10px 0; color: #10b981; font-size: 13px;"><?php echo __('medical.v2.obj_title'); ?></h4>
    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #cbd5e1;">
        <?php if(!empty($data['muscle_hypertonicity']) || !empty($data['muscle_severity'])): ?>
            <div><strong><?php echo __('medical.v2.obj_muscle_tone'); ?>:</strong> <?php echo e(__($data['muscle_hypertonicity'] ?? '')); ?> <?php echo !empty($data['muscle_severity']) ? '('.e($data['muscle_severity']).')' : ''; ?></div>
        <?php endif; ?>
        <?php if(!empty($data['fixation_cervical']) || !empty($data['fixation_thoracic']) || !empty($data['fixation_lumbar'])): ?>
        <div>
            <strong><?php echo __('medical.v2.fixation_title'); ?></strong> 
            <?php if(!empty($data['fixation_cervical'])) echo __('medical.v2.ass_cervical_short').": ".e($data['fixation_cervical'])." | "; ?>
            <?php if(!empty($data['fixation_thoracic'])) echo __('medical.v2.ass_thoracic_short').": ".e($data['fixation_thoracic'])." | "; ?>
            <?php if(!empty($data['fixation_lumbar'])) echo __('medical.v2.ass_lumbar_short').": ".e($data['fixation_lumbar']); ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($data['fixation_si_joint']) || !empty($data['fixation_peripheral'])): ?>
        <div>
            <strong><?php echo __('medical.v2.fixation_other'); ?></strong> 
            <?php if(!empty($data['fixation_si_joint'])) echo "SI: ".e($data['fixation_si_joint'])." | "; ?>
            <?php if(!empty($data['fixation_peripheral'])) echo __('medical.v2.ass_peripheral_short').": ".e($data['fixation_peripheral']); ?>
        </div>
        <?php endif; ?>
        <?php if(!empty($data['rom_limitations'])): ?>
            <div><strong><?php echo __('medical.v2.obj_rom'); ?>:</strong> <?php echo implode(', ', array_map('__', $data['rom_limitations'])); ?></div>
        <?php endif; ?>
    </div>

    <!-- A - Đánh giá & Điều trị -->
    <h4 style="margin:0 0 10px 0; color: #f59e0b; font-size: 13px;"><?php echo __('medical.v2.ass_title'); ?></h4>
    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #cbd5e1;">
        <?php 
        $subs = [];
        foreach(($data['subluxation'] ?? []) as $group => $items) {
            foreach($items as $bone => $sides) {
                if(is_array($sides) && !empty($sides)) {
                    $subs[] = "<b>$bone</b> (" . implode(',', $sides) . ")";
                } elseif (!is_array($sides) && $sides == 1) {
                    $subs[] = "<b>$bone</b>";
                }
            }
        }
        if(!empty($subs)) echo "<div style='margin-bottom:8px;'><strong>" . __('medical.v2.subluxation_title') . "</strong> " . implode(' | ', $subs) . "</div>";
        ?>
        <?php if(!empty($data['symptom_notes'])): ?>
            <div><strong><?php echo __('medical.v2.print_diag_ai'); ?></strong> <?php echo nl2br(e($data['symptom_notes'])); ?></div>
        <?php endif; ?>
        <?php if(!empty($data['physiotherapy'])): ?>
            <div><strong><?php echo __('medical.v2.print_physio'); ?></strong> <?php echo implode(', ', array_map('__', $data['physiotherapy'])); ?></div>
        <?php endif; ?>
        <?php if(!empty($data['assessment_notes'])): ?>
            <div style="margin-top:5px; font-style:italic;"><?php echo __('medical.v2.print_add_notes'); ?> <?php echo e($data['assessment_notes']); ?></div>
        <?php endif; ?>
    </div>

    <!-- P - Kế hoạch -->
    <h4 style="margin:0 0 10px 0; color: #6366f1; font-size: 13px;"><?php echo __('medical.v2.plan_title'); ?></h4>
    <div>
        <?php if(!empty($data['progress_assessment'])): ?>
            <div><strong><?php echo __('medical.v2.progress_today'); ?></strong> <?php echo __($data['progress_assessment']); ?></div>
        <?php endif; ?>
        <?php if(!empty($data['treatment_frequency'])): ?>
            <div><strong><?php echo __('medical.v2.plan_today'); ?></strong> <?php echo __($data['treatment_frequency']); ?></div>
        <?php endif; ?>
        <?php if(!empty($data['plan_notes'])): ?>
            <div style="margin-top: 5px;"><strong><?php echo __('medical.v2.plan_notes'); ?></strong><br><?php echo nl2br(e($data['plan_notes'])); ?></div>
        <?php endif; ?>
    </div>

</div>
