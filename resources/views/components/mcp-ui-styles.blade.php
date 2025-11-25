{{--
MCP-UI Styles Component

Includes default CSS styles for MCP-UI components.

Usage:
    {{-- In your <head> section --}}
    <x-mcp-ui-styles />

    {{-- Or with inline styles --}}
    <x-mcp-ui-styles inline />

Props:
    - inline: Whether to output styles inline (default: false, outputs in <style> tag)
--}}
@props([
    'inline' => false,
])

@php
    use MCP\UI\UIResourceRenderer;

    $styles = UIResourceRenderer::styles();
@endphp

@if($inline)
{!! $styles !!}
@else
<style>
{!! $styles !!}

/* Additional Laravel-specific styles */
.mcp-ui-resource-wrapper {
    position: relative;
    width: 100%;
}

.mcp-ui-resource-error {
    padding: 1rem;
    background: #fee2e2;
    border: 1px solid #ef4444;
    border-radius: 0.375rem;
    color: #dc2626;
    font-size: 0.875rem;
}

.mcp-ui-grid-empty {
    padding: 2rem;
    text-align: center;
    background: #f3f4f6;
    border: 1px dashed #d1d5db;
    border-radius: 0.375rem;
    color: #6b7280;
}

.mcp-ui-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: #f9fafb;
    border-radius: 0.375rem;
}

.mcp-ui-loading-spinner {
    width: 2rem;
    height: 2rem;
    border: 3px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: mcp-ui-spin 1s linear infinite;
}

@keyframes mcp-ui-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .mcp-ui-resource-error {
        background: #450a0a;
        border-color: #991b1b;
        color: #fca5a5;
    }

    .mcp-ui-grid-empty {
        background: #1f2937;
        border-color: #374151;
        color: #9ca3af;
    }

    .mcp-ui-loading {
        background: #1f2937;
    }

    .mcp-ui-loading-spinner {
        border-color: #374151;
        border-top-color: #60a5fa;
    }
}
</style>
@endif

