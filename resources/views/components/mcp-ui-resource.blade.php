{{--
MCP-UI Resource Component

Renders a single UI resource as a sandboxed iframe.

Usage:
    <x-mcp-ui-resource :resource="$resource" />
    <x-mcp-ui-resource :resource="$resource" width="500px" height="300px" />
    <x-mcp-ui-resource :resource="$resource" :sandbox="'allow-scripts allow-forms allow-popups'" />

Props:
    - resource: The UI resource array from a tool response
    - width: CSS width (default from config or '100%')
    - height: CSS height (default from config or '400px')
    - sandbox: Iframe sandbox attribute (default from config)
    - class: Additional CSS classes
    - id: Custom element ID
--}}
@props([
    'resource',
    'width' => config('mcp.ui.default_width', '100%'),
    'height' => config('mcp.ui.default_height', '400px'),
    'sandbox' => config('mcp.ui.default_sandbox', 'allow-scripts allow-forms'),
    'class' => '',
    'id' => null,
])

@php
    use MCP\UI\UIResourceRenderer;
    use MCP\UI\UIResourceData;

    // Parse the resource if it's an array
    $parsed = is_array($resource) ? UIResourceData::fromArray($resource) : $resource;

    // Generate unique ID if not provided
    $elementId = $id ?? 'mcp-ui-' . md5($parsed->uri ?? uniqid());

    // Build options array
    $options = [
        'width' => $width,
        'height' => $height,
        'sandbox' => $sandbox,
        'id' => $elementId,
    ];

    if (!empty($class)) {
        $options['class'] = $class;
    }
@endphp

@if($parsed)
    <div class="mcp-ui-resource-wrapper {{ $class }}" id="{{ $elementId }}-wrapper">
        {!! UIResourceRenderer::renderIframe($resource, $options) !!}
    </div>
@else
    <div class="mcp-ui-resource-error">
        Unable to render UI resource: Invalid resource data
    </div>
@endif

