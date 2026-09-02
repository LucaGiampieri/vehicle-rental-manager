<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Requests\Api\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    //Restituisce l'elenco paginato dei clienti
    public function index(): AnonymousResourceCollection
    {
        //Carica i clienti e conta i noleggi senza recuperare tutti i record
        $customers = Customer::query()
            ->withCount('rentals')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        //Trasforma ogni cliente tramite CustomerResource
        return CustomerResource::collection($customers);
    }

    //Crea un nuovo cliente
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        //Validated restituisce soltanto i dati che hanno superato le regole
        $customer = Customer::create(
            $request->validated()
        );

        //Rilegge i valori predefiniti assegnati dal database
        $customer->refresh();

        //Carica il conteggio iniziale dei noleggi
        $customer->loadCount('rentals');

        //Restituisce il cliente con il codice HTTP 201 Created
        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    //Restituisce un singolo cliente
    public function show(Customer $customer): CustomerResource
    {
        //Carica il conteggio dei noleggi collegati al cliente
        $customer->loadCount('rentals');

        return new CustomerResource($customer);
    }

    //Modifica un cliente esistente
    public function update(
        UpdateCustomerRequest $request,
        Customer $customer
    ): CustomerResource {
        //Aggiorna soltanto i campi validati e realmente inviati
        $customer->update(
            $request->validated()
        );

        //Rilegge il cliente e aggiorna il conteggio dei noleggi
        $customer->refresh();
        $customer->loadCount('rentals');

        return new CustomerResource($customer);
    }

    //Elimina un cliente soltanto quando non possiede noleggi collegati
    public function destroy(Customer $customer): Response
    {
        //Controlla l'esistenza di almeno un noleggio
        $hasRentals = $customer->rentals()->exists();

        //Impedisce di eliminare lo storico di un cliente che ha effettuato noleggi
        if ($hasRentals) {
            return response()->json([
                'message' => 'Il cliente non può essere eliminato perché possiede noleggi collegati. Disattivalo per conservare lo storico.',
            ], Response::HTTP_CONFLICT);
        }

        //Elimina definitivamente il cliente
        $customer->delete();

        //Restituisce 204 perché non ci sono dati da mostrare
        return response()->noContent();
    }
}
