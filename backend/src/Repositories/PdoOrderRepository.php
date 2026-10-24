<?php
declare(strict_types=1);

namespace NovaTech\Repositories;

use NovaTech\Core\Database;
use NovaTech\Core\Support\Dates;
use NovaTech\Domain\{Order, OrderItem, OrderStatus};

final class PdoOrderRepository implements OrderRepository
{
    private const SELECT = 'SELECT id, user_id, status, total_cents, payment_ref, created_at FROM orders';

    public function __construct(private readonly Database $db)
    {
    }

    public function create(int $userId, int $totalCents): int
    {
        $this->db->execute(
            'INSERT INTO orders (user_id, status, total_cents) VALUES (?, ?, ?)',
            [$userId, OrderStatus::Pending->value, $totalCents],
        );
        return $this->db->lastInsertId();
    }

    public function addItem(int $orderId, int $productId, int $quantity, int $unitPriceCents): void
    {
        $this->db->execute(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price_cents) VALUES (?, ?, ?, ?)',
            [$orderId, $productId, $quantity, $unitPriceCents],
        );
    }

    public function markPaid(int $orderId, string $paymentRef): void
    {
        $this->db->execute(
            'UPDATE orders SET status = ?, payment_ref = ? WHERE id = ?',
            [OrderStatus::Paid->value, $paymentRef, $orderId],
        );
    }

    public function markFailed(int $orderId): void
    {
        $this->db->execute('UPDATE orders SET status = ? WHERE id = ?', [OrderStatus::Failed->value, $orderId]);
    }

    public function findForUser(int $orderId, int $userId): ?Order
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE id = ? AND user_id = ?', [$orderId, $userId]);
        return $row === null ? null : $this->withItems([$row])[0];
    }

    public function findAny(int $orderId): ?Order
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE id = ?', [$orderId]);
        return $row === null ? null : $this->withItems([$row])[0];
    }

    public function paginate(?OrderStatus $status, int $limit, int $offset): array
    {
        $where = $status === null ? '' : ' WHERE status = ?';
        $params = $status === null ? [] : [$status->value];

        $total = (int) ($this->db->fetchOne("SELECT COUNT(*) AS n FROM orders{$where}", $params)['n'] ?? 0);
        $rows = $this->db->fetchAll(self::SELECT . "{$where} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}", $params);
        return ['items' => $this->withItems($rows), 'total' => $total];
    }

    public function transition(int $orderId, OrderStatus $from, OrderStatus $to): bool
    {
        return $this->db->execute(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            [$to->value, $orderId, $from->value],
        ) === 1;
    }

    public function listForUser(int $userId): array
    {
        return $this->withItems($this->db->fetchAll(self::SELECT . ' WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]));
    }

    /**
     * Carga los ítems de todos los pedidos con una sola consulta.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<Order>
     */
    private function withItems(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r) => (int) $r['id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $itemRows = $this->db->fetchAll(
            "SELECT oi.order_id, oi.product_id, p.name, oi.quantity, oi.unit_price_cents
               FROM order_items oi JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id IN ({$marks}) ORDER BY oi.id",
            $ids,
        );

        $items = [];
        foreach ($itemRows as $i) {
            $items[(int) $i['order_id']][] = new OrderItem((int) $i['product_id'], (string) $i['name'], (int) $i['quantity'], (int) $i['unit_price_cents']);
        }

        return array_map(fn (array $r) => new Order(
            (int) $r['id'],
            (int) $r['user_id'],
            OrderStatus::from((string) $r['status']),
            (int) $r['total_cents'],
            $r['payment_ref'] === null ? null : (string) $r['payment_ref'],
            Dates::toIso((string) $r['created_at']),
            $items[(int) $r['id']] ?? [],
        ), $rows);
    }
}
