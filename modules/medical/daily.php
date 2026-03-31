<?php
// modules/medical/daily.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('view_medical');

$page_title = __('medical.daily.title');
$current_page = 'medical';
require_once '../../templates/header.php';

$db = getDB();
$now = date('Y-m-d');

// 1. ARRIVED (Chờ khám) - Patients arrived today but don't have a session today yet
$stmt_arrived = $db->prepare("
    SELECT p.id, p.full_name, p.phone, p.gender, a.appointment_date
    FROM patients p
    JOIN appointments a ON p.id = a.patient_id
    WHERE DATE(a.appointment_date) = ? AND a.status = 'arrived'
      AND NOT EXISTS (
          SELECT id FROM medical_sessions ms 
          WHERE ms.patient_id = p.id AND DATE(ms.session_date) = ?
      )
    ORDER BY a.appointment_date ASC
");
$stmt_arrived->execute([$now, $now]);
$arrived_patients = $stmt_arrived->fetchAll();

// 2. ACTIVE (Đang khám)
$stmt_active = $db->prepare("
    SELECT ms.id, ms.patient_id, ms.status, ms.created_at, p.full_name, p.gender, u.full_name as doctor_name
    FROM medical_sessions ms
    JOIN patients p ON ms.patient_id = p.id
    LEFT JOIN users u ON ms.doctor_id = u.id
    WHERE DATE(ms.session_date) = ? AND ms.status = 'active'
    ORDER BY ms.created_at ASC
");
$stmt_active->execute([$now]);
$active_sessions = $stmt_active->fetchAll();

// 3. COMPLETED (Đã khám xong)
$stmt_completed = $db->prepare("
    SELECT ms.id, ms.patient_id, ms.status, ms.updated_at, p.full_name, p.gender, u.full_name as doctor_name
    FROM medical_sessions ms
    JOIN patients p ON ms.patient_id = p.id
    LEFT JOIN users u ON ms.doctor_id = u.id
    WHERE DATE(ms.session_date) = ? AND ms.status = 'completed'
    ORDER BY ms.updated_at ASC
");
$stmt_completed->execute([$now]);
$completed_sessions = $stmt_completed->fetchAll();

?>

<style>
.kanban-board {
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    padding-bottom: 2rem;
    align-items: flex-start;
}

.kanban-column {
    flex: 1;
    min-width: 320px;
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.kanban-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e2e8f0;
}

.kanban-title {
    font-size: 1rem;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-transform: uppercase;
}

.kanban-badge {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 800;
}

.kanban-card {
    background: white;
    border-radius: 10px;
    padding: 0.85rem;
    box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
    border-left: 4px solid var(--primary);
}

.kanban-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.kanban-card.status-arrived { border-left-color: #f59e0b; }
.kanban-card.status-active { border-left-color: #3b82f6; }
.kanban-card.status-completed { border-left-color: #10b981; }

.kc-patient-name {
    font-weight: 800;
    font-size: 0.95rem;
    color: #0f172a;
    margin-bottom: 0.15rem;
}

.kc-meta {
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.4rem;
}

.kc-doctor {
    background: #f1f5f9;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: #334155;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.5rem;
}

.kc-actions {
    display: flex;
    gap: 0.5rem;
    border-top: 1px dashed #e2e8f0;
    padding-top: 0.5rem;
    margin-top: 0.5rem;
}

.kc-btn {
    flex: 1;
    text-align: center;
    padding: 0.4rem;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.2s;
}

.kc-btn-primary { background: var(--primary); color: white; }
.kc-btn-primary:hover { opacity: 0.9; color: white; }
.kc-btn-secondary { background: #f1f5f9; color: #475569; }
.kc-btn-secondary:hover { background: #e2e8f0; color: #334155; }
.kc-btn-success { background: #10b981; color: white; }
.kc-btn-success:hover { opacity: 0.9; color: white; }

/* Empty state */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #94a3b8;
    font-weight: 600;
    font-size: 0.9rem;
}
</style>

<div style="margin-bottom: 2rem;">
    <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem;">
        <i class="fas fa-calendar-day" style="color: var(--primary);"></i> <?php echo __('medical.daily.title'); ?>
    </h2>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;">
        <?php echo __('medical.daily.desc'); ?>
    </p>
</div>

<div class="kanban-board">
    
    <!-- COLUMN 1: ARRIVED -->
    <div class="kanban-column">
        <div class="kanban-header">
            <h3 class="kanban-title"><i class="fas fa-walking" style="color: #f59e0b;"></i> <?php echo __('medical.daily.arrived'); ?></h3>
            <span class="kanban-badge" style="background: #f59e0b;"><?php echo count($arrived_patients); ?></span>
        </div>
        
        <?php if (empty($arrived_patients)): ?>
            <div class="empty-state">
                <i class="fas fa-mug-hot" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i><br>
                <?php echo __('medical.daily.no_arrived'); ?>
            </div>
        <?php else: ?>
            <?php foreach ($arrived_patients as $p): ?>
                <div class="kanban-card status-arrived">
                    <div class="kc-patient-name"><?php echo e($p['full_name']); ?></div>
                    <div style="color: #64748b; font-size: 0.8rem; margin-bottom: 0.5rem;"><i class="fas fa-phone"></i> <?php echo e($p['phone'] ?: 'No phone'); ?></div>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 0.6rem; margin-top: 0.5rem;">
                        <div style="color: #f59e0b; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($p['appointment_date'])); ?>
                        </div>
                        <a href="session_start.php?patient_id=<?php echo $p['id']; ?>" class="kc-btn kc-btn-primary" style="flex: none; padding: 0.3rem 0.75rem; font-size: 0.75rem;">
                            <?php echo __('medical.daily.btn_exam'); ?> <i class="fas fa-play" style="margin-left: 0.3rem;"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- COLUMN 2: ACTIVE -->
    <div class="kanban-column">
        <div class="kanban-header">
            <h3 class="kanban-title"><i class="fas fa-stethoscope" style="color: #3b82f6;"></i> <?php echo __('medical.daily.active'); ?></h3>
            <span class="kanban-badge" style="background: #3b82f6;"><?php echo count($active_sessions); ?></span>
        </div>
        
        <?php if (empty($active_sessions)): ?>
            <div class="empty-state">
                <i class="fas fa-bed" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i><br>
                <?php echo __('medical.daily.no_active'); ?>
            </div>
        <?php else: ?>
            <?php foreach ($active_sessions as $s): ?>
                <div class="kanban-card status-active">
                    <div class="kc-patient-name"><?php echo e($s['full_name']); ?></div>
                    <?php if ($s['doctor_name']): ?>
                    <div class="kc-doctor">
                        <i class="fas fa-user-md"></i> <?php echo e($s['doctor_name']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 0.6rem; margin-top: 0.5rem;">
                        <div style="color: #3b82f6; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fas fa-stopwatch"></i> <?php echo date('H:i', strtotime($s['created_at'])); ?>
                        </div>
                        <a href="session_view.php?id=<?php echo $s['id']; ?>" class="kc-btn kc-btn-primary" style="flex: none; padding: 0.3rem 0.75rem; font-size: 0.75rem;">
                            <?php echo __('medical.daily.btn_dossier'); ?> <i class="fas fa-arrow-right" style="margin-left: 0.3rem;"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- COLUMN 3: COMPLETED -->
    <div class="kanban-column">
        <div class="kanban-header">
            <h3 class="kanban-title"><i class="fas fa-check-circle" style="color: #10b981;"></i> <?php echo __('medical.daily.completed'); ?></h3>
            <span class="kanban-badge" style="background: #10b981;"><?php echo count($completed_sessions); ?></span>
        </div>
        
        <?php if (empty($completed_sessions)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i><br>
                <?php echo __('medical.daily.no_completed'); ?>
            </div>
        <?php else: ?>
            <?php foreach ($completed_sessions as $s): ?>
                <div class="kanban-card status-completed">
                    <div class="kc-patient-name"><?php echo e($s['full_name']); ?></div>
                    <?php if ($s['doctor_name']): ?>
                    <div class="kc-doctor" style="background: #f0fdf4; color: #166534;">
                        <i class="fas fa-user-md"></i> <?php echo e($s['doctor_name']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #e2e8f0; padding-top: 0.6rem; margin-top: 0.5rem;">
                        <div style="color: #10b981; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 0.4rem;">
                            <i class="fas fa-check-circle"></i> <?php echo date('H:i', strtotime($s['updated_at'])); ?>
                        </div>
                        <div style="display: flex; gap: 0.3rem; flex: none;">
                            <a href="session_view.php?id=<?php echo $s['id']; ?>" class="kc-btn kc-btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                <i class="fas fa-eye"></i> <?php echo __('medical.daily.btn_view'); ?>
                            </a>
                            <a href="print_session.php?id=<?php echo $s['id']; ?>" target="_blank" class="kc-btn kc-btn-success" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                <i class="fas fa-print"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once '../../templates/footer.php'; ?>
