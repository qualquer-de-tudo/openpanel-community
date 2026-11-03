<?php

use Laravel\Mcp\Facades\Mcp;
use QOpenPanel\Community\Http\Middleware\AuthenticatePanelMcp;
use QOpenPanel\Community\Http\Middleware\SetLocale;
use QOpenPanel\Community\Mcp\Servers\PanelServer;

Mcp::web('/'.trim(config('qopenpanel.path'), '/').'/mcp/panel', PanelServer::class)->middleware([SetLocale::class, 'throttle:120,1', AuthenticatePanelMcp::class, 'throttle:panel-mcp']);
