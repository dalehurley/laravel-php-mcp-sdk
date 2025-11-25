{{--
MCP-UI Grid Component

Renders multiple UI resources in a responsive grid layout.

Usage:
    <x-mcp-ui-grid :resources="$resources" />
    <x-mcp-ui-grid :resources="$resources" columns="3" gap="20px" />
    <x-mcp-ui-grid :resources="$resources" :options="['width' => '100%', 'height' => '300px']" />

Props:
    - resources: Array of UI resource arrays from a tool response
    - columns: Number of columns in the grid (default: 2)
    - gap: CSS gap between items (default: '16px')
    - options: Options to pass to each iframe renderer
    - class: Additional CSS classes for the grid container
--}}
@props([
    'resources',
    'columns' => 2,
    'gap' => '16px',
    'options' => [],
    'class' => '',
])

@php
    use MCP\UI\UIResourceRenderer;
    use MCP\UI\UIResourceParser;

    // Filter to only UI resources if we have a mixed response
    $uiResources = UIResourceParser::getUIResourcesOnly($resources);
@endphp

@if(count($uiResources) > 0)
    <div 
        class="mcp-ui-grid {{ $class }}" 
        style="
            display: grid; 
            grid-template-columns: repeat({{ $columns }}, 1fr); 
            gap: {{ $gap }};
        "
    >
        @foreach($uiResources as $index => $resource)
            <div class="mcp-ui-grid-item" data-index="{{ $index }}">
                {!! UIResourceRenderer::renderIframe($resource, array_merge($options, ['id' => 'mcp-ui-grid-' . $index])) !!}
            </div>
        @endforeach
    </div>
@else
    <div class="mcp-ui-grid-empty">
        No UI resources to display
    </div>
@endif

