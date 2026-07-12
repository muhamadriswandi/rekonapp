<?php

use App\Mcp\Servers\RekonServer;
use Laravel\Mcp\Facades\Mcp;

// Web server - accessible via HTTP POST at /mcp/rekon
Mcp::web('/mcp/rekon', RekonServer::class);

// Local server - for AI IDE (Laravel Boost) integration
Mcp::local('rekon', RekonServer::class);
