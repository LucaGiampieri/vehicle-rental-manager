<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DashboardRequest;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    //Restituisce il riepilogo economico e operativo.
    public function index(
        DashboardRequest $request,
        DashboardService $dashboardService
    ): JsonResponse {
        //Recupera soltanto i filtri che hanno superato la validazione.
        $filters = $request->validated();

        //La data iniziale parte dalle ore 00:00:00.
        $dateFrom = Carbon::createFromFormat(
            'Y-m-d',
            $filters['date_from']
        )->startOfDay();

        //La data finale comprende tutta la giornata fino alle 23:59:59.
        $dateTo = Carbon::createFromFormat(
            'Y-m-d',
            $filters['date_to']
        )->endOfDay();

        //Il filtro del veicolo rimane null quando non viene utilizzato.
        $vehicleId = isset($filters['vehicle_id'])
            ? (int) $filters['vehicle_id']
            : null;

        $dashboard = $dashboardService->build(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            vehicleId: $vehicleId
        );

        return response()->json([
            'data' => $dashboard,
        ]);
    }
}
