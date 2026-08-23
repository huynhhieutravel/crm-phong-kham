<?php
// modules/hr/users.php
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth_middleware.php';
require_permission('manage_users');

$page_title = 'Quản lý Nhân sự';
$current_page = 'hr';
require_once '../../templates/header.php';

$db = getDB();
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role_id'] ?? '';
$status_filter = $_GET['status'] ?? 'active'; // Mặc định hiển thị nhân sự đang làm việc

// Đếm số lượng nhân sự theo trạng thái
$count_active = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$count_inactive = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn();
$count_all = $count_active + $count_inactive;

$sql = "SELECT u.*, r.display_name as role_name, b.name as branch_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN branches b ON u.branch_id = b.id";
$params = [];
$conditions = [];

// Filter trạng thái
if ($status_filter === 'active') {
    $conditions[] = "u.status = 'active'";
} elseif ($status_filter === 'inactive') {
    $conditions[] = "u.status = 'inactive'";
}

if ($search) {
    $safe_search = addcslashes($search, '%_');
    $conditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)";
    $params[] = "%{$safe_search}%";
    $params[] = "%{$safe_search}%";
    $params[] = "%{$safe_search}%";
}

if ($role_filter) {
    $conditions[] = "u.role_id = ?";
    $params[] = $role_filter;
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY u.status ASC, u.sort_order ASC, u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$is_admin = ($_SESSION['role'] === 'admin');
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main); font-size: 1.6rem; letter-spacing: -0.5px;">DANH SÁCH NHÂN SỰ</h2>
        <p style="color: var(--text-muted); margin: 0.3rem 0 0 0; font-size: 0.9rem;">Quản lý tài khoản, trạng thái làm việc và phân quyền nhân viên hệ thống</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="user_add.php" class="btn btn-primary shadow-sm" style="background: var(--primary); color: white; border-radius: 12px; padding: 0.75rem 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
            <i class="fas fa-user-plus"></i> Thêm Nhân viên
        </a>
    </div>
</div>

<!-- Tabs Lọc Trạng Thái Nhân Sự -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
    <a href="?status=active<?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $role_filter ? '&role_id='.urlencode($role_filter) : ''; ?>" 
       style="padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; font-size: 0.88rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; <?php echo $status_filter === 'active' ? 'background: #eff6ff; color: #2563eb; box-shadow: 0 1px 2px rgba(37,99,235,0.1);' : 'color: var(--text-muted); background: transparent;'; ?>">
        <i class="fas fa-user-check" style="font-size: 0.85rem;"></i> Đang làm việc
        <span style="background: <?php echo $status_filter === 'active' ? '#2563eb; color: white;' : '#e2e8f0; color: #475569;'; ?> padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">
            <?php echo $count_active; ?>
        </span>
    </a>
    
    <a href="?status=inactive<?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $role_filter ? '&role_id='.urlencode($role_filter) : ''; ?>" 
       style="padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; font-size: 0.88rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; <?php echo $status_filter === 'inactive' ? 'background: #fff1f2; color: #e11d48; box-shadow: 0 1px 2px rgba(225,29,72,0.1);' : 'color: var(--text-muted); background: transparent;'; ?>">
        <i class="fas fa-user-slash" style="font-size: 0.85rem;"></i> Đã nghỉ việc
        <span style="background: <?php echo $status_filter === 'inactive' ? '#e11d48; color: white;' : '#e2e8f0; color: #475569;'; ?> padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">
            <?php echo $count_inactive; ?>
        </span>
    </a>

    <a href="?status=all<?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $role_filter ? '&role_id='.urlencode($role_filter) : ''; ?>" 
       style="padding: 0.6rem 1.2rem; border-radius: 10px; font-weight: 700; font-size: 0.88rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s; <?php echo $status_filter === 'all' ? 'background: #f1f5f9; color: var(--text-main);' : 'color: var(--text-muted); background: transparent;'; ?>">
        <i class="fas fa-users" style="font-size: 0.85rem;"></i> Tất cả
        <span style="background: #cbd5e1; color: #334155; padding: 0.15rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">
            <?php echo $count_all; ?>
        </span>
    </a>
</div>

<!-- Bộ lọc tìm kiếm -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
        <input type="hidden" name="status" value="<?php echo e($status_filter); ?>">
        <div style="flex: 1; min-width: 250px; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" name="search" class="form-input" placeholder="Tìm tên, username hoặc số điện thoại..." value="<?php echo e($search); ?>" style="padding-left: 2.5rem; width: 100%; border-radius: 10px;">
        </div>
        <select name="role_id" class="form-input" style="width: 200px; border-radius: 10px;" onchange="this.form.submit()">
            <option value="">-- Tất cả vai trò --</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r['id']; ?>" <?php echo (string)$role_filter === (string)$r['id'] ? 'selected' : ''; ?>><?php echo e($r['display_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" style="border-radius: 10px;"><i class="fas fa-filter"></i> Lọc</button>
        <?php if ($search || $role_filter): ?>
            <a href="users.php?status=<?php echo e($status_filter); ?>" class="btn" style="background: #f1f5f9; color: var(--text-main); display: flex; align-items: center; text-decoration: none; border-radius: 10px;">Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<!-- Bảng nhân sự -->
<div class="card" style="padding: 0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid var(--border-color); border-radius: 16px;">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; text-align: left;">
                <th style="padding: 1.1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border-color);">Nhân viên</th>
                <th style="padding: 1.1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border-color);">Vai trò</th>
                <th style="padding: 1.1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border-color);">Chi nhánh</th>
                <th style="padding: 1.1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border-color);">Trạng thái</th>
                <th style="padding: 1.1rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border-color); text-align: center;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s;" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background='white'">
                    <td style="padding: 1.1rem 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 42px; height: 42px; background: <?php echo $u['status'] === 'active' ? '#eff6ff; color: #2563eb;' : '#f1f5f9; color: #94a3b8;'; ?> border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.15rem; flex-shrink: 0;">
                                <?php echo strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8')); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: <?php echo $u['status'] === 'active' ? 'var(--text-main)' : '#94a3b8'; ?>; font-size: 0.95rem;">
                                    <?php echo e($u['full_name']); ?>
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <span style="font-size: 0.7rem; background: #e0e7ff; color: #4338ca; padding: 0.15rem 0.45rem; border-radius: 6px; font-weight: 700; margin-left: 0.3rem;">Bạn</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 0.8rem; margin-top: 0.15rem;">
                                    <span>@<?php echo e($u['username']); ?></span>
                                    <?php if (!empty($u['phone'])): ?>
                                        <span><i class="fas fa-phone-alt" style="font-size: 0.7rem;"></i> <?php echo e($u['phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 1.1rem 1.5rem;">
                        <span class="badge" style="background: <?php 
                            echo match($u['role_id']) {
                                1 => '#fee2e2; color: #991b1b', // admin
                                7 => '#fdf2f8; color: #9d174d', // manager
                                2 => '#eff6ff; color: #1e40af', // doctor
                                6 => '#f0fdf4; color: #166534', // cskh
                                default => '#f1f5f9; color: #475569'
                            }; ?>; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.78rem;">
                            <?php echo e($u['role_name']); ?>
                        </span>
                    </td>
                    <td style="padding: 1.1rem 1.5rem;">
                        <span style="font-size: 0.88rem; font-weight: 500; color: var(--text-main);">
                            <i class="fas fa-building" style="color: #94a3b8; margin-right: 0.4rem; font-size: 0.8rem;"></i>
                            <?php echo e($u['branch_name'] ?: 'Trụ sở chính'); ?>
                        </span>
                    </td>
                    <td style="padding: 1.1rem 1.5rem;">
                        <?php if ($u['status'] === 'active'): ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.45rem; background: #ecfdf5; color: #059669; padding: 0.35rem 0.75rem; border-radius: 20px; font-weight: 700; font-size: 0.8rem; border: 1px solid #a7f3d0;">
                                <span style="width: 7px; height: 7px; background: #10b981; border-radius: 50%;"></span> Đang làm việc
                            </span>
                        <?php else: ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.45rem; background: #fef2f2; color: #e11d48; padding: 0.35rem 0.75rem; border-radius: 20px; font-weight: 700; font-size: 0.8rem; border: 1px solid #fecdd3;">
                                <span style="width: 7px; height: 7px; background: #e11d48; border-radius: 50%;"></span> Đã nghỉ việc
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1.1rem 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; justify-content: center; align-items: center;">
                            <!-- Nút Chỉnh sửa -->
                            <a href="user_edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm" title="Chỉnh sửa nhân viên" style="background: #f1f5f9; color: var(--text-muted); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'; this.style.color='var(--primary)';" onmouseout="this.style.background='#f1f5f9'; this.style.color='var(--text-muted)';">
                                <i class="fas fa-edit"></i>
                            </a>

                            <!-- Nút Xóa (Dành cho Administrator, không cho xóa chính mình) -->
                            <?php if ($is_admin && (int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form action="user_delete.php" method="POST" style="margin: 0;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhân sự «<?php echo e(addslashes($u['full_name'])); ?>» khỏi hệ thống?\n\n- Nếu nhân viên chưa có dữ liệu lịch sử: Hệ thống sẽ xóa vĩnh viễn.\n- Nếu nhân viên đã có hồ sơ y tế/hóa đơn/giao dịch: Hệ thống sẽ chuyển sang trạng thái ĐÃ NGHỈ VIỆC để bảo toàn dữ liệu sổ sách.');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm" title="Xóa nhân viên" style="background: #fee2e2; color: #ef4444; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px; border: none; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='white';" onmouseout="this.style.background='#fee2e2'; this.style.color='#ef4444';">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3.5rem 1rem; color: var(--text-muted);">
                        <i class="fas fa-users-slash" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                        <span style="font-size: 0.95rem; font-weight: 600;">
                            <?php if ($status_filter === 'active'): ?>
                                Không có nhân sự nào đang làm việc phù hợp bộ lọc.
                            <?php elseif ($status_filter === 'inactive'): ?>
                                Không có nhân sự nào đã nghỉ việc.
                            <?php else: ?>
                                Không tìm thấy nhân viên nào.
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
