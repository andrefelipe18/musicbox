@php
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
    $pollingInterval = $this->getPollingInterval();
    $chartId = $this->getChartId();
    $chartOptions = $this->options;
    $loadingIndicator = $this->getLoadingIndicator();
    $contentHeight = $this->getContentHeight();
    $deferLoading = $this->getDeferLoading();
    $darkMode = $this->getDarkMode();
    $readyToLoad = $this->readyToLoad;
    $extraJsOptions = $this->extraJsOptions();
@endphp

<x-filament-widgets::widget class="fi-wi-chart filament-widgets-chart-widget filament-apex-charts-widget">
    <div class="flex w-full flex-col gap-3">
        @if ($heading)
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }}</h2>
        @endif

        @if ($subheading)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $subheading }}</p>
        @endif

        <div x-data="{ dropdownOpen: false }" @apexhcharts-dropdown.window="dropdownOpen = $event.detail.open">
            <x-filament-apex-charts::chart
                :$chartId
                :$chartOptions
                :$contentHeight
                :$pollingInterval
                :$loadingIndicator
                :$darkMode
                :$deferLoading
                :$readyToLoad
                :$extraJsOptions
            />
        </div>
    </div>
</x-filament-widgets::widget>
