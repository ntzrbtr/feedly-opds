<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Feedly OPDS') }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <style>
            :root { color-scheme: light dark; }
            body {
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
                max-width: 42rem;
                margin: 3rem auto;
                padding: 0 1.25rem;
                line-height: 1.6;
                color: #1b1b18;
                background: #fdfdfc;
            }
            @media (prefers-color-scheme: dark) {
                body { color: #ededec; background: #0a0a0a; }
                a { color: #8ab4ff; }
                code { background: #1f1f1f; }
            }
            code {
                background: #f1f0ee;
                padding: 0.1rem 0.35rem;
                border-radius: 0.25rem;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
            }
        </style>
    </head>
    <body>
        <header>
            <h1>{{ config('app.name', 'Feedly OPDS') }}</h1>
        </header>

        <main>
            <p>
                Dieser Server stellt die gespeicherten Artikel aus deinem
                Feedly-&bdquo;Read Later&ldquo;-Feed als OPDS-Katalog bereit,
                damit du sie in OPDS-fähigen Readern (z. B. KOReader, Marvin)
                auch unterwegs lesen kannst. Der Volltext der Original-Artikel
                wird über die Instaparser-API bezogen.
            </p>

            <h2>Endpunkte</h2>
            <ul>
                <li><code>/opds</code> &ndash; Root-Katalog</li>
                <li><code>/opds/saved</code> &ndash; Liste aller gespeicherten Artikel</li>
                <li><code>/opds/entry/{id}</code> &ndash; Detail mit Volltext</li>
                <li><code>/opds/download/{id}</code> &ndash; HTML-Download</li>
            </ul>

            <h2>Zugriff</h2>
            <p>
                Der OPDS-Feed ist durch ein statisches Token geschützt. Du kannst
                es entweder per HTTP Basic Auth (<code>Authorization: Basic
                &lt;base64(benutzername:token)&gt;</code>; der Benutzername
                wird ignoriert) oder als Query-Parameter
                (<code>?token=...</code>) übergeben.
            </p>

            <h2>Konfiguration</h2>
            <p>
                Jegliche Einstellungen erfolgen über die <code>.env</code>-Datei.
                Eine Übersicht findest du in <code>.env.example</code>.
            </p>
        </main>
    </body>
</html>
