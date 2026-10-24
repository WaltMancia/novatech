<?php
declare(strict_types=1);

namespace NovaTech\Http;

use NovaTech\Core\{Request, Validator};

final readonly class Pagination
{
    public function __construct(public int $page, public int $perPage)
    {
    }

    public static function from(Request $request, int $defaultPerPage = 20, int $maxPerPage = 100): self
    {
        $data = Validator::validate(
            ['page' => $request->query('page'), 'perPage' => $request->query('perPage')],
            ['page' => 'int|between:1,100000', 'perPage' => "int|between:1,{$maxPerPage}"],
        );
        return new self($data['page'] ?? 1, $data['perPage'] ?? $defaultPerPage);
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** @return array{page: int, perPage: int, total: int} */
    public function meta(int $total): array
    {
        return ['page' => $this->page, 'perPage' => $this->perPage, 'total' => $total];
    }
}
