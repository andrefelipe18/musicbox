<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#08070c">
        <meta name="description" content="{{ __('app.landing.meta_description') }}">

        <title>{{ __('app.landing.title') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="musicbox-landing">
        <a class="skip-link" href="#conteudo">{{ __('app.landing.skip_to_content') }}</a>

        <div class="landing-shell">
            <header class="landing-header">
                <a class="landing-brand" href="{{ route('home') }}" aria-label="{{ __('app.landing.home_label') }}">
                    <img src="{{ asset('musicbox-logo.png') }}" alt="{{ __('app.landing.brand') }}" width="180" height="54">
                </a>

                <nav class="landing-nav" aria-label="{{ __('app.landing.navigation.label') }}">
                    <a class="nav-section" href="#recursos">{{ __('app.landing.navigation.about') }}</a>
                    <a class="nav-section" href="#como-funciona">{{ __('app.landing.navigation.how_it_works') }}</a>
                </nav>
            </header>

            <main id="conteudo">
                <section class="landing-hero" aria-labelledby="hero-title">
                    <div class="hero-copy">
                        <p class="landing-eyebrow">{{ __('app.landing.hero.eyebrow') }}</p>
                        <h1 id="hero-title">{{ __('app.landing.hero.heading') }}<br><span>{{ __('app.landing.hero.heading_accent') }}</span></h1>
                        <p class="hero-description">{{ __('app.landing.hero.description') }}</p>
                        <a class="landing-button" href="#recursos">
                            {{ __('app.landing.hero.action') }}
                            <x-phosphor-arrow-down-right aria-hidden="true" />
                        </a>
                    </div>

                    <figure class="hero-art">
                        <img src="{{ asset('musicbox-records.jpg') }}" alt="{{ __('app.landing.hero.image_alt') }}" width="1254" height="1254" fetchpriority="high">
                        <figcaption>{{ __('app.landing.hero.image_caption') }}</figcaption>
                    </figure>
                </section>

                <section id="recursos" class="landing-features" aria-labelledby="features-title">
                    <div class="features-intro">
                        <x-phosphor-vinyl-record-duotone class="section-icon" aria-hidden="true" />
                        <h2 id="features-title">{{ __('app.landing.features.heading') }}<br>{{ __('app.landing.features.heading_accent') }}</h2>
                        <p>{{ __('app.landing.features.description') }}</p>
                    </div>

                    <div class="feature-list">
                        <article class="feature-item">
                            <x-phosphor-stack-duotone aria-hidden="true" />
                            <div>
                                <h3>{{ __('app.landing.features.catalog.heading') }}</h3>
                                <p>{{ __('app.landing.features.catalog.description') }}</p>
                            </div>
                        </article>
                        <article class="feature-item">
                            <x-phosphor-headphones-duotone aria-hidden="true" />
                            <div>
                                <h3>{{ __('app.landing.features.listening.heading') }}</h3>
                                <p>{{ __('app.landing.features.listening.description') }}</p>
                            </div>
                        </article>
                        <article class="feature-item">
                            <x-phosphor-star-duotone aria-hidden="true" />
                            <div>
                                <h3>{{ __('app.landing.features.reviews.heading') }}</h3>
                                <p>{{ __('app.landing.features.reviews.description') }}</p>
                            </div>
                        </article>
                    </div>
                </section>

                <section id="como-funciona" class="landing-workflow" aria-labelledby="workflow-title">
                    <h2 id="workflow-title">{{ __('app.landing.workflow.heading') }}</h2>
                    <p>{{ __('app.landing.workflow.description') }}</p>
                    <ol class="workflow-list">
                        <li>
                            <x-phosphor-magnifying-glass-duotone aria-hidden="true" />
                            <h3>{{ __('app.landing.workflow.discover.heading') }}</h3>
                            <p>{{ __('app.landing.workflow.discover.description') }}</p>
                        </li>
                        <li>
                            <x-phosphor-plus-circle-duotone aria-hidden="true" />
                            <h3>{{ __('app.landing.workflow.collect.heading') }}</h3>
                            <p>{{ __('app.landing.workflow.collect.description') }}</p>
                        </li>
                        <li>
                            <x-phosphor-heart-duotone aria-hidden="true" />
                            <h3>{{ __('app.landing.workflow.personalize.heading') }}</h3>
                            <p>{{ __('app.landing.workflow.personalize.description') }}</p>
                        </li>
                    </ol>
                </section>
            </main>

            <footer class="landing-footer">
                <a href="{{ route('home') }}" aria-label="{{ __('app.landing.home_label') }}">
                    <img src="{{ asset('musicbox-logo.png') }}" alt="{{ __('app.landing.brand') }}" width="120" height="36" loading="lazy">
                </a>
                <p>{{ __('app.landing.footer.tagline') }}</p>
                <span>{{ __('app.landing.footer.copyright', ['year' => now()->year]) }}</span>
            </footer>
        </div>
    </body>
</html>
