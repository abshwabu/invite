<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    /**
     * Display host notifications.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all');

        $query = $user->notifications();
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate(25)->withQueryString();

        $unreadCount = $user->unreadNotifications()->count();

        // Transform notifications for clean frontend consumption
        $notifications->getCollection()->transform(function ($item) {
            $data = is_array($item->data) ? $item->data : (json_decode($item->data, true) ?: []);

            return [
                'id' => $item->id,
                'type' => $data['type'] ?? 'info',
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'meta' => $data['meta'] ?? [],
                'action_url' => $data['action_url'] ?? null,
                'read_at' => $item->read_at?->toIso8601String(),
                'is_read' => $item->read_at !== null,
                'created_at' => $item->created_at?->diffForHumans(),
                'created_at_exact' => $item->created_at?->format('M d, Y g:i A'),
            ];
        });

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'currentFilter' => $filter,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('Notification marked as read.'));
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('All notifications marked as read.'));
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->delete();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', __('Notification deleted.'));
    }
}
