<?php
// modules/medical/session_view.php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';

$db = getDB();
$session_id = $_GET['id'] ?? 0;

if (!$session_id) {
    set_flash('Thiếu mã buổi khám.', 'error');
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
    set_flash('Không tìm thấy buổi khám.', 'error');
    redirect('../patients/index.php');
}

// 2. Handle POST (Assessment & Plan)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessment = $_POST['assessment'] ?? '';
    $plan = $_POST['treatment_plan'] ?? '';
    $status = isset($_POST['complete']) ? 'completed' : $session['status'];

    $stmt = $db->prepare("
        UPDATE medical_sessions 
        SET assessment = ?, treatment_plan = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([$assessment, $plan, $status, $session_id]);
    
    set_flash('Cập nhật buổi khám thành công!');
    if ($status === 'completed') {
        redirect("../patients/view.php?id=" . $session['patient_id']);
    }
    // Refresh
    header("Location: session_view.php?id=$session_id");
    exit;
}

// 3. Fetch component status
$stmt = $db->prepare("SELECT type, id FROM medical_history WHERE session_id = ?");
$stmt->execute([$session_id]);
$history_records = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT id FROM treatments WHERE session_id = ?");
$stmt->execute([$session_id]);
$treatment_records = $stmt->fetchAll();

$page_title = 'Chi tiết Buổi khám';
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
                <h3 style="margin: 0;">Buổi khám ngày <?php echo date('d/m/Y', strtotime($session['session_date'])); ?></h3>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <a href="print_session.php?id=<?php echo $session_id; ?>" target="_blank" class="btn btn-outline btn-sm" style="border-radius: 50px;">
                        <i class="fas fa-print"></i> In buổi khám
                    </a>
                    <span class="badge <?php echo $session['status'] === 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $session['status'] === 'completed' ? 'Đã hoàn tất' : 'Đang xử lý'; ?>
                    </span>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.95rem;">
                <div>Bệnh nhân: <strong><?php echo e($session['patient_name']); ?></strong></div>
                <div>Bác sĩ: <strong><?php echo e($session['doctor_name']); ?></strong></div>
            </div>
        </div>

        <div class="card">
            <h4 style="margin: 0 0 1.5rem 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.75rem;">
                <i class="fas fa-tasks"></i> Các thành phần buổi khám
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
                'chiro_history' => ['label' => 'Tiền sử Chiropractic', 'url' => 'chiro_history.php', 'icon' => 'fa-history'],
                'chiro_exam'    => ['label' => 'Khám Thực Thể (Chiro)', 'url' => 'chiro_exam.php', 'icon' => 'fa-stethoscope'],
                'chiropractic'  => ['label' => 'Theo dõi SOAP', 'url' => 'form.php?type=chiropractic', 'icon' => 'fa-notes-medical'],
                'dong_y'        => ['label' => 'Khám Đông Y', 'url' => 'form.php?type=dong_y', 'icon' => 'fa-leaf'],
                'treatment'     => ['label' => 'Điều trị KTV', 'url' => 'add_treatment.php', 'icon' => 'fa-hand-holding-medical']
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
                                <?php echo $is_done ? 'Đã hoàn thành' : 'Chưa nhập'; ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo $edit_url; ?>" class="btn btn-sm <?php echo $is_done ? 'btn-outline' : 'btn-primary'; ?>" style="border-radius: 50px;">
                        <?php echo $is_done ? 'Xem/Sửa' : 'Bắt đầu'; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right Column: Assessment & Plan (Rich-Text) -->
    <div style="width: 500px; display: flex; flex-direction: column; gap: 1.5rem;">
        <form method="POST" id="session-form" class="card" style="position: sticky; top: 1.5rem; background: #fcfdfe;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 2px solid #eef2f6; padding-bottom: 1rem;">
                <h4 style="margin: 0; color: var(--primary); font-weight: 800;">
                    <i class="fas fa-user-md"></i> TỔNG KẾT LÂM SÀNG
                </h4>
                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700;">DR. <?php echo strtoupper($session['doctor_name']); ?></div>
            </div>
            
            <div class="editor-wrapper">
                <label class="editor-label">1. Đánh giá chung (Assessment)</label>
                <div id="assessment-editor" style="height: 200px;"><?php echo $session['assessment']; ?></div>
                <input type="hidden" name="assessment" id="assessment-input">
            </div>

            <div class="editor-wrapper">
                <label class="editor-label">2. Kế hoạch điều trị (Plan)</label>
                <div id="plan-editor" style="height: 200px;"><?php echo $session['treatment_plan']; ?></div>
                <input type="hidden" name="treatment_plan" id="plan-input">
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-save"></i> Lưu ghi chú
                </button>
                <button type="submit" name="complete" value="1" class="btn" style="width: 100%; justify-content: center; background: #10b981; color: white;">
                    <i class="fas fa-check-double"></i> Hoàn tất Buổi khám
                </button>
                <a href="../patients/view.php?id=<?php echo $session['patient_id']; ?>" class="btn" style="width: 100%; justify-content: center; background: #f1f5f9; color: var(--text-main);">
                    Quay lại Bệnh nhân
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var quillOptions = {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['clean']
            ]
        }
    };

    var assessmentEditor = new Quill('#assessment-editor', quillOptions);
    var planEditor = new Quill('#plan-editor', quillOptions);

    var form = document.getElementById('session-form');
    form.onsubmit = function() {
        // Populate hidden inputs on submit
        var assessmentInput = document.getElementById('assessment-input');
        assessmentInput.value = assessmentEditor.root.innerHTML;

        var planInput = document.getElementById('plan-input');
        planInput.value = planEditor.root.innerHTML;
    };
});
</script>

<?php require_once '../../templates/footer.php'; ?>
