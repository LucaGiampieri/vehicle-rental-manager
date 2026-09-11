<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExpenseRequest;
use App\Http\Requests\Api\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    // Restituisce l'elenco paginato e filtrabile delle spese
    public function index(Request $request): AnonymousResourceCollection
    {
        // Valida i parametri inseriti nell'indirizzo della richiesta
        $filters = $request->validate([
            'vehicle_id' => [
                'sometimes',
                'integer',
                'exists:vehicles,id',
            ],
            'category' => [
                'sometimes',
                'string',
                Rule::in(Expense::CATEGORIES),
            ],
            'date_from' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'expires_before' => [
                'sometimes',
                'date_format:Y-m-d',
            ],
            'search' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        // La data finale non può precedere quella iniziale
        if (
            isset($filters['date_from'], $filters['date_to'])
            && $filters['date_to'] < $filters['date_from']
        ) {
            throw ValidationException::withMessages([
                'date_to' => [
                    'La data finale non può precedere quella iniziale.',
                ],
            ]);
        }

        $expenses = Expense::query()
            // Carica il mezzo associato evitando query aggiuntive
            ->with('vehicle')

            // Applica ogni filtro soltanto quando è stato inviato
            ->when(
                isset($filters['vehicle_id']),
                fn ($query) => $query->where(
                    'vehicle_id',
                    $filters['vehicle_id']
                )
            )
            ->when(
                isset($filters['category']),
                fn ($query) => $query->where(
                    'category',
                    $filters['category']
                )
            )
            ->when(
                isset($filters['date_from']),
                fn ($query) => $query->whereDate(
                    'expense_date',
                    '>=',
                    $filters['date_from']
                )
            )
            ->when(
                isset($filters['date_to']),
                fn ($query) => $query->whereDate(
                    'expense_date',
                    '<=',
                    $filters['date_to']
                )
            )
            ->when(
                isset($filters['expires_before']),
                fn ($query) => $query
                    ->whereNotNull('expires_on')
                    ->whereDate(
                        'expires_on',
                        '<=',
                        $filters['expires_before']
                    )
            )
            ->when(
                isset($filters['search']),
                function ($query) use ($filters): void {
                    $search = trim($filters['search']);

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where(
                                'description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'supplier',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return ExpenseResource::collection($expenses);
    }

    // Crea una nuova spesa
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $expense = DB::transaction(function () use ($data): Expense {
            // Blocca il veicolo durante il salvataggio
            $vehicle = Vehicle::query()
                ->whereKey($data['vehicle_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $expense = Expense::create($data);

            // Una lettura più recente del contachilometri aggiorna il mezzo
            if (
                isset($data['mileage'])
                && $data['mileage'] > $vehicle->mileage
            ) {
                $vehicle->update([
                    'mileage' => $data['mileage'],
                ]);
            }

            return $expense;
        });

        $expense->load('vehicle');

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    // Restituisce una singola spesa
    public function show(Expense $expense): ExpenseResource
    {
        $expense->load('vehicle');

        return new ExpenseResource($expense);
    }

    // Modifica una spesa esistente
    public function update(
        UpdateExpenseRequest $request,
        Expense $expense
    ): ExpenseResource {
        $data = $request->validated();

        DB::transaction(function () use (
            $expense,
            $data
        ): void {
            // Usa il nuovo veicolo se la relazione viene modificata
            $vehicleId = $data['vehicle_id']
                ?? $expense->vehicle_id;

            $vehicle = Vehicle::query()
                ->whereKey($vehicleId)
                ->lockForUpdate()
                ->firstOrFail();

            $expense->update($data);

            // Se il chilometraggio viene modificato, non permette
            // comunque al contachilometri generale di diminuire
            if (
                array_key_exists('mileage', $data)
                && $data['mileage'] !== null
                && $data['mileage'] > $vehicle->mileage
            ) {
                $vehicle->update([
                    'mileage' => $data['mileage'],
                ]);
            }
        });

        $expense->refresh();
        $expense->load('vehicle');

        return new ExpenseResource($expense);
    }

    // Elimina una spesa inserita per errore
    public function destroy(Expense $expense): Response
    {
        $expense->delete();

        return response()->noContent();
    }
}
