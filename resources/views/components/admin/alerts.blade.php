{{--
    Flash and validation messages for the back office.

    Every back office alert goes through this component so the three message
    sources can never drift apart again:

      session('success')  one-off confirmation, set with ->with('success', ...)
      session('error')    one-off failure,     set with ->with('error', ...)
      $errors             validation failures  (shown as a list, with @error(...))

    The bug this replaces: the admin login page wrapped all three in a single
    `@if($errors->any())`. The login controller reports bad credentials with
    ->with('error', ...) and no validation errors, so $errors was empty, the
    whole block was skipped, and "Invalid email or password" never reached the
    screen. A failed login silently re-rendered an empty form.

    The three sources are rendered independently, never nested.

    `role="alert"` / `aria-live` make the message announce itself to assistive
    technology instead of appearing silently at the top of the page.
--}}
@php
    $flashes = array_filter([
        'success' => session('success'),
        'error' => session('error'),
    ]);

    // $errors is shared by ShareErrorsFromSession during a normal request, but
    // it is absent when the view is rendered outside one (a console render, or
    // any @include that escapes that middleware). Defaulting keeps the partial
    // safe to render anywhere instead of fataling on a null ->any() call.
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
