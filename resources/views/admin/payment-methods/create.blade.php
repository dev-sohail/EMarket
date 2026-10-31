@extends('layouts.admin')

@section('title', 'Payment Methods')

@section('header')
<h1 class="text-2xl font-bold text-[var(--color-ink)]">Payment Methods</h1>
@endsection

@section('content')
<div class="bg-[var(--color-paper)] rounded-lg border p-6 max-w-2xl space-y-4">
    <p class="text-sm text-[var(--color-steel)]">Only COD and Online are available. Additional payment methods cannot be added.</p>
    <p class="text-sm text-[var(--color-steel)]">Online records the customer's payment selection and instructions; it does not charge a payment gateway.</p>
    <a href="{{ route('admin.payment-methods.index') }}" class="inline-flex px-4 py-2 bg-[var(--color-paper-dim)] text-[var(--color-ink)] text-sm font-medium rounded hover:bg-[var(--color-line)] transition">Back to Payment Methods</a>
</div>
@endsection
