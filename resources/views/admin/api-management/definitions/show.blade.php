@extends('layouts.admin')

@section('title', $definition->name)

@section('header')
<div class="flex items-start justify-between gap-4 flex-wrap">
    <div>
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-display text-2xl font-bold text-[var(--color-ink)]">{{ $definition->name }}</h1>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $definition->source === 'reflection' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                {{ ucfirst($definition->source) }}
            </span>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $definition->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                {{ $definition->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        <p class="text-sm text-[var(--color-steel)] mt-1 font-mono">{{ $definition->slug }}</p>
    </div>
    <div class="flex items-center gap-2">
        @if ($definition->source === 'reflection')
            <form method="POST" action="{{ route('admin.api-management.definitions.regenerate', $definition->getKey()) }}"
                  data-confirm="Regenerate from the model? This overwrites the name, description and the generated endpoints, and replaces their parameters.">
                @csrf
                <button type="submit"
                        class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">
                    Regenerate
                </button>
            </form>
        @endif
        <a href="{{ route('admin.api-management.definitions.edit', $definition->getKey()) }}"
           class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">Edit</a>
        <a href="{{ route('admin.api-management.definitions.index') }}"
           class="py-2 px-4 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-sm">Back to list</a>
    </div>
</div>
@endsection

@section('content')
@php
    $prefix = trim(config('api_management.prefix', 'api'), '/');
    $baseUrl = rtrim(parse_url(url('/'), PHP_URL_SCHEME) . '://' . parse_url(url('/'), PHP_URL_HOST), '/') . '/' . $prefix;
@endphp

<div class="max-w-5xl space-y-6">

    @if(session('success'))
        <div class="p-3 border hairline bg-green-50 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    {{-- Overview --}}
    <div class="bg-[var(--color-paper)] rounded-lg border hairline p-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div>
                <dt class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Slug</dt>
                <dd class="mt-1 text-sm font-mono text-[var(--color-ink)]">{{ $definition->slug }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Endpoints</dt>
                <dd class="mt-1 text-sm text-[var(--color-ink)]">{{ $definition->endpoints->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Created</dt>
                <dd class="mt-1 text-sm text-[var(--color-ink)]">{{ $definition->created_at?->format('j M Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">ID</dt>
                <dd class="mt-1 text-sm font-mono text-[var(--color-ink)] break-all">{{ $definition->getKey() }}</dd>
            </div>
        </dl>

        @if($definition->description)
            <p class="mt-5 pt-5 border-t border-[var(--color-line)] text-sm text-[var(--color-ink-soft)]">{{ $definition->description }}</p>
        @endif
    </div>

    {{-- Endpoints, each with its declared parameters and a tester --}}
    @forelse($definition->endpoints as $endpoint)
        @php
            $isResultEndpoint = $testEndpointId === $endpoint->getKey();
            $methodClass = match (strtoupper($endpoint->method)) {
                'GET' => 'bg-green-100 text-green-800',
                'POST' => 'bg-blue-100 text-blue-800',
                'PUT' => 'bg-yellow-100 text-yellow-800',
                'PATCH' => 'bg-orange-100 text-orange-800',
                'DELETE' => 'bg-red-100 text-red-800',
                default => 'bg-gray-100 text-gray-800',
            };
        @endphp

        <div class="bg-[var(--color-paper)] rounded-lg border hairline overflow-hidden">
            {{-- Endpoint header --}}
            <div class="p-6 border-b border-[var(--color-line)]">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full font-mono {{ $methodClass }}">{{ strtoupper($endpoint->method) }}</span>
                            <code class="text-sm font-mono text-[var(--color-ink)] break-all">{{ $baseUrl }}/{{ $endpoint->path }}</code>
                        </div>
                        @if($endpoint->description)
                            <p class="mt-2 text-sm text-[var(--color-steel)]">{{ $endpoint->description }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if(! $endpoint->is_active)
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-800">Inactive</span>
                        @endif
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $endpoint->requires_auth ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $endpoint->requires_auth ? 'Auth required' : 'Public' }}
                        </span>
                        <a href="{{ route('admin.api-management.endpoints.edit', [$definition->getKey(), $endpoint->getKey()]) }}"
                           class="py-1.5 px-3 rounded border hairline text-[var(--color-ink)] hover:bg-[var(--color-paper-dim)] transition text-xs">Edit</a>
                    </div>
                </div>

                {{-- Dispatch metadata --}}
                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <dt class="text-[var(--color-steel)] uppercase tracking-wider font-medium">Handler</dt>
                        <dd class="mt-0.5 font-mono text-[var(--color-ink-soft)] break-all">{{ $endpoint->action }}</dd>
                    </div>
                    <div>
                        <dt class="text-[var(--color-steel)] uppercase tracking-wider font-medium">Handler type</dt>
                        <dd class="mt-0.5 text-[var(--color-ink-soft)]">{{ $endpoint->handler_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-[var(--color-steel)] uppercase tracking-wider font-medium">Sort order</dt>
                        <dd class="mt-0.5 text-[var(--color-ink-soft)]">{{ $endpoint->sort_order }}</dd>
                    </div>
                </dl>

                {{-- Permission grants --}}
                @if($endpoint->permissions->isNotEmpty())
                    <div class="mt-4 pt-4 border-t border-[var(--color-line)]">
                        <span class="text-xs text-[var(--color-steel)] uppercase tracking-wider font-medium">Permissions</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($endpoint->permissions as $grant)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full
                                    {{ $grant->access_type === 'deny' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $grant->access_type }}: {{ $grant->permission?->name ?? 'missing permission #' . $grant->permission_id }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Declared parameters --}}
            <div class="p-6 border-b border-[var(--color-line)] bg-[var(--color-paper-dim)]/40">
                <h3 class="text-sm font-semibold text-[var(--color-ink)] mb-3">
                    Parameters <span class="text-[var(--color-steel)] font-normal">({{ $endpoint->parameters->count() }})</span>
                </h3>

                @if($endpoint->parameters->isEmpty())
                    <p class="text-sm text-[var(--color-steel)]">This endpoint takes no parameters.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-[var(--color-line)]">
                                    <th class="text-left py-2 pr-4 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Name</th>
                                    <th class="text-left py-2 pr-4 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">In</th>
                                    <th class="text-left py-2 pr-4 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Type</th>
                                    <th class="text-left py-2 pr-4 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Required</th>
                                    <th class="text-left py-2 pr-4 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Default</th>
                                    <th class="text-left py-2 text-xs font-medium text-[var(--color-steel)] uppercase tracking-wider">Rules</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($endpoint->parameters as $parameter)
                                    <tr class="border-b border-[var(--color-line)] last:border-0">
                                        <td class="py-2 pr-4 text-sm font-mono text-[var(--color-ink)]">{{ $parameter->name }}</td>
                                        <td class="py-2 pr-4 text-sm text-[var(--color-ink-soft)]">{{ $parameter->location }}</td>
                                        <td class="py-2 pr-4 text-sm text-[var(--color-ink-soft)]">{{ $parameter->type }}</td>
                                        <td class="py-2 pr-4 text-sm">
                                            @if($parameter->required)
                                                <span class="text-[var(--color-red)]">yes</span>
                                            @else
                                                <span class="text-[var(--color-steel)]">no</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-sm font-mono text-[var(--color-ink-soft)]">{{ $parameter->default_value ?? '—' }}</td>
                                        <td class="py-2 text-xs font-mono text-[var(--color-ink-soft)] break-all">
                                            {{ $parameter->validationRules() ? implode(' | ', $parameter->validationRules()) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Tester --}}
            <div class="p-6">
                <h3 class="text-sm font-semibold text-[var(--color-ink)] mb-1">Test this endpoint</h3>
                <p class="text-xs text-[var(--color-steel)] mb-4">
                    Sends a real request through the API pipeline — token check, parameter validation and permissions included.
                    Every run is recorded in the route log.
                </p>

                <form method="POST" action="{{ route('admin.api-management.endpoints.test', [$definition->getKey(), $endpoint->getKey()]) }}" class="space-y-4">
                    @csrf

                    @php
                        $pathParameters = $endpoint->parameters->where('location', 'path');
                        $wasTested = $isResultEndpoint && $testResult !== null;
                        $sentPath = $wasTested ? ($testResult['request']['path_params'] ?? []) : [];
                    @endphp

                    @if($pathParameters->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            @foreach($pathParameters as $parameter)
                                <div>
                                    <label for="path_{{ $endpoint->getKey() }}_{{ $parameter->name }}" class="block text-xs font-medium text-[var(--color-ink-soft)] mb-1">
                                        {{ $parameter->name }} <span class="text-[var(--color-steel)]">(path)</span>
                                    </label>
                                    <input type="text"
                                           id="path_{{ $endpoint->getKey() }}_{{ $parameter->name }}"
                                           name="path[{{ $parameter->name }}]"
                                           value="{{ old('path.'.$parameter->name, $sentPath[$parameter->name] ?? '') }}"
                                           placeholder="{{ $parameter->default_value ?: 'value' }}"
                                           class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:outline-none focus:border-[var(--color-ink)]">
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="query_{{ $endpoint->getKey() }}" class="block text-xs font-medium text-[var(--color-ink-soft)] mb-1">Query parameters</label>
                            <textarea id="query_{{ $endpoint->getKey() }}" name="query" rows="4" spellcheck="false"
                                      placeholder="per_page=5&#10;sort=price_asc"
                                      class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:outline-none focus:border-[var(--color-ink)]">{{ old('query') }}</textarea>
                            <p class="mt-1 text-xs text-[var(--color-steel)]">One <code>key=value</code> per line. A bare key counts as <code>1</code>.</p>
                        </div>

                        <div>
                            <label for="headers_{{ $endpoint->getKey() }}" class="block text-xs font-medium text-[var(--color-ink-soft)] mb-1">Headers</label>
                            <textarea id="headers_{{ $endpoint->getKey() }}" name="headers" rows="4" spellcheck="false"
                                      placeholder="X-Custom: value"
                                      class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:outline-none focus:border-[var(--color-ink)]">{{ old('headers') }}</textarea>
                            <p class="mt-1 text-xs text-[var(--color-steel)]">One <code>key: value</code> per line. Use this instead of the token field if your server strips <code>Authorization</code>.</p>
                        </div>
                    </div>

                    @if(in_array(strtoupper($endpoint->method), ['POST', 'PUT', 'PATCH'], true))
                        <div>
                            <label for="body_{{ $endpoint->getKey() }}" class="block text-xs font-medium text-[var(--color-ink-soft)] mb-1">Request body</label>
                            <textarea id="body_{{ $endpoint->getKey() }}" name="body" rows="4" spellcheck="false"
                                      placeholder='{"name": "example"}'
                                      class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:outline-none focus:border-[var(--color-ink)]">{{ old('body') }}</textarea>
                            <p class="mt-1 text-xs text-[var(--color-steel)]">Sent as JSON unless a <code>Content-Type</code> header overrides it.</p>
                        </div>
                    @endif

                    @if($endpoint->requires_auth)
                        <div>
                            <label for="token_{{ $endpoint->getKey() }}" class="block text-xs font-medium text-[var(--color-ink-soft)] mb-1">API token</label>
                            <input type="password" id="token_{{ $endpoint->getKey() }}" name="token" autocomplete="off"
                                   placeholder="Paste a bearer token for a user holding the permission above"
                                   class="w-full border hairline bg-[var(--color-paper)] px-3 py-2 text-sm font-mono focus:outline-none focus:border-[var(--color-ink)]">
                            <p class="mt-1 text-xs text-[var(--color-steel)]">
                                Sent as <code>Authorization: Bearer</code> and used for this run only — never stored.
                            </p>
                        </div>
                    @endif

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="bg-[var(--color-ink)] text-white px-4 py-2 rounded font-medium hover:opacity-90 transition text-sm">
                            Send {{ strtoupper($endpoint->method) }} request
                        </button>
                        <code class="text-xs text-[var(--color-steel)]">{{ $baseUrl }}/{{ $endpoint->path }}</code>
                    </div>
                </form>

                {{-- Result --}}
                @if($isResultEndpoint && $testResult !== null)
                    <div class="mt-5 pt-5 border-t border-[var(--color-line)]">
                        <h4 class="text-sm font-semibold text-[var(--color-ink)] mb-3">Response</h4>

                        @if($testResult['error'] !== null)
                            <div class="p-3 rounded-lg border border-red-300 bg-red-50 text-red-800 text-sm font-mono break-words">
                                {{ $testResult['error'] }}
                            </div>
                        @else
                            @php $status = $testResult['response']['status']; @endphp
                            <div class="flex items-center gap-3 mb-3 flex-wrap">
                                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                                    {{ $status < 300 ? 'bg-green-100 text-green-800' : ($status < 400 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $status }} {{ $testResult['response']['status_text'] }}
                                </span>
                                <span class="text-xs text-[var(--color-steel)]">{{ $testResult['response']['duration_ms'] }} ms</span>
                                @foreach($testResult['response']['headers'] as $name => $value)
                                    <span class="text-xs text-[var(--color-steel)] font-mono">{{ $name }}: {{ $value }}</span>
                                @endforeach
                            </div>

                            <pre class="bg-[var(--color-ink)] text-[var(--color-paper)] rounded-lg p-4 overflow-x-auto text-xs font-mono max-h-96"><code>{{ $testResult['response']['body'] ?: '(empty body)' }}</code></pre>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-[var(--color-paper)] rounded-lg border hairline p-12 text-center">
            <h3 class="text-lg font-medium text-[var(--color-ink)]">No endpoints yet</h3>
            <p class="mt-2 text-sm text-[var(--color-steel)]">Add an endpoint to expose a URL for this definition.</p>
            <a href="{{ route('admin.api-management.endpoints.create', $definition->getKey()) }}"
               class="mt-6 inline-flex items-center gap-2 bg-[var(--color-red)] text-white px-4 py-2 rounded font-medium hover:bg-[var(--color-red-dark)] transition text-sm">
                Add Endpoint
            </a>
        </div>
    @endforelse
</div>
@endsection