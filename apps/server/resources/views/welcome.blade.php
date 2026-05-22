<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Wayfindr') }} Server</title>
        <style>
            :root {
                color-scheme: light dark;
                font-family:
                    Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                line-height: 1.5;
            }

            body {
                align-items: center;
                background: #f7f4ed;
                color: #16201d;
                display: flex;
                margin: 0;
                min-height: 100vh;
            }

            main {
                margin: 0 auto;
                max-width: 42rem;
                padding: 3rem 1.5rem;
            }

            p {
                color: #46524d;
                font-size: 1rem;
                margin: 0;
            }

            h1 {
                font-size: clamp(2rem, 5vw, 3.5rem);
                letter-spacing: 0;
                line-height: 1;
                margin: 0 0 1rem;
            }

            code {
                background: #e5ddd1;
                border-radius: 0.25rem;
                padding: 0.1rem 0.3rem;
            }

            @media (prefers-color-scheme: dark) {
                body {
                    background: #101614;
                    color: #edf4ef;
                }

                p {
                    color: #b5c2bc;
                }

                code {
                    background: #26312d;
                }
            }
        </style>
    </head>
    <body>
        <main>
            <h1>Wayfindr Server</h1>
            <p>
                Laravel core application scaffold is running. Use <code>/up</code> for a lightweight
                service check while the product surface is still pre-alpha.
            </p>
        </main>
    </body>
</html>
