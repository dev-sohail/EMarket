<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends Controller
{
    public function markRead(Request $request)
    {
        $user = auth()->guard('admin')->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function index(Request $request)
    {
        $user = auth()->guard('admin')->user();

        $query = $user ? $user->notifications()->latest() : Builder::from(new DatabaseNotification);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('data', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->input('status') === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        $notifications = $user ? $query->paginate(20)->withQueryString() : LengthAwarePaginator::make([], 0, 20);

        $unreadCount = $user ? $user->unreadNotifications->count() : 0;

        return view('admin.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAsRead(DatabaseNotification $notification)
    {
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function destroy(DatabaseNotification $notification)
    {
        $notification->delete();

        return back()->with('success', 'Notification deleted.');
    }

    public function bulkRead(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id',
        ]);

        $user = auth()->guard('admin')->user();

        if ($user) {
            $user->notifications()
                ->whereIn('id', $request->input('notification_ids'))
                ->update(['read_at' => now()]);
        }

        return back()->with('success', 'Selected notifications marked as read.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id',
        ]);

        $user = auth()->guard('admin')->user();

        if ($user) {
            $user->notifications()
                ->whereIn('id', $request->input('notification_ids'))
                ->delete();
        }

        return back()->with('success', 'Selected notifications deleted.');
    }
}
