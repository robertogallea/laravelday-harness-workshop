<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Support = 'support';
    case Finance = 'finance';
    case Admin = 'admin';
}
