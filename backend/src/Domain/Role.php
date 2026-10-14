<?php
declare(strict_types=1);

namespace NovaTech\Domain;

enum Role: string
{
    case Customer = 'customer';
    case Admin = 'admin';
}
