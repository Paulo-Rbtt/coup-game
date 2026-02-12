<?php

namespace App\Http\Controllers;

use App\Services\NetworkService;
use Illuminate\Http\JsonResponse;

class NetworkController extends Controller
{
    public function __construct(private NetworkService $networkService) {}

    /**
     * GET /api/network/info — Get LAN IP addresses and server ports.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'ips' => $this->networkService->getLanIps(),
            'primary_ip' => $this->networkService->getPrimaryLanIp(),
            'server_port' => $this->networkService->getServerPort(),
            'ws_port' => $this->networkService->getWebSocketPort(),
        ]);
    }
}
