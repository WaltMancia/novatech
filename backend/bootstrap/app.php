<?php
declare(strict_types=1);

use NovaTech\Core\{App, Config, Container, Database, Logger, Router};
use NovaTech\Core\Contracts\{RateLimitStore, Session, Transactor};
use NovaTech\Core\Security\CsrfTokenManager;
use NovaTech\Core\Support\{FileRateLimitStore, NativeSession};
use NovaTech\Domain\Role;
use NovaTech\Http\Middleware\{HandleErrors, RateLimit, RequestContext, RequireRole, SecurityHeaders, VerifyCsrf};
use NovaTech\Repositories\{AuditLogReader, DatabaseAuditLog, OrderRepository, PdoAuditLogReader, PdoOrderRepository, PdoProductRepository, PdoUserRepository, ProductRepository, UserRepository};
use NovaTech\Services\AuditLog;
use NovaTech\Services\Payment\{PaymentGateway, SimulatedPaymentGateway};

/**
 * Fábrica de la aplicación (composition root).
 * $overrides permite inyectar dobles de prueba; $extraRoutes registrar rutas adicionales en tests.
 */
return static function (array $overrides = [], ?callable $extraRoutes = null): App {
    $config = (require __DIR__ . '/config.php')();

    $c = new Container();
    $c->instance(Config::class, $config);
    $c->instance(Container::class, $c);
    $c->singleton(Logger::class, static fn () => new Logger((string) $config->get('log.stream', 'php://stderr')));
    $c->singleton(Database::class, static fn () => new Database((array) $config->get('database')));
    $c->singleton(Session::class, static fn () => new NativeSession(
        (int) $config->get('app.session_idle_ttl', 1800),
        (int) $config->get('app.session_absolute_ttl', 28800),
    ));
    $c->singleton(RateLimitStore::class, static fn () => new FileRateLimitStore(sys_get_temp_dir() . '/novatech_rl'));
    $c->singleton(CsrfTokenManager::class, static fn (Container $c) => new CsrfTokenManager($c->get(Session::class)));

    $c->singleton(UserRepository::class, static fn (Container $c) => new PdoUserRepository($c->get(Database::class)));
    $c->singleton(AuditLog::class, static fn (Container $c) => new DatabaseAuditLog($c->get(Database::class), $c->get(Logger::class)));

    $c->singleton(Transactor::class, static fn (Container $c) => $c->get(Database::class));
    $c->singleton(ProductRepository::class, static fn (Container $c) => new PdoProductRepository($c->get(Database::class)));
    $c->singleton(OrderRepository::class, static fn (Container $c) => new PdoOrderRepository($c->get(Database::class)));
    $c->singleton(PaymentGateway::class, static fn () => new SimulatedPaymentGateway());

    $c->singleton(AuditLogReader::class, static fn (Container $c) => new PdoAuditLogReader($c->get(Database::class)));

    foreach ($overrides as $id => $object) {
        $c->instance($id, $object);
    }

    $router = new Router();
    (require __DIR__ . '/../routes/api.php')($router);
    if ($extraRoutes !== null) {
        $extraRoutes($router);
    }

    // Orden importa: contexto -> cabeceras -> manejo de errores -> límite global -> CSRF -> router.
    $global = [
        RequestContext::class,
        SecurityHeaders::class,
        HandleErrors::class,
        'throttle:120,60',
        VerifyCsrf::class,
    ];

    $aliases = [
        'role' => static fn (Container $c, array $a) => new RequireRole(Role::from($a[0] ?? 'customer')),
        'throttle' => static fn (Container $c, array $a) => new RateLimit(
            $c->get(RateLimitStore::class), (int) ($a[0] ?? 60), (int) ($a[1] ?? 60)
        ),
    ];

    return new App($c, $router, $global, $aliases);
};
