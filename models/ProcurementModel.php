<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED);

require_once __DIR__ . '/../config/Database.php';

class ProcurementModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function generatePoNumber(): string
    {
        $date = date('Ymd');
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM procurement_orders WHERE DATE(created_at) = CURDATE()"
        );
        $stmt->execute();
        $count = (int)$stmt->fetch()['cnt'] + 1;
        return sprintf('PO-%s-%04d', $date, $count);
    }

    public function checkBudget(int $divisionId, float $requiredAmount): array
    {
        $stmt = $this->db->prepare(
            "SELECT division_name, budget_annual, budget_used,
                    (budget_annual - budget_used) AS budget_remaining
             FROM divisions WHERE id = :id"
        );
        $stmt->execute([':id' => $divisionId]);
        $div = $stmt->fetch();

        if (!$div) {
            return ['ok' => false, 'error' => 'Divisi tidak ditemukan.'];
        }

        $remaining = (float)$div['budget_remaining'];
        if ($requiredAmount > $remaining) {
            return [
                'ok' => false,
                'error' => sprintf(
                    'Anggaran tidak mencukupi. Sisa: Rp %s, Dibutuhkan: Rp %s',
                    number_format($remaining, 2, ',', '.'),
                    number_format($requiredAmount, 2, ',', '.')
                ),
                'remaining' => $remaining
            ];
        }

        return ['ok' => true, 'remaining' => $remaining, 'division' => $div];
    }

    public function createOrder(array $header, array $items): array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO procurement_orders 
                 (po_number, division_id, vendor_id, requested_by, order_date, expected_date, total_amount, notes)
                 VALUES (:po, :div, :vendor, :req, :odate, :edate, :total, :notes)"
            );
            $stmt->execute([
                ':po'     => $header['po_number'],
                ':div'    => $header['division_id'],
                ':vendor' => $header['vendor_id'],
                ':req'    => $header['requested_by'],
                ':odate'  => $header['order_date'],
                ':edate'  => $header['expected_date'] ?: null,
                ':total'  => $header['total_amount'],
                ':notes'  => $header['notes'] ?: null,
            ]);

            $orderId = (int)$this->db->lastInsertId();

            $stmtItem = $this->db->prepare(
                "INSERT INTO procurement_items (order_id, item_name, specification, unit, quantity, unit_price)
                 VALUES (:oid, :name, :spec, :unit, :qty, :price)"
            );

            foreach ($items as $item) {
                $stmtItem->execute([
                    ':oid'   => $orderId,
                    ':name'  => $item['item_name'],
                    ':spec'  => $item['specification'] ?? '',
                    ':unit'  => $item['unit'],
                    ':qty'   => $item['quantity'],
                    ':price' => $item['unit_price'],
                ]);
            }

            $stmtBudget = $this->db->prepare(
                "UPDATE divisions SET budget_used = budget_used + :amount WHERE id = :div"
            );
            $stmtBudget->execute([
                ':amount' => $header['total_amount'],
                ':div'    => $header['division_id'],
            ]);

            $this->db->commit();
            return ['ok' => true, 'order_id' => $orderId, 'po_number' => $header['po_number']];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('CreateOrder FAILED: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Gagal menyimpan data: ' . $e->getMessage()];
        }
    }

    public function getAllOrders(?int $divisionId = null, ?string $status = null): array
    {
        $sql = "SELECT po.*, d.division_name, v.vendor_name, 
                       u.username AS requester_name,
                       a.username AS approver_name
                FROM procurement_orders po
                JOIN divisions d ON po.division_id = d.id
                JOIN vendors v ON po.vendor_id = v.id
                JOIN users u ON po.requested_by = u.id
                LEFT JOIN users a ON po.approved_by = a.id
                WHERE 1=1";

        $params = [];

        if ($divisionId) {
            $sql .= " AND po.division_id = :div";
            $params[':div'] = $divisionId;
        }
        if ($status) {
            $sql .= " AND po.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY po.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOrderById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT po.*, d.division_name, d.budget_annual, d.budget_used,
                    v.vendor_name, v.contact_person, v.phone AS vendor_phone,
                    u.username AS requester_name
             FROM procurement_orders po
             JOIN divisions d ON po.division_id = d.id
             JOIN vendors v ON po.vendor_id = v.id
             JOIN users u ON po.requested_by = u.id
             WHERE po.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        if (!$order) return null;

        $stmtItems = $this->db->prepare(
            "SELECT * FROM procurement_items WHERE order_id = :oid ORDER BY id ASC"
        );
        $stmtItems->execute([':oid' => $id]);
        $order['items'] = $stmtItems->fetchAll();

        return $order;
    }

    public function updateStatus(int $orderId, string $newStatus, int $userId, ?string $note = null): bool
    {
        $validTransitions = [
            'Pending'              => ['Approved by Manager', 'Rejected'],
            'Approved by Manager'  => ['Ordered to Vendor'],
            'Ordered to Vendor'    => ['Received'],
        ];

        $stmt = $this->db->prepare("SELECT status FROM procurement_orders WHERE id = :id");
        $stmt->execute([':id' => $orderId]);
        $current = $stmt->fetchColumn();

        if (
            !$current || !isset($validTransitions[$current])
            || !in_array($newStatus, $validTransitions[$current], true)
        ) {
            return false;
        }

        $sql = "UPDATE procurement_orders SET status = :status, updated_at = NOW()";
        $params = [':status' => $newStatus, ':id' => $orderId];

        if ($newStatus === 'Approved by Manager') {
            $sql .= ", approved_by = :approver";
            $params[':approver'] = $userId;
        }
        if ($newStatus === 'Rejected' && $note) {
            $sql .= ", rejection_note = :note";
            $params[':note'] = $note;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteOrder(int $orderId, int $divisionId): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT total_amount, division_id FROM procurement_orders 
                 WHERE id = :id AND status = 'Pending' AND division_id = :div"
            );
            $stmt->execute([':id' => $orderId, ':div' => $divisionId]);
            $order = $stmt->fetch();

            if (!$order) {
                $this->db->rollBack();
                return false;
            }

            $this->db->prepare("UPDATE divisions SET budget_used = budget_used - :amt WHERE id = :div")
                ->execute([':amt' => $order['total_amount'], ':div' => $order['division_id']]);

            $this->db->prepare("DELETE FROM procurement_items WHERE order_id = :id")->execute([':id' => $orderId]);
            $this->db->prepare("DELETE FROM procurement_orders WHERE id = :id")->execute([':id' => $orderId]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('DeleteOrder FAILED: ' . $e->getMessage());
            return false;
        }
    }

    public function getVendors(): array
    {
        return $this->db->query("SELECT id, vendor_name FROM vendors WHERE is_active = 1 ORDER BY vendor_name")->fetchAll();
    }

    public function getDivisions(): array
    {
        return $this->db->query("SELECT id, division_name, budget_annual, budget_used, (budget_annual - budget_used) AS budget_remaining FROM divisions ORDER BY division_name")->fetchAll();
    }
}
