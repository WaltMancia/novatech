<?php
declare(strict_types=1);

namespace NovaTech\Services;

use NovaTech\Core\Contracts\Session;
use NovaTech\Core\Exceptions\ValidationException;
use NovaTech\Core\Security\CsrfTokenManager;
use NovaTech\Domain\{DuplicateEmailException, Role, User};
use NovaTech\Repositories\UserRepository;

final class AuthService
{
    public const MAX_ATTEMPTS = 5;
    public const LOCK_SECONDS = 900;
    public const SESSION_KEY = 'user_id';

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Session $session,
        private readonly CsrfTokenManager $csrf,
        private readonly AuditLog $audit,
    ) {
    }

    public function register(string $email, string $name, string $password, string $ip): User
    {
        try {
            $user = $this->users->create($this->normalize($email), $name, $this->hasher->hash($password), Role::Customer);
        } catch (DuplicateEmailException) {
            throw new ValidationException(['email' => ['Este correo no está disponible.']]);
        }
        $this->audit->record('user.registered', $user->id, $ip);
        $this->startSession($user);
        return $user;
    }

    /** @throws InvalidCredentialsException */
    public function login(string $email, string $password, string $ip): User
    {
        $user = $this->users->findByEmail($this->normalize($email));

        // Siempre se ejecuta exactamente un hash, exista o no el usuario, para no filtrar su existencia por tiempo.
        if ($user === null || $user->locked) {
            $this->hasher->burn($password);
            $this->audit->record('auth.login_failed', $user?->id, $ip, ['reason' => $user === null ? 'unknown_user' : 'locked']);
            throw new InvalidCredentialsException();
        }

        if (!$this->hasher->verify($password, $user->passwordHash)) {
            $this->users->recordFailedLogin($user->id, self::MAX_ATTEMPTS, self::LOCK_SECONDS);
            $this->audit->record('auth.login_failed', $user->id, $ip, ['reason' => 'bad_password']);
            throw new InvalidCredentialsException();
        }

        $this->users->resetFailedLogins($user->id);
        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->updatePasswordHash($user->id, $this->hasher->hash($password));
        }
        $this->audit->record('auth.login_success', $user->id, $ip);
        $this->startSession($user);
        return $user;
    }

    public function logout(?User $user, string $ip): void
    {
        $this->audit->record('auth.logout', $user?->id, $ip);
        $this->session->destroy();
    }

    /** Nuevo ID de sesión y nuevo token CSRF al elevar privilegios (anti session fixation). */
    private function startSession(User $user): void
    {
        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, $user->id);
        $this->csrf->rotate();
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
