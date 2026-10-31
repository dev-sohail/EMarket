<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Notifications\Admin\GenericAdminNotification;

class NotificationService
{
    public function sendToAllAdmins(string $title, string $message, ?array $data = null): void
    {
        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('name', config('admin.roles', ['Super Admin', 'Admin', 'super_admin', 'admin'])))->get();

        foreach ($admins as $admin) {
            $admin->notify(new GenericAdminNotification($title, $message, $data));
        }
    }

    public function sendToAdmin(?User $admin, string $title, string $message, ?array $data = null): void
    {
        if (! $admin) {
            return;
        }

        $admin->notify(new GenericAdminNotification($title, $message, $data));
    }
}
