<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\{Request, Response, Validator};
use NovaTech\Http\CurrentUser;
use NovaTech\Services\CartService;

final class CartController
{
    public function __construct(private readonly CartService $cart)
    {
    }

    public function show(Request $request): Response
    {
        return Response::json($this->cart->view(CurrentUser::of($request)));
    }

    public function add(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'productId' => 'required|int|between:1,999999999',
            'quantity' => 'required|int|between:1,' . CartService::MAX_QUANTITY,
        ], rejectUnknown: true);

        $user = CurrentUser::of($request);
        $this->cart->add($user, $data['productId'], $data['quantity']);
        return Response::json($this->cart->view($user), 201);
    }

    public function update(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'quantity' => 'required|int|between:1,' . CartService::MAX_QUANTITY,
        ], rejectUnknown: true);

        $user = CurrentUser::of($request);
        $this->cart->setQuantity($user, (int) $request->param('productId'), $data['quantity']);
        return Response::json($this->cart->view($user));
    }

    public function remove(Request $request): Response
    {
        $user = CurrentUser::of($request);
        $this->cart->remove($user, (int) $request->param('productId'));
        return Response::json($this->cart->view($user));
    }

    public function clear(Request $request): Response
    {
        $user = CurrentUser::of($request);
        $this->cart->clear($user);
        return Response::json($this->cart->view($user));
    }
}
