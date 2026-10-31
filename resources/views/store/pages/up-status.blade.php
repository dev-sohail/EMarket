@extends('layouts.store')

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-12">
        <!-- Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full mb-6 {{ $allOperational ? 'bg-green-100' : 'bg-red-100' }}">
                <i class="fa-solid {{ $allOperational ? 'fa-check-circle text-green-600' : 'fa-exclamation-triangle text-red-600' }} text-4xl"></i>
            </div>
            <h1 class="text-4xl font-bold text-[var(--color-ink)] mb-3">System Status</h1>
            <p class="text-lg text-[var(--color-steel)]">
                {{ $allOperational ? 'All systems are operational' : 'Some systems are experiencing issues' }}
            </p>
            <p class="text-sm text-[var(--color-steel)] mt-2">Last updated: {{ now()->format('F j, Y H:i:s') }}</p>
        </div>

        <!-- Core Services Section -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold text-[var(--color-ink)] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-server text-[var(--color-red)]"></i>
                Core Services
            </h2>
            <div class="bg-white rounded-xl border border-[var(--color-line)] shadow-sm overflow-hidden">
                <div class="divide-y divide-[var(--color-line)]">
                    @foreach(['database', 'cache', 'storage'] as $key)
                        @if(isset($statuses[$key]))
                            <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center {{ $statuses[$key]['status'] === 'operational' ? 'bg-green-100' : 'bg-red-100' }}">
                                        <i class="fa-solid {{ $statuses[$key]['icon'] }} {{ $statuses[$key]['status'] === 'operational' ? 'text-green-600' : 'text-red-600' }}"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-[var(--color-ink)]">{{ $statuses[$key]['name'] }}</p>
                                        <p class="text-sm text-[var(--color-steel)]">{{ $statuses[$key]['message'] }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium {{ $statuses[$key]['status'] === 'operational' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($statuses[$key]['status']) }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold text-[var(--color-ink)] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-puzzle-piece text-[var(--color-red)]"></i>
                Features
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($statuses as $key => $status)
                    @if(in_array($status['status'], ['enabled', 'disabled']) && in_array($key, array_keys(['returns_enabled' => true, 'reviews_enabled' => true, 'affiliate_enabled' => true, 'wishlist_enabled' => true, 'compare_enabled' => true, 'stock_notifications_enabled' => true, 'newsletter_enabled' => true, 'coupons_enabled' => true, 'flash_sales_enabled' => true, 'b2b_enabled' => true])))
                        <div class="bg-white rounded-xl border border-[var(--color-line)] p-5 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center {{ $status['status'] === 'enabled' ? 'bg-green-100' : 'bg-gray-100' }}">
                                    <i class="fa-solid {{ $status['icon'] }} {{ $status['status'] === 'enabled' ? 'text-green-600' : 'text-gray-500' }} text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-[var(--color-ink)]">{{ $status['name'] }}</p>
                                    <p class="text-sm text-[var(--color-steel)]">{{ $status['message'] }}</p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $status['status'] === 'enabled' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($status['status']) }}
                                </span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Integrations Section -->
        <div class="mb-10">
            <h2 class="text-2xl font-bold text-[var(--color-ink)] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-plug text-[var(--color-red)]"></i>
                Integrations
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($statuses as $key => $status)
                    @if(in_array($status['status'], ['enabled', 'disabled']) && in_array($key, array_keys(['payment_stripe' => true, 'payment_paypal' => true, 'shipping_royal_mail' => true, 'shipping_dhl' => true, 'tax_eu' => true])))
                        <div class="bg-white rounded-xl border border-[var(--color-line)] p-5 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg flex items-center justify-center {{ $status['status'] === 'enabled' ? 'bg-blue-100' : 'bg-gray-100' }}">
                                    <i class="fa-solid {{ $status['icon'] }} {{ $status['status'] === 'enabled' ? 'text-blue-600' : 'text-gray-500' }} text-lg"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-[var(--color-ink)]">{{ $status['name'] }}</p>
                                    <p class="text-sm text-[var(--color-steel)]">{{ $status['message'] }}</p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-xs font-medium {{ $status['status'] === 'enabled' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($status['status']) }}
                                </span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- System Information -->
        <div>
            <h2 class="text-2xl font-bold text-[var(--color-ink)] mb-6 flex items-center gap-3">
                <i class="fa-solid fa-info-circle text-[var(--color-red)]"></i>
                System Information
            </h2>
            <div class="bg-white rounded-xl border border-[var(--color-line)] shadow-sm overflow-hidden">
                <div class="divide-y divide-[var(--color-line)]">
                    <div class="px-6 py-3 flex justify-between hover:bg-gray-50">
                        <span class="text-[var(--color-steel)]">PHP Version</span>
                        <span class="font-mono text-sm text-[var(--color-ink)]">{{ $systemInfo['php_version'] }}</span>
                    </div>
                    <div class="px-6 py-3 flex justify-between hover:bg-gray-50">
                        <span class="text-[var(--color-steel)]">Laravel Version</span>
                        <span class="font-mono text-sm text-[var(--color-ink)]">{{ $systemInfo['laravel_version'] }}</span>
                    </div>
                    <div class="px-6 py-3 flex justify-between hover:bg-gray-50">
                        <span class="text-[var(--color-steel)]">Application URL</span>
                        <span class="font-mono text-sm text-[var(--color-ink)]">{{ $systemInfo['app_url'] }}</span>
                    </div>
                    <div class="px-6 py-3 flex justify-between hover:bg-gray-50">
                        <span class="text-[var(--color-steel)]">Environment</span>
                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $systemInfo['app_env'] === 'production' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($systemInfo['app_env']) }}
                        </span>
                    </div>
                    <div class="px-6 py-3 flex justify-between hover:bg-gray-50">
                        <span class="text-[var(--color-steel)]">Debug Mode</span>
                        <span class="font-mono text-sm text-[var(--color-ink)]">{{ $systemInfo['app_debug'] }}</span>
                    </div>
                    <div class="px-6 py-3 flex justify-between hover:bg-gray-50">
                        <span class="text-[var(--color-steel)]">Server Software</span>
                        <span class="font-mono text-sm text-[var(--color-ink)]">{{ $systemInfo['server_software'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-center text-sm text-[var(--color-steel)]">
            <p>This page automatically refreshes every 5 minutes</p>
        </div>
    </div>

    <!-- Auto-refresh script -->
    <script nonce="{{ $cspNonce }}">
        setTimeout(function() {
            window.location.reload();
        }, 300000); // Refresh every 5 minutes
    </script>
@endsection