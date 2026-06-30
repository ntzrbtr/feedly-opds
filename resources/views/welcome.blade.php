<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            :root {
                color-scheme: light dark;
                --bg: #fafaf9;
                --bg-dark: #18181b;
                --text: #1c1917;
                --text-dark: #e7e5e4;
                --text-muted: #78716c;
                --text-muted-dark: #a8a29e;
                --accent: #f97316;
                --accent-bg: #fff7ed;
                --accent-bg-dark: rgba(249, 115, 22, 0.15);
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: var(--bg-dark);
                    --text: var(--text-dark);
                    --text-muted: var(--text-muted-dark);
                }
            }
            body {
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                background: var(--bg);
                color: var(--text);
                line-height: 1.6;
                margin: 0;
                min-height: 100vh;
            }
            main {
                max-width: 42rem;
                margin: 0 auto;
                padding: 4rem 1.5rem;
            }
            header { text-align: center; margin-bottom: 3rem; }
            .icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 4rem;
                height: 4rem;
                margin-bottom: 1rem;
                border-radius: 9999px;
                background: var(--accent-bg);
                color: var(--accent);
            }
            @media (prefers-color-scheme: dark) {
                .icon { background: var(--accent-bg-dark); }
            }
            .icon svg {
                width: 2rem;
                height: 2rem;
                color: var(--accent);
            }
            h1 {
                font-size: 1.875rem;
                font-weight: 600;
                letter-spacing: -0.025em;
                margin: 0;
            }
            h2 {
                font-size: 1.25rem;
                font-weight: 600;
                margin-top: 2rem;
                margin-bottom: 0.75rem;
            }
            p { margin: 0 0 1rem; }
            ul { padding-left: 1.5rem; margin: 0 0 1rem; }
            li { margin-bottom: 0.25rem; }
            code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                background: #f5f5f4;
                padding: 0.125rem 0.375rem;
                border-radius: 0.25rem;
                font-size: 0.875em;
            }
            @media (prefers-color-scheme: dark) {
                code { background: #27272a; }
            }
        </style>
    </head>
    <body>
        <main>
            <header>
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" style="fill: currentColor;"><!--!Font Awesome Free v7.3.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.--><path d="M320 205.3L320 514.6L320.5 514.4C375.1 491.7 433.7 480 492.8 480L512 480L512 160L492.8 160C450.6 160 408.7 168.4 369.7 184.6C352.9 191.6 336.3 198.5 320 205.3zM294.9 125.5L320 136L345.1 125.5C391.9 106 442.1 96 492.8 96L528 96C554.5 96 576 117.5 576 144L576 496C576 522.5 554.5 544 528 544L492.8 544C442.1 544 391.9 554 345.1 573.5L332.3 578.8C324.4 582.1 315.6 582.1 307.7 578.8L294.9 573.5C248.1 554 197.9 544 147.2 544L112 544C85.5 544 64 522.5 64 496L64 144C64 117.5 85.5 96 112 96L147.2 96C197.9 96 248.1 106 294.9 125.5z"/></svg>
                </div>
                <h1>{{ config('app.name') }}</h1>
            </header>

            <p>
                This server exposes your saved articles from Feedly's
                &ldquo;Read Later&rdquo; feed as an OPDS catalog, so you can
                read them on the go with any OPDS-compatible reader
                (e.g., KOReader, Marvin). Full-text content is fetched via
                the Instaparser API.
            </p>

            <h2>Endpoints</h2>
            <ul>
                <li><code>/opds</code> &ndash; Root catalog</li>
                <li><code>/opds/saved</code> &ndash; List of saved articles</li>
                <li><code>/opds/entry/{id}</code> &ndash; Article detail with full text</li>
                <li><code>/opds/download/{id}</code> &ndash; HTML download</li>
            </ul>

            <h2>Access</h2>
            <p>
                The OPDS feed is protected by a static token. You can authenticate
                using HTTP Basic Auth (<code>Authorization: Basic
                &lt;base64(username:token)&gt;</code>; the username is ignored)
                or as a query parameter (<code>?token=...</code>).
            </p>

            <h2>Configuration</h2>
            <p>
                All settings are managed via the <code>.env</code> file.
                See <code>.env.example</code> for available options.
            </p>
        </main>
    </body>
</html>
