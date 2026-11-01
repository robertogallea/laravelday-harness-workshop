<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->isStaff() || $order->user_id === $user->id;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && $order->status === OrderStatus::Pending;
    }

    public function refund(User $user, Order $order): bool
    {
        return in_array($user->role, [Role::Finance, Role::Admin], true)
            && $order->status === OrderStatus::Paid;
    }
}
