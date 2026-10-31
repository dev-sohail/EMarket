@extends('layouts.admin')
@section('title', 'Enquiry #' . $enquiry->_id)
@section('header')
<div class="flex items-center justify-between">
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.enquiries.index') }}" class="text-[var(--color-steel)] hover:text-[var(--color-ink)] text-sm">← Back</a>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Enquiry #{{ $enquiry->_id }}</h1>
    </div>
    <form method="POST" action="{{ route('admin.enquiries.destroy', $enquiry) }}" data-confirm="Delete this enquiry?">
        @csrf @method('DELETE')
        <button type="submit" class="text-[var(--color-red)] hover:text-[var(--color-ink)] text-sm font-medium">Delete</button>
    </form>
</div>
@endsection
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Message</h2>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider mb-1">From</p>
                        <p class="font-medium text-[var(--color-ink)]">{{ $enquiry->name }}</p>
                        <p class="text-sm text-[var(--color-steel)]">{{ $enquiry->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider mb-1">Subject</p>
                        <p class="font-medium text-[var(--color-ink)]">{{ $enquiry->subject }}</p>
                    </div>
                </div>
                <div class="border-t pt-4">
                    <p class="text-xs text-[var(--color-steel)] uppercase tracking-wider mb-2">Message</p>
                    <div class="bg-[var(--color-paper-dim)] rounded-lg p-4 text-[var(--color-ink-soft)] whitespace-pre-wrap">{{ $enquiry->message }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-4">Status</h2>
            <form method="POST" action="{{ route('admin.enquiries.update', $enquiry) }}">
                @csrf @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Status</label>
                        <select name="status" class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-[var(--color-red)]">
                            @foreach(['new', 'read', 'replied', 'closed'] as $s)
                                <option value="{{ $s }}" {{ $enquiry->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink-soft)] mb-1">Admin Notes</label>
                        <textarea name="admin_notes" rows="4" class="w-full border rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-[var(--color-red)]" placeholder="Internal notes...">{{ $enquiry->admin_notes }}</textarea>
                    </div>
                    <button type="submit" class="w-full bg-[var(--color-red)] text-white py-2 rounded-lg font-medium hover:bg-[var(--color-red-dark)]">Update</button>
                </div>
            </form>
        </div>

        <div class="bg-[var(--color-paper)] rounded-lg border p-6">
            <h2 class="font-semibold text-[var(--color-ink)] mb-3">Details</h2>
            <div class="text-sm text-[var(--color-steel)] space-y-2">
                <div class="flex justify-between">
                    <span class="text-[var(--color-steel)]">Received</span>
                    <span>{{ $enquiry->created_at->format('M d, Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[var(--color-steel)]">Last Updated</span>
                    <span>{{ $enquiry->updated_at->diffForHumans() }}</span>
                </div>
                @if($enquiry->read_at)
                    <div class="flex justify-between">
                        <span class="text-[var(--color-steel)]">First Read</span>
                        <span>{{ $enquiry->read_at->format('M d, Y H:i') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
