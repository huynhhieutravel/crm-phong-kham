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
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_id'] ?? '';

$sql = "SELECT u.*, r.display_name as role_name, b.name as branch_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        LEFT JOIN branches b ON u.branch_id = b.id";
$params = [];
$conditions = [];

if ($search) {
    $conditions[] = "(u.full_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $conditions[] = "u.role_id = ?";
    $params[] = $role_filter;
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="margin: 0; font-weight: 800; color: var(--text-main);">DANH SÁCH NHÂN VIÊN</h2>
        <p style="color: var(--text-muted); margin: 0.2rem 0 0 0;">Quản lý tài khoản và phân quyền nhân sự hệ thống</p>
    </div>
    <a href="user_add.php" class="btn btn-primary shadow-sm" style="background: var(--primary); color: white; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; text-decoration: none;">
        <i class="fas fa-user-plus"></i> Thêm Nhân viên
    </a>
</div>

<div class="card" style="margin-bottom: 2rem;">
    <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 250px; position: relative;">
            <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" name="search" class="form-input" placeholder="Tìm tên hoặc tên đăng nhập..." value="<?php echo e($search); ?>" style="padding-left: 2.5rem; width: 100%;">
        </div>
        <select name="role_id" class="form-input" style="width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả vai trò --</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r['id']; ?>" <?php echo (int)$role_filter === (int)$r['id'] ? 'selected' : ''; ?>><?php echo e($r['display_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Lọc</button>
        <?php if ($search || $role_filter): ?>
            <a href="users.php" class="btn" style="background: #f1f5f9; color: var(--text-main); display: flex; align-items: center; text-decoration: none;">Xóa lọc</a>
        <?php endif; ?>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <table class="table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; text-align: left;">
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Nhân viên</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Vai trò</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Chi nhánh</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color);">Trạng thái</th>
                <th style="padding: 1.25rem 1.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05rem; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border-color); text-align: center;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;">
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #eff6ff; color: #2563eb; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">
                                <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: var(--text-main);"><?php echo e($u['full_name']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">@<?php echo e($u['username']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <span class="badge" style="background: <?php 
                            echo match($u['role_id']) {
                                1 => '#fee2e2; color: #991b1b', // admin
                                7 => '#fdf2f8; color: #9d174d', // manager
                                2 => '#eff6ff; color: #1e40af', // doctor
                                6 => '#f0fdf4; color: #166534', // cskh
                                default => '#f1f5f9; color: #475569'
                            }; ?>; font-weight: 700; padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.75rem;">
                            <?php echo e($u['role_name']); ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <span style="font-size: 0.9rem; font-weight: 500; color: var(--text-main);">
                            <i class="fas fa-building" style="color: #94a3b8; margin-right: 0.4rem;"></i>
                            <?php echo e($u['branch_name'] ?: 'Chưa gán'); ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <?php if ($u['status'] === 'active'): ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.4rem; color: #10b981; font-weight: 700; font-size: 0.85rem;">
                                <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span> Hoạt động
                            </span>
                        <?php else: ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.4rem; color: #94a3b8; font-weight: 700; font-size: 0.85rem;">
                                <span style="width: 8px; height: 8px; background: #94a3b8; border-radius: 50%;"></span> Ngừng
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1.25rem 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                            <a href="user_edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm" title="Chỉnh sửa" style="background: #f1f5f9; color: var(--text-muted); width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px;">
                                <i class="fas fa-user-edit"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fas fa-users-slash" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                        Không tìm thấy nhân viên nào.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once '../../templates/footer.php'; ?>
