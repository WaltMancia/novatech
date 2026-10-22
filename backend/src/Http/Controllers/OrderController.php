<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\{Request, Response, Validator};
use NovaTech\Domain\Order;
use NovaTech\Http\CurrentUser;
use NovaTech\Services\OrderService;
use NovaTech\Services\Payment\SimulatedPaymentGateway;

final class OrderController
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'paymentMethod' => 'required|in:' . implode(',', SimulatedPaymentGateway::METHODS),
        ], rejectUnknown: true);

        $order = $this->orders->checkout(CurrentUser::of($request), $data['paymentMethod'], $request->ip());
        return Response::json(['order' => $order->toPublic()], 201);
    }

    public function index(Request $request): Response
    {
        $orders = $this->orders->list(CurrentUser::of($request));
        return Response::json(['orders' => array_map(static fn (Order $o) => $o->toPublic(), $orders)]);
    }

    public function show(Request $request): Response
    {
        $order = $this->orders->find(CurrentUser::of($request), (int) $request->param('id'));
        return Response::json(['order' => $order->toPublic()]);
    }
}
