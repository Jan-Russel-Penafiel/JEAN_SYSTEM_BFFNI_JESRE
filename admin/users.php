<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'User Management';
$activePage = 'users';
$current = current_user();
$allowedRoles = app_roles();
$roleLabels = [
    'ADMIN' => 'Admin',
    'CASHIER' => 'Cashier',
    'INVENTORY' => 'Inventory',
    'PURCHASING' => 'Purchasing',
    'RECEIVING' => 'Receiving',
    'STORAGE' => 'Storage',
    'ACCOUNTING' => 'Accounting',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $selectedRole = strtoupper(trim((string)($_POST['role'] ?? 'CASHIER')));
        $role = in_array($selectedRole, $allowedRoles, true) ? $selectedRole : 'CASHIER';

        if ($name === '' || !preg_match('/^[A-Za-z0-9_]{3,30}$/', $username) || strlen($password) < 6) {
            flash_set('error', 'Please provide valid name, username, and password (min 6 characters).');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (name, username, password, role) VALUES (:name, :username, :password, :role)');
                $stmt->execute([
                    'name' => $name,
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ]);
                flash_set('success', 'User created successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Failed to create user. Username may already exist.');
            }
        }
        header('Location: ' . app_url('admin/users.php'));
        exit;
    }

    if ($action === 'update_user') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $selectedRole = strtoupper(trim((string)($_POST['role'] ?? 'CASHIER')));
        $role = in_array($selectedRole, $allowedRoles, true) ? $selectedRole : 'CASHIER';

        if ($id <= 0 || $name === '' || !preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            flash_set('error', 'Invalid update request.');
        } else {
            try {
                $password = trim($_POST['password'] ?? '');
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE users SET name = :name, username = :username, role = :role, password = :password WHERE id = :id');
                    $stmt->execute([
                        'id' => $id,
                        'name' => $name,
                        'username' => $username,
                        'role' => $role,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name = :name, username = :username, role = :role WHERE id = :id');
                    $stmt->execute([
                        'id' => $id,
                        'name' => $name,
                        'username' => $username,
                        'role' => $role,
                    ]);
                }
                flash_set('success', 'User updated successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Failed to update user. Username may already exist.');
            }
        }
        header('Location: ' . app_url('admin/users.php'));
        exit;
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$current['id']) {
            flash_set('error', 'You cannot delete your own account while logged in.');
        } elseif ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $id]);
                flash_set('success', 'User deleted successfully.');
            } catch (PDOException $e) {
                flash_set('error', 'Cannot delete user linked to transactions.');
            }
        }
        header('Location: ' . app_url('admin/users.php'));
        exit;
    }
}

$users = $pdo->query('SELECT id, name, username, role, created_at FROM users ORDER BY id DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Users</h2>
            <p class="text-sm text-slate-500">Manage admin and department user accounts.</p>
        </div>
        <button data-modal-open="create-user-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create User</button>
    </div>

    <div class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-3 pr-3">Name</th>
                <th class="py-3 pr-3">Username</th>
                <th class="py-3 pr-3">Role</th>
                <th class="py-3 pr-3">Created</th>
                <th class="py-3">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td class="py-4 text-slate-500" colspan="5">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-3 pr-3 font-medium text-slate-700"><?= e($u['name']); ?></td>
                        <td class="py-3 pr-3"><?= e($u['username']); ?></td>
                        <td class="py-3 pr-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold <?= $u['role'] === 'ADMIN' ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-700'; ?>">
                                <?= e($u['role']); ?>
                            </span>
                        </td>
                        <td class="py-3 pr-3"><?= e(date('Y-m-d', strtotime($u['created_at']))); ?></td>
                        <td class="py-3">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-user-<?= (int)$u['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200">View</button>
                                <button data-modal-open="edit-user-<?= (int)$u['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-200">Edit</button>
                                <button data-modal-open="delete-user-<?= (int)$u['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-200">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="create-user-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-lg rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Create User</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="create_user">
            <div>
                <label class="text-sm text-slate-600">Name</label>
                <input name="name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
            </div>
            <div>
                <label class="text-sm text-slate-600">Username</label>
                <input type="text" name="username" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
            </div>
            <div>
                <label class="text-sm text-slate-600">Role</label>
                <select name="role" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <?php foreach ($allowedRoles as $allowedRole): ?>
                        <option value="<?= e($allowedRole); ?>" <?= $allowedRole === 'CASHIER' ? 'selected' : ''; ?>><?= e($roleLabels[$allowedRole] ?? $allowedRole); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Password</label>
                <input type="password" name="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" minlength="6" required>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Create</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($users as $u): ?>
    <div id="view-user-<?= (int)$u['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View User</h3>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Name</dt><dd class="font-medium"><?= e($u['name']); ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Username</dt><dd class="font-medium"><?= e($u['username']); ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Role</dt><dd class="font-medium"><?= e($u['role']); ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Created</dt><dd class="font-medium"><?= e($u['created_at']); ?></dd></div>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-user-<?= (int)$u['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit User</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="<?= e($u['name']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Username</label>
                    <input type="text" name="username" value="<?= e($u['username']); ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Role</label>
                    <select name="role" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <?php foreach ($allowedRoles as $allowedRole): ?>
                            <option value="<?= e($allowedRole); ?>" <?= $u['role'] === $allowedRole ? 'selected' : ''; ?>><?= e($roleLabels[$allowedRole] ?? $allowedRole); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">New Password (optional)</label>
                    <input type="password" name="password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" minlength="6">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-user-<?= (int)$u['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete User</h3>
            <p class="mt-2 text-sm text-slate-600">Delete account <span class="font-semibold"><?= e($u['username']); ?></span>?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php include __DIR__ . '/../partials/footer.php'; ?>
