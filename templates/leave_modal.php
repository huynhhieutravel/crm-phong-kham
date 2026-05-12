<?php
// templates/leave_modal.php
require_once __DIR__ . '/../includes/db.php';
$db = getDB();
$active_users = $db->query("SELECT id, full_name, role_id FROM users WHERE status = 'active' ORDER BY full_name")->fetchAll();
?>

<!-- LEAVE MODAL (GLOBAL) -->
<div id="leaveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; max-width: 500px; overflow: hidden; transform: scale(0.95); animation: modalIn 0.2s forwards;">
        <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-weight: 800; font-size: 1.25rem; color: #1e293b;"><i class="fas fa-calendar-times text-warning"></i> Đơn Xin Nghỉ Phép</h3>
            <button onclick="document.getElementById('leaveModal').style.display='none'" style="background: none; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding: 2rem;">
            <form id="leaveForm" onsubmit="submitLeaveRequest(event)">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Nhân sự nghỉ phép <span style="color: red;">*</span></label>
                    <select name="assigned_user_id" class="form-input" required>
                        <?php foreach ($active_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $_SESSION['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Loại nghỉ <span style="color: red;">*</span></label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <label style="border: 2px solid #6366f1; border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; background: #eef2ff; transition: all 0.2s;" id="lbl_full_day">
                            <input type="radio" name="leave_type" value="full_day" checked style="display: none;" onchange="toggleLeaveType(this.value)">
                            <i class="fas fa-calendar-day" style="font-size: 1.1rem; color: #6366f1; display: block; margin-bottom: 0.25rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 700; color: #4338ca;">Nghỉ cả ngày</span>
                        </label>
                        <label style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 0.75rem; cursor: pointer; text-align: center; background: white; transition: all 0.2s;" id="lbl_hourly">
                            <input type="radio" name="leave_type" value="hourly" style="display: none;" onchange="toggleLeaveType(this.value)">
                            <i class="fas fa-clock" style="font-size: 1.1rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;"></i>
                            <span style="font-size: 0.8rem; font-weight: 700; color: #64748b;">Nghỉ theo giờ</span>
                        </label>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label" id="lbl_start_date">Từ ngày <span style="color: red;">*</span></label>
                        <input type="date" name="start_date" id="leave_start" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" id="grp_end_date">
                        <label class="form-label">Đến ngày <span style="color: red;">*</span></label>
                        <input type="date" name="end_date" id="leave_end" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <!-- Time inputs (for hourly leave) -->
                <div id="hourlyTimeRow" style="display: none; margin-bottom: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-sign-in-alt" style="color: #f59e0b;"></i> Từ giờ</label>
                            <input type="time" name="start_time" id="leave_start_time" class="form-input" value="08:00">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-sign-out-alt" style="color: #ef4444;"></i> Đến giờ</label>
                            <input type="time" name="end_time" id="leave_end_time" class="form-input" value="17:00">
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem; background: #fefce8; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.8rem; color: #854d0e;">
                        <i class="fas fa-lightbulb"></i> <strong>Vô muộn:</strong> VD 10:00 → 17:00 · <strong>Về sớm:</strong> VD 08:00 → 14:00
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Lý do nghỉ (Tuỳ chọn)</label>
                    <textarea name="reason" class="form-input" rows="3" placeholder="Nhập lý do..."></textarea>
                </div>
                <div style="background: #eff6ff; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; gap: 0.75rem; align-items: flex-start;">
                    <i class="fas fa-info-circle" style="color: #3b82f6; margin-top: 3px;"></i>
                    <p style="margin: 0; font-size: 0.85rem; color: #1e40af; line-height: 1.4;">
                        Hệ thống sẽ quét các <strong>Lịch hẹn Bệnh nhân</strong> của nhân sự này trong khoảng thời gian trên và tự báo cáo cho Bàn Lễ Tân thu xếp thay đổi.
                    </p>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('leaveModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #64748b;">Huỷ</button>
                    <button type="submit" id="btnSubmitLeave" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-paper-plane"></i> Gửi Đơn & Bàn Giao</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleLeaveType(type) {
    var timeRow = document.getElementById('hourlyTimeRow');
    var endDateGrp = document.getElementById('grp_end_date');
    var lblFullDay = document.getElementById('lbl_full_day');
    var lblHourly = document.getElementById('lbl_hourly');
    
    if (type === 'hourly') {
        timeRow.style.display = 'block';
        endDateGrp.style.display = 'none';
        // Auto-copy start_date to end_date
        var startInput = document.getElementById('leave_start');
        document.getElementById('leave_end').value = startInput.value;
        startInput.addEventListener('change', function() {
            document.getElementById('leave_end').value = this.value;
        });
        lblFullDay.style.borderColor = '#e2e8f0';
        lblFullDay.style.background = 'white';
        lblFullDay.querySelector('i').style.color = '#94a3b8';
        lblFullDay.querySelector('span').style.color = '#64748b';
        lblHourly.style.borderColor = '#f59e0b';
        lblHourly.style.background = '#fefce8';
        lblHourly.querySelector('i').style.color = '#f59e0b';
        lblHourly.querySelector('span').style.color = '#92400e';
    } else {
        timeRow.style.display = 'none';
        endDateGrp.style.display = 'block';
        lblFullDay.style.borderColor = '#6366f1';
        lblFullDay.style.background = '#eef2ff';
        lblFullDay.querySelector('i').style.color = '#6366f1';
        lblFullDay.querySelector('span').style.color = '#4338ca';
        lblHourly.style.borderColor = '#e2e8f0';
        lblHourly.style.background = 'white';
        lblHourly.querySelector('i').style.color = '#94a3b8';
        lblHourly.querySelector('span').style.color = '#64748b';
    }
}

async function submitLeaveRequest(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitLeave');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    btn.disabled = true;

    const fd = new FormData(e.target);
    fd.append('_csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    try {
        const r = await fetch('<?php echo isset($base_url) ? $base_url : "/"; ?>api/leave_requests.php', { method: 'POST', body: fd });
        const res = await r.json();
        
        if (res.success) {
            alert(res.message);
            document.getElementById('leaveModal').style.display = 'none';
            e.target.reset();
            window.location.reload();
        } else {
            alert(res.message);
        }
    } catch(err) {
        alert("Lỗi hệ thống khi gửi đơn.");
    } finally {
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi Đơn & Bàn Giao';
        btn.disabled = false;
    }
}
</script>
