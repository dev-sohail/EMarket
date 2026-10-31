<?php

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Notifications\Notification;

class GenericAdminNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
        public ?array $data = null
    ) {}

    public function via(User $notifiable): array
    {
        return ['database'];
    }

    public function toArray(User $notifiable): array
    {
        return array_merge([
            'title' => $this->title,
            'message' => $this->message,
        ], $this->data ?? []);
    }
}
