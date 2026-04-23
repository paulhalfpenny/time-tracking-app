<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Manager = 'manager';
    case Admin = 'admin';
}
