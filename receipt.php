<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$paymentId = (int)($_GET['payment_id'] ?? 0);
$autoPrint = ($_GET['print'] ?? '') === '1';

if ($paymentId <= 0) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

$stmt = $pdo->prepare('SELECT p.*, so.id AS order_id, so.order_no, so.cashier_id, so.total_amount, so.created_at AS order_date, soi.quantity, soi.unit_price, soi.subtotal, pr.sku, pr.product_name, u.name AS cashier_name FROM payments p JOIN sales_orders so ON so.id = p.sales_order_id JOIN sales_order_items soi ON soi.sales_order_id = so.id JOIN products pr ON pr.id = soi.product_id JOIN users u ON u.id = so.cashier_id WHERE p.id = :payment_id LIMIT 1');
$stmt->execute(['payment_id' => $paymentId]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

$role = (string)($user['role'] ?? '');
$receiptViewerRoles = ['ACCOUNTING'];

if ($role === 'CASHIER' && (int)$receipt['cashier_id'] !== (int)$user['id']) {
    header('Location: ' . app_url('unauthorized.php'));
    exit;
}

if ($role !== 'CASHIER' && !in_array($role, $receiptViewerRoles, true)) {
    header('Location: ' . app_url('unauthorized.php'));
    exit;
}

$changeAmount = (float)$receipt['amount_paid'] - (float)$receipt['total_amount'];
$backPath = $role === 'ACCOUNTING'
    ? app_url('department/accounting.php')
    : app_url('cashier/payments.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= e($receipt['receipt_no']); ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('css/tailwind.css')); ?>">
    <style>
        @media print {
            .print-hidden {
                display: none !important;
            }
            body {
                background: #ffffff;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-50 to-white text-slate-800 font-sans">
<div class="mx-auto max-w-2xl p-4 sm:p-8">
    <div class="print-hidden mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="<?= e($backPath); ?>" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a>
        <button onclick="window.print()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Print Receipt</button>
    </div>

    <div class="rounded-2xl border border-brand-100 bg-white p-6 shadow-xl shadow-brand-100/40">
        <div class="border-b border-dashed border-brand-100 pb-4">
            <h1 class="text-2xl font-bold text-brand-700">JZ Sisters Trading OPC</h1>
            <p class="mt-1 text-sm text-slate-500">Official Receipt</p>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <p class="text-slate-500">Receipt No.</p>
            <p class="text-right font-semibold text-slate-800"><?= e($receipt['receipt_no']); ?></p>

            <p class="text-slate-500">Order No.</p>
            <p class="text-right font-semibold text-slate-800"><?= e($receipt['order_no']); ?></p>

            <p class="text-slate-500">Cashier</p>
            <p class="text-right font-semibold text-slate-800"><?= e($receipt['cashier_name']); ?></p>

            <p class="text-slate-500">Payment Method</p>
            <p class="text-right font-semibold text-slate-800"><?= e($receipt['payment_method']); ?></p>

            <p class="text-slate-500">Paid At</p>
            <p class="text-right font-semibold text-slate-800"><?= e($receipt['paid_at']); ?></p>
        </div>

        <div class="mt-5 rounded-xl border border-brand-100 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-50 text-slate-600">
                <tr>
                    <th class="px-3 py-2 text-left">Item</th>
                    <th class="px-3 py-2 text-right">Qty</th>
                    <th class="px-3 py-2 text-right">Unit Price</th>
                    <th class="px-3 py-2 text-right">Subtotal</th>
                </tr>
                </thead>
                <tbody>
                <tr class="border-t border-brand-100">
                    <td class="px-3 py-3"><?= e($receipt['sku']); ?> - <?= e($receipt['product_name']); ?></td>
                    <td class="px-3 py-3 text-right"><?= (int)$receipt['quantity']; ?></td>
                    <td class="px-3 py-3 text-right"><?= e(format_currency($receipt['unit_price'])); ?></td>
                    <td class="px-3 py-3 text-right font-semibold"><?= e(format_currency($receipt['subtotal'])); ?></td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-500">Total Amount</span>
                <span class="font-semibold text-slate-800"><?= e(format_currency($receipt['total_amount'])); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Amount Paid</span>
                <span class="font-semibold text-slate-800"><?= e(format_currency($receipt['amount_paid'])); ?></span>
            </div>
            <div class="flex justify-between border-t border-dashed border-brand-100 pt-2">
                <span class="text-slate-500">Change</span>
                <span class="font-bold <?= $changeAmount >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>"><?= e(format_currency($changeAmount)); ?></span>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">Thank you for shopping with JZ Sisters Trading OPC.</p>
    </div>
</div>
<script>
    if (<?= $autoPrint ? 'true' : 'false'; ?>) {
        window.addEventListener('load', function () {
            window.print();
        });
    }
</script>
</body>
</html>
