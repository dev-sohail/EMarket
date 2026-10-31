@extends('install.layout')

@section('title', 'Install ' . config('app.name', 'the application'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="card-icon purple">⚙</div>
            <h2>Installation Wizard</h2>
        </div>
        <p class="hint">Set up the database and create the first administrator account.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <span class="alert-icon">✕</span>
            <div class="alert-content">
                <strong>Some details need attention.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if (session('connection_test'))
        <div class="alert {{ session('connection_test.ok') ? 'alert-ok' : 'alert-error' }}">
            <span class="alert-icon">{{ session('connection_test.ok') ? '✓' : '✕' }}</span>
            <div class="alert-content">{{ session('connection_test.message') }}</div>
        </div>
    @endif

    @if (session('progress'))
        <div class="alert alert-ok">
            <span class="alert-icon">✓</span>
            <div class="alert-content">
                <strong>Completed so far:</strong>
                <ul class="progress-list">
                    @foreach (session('progress') as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-icon blue">✓</div>
            <h2>Requirements</h2>
        </div>
        <p class="hint">Everything here must pass before the installation can run.</p>
        <ul class="requirements-list">
            @foreach ($requirements as $requirement)
                <li>
                    <span class="check-mark {{ $requirement['ok'] ? 'pass' : 'fail' }}">
                        {{ $requirement['ok'] ? '✓' : '✕' }}
                    </span>
                    <span class="requirement-label">{{ $requirement['label'] }}</span>
                    <span class="requirement-detail">{{ $requirement['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <form method="POST" action="{{ route('install.store') }}">
        @csrf

        <div class="card">
            <div class="card-header">
                <div class="card-icon purple">🌐</div>
                <h2>Application</h2>
            </div>
            <p class="hint">Used for the site name and to build absolute URLs in emails.</p>

            <div class="form-grid">
                <div class="form-group">
                    <label for="app_name">Application name</label>
                    <input type="text" id="app_name" name="app_name" value="{{ old('app_name', config('app.name', 'Emarket')) }}"
                           required maxlength="120">
                </div>

                <div class="form-group">
                    <label for="app_url">Application URL</label>
                    <input type="url" id="app_url" name="app_url" value="{{ old('app_url', request()->getSchemeAndHttpHost()) }}"
                           required maxlength="255">
                    <span class="hint">No trailing slash. Used to build links in emails and the admin area.</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon green">🗄</div>
                <h2>Database</h2>
            </div>
            <p class="hint">
                A MySQL server must be reachable from this machine. The database is created for you if it
                does not exist, provided the credentials have permission to do so.
            </p>

            <div class="form-grid">
                <div class="form-group">
                    <label for="db_host">Host</label>
                    <input type="text" id="db_host" name="db_host" value="{{ old('db_host', '127.0.0.1') }}"
                           required maxlength="255" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="db_port">Port</label>
                    <input type="number" id="db_port" name="db_port" value="{{ old('db_port', '3306') }}"
                           required min="1" max="65535" autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="db_database">Database name</label>
                    <input type="text" id="db_database" name="db_database" value="{{ old('db_database', 'emarket_test') }}"
                           required maxlength="64" autocomplete="off">
                    <span class="hint">Letters, numbers and underscores only.</span>
                </div>

                <div class="form-group">
                    <label for="db_username">Username</label>
                    <input type="text" id="db_username" name="db_username" value="{{ old('db_username', 'root') }}"
                           required maxlength="128" autocomplete="off">
                </div>

                <div class="form-group full">
                    <label for="db_password">Password</label>
                    <input type="password" id="db_password" name="db_password"
                           required maxlength="255" autocomplete="new-password">
                    <span class="hint">Written to <code>.env</code> with owner-only permissions.</span>
                </div>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-secondary" formaction="{{ route('install.test-connection') }}">
                    Test connection
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon orange">👤</div>
                <h2>Administrator</h2>
            </div>
            <p class="hint">
                This account is created during installation. Its password is not stored in
                <code>.env</code> and cannot be recovered from this page afterwards.
            </p>

            <div class="form-grid">
                <div class="form-group full">
                    <label for="admin_email">Email address</label>
                    <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}"
                           required maxlength="255" autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="admin_password">Password</label>
                    <input type="password" id="admin_password" name="admin_password"
                           required minlength="12" maxlength="255" autocomplete="new-password">
                    <span class="hint">At least 12 characters.</span>
                </div>

                <div class="form-group">
                    <label for="admin_password_confirmation">Confirm password</label>
                    <input type="password" id="admin_password_confirmation" name="admin_password_confirmation"
                           required minlength="12" maxlength="255" autocomplete="new-password">
                </div>
            </div>
        </div>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Install</button>
            <span class="note">Writing .env, creating the database and running migrations.</span>
        </div>
    </form>
@endsection