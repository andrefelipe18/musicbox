@php
    $plugin = (function_exists('filament') && filament()->isServing()) ? \Leandrocfe\FilamentApexCharts\FilamentApexChartsPlugin::get() : null;
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $darkMode = $this->getDarkMode();
    $width = $this->getFilterFormWidth();
    $pollingInterval = $this->getPollingInterval();
    $chartId = $this->getChartId();
    $chartOptions = $this->getOptions();
    $loadingIndicator = $this->getLoadingIndicator();
    $contentHeight = $this->getContentHeight();
    $deferLoading = $this->getDeferLoading();
    $footer = $this->getFooter();
    $readyToLoad = $this->readyToLoad;
    $extraJsOptions = $this->extraJsOptions();
@endphp
<x-filament-widgets::widget class="fi-wi-chart filament-widgets-chart-widget filament-apex-charts-widget">
    {{-- Dashboard charts render without the section card: heading and chart only. --}}
    <div class="flex flex-col gap-3">
        @if ($heading)
            <h2 class="fi-wi-chart-heading text-sm font-semibold text-gray-950 dark:text-white">
                {{ $heading }}
            </h2>
        @endif

        @if ($subheading)
            <p class="fi-wi-chart-subheading text-sm text-gray-500 dark:text-gray-400">
                {{ $subheading }}
            </p>
        @endif

        <div x-data="{ dropdownOpen: false }" @apexhcharts-dropdown.window="dropdownOpen = $event.detail.open">

            @if ($filters || method_exists($this, 'getFiltersSchema'))
                <div class="fi-wi-chart-filters flex items-center gap-2">
                    @if ($filters)
                        <x-filament::input.wrapper
                            inline-prefix
                            wire:target="filter"
                            class="fi-wi-chart-filter"
                        >
                            <x-filament::input.select
                                inline-prefix
                                wire:model.live="filter"
                            >
                                @foreach ($filters as $value => $label)
                                    <option value="{{ $value }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    @endif

                    @if (method_exists($this, 'getFiltersSchema'))
                        <x-filament::dropdown
                            placement="bottom-end"
                            shift
                            width="xs"
                            class="fi-wi-chart-filter"
                        >
                            <x-slot name="trigger">
                                {{ $this->getFiltersTriggerAction() }}
                            </x-slot>

                            <div class="fi-wi-chart-filter-content">
                                {{ $this->getFiltersSchema() }}
                            </div>
                        </x-filament::dropdown>
                    @endif
                </div>
            @endif

            <x-filament-apex-charts::chart :$chartId :$chartOptions :$contentHeight :$pollingInterval :$loadingIndicator
                :$darkMode :$deferLoading :$readyToLoad :$extraJsOptions />

            @if ($footer)
                <div class="relative">
                    {!! $footer !!}
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>