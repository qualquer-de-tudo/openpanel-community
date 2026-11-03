<?php

use App\Http\Middleware\AuthenticatePanelMcp;
use App\Mcp\Servers\PanelServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/panel', PanelServer::class)->middleware(['throttle:120,1', AuthenticatePanelMcp::class, 'throttle:panel-mcp']);
