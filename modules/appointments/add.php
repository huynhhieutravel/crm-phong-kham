<?php
// modules/appointments/add.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_appointments');

try {
    $db = getDB();
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_val = $_POST['contact_id'] ?? '';
    list($type, $cid) = explode(':', $contact_val);
    
    $patient_id = ($type === 'patient' && !empty($cid)) ? (int)$cid : null;
    $lead_id = ($type === 'lead' && !empty($cid)) ? (int)$cid : null;
    $doctor_id = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;

    // Build dynamic INSERT to be 100% safe against missing columns on VPS
    $data = [
        'patient_id' => $patient_id,
        'lead_id' => $lead_id,
        'doctor_id' => $doctor_id,
        'appointment_date' => $_POST['appointment_date'] . ' ' . $_POST['appointment_time'],
        'notes' => $_POST['notes'] ?? ''
    ];

    // Optional columns that might be missing on an old VPS schema
    $optionals = [
        'branch_id' => $_SESSION['branch_id'] ?? 1,
        'appointment_end_time' => $_POST['appointment_end_time'] ?: null,
        'type' => $_POST['type'] ?? 'consultation',
        'reexam_rule_id' => $_GET['reexam_rule_id'] ?: null
    ];

    // Detect available columns
    $available_cols = $db->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($optionals as $col => $val) {
        if (in_array($col, $available_cols)) {
            $data[$col] = $val;
        }
    }

    $cols = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));
    
    try {
        $stmt = $db->prepare("INSERT INTO appointments ($cols) VALUES ($placeholders)");
        $stmt->execute(array_values($data));
        
        // Update lead status if applicable
        if ($lead_id) {
            $lead_cols = $db->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);
            $update_fields = [];
            $update_params = [];
            
            if (in_array('appointment_booking_time', $lead_cols)) {
                $update_fields[] = "appointment_booking_time = NOW()";
            }
            if (in_array('status', $lead_cols)) {
                $update_fields[] = "status = 'scheduled'";
            }
            
            if (!empty($update_fields)) {
                $update_sql = "UPDATE leads SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $update_params[] = $lead_id;
                $db->prepare($update_sql)->execute($update_params);
            }
        }

        set_flash(__('appointment.msg.add_success'));
        redirect('index.php');
    } catch (Exception $e) {
        die("Fatal Error during save: " . $e->getMessage());
    }
}


$page_title = __('appointment.add.title');
$current_page = 'appointments';
require_once '../../templates/header.php';

try {
    $patients = $db->query("SELECT id, full_name, phone FROM patients ORDER BY full_name ASC LIMIT 500")->fetchAll();
} catch (Exception $e) {
    $patients = [];
}

try {
    // Check if status column exists in leads, otherwise ignore the filter
    $leads = $db->query("SELECT id, full_name, phone FROM leads ORDER BY full_name ASC LIMIT 500")->fetchAll();
} catch (Exception $e) {
    $leads = [];
}

try {
    // Fetch staff - attempt with display_name, fallback to name
    $role_cols = $db->query("SHOW COLUMNS FROM roles")->fetchAll(PDO::FETCH_COLUMN);
    $role_label_col = in_array('display_name', $role_cols) ? 'display_name' : 'name';

    $doctors = $db->query("
        SELECT u.id, u.full_name, r.$role_label_col as role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE r.name IN ('doctor', 'technician', 'cskh', 'admin') AND u.status = 'active'
        ORDER BY r.name = 'doctor' DESC, u.full_name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $doctors = [];
}


$prefill_lead_id = $_GET['lead_id'] ?? null;
$prefill_patient_id = $_GET['patient_id'] ?? null;

// Add Select2 CSS
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* Flatpickr Premium Styling */
.flatpickr-calendar {
    border-radius: 16px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
}
.flatpickr-day.selected {
    background: var(--primary) !important;
    border-color: var(--primary) !important;
}
/* Select2 Premium Styling */
.select2-container {
    width: 100% !important;
    display: block;
}
.select2-container--default .select2-selection--single {
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    height: 48px;
    padding-left: 8px;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
    width: 100%;
}
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single {
    background: white;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    outline: none;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #1e293b;
    font-size: 0.95rem;
    font-weight: 500;
    line-height: normal;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 46px;
    right: 10px;
}
.select2-dropdown {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    z-index: 10001; /* Above top bar if needed */
    background: white;
}
.select2-search--dropdown .select2-search__field {
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    padding: 8px 12px;
    outline: none;
}
.select2-results__option--highlighted[aria-selected] {
    background-color: var(--primary) !important;
}
.select2-results__group {
    font-weight: 800;
    color: #64748b;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 8px 12px;
    background: #f8fafc;
}
</style>
<?php
?>

<div class="premium-card" style="max-width: 700px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, rgba(79, 70, 229, 0.05) 0%, rgba(59, 130, 246, 0.05) 100%); padding: 2rem; border-bottom: 1px solid #e2e8f0; text-align: center;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 1rem;">
            <i class="fas fa-calendar-check" style="color: var(--primary); font-size: 1.5rem;"></i>
        </div>
        <h2 style="font-weight: 900; color: #1e293b; margin: 0; letter-spacing: -0.025em; font-size: 1.75rem;"><?php echo __('appointment.add.smart_schedule'); ?></h2>
        <p style="color: #64748b; font-size: 0.95rem; margin-top: 0.5rem; font-weight: 500;"><?php echo __('appointment.add.smart_desc'); ?></p>
    </div>

    <form method="POST" id="appointmentForm" style="padding: 2.5rem;">
        <div class="form-group" style="margin-bottom: 2rem;">
            <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.75rem;"><?php echo __('appointment.add.type_label'); ?> <span style="color: #ef4444;">*</span></label>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem;">
                <label class="type-btn">
                    <input type="radio" name="type" value="consultation" checked required>
                    <div class="type-content">
                        <i class="fas fa-comments"></i>
                        <span><?php echo __('appointment.type.consultation'); ?></span>
                    </div>
                </label>
                <label class="type-btn">
                    <input type="radio" name="type" value="treatment">
                    <div class="type-content">
                        <i class="fas fa-hand-holding-medical"></i>
                        <span><?php echo __('appointment.type.treatment'); ?></span>
                    </div>
                </label>
                <label class="type-btn">
                    <input type="radio" name="type" value="re_exam">
                    <div class="type-content">
                        <i class="fas fa-redo"></i>
                        <span><?php echo __('appointment.type.re_exam'); ?></span>
                    </div>
                </label>
                <label class="type-btn">
                    <input type="radio" name="type" value="adjustment">
                    <div class="type-content">
                        <i class="fas fa-tools"></i>
                        <span><?php echo __('appointment.type.adjustment'); ?></span>
                    </div>
                </label>
            </div>
        </div>

        <div class="premium-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase;"><?php echo __('appointment.add.customer_label'); ?> <span style="color: #ef4444;">*</span></label>
                <select name="contact_id" id="contact_id" class="form-premium-input" required>
                    <option value=""><?php echo __('appointment.add.select_customer'); ?></option>
                    <optgroup label="<?php echo __('appointment.add.patients_group'); ?>">
                        <?php foreach ($patients as $p): ?>
                            <option value="patient:<?php echo $p['id']; ?>" <?php echo (int)$prefill_patient_id === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo e($p['full_name']); ?> (<?php echo e($p['phone']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="<?php echo __('appointment.add.leads_group'); ?>">
                        <?php foreach ($leads as $l): ?>
                            <option value="lead:<?php echo $l['id']; ?>" <?php echo (int)$prefill_lead_id === (int)$l['id'] ? 'selected' : ''; ?>>
                                <?php echo e($l['full_name']); ?> (<?php echo e($l['phone']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase;"><?php echo __('appointment.add.doctor_label'); ?></label>
                <select name="doctor_id" id="doctor_id" class="form-premium-input">
                    <option value=""><?php echo __('appointment.add.select_doctor'); ?></option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo e($d['full_name']); ?> (<?php echo e($d['role_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div id="doctorTimeline" style="display: none; margin-bottom: 2rem; background: white; padding: 1.25rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 800; color: #475569; text-transform: uppercase;"><?php echo __('appointment.add.daily_schedule'); ?></span>
                <div style="display: flex; gap: 0.75rem; font-size: 0.7rem; font-weight: 700;">
                    <span style="display: flex; align-items: center; gap: 0.25rem;"><i class="fas fa-circle" style="color: #22c55e; font-size: 0.5rem;"></i> <?php echo __('appointment.add.slot_free'); ?></span>
                    <span style="display: flex; align-items: center; gap: 0.25rem;"><i class="fas fa-circle" style="color: #ef4444; font-size: 0.5rem;"></i> <?php echo __('appointment.add.slot_busy'); ?></span>
                </div>
            </div>
            <div id="timelineSlots" class="timeline-row"></div>
            <div class="timeline-labels">
                <span>08:00</span>
                <span>10:00</span>
                <span>12:00</span>
                <span>14:00</span>
                <span>16:00</span>
                <span>18:00</span>
            </div>
        </div>

        <div id="doctorConflict" style="display: none; margin-bottom: 1.5rem; padding: 1rem; background: #fff1f2; color: #e11d48; border-radius: 12px; font-size: 0.85rem; border: 1px solid #fecdd3; font-weight: 600;">
            <i class="fas fa-exclamation-circle" style="margin-right: 0.25rem;"></i> <?php echo __('appointment.add.conflict_warning'); ?>
        </div>

        <div class="premium-grid" style="display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="form-group">
                <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase;"><?php echo __('appointment.add.date_label'); ?> <span style="color: #ef4444;">*</span></label>
                <input type="text" name="appointment_date" id="appointment_date" class="form-premium-input" required value="<?php echo date('Y-m-d'); ?>" placeholder="dd/mm/yyyy">
                
                <div class="quick-date-btns" style="display: flex; gap: 0.35rem; margin-top: 0.75rem;">
                    <button type="button" class="q-date-btn" onclick="addDays(7)">+7N</button>
                    <button type="button" class="q-date-btn" onclick="addDays(14)">+14N</button>
                    <button type="button" class="q-date-btn" onclick="addDays(30)">+30N</button>
                    <button type="button" class="q-date-btn" onclick="addDays(60)">+60N</button>
                    <button type="button" class="q-date-btn" onclick="addDays(90)">+90N</button>
                </div>

                <div id="loadIndicator" style="display: none; margin-top: 1rem; font-size: 0.75rem; color: #94a3b8; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 10px; border: 1px solid #e2e8f0;">
                    <span id="morningLoad" style="margin-right: 1.5rem; font-weight: 700;"></span>
                    <span id="afternoonLoad" style="font-weight: 700;"></span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase;"><?php echo __('appointment.add.time_label'); ?> <span style="color: #ef4444;">*</span></label>
                <input type="time" name="appointment_time" id="appointment_time" class="form-premium-input" required>
                <p style="margin-top: 0.5rem; font-size: 0.75rem; color: #94a3b8; font-style: italic;"><?php echo __('appointment.add.time_hint'); ?></p>
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase;"><?php echo __('appointment.add.end_time_label'); ?></label>
                <input type="time" name="appointment_end_time" id="appointment_end_time" class="form-premium-input">
                <div class="quick-time-btns" style="display: flex; gap: 0.35rem; margin-top: 0.75rem;">
                    <button type="button" class="q-time-btn" onclick="addMinutes(30)">+30p</button>
                    <button type="button" class="q-time-btn" onclick="addMinutes(60)">+60p</button>
                    <button type="button" class="q-time-btn" onclick="addMinutes(90)">+90p</button>
                </div>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 1rem;">
            <label class="form-label" style="font-weight: 800; color: #475569; font-size: 0.8rem; text-transform: uppercase;"><?php echo __('appointment.add.notes_label'); ?></label>
            <textarea name="notes" class="form-premium-input" rows="3" placeholder="<?php echo __('appointment.add.notes_placeholder'); ?>"></textarea>
        </div>
        
        <div style="margin-top: 3rem; display: flex; gap: 1rem; align-items: stretch;">
            <button type="submit" class="btn-confirm" style="flex: 2;">
                <i class="fas fa-check-circle"></i> <?php echo __('appointment.add.submit_btn'); ?>
            </button>
            <a href="index.php" class="btn-cancel" style="flex: 1;"><?php echo __('common.cancel'); ?></a>
        </div>
    </form>
</div>

<style>
:root {
    --glass-bg: rgba(255, 255, 255, 0.85);
    --glass-border: rgba(255, 255, 255, 0.5);
}

body {
    background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
    min-height: 100vh;
}

.premium-card {
    background: var(--glass-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    overflow: hidden;
    width: 95%;
    max-width: 850px;
    margin: 2rem auto;
}

@media (max-width: 640px) {
    .premium-grid {
        grid-template-columns: 1fr !important;
    }
}

.type-btn {
    position: relative;
    cursor: pointer;
    perspective: 1000px;
}
.type-btn input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.type-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.type-content i {
    font-size: 1.4rem;
    margin-bottom: 0.5rem;
    color: #94a3b8;
    transition: transform 0.3s ease;
}
.type-content span {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    color: #64748b;
}

.type-btn:hover .type-content {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.type-btn:hover i {
    transform: scale(1.1);
}

.type-btn input:checked + .type-content {
    border-color: var(--primary);
    background: linear-gradient(to bottom right, #ffffff, #eff6ff);
    box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1);
}
.type-btn input:checked + .type-content i {
    color: var(--primary);
    transform: scale(1.15);
}
.type-btn input:checked + .type-content span {
    color: var(--primary);
}

.form-premium-input {
    background: rgba(248, 250, 252, 0.8);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    width: 100%;
}
.form-premium-input:focus {
    background: white;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    outline: none;
}

.q-date-btn, .q-time-btn {
    flex: 1;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.4rem 0;
    font-size: 0.75rem;
    font-weight: 800;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.q-date-btn:hover, .q-time-btn:hover {
    background: white;
    color: var(--primary);
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.q-date-btn:active, .q-time-btn:active {
    transform: translateY(0);
}

.btn-confirm {
    background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
    color: white;
    border: none;
    padding: 1rem;
    border-radius: 14px;
    font-weight: 700;
    letter-spacing: 0.025em;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
}
.btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 20px -5px rgba(79, 70, 229, 0.4);
    opacity: 0.95;
}

.btn-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 1rem;
    border-radius: 14px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.btn-cancel:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.timeline-row {
    display: flex;
    gap: 2px;
    height: 12px;
    background: #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
    margin-top: 1rem;
}
.timeline-slot {
    flex: 1;
    background: #22c55e;
    transition: all 0.2s;
}
.timeline-slot.busy {
    background: #ef4444;
}
.timeline-slot.selected {
    background: var(--primary);
    box-shadow: 0 0 0 2px white, 0 0 0 4px var(--primary);
    position: relative;
    z-index: 10;
}
.timeline-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.65rem;
    color: #94a3b8;
    margin-top: 0.5rem;
    font-weight: 600;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('appointment_date');
    const timeInput = document.getElementById('appointment_time');
    const doctorSelect = document.getElementById('doctor_id');
    const loadIndicator = document.getElementById('loadIndicator');
    const morningLoad = document.getElementById('morningLoad');
    const afternoonLoad = document.getElementById('afternoonLoad');
    const doctorConflict = document.getElementById('doctorConflict');

    window.addDays = function(days) {
        let currentVal = dateInput.value;
        let date = currentVal ? new Date(currentVal) : new Date();
        
        // Use UTC to avoid timezone issues when adding days
        date.setDate(date.getDate() + days);
        
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        
        dateInput.value = `${yyyy}-${mm}-${dd}`;
        checkAvailability();
    };

    window.addMinutes = function(minutes) {
        const startTime = timeInput.value;
        if (!startTime) return;
        
        const [hours, mins] = startTime.split(':').map(Number);
        const date = new Date();
        date.setHours(hours);
        date.setMinutes(mins + minutes);
        
        const endHours = String(date.getHours()).padStart(2, '0');
        const endMins = String(date.getMinutes()).padStart(2, '0');
        
        document.getElementById('appointment_end_time').value = `${endHours}:${endMins}`;
    };

    function checkAvailability() {
        const date = dateInput.value;
        const time = timeInput.value;
        const doctorId = doctorSelect.value;

        if (!date) return;

        fetch(`check_availability.php?date=${date}&time=${time}&doctor_id=${doctorId}`)
            .then(response => response.json())
            .then(data => {
                // Update load indicator
                loadIndicator.style.display = 'block';
                morningLoad.textContent = `<?php echo __('appointment.add.morning'); ?>${data.morning_count}${data.morning_count > 5 ? '<?php echo __('appointment.add.crowded'); ?>' : ''}`;
                afternoonLoad.textContent = `<?php echo __('appointment.add.afternoon'); ?>${data.afternoon_count}${data.afternoon_count > 5 ? '<?php echo __('appointment.add.crowded'); ?>' : ''}`;
                
                // Color coding based on load
                morningLoad.style.color = data.morning_count > 8 ? '#dc2626' : (data.morning_count > 5 ? '#d97706' : '#64748b');
                afternoonLoad.style.color = data.afternoon_count > 8 ? '#dc2626' : (data.afternoon_count > 5 ? '#d97706' : '#64748b');

                // Check doctor busy
                if (data.doctor_busy) {
                    doctorConflict.style.display = 'block';
                } else {
                    doctorConflict.style.display = 'none';
                }

                // Render Timeline
                if (doctorId && date) {
                    renderTimeline(data.schedule, time);
                } else {
                    document.getElementById('doctorTimeline').style.display = 'none';
                }
            });
    }

    function renderTimeline(busySlots, selectedTime) {
        const timelineDiv = document.getElementById('doctorTimeline');
        const slotsContainer = document.getElementById('timelineSlots');
        timelineDiv.style.display = 'block';
        slotsContainer.innerHTML = '';

        // Working hours 08:00 - 18:00 (10 hours = 20 slots of 30 mins)
        for (let hour = 8; hour < 18; hour++) {
            for (let min of ['00', '30']) {
                const timeStr = `${String(hour).padStart(2, '0')}:${min}`;
                const slot = document.createElement('div');
                slot.className = 'timeline-slot';
                slot.title = timeStr;

                // Check if busy (overlap checking logic matches backend +/- 29 mins)
                const isBusy = busySlots.some(s => {
                    const [sH, sM] = s.time.split(':').map(Number);
                    const [cH, cM] = timeStr.split(':').map(Number);
                    const sTotal = sH * 60 + sM;
                    const cTotal = cH * 60 + cM;
                    return Math.abs(sTotal - cTotal) < 30;
                });

                if (isBusy) slot.classList.add('busy');
                if (selectedTime === timeStr) slot.classList.add('selected');

                slotsContainer.appendChild(slot);
            }
        }
    }

    dateInput.addEventListener('change', checkAvailability);
    timeInput.addEventListener('change', checkAvailability);
    doctorSelect.addEventListener('change', checkAvailability);

    // Initial check
    checkAvailability();

    // Select2 Initialization
    if (typeof jQuery !== 'undefined') {
        $(document).ready(function() {
            $('#contact_id').select2({
                placeholder: "<?php echo __('appointment.add.select_customer'); ?>",
                allowClear: true,
                width: '100%'
            });

            // Initialize Flatpickr
            flatpickr("#appointment_date", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                locale: "vn",
                disableMobile: "true",
                onChange: function(selectedDates, dateStr, instance) {
                    checkAvailability();
                }
            });
        });
    }
});
</script>

<!-- Select2 JS and Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>
<script>
// Re-initialize Select2 once scripts are loaded
$(document).ready(function() {
    $('#contact_id').select2({
        placeholder: "<?php echo __('appointment.add.select_customer'); ?>",
        allowClear: true,
        width: '100%'
    });
});
</script>

<?php require_once '../../templates/footer.php'; ?>
