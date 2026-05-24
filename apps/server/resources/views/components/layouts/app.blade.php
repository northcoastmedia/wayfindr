<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Wayfindr') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f7f7f3;
            --surface: #ffffff;
            --surface-muted: #f1f5f4;
            --text: #1d2523;
            --muted: #62706b;
            --border: #d8dfdc;
            --accent: #0d6f68;
            --accent-strong: #094f4b;
            --danger: #9d1c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        a {
            color: var(--accent-strong);
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        .shell {
            min-height: 100vh;
        }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .topbar-inner,
        .page {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
        }

        .topbar-inner {
            min-height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            font-weight: 700;
        }

        .page {
            padding: 32px 0;
        }

        .auth-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .panel {
            width: min(100%, 420px);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 28px;
        }

        .panel h1,
        .page h1 {
            margin: 0;
            font-size: 1.75rem;
            line-height: 1.2;
        }

        .lede {
            margin: 8px 0 0;
            color: var(--muted);
        }

        .field {
            margin-top: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 650;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 11px 12px;
            background: #ffffff;
            color: var(--text);
        }

        .field textarea {
            min-height: 132px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: 3px solid color-mix(in srgb, var(--accent) 22%, transparent);
            border-color: var(--accent);
        }

        .field-error {
            margin: 6px 0 0;
            color: var(--danger);
            font-size: 0.9rem;
        }

        .field-help {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            color: var(--muted);
        }

        .check-row input {
            width: 16px;
            height: 16px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 0 16px;
            background: var(--accent);
            color: #ffffff;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .button:hover {
            background: var(--accent-strong);
        }

        .button.secondary {
            background: transparent;
            color: var(--text);
            border-color: var(--border);
        }

        .button.secondary:hover {
            background: var(--surface-muted);
        }

        .button.full {
            width: 100%;
            margin-top: 22px;
        }

        .text-link {
            color: var(--accent-strong);
            font-weight: 700;
            text-decoration: none;
        }

        .text-link:hover {
            text-decoration: underline;
        }

        .section {
            margin-top: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            margin: 0;
            font-size: 1rem;
        }

        .section-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-end;
        }

        .section-actions .lede {
            margin-top: 0;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            white-space: nowrap;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        th {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .empty {
            padding: 20px;
            color: var(--muted);
        }

        .status-message {
            margin: 20px 0 0;
            color: var(--accent-strong);
            font-weight: 700;
        }

        .section-form {
            padding: 20px;
        }

        .section-form .field:first-child {
            margin-top: 0;
        }

        .section-form .button {
            margin-top: 16px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            background: var(--border);
            border-top: 1px solid var(--border);
        }

        .meta-item {
            background: var(--surface);
            padding: 16px 20px;
        }

        .meta-label {
            color: var(--muted);
            display: block;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .meta-value {
            display: block;
            font-weight: 700;
            margin-top: 4px;
            overflow-wrap: anywhere;
        }

        .realtime-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }

        .realtime-note {
            border-top: 1px solid var(--border);
            margin: 0;
        }

        .notice-copy {
            padding: 20px;
            color: var(--muted);
        }

        .notice-copy p {
            margin: 0;
        }

        .notice-copy p + p {
            margin-top: 8px;
        }

        .notice-list {
            display: grid;
            gap: 8px;
            margin-top: 16px;
            color: var(--muted);
        }

        .notice-list p {
            margin: 0;
        }

        code {
            background: var(--surface-muted);
            border: 1px solid var(--border);
            border-radius: 4px;
            color: var(--text);
            padding: 1px 4px;
        }

        .code-block {
            margin: 0;
            overflow-x: auto;
            padding: 18px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface-muted);
            color: var(--text);
            font-size: 0.88rem;
            line-height: 1.55;
            white-space: pre;
        }

        .code-block code {
            display: block;
            padding: 0;
            border: 0;
            background: transparent;
        }

        .cobrowse-preview-frame {
            background: var(--surface-muted);
            border-top: 1px solid var(--border);
            padding: 16px;
        }

        .cobrowse-preview {
            display: block;
            width: 100%;
            min-height: 360px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: #ffffff;
        }

        .live-update {
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 20px;
        }

        .live-update strong {
            display: block;
        }

        .live-update .lede {
            margin-top: 2px;
        }

        .live-update[data-state="available"] {
            background: var(--surface-muted);
        }

        [hidden] {
            display: none !important;
        }

        .message-list {
            display: grid;
            gap: 14px;
            padding: 20px;
        }

        .message {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
        }

        .message.agent {
            background: var(--surface-muted);
        }

        .message-meta {
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            font-size: 0.85rem;
            gap: 8px;
            justify-content: space-between;
        }

        .message-body {
            margin: 10px 0 0;
            white-space: pre-wrap;
        }

        @media (max-width: 640px) {
            .topbar-inner {
                align-items: flex-start;
                flex-direction: column;
                padding: 16px 0;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .section-actions {
                justify-content: flex-start;
            }

            .meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    {{ $slot }}
</body>
</html>
