<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Services\Audit\AuditService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    protected NotificationService $notificationService;

    protected AuditService $auditService;

    public function __construct(NotificationService $notificationService, AuditService $auditService)
    {
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $enquiries = Enquiry::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20);

        $stats = [
            'all' => Enquiry::count(),
            'new' => Enquiry::where('status', 'new')->count(),
            'read' => Enquiry::where('status', 'read')->count(),
            'replied' => Enquiry::where('status', 'replied')->count(),
            'closed' => Enquiry::where('status', 'closed')->count(),
        ];

        return view('admin.enquiries.index', compact('enquiries', 'stats'));
    }

    public function show(Enquiry $enquiry)
    {
        $oldValues = $enquiry->toArray();

        if ($enquiry->status === 'new') {
            $enquiry->update([
                'status' => 'read',
                'read_at' => now(),
                'user_id' => acting_user_id(),
            ]);
        }

        $newValues = $enquiry->fresh()->toArray();

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Enquiry::class,
            entityId: $enquiry->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return view('admin.enquiries.show', compact('enquiry'));
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $oldValues = $enquiry->toArray();

        $validated = $request->validate([
            'status' => ['required', 'in:new,read,replied,closed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $enquiry->update($validated);
        $newValues = $enquiry->fresh()->toArray();

        $this->notificationService->sendToAllAdmins(
            title: 'Enquiry Updated',
            message: "Enquiry '{$enquiry->subject}' status changed to {$validated['status']}.",
            data: ['enquiry_id' => $enquiry->getKey()]
        );

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'updated',
            entity: Enquiry::class,
            entityId: $enquiry->getKey(),
            oldValues: $oldValues,
            newValues: $newValues
        );

        return back()->with('success', 'Enquiry updated successfully.');
    }

    public function destroy(Enquiry $enquiry)
    {
        $enquiryData = $enquiry->toArray();
        $enquiry->delete();

        $this->notificationService->sendToAllAdmins(
            title: 'Enquiry Deleted',
            message: "Enquiry '{$enquiryData['subject']}' has been deleted.",
            data: ['enquiry_id' => $enquiryData['_id']]
        );

        $this->auditService->log(
            user: auth()->guard('admin')->user(),
            action: 'deleted',
            entity: Enquiry::class,
            entityId: $enquiryData['_id'],
            oldValues: $enquiryData
        );

        return redirect()->route('admin.enquiries.index')
            ->with('success', 'Enquiry deleted successfully.');
    }
}
