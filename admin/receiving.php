<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['RECEIVING', 'ADMIN']);

$pageTitle = 'Receiving Department';
$activePage = 'receiving';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'inspect_items') {
        $purchaseOrderId = (int)($_POST['purchase_order_id'] ?? 0);
        $inspectedQty = (int)($_POST['inspected_qty'] ?? 0);
        $itemsOk = ($_POST['items_ok'] ?? 'NO') === 'YES' ? 'YES' : 'NO';
        $notes = trim($_POST['notes'] ?? '');

        $poStmt = $pdo->prepare('SELECT po.*, p.product_name, p.sku, p.stock_qty, p.reorder_level FROM purchase_orders po JOIN products p ON p.id = po.product_id WHERE po.id = :id LIMIT 1');
        $poStmt->execute(['id' => $purchaseOrderId]);
        $purchaseOrder = $poStmt->fetch();

        if (!$purchaseOrder || $inspectedQty <= 0) {
            flash_set('error', 'Invalid inspection request.');
        } else {
            $pdo->beginTransaction();
            try {
                $insertReport = $pdo->prepare('INSERT INTO receiving_reports (purchase_order_id, inspected_qty, items_ok, notes, created_by) VALUES (:purchase_order_id, :inspected_qty, :items_ok, :notes, :created_by)');
                $insertReport->execute([
                    'purchase_order_id' => $purchaseOrderId,
                    'inspected_qty' => $inspectedQty,
                    'items_ok' => $itemsOk,
                    'notes' => $notes,
                    'created_by' => $user['id'],
                ]);

                if ($itemsOk === 'YES') {
                    $qtyBefore = (int)$purchaseOrder['stock_qty'];
                    $qtyAfter = $qtyBefore + $inspectedQty;

                    $updateProduct = $pdo->prepare('UPDATE products SET stock_qty = :stock_qty WHERE id = :id');
                    $updateProduct->execute([
                        'stock_qty' => $qtyAfter,
                        'id' => $purchaseOrder['product_id'],
                    ]);

                    $insertInventory = $pdo->prepare('INSERT INTO inventory_records (product_id, department, change_type, availability_status, item_check_status, qty_before, qty_change, qty_after, remarks, created_by) VALUES (:product_id, :department, :change_type, :availability_status, :item_check_status, :qty_before, :qty_change, :qty_after, :remarks, :created_by)');
                    $insertInventory->execute([
                        'product_id' => $purchaseOrder['product_id'],
                        'department' => 'RECEIVING',
                        'change_type' => 'PURCHASE',
                        'availability_status' => 'YES',
                        'item_check_status' => 'YES',
                        'qty_before' => $qtyBefore,
                        'qty_change' => $inspectedQty,
                        'qty_after' => $qtyAfter,
                        'remarks' => 'Receiving accepted items for PO ' . $purchaseOrder['po_number'],
                        'created_by' => $user['id'],
                    ]);

                    $store = $pdo->prepare('INSERT INTO storage_logs (product_id, quantity, from_department, action, notes, created_by) VALUES (:product_id, :quantity, :from_department, :action, :notes, :created_by)');
                    $store->execute([
                        'product_id' => $purchaseOrder['product_id'],
                        'quantity' => $inspectedQty,
                        'from_department' => 'RECEIVING',
                        'action' => 'STORE',
                        'notes' => 'Items inspected OK and sent to storage.',
                        'created_by' => $user['id'],
                    ]);

                    $updatePO = $pdo->prepare("UPDATE purchase_orders SET status = 'STORED' WHERE id = :id");
                    $updatePO->execute(['id' => $purchaseOrderId]);
                } else {
                    $updatePO = $pdo->prepare("UPDATE purchase_orders SET status = 'RETURNED' WHERE id = :id");
                    $updatePO->execute(['id' => $purchaseOrderId]);

                    $returnLog = $pdo->prepare('INSERT INTO storage_logs (product_id, quantity, from_department, action, notes, created_by) VALUES (:product_id, :quantity, :from_department, :action, :notes, :created_by)');
                    $returnLog->execute([
                        'product_id' => $purchaseOrder['product_id'],
                        'quantity' => $inspectedQty,
                        'from_department' => 'RECEIVING',
                        'action' => 'RETURN_TO_PURCHASING',
                        'notes' => 'Items not OK. Returned to purchasing.',
                        'created_by' => $user['id'],
                    ]);

                    $notify = $pdo->prepare('INSERT INTO department_notifications (target_department, message, status) VALUES (:target_department, :message, :status)');
                    $notify->execute([
                        'target_department' => 'PURCHASING',
                        'message' => 'Inspection failed for PO ' . $purchaseOrder['po_number'] . '. Returned for repurchase.',
                        'status' => 'PENDING',
                    ]);
                }

                $pdo->commit();
                flash_set('success', 'Inspection recorded successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                flash_set('error', 'Failed to record inspection.');
            }
        }

        header('Location: ' . app_url('admin/receiving.php'));
        exit;
    }

    if ($action === 'update_report') {
        $id = (int)($_POST['id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $itemsOk = ($_POST['items_ok'] ?? 'NO') === 'YES' ? 'YES' : 'NO';

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE receiving_reports SET items_ok = :items_ok, notes = :notes WHERE id = :id');
            $stmt->execute([
                'id' => $id,
                'items_ok' => $itemsOk,
                'notes' => $notes,
            ]);
            flash_set('success', 'Receiving report updated.');
        }

        header('Location: ' . app_url('admin/receiving.php'));
        exit;
    }

    if ($action === 'delete_report') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM receiving_reports WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash_set('success', 'Receiving report deleted.');
        }

        header('Location: ' . app_url('admin/receiving.php'));
        exit;
    }
}

$purchaseOrders = $pdo->query("SELECT po.*, p.sku, p.product_name FROM purchase_orders po JOIN products p ON p.id = po.product_id WHERE po.status = 'SENT_TO_RECEIVING' ORDER BY po.id DESC")->fetchAll();
$reports = $pdo->query('SELECT rr.*, po.po_number, p.sku, p.product_name, u.name AS created_by_name FROM receiving_reports rr JOIN purchase_orders po ON po.id = rr.purchase_order_id JOIN products p ON p.id = po.product_id JOIN users u ON u.id = rr.created_by ORDER BY rr.id DESC')->fetchAll();

include __DIR__ . '/../partials/header.php';
?>
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-brand-700">Receiving Department</h2>
            <p class="text-sm text-slate-500">Inspect delivered items. If YES, send to storage. If NO, return to purchasing.</p>
        </div>
        <button data-modal-open="inspect-modal" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Inspect Items</button>
    </div>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <h3 class="text-base font-semibold text-brand-700 mb-3">Purchase Orders for Receiving</h3>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">PO #</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Qty</th>
                <th class="py-2 pr-3">Status</th>
                <th class="py-2">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$purchaseOrders): ?>
                <tr><td class="py-3 text-slate-500" colspan="5">No purchase orders waiting for inspection.</td></tr>
            <?php else: ?>
                <?php foreach ($purchaseOrders as $po): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3 font-medium text-slate-700"><?= e($po['po_number']); ?></td>
                        <td class="py-2 pr-3"><?= e($po['sku']); ?> - <?= e($po['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$po['quantity']; ?></td>
                        <td class="py-2 pr-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= e(status_badge_class($po['status'])); ?>"><?= e($po['status']); ?></span></td>
                        <td class="py-2">
                            <button data-modal-open="inspect-po-<?= (int)$po['id']; ?>" class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Inspect</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="rounded-xl border border-brand-100 bg-white p-4 overflow-x-auto">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-brand-700">Receiving Reports</h3>
            <button type="button" id="print-all-reports-pdf" class="rounded-md bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-200">Print PDF</button>
        </div>
        <table class="min-w-full text-sm">
            <thead>
            <tr class="border-b border-slate-100 text-left text-slate-500">
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">PO #</th>
                <th class="py-2 pr-3">Product</th>
                <th class="py-2 pr-3">Inspected Qty</th>
                <th class="py-2 pr-3">Items OK</th>
                <th class="py-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$reports): ?>
                <tr><td class="py-3 text-slate-500" colspan="6">No receiving reports yet.</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <tr class="border-b border-slate-50">
                        <td class="py-2 pr-3"><?= e($report['created_at']); ?></td>
                        <td class="py-2 pr-3"><?= e($report['po_number']); ?></td>
                        <td class="py-2 pr-3"><?= e($report['sku']); ?> - <?= e($report['product_name']); ?></td>
                        <td class="py-2 pr-3"><?= (int)$report['inspected_qty']; ?></td>
                        <td class="py-2 pr-3"><span class="rounded-full px-2 py-1 text-xs font-semibold <?= $report['items_ok'] === 'YES' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?= e($report['items_ok']); ?></span></td>
                        <td class="py-2">
                            <div class="flex flex-wrap gap-2">
                                <button data-modal-open="view-report-<?= (int)$report['id']; ?>" class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">View</button>
                                <button data-modal-open="edit-report-<?= (int)$report['id']; ?>" class="rounded-md bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Edit</button>
                                <button data-modal-open="delete-report-<?= (int)$report['id']; ?>" class="rounded-md bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Delete</button>
                                <button
                                    type="button"
                                    data-print-report-pdf
                                    data-report-id="<?= (int)$report['id']; ?>"
                                    data-created-at="<?= e($report['created_at']); ?>"
                                    data-po-number="<?= e($report['po_number']); ?>"
                                    data-product="<?= e($report['sku']); ?> - <?= e($report['product_name']); ?>"
                                    data-inspected-qty="<?= (int)$report['inspected_qty']; ?>"
                                    data-items-ok="<?= e($report['items_ok']); ?>"
                                    data-notes="<?= e($report['notes']); ?>"
                                    data-created-by="<?= e($report['created_by_name']); ?>"
                                    class="rounded-md bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700"
                                >
                                    Print PDF
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div id="inspect-modal" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-xl rounded-xl bg-white p-6">
        <h3 class="text-lg font-semibold text-brand-700">Inspect Items</h3>
        <form method="post" class="mt-4 space-y-3">
            <input type="hidden" name="action" value="inspect_items">
            <div>
                <label class="text-sm text-slate-600">Purchase Order</label>
                <select name="purchase_order_id" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                    <option value="">Select PO</option>
                    <?php foreach ($purchaseOrders as $po): ?>
                        <option value="<?= (int)$po['id']; ?>"><?= e($po['po_number']); ?> - <?= e($po['sku']); ?> <?= e($po['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm text-slate-600">Inspected Quantity</label>
                    <input type="number" min="1" name="inspected_qty" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" required>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Items OK?</label>
                    <select name="items_ok" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="YES">YES</option>
                        <option value="NO">NO</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Record Inspection</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($purchaseOrders as $po): ?>
    <div id="inspect-po-<?= (int)$po['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-xl rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Inspect PO <?= e($po['po_number']); ?></h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="inspect_items">
                <input type="hidden" name="purchase_order_id" value="<?= (int)$po['id']; ?>">
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm text-slate-600">Inspected Quantity</label>
                        <input type="number" min="1" max="<?= (int)$po['quantity']; ?>" name="inspected_qty" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" value="<?= (int)$po['quantity']; ?>" required>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Items OK?</label>
                        <select name="items_ok" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                            <option value="YES">YES</option>
                            <option value="NO">NO</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Submit Inspection</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php foreach ($reports as $report): ?>
    <div id="view-report-<?= (int)$report['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">View Receiving Report</h3>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <dt class="text-slate-500">PO #</dt><dd class="font-medium"><?= e($report['po_number']); ?></dd>
                <dt class="text-slate-500">Product</dt><dd class="font-medium"><?= e($report['sku']); ?> - <?= e($report['product_name']); ?></dd>
                <dt class="text-slate-500">Inspected Qty</dt><dd class="font-medium"><?= (int)$report['inspected_qty']; ?></dd>
                <dt class="text-slate-500">Items OK</dt><dd class="font-medium"><?= e($report['items_ok']); ?></dd>
                <dt class="text-slate-500">Notes</dt><dd class="font-medium"><?= e($report['notes']); ?></dd>
                <dt class="text-slate-500">Created By</dt><dd class="font-medium"><?= e($report['created_by_name']); ?></dd>
            </dl>
            <div class="mt-5 flex justify-end">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Close</button>
            </div>
        </div>
    </div>

    <div id="edit-report-<?= (int)$report['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-lg rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-brand-700">Edit Receiving Report</h3>
            <form method="post" class="mt-4 space-y-3">
                <input type="hidden" name="action" value="update_report">
                <input type="hidden" name="id" value="<?= (int)$report['id']; ?>">
                <div>
                    <label class="text-sm text-slate-600">Items OK?</label>
                    <select name="items_ok" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        <option value="YES" <?= $report['items_ok'] === 'YES' ? 'selected' : ''; ?>>YES</option>
                        <option value="NO" <?= $report['items_ok'] === 'NO' ? 'selected' : ''; ?>>NO</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><?= e($report['notes']); ?></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-report-<?= (int)$report['id']; ?>" data-modal class="hidden fixed inset-0 z-30 items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-xl bg-white p-6">
            <h3 class="text-lg font-semibold text-rose-700">Delete Report</h3>
            <p class="mt-2 text-sm text-slate-600">Delete receiving report #<?= (int)$report['id']; ?>?</p>
            <form method="post" class="mt-5 flex justify-end gap-2">
                <input type="hidden" name="action" value="delete_report">
                <input type="hidden" name="id" value="<?= (int)$report['id']; ?>">
                <button type="button" data-modal-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm">Cancel</button>
                <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script src="<?= e(asset_url('vendor/jspdf/jspdf.umd.min.js')); ?>"></script>
<script>
    (function () {
        function safeValue(value) {
            return value && String(value).trim() !== '' ? String(value) : '-';
        }

        function getReportData(button) {
            return {
                reportId: safeValue(button.dataset.reportId),
                createdAt: safeValue(button.dataset.createdAt),
                poNumber: safeValue(button.dataset.poNumber),
                product: safeValue(button.dataset.product),
                inspectedQty: safeValue(button.dataset.inspectedQty),
                itemsOk: safeValue(button.dataset.itemsOk),
                notes: safeValue(button.dataset.notes),
                createdBy: safeValue(button.dataset.createdBy)
            };
        }

        function fileSafe(value) {
            return String(value)
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '') || 'report';
        }

        function renderReportPdf(button) {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                window.alert('PDF generator failed to load. Please refresh and try again.');
                return;
            }

            const jsPDF = window.jspdf.jsPDF;
            const doc = new jsPDF({ unit: 'pt', format: 'a4' });
            const pageWidth = doc.internal.pageSize.getWidth();
            const left = 40;
            const right = pageWidth - 40;
            let y = 52;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(17);
            doc.text('Receiving Report', left, y);

            y += 18;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text('JZ Sisters Trading OPC', left, y);

            y += 12;
            doc.text('Generated on: ' + new Date().toLocaleString(), left, y);

            y += 16;
            doc.setDrawColor(220, 20, 60);
            doc.line(left, y, right, y);
            y += 24;

            const report = getReportData(button);
            const fields = [
                ['Report ID', report.reportId],
                ['Date', report.createdAt],
                ['PO #', report.poNumber],
                ['Product', report.product],
                ['Inspected Qty', report.inspectedQty],
                ['Items OK', report.itemsOk],
                ['Created By', report.createdBy],
                ['Notes', report.notes]
            ];

            const labelWidth = 105;
            const valueWidth = right - left - labelWidth;

            fields.forEach(function (field) {
                const label = field[0] + ':';
                const value = field[1];

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(11);
                doc.text(label, left, y);

                doc.setFont('helvetica', 'normal');
                const wrappedValue = doc.splitTextToSize(value, valueWidth);
                doc.text(wrappedValue, left + labelWidth, y);

                y += Math.max(16, wrappedValue.length * 14);

                // Add a new page if content exceeds printable area.
                if (y > 760) {
                    doc.addPage();
                    y = 52;
                }
            });

            const filename = 'receiving-report-' + fileSafe(report.poNumber || report.reportId) + '.pdf';
            doc.save(filename);
        }

        function renderAllReportsPdf(reportButtons) {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                window.alert('PDF generator failed to load. Please refresh and try again.');
                return;
            }

            const reports = Array.from(reportButtons).map(getReportData);
            if (!reports.length) {
                window.alert('No receiving reports available to print yet.');
                return;
            }

            const jsPDF = window.jspdf.jsPDF;
            const doc = new jsPDF({ unit: 'pt', format: 'a4' });
            const pageWidth = doc.internal.pageSize.getWidth();
            const left = 40;
            const right = pageWidth - 40;
            const contentWidth = right - left;
            let y = 52;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(17);
            doc.text('Receiving Reports', left, y);

            y += 18;
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text('JZ Sisters Trading OPC', left, y);

            y += 12;
            doc.text('Generated on: ' + new Date().toLocaleString(), left, y);

            y += 12;
            doc.text('Total Reports: ' + reports.length, left, y);

            y += 16;
            doc.setDrawColor(220, 20, 60);
            doc.line(left, y, right, y);
            y += 20;

            reports.forEach(function (report, index) {
                if (y > 730) {
                    doc.addPage();
                    y = 52;
                }

                doc.setFont('helvetica', 'bold');
                doc.setFontSize(12);
                doc.text((index + 1) + '. PO ' + report.poNumber + ' (ID ' + report.reportId + ')', left, y);
                y += 16;

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);

                const lines = [
                    'Date: ' + report.createdAt,
                    'Product: ' + report.product,
                    'Inspected Qty: ' + report.inspectedQty + ' | Items OK: ' + report.itemsOk,
                    'Created By: ' + report.createdBy
                ];

                lines.forEach(function (line) {
                    doc.text(line, left, y);
                    y += 13;
                });

                const notesLines = doc.splitTextToSize('Notes: ' + report.notes, contentWidth);
                doc.text(notesLines, left, y);
                y += notesLines.length * 13;

                y += 8;
                doc.setDrawColor(226, 232, 240);
                doc.line(left, y, right, y);
                y += 16;
            });

            const filename = 'receiving-reports-' + new Date().toISOString().slice(0, 10) + '.pdf';
            doc.save(filename);
        }

        const reportButtons = document.querySelectorAll('[data-print-report-pdf]');

        reportButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                renderReportPdf(button);
            });
        });

        const printAllButton = document.getElementById('print-all-reports-pdf');
        if (printAllButton) {
            printAllButton.addEventListener('click', function () {
                renderAllReportsPdf(reportButtons);
            });
        }
    })();
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
