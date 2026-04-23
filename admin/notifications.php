<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$pageTitle = 'Department Notifications';
$activePage = 'notifications';

$totalNotifications = (int)$pdo->query('SELECT COUNT(*) FROM department_notifications')->fetchColumn();
$pendingNotifications = (int)$pdo->query("SELECT COUNT(*) FROM department_notifications WHERE status = 'PENDING'")->fetchColumn();
$readNotifications = (int)$pdo->query("SELECT COUNT(*) FROM department_notifications WHERE status = 'READ'")->fetchColumn();

$notifications = $pdo->query('SELECT * FROM department_notifications ORDER BY created_at DESC LIMIT 100')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Latest Department Notifications</h2>
            <p class="text-sm text-slate-500">Centralized notification history across receiving, purchasing, storage, and accounting workflows.</p>
        </div>
        <a href="<?= e(app_url('admin/dashboard.php')); ?>"
           class="rounded-lg border border-brand-200 bg-white px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">
            Back to Dashboard
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Total Notifications</p>
            <p class="mt-1 text-2xl font-bold text-brand-700"><?= $totalNotifications; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Pending</p>
            <p class="mt-1 text-2xl font-bold text-amber-600"><?= $pendingNotifications; ?></p>
        </div>
        <div class="rounded-xl border border-brand-100 bg-white p-4">
            <p class="text-xs text-slate-500">Read</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600"><?= $readNotifications; ?></p>
        </div>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-5 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-brand-700">Recent Notification Feed</h3>
            <p class="text-xs text-slate-500">Showing up to 100 latest records.</p>
        </div>

        <div class="mt-4 space-y-3">
            <?php if (!$notifications): ?>
                <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
                    <p class="text-sm text-slate-500">No notifications available yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $status = strtoupper((string)$notification['status']);
                    $statusClass = $status === 'PENDING'
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-emerald-100 text-emerald-700';
                    ?>
                    <article class="rounded-lg border border-slate-100 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-brand-700">To: <?= e($notification['target_department']); ?></p>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= e($statusClass); ?>">
                                <?= e($status); ?>
                            </span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700"><?= e($notification['message']); ?></p>
                        <p class="mt-2 text-xs text-slate-400"><?= e($notification['created_at']); ?></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php include __DIR__ . '/../partials/footer.php'; ?>
