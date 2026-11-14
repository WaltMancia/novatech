<?php
declare(strict_types=1);

namespace NovaTech\Http\Controllers;

use NovaTech\Core\Exceptions\HttpException;
use NovaTech\Core\{Request, Response};
use NovaTech\Domain\Product;
use NovaTech\Repositories\ProductRepository;

final class ProductController
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function index(Request $request): Response
    {
        return Response::json(['products' => array_map(static fn (Product $p) => $p->toPublic(), $this->products->listActive())]);
    }

    /** [LAB-BASELINE] Ruta de búsqueda con SQL concatenado (ver ProductRepository::searchUnsafe). */
    public function search(Request $request): Response
    {
        $term = $request->query('q', '');
        return Response::json(['products' => array_map(static fn (Product $p) => $p->toPublic(), $this->products->searchUnsafe($term))]);
    }

    public function show(Request $request): Response
    {
        $product = $this->products->findActive((int) $request->param('id'))
            ?? throw new HttpException(404, 'product_not_found', 'Producto no encontrado.');
        return Response::json(['product' => $product->toPublic()]);
    }
}
