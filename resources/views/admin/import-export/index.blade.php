@extends('layouts.admin')
@section('title', 'Import & Export')
@section('header')
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-[var(--color-ink)]">Import & Export</h1>
        <p class="text-sm text-[var(--color-steel)] mt-1">Bulk import or export your products via CSV.</p>
    </div>
</div>
@endsection
@section('content')
<div class="space-y-6">

    {{-- Export --}}
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-1">Export Products</h2>
        <p class="text-xs text-[var(--color-steel)] mb-4">Download all products as a CSV file.</p>
        <a href="{{ route('admin.import-export.export') }}" class="inline-flex items-center gap-2 bg-[var(--color-red)] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
            <i class="fa-solid fa-download"></i> Export to CSV
        </a>
    </div>

    {{-- Import --}}
    <div class="bg-[var(--color-paper)] rounded-lg border p-6">
        <h2 class="font-semibold text-[var(--color-ink)] mb-1">Import Products</h2>
        <p class="text-xs text-[var(--color-steel)] mb-4">Upload a CSV file to create or update products. Use the sample file for the correct format.</p>

        <div class="mb-4">
            <a href="{{ route('admin.import-export.sample') }}" class="text-sm text-[var(--color-red)] hover:text-[var(--color-red-dark)] font-medium">
                <i class="fa-solid fa-file-csv mr-1"></i> Download Sample CSV
            </a>
        </div>

        @if(session('import_errors'))
            <div class="mb-4 bg-[var(--color-paper-dim)] border border-[var(--color-line)] rounded-lg p-4">
                <p class="text-sm font-medium text-[var(--color-red-dark)] mb-2">Errors:</p>
                <ul class="text-xs text-[var(--color-red)] list-disc list-inside space-y-1 max-h-40 overflow-y-auto">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.import-export.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="border-2 border-dashed border-[var(--color-line)] rounded-lg p-6 text-center hover:border-[var(--color-red)] transition">
                <i class="fa-solid fa-cloud-arrow-up text-3xl text-[var(--color-steel)] mb-2"></i>
                <p class="text-sm text-[var(--color-steel)] mb-2">Click to upload or drag & drop</p>
                <p class="text-xs text-[var(--color-steel)] mb-3">CSV file, max 10MB</p>
                <input type="file" name="csv_file" accept=".csv,txt" required class="block w-full text-sm text-[var(--color-steel)] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[var(--color-red)]/10 file:text-[var(--color-red)] hover:file:bg-[var(--color-red)]/20 cursor-pointer">
                @error('csv_file') <p class="mt-2 text-sm text-[var(--color-red)]">{{ $message }}</p> @enderror
            </div>

            <div class="bg-[var(--color-paper-dim)] rounded-lg p-4 text-xs text-[var(--color-steel)]">
                <p class="font-medium mb-1">CSV Format:</p>
                <p>Columns: <code class="bg-[var(--color-line)] px-1 rounded">name, slug, sku, short_description, description, base_price, cost_price, sale_price, stock_status, weight, is_active, is_featured, categories, brand, tags</code></p>
                <p class="mt-1">Categories and tags can be pipe-separated (e.g. <code class="bg-[var(--color-line)] px-1 rounded">Electronics|Gadgets</code>). Existing products are matched by slug or sku and updated.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-[var(--color-red)] text-white px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-[var(--color-red-dark)] transition">
                    <i class="fa-solid fa-upload mr-1"></i> Import CSV
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
