<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\ParkingMovement;
use App\Models\ParkingSpace;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /** Inserisce dati dimostrativi realistici e ripetibili. */
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = now()->startOfDay();

            // Crea l'account demo senza modificare gli altri utenti.
            $user = User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Amministratore Demo',
                    'password' => Hash::make('PasswordDemo!2026'),
                ]
            );

            $vehicles = $this->createVehicles();

            // Elimina solo i dati operativi dei mezzi demo.
            ParkingMovement::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->delete();
            Rental::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->delete();
            Expense::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->delete();

            $customers = $this->createCustomers($today);
            $spaces = $this->createParkingSpaces();

            ParkingSpace::query()
                ->where('zone', 'main')
                ->update(['vehicle_id' => null]);

            $activeRentals = $this->createRentals(
                $vehicles,
                $customers,
                $today
            );

            $this->createExpenses($vehicles, $today);
            $this->parkVehicles($vehicles, $spaces);
            $this->createMovements(
                $vehicles,
                $spaces,
                $activeRentals,
                $user,
                $today
            );
        });
    }

    /** @return Collection<int, Vehicle> */
    private function createVehicles(): Collection
    {
        // Marca, modello, tipo, celle, anno, km, tariffa, attivo.
        $fleet = [
            ['Fiat', 'Panda', Vehicle::TYPE_CAR, 1, 2022, 38500, 50, true],
            ['Ford', 'Transit', Vehicle::TYPE_VAN, 2, 2021, 72000, 90, true],
            ['Fiat', 'Ducato Camper', Vehicle::TYPE_CAMPER, 4, 2023, 22000, 140, true],
            ['Volkswagen', 'Golf', Vehicle::TYPE_CAR, 1, 2018, 128000, 45, false],
            ['Toyota', 'Corolla', Vehicle::TYPE_CAR, 1, 2022, 46000, 58, true],
            ['Yamaha', 'MT-07', Vehicle::TYPE_MOTORCYCLE, 1, 2023, 12500, 42, true],
            ['Mercedes-Benz', 'Sprinter', Vehicle::TYPE_VAN, 2, 2022, 61000, 105, true],
            ['Tesla', 'Model 3', Vehicle::TYPE_CAR, 1, 2024, 18000, 95, true],
            ['Iveco', 'Daily', Vehicle::TYPE_TRUCK, 4, 2020, 89000, 125, true],
            ['Setra', 'ComfortClass', Vehicle::TYPE_BUS, 8, 2019, 145000, 240, true],
            ['Renault', 'Captur', Vehicle::TYPE_CAR, 1, 2021, 52000, 62, true],
            ['BMW', 'X1', Vehicle::TYPE_CAR, 1, 2019, 97000, 78, false],
            ['Honda', 'SH 125', Vehicle::TYPE_MOTORCYCLE, 1, 2022, 21000, 32, true],
            ['Volkswagen', 'California', Vehicle::TYPE_CAMPER, 4, 2022, 35000, 165, true],
            ['Peugeot', '208', Vehicle::TYPE_CAR, 1, 2023, 27000, 55, true],
            ['Citroen', 'Berlingo', Vehicle::TYPE_VAN, 2, 2021, 68000, 82, true],
            ['Ford', 'Puma', Vehicle::TYPE_CAR, 1, 2022, 41000, 68, true],
            ['Renault', 'Master', Vehicle::TYPE_VAN, 2, 2020, 104000, 98, true],
            ['Ducati', 'Multistrada', Vehicle::TYPE_MOTORCYCLE, 1, 2021, 28500, 75, true],
            ['Audi', 'A3', Vehicle::TYPE_CAR, 1, 2020, 76000, 80, true],
            ['Opel', 'Corsa', Vehicle::TYPE_CAR, 1, 2023, 24000, 52, true],
            ['Mercedes-Benz', 'Vito', Vehicle::TYPE_VAN, 2, 2022, 57000, 110, true],
            ['Piaggio', 'Beverly 300', Vehicle::TYPE_MOTORCYCLE, 1, 2024, 9000, 38, true],
            ['Toyota', 'Proace', Vehicle::TYPE_VAN, 2, 2023, 33000, 100, true],
            ['Hyundai', 'Tucson', Vehicle::TYPE_CAR, 1, 2022, 44000, 76, true],
            ['Nissan', 'Qashqai', Vehicle::TYPE_CAR, 1, 2021, 59000, 72, true],
            ['Knaus', 'Boxstar', Vehicle::TYPE_CAMPER, 4, 2024, 12000, 180, true],
            ['Scania', 'Touring', Vehicle::TYPE_BUS, 8, 2018, 210000, 260, false],
            ['MAN', 'TGE', Vehicle::TYPE_TRUCK, 4, 2021, 83000, 135, true],
            ['Jeep', 'Renegade', Vehicle::TYPE_CAR, 1, 2022, 48000, 74, true],
        ];

        return collect($fleet)->map(
            fn (array $data, int $index): Vehicle => Vehicle::updateOrCreate(
                ['license_plate' => sprintf('DEMO-%03d', $index + 1)],
                [
                    'brand' => $data[0],
                    'model' => $data[1],
                    'type' => $data[2],
                    'parking_units' => $data[3],
                    'year' => $data[4],
                    'mileage' => $data[5],
                    'daily_rate' => $data[6],
                    'is_active' => $data[7],
                ]
            )
        )->values();
    }

    /** @return Collection<int, Customer> */
    private function createCustomers($today): Collection
    {
        $people = [
            ['Luca', 'Rossi', 'Pesaro'], ['Anna', 'Bianchi', 'Fano'],
            ['Marco', 'Verdi', 'Senigallia'], ['Sofia', 'Romano', 'Ancona'],
            ['Davide', 'Marini', 'Rimini'], ['Elena', 'Conti', 'Urbino'],
            ['Giulia', 'Ricci', 'Cattolica'], ['Matteo', 'Moretti', 'Jesi'],
            ['Chiara', 'Ferrari', 'Cesenatico'], ['Andrea', 'Esposito', 'Civitanova Marche'],
            ['Francesca', 'Gallo', 'Pesaro'], ['Simone', 'Costa', 'Fano'],
            ['Martina', 'Greco', 'Rimini'], ['Alessandro', 'Mancini', 'Ancona'],
            ['Valentina', 'Lombardi', 'Senigallia'],
        ];

        return collect($people)->map(
            function (array $person, int $index) use ($today): Customer {
                $number = $index + 1;

                return Customer::updateOrCreate(
                    ['driving_license_number' => sprintf('DEMO-LIC-%03d', $number)],
                    [
                        'first_name' => $person[0],
                        'last_name' => $person[1],
                        'birth_date' => $today->copy()->subYears(24 + $number),
                        'email' => "demo.cliente{$number}@example.com",
                        'phone' => sprintf('333%07d', $number * 731),
                        'tax_code' => 'DMO'.str_pad((string) $number, 13, '0', STR_PAD_LEFT),
                        'driving_license_expiry_date' => $today->copy()->addYears(2 + ($number % 5)),
                        'address' => "Via Demo {$number}, {$person[2]}",
                        'notes' => $number % 4 === 0 ? 'Cliente aziendale dimostrativo.' : null,
                        'is_active' => $number !== 15,
                    ]
                );
            }
        )->values();
    }

    /** @return Collection<string, ParkingSpace> */
    private function createParkingSpaces(): Collection
    {
        $spaces = collect();

        for ($row = 1; $row <= 4; $row++) {
            for ($column = 1; $column <= 6; $column++) {
                $space = ParkingSpace::updateOrCreate(
                    ['zone' => 'main', 'row_number' => $row, 'column_number' => $column],
                    [
                        'label' => "M-{$row}-{$column}",
                        'vehicle_id' => null,
                        'is_active' => ! ($row === 4 && $column === 6),
                        'notes' => $row === 4 && $column === 6
                            ? 'Cella temporaneamente non utilizzabile.'
                            : null,
                    ]
                );

                $spaces->put("{$row}-{$column}", $space);
            }
        }

        return $spaces;
    }

    /** @return Collection<string, Rental> */
    private function createRentals(Collection $vehicles, Collection $customers, $today): Collection
    {
        $activeRentals = collect();

        // Quindici noleggi completati.
        for ($index = 0; $index < 15; $index++) {
            $vehicle = $vehicles->get($index);
            $days = 2 + ($index % 4);
            $start = $today->copy()->subDays(70 - ($index * 3))->setTime(9, 0);
            $end = $start->copy()->addDays($days);
            $total = (float) $vehicle->daily_rate * $days;

            Rental::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customers->get($index)->id,
                'status' => Rental::STATUS_COMPLETED,
                'starts_at' => $start,
                'actual_starts_at' => $start->copy()->addMinutes(10),
                'expected_ends_at' => $end,
                'actual_ends_at' => $end->copy()->addMinutes(30),
                'daily_rate' => $vehicle->daily_rate,
                'total_amount' => $total,
                'amount_paid' => $total,
                'start_mileage' => max(0, $vehicle->mileage - 650),
                'end_mileage' => $vehicle->mileage,
                'notes' => 'Noleggio storico dimostrativo.',
            ]);
        }

        // Cinque prenotazioni annullate.
        foreach ([15, 16, 17, 18, 19] as $offset => $vehicleIndex) {
            $vehicle = $vehicles->get($vehicleIndex);

            Rental::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customers->get($offset + 4)->id,
                'status' => Rental::STATUS_CANCELLED,
                'starts_at' => $today->copy()->addDays(18 + $offset),
                'actual_starts_at' => null,
                'expected_ends_at' => $today->copy()->addDays(21 + $offset),
                'actual_ends_at' => null,
                'daily_rate' => $vehicle->daily_rate,
                'total_amount' => (float) $vehicle->daily_rate * 3,
                'amount_paid' => 0,
                'start_mileage' => null,
                'end_mileage' => null,
                'notes' => 'Prenotazione annullata dimostrativa.',
            ]);
        }

        // Quattro noleggi attualmente attivi.
        foreach ([2, 6, 13, 21] as $offset => $vehicleIndex) {
            $vehicle = $vehicles->get($vehicleIndex);
            $start = $today->copy()->subDays($offset + 1)->setTime(9, 0);
            $end = $today->copy()->addDays($offset + 2)->setTime(9, 0);
            $total = (float) $vehicle->daily_rate * ($offset + 4);

            $rental = Rental::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customers->get($offset + 1)->id,
                'status' => Rental::STATUS_ACTIVE,
                'starts_at' => $start,
                'actual_starts_at' => $start->copy()->addMinutes(15),
                'expected_ends_at' => $end,
                'actual_ends_at' => null,
                'daily_rate' => $vehicle->daily_rate,
                'total_amount' => $total,
                'amount_paid' => round($total / 2, 2),
                'start_mileage' => $vehicle->mileage,
                'end_mileage' => null,
                'notes' => 'Noleggio attualmente in corso.',
            ]);

            $activeRentals->put($vehicleIndex, $rental);
        }

        // Sei prenotazioni future.
        foreach ([0, 5, 7, 15, 23, 26] as $offset => $vehicleIndex) {
            $vehicle = $vehicles->get($vehicleIndex);
            $start = $today->copy()->addDays(4 + ($offset * 3))->setTime(9, 0);
            $total = (float) $vehicle->daily_rate * 3;

            Rental::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customers->get($offset + 7)->id,
                'status' => Rental::STATUS_RESERVED,
                'starts_at' => $start,
                'actual_starts_at' => null,
                'expected_ends_at' => $start->copy()->addDays(3),
                'actual_ends_at' => null,
                'daily_rate' => $vehicle->daily_rate,
                'total_amount' => $total,
                'amount_paid' => round($total * 0.25, 2),
                'start_mileage' => null,
                'end_mileage' => null,
                'notes' => 'Prenotazione futura dimostrativa.',
            ]);
        }

        return $activeRentals;
    }

    private function createExpenses(Collection $vehicles, $today): void
    {
        // Tutti i mezzi hanno almeno una spesa.
        foreach ($vehicles as $index => $vehicle) {
            $category = Expense::CATEGORIES[$index % count(Expense::CATEGORIES)];

            Expense::create([
                'vehicle_id' => $vehicle->id,
                'category' => $category,
                'description' => 'Costo operativo dimostrativo',
                'amount' => 75 + ($index * 37),
                'expense_date' => $today->copy()->subDays($index + 8),
                'expires_on' => in_array($category, [
                    Expense::CATEGORY_INSURANCE,
                    Expense::CATEGORY_ROAD_TAX,
                    Expense::CATEGORY_INSPECTION,
                ], true) ? $today->copy()->addDays(($index - 10) * 4) : null,
                'mileage' => max(0, $vehicle->mileage - 500),
                'supplier' => 'Fornitore Demo '.(($index % 5) + 1),
                'notes' => null,
            ]);
        }

        // I primi quindici mezzi hanno anche una spesa recente.
        for ($index = 0; $index < 15; $index++) {
            $vehicle = $vehicles->get($index);

            Expense::create([
                'vehicle_id' => $vehicle->id,
                'category' => $index % 2 === 0
                    ? Expense::CATEGORY_MAINTENANCE
                    : Expense::CATEGORY_CLEANING,
                'description' => $index % 2 === 0
                    ? 'Manutenzione periodica'
                    : 'Pulizia professionale',
                'amount' => 45 + ($index * 18),
                'expense_date' => $today->copy()->subDays($index + 1),
                'expires_on' => null,
                'mileage' => $vehicle->mileage,
                'supplier' => 'Officina e servizi Demo',
                'notes' => 'Spesa recente dimostrativa.',
            ]);
        }
    }

    private function parkVehicles(Collection $vehicles, Collection $spaces): void
    {
        $placements = [
            0 => ['1-1'], 1 => ['1-2', '1-3'], 4 => ['1-4'],
            7 => ['1-5'], 10 => ['1-6'],
            8 => ['2-1', '2-2', '2-3', '2-4'],
            5 => ['2-5'], 12 => ['2-6'],
            17 => ['3-1', '3-2'], 18 => ['3-3'],
        ];

        foreach ($placements as $vehicleIndex => $positions) {
            foreach ($positions as $position) {
                $spaces->get($position)->update([
                    'vehicle_id' => $vehicles->get($vehicleIndex)->id,
                ]);
            }
        }
    }

    private function createMovements(
        Collection $vehicles,
        Collection $spaces,
        Collection $activeRentals,
        User $user,
        $today
    ): void {
        $parked = [
            0 => '1-1', 1 => '1-2', 4 => '1-4', 7 => '1-5', 10 => '1-6',
            8 => '2-1', 5 => '2-5', 12 => '2-6', 17 => '3-1', 18 => '3-3',
        ];

        foreach ($parked as $vehicleIndex => $position) {
            $vehicle = $vehicles->get($vehicleIndex);
            $space = $spaces->get($position);

            ParkingMovement::create([
                'vehicle_id' => $vehicle->id,
                'vehicle_license_plate' => $vehicle->license_plate,
                'performed_by_user_id' => $user->id,
                'rental_id' => null,
                'type' => ParkingMovement::TYPE_PARKED,
                'from_parking_space_id' => null,
                'from_zone' => null,
                'from_row_number' => null,
                'from_column_number' => null,
                'to_parking_space_id' => $space->id,
                'to_zone' => 'main',
                'to_row_number' => $space->row_number,
                'to_column_number' => $space->column_number,
                'parking_units' => $vehicle->parking_units,
                'notes' => 'Parcheggio iniziale dimostrativo.',
                'occurred_at' => $today->copy()->subDays(($vehicleIndex % 12) + 1),
            ]);
        }

        foreach ([2, 6, 13, 21] as $index => $vehicleIndex) {
            $vehicle = $vehicles->get($vehicleIndex);
            $rental = $activeRentals->get($vehicleIndex);
            $space = $spaces->get('4-'.($index + 1));

            ParkingMovement::create([
                'vehicle_id' => $vehicle->id,
                'vehicle_license_plate' => $vehicle->license_plate,
                'performed_by_user_id' => $user->id,
                'rental_id' => $rental->id,
                'type' => ParkingMovement::TYPE_RENTAL_DEPARTURE,
                'from_parking_space_id' => $space->id,
                'from_zone' => 'main',
                'from_row_number' => 4,
                'from_column_number' => $index + 1,
                'to_parking_space_id' => null,
                'to_zone' => null,
                'to_row_number' => null,
                'to_column_number' => null,
                'parking_units' => $vehicle->parking_units,
                'notes' => 'Partenza del noleggio attivo dimostrativo.',
                'occurred_at' => $rental->actual_starts_at,
            ]);
        }
    }
}
