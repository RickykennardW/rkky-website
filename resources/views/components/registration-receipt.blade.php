@props(['title', 'heading', 'variant' => 'success'])

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0B0F17">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ $title }} · Cloud Computing 2025</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="registration-page font-sans antialiased">
        <a class="skip-link" href="#registration-receipt">Skip to registration details</a>
        <div class="page-shell mx-auto flex min-h-svh w-full max-w-6xl flex-col px-5 sm:px-8 lg:px-12">
            <header class="site-header flex items-center justify-between gap-6">
                <div class="brand flex items-center gap-3.5" aria-label="Cloud Computing 2025">
                    <svg class="brand-mark shrink-0" width="34" height="38" viewBox="0 0 34 38" fill="none" aria-hidden="true">
                        <path d="m17 2 15 8.5v17L17 36 2 27.5v-17L17 2Z" stroke="currentColor" stroke-width="1.5"/>
                        <path d="m2 10.5 15 8.7 15-8.7M17 19.2V36M9.5 6.3l15 8.5v8.7L17 28l-7.5-4.5V15L17 10.5l7.5 4.3" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    </svg>
                    <div class="brand-name">CLOUD COMPUTING <span>/ 2025</span></div>
                </div>
                <span class="portal-label hidden items-center gap-2.5 sm:flex"><span class="portal-dot" aria-hidden="true"></span> Student registration portal</span>
            </header>

            <main class="receipt-layout flex flex-1 items-center justify-center">
                <section {{ $attributes->merge(['class' => 'registration-card receipt-card', 'id' => 'registration-receipt', 'aria-labelledby' => 'receipt-heading', 'tabindex' => '-1']) }}>
                    <div class="receipt-icon flex items-center justify-center" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">@if ($variant === 'invalid')<path d="M12 7v6m0 4h.01"/>@else<path d="m6 12 4 4 8-8"/>@endif</svg>
                    </div>
                    <p class="card-eyebrow">CLOUD COMPUTING 2025</p>
                    <h1 class="receipt-heading" id="receipt-heading">{{ $heading }}</h1>
                    {{ $slot }}
                </section>
            </main>

            <footer class="site-footer flex flex-wrap items-center justify-between gap-3">
                <p>Cloud Computing <span>2025</span></p>
                <p>Learn with purpose. Build with confidence.</p>
            </footer>
        </div>
    </body>
</html>
