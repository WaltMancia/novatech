<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers\Admin;

use NovaTech\Core\{Request, Response, Validator};
use NovaTech\Domain\{Order, OrderStatus};
use NovaTech\Http\{CurrentUser, Pagination};
use NovaTech\Repositories\OrderRepository;
use NovaTech\Services\AdminOrderService;

final class OrderAdminController
{
    public function __construct(private readonly AdminOrderService $service, private readonly OrderRepository $orders)
    {
    }

    public function index(Request $request): Response
    {
        $filter = Validator::validate(
            ['status' => $request->query('status')],
            ['status' => 'in:' . implode(',', array_column(OrderStatus::cases(), 'value'))],
        );
        $page = Pagination::from($request);
        $status = isset($filter['status']) ? OrderStatus::from($filter['status']) : null;

        $result = $this->orders->paginate($status, $page->limit(), $page->offset());
        return Response::json([
            'orders' => array_map(static fn (Order $o) => $o->toAdmin(), $result['items']),
            'meta' => $page->meta($result['total']),
        ]);
    }

    public function show(Request $request): Response
    {
        return Response::json(['order' => $this->service->find((int) $request->param('id'))->toAdmin()]);
    }

    public function cancel(Request $request): Response
    {
        $order = $this->service->cancel(CurrentUser::of($request), (int) $request->param('id'), $request->ip());
        return Response::json(['order' => $order->toAdmin()]);
    }
}
