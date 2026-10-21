<?php
declare(strict_types=1);

use NovaTech\Core\Contracts\Transactor;
use NovaTech\Domain\{DuplicateEmailException, Order, OrderItem, OrderStatus, Product, Role, User};
use NovaTech\Repositories\{OrderRepository, ProductRepository, UserRepository};
use NovaTech\Services\AuditLog;

final class InMemoryUsers implements UserRepository
{
    /** @var array<int, User> */
    public array $users = [];
    /** @var array<int, int> */
    public array $failed = [];
    /** @var array<int, true> */
    public array $locked = [];

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $u) {
            if ($u->email === $email) {
                return $this->withLock($u);
            }
        }
        return null;
    }

    public function findById(int $id): ?User
    {
        return isset($this->users[$id]) ? $this->withLock($this->users[$id]) : null;
    }

    public function create(string $email, string $name, string $passwordHash, Role $role): User
    {
        if ($this->findByEmail($email) !== null) {
            throw new DuplicateEmailException();
        }
        $id = count($this->users) + 1;
        return $this->users[$id] = new User($id, $email, $name, $role, $passwordHash);
    }

    public function recordFailedLogin(int $id, int $maxAttempts, int $lockSeconds): void
    {
        $this->failed[$id] = ($this->failed[$id] ?? 0) + 1;
        if ($this->failed[$id] >= $maxAttempts) {
            $this->locked[$id] = true;
            $this->failed[$id] = 0;
        }
    }

    public function resetFailedLogins(int $id): void
    {
        $this->failed[$id] = 0;
        unset($this->locked[$id]);
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $u = $this->users[$id];
        $this->users[$id] = new User($u->id, $u->email, $u->name, $u->role, $hash);
    }

    private function withLock(User $u): User
    {
        return new User($u->id, $u->email, $u->name, $u->role, $u->passwordHash, isset($this->locked[$u->id]));
    }
}

final class ArrayAudit implements AuditLog
{
    /** @var list<array{action: string, userId: ?int, meta: array}> */
    public array $events = [];

    public function record(string $action, ?int $userId, string $ip, array $metadata = []): void
    {
        $this->events[] = ['action' => $action, 'userId' => $userId, 'meta' => $metadata];
    }

    public function actions(): array
    {
        return array_column($this->events, 'action');
    }
}

final class InMemoryProducts implements ProductRepository
{
    /** @var array<int, Product> */
    public array $items = [];

    public function __construct()
    {
        $this->items = [
            1 => new Product(1, 'NV-LAP-14', 'Laptop', 'Portátil', 749900, 5),
            2 => new Product(2, 'NV-MOU-ER', 'Ratón', 'Ratón ergonómico', 29900, 3),
            3 => new Product(3, 'NV-OLD', 'Descontinuado', 'Inactivo', 10000, 9, null, false),
        ];
    }

    public function listActive(): array
    {
        return array_values(array_filter($this->items, static fn (Product $p) => $p->active));
    }

    public function findActive(int $id): ?Product
    {
        $p = $this->items[$id] ?? null;
        return $p !== null && $p->active ? $p : null;
    }

    public function findActiveByIds(array $ids): array
    {
        return array_filter($this->items, static fn (Product $p) => $p->active && in_array($p->id, $ids, true));
    }

    public function lockActiveByIds(array $ids): array
    {
        return $this->findActiveByIds($ids);
    }

    public function decrementStock(int $id, int $quantity): bool
    {
        $p = $this->items[$id];
        if ($p->stock < $quantity) {
            return false;
        }
        $this->items[$id] = $this->with($p, $p->stock - $quantity);
        return true;
    }

    public function restoreStock(int $id, int $quantity): void
    {
        $this->items[$id] = $this->with($this->items[$id], $this->items[$id]->stock + $quantity);
    }

    public function setPrice(int $id, int $priceCents): void
    {
        $p = $this->items[$id];
        $this->items[$id] = new Product($p->id, $p->sku, $p->name, $p->description, $priceCents, $p->stock, $p->imagePath, $p->active);
    }

    private function with(Product $p, int $stock): Product
    {
        return new Product($p->id, $p->sku, $p->name, $p->description, $p->priceCents, $stock, $p->imagePath, $p->active);
    }
}

final class InMemoryOrders implements OrderRepository
{
    /** @var array<int, array{userId: int, status: OrderStatus, total: int, ref: ?string, items: list<array{int,int,int}>}> */
    public array $rows = [];

    public function __construct(private readonly InMemoryProducts $products)
    {
    }

    public function create(int $userId, int $totalCents): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = ['userId' => $userId, 'status' => OrderStatus::Pending, 'total' => $totalCents, 'ref' => null, 'items' => []];
        return $id;
    }

    public function addItem(int $orderId, int $productId, int $quantity, int $unitPriceCents): void
    {
        $this->rows[$orderId]['items'][] = [$productId, $quantity, $unitPriceCents];
    }

    public function markPaid(int $orderId, string $paymentRef): void
    {
        $this->rows[$orderId]['status'] = OrderStatus::Paid;
        $this->rows[$orderId]['ref'] = $paymentRef;
    }

    public function markFailed(int $orderId): void
    {
        $this->rows[$orderId]['status'] = OrderStatus::Failed;
    }

    public function findForUser(int $orderId, int $userId): ?Order
    {
        $r = $this->rows[$orderId] ?? null;
        return $r !== null && $r['userId'] === $userId ? $this->hydrate($orderId, $r) : null;
    }

    public function listForUser(int $userId): array
    {
        $out = [];
        foreach ($this->rows as $id => $r) {
            if ($r['userId'] === $userId) {
                $out[] = $this->hydrate($id, $r);
            }
        }
        return $out;
    }

    private function hydrate(int $id, array $r): Order
    {
        $items = array_map(fn (array $i) => new OrderItem($i[0], $this->products->items[$i[0]]->name, $i[1], $i[2]), $r['items']);
        return new Order($id, $r['userId'], $r['status'], $r['total'], $r['ref'], '2026-01-01T00:00:00+00:00', $items);
    }
}

final class ImmediateTransactor implements Transactor
{
    public function transaction(callable $work): mixed
    {
        return $work();
    }
}
