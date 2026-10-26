<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers\Admin;

use NovaTech\Core\{Request, Response};
use NovaTech\Domain\User;
use NovaTech\Http\Pagination;
use NovaTech\Repositories\UserRepository;

final class UserAdminController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function index(Request $request): Response
    {
        $page = Pagination::from($request);
        $result = $this->users->paginate($page->limit(), $page->offset());
        return Response::json([
            'users' => array_map(static fn (User $u) => $u->toAdmin(), $result['items']),
            'meta' => $page->meta($result['total']),
        ]);
    }
}
