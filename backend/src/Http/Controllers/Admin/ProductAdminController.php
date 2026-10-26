<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers\Admin;

use NovaTech\Core\Exceptions\ValidationException;
use NovaTech\Core\{Request, Response, Validator};
use NovaTech\Domain\Product;
use NovaTech\Http\{CurrentUser, Pagination};
use NovaTech\Repositories\ProductRepository;
use NovaTech\Services\AdminCatalogService;

final class ProductAdminController
{
    // Solo rutas de imagen internas y con nombre simple: sin URLs externas, esquemas ni recorrido de directorios.
    private const IMAGE = '/^\/images\/[a-z0-9-]{1,64}\.(png|jpe?g|webp)$/';

    public function __construct(
        private readonly AdminCatalogService $catalog,
        private readonly ProductRepository $products,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = Pagination::from($request);
        $result = $this->products->paginate($page->limit(), $page->offset());
        return Response::json([
            'products' => array_map(static fn (Product $p) => $p->toPublic() + ['active' => $p->active], $result['items']),
            'meta' => $page->meta($result['total']),
        ]);
    }

    public function show(Request $request): Response
    {
        return Response::json(['product' => $this->view($this->catalog->find((int) $request->param('id')))]);
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'sku' => ['required', 'trim', 'regex:/^[A-Z0-9-]{3,32}$/'],
            'name' => 'required|trim|string|between:2,120',
            'description' => 'required|trim|string|between:1,2000',
            'priceCents' => 'required|int|between:1,100000000',
            'stock' => 'required|int|between:0,100000',
            'imagePath' => ['nullable', 'regex:' . self::IMAGE],
        ], rejectUnknown: true);

        $product = $this->catalog->create(CurrentUser::of($request), $data, $request->ip());
        return Response::json(['product' => $this->view($product)], 201);
    }

    public function update(Request $request): Response
    {
        $changes = Validator::validate($request->json(), [
            'name' => 'trim|string|between:2,120',
            'description' => 'trim|string|between:1,2000',
            'priceCents' => 'int|between:1,100000000',
            'stock' => 'int|between:0,100000',
            'imagePath' => ['nullable', 'regex:' . self::IMAGE],
            'active' => 'bool',
        ], rejectUnknown: true);

        if ($changes === []) {
            throw new ValidationException(['body' => ['Debes enviar al menos un campo a modificar.']]);
        }
        $product = $this->catalog->update(CurrentUser::of($request), (int) $request->param('id'), $changes, $request->ip());
        return Response::json(['product' => $this->view($product)]);
    }

    public function destroy(Request $request): Response
    {
        $this->catalog->deactivate(CurrentUser::of($request), (int) $request->param('id'), $request->ip());
        return Response::empty();
    }

    /** @return array<string, mixed> */
    private function view(Product $p): array
    {
        return $p->toPublic() + ['active' => $p->active];
    }
}
