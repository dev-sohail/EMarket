@php
    $flashes = array_filter([
        'success' => session('success'),
        'error' => session('error'),
    ]);

    $errorBag = $errors ?? new Illuminate\Support\ViewErrorBag;
@endphp

@foreach ($flashes as $level => $message)
    <div
        role="alert"
        @if ($level === 'error') aria-live="assertive" @else aria-live="polite" @endif
        @class([
            'mb-4 p-4 rounded-lg flex items-start gap-2 border',
            'bg-[#1B7A43]/10 border-[#1B7A43]/30 text-[#1B7A43]' => $level === 'success',
            'bg-[var(--color-red)]/10 border-[var(--color-red)]/30 text-[var(--color-red)]' => $level === 'error',
        ])
    >
        <i @class([
            'fa-solid fa-check mt-0.5',
            'fa-solid fa-xmark mt-0.5' => $level === 'error',
        ]) aria-hidden="true"></i>
        <div>{{ $message }}</div>
    </div>
@endforeach

@if ($errorBag->any())
    <div
        role="alert"
        aria-live="assertive"
        class="mb-4 p-4 rounded-lg border bg-[var(--color-red)]/10 border-[var(--color-red)]/30 text-[var(--color-red)]"
    >
        <div class="flex items-start gap-2">
            <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
            <div class="flex-1">
                <p class="font-medium">
                    {{ trans_choice(
                        'There is :count problem with this form.|There are :count problems with this form.',
                        $errorBag->count(),
                        ['count' => $errorBag->count()],
                    ) }}
                </p>
                <ul class="list-disc list-inside mt-1 text-sm">
                    @foreach ($errorBag->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif