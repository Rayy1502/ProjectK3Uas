<?php
declare(strict_types=1);
require_once __DIR__ . '/helpers/Session.php';
require_once __DIR__ . '/models/ProcurementModel.php';

Session::start();
Session::requireRole('admin', 'manager', 'staff');

$model   = new ProcurementModel();
$vendors = $model->getVendors();
$divs    = $model->getDivisions();
$role    = Session::getUserRole();
$divId   = Session::getDivisionId();

$filterDiv = ($role === 'admin')
    ? (filter_input(INPUT_GET, 'div', FILTER_VALIDATE_INT) ?: null)
    : $divId;
$filterStatus = trim($_GET['status'] ?? '');
$orders       = $model->getAllOrders($filterDiv, $filterStatus ?: null);

$flash = Session::getFlash();

$currentDiv = null;
foreach ($divs as $d) {
    if ($d['id'] == $divId) { $currentDiv = $d; break; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Procurement - Pengajuan Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --bs-body-bg: #0f172a; --accent: #6366f1; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; font-family: 'Segoe UI', system-ui, sans-serif; }
        .glass-card { background: rgba(30,41,59,.85); backdrop-filter: blur(12px); border: 1px solid rgba(99,102,241,.2); border-radius: 1rem; }
        .badge-pending { background: #f59e0b; } .badge-approved { background: #10b981; }
        .badge-ordered { background: #3b82f6; } .badge-received { background: #8b5cf6; }
        .badge-rejected { background: #ef4444; }
        .btn-accent { background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; color: #fff; }
        .btn-accent:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; transform: translateY(-1px); }
        .table-dark-custom th { background: rgba(99,102,241,.15); color: #a5b4fc; border-color: rgba(99,102,241,.2); }
        .table-dark-custom td { border-color: rgba(148,163,184,.1); color: #cbd5e1; }
        .item-row { transition: all 0.3s ease; }
        .stat-card { transition: transform .2s; } .stat-card:hover { transform: translateY(-3px); }
        .navbar-brand-glow { text-shadow: 0 0 20px rgba(99,102,241,.5); }
    </style>
</head>
<body class="text-light">

<nav class="navbar navbar-dark border-bottom border-secondary mb-4" style="background:rgba(15,23,42,.95)">
    <div class="container">
        <span class="navbar-brand fw-bold navbar-brand-glow">
            <i class="bi bi-box-seam-fill me-2" style="color:var(--accent)"></i>E-Procurement System
        </span>
        <div class="d-flex align-items-center gap-3">
            <?php if ($role === 'admin' || $role === 'staff'): ?>
            <form method="POST" action="proses_procurement.php" class="d-inline-flex align-items-center gap-2">
                <input type="hidden" name="action" value="change_division">
                <?= Session::csrfField() ?>
                <label class="text-secondary small mb-0 text-nowrap"><i class="bi bi-building me-1"></i>Divisi:</label>
                <select name="new_division_id" class="form-select form-select-sm bg-dark text-light border-secondary" style="width:auto;min-width:160px" onchange="this.form.submit()">
                    <?php foreach ($divs as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $d['id'] == $divId ? 'selected' : '' ?>><?= htmlspecialchars($d['division_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <span class="badge bg-secondary"><i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
            <span class="badge" style="background:var(--accent)"><?= ucfirst($role) ?></span>
            <a href="login.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<div class="container pb-5">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($currentDiv): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="glass-card p-3 stat-card">
                <div class="text-secondary small"><i class="bi bi-building me-1"></i>Divisi</div>
                <div class="fs-5 fw-bold" style="color:#a5b4fc"><?= htmlspecialchars($currentDiv['division_name']) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card p-3 stat-card">
                <div class="text-secondary small"><i class="bi bi-wallet2 me-1"></i>Anggaran Tahunan</div>
                <div class="fs-5 fw-bold text-success">Rp <?= number_format((float)$currentDiv['budget_annual'], 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card p-3 stat-card">
                <div class="text-secondary small"><i class="bi bi-graph-down me-1"></i>Sisa Anggaran</div>
                <div class="fs-5 fw-bold <?= (float)$currentDiv['budget_remaining'] < 50000000 ? 'text-warning' : 'text-info' ?>">
                    Rp <?= number_format((float)$currentDiv['budget_remaining'], 0, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($role === 'staff'): ?>
    <div class="glass-card p-4 mb-4">
        <h5 class="mb-3"><i class="bi bi-plus-circle-fill me-2" style="color:var(--accent)"></i>Buat Pengajuan Procurement Baru</h5>
        <form action="proses_procurement.php" method="POST" id="formOrder">
            <input type="hidden" name="action" value="create">
            <?= Session::csrfField() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label text-secondary">Vendor <span class="text-danger">*</span></label>
                    <select name="vendor_id" class="form-select bg-dark text-light border-secondary" required>
                        <option value="">-- Pilih Vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary">Tanggal Order <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control bg-dark text-light border-secondary" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary">Estimasi Diterima</label>
                    <input type="date" name="expected_date" class="form-control bg-dark text-light border-secondary">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-accent w-100" onclick="addItemRow()">
                        <i class="bi bi-plus-lg me-1"></i>Item
                    </button>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-dark-custom table-sm align-middle" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:5%">#</th>
                            <th style="width:25%">Nama Barang <span class="text-danger">*</span></th>
                            <th style="width:20%">Spesifikasi</th>
                            <th style="width:10%">Satuan</th>
                            <th style="width:10%">Qty <span class="text-danger">*</span></th>
                            <th style="width:15%">Harga Satuan <span class="text-danger">*</span></th>
                            <th style="width:12%">Subtotal</th>
                            <th style="width:3%"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr class="item-row" data-row="0">
                            <td class="text-center row-num">1</td>
                            <td><input type="text" name="item_name[]" class="form-control form-control-sm bg-dark text-light border-secondary" required></td>
                            <td><input type="text" name="specification[]" class="form-control form-control-sm bg-dark text-light border-secondary"></td>
                            <td>
                                <select name="unit[]" class="form-select form-select-sm bg-dark text-light border-secondary">
                                    <option>pcs</option><option>unit</option><option>kg</option>
                                    <option>liter</option><option>box</option><option>rim</option><option>set</option>
                                </select>
                            </td>
                            <td><input type="number" name="quantity[]" class="form-control form-control-sm bg-dark text-light border-secondary qty-input" min="1" value="1" required></td>
                            <td><input type="number" name="unit_price[]" class="form-control form-control-sm bg-dark text-light border-secondary price-input" min="0" step="100" required></td>
                            <td class="subtotal-cell text-end fw-bold" style="color:#a5b4fc">Rp 0</td>
                            <td></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold" style="color:#a5b4fc">TOTAL</td>
                            <td class="text-end fw-bold fs-6" style="color:#10b981" id="grandTotal">Rp 0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mb-3">
                <label class="form-label text-secondary">Catatan</label>
                <textarea name="notes" class="form-control bg-dark text-light border-secondary" rows="2" placeholder="Catatan tambahan (opsional)..."></textarea>
            </div>

            <button type="submit" class="btn btn-accent btn-lg">
                <i class="bi bi-send-fill me-2"></i>Ajukan Procurement Order
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="glass-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-list-columns-reverse me-2" style="color:var(--accent)"></i>Daftar Procurement Orders</h5>
            <div class="d-flex gap-2">
                <?php foreach (['Pending','Approved by Manager','Ordered to Vendor','Received','Rejected'] as $s): ?>
                <a href="?status=<?= urlencode($s) ?>" class="badge text-decoration-none <?php
                    echo match($s) { 'Pending'=>'badge-pending','Approved by Manager'=>'badge-approved',
                        'Ordered to Vendor'=>'badge-ordered','Received'=>'badge-received','Rejected'=>'badge-rejected' };
                ?>"><?= $s ?></a>
                <?php endforeach; ?>
                <a href="view_create_order.php" class="badge bg-secondary text-decoration-none">All</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-dark-custom table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>No. PO</th>
                        <th>Divisi</th>
                        <th>Vendor</th>
                        <th>Tanggal</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th>Pengaju</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Belum ada data order.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($orders as $o): 
                        $badgeClass = match($o['status']) {
                            'Pending' => 'badge-pending', 'Approved by Manager' => 'badge-approved',
                            'Ordered to Vendor' => 'badge-ordered', 'Received' => 'badge-received',
                            'Rejected' => 'badge-rejected', default => 'bg-secondary'
                        };
                        $isLocked = !in_array($o['status'], ['Pending']);
                    ?>
                    <tr>
                        <td class="fw-bold" style="color:#a5b4fc"><?= htmlspecialchars($o['po_number']) ?></td>
                        <td><?= htmlspecialchars($o['division_name']) ?></td>
                        <td><?= htmlspecialchars($o['vendor_name']) ?></td>
                        <td><?= date('d/m/Y', strtotime($o['order_date'])) ?></td>
                        <td class="text-end">Rp <?= number_format((float)$o['total_amount'], 0, ',', '.') ?></td>
                        <td class="text-center"><span class="badge <?= $badgeClass ?>"><?= $o['status'] ?></span></td>
                        <td><?= htmlspecialchars($o['requester_name']) ?></td>
                        <td class="text-center">
                            <?php if ($role === 'manager'): ?>

                                <?php if ($o['status'] === 'Pending'): ?>
                                <form method="POST" action="proses_procurement.php" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="new_status" value="Approved by Manager">
                                    <?= Session::csrfField() ?>
                                    <button class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <form method="POST" action="proses_procurement.php" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="new_status" value="Rejected">
                                    <?= Session::csrfField() ?>
                                    <button class="btn btn-sm btn-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                </form>
                                <?php endif; ?>

                            <?php endif; ?>

                            <?php if ($role === 'admin'): ?>

                                <?php if ($o['status'] === 'Approved by Manager'): ?>
                                <form method="POST" action="proses_procurement.php" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="new_status" value="Ordered to Vendor">
                                    <?= Session::csrfField() ?>
                                    <button class="btn btn-sm btn-info" title="Order ke Vendor"><i class="bi bi-truck"></i></button>
                                </form>
                                <?php endif; ?>

                                <?php if ($o['status'] === 'Ordered to Vendor'): ?>
                                <form method="POST" action="proses_procurement.php" class="d-inline">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="new_status" value="Received">
                                    <?= Session::csrfField() ?>
                                    <button class="btn btn-sm btn-purple" style="background:#8b5cf6;border:none;color:#fff" title="Barang Diterima"><i class="bi bi-box-seam"></i></button>
                                </form>
                                <?php endif; ?>

                            <?php endif; ?>

                            <?php if (!$isLocked && in_array($role, ['admin', 'staff'])): ?>
                            <form method="POST" action="proses_procurement.php" class="d-inline" onsubmit="return confirm('Yakin hapus order ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <?= Session::csrfField() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php elseif ($isLocked): ?>
                                <i class="bi bi-lock-fill text-secondary" title="Terkunci - Status sudah diproses"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let rowIndex = 1;

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.dataset.row = rowIndex;
    row.innerHTML = `
        <td class="text-center row-num">${rowIndex + 1}</td>
        <td><input type="text" name="item_name[]" class="form-control form-control-sm bg-dark text-light border-secondary" required></td>
        <td><input type="text" name="specification[]" class="form-control form-control-sm bg-dark text-light border-secondary"></td>
        <td><select name="unit[]" class="form-select form-select-sm bg-dark text-light border-secondary">
            <option>pcs</option><option>unit</option><option>kg</option>
            <option>liter</option><option>box</option><option>rim</option><option>set</option>
        </select></td>
        <td><input type="number" name="quantity[]" class="form-control form-control-sm bg-dark text-light border-secondary qty-input" min="1" value="1" required></td>
        <td><input type="number" name="unit_price[]" class="form-control form-control-sm bg-dark text-light border-secondary price-input" min="0" step="100" required></td>
        <td class="subtotal-cell text-end fw-bold" style="color:#a5b4fc">Rp 0</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>`;
    tbody.appendChild(row);
    rowIndex++;
    bindCalc();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    recalculate();
    reindex();
}

function reindex() {
    document.querySelectorAll('#itemsBody .row-num').forEach((el, i) => el.textContent = i + 1);
}

function recalculate() {
    let grand = 0;
    document.querySelectorAll('#itemsBody .item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const price = parseFloat(row.querySelector('.price-input')?.value) || 0;
        const sub = qty * price;
        grand += sub;
        row.querySelector('.subtotal-cell').textContent = 'Rp ' + sub.toLocaleString('id-ID');
    });
    document.getElementById('grandTotal').textContent = 'Rp ' + grand.toLocaleString('id-ID');
}

function bindCalc() {
    document.querySelectorAll('.qty-input, .price-input').forEach(el => {
        el.removeEventListener('input', recalculate);
        el.addEventListener('input', recalculate);
    });
}

bindCalc();
</script>
</body>
</html>
