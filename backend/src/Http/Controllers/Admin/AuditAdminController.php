<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers\Admin;

use NovaTech\Core\{Request, Response, Validator};
use NovaTech\Http\Pagination;
use NovaTech\Repositories\AuditLogReader;

final class AuditAdminController
{
    public function __construct(private readonly AuditLogReader $audit)
    {
    }

    public function index(Request $request): Response
    {
        $filter = Validator::validate(
            ['action' => $request->query('action'), 'userId' => $request->query('userId')],
            ['action' => ['regex:/^[a-z_.]{1,64}$/'], 'userId' => 'int|between:1,999999999'],
        );
        $page = Pagination::from($request, 50);

        $result = $this->audit->paginate($filter['action'] ?? null, $filter['userId'] ?? null, $page->limit(), $page->offset());
        return Response::json(['events' => $result['items'], 'meta' => $page->meta($result['total'])]);
    }
}
