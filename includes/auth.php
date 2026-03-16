<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) session_start();

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name, r.display_name as role_display 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.username = ? AND u.status = 'active'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['role_display'] = $user['role_display'];
        $_SESSION['branch_id'] = $user['branch_id'];
        return true;
    }
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function logout() {
    session_destroy();
}
