@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $renderHookScopes = $livewire->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="panel-login-layout">
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <main class="panel-login-card">
            <section class="panel-login-main">
                <div class="panel-login-form">
                    {{ $slot }}
                </div>

                <footer class="panel-login-footer">{{ filament()->getBrandName() }} · {{ now()->year }}</footer>
            </section>

            <aside class="panel-login-visual" aria-hidden="true">
                <div class="panel-login-visual__label">CATALOG / CONTROL</div>
                <svg viewBox="0 0 640 720" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                    <g class="panel-login-visual__contours" stroke="currentColor" stroke-width="1">
                        <path d="M-90 374C-62 233 35 141 164 121c111-17 164 39 256 23 118-20 200 32 223 128 25 106-70 139-117 190-49 53-62 135-180 174-121 40-231 8-302-58C-18 507-110 474-90 374Z" />
                        <path d="M-62 375C-37 251 48 168 166 150c101-15 157 34 249 19 102-17 174 29 195 112 21 92-63 122-107 168-45 48-56 119-160 153-107 35-204 7-268-51C-1 491-80 463-62 375Z" />
                        <path d="M-34 376C-12 269 61 194 168 179c91-13 151 29 241 15 87-13 149 26 167 97 18 77-55 104-97 146-40 42-50 102-139 132-93 30-177 7-233-44C17 475-50 450-34 376Z" />
                        <path d="M-6 377C13 287 74 221 170 208c80-11 144 23 233 11 72-10 124 22 139 81 15 63-47 86-87 124-36 36-44 85-118 110-79 26-150 7-198-36C35 459-21 438-6 377Z" />
                        <path d="M22 378C38 305 88 248 171 237c70-9 138 18 225 8 58-7 99 18 111 66 12 48-39 68-77 101-32 29-39 68-98 89-65 22-123 7-164-29C53 443 8 425 22 378Z" />
                        <path d="M50 379C63 323 101 275 173 266c59-7 131 12 218 5 43-4 74 14 83 50 9 33-31 49-67 77-27 22-33 51-78 68-50 18-97 7-129-21C71 427 37 413 50 379Z" />
                        <path d="M80 379C91 342 116 304 175 296c49-6 124 5 209 1 29-2 49 10 55 33 6 19-23 32-56 54-23 15-28 34-59 47-36 14-71 7-95-13-24-20-39-29-92-39" />
                    </g>
                    <circle cx="330" cy="365" r="8" stroke="currentColor" stroke-dasharray="2 5" />
                    <circle cx="330" cy="365" r="46" stroke="currentColor" stroke-dasharray="3 8" />
                    <circle cx="330" cy="365" r="108" stroke="currentColor" stroke-dasharray="2 8" />
                    <circle cx="330" cy="365" r="174" stroke="currentColor" stroke-dasharray="2 9" />
                </svg>
                <div class="panel-login-visual__caption">
                    <span>01 — CATALOG</span>
                    <span>Records, syncs, and releases</span>
                </div>
            </aside>
        </main>

        {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
