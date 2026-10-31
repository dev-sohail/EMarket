@extends('layouts.admin')

@section('title', 'Reflect API from Model')

@section('header')
<div class="flex items-center justify-between">
    <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">Reflect API from Model</h1>
    <a href="{{ route('admin.api-management.definitions.index') }}" class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">Back to list</a>
</div>
@endsection

@section('content')

@php
    $reflectable = collect($rows)->where('available', true);
    $blocked = collect($rows)->where('available', false);
@endphp

<div class="space-y-6">
    <div class="bg-[var(--color-paper-dim)]/50 border hairline rounded-lg p-4">
        <h2 class="text-sm font-medium text-[var(--color-ink)] mb-2">What this does</h2>
        <p class="text-sm text-[var(--color-steel)]">
            Reflection reads an Eloquent model and generates a complete API definition from it: a
            definition, a listing and a detail endpoint, and one validated
            <code>filter_&lt;column&gt;</code> query parameter per column. Nothing is hand-written,
            and the generated endpoints are ordinary endpoints — they route and dispatch like any
            other.
        </p>
        <p class="text-sm text-[var(--color-steel)] mt-2">
            Reflection is a <strong>generation</strong> step, not a setting. This is why
            <code>source</code> is not a choice on the create form: ticking &ldquo;reflection&rdquo;
            there would only produce a definition with no endpoints, which serves nothing.
        </p>
    </div>

    @if (session('error'))
        <div class="rounded-lg border border-[var(--color-red)] bg-[var(--color-red)]/10 p-4 text-sm text-[var(--color-red)] whitespace-pre-line">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-[var(--color-paper)] border hairline rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-[var(--color-line)]">
            <h2 class="text-sm font-medium text-[var(--color-ink)]">Reflectable models</h2>
            <p class="text-xs text-[var(--color-steel)] mt-1">
                {{ $reflectable->count() }} of {{ count($rows) }} model(s) can be reflected.
                Models already reflected are marked, and generating again regenerates in place.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.api-management.definitions.reflect.store') }}" id="reflect-form">
            @csrf

            <div class="px-6 py-4 border-b border-[var(--color-line)] space-y-3">
                <div>
                    <label for="model" class="block text-sm font-medium text-[var(--color-ink)] mb-1">
                        Model <span class="text-[var(--color-red)]">*</span>
                    </label>
                    <select name="model" id="model" required
                            class="w-full border hairline bg-[var(--color-paper)] px-4 py-2.5 focus:outline-none focus:border-[var(--color-ink)] font-mono @error('model') border-[var(--color-red)] @enderror">
                        <option value="">Select a model&hellip;</option>
                        @foreach ($reflectable as $row)
                            <option value="{{ $row['class'] }}"
                                    data-availability="{{ count($row['filters']) }} filter(s), {{ count($row['relations']) }} relation(s)"
                                    @selected(old('model') === $row['class'])>
                                {{ class_basename($row['class']) }} — {{ $row['table'] }}
                                @if (isset($existingSlugs[$row['paths'][0] ?? null]))
                                    (already reflected)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('model')
                        <p class="mt-1 text-sm text-[var(--color-red)]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <input type="hidden" name="activate" value="0">
                    <label for="activate" class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="activate" id="activate" value="1" @checked(old('activate'))
                               class="rounded border-[var(--color-line)] text-[var(--color-red)] focus:ring-[var(--color-red)]">
                        <span class="text-sm text-[var(--color-ink-soft)]">
                            Activate immediately
                            <span class="text-xs text-[var(--color-steel)]">(off by default — generating a public URL is a decision)</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <button type="submit" id="reflect-submit"
                            class="bg-[var(--color-red)] text-white py-2.5 px-6 rounded-lg font-medium hover:bg-[var(--color-red-dark)] transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Generate API
                    </button>
                    <a href="{{ route('admin.api-management.definitions.index') }}"
                       class="py-2.5 px-4 rounded-lg border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition">
                        Back
                    </a>
                </div>
            </div>
        </form>
    </div>

    @if ($blocked->isNotEmpty())
        <div class="bg-[var(--color-paper)] border hairline rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--color-line)]">
                <h2 class="text-sm font-medium text-[var(--color-ink)]">Cannot be reflected</h2>
                <p class="text-xs text-[var(--color-steel)] mt-1">
                    These are listed so the reason is visible, rather than a model silently missing
                    from the picker.
                </p>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-[var(--color-line)]">
                    @foreach ($blocked as $row)
                        <tr>
                            <td class="px-6 py-3 font-mono text-[var(--color-ink)] whitespace-nowrap">{{ class_basename($row['class']) }}</td>
                            <td class="px-6 py-3 font-mono text-xs text-[var(--color-steel)] whitespace-nowrap">{{ $row['table'] }}</td>
                            <td class="px-6 py-3 text-xs text-[var(--color-steel)]">{{ $row['problem'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Progressive enhancement only. The form works without JS; this just stops a
     double submit and disables the button while generation runs. Guarded by
     the CSP nonce like every other script in the admin. --}}
<script nonce="{{ $cspNonce }}">
    (function () {
        var form = document.getElementById('reflect-form');
        var button = document.getElementById('reflect-submit');

        if (!form || !button) {
            return;
        }

        form.addEventListener('submit', function () {
            button.disabled = true;
            button.textContent = 'Generating…';
        });
    })();
</script>
@endsection
