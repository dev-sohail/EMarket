@php use Illuminate\Support\Str; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel') - {{ config('app.name') }}</title>
    @if(setting('favicon'))
        @php $faviconExt = pathinfo(setting('favicon'), PATHINFO_EXTENSION); @endphp
        <link rel="icon" type="{{ in_array($faviconExt, ['png','jpg','jpeg']) ? 'image/' . ($faviconExt === 'jpg' ? 'jpeg' : $faviconExt) : 'image/x-icon' }}" href="{{ asset('storage/' . setting('favicon')) }}">
    @endif
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3.17.4/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.min.js"></script>
    @stack('styles')
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="font-sans antialiased bg-[var(--color-paper)] text-[var(--color-ink)]">
    <div class="flex h-screen overflow-hidden">
        {{-- Sidebar --}}
        @include('components.admin.sidebar')

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col overflow-hidden">
            {{-- Top Bar --}}
            <header class="bg-white border-b hairline px-6 py-3 flex items-center justify-between flex-shrink-0">
                <button id="sidebar-toggle" class="p-2 hover:bg-[var(--color-paper-dim)] rounded-lg lg:hidden">
                    <i class="fa-solid fa-bars text-xl text-[var(--color-ink)]"></i>
                </button>
                <div class="flex items-center gap-4 ml-auto">
                    {{-- Notifications --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="p-2 hover:bg-[var(--color-paper-dim)] rounded-full relative">
                            <i class="fas fa-bell text-[var(--color-steel)]"></i>
                            @php
                                $unreadCount = auth()->guard('admin')->user() ? auth()->guard('admin')->user()->unreadNotifications->count() : 0;
                            @endphp
                            @if($unreadCount > 0)
                                <span class="absolute top-1 right-1 w-5 h-5 bg-[var(--color-red)] text-white text-xs rounded-full flex items-center justify-center">{{ $unreadCount }}</span>
                            @endif
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-80 bg-white rounded-lg border hairline py-1 z-50">
                            <div class="px-4 py-2 border-b hairline">
                                <h4 class="font-display font-semibold text-sm text-[var(--color-ink)]">Notifications</h4>
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                @php
                                    $notifications = auth()->guard('admin')->user() ? auth()->guard('admin')->user()->notifications()->latest()->limit(10)->get() : collect();
                                @endphp
                                @forelse($notifications as $notification)
                                    @php
                                        $data = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
                                        $title = $data['title'] ?? 'Notification';
                                        $message = $data['message'] ?? '';
                                        $time = \Carbon\Carbon::parse($notification->created_at)->diffForHumans();
                                    @endphp
                                    <a href="{{ $data['url'] ?? '#' }}" class="block px-4 py-3 text-sm hover:bg-[var(--color-paper-dim)] {{ is_null($notification->read_at) ? 'bg-[var(--color-paper-dim)] border-l-2 border-[var(--color-red)]' : '' }}">
                                        <div class="font-medium text-[var(--color-ink)]">{{ $title }}</div>
                                        <div class="text-[var(--color-steel)] text-xs mt-1">{{ Str::limit($message, 80) }}</div>
                                        <div class="text-xs text-[var(--color-steel)] mt-1 opacity-60">{{ $time }}</div>
                                    </a>
                                @empty
                                    <div class="px-4 py-3 text-sm text-[var(--color-steel)]">No notifications.</div>
                                @endforelse
                            </div>
                            @if($notifications->isNotEmpty())
                                <div class="px-4 py-2 border-t hairline">
                                    <form method="POST" action="{{ route('admin.notifications.mark-read') }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-[var(--color-red)] hover:text-[var(--color-red-dark)]">Mark all as read</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if(session()->has('impersonator_id'))
                        <div class="relative">
                            <form method="POST" action="{{ route('admin.customers.stop-impersonating') }}">
                                @csrf
                                <button type="submit" class="text-xs bg-[var(--color-paper-dim)] text-[var(--color-ink-soft)] px-3 py-1 rounded-full hover:bg-[var(--color-line)] transition flex items-center gap-1">
                                    <span class="w-2 h-2 bg-[var(--color-red)] rounded-full animate-pulse"></span>
                                    Impersonating Customer
                                </button>
                            </form>
                        </div>
                    @endif
                    {{-- User Dropdown --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 p-2 hover:bg-[var(--color-paper-dim)] rounded-lg">
                            <div class="w-8 h-8 bg-[var(--color-red)] rounded-full flex items-center justify-center text-white font-medium text-sm">
                                {{ substr(auth()->guard('admin')->user()?->name ?? 'A', 0, 1) }}
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-[var(--color-ink)]">{{ auth()->guard('admin')->user()?->name ?? 'Admin' }}</span>
                            <i class="fa-solid fa-chevron-down text-base text-[var(--color-steel)]"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg border hairline py-1 z-50">
                            <a href="{{ route('home') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)] text-[var(--color-ink)]" target="_blank">Goto Store</a>
                            <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)] text-[var(--color-ink)]">Dashboard</a>
                            <a href="{{ route('admin.settings.index') }}" class="block px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)] text-[var(--color-ink)]">Settings</a>
                            <hr class="my-1 border-[var(--color-line)]">
                                <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--color-paper-dim)] text-[var(--color-red)]">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Page Content --}}
            <main class="flex-1 overflow-y-auto p-6 bg-[var(--color-paper)]">
                {{-- Page Header --}}
                @hasSection('header')
                    @yield('header')
                @endif

                {{-- Flash and validation messages --}}
                @include('components.admin.alerts')

                @yield('content')
            </main>
        </div>
    </div>

     <script nonce="{{ $cspNonce }}">
          document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
              const sidebar = document.getElementById('admin-sidebar');
              sidebar.classList.toggle('-translate-x-full');
          });
      </script>
 
      {{-- CKEditor 4 holds the text you type in its own widget, not in the
           <textarea> it replaced. Unless that text is copied back into the
           textarea, the POST carries no value for the field and the form reports
           it missing — the operator sees "Product description is required" for a
           description sitting in the editor in front of them.

           ckeditorSync() attaches that copy-back to an editor instance directly.
           Pages call it as:

               ckeditorSync(CKEDITOR.replace('description'));

           It hooks CKEditor's own `change` event (the signal its built-in
           autoUpdateElement uses), syncs once on `afterInit` so an existing
           record's content reaches the textarea, and syncs again on the form's
           submit as a backstop for any path that bypasses `change`.

           The instance is passed in rather than discovered via
           CKEDITOR.on('instanceReady') so nothing depends on the relative order
           of DOMContentLoaded listeners: the hook is always in place by the time
           the editor exists. --}}
      <script nonce="{{ $cspNonce }}">
          /**
           * Let CKEditor's editing iframe finish booting under our CSP.
           *
           * The wysiwygarea plugin builds the editing area by writing an HTML
           * document into a same-origin <iframe>, and that document ends with an
           * inline bootstrap script:
           *
           *     <\script>var wasLoaded=0;function onload(){if(!wasLoaded)
           *         window.parent.CKEDITOR && window.parent.CKEDITOR.tools
           *         .callFunction(<frameLoadedHandler>,window);wasLoaded=1;}
           *         document.addEventListener("DOMContentLoaded",onload,false);
           *     <\/script>
           *
           * An about:blank iframe inherits the parent document's CSP, and our
           * script-src has no 'unsafe-inline' (only a per-request nonce that
           * CKEditor cannot know), so that script is blocked. Without it the
           * handler never runs, `contentDom`/`instanceReady` never fire, the
           * instance stays at status "loaded", `ckeditorSync()`'s hook never
           * sees a `change` event, and the textarea posts empty — the operator
           * gets "Product description is required" for text they can see in the
           * editor.
           *
           * The id is minted per install (CKEDITOR.tools.addFunction), so no CSP
           * hash can ever name this script: it has to be allowed by hand. The
           * iframe is same-origin, so we wait for its document ourselves and make
           * the very same call. `CKEDITOR.tools.callFunction` deletes the
           * function it invokes, so this is a no-op when the inline script was
           * allowed to run and the editor is left untouched in that case.
           */
          function ckeditorUnblockEditArea(editor) {
              // Inline editors edit the page itself and have no iframe to boot.
              if (!editor || editor.elementMode === CKEDITOR.ELEMENT_MODE_INLINE) {
                  return;
              }

              var attempts = 0;

              (function waitForEditArea() {
                  // Already finished by its own means — nothing to do.
                  if (editor.status === 'ready') {
                      return;
                  }

                  var frame = editor.container ? editor.container.findOne('iframe.cke_wysiwyg_frame') : null;
                  var doc = (frame && frame.$) ? frame.$.contentDocument : null;
                  var editable = typeof editor.editable === 'function' ? editor.editable() : null;
                  var handlerId = (editable && editable._) ? editable._.frameLoadedHandler : null;

                  if (handlerId && doc && doc.body && doc.readyState === 'complete') {
                      // Same call, same window argument, as the blocked script.
                      CKEDITOR.tools.callFunction(handlerId, frame.$.contentWindow);

                      return;
                  }

                  // Give up quietly: if the editing area has not appeared after
                  // five seconds something else is wrong and this shim has no
                  // business throwing over it.
                  if (++attempts < 100) {
                      setTimeout(waitForEditArea, 50);
                  }
              })();
          }

          document.addEventListener('DOMContentLoaded', function () {
              // ckeditor.js is loaded by the page, i.e. after this block has
              // already run, so the global can only be touched from here.
              if (!window.CKEDITOR || !CKEDITOR.on) {
                  return;
              }

              // Registered on the layout rather than in each page's script so a
              // screen that creates an editor can never forget it.
              CKEDITOR.on('instanceCreated', function (event) {
                  ckeditorUnblockEditArea(event.editor);
              });
          });

          window.ckeditorSync = function (editor) {
              if (!editor || !editor.updateElement) {
                  return;
              }

              var sync = function () {
                  editor.updateElement();
              };

              editor.on('afterInit', sync);
              editor.on('change', sync);

              var field = editor.element;
              var form = (field && field.$) ? field.$.closest('form') : null;

              if (form) {
                  form.addEventListener('submit', sync);
              }

              sync();
          };
       </script>
    @stack('scripts')
</body>
</html>