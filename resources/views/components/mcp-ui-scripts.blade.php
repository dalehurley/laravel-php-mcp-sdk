{{--
MCP-UI Scripts Component

Includes the action handler JavaScript for MCP-UI widget communication.

Usage:
    {{-- Before closing </body> tag --}}
    <x-mcp-ui-scripts />

    {{-- With custom action endpoint --}}
    <x-mcp-ui-scripts :endpoint="route('mcp.ui.action')" />

    {{-- With custom callback functions --}}
    <x-mcp-ui-scripts 
        :onSuccess="'handleSuccess'" 
        :onError="'handleError'" 
    />

Props:
    - endpoint: The UI action endpoint URL (default from config/route)
    - onSuccess: Name of the global success callback function
    - onError: Name of the global error callback function
    - debug: Enable debug logging (default from config)
--}}
@props([
    'endpoint' => null,
    'onSuccess' => null,
    'onError' => null,
    'debug' => config('mcp.ui.debug', false),
])

@php
    use MCP\UI\UIResourceRenderer;

    // Determine the action endpoint
    $actionEndpoint = $endpoint;
    if (!$actionEndpoint) {
        try {
            $actionEndpoint = route('mcp.ui.action');
        } catch (\Exception $e) {
            $actionEndpoint = config('mcp.ui.action_endpoint', '/mcp/ui-action');
        }
    }
@endphp

<script>
/**
 * MCP-UI Action Handler
 * 
 * Handles postMessage communication from MCP-UI widgets embedded in iframes.
 */
(function() {
    'use strict';

    const MCP_UI_CONFIG = {
        endpoint: @json($actionEndpoint),
        debug: @json($debug),
        onSuccess: @json($onSuccess),
        onError: @json($onError),
        csrfToken: @json(csrf_token()),
    };

    /**
     * Log debug messages
     */
    function debug(...args) {
        if (MCP_UI_CONFIG.debug) {
            console.log('[MCP-UI]', ...args);
        }
    }

    /**
     * Handle actions from MCP-UI widgets
     */
    async function handleAction(action) {
        debug('Received action:', action);

        try {
            const response = await fetch(MCP_UI_CONFIG.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': MCP_UI_CONFIG.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    type: action.type,
                    payload: action.payload || {},
                    server: action.server || null,
                    widget_uri: action.widgetUri || null,
                }),
            });

            const result = await response.json();
            debug('Action result:', result);

            // Call success callback if defined
            if (result.success) {
                if (MCP_UI_CONFIG.onSuccess && typeof window[MCP_UI_CONFIG.onSuccess] === 'function') {
                    window[MCP_UI_CONFIG.onSuccess](result, action);
                }

                // Dispatch custom event
                window.dispatchEvent(new CustomEvent('mcp-ui:action-success', {
                    detail: { action, result }
                }));
            } else {
                throw new Error(result.error?.message || 'Action failed');
            }

            return result;
        } catch (error) {
            debug('Action error:', error);

            // Call error callback if defined
            if (MCP_UI_CONFIG.onError && typeof window[MCP_UI_CONFIG.onError] === 'function') {
                window[MCP_UI_CONFIG.onError](error, action);
            }

            // Dispatch custom event
            window.dispatchEvent(new CustomEvent('mcp-ui:action-error', {
                detail: { action, error }
            }));

            throw error;
        }
    }

    /**
     * Handle postMessage events from iframes
     */
    function handleMessage(event) {
        // Validate message structure
        if (!event.data || typeof event.data !== 'object') {
            return;
        }

        // Check if this is an MCP-UI action
        if (!event.data.mcpAction && !event.data.type) {
            return;
        }

        debug('Received postMessage:', event.data);

        // Handle the action
        const action = {
            type: event.data.type || event.data.mcpAction,
            payload: event.data.payload || event.data,
            server: event.data.server,
            widgetUri: event.data.widgetUri || event.data.uri,
        };

        // Handle link actions specially - open in new tab
        if (action.type === 'link' && action.payload.url) {
            const target = action.payload.target || '_blank';
            window.open(action.payload.url, target);
            return;
        }

        // Process other actions through the server
        handleAction(action)
            .then(result => {
                // Send response back to iframe if we can identify it
                if (event.source) {
                    event.source.postMessage({
                        mcpResponse: true,
                        success: true,
                        result: result,
                    }, '*');
                }
            })
            .catch(error => {
                if (event.source) {
                    event.source.postMessage({
                        mcpResponse: true,
                        success: false,
                        error: error.message,
                    }, '*');
                }
            });
    }

    // Listen for postMessage events from iframes
    window.addEventListener('message', handleMessage);

    // Expose API for direct usage
    window.McpUI = {
        handleAction,
        config: MCP_UI_CONFIG,
    };

    debug('MCP-UI handler initialized', MCP_UI_CONFIG);
})();
</script>

{!! UIResourceRenderer::actionHandlerScript() !!}

