<!-- templates/tasks_drawer.php -->
<div id="globalTasksDrawer" class="tasks-drawer">
    <div class="tasks-drawer-header">
        <h3 style="margin: 0; font-weight: 800; font-size: 1.1rem; color: var(--text-main);"><i class="fas fa-tasks text-primary"></i> Việc cần làm của tôi</h3>
        <button onclick="toggleTasksDrawer()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem;"><i class="fas fa-times"></i></button>
    </div>
    <div class="tasks-drawer-body" id="tasksDrawerBody">
        <div style="text-align: center; padding: 2rem; color: #94a3b8;">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top: 1rem; font-size: 0.85rem;">Đang tải dữ liệu...</p>
        </div>
    </div>
</div>

<style>
.tasks-drawer {
    position: fixed;
    top: 0;
    right: -400px;
    width: 350px;
    height: 100vh;
    background: white;
    box-shadow: -5px 0 25px rgba(0,0,0,0.1);
    z-index: 9999;
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}
.tasks-drawer.open {
    right: 0;
}
.tasks-drawer-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}
.tasks-drawer-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}
.staff-task-item {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    transition: all 0.2s;
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}
.staff-task-item:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}
.staff-task-item.completed {
    opacity: 0.6;
    background: #f8fafc;
}
.staff-task-item.completed .task-title {
    text-decoration: line-through;
    color: #94a3b8;
}
.task-checkbox {
    width: 20px;
    height: 20px;
    border: 2px solid #cbd5e1;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    transition: all 0.2s;
    margin-top: 2px;
    flex-shrink: 0;
}
.staff-task-item.completed .task-checkbox {
    background: #10b981;
    border-color: #10b981;
    color: white;
}
.task-content {
    flex: 1;
}
.task-title {
    font-weight: 700;
    font-size: 0.95rem;
    color: #334155;
    margin-bottom: 0.25rem;
}
.task-desc {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}
.task-meta {
    font-size: 0.75rem;
    color: #94a3b8;
    display: flex;
    gap: 1rem;
}
.task-meta i {
    margin-right: 4px;
}
.task-due.overdue {
    color: #ef4444;
    font-weight: 600;
}
</style>

<script>
function toggleTasksDrawer() {
    const drawer = document.getElementById('globalTasksDrawer');
    if (drawer) {
        drawer.classList.toggle('open');
        if (drawer.classList.contains('open')) {
            loadStaffTasks();
        }
    }
}

async function loadStaffTasks() {
    const body = document.getElementById('tasksDrawerBody');
    try {
        const res = await fetch('<?php echo isset($base_url) ? $base_url : "/"; ?>api/staff_tasks.php');
        const data = await res.json();
        
        // Update badge
        const badge = document.getElementById('taskCountBadge');
        if (badge && data.pending_count !== undefined) {
            badge.innerText = data.pending_count;
            badge.style.display = data.pending_count > 0 ? 'block' : 'none';
        }

        if (data.tasks.length === 0) {
            body.innerHTML = `
                <div style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                    <i class="fas fa-clipboard-check fa-3x" style="margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p style="font-weight: 600;">Tuyệt vời! Bạn đã hoàn thành hết việc.</p>
                </div>
            `;
            return;
        }

        body.innerHTML = data.tasks.map(t => {
            const isCompleted = t.status === 'completed';
            let dueHtml = '';
            if (t.due_date) {
                const isOverdue = new Date(t.due_date) < new Date() && !isCompleted;
                dueHtml = `<span class="task-due ${isOverdue ? 'overdue' : ''}"><i class="fas fa-calendar-alt"></i> ${t.due_formatted}</span>`;
            }

            return `
                <div class="staff-task-item ${isCompleted ? 'completed' : ''}" data-id="${t.id}">
                    <div class="task-checkbox" onclick="toggleTaskStatus(${t.id}, '${isCompleted ? 'pending' : 'completed'}')">
                        <i class="fas fa-check" style="font-size: 12px;"></i>
                    </div>
                    <div class="task-content">
                        <div class="task-title">${t.title}</div>
                        ${t.description ? `<div class="task-desc">${t.description}</div>` : ''}
                        <div class="task-meta">
                            ${dueHtml}
                            <span title="Người giao việc"><i class="fas fa-user-edit"></i> Quản lý</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (e) {
        body.innerHTML = `<p style="color:red;">Lỗi tải dữ liệu...</p>`;
    }
}

async function toggleTaskStatus(id, newStatus) {
    try {
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('id', id);
        formData.append('status', newStatus);

        await fetch('<?php echo isset($base_url) ? $base_url : "/"; ?>api/staff_tasks.php', {
            method: 'POST',
            body: formData
        });
        loadStaffTasks(); // Reload immediately
    } catch(e) {
        alert('Lỗi cập nhật trạng thái');
    }
}

// Load background unread count on startup
document.addEventListener('DOMContentLoaded', () => {
    // Only check if logged in. We assume this footer is within a logged in session
    fetch('<?php echo isset($base_url) ? $base_url : "/"; ?>api/staff_tasks.php?count_only=1')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('taskCountBadge');
            if (badge && data.pending_count > 0) {
                badge.innerText = data.pending_count;
                badge.style.display = 'block';
            }
        }).catch(err => console.log('Tasks not active'));
});
</script>
