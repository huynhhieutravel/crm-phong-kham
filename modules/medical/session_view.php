<?php
// modules/medical/session_view.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$session_id = isset($_GET['id']) ? $_GET['id'] : 0;

if (!$session_id) {
    set_flash(__('medical.session.err_missing_id'), 'error');
    redirect('../patients/index.php');
}

// 1. Fetch Session Info
$stmt = $db->prepare("
    SELECT s.*, p.full_name as patient_name, p.birthday, p.gender, p.id as patient_id, 
           u.full_name as doctor_name
    FROM medical_sessions s
    JOIN patients p ON s.patient_id = p.id
    JOIN users u ON s.doctor_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    set_flash(__('medical.session.err_not_found'), 'error');
    redirect('../patients/index.php');
}

// 2. Handle POST (Assessment & Plan & Status Changes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check Lock: If completed and not admin, block editing
    if ($session['status'] === 'completed' && !has_role('admin')) {
        set_flash(__('medical.session.err_locked'), 'error');
        header("Location: session_view.php?id=$session_id");
        exit;
    }

    // Admin Re-open Logic
    if (isset($_POST['reopen']) && has_role('admin')) {
        // Enforce 7-day rule: Cannot re-open if more than 7 days have passed since session date
        $session_date = new DateTime($session['session_date']);
        $now = new DateTime();
        $interval = $now->diff($session_date);
        
        if ($interval->days > 7) {
            set_flash(__('medical.session.err_reopen_expired'), 'error');
        } else {
            $stmt = $db->prepare("UPDATE medical_sessions SET status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$session_id]);
            log_audit($_SESSION['user_id'], 'reopen_session', 'medical_sessions', $session_id, ['status' => 'completed'], ['status' => 'active']);
            set_flash(__('medical.session.msg_reopened'));
        }
        redirect("session_view.php?id=$session_id");
    }

    $assessment = isset($_POST['assessment']) ? $_POST['assessment'] : '';
    $plan = isset($_POST['treatment_plan']) ? $_POST['treatment_plan'] : '';
    $status = isset($_POST['complete']) ? 'completed' : $session['status'];

    // Audit Logging for Data changes
    $old_data = [
        'assessment' => $session['assessment'],
        'treatment_plan' => $session['treatment_plan'],
        'status' => $session['status']
    ];
    $new_data = [
        'assessment' => $assessment,
        'treatment_plan' => $plan,
        'status' => $status
    ];

    if ($old_data !== $new_data) {
        $stmt = $db->prepare("
            UPDATE medical_sessions 
            SET assessment = ?, treatment_plan = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$assessment, $plan, $status, $session_id]);
        
        log_audit($_SESSION['user_id'], 'update', 'medical_sessions', $session_id, $old_data, $new_data);
        set_flash(__('medical.session.msg_updated'));
    }
    
    if ($status === 'completed' && $old_data['status'] !== 'completed') {
        // Find today's arrived appointment for this patient and mark as completed
        $today = date('Y-m-d');
        $stmt_app = $db->prepare("
            UPDATE appointments 
            SET status = 'completed'
            WHERE patient_id = ? AND DATE(appointment_date) = ? AND status = 'arrived'
        ");
        $stmt_app->execute([$session['patient_id'], $today]);
        
        redirect("../patients/view.php?id=" . $session['patient_id']);
    }
    // Refresh
    header("Location: session_view.php?id=$session_id");
    exit;
}

// 3. Locking Variable
$is_locked = ($session['status'] === 'completed' && !has_role('admin'));

// 4. Fetch component status
$stmt = $db->prepare("SELECT type, id FROM medical_history WHERE session_id = ?");
$stmt->execute([$session_id]);
$history_records = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id FROM treatments WHERE session_id = ?");
$stmt->execute([$session_id]);
$treatment_records = $stmt->fetchAll();

$page_title = __('medical.session.title_detail');
$current_page = 'medical';
require_once '../../templates/header.php';
?>

<!-- Quill.js CDN -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<style>
    .ql-container { border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; background: white; }
    .ql-toolbar { border-top-left-radius: 12px; border-top-right-radius: 12px; background: #f8fafc; }
    .editor-wrapper { margin-bottom: 2rem; }
    .editor-label { font-weight: 800; color: var(--primary); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.75rem; display: block; }
</style>

<div style="display: flex; gap: 2rem; align-items: flex-start;">
    <!-- Left Column: Components List -->
    <div style="flex: 1; display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="border-left: 5px solid var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0;"><?php echo __('medical.session.date_prefix'); ?> <?php echo date('d/m/Y', strtotime($session['session_date'])); ?></h3>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <a href="print_session.php?id=<?php echo $session_id; ?>" target="_blank" class="btn btn-outline btn-sm" style="border-radius: 50px;">
                        <i class="fas fa-print"></i> <?php echo __('medical.session.print'); ?>
                    </a>
                    <span class="badge <?php echo $session['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $session['status'] === 'completed' ? __('medical.session.status_completed') : __('medical.session.status_processing'); ?>
                    </span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.95rem;">
                <div><?php echo __('medical.session.patient_label'); ?> <strong><?php echo e($session['patient_name']); ?></strong></div>
                <div><?php echo __('medical.session.doctor_label'); ?> <strong><?php echo e($session['doctor_name']); ?></strong></div>
            </div>
        </div>

        <div class="card">
            <h4 style="margin: 0 0 1.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-tasks"></i> <?php echo __('medical.session.components_title'); ?>
            </h4>
            
            <style>
                .component-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 1rem;
                    background: #f8fafc;
                    border-radius: 12px;
                    border: 1px solid #e2e8f0;
                    margin-bottom: 0.75rem;
                    transition: all 0.2s;
                }
                .component-item:hover { border-color: var(--primary); transform: translateX(5px); }
                .component-status { font-weight: 700; font-size: 0.85rem; }
                .status-pending { color: #64748b; }
                .status-done { color: #10b981; }
            </style>

            <?php
                $components = [
                    'chiro_exam'    => ['label' => __('medical.type.chiro_exam_full'), 'url' => 'chiro_exam.php', 'icon' => 'fa-stethoscope'],
                    'chiro_history' => ['label' => __('medical.type.chiro_history_full'), 'url' => 'chiro_history.php', 'icon' => 'fa-hospital-user'],
                    'chiropractic'  => ['label' => __('medical.type.chiropractic_full'), 'url' => 'follow_up.php', 'icon' => 'fa-notes-medical'],
                    'dong_y'        => ['label' => __('medical.type.dong_y_full'), 'url' => 'form.php?type=dong_y', 'icon' => 'fa-leaf'],
                    'treatment'     => ['label' => __('medical.type.treatment_full'), 'url' => 'add_treatment.php', 'icon' => 'fa-file-signature']
                ];

            foreach ($components as $type => $info):
                $is_done = ($type === 'treatment') ? !empty($treatment_records) : isset($history_records[$type]);
                
                // Base parameters
                $params = [
                    'patient_id' => $session['patient_id'],
                    'session_id' => $session_id
                ];
                
                // Add ID if already exists (for editing)
                if ($is_done && $type !== 'treatment') {
                    $params['id'] = $history_records[$type]['id'];
                }
                
                // Construct URL
                $url_parts = parse_url($info['url']);
                $query = [];
                if (isset($url_parts['query'])) {
                    parse_str($url_parts['query'], $query);
                }
                $final_query = array_merge($query, $params);
                $edit_url = $url_parts['path'] . '?' . http_build_query($final_query);
            ?>
                <div class="component-item">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--primary); border: 1px solid #e2e8f0;">
                            <i class="fas <?php echo $info['icon']; ?>"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #1e293b;"><?php echo $info['label']; ?></div>
                            <div class="component-status <?php echo $is_done ? 'status-done' : 'status-pending'; ?>">
                                <i class="fas <?php echo $is_done ? 'fa-check-circle' : 'fa-hourglass-half'; ?>"></i>
                                <?php echo $is_done ? __('medical.session.status_done') : __('medical.session.status_pending'); ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $edit_url; ?>" class="btn btn-sm <?php echo $is_done ? 'btn-outline' : 'btn-primary'; ?>" style="border-radius: 50px;">
                        <?php echo $is_done ? __('medical.session.btn_edit') : __('medical.session.btn_start'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- IMAGE UPLOAD SECTION -->
        <div class="card" style="margin-top: 0;">
            <h4 style="margin: 0 0 1rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-images" style="color: #7c3aed;"></i> <?php echo __('common.attachments'); ?>
            </h4>

            <?php
            // Load existing attachments from all records in this session
            $stmt_att = $db->prepare("SELECT id, attachments FROM medical_history WHERE session_id = ? AND attachments IS NOT NULL AND attachments != '[]'");
            $stmt_att->execute([$session_id]);
            $all_attachments = [];
            while ($row = $stmt_att->fetch()) {
                $atts = json_decode($row['attachments'], true);
                if ($atts) $all_attachments = array_merge($all_attachments, $atts);
            }
            ?>

            <?php if (!empty($all_attachments)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
                <?php foreach ($all_attachments as $att): ?>
                <div style="position: relative; border-radius: 12px; overflow: hidden; border: 2px solid #e2e8f0; cursor: pointer; aspect-ratio: 1;" onclick="openLightbox('<?php echo $att['path']; ?>')">
                    <?php if (strpos(isset($att['type']) ? $att['type'] : '', 'image') !== false): ?>
                    <img src="<?php echo $att['path']; ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo e($att['name']); ?>">
                    <?php else: ?>
                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f1f5f9;">
                        <i class="fas fa-file-pdf" style="font-size: 2rem; color: #ef4444;"></i>
                    </div>
                    <?php endif; ?>
                    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 0.5rem; color: white; font-size: 0.65rem; font-weight: 600;">
                        <?php echo e(mb_substr($att['name'], 0, 18)); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$is_locked): ?>
            <div id="upload-zone" style="border: 2px dashed #cbd5e1; border-radius: 16px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; background: #fafbfc;" ondragover="event.preventDefault(); this.style.borderColor='#6366f1'; this.style.background='#eef2ff'" ondragleave="this.style.borderColor='#cbd5e1'; this.style.background='#fafbfc'" ondrop="handleDrop(event)">
                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #94a3b8; margin-bottom: 0.5rem;"></i>
                <div style="font-weight: 700; color: #64748b; font-size: 0.9rem;"><?php echo __('common.upload_images'); ?></div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;"><?php echo __('medical.session.upload_hint'); ?></div>
                <input type="file" id="file-input" multiple accept="image/*,.pdf" style="display: none;" onchange="uploadFiles(this.files)">
            </div>
            <div id="upload-progress" style="display: none; margin-top: 1rem;">
                <div style="background: #e2e8f0; border-radius: 8px; overflow: hidden; height: 6px;">
                    <div id="progress-bar" style="height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); width: 0%; transition: width 0.3s;"></div>
                </div>
                <div id="upload-status" style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; text-align: center;"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Assessment & Plan (Rich-Text) -->
    <div style="width: 500px; display: flex; flex-direction: column; gap: 1.5rem;">
        <form method="POST" id="session-form" class="card" style="position: sticky; top: 1.5rem; background: #fcfdfe;">
            <?php if ($session['status'] === 'completed'): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-lock"></i>
                    <div style="font-size: 0.85rem; font-weight: 700;">
                        <?php echo __('medical.session.locked_title'); ?>
                        <?php if ($is_locked): ?>
                            <br><span style="font-weight: 500; font-size: 0.75rem;"><?php echo __('medical.session.locked_desc'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #eef2f6; padding-bottom: 1rem;">
                <h4 style="margin: 0; color: var(--primary); font-weight: 800;">
                    <i class="fas fa-user-md"></i> <?php echo __('medical.session.clinical_summary'); ?>
                </h4>
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700;"><?php echo __('medical.session.dr_prefix'); ?> <?php echo strtoupper($session['doctor_name']); ?></div>
            </div>
            
            <div class="editor-wrapper">
                <label class="editor-label"><?php echo __('medical.session.assessment_label'); ?></label>
                <div id="assessment-editor" style="height: 200px;"><?php echo $session['assessment']; ?></div>
                <input type="hidden" name="assessment" id="assessment-input">
            </div>

            <div class="editor-wrapper">
                <label class="editor-label"><?php echo __('medical.session.plan_label'); ?></label>
                <div id="plan-editor" style="height: 200px;"><?php echo $session['treatment_plan']; ?></div>
                <input type="hidden" name="treatment_plan" id="plan-input">
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php if (!$is_locked): ?>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        <i class="fas fa-save"></i> <?php echo $session['status'] === 'completed' ? __('medical.session.btn_update_admin') : __('medical.session.btn_save_notes'); ?>
                    </button>
                    <?php if ($session['status'] !== 'completed'): ?>
                        <button type="submit" name="complete" value="1" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: white;">
                            <i class="fas fa-check-double"></i> <?php echo __('medical.session.btn_complete'); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($session['status'] === 'completed' && has_role('admin')): ?>
                    <button type="submit" name="reopen" value="1" class="btn btn-outline" style="width: 100%; justify-content: center; border-color: #f59e0b; color: #b45309;">
                        <i class="fas fa-unlock"></i> <?php echo __('medical.session.btn_reopen'); ?>
                    </button>
                <?php endif; ?>

                <a href="../patients/view.php?id=<?php echo $session['patient_id']; ?>" class="btn" style="width: 100%; justify-content: center; background: #f1f5f9; color: var(--text-main);">
                    <?php echo __('medical.session.btn_back_patient'); ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var quillOptions = {
        theme: 'snow',
        readOnly: <?php echo $is_locked ? 'true' : 'false'; ?>,
        modules: {
            toolbar: <?php echo $is_locked ? 'false' : "[['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']]" ; ?>
        }
    };

    var assessmentEditor = new Quill('#assessment-editor', quillOptions);
    var planEditor = new Quill('#plan-editor', quillOptions);

    var form = document.getElementById('session-form');
    if (form) {
        form.onsubmit = function() {
            var assessmentInput = document.getElementById('assessment-input');
            if (assessmentInput) assessmentInput.value = assessmentEditor.root.innerHTML;

            var planInput = document.getElementById('plan-input');
            if (planInput) planInput.value = planEditor.root.innerHTML;
        };
    }
});

// Upload Functions
var uploadZone = document.getElementById('upload-zone');
if (uploadZone) {
    uploadZone.addEventListener('click', function() {
        document.getElementById('file-input').click();
    });
}

function handleDrop(e) {
    e.preventDefault();
    var zone = document.getElementById('upload-zone');
    zone.style.borderColor = '#cbd5e1';
    zone.style.background = '#fafbfc';
    uploadFiles(e.dataTransfer.files);
}

function uploadFiles(files) {
    if (!files.length) return;
    var fd = new FormData();
    fd.append('patient_id', '<?php echo $session['patient_id']; ?>');
    
    for (var i = 0; i < files.length; i++) {
        fd.append('images[]', files[i]);
    }
    
    var progress = document.getElementById('upload-progress');
    var bar = document.getElementById('progress-bar');
    var status = document.getElementById('upload-status');
    progress.style.display = 'block';
    bar.style.width = '30%';
    status.textContent = '<?php echo __('medical.session.uploading'); ?> ' + files.length + '...';
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/includes/upload_handler.php');
    
    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            bar.style.width = Math.round(e.loaded / e.total * 90) + '%';
        }
    };
    
    xhr.onload = function() {
        bar.style.width = '100%';
        try {
            var res = JSON.parse(xhr.responseText);
            if (res.success) {
                status.textContent = '✅ <?php echo __('medical.session.upload_success'); ?> ' + res.files.length + '!';
                setTimeout(function() { location.reload(); }, 1000);
            } else {
                status.textContent = '❌ ' + (res.error || '<?php echo __('medical.session.err_unknown'); ?>');
            }
        } catch(e) {
            status.textContent = '❌ <?php echo __('medical.session.err_server'); ?>';
        }
    };
    
    xhr.onerror = function() {
        status.textContent = '❌ <?php echo __('medical.session.err_conn'); ?>';
    };
    
    xhr.send(fd);
}

// Lightbox
function openLightbox(src) {
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:pointer;backdrop-filter:blur(5px)';
    overlay.onclick = function() { document.body.removeChild(overlay); };
    
    if (src.toLowerCase().endsWith('.pdf')) {
        var iframe = document.createElement('iframe');
        iframe.src = src;
        iframe.style.cssText = 'width:80%;height:90%;border-radius:12px;border:none';
        overlay.appendChild(iframe);
    } else {
        var img = document.createElement('img');
        img.src = src;
        img.style.cssText = 'max-width:90%;max-height:90%;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5)';
        overlay.appendChild(img);
    }
    
    var closeBtn = document.createElement('div');
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.style.cssText = 'position:absolute;top:1.5rem;right:1.5rem;width:40px;height:40px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;cursor:pointer';
    overlay.appendChild(closeBtn);
    
    document.body.appendChild(overlay);
}
</script>

<?php require_once '../../templates/footer.php'; ?>
