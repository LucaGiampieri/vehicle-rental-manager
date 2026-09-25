<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;

class DashboardService
{
    // Costruisce tutti i dati riassuntivi della dashboard.
    public function build(
        Carbon $dateFrom,
        Carbon $dateTo,
        ?int $vehicleId = null
    ): array {
        // I noleggi economici appartengono al periodo della loro data iniziale.
        $periodRentals = Rental::query()
            ->when(
                $vehicleId !== null,
                fn ($query) => $query->where('vehicle_id', $vehicleId)
            )
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->get();

        // I noleggi annullati non producono ricavi.
        $revenueRentals = $periodRentals->reject(
            fn (Rental $rental) => $rental->status === Rental::STATUS_CANCELLED
        );

        $completedRentals = $revenueRentals->where(
            'status',
            Rental::STATUS_COMPLETED
        );

        $contractedRevenue = $this->money(
            $revenueRentals->sum('total_amount')
        );

        $completedRevenue = $this->money(
            $completedRentals->sum('total_amount')
        );

        $amountCollected = $this->money(
            $revenueRentals->sum('amount_paid')
        );

        $amountOutstanding = $this->money(
            $revenueRentals->sum(
                fn (Rental $rental) => max(
                    (float) $rental->total_amount
                    - (float) $rental->amount_paid,
                    0
                )
            )
        );

        // Recupera le spese sostenute nel periodo selezionato.
        $periodExpenses = Expense::query()
            ->with('vehicle')
            ->when(
                $vehicleId !== null,
                fn ($query) => $query->where('vehicle_id', $vehicleId)
            )
            ->whereBetween('expense_date', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->get();

        $totalExpenses = $this->money(
            $periodExpenses->sum('amount')
        );

        // Raggruppa le spese per manutenzione, carburante e altre categorie.
        $expenseBreakdown = $periodExpenses
            ->groupBy('category')
            ->sortKeys()
            ->map(
                fn ($expenses, string $category) => [
                    'category' => $category,
                    'count' => $expenses->count(),
                    'total' => $this->money(
                        $expenses->sum('amount')
                    ),
                ]
            )
            ->values()
            ->all();

        $vehicles = Vehicle::query()
            ->when(
                $vehicleId !== null,
                fn ($query) => $query->whereKey($vehicleId)
            )
            ->get();

        $vehicleIds = $vehicles->pluck('id');

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'days' => $this->periodDays($dateFrom, $dateTo),
            ],
            'filters' => [
                'vehicle_id' => $vehicleId,
            ],
            'financial' => [
                // Valore di tutti i noleggi non annullati iniziati nel periodo.
                'contracted_revenue' => $contractedRevenue,

                // Valore dei soli noleggi completati.
                'completed_revenue' => $completedRevenue,
                'amount_collected' => $amountCollected,
                'amount_outstanding' => $amountOutstanding,
                'total_expenses' => $totalExpenses,

                // Utile previsto considerando tutti i contratti non annullati.
                'projected_profit' => $this->money(
                    $contractedRevenue - $totalExpenses
                ),

                // Utile maturato considerando soltanto i noleggi completati.
                'realized_profit' => $this->money(
                    $completedRevenue - $totalExpenses
                ),

                // Differenza tra denaro incassato e spese sostenute.
                'cash_balance' => $this->money(
                    $amountCollected - $totalExpenses
                ),
                'expenses_by_category' => $expenseBreakdown,

                // Ultime spese del periodo con il veicolo interessato.
                'recent_expenses' => $this->recentExpenseSummary(
                    $periodExpenses
                ),
            ],
            'rentals' => $this->rentalSummary($periodRentals),

            // Prenotazioni future, indipendenti dal periodo selezionato.
            'upcoming_reservations' => $this->upcomingReservationSummary(
                $vehicleId
            ),

            // Elenca i noleggi attualmente in corso con mezzo e cliente
            'active_rentals' => $this->activeRentalSummary(
                $vehicleId
            ),

            'fleet' => $this->fleetSummary($vehicles, $vehicleIds),
            'garage' => $this->garageSummary(),
            'utilization' => $this->utilizationSummary(
                $dateFrom,
                $dateTo,
                $vehicles,
                $vehicleId
            ),
            'deadlines' => $this->deadlineSummary($vehicleId),
        ];
    }

    // Conta i noleggi del periodo dividendoli per stato.
    private function rentalSummary($rentals): array
    {
        return [
            'total' => $rentals->count(),
            'reserved' => $rentals
                ->where('status', Rental::STATUS_RESERVED)
                ->count(),
            'active' => $rentals
                ->where('status', Rental::STATUS_ACTIVE)
                ->count(),
            'completed' => $rentals
                ->where('status', Rental::STATUS_COMPLETED)
                ->count(),
            'cancelled' => $rentals
                ->where('status', Rental::STATUS_CANCELLED)
                ->count(),
        ];
    }

    // Restituisce i dettagli dei noleggi attualmente in corso
    private function activeRentalSummary(
        ?int $vehicleId
    ): array {
        return Rental::query()
            // Evita richieste aggiuntive per ogni mezzo e cliente
            ->with([
                'vehicle',
                'customer',
            ])

            // Applica il filtro quando viene analizzato un solo veicolo
            ->when(
                $vehicleId !== null,
                fn ($query) => $query->where(
                    'vehicle_id',
                    $vehicleId
                )
            )

            // Recupera soltanto i noleggi realmente attivi
            ->where(
                'status',
                Rental::STATUS_ACTIVE
            )

            // Mostra prima i noleggi con rientro più vicino
            ->orderBy('expected_ends_at')
            ->get()
            ->map(function (Rental $rental): array {
                $customerName = trim(
                    ($rental->customer?->first_name ?? '')
                    .' '
                    .($rental->customer?->last_name ?? '')
                );

                return [
                    'rental_id' => $rental->id,
                    'vehicle_id' => $rental->vehicle_id,
                    'license_plate' => $rental
                        ->vehicle
                        ?->license_plate,
                    'brand' => $rental
                        ->vehicle
                        ?->brand,
                    'model' => $rental
                        ->vehicle
                        ?->model,
                    'customer_id' => $rental->customer_id,
                    'customer_name' => $customerName !== ''
                        ? $customerName
                        : null,
                    'actual_starts_at' => $rental
                        ->actual_starts_at
                        ?->toISOString(),
                    'expected_ends_at' => $rental
                        ->expected_ends_at
                        ?->toISOString(),
                    'total_amount' => $this->money(
                        $rental->total_amount
                    ),
                    'amount_paid' => $this->money(
                        $rental->amount_paid
                    ),
                ];
            })
            ->values()
            ->all();
    }

    // Elenca le prossime prenotazioni non ancora iniziate.
    private function upcomingReservationSummary(
        ?int $vehicleId
    ): array {
        $reservations = Rental::query()
            ->with([
                'vehicle',
                'customer',
            ])
            ->when(
                $vehicleId !== null,
                fn ($query) => $query->where(
                    'vehicle_id',
                    $vehicleId
                )
            )
            ->where('status', Rental::STATUS_RESERVED)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        return [
            'total' => $reservations->count(),
            'items' => $reservations
                ->take(10)
                ->map(function (Rental $rental): array {
                    $customerName = trim(
                        ($rental->customer?->first_name ?? '')
                        .' '
                        .($rental->customer?->last_name ?? '')
                    );

                    return [
                        'rental_id' => $rental->id,
                        'vehicle_id' => $rental->vehicle_id,
                        'license_plate' => $rental
                            ->vehicle
                            ?->license_plate,
                        'brand' => $rental->vehicle?->brand,
                        'model' => $rental->vehicle?->model,
                        'customer_id' => $rental->customer_id,
                        'customer_name' => $customerName !== ''
                            ? $customerName
                            : null,
                        'starts_at' => $rental
                            ->starts_at
                            ?->toISOString(),
                        'expected_ends_at' => $rental
                            ->expected_ends_at
                            ?->toISOString(),
                        'total_amount' => $this->money(
                            $rental->total_amount
                        ),
                        'amount_paid' => $this->money(
                            $rental->amount_paid
                        ),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    // Restituisce le spese più recenti comprese nel periodo selezionato.
    private function recentExpenseSummary($expenses): array
    {
        return $expenses
            ->sortByDesc(
                fn (Expense $expense) => sprintf(
                    '%s-%010d',
                    $expense->expense_date->format('Y-m-d'),
                    $expense->id
                )
            )
            ->take(8)
            ->map(fn (Expense $expense): array => [
                'expense_id' => $expense->id,
                'vehicle_id' => $expense->vehicle_id,
                'license_plate' => $expense->vehicle?->license_plate,
                'brand' => $expense->vehicle?->brand,
                'model' => $expense->vehicle?->model,
                'category' => $expense->category,
                'description' => $expense->description,
                'amount' => $this->money($expense->amount),
                'expense_date' => $expense
                    ->expense_date
                    ->toDateString(),
                'expires_on' => $expense
                    ->expires_on
                    ?->toDateString(),
            ])
            ->values()
            ->all();
    }

    // Calcola la situazione attuale dei veicoli selezionati.
    private function fleetSummary($vehicles, $vehicleIds): array
    {
        $activeVehicles = $vehicles
            ->where('is_active', true)
            ->count();

        $rentedVehicles = Rental::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('status', Rental::STATUS_ACTIVE)
            ->distinct()
            ->count('vehicle_id');

        $parkedVehicles = ParkingSpace::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereNotNull('vehicle_id')
            ->distinct()
            ->count('vehicle_id');

        return [
            'total' => $vehicles->count(),
            'active' => $activeVehicles,
            'inactive' => $vehicles->count() - $activeVehicles,
            'rented_now' => $rentedVehicles,
            'parked_now' => $parkedVehicles,
            'outside_garage' => max(
                $vehicles->count() - $parkedVehicles,
                0
            ),
            'available_for_rental' => max(
                $activeVehicles - $rentedVehicles,
                0
            ),
        ];
    }

    // Calcola l'occupazione attuale dell'autorimessa.
    private function garageSummary(): array
    {
        $totalSpaces = ParkingSpace::query()->count();

        $activeSpaces = ParkingSpace::query()
            ->where('is_active', true)
            ->count();

        $occupiedActiveSpaces = ParkingSpace::query()
            ->where('is_active', true)
            ->whereNotNull('vehicle_id')
            ->count();

        return [
            'total_spaces' => $totalSpaces,
            'active_spaces' => $activeSpaces,
            'inactive_spaces' => $totalSpaces - $activeSpaces,
            'occupied_spaces' => $occupiedActiveSpaces,
            'free_spaces' => max(
                $activeSpaces - $occupiedActiveSpaces,
                0
            ),
            'occupancy_rate' => $activeSpaces > 0
                ? round(
                    ($occupiedActiveSpaces / $activeSpaces) * 100,
                    2
                )
                : 0.0,
        ];
    }

    // Calcola giorni di noleggio, giacenza e percentuale di utilizzo.
    private function utilizationSummary(
        Carbon $dateFrom,
        Carbon $dateTo,
        $vehicles,
        ?int $vehicleId
    ): array {
        /*
         * Per tutta la flotta consideriamo i veicoli attualmente attivi.
         * Se viene scelto un veicolo specifico, analizziamo quel veicolo
         * anche se è stato successivamente disattivato.
         */
        $capacityVehicles = $vehicleId !== null
            ? $vehicles
            : $vehicles->where('is_active', true);

        $capacityVehicleIds = $capacityVehicles->pluck('id');

        $rentals = Rental::query()
            ->whereIn('vehicle_id', $capacityVehicleIds)
            ->whereIn('status', [
                Rental::STATUS_ACTIVE,
                Rental::STATUS_COMPLETED,
            ])
            ->where('starts_at', '<=', $dateTo)
            ->get();

        $usedSeconds = 0;
        $currentTime = now();

        foreach ($rentals as $rental) {
            $usageStart = (
                $rental->actual_starts_at
                ?? $rental->starts_at
            )->copy();

            $usageEnd = $rental->status === Rental::STATUS_ACTIVE
                ? $currentTime->copy()
                : (
                    $rental->actual_ends_at
                    ?? $rental->expected_ends_at
                )->copy();

            // Limita ogni noleggio ai confini del periodo richiesto.
            if ($usageStart->lt($dateFrom)) {
                $usageStart = $dateFrom->copy();
            }

            if ($usageEnd->gt($dateTo)) {
                $usageEnd = $dateTo->copy();
            }

            if ($usageEnd->gt($usageStart)) {
                $usedSeconds +=
                    $usageEnd->timestamp - $usageStart->timestamp;
            }
        }

        $periodDays = $this->periodDays($dateFrom, $dateTo);
        $capacityHours = $periodDays
            * 24
            * $capacityVehicles->count();

        $usedHours = min(
            $usedSeconds / 3600,
            $capacityHours
        );

        $idleHours = max($capacityHours - $usedHours, 0);

        return [
            'vehicles_considered' => $capacityVehicles->count(),
            'capacity_days' => round($capacityHours / 24, 2),
            'rented_days' => round($usedHours / 24, 2),
            'idle_days' => round($idleHours / 24, 2),
            'utilization_rate' => $capacityHours > 0
                ? round(($usedHours / $capacityHours) * 100, 2)
                : 0.0,
        ];
    }

    // Recupera scadenze già superate oppure previste nei prossimi 30 giorni.
    private function deadlineSummary(?int $vehicleId): array
    {
        $today = today();
        $limitDate = $today->copy()->addDays(30);

        $expenses = Expense::query()
            ->with('vehicle')
            ->when(
                $vehicleId !== null,
                fn ($query) => $query->where('vehicle_id', $vehicleId)
            )
            ->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', $limitDate)
            ->orderBy('expires_on')
            ->get();

        $overdue = $expenses->filter(
            fn (Expense $expense) => $expense->expires_on->lt($today)
        );

        $upcoming = $expenses->filter(
            fn (Expense $expense) => $expense->expires_on->gte($today)
                && $expense->expires_on->lte($limitDate)
        );

        $items = $expenses
            ->take(20)
            ->map(function (Expense $expense) use ($today): array {
                $daysRemaining = (int) $today->diffInDays(
                    $expense->expires_on,
                    false
                );

                return [
                    'expense_id' => $expense->id,
                    'vehicle_id' => $expense->vehicle_id,
                    'license_plate' => $expense->vehicle?->license_plate,
                    'brand' => $expense->vehicle?->brand,
                    'model' => $expense->vehicle?->model,
                    'category' => $expense->category,
                    'description' => $expense->description,
                    'amount' => $this->money($expense->amount),
                    'expires_on' => $expense->expires_on->toDateString(),
                    'days_remaining' => $daysRemaining,
                    'status' => $daysRemaining < 0
                        ? 'overdue'
                        : 'upcoming',
                ];
            })
            ->values()
            ->all();

        return [
            'overdue_count' => $overdue->count(),
            'upcoming_30_days_count' => $upcoming->count(),
            'items' => $items,
        ];
    }

    // Restituisce il numero di giorni inclusivi del periodo.
    private function periodDays(
        Carbon $dateFrom,
        Carbon $dateTo
    ): int {
        return (int) $dateFrom
            ->copy()
            ->startOfDay()
            ->diffInDays(
                $dateTo->copy()->startOfDay()
            ) + 1;
    }

    // Uniforma tutti gli importi a due cifre decimali.
    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
