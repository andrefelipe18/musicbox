<!doctype html>
<html lang="en" data-theme="{{ $config->renderer()->get('theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->renderer()->get('theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|jetbrains-mono:400,500&display=swap">

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    @include('scramble::dev-tools', ['renderer' => 'elements'])

    <script>
        const originalFetch = window.fetch;

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                return originalFetch(url, {
                    ...options,
                    headers,
                });
            }

            return originalFetch(url, options);
        };
    </script>

    {{-- MusicBox console theme (DESIGN.md) mapped onto Stoplight Elements' design tokens. --}}
    <style>
        /* Tokens — MusicBox Midnight catalog console.
         * Mosaic resolves every color from CSS custom properties, so the whole
         * Elements surface is themed by overriding them. Scoped to
         * html[data-theme=dark] because Elements redeclares all of them at
         * [data-theme=dark] (specificity 0,1,0) further down its own stylesheet.
         */
        html[data-theme="dark"] {
            /* Surfaces — concentric violet-tinted charcoals, Void -> Carbon -> Graphite -> Slate */
            --canvas-h: 252; --canvas-s: 26%; --canvas-l: 3.7%;
            --color-canvas: #08070c;          /* Void — page canvas */
            --color-canvas-50: #1c1a25;      /* Slate — table headers, menus, hovered rows */
            --color-canvas-100: #15141c;     /* Graphite — cards, panels, widget bodies */
            --color-canvas-200: #0e0d13;     /* Carbon — shell, inputs, empty-state frames */
            --color-canvas-300: #08070c;     /* Void */
            --color-canvas-400: #08070c;
            --color-canvas-500: #08070c;
            --color-canvas-dark: #08070c;
            --color-canvas-pure: #08070c;
            --color-canvas-tint: rgba(167, 139, 250, 0.05);
            --color-canvas-dialog: #0e0d13;  /* Carbon — true overlays */

            /* Text — Ice primary, Fog secondary, Linen disabled */
            --text-h: 255; --text-s: 38%; --text-l: 94%;
            --color-text: #ece9f5;
            --color-text-heading: #ece9f5;
            --color-text-paragraph: #ece9f5;
            --color-text-muted: #8b8799;
            --color-text-light: #8b8799;
            --color-text-disabled: #6f6b80;

            /* Borders — Iron decorative, Linen when the border carries state */
            --color-border: #2a2835;
            --color-border-dark: #2a2835;
            --color-border-light: #6f6b80;
            --color-border-input: #6f6b80;
            --color-border-button: #6f6b80;

            /* Violet — 600 is the only fill, 400 is the only violet used as text */
            --primary-h: 262; --primary-s: 83%; --primary-l: 58%;
            --color-primary: #7c3aed;
            --color-primary-dark: #6d28d9;
            --color-primary-darker: #6d28d9;
            --color-primary-light: #a78bfa;
            --color-primary-tint: rgba(167, 139, 250, 0.18);
            --color-on-primary: #ece9f5;
            --color-text-primary: #a78bfa;
            --color-link: #a78bfa;
            --color-link-dark: #a78bfa;

            /* Status — Rose failed/destructive, Amber queued/stale.
             * Success is deliberately grey: green is absent from this system. */
            --danger-h: 351; --danger-s: 95%; --danger-l: 71%;
            --color-danger: #fb7185;
            --color-danger-dark: #fb7185;
            --color-danger-darker: #fb7185;
            --color-danger-light: #fda4af;
            --color-danger-tint: rgba(251, 113, 133, 0.18);
            --color-text-danger: #fb7185;
            --color-on-danger: #ece9f5;

            --warning-h: 43; --warning-s: 96%; --warning-l: 56%;
            --color-warning: #fbbf24;
            --color-warning-dark: #b45309;
            --color-warning-darker: #92400e;
            --color-warning-light: #fcd34d;
            --color-warning-tint: rgba(251, 191, 36, 0.18);
            --color-text-warning: #fbbf24;
            --color-on-warning: #08070c;

            --success-h: 253; --success-s: 8%; --success-l: 57%;
            --color-success: #8b8799;
            --color-success-dark: #6f6b80;
            --color-success-darker: #4a4759;
            --color-success-light: #ece9f5;
            --color-success-tint: rgba(139, 135, 153, 0.18);
            --color-text-success: #8b8799;
            --color-on-success: #08070c;

            /* Type — Instrument Sans for UI, JetBrains Mono for IDs, enums, code */
            --font-ui: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            --font-prose: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            --font-code: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            --fs-paragraph: 14px;
            --fs-paragraph-small: 13px;
            --fs-paragraph-tiny: 11px;
            --fs-paragraph-leading: 24px;
            --fs-code: 12px;
            --lh-paragraph: 1.6;
            --lh-paragraph-small: 1.45;
            --lh-paragraph-tiny: 1.2;
            --lh-paragraph-leading: 1.3;
            --lh-code: 1.5;

            /* Code surface */
            --color-code: #0e0d13;
            --color-on-code: #ece9f5;

            /* Elevation — no in-page shadows. Depth comes from the surface step
             * plus hairlines; only true overlays keep a drop shadow. */
            --shadow-sm: none;
            --shadow-md: none;
            --shadow-lg: none;
            --shadow-xl: none;
            --shadow-2xl: none;
        }

        html, body { margin:0; height:100%; }
        body {
            background-color: var(--color-canvas);
            font-family: var(--font-ui);
            -webkit-font-smoothing: antialiased;
        }

        /* Syntax colors inside the web-component code viewer.
         * issues about the dark theme of stoplight/mosaic-code-viewer using web component:
         * https://github.com/stoplightio/elements/issues/2188#issuecomment-1485461965
         */
        [data-theme="dark"] .token.property { color: #a78bfa !important; }
        [data-theme="dark"] .token.operator { color: #8b8799 !important; }
        [data-theme="dark"] .token.number { color: #fbbf24 !important; }
        [data-theme="dark"] .token.string { color: #ece9f5 !important; }
        [data-theme="dark"] .token.boolean { color: #a78bfa !important; }
        [data-theme="dark"] .token.punctuation { color: #6f6b80 !important; }

        /* Brand lockup — the logo stands alone at the top of the sidebar.
         * Elements always renders the API title beside the logo and pins the
         * image to 30x30 through HTML attributes, so the title is dropped and
         * the logo is scaled back up here. */
        elements-api img[src$="musicbox-logo.png"] {
            width: auto;
            height: 40px;
        }

        elements-api .sl-flex:has(> div > img[src$="musicbox-logo.png"]) > * ~ * {
            display: none;
        }
    </style>
</head>
<body style="height: 100vh; overflow-y: hidden">
<elements-api
    id="docs"
    @foreach($config->renderer()->all(except: ['theme']) as $key => $value)
        @continue(! $value)
        {{ $key }}="{{ $value === true ? 'true' : ($value === false ? 'false' : $value) }}"
    @endforeach
/>
<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($config->renderer()->get('theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif
</body>
</html>
