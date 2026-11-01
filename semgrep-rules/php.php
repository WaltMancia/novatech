<?php
// Casos de prueba de las reglas de .semgrep/php.yml (se ejecutan con `semgrep --test .semgrep`).

function sqlInterpolation($db, $id, $marks) {
    // ruleid: novatech-php-sql-interpolation
    $db->fetchAll("SELECT * FROM t WHERE id IN ({$marks})");
    // ruleid: novatech-php-sql-interpolation
    $db->execute("DELETE FROM t WHERE id = $id");
    // ruleid: novatech-php-sql-interpolation
    $db->fetchOne('SELECT * FROM t WHERE name = ' . $id . ' LIMIT 1');
    // ruleid: novatech-php-sql-interpolation
    $db->fetchAll(self::SELECT . " ORDER BY id LIMIT {$id}");
    // ok: novatech-php-sql-interpolation
    $db->fetchOne('SELECT * FROM t WHERE id = ?', [$id]);
    // ok: novatech-php-sql-interpolation
    $db->fetchAll('SELECT * FROM t ORDER BY id');
}

function dangerous($input) {
    // ruleid: novatech-php-dangerous-function
    eval($input);
    // ruleid: novatech-php-dangerous-function
    shell_exec($input);
    // ruleid: novatech-php-dangerous-function
    unserialize($input);
    // ok: novatech-php-dangerous-function
    json_decode($input, true, 16, JSON_THROW_ON_ERROR);
}

function hashing($p) {
    // ruleid: novatech-php-weak-hash
    md5($p);
    // ruleid: novatech-php-weak-hash
    sha1($p);
    // ok: novatech-php-weak-hash
    hash('sha256', $p);
    // ruleid: novatech-php-weak-random
    mt_rand(1, 10);
    // ruleid: novatech-php-weak-random
    uniqid();
    // ok: novatech-php-weak-random
    random_int(1, 10);
    // ruleid: novatech-php-password-algorithm
    password_hash($p, PASSWORD_BCRYPT);
    // ruleid: novatech-php-password-algorithm
    password_hash($p, PASSWORD_DEFAULT);
    // ok: novatech-php-password-algorithm
    password_hash($p, PASSWORD_ARGON2ID, ['memory_cost' => 19456]);
}

function input() {
    // ruleid: novatech-php-superglobal-input
    $a = $_GET['x'];
    // ruleid: novatech-php-superglobal-input
    $b = $_POST['y'];
    // ok: novatech-php-superglobal-input
    $c = $_SERVER['REMOTE_ADDR'];
    // ruleid: novatech-php-cors-wildcard
    header('Access-Control-Allow-Origin: *');
    // ok: novatech-php-cors-wildcard
    header('X-Content-Type-Options: nosniff');
}
