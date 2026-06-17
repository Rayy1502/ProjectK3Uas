<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/helpers/Session.php';
require_once __DIR__ . '/models/ProcurementModel.php';

Session::start();
Session::requireRole('admin', 'manager', 'staff');

$model = new ProcurementModel();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
        Session::setFlash('danger', 'Token CSRF tidak valid. Silakan coba lagi.');
        header('Location: view_create_order.php');
        exit;
    }
}

switch ($action) {

    case 'create':
        Session::requireRole('staff');

        $vendorId     = filter_input(INPUT_POST, 'vendor_id', FILTER_VALIDATE_INT);
        $orderDate    = trim($_POST['order_date'] ?? '');
        $expectedDate = trim($_POST['expected_date'] ?? '');
        $notes        = trim($_POST['notes'] ?? '');
        $divisionId   = Session::getDivisionId();

        if (!$vendorId || !$orderDate) {
            Session::setFlash('danger', 'Vendor dan Tanggal Order wajib diisi.');
            header('Location: view_create_order.php');
            exit;
        }

        $itemNames  = $_POST['item_name'] ?? [];
        $itemSpecs  = $_POST['specification'] ?? [];
        $itemUnits  = $_POST['unit'] ?? [];
        $itemQtys   = $_POST['quantity'] ?? [];
        $itemPrices = $_POST['unit_price'] ?? [];

        if (empty($itemNames) || !is_array($itemNames)) {
            Session::setFlash('danger', 'Minimal satu item barang harus diisi.');
            header('Location: view_create_order.php');
            exit;
        }

        $items = [];
        $totalAmount = 0;

        for ($i = 0; $i < count($itemNames); $i++) {
            $name  = trim($itemNames[$i] ?? '');
            $qty   = (int)($itemQtys[$i] ?? 0);
            $price = (float)str_replace(['.', ','], ['', '.'], $itemPrices[$i] ?? '0');

            if ($name === '' || $qty <= 0 || $price <= 0) continue;

            $subtotal = $qty * $price;
            $totalAmount += $subtotal;

            $items[] = [
                'item_name'     => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                'specification' => htmlspecialchars(trim($itemSpecs[$i] ?? ''), ENT_QUOTES, 'UTF-8'),
                'unit'          => htmlspecialchars(trim($itemUnits[$i] ?? 'pcs'), ENT_QUOTES, 'UTF-8'),
                'quantity'      => $qty,
                'unit_price'    => $price,
            ];
        }

        if (empty($items)) {
            Session::setFlash('danger', 'Tidak ada item valid. Periksa kembali data barang.');
            header('Location: view_create_order.php');
            exit;
        }

        $budgetCheck = $model->checkBudget($divisionId, $totalAmount);
        if (!$budgetCheck['ok']) {
            Session::setFlash('danger', $budgetCheck['error']);
            header('Location: view_create_order.php');
            exit;
        }

        $header = [
            'po_number'     => $model->generatePoNumber(),
            'division_id'   => $divisionId,
            'vendor_id'     => $vendorId,
            'requested_by'  => Session::getUserId(),
            'order_date'    => $orderDate,
            'expected_date' => $expectedDate,
            'total_amount'  => $totalAmount,
            'notes'         => htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'),
        ];

        $result = $model->createOrder($header, $items);

        if ($result['ok']) {
            Session::setFlash('success', "PO #{$result['po_number']} berhasil dibuat!");
        } else {
            Session::setFlash('danger', $result['error']);
        }

        header('Location: view_create_order.php');
        exit;

    case 'update_status':
        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $newStatus = trim($_POST['new_status'] ?? '');
        $note = trim($_POST['rejection_note'] ?? '');

        if (!$orderId) {
            Session::setFlash('danger', 'ID order tidak valid.');
            header('Location: view_create_order.php');
            exit;
        }

        if ($newStatus === 'Approved by Manager') {
            Session::requireRole('manager');
            $order = $model->getOrderById($orderId);
            if (!$order || (int)$order['division_id'] !== Session::getDivisionId()) {
                Session::setFlash('danger', 'Order tidak ditemukan atau bukan dari divisi Anda.');
                header('Location: view_create_order.php');
                exit;
            }
            $ok = $model->updateStatus($orderId, 'Approved by Manager', Session::getUserId());
            Session::setFlash($ok ? 'success' : 'danger', $ok ? 'Order berhasil disetujui.' : 'Gagal menyetujui. Pastikan status masih Pending.');
        } elseif ($newStatus === 'Rejected') {
            Session::requireRole('manager');
            $order = $model->getOrderById($orderId);
            if (!$order || (int)$order['division_id'] !== Session::getDivisionId()) {
                Session::setFlash('danger', 'Order tidak ditemukan atau bukan dari divisi Anda.');
                header('Location: view_create_order.php');
                exit;
            }
            $ok = $model->updateStatus($orderId, 'Rejected', Session::getUserId(), $note);
            Session::setFlash($ok ? 'success' : 'danger', $ok ? 'Order berhasil ditolak.' : 'Gagal menolak. Pastikan status masih Pending.');
        } elseif ($newStatus === 'Ordered to Vendor') {
            Session::requireRole('admin');
            $ok = $model->updateStatus($orderId, 'Ordered to Vendor', Session::getUserId());
            Session::setFlash($ok ? 'success' : 'danger', $ok ? 'Order berhasil dikirim ke vendor.' : 'Gagal. Pastikan status sudah Approved by Manager.');
        } elseif ($newStatus === 'Received') {
            Session::requireRole('admin');
            $ok = $model->updateStatus($orderId, 'Received', Session::getUserId());
            Session::setFlash($ok ? 'success' : 'danger', $ok ? 'Barang berhasil dikonfirmasi diterima.' : 'Gagal. Pastikan status sudah Ordered to Vendor.');
        } else {
            Session::setFlash('danger', 'Status tidak valid.');
        }

        header('Location: view_create_order.php');
        exit;

    case 'delete':
        Session::requireRole('staff');

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        if (!$orderId) {
            Session::setFlash('danger', 'ID order tidak valid.');
            header('Location: view_create_order.php');
            exit;
        }

        $order = $model->getOrderById($orderId);
        if (!$order || (int)$order['requested_by'] !== Session::getUserId()) {
            Session::setFlash('danger', 'Anda hanya dapat menghapus order milik sendiri.');
            header('Location: view_create_order.php');
            exit;
        }

        $ok = $model->deleteOrder($orderId, Session::getDivisionId());
        Session::setFlash(
            $ok ? 'success' : 'danger',
            $ok ? 'Order berhasil dihapus dan anggaran dikembalikan.' : 'Gagal menghapus. Hanya order berstatus Pending yang dapat dihapus.'
        );

        header('Location: view_create_order.php');
        exit;

    case 'change_division':
        Session::requireRole('admin', 'staff');

        $newDivisionId = filter_input(INPUT_POST, 'new_division_id', FILTER_VALIDATE_INT);
        if ($newDivisionId) {
            $divCheck = $model->getDivisions();
            $validDiv = false;
            foreach ($divCheck as $d) {
                if ((int)$d['id'] === $newDivisionId) {
                    $validDiv = true;
                    break;
                }
            }
            if ($validDiv) {
                $_SESSION['division_id'] = $newDivisionId;
                Session::setFlash('success', 'Divisi berhasil diubah.');
            } else {
                Session::setFlash('danger', 'Divisi tidak ditemukan.');
            }
        }
        header('Location: view_create_order.php');
        exit;

    default:
        header('Location: view_create_order.php');
        exit;
}
