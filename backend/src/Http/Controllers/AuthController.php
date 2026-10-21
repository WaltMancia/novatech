<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\{Request, Response, Validator};
use NovaTech\Domain\User;
use NovaTech\Services\AuthService;

final class AuthController
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'email' => 'required|trim|email',
            'name' => 'required|trim|string|between:2,100',
            'password' => 'required|password',
        ], rejectUnknown: true);

        $user = $this->auth->register($data['email'], $data['name'], $data['password'], $request->ip());
        return Response::json(['user' => $user->toPublic()], 201);
    }

    public function login(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'email' => 'required|trim|string|max:254',
            'password' => 'required|string|max:128',
        ], rejectUnknown: true);

        $user = $this->auth->login($data['email'], $data['password'], $request->ip());
        return Response::json(['user' => $user->toPublic()]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->attribute('user');
        $this->auth->logout($user instanceof User ? $user : null, $request->ip());
        return Response::empty();
    }

    public function me(Request $request): Response
    {
        $user = $request->attribute('user');
        return Response::json(['user' => $user instanceof User ? $user->toPublic() : null]);
    }
}
