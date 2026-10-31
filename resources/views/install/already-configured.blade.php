@extends('install.layout')

@section('title', 'Already Installed')

@section('content')
    <div class="content">
        <div class="card">
            <h2>Application Already Configured</h2>
            <p class="hint">This installer cannot run because an existing <code>.env</code> configuration file was detected. This is a safety feature to prevent overwriting an existing production site.</p>
            <p>If you need to reinstall the application, please delete the <code>.env</code> file and <code>storage/installed.lock</code> file (if it exists) and refresh this page.</p>
            <div class="actions">
                <a href="{{ url('/') }}" class="button-link secondary">Go to the homepage</a>
            </div>
        </div>
    </div>
@endsection