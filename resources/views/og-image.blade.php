<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'Instrument Sans';
            font-style: normal;
            font-weight: 400;
            src: url('file://{{ public_path('fonts/instrument-sans-latin-400-normal.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Instrument Sans';
            font-style: normal;
            font-weight: 600;
            src: url('file://{{ public_path('fonts/instrument-sans-latin-600-normal.woff2') }}') format('woff2');
        }

        @font-face {
            font-family: 'Instrument Sans';
            font-style: normal;
            font-weight: 700;
            src: url('file://{{ public_path('fonts/instrument-sans-latin-700-normal.woff2') }}') format('woff2');
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            width: 1200px;
            height: 630px;
            font-family: 'Instrument Sans', sans-serif;
            background: #0c0c0f;
            color: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .bg-gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 50% 50%, rgba(39, 39, 42, 0.5) 0%, transparent 70%),
                radial-gradient(ellipse 40% 40% at 75% 25%, rgba(239, 68, 68, 0.06) 0%, transparent 60%),
                radial-gradient(ellipse 40% 40% at 25% 75%, rgba(239, 68, 68, 0.04) 0%, transparent 60%);
        }

        .grid-pattern {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .content {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
        }

        .logo {
            width: 100px;
            height: 105px;
            filter: drop-shadow(0 0 40px rgba(239, 68, 68, 0.15));
        }

        .title {
            font-size: 72px;
            font-weight: 700;
            letter-spacing: -2px;
            line-height: 1;
        }

        .subtitle {
            font-size: 28px;
            font-weight: 400;
            color: #a1a1aa;
            letter-spacing: 0.5px;
        }

        .games {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .game-badge {
            font-size: 14px;
            font-weight: 600;
            color: #71717a;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 6px 16px;
            border-radius: 999px;
        }

        .border-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, #ef4444, transparent);
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <div class="border-top"></div>
    <div class="bg-gradient"></div>
    <div class="grid-pattern"></div>

    <div class="content">
        <svg class="logo" viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path fill="#27272a" d="M6 1H34C36.76 1 39 3.24 39 6V22C39 31 29 38 20 42C11 38 1 31 1 22V6C1 3.24 3.24 1 6 1Z"/>
            <path fill="#ef4444" d="M20 26C16 22 9 17 9 12C9 9 11.5 7 14.5 7C17 7 19 9 20 11C21 9 23 7 25.5 7C28.5 7 31 9 31 12C31 17 24 22 20 26Z"/>
        </svg>

        <div class="title">Armaani</div>
        <div class="subtitle">Game Server Manager</div>

        <div class="games">
            <span class="game-badge">Arma 3</span>
            <span class="game-badge">Arma Reforger</span>
            <span class="game-badge">DayZ</span>
        </div>
    </div>
</body>
</html>
