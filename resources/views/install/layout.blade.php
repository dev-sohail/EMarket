{{-- Standalone by necessity.

     The installer cannot use layouts/store or layouts/admin: both call setting()
     (a database read), @fonts (database-backed) and @vite (needs a built
     manifest that does not exist before installation). This layout is therefore
     entirely self-contained — inline styles, no JavaScript at all.

     No JavaScript is the point, not a limitation. A single-page installer would
     need an inline <script>, which conflicts with the CSP this application sets
     in SecurityHeadersMiddleware, and every bit of client-side logic would be
     logic the server does not have to agree with. Plain form posts mean the
     browser can submit nothing the server did not validate. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/font-awesome.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Installation')</title>
    <style>
        :root {
            color-scheme: light dark;
            --brand: #000000;
            --brand-hover: #000000;
            --brand-light: #f0f0f0;
            --ink: #000000;
            --ink-soft: #333333;
            --muted: #666666;
            --line: #cccccc;
            --bg: #ffffff;
            --card: #ffffff;
            --ok: #000000;
            --ok-bg: #f0f0f0;
            --bad: #000000;
            --bad-bg: #f0f0f0;
            --shadow: none;
            --radius: 0;
            --transition: none;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            padding: 0;
        }

        /* Header */
        .header {
            background: var(--card);
            border-bottom: 1px solid var(--line);
            padding: 2.5rem 1rem;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .header-inner {
            max-width: 800px;
            margin: 0 auto;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .header p {
            color: var(--muted);
            font-size: 1rem;
            margin: 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--brand-light);
            color: var(--brand);
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }

        .badge::before {
            content: "⚡";
            font-size: 1rem;
        }

        /* Main container */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem 4rem;
        }

        /* Cards */
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-left: 4px solid var(--brand);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .card-icon.blue {
            background: var(--brand-light);
            color: var(--brand);
        }

        .card-icon.green {
            background: var(--ok-bg);
            color: var(--ok);
        }

        .card-icon.orange {
            background: var(--bad-bg);
            color: var(--bad);
        }

        .card-icon.purple {
            background: var(--brand-light);
            color: var(--brand);
        }

        .card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--ink);
            margin: 0;
        }

        .card > .hint {
            color: var(--muted);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        /* Forms */
        .form-grid {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--ink-soft);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        label .required {
            color: var(--bad);
            font-weight: 400;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="url"],
        input[type="number"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            font-size: 1rem;
            font-family: inherit;
            background: var(--card);
            color: var(--ink);
            transition: all 0.2s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand);
        }

        input:disabled {
            background: var(--bg);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .hint {
            font-size: 0.8rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .hint::before {
            content: "ℹ";
            font-size: 0.9rem;
            opacity: 0.7;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: var(--brand);
            color: var(--card);
        }

        .btn-primary:hover {
            background: var(--brand-hover);
            box-shadow: var(--shadow);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--card);
            color: var(--ink-soft);
            border: 1px solid var(--line);
        }

        .btn-secondary:hover {
            background: var(--bg);
            border-color: var(--muted);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--line);
        }

        .actions .note {
            margin-left: auto;
            font-size: 0.875rem;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .actions .note::before {
            content: "⚠";
            font-size: 0.9rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            border: 1px solid;
        }

        .alert-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .alert-error {
            background: var(--bad-bg);
            color: var(--bad);
            border-color: var(--bad-bg);
        }

        .alert-ok {
            background: var(--ok-bg);
            color: var(--ok);
            border-color: var(--ok-bg);
        }

        .alert-info {
            background: var(--brand-light);
            color: var(--brand);
            border-color: var(--brand-light);
        }

        .alert-content strong {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .alert-content ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.5rem;
        }

        .alert-content li {
            margin-bottom: 0.25rem;
        }

        /* Requirements */
        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirements-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--line);
            transition: background 0.15s ease;
        }

        .requirements-list li:last-child {
            border-bottom: none;
        }

        .requirements-list li:hover {
            background: var(--bg);
        }

        .check-mark {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .check-mark.pass {
            background: var(--ok-bg);
            color: var(--ok);
        }

        .check-mark.fail {
            background: var(--bad-bg);
            color: var(--bad);
        }

        .requirement-label {
            flex: 1;
            font-weight: 500;
            color: var(--ink);
        }

        .requirement-detail {
            font-size: 0.875rem;
            color: var(--muted);
            text-align: right;
        }

        /* Progress list */
        .progress-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0 0;
        }

        .progress-list li {
            padding: 0.4rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .progress-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--ok);
            font-weight: 700;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .header {
                padding: 1.5rem 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .container {
                padding: 1.5rem 1rem 3rem;
            }

            .card {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .actions .note {
                margin-left: 0;
                justify-content: center;
            }
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            body {
                background: var(--bg);
                color: var(--ink);
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <div class="header-inner">
                <div class="badge">Setup Wizard</div>
                <h1>@yield('title', 'Installation')</h1>
                <p>Get your E-com application up and running in just a few steps.</p>
            </div>
        </div>

        <div class="container">
            @yield('content')
        </div>
    </div>
</body>
</html>
