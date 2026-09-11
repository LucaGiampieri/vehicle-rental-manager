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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Inserisce un insieme coerente di dati dimostrativi.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $today = now()->startOfDay();

            //Crea l'utente con cui provare l'applicazione.
            $user = User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Amministratore Demo',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            //Crea quattro mezzi con dimensioni e situazioni differenti.
            $panda = Vehicle::updateOrCreate(
                ['license_plate' => 'DEMO-001'],
                [
                    'brand' => 'Fiat',
                    'model' => 'Panda',
                    'type' => 'car',
                    'parking_units' => 1,
                    'year' => 2022,
                    'mileage' => 38500,
                    'daily_rate' => 50,
                    'is_active' => true,
                ]
            );

            $transit = Vehicle::updateOrCreate(
                ['license_plate' => 'DEMO-002'],
                [
                    'brand' => 'Ford',
                    'model' => 'Transit',
                    'type' => 'van',
                    'parking_units' => 2,
                    'year' => 2021,
                    'mileage' => 72000,
                    'daily_rate' => 90,
                    'is_active' => true,
                ]
            );

            $ducato = Vehicle::updateOrCreate(
                ['license_plate' => 'DEMO-003'],
                [
                    'brand' => 'Fiat',
                    'model' => 'Ducato Camper',
                    'type' => 'camper',
                    'parking_units' => 4,
                    'year' => 2023,
                    'mileage' => 22000,
                    'daily_rate' => 140,
                    'is_active' => true,
                ]
            );

            $inactiveVehicle = Vehicle::updateOrCreate(
                ['license_plate' => 'DEMO-004'],
                [
                    'brand' => 'Volkswagen',
                    'model' => 'Golf',
                    'type' => 'car',
                    'parking_units' => 1,
                    'year' => 2018,
                    'mileage' => 128000,
                    'daily_rate' => 45,
                    'is_active' => false,
                ]
            );

            $vehicles = collect([
                $panda,
                $transit,
                $ducato,
                $inactiveVehicle,
            ]);

            /*
             * Elimina solamente i dati operativi appartenenti ai mezzi demo.
             * Questo rende il Seeder riutilizzabile senza creare duplicati.
             */
            ParkingMovement::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->delete();

            Rental::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->delete();

            Expense::query()
                ->whereIn('vehicle_id', $vehicles->pluck('id'))
                ->delete();

            //Crea tre clienti dimostrativi con patente valida.
            $luca = Customer::updateOrCreate(
                ['driving_license_number' => 'DEMO-LIC-001'],
                [
                    'first_name' => 'Luca',
                    'last_name' => 'Rossi',
                    'birth_date' => '1992-04-15',
                    'email' => 'luca.rossi@example.com',
                    'phone' => '3331112233',
                    'tax_code' => 'DEMO-RSSLCU92D15',
                    'driving_license_expiry_date' => $today
                        ->copy()
                        ->addYears(5),
                    'address' => 'Via Roma 10, Pesaro',
                    'notes' => 'Cliente dimostrativo abituale.',
                    'is_active' => true,
                ]
            );

            $anna = Customer::updateOrCreate(
                ['driving_license_number' => 'DEMO-LIC-002'],
                [
                    'first_name' => 'Anna',
                    'last_name' => 'Bianchi',
                    'birth_date' => '1987-09-08',
                    'email' => 'anna.bianchi@example.com',
                    'phone' => '3332223344',
                    'tax_code' => 'DEMO-BNCNNA87P48',
                    'driving_license_expiry_date' => $today
                        ->copy()
                        ->addYears(4),
                    'address' => 'Corso Italia 25, Fano',
                    'notes' => null,
                    'is_active' => true,
                ]
            );

            $marco = Customer::updateOrCreate(
                ['driving_license_number' => 'DEMO-LIC-003'],
                [
                    'first_name' => 'Marco',
                    'last_name' => 'Verdi',
                    'birth_date' => '1979-12-20',
                    'email' => 'marco.verdi@example.com',
                    'phone' => '3334445566',
                    'tax_code' => 'DEMO-VRDMRC79T20',
                    'driving_license_expiry_date' => $today
                        ->copy()
                        ->addYears(3),
                    'address' => 'Via Adriatica 8, Senigallia',
                    'notes' => null,
                    'is_active' => true,
                ]
            );

            //Crea una griglia principale di 4 righe per 6 colonne.
            $spaces = collect();

            for ($row = 1; $row <= 4; $row++) {
                for ($column = 1; $column <= 6; $column++) {
                    $space = ParkingSpace::updateOrCreate(
                        [
                            'zone' => 'main',
                            'row_number' => $row,
                            'column_number' => $column,
                        ],
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

            //La Panda occupa una cella; il Transit ne occupa due affiancate.
            $spaces->get('1-1')->update([
                'vehicle_id' => $panda->id,
            ]);

            foreach (['1-3', '1-4'] as $position) {
                $spaces->get($position)->update([
                    'vehicle_id' => $transit->id,
                ]);
            }

            //Noleggio concluso recentemente.
            $completedRental = Rental::create([
                'vehicle_id' => $panda->id,
                'customer_id' => $luca->id,
                'status' => Rental::STATUS_COMPLETED,
                'starts_at' => $today->copy()->subDays(20)->setTime(9, 0),
                'actual_starts_at' => $today->copy()->subDays(20)->setTime(9, 0),
                'expected_ends_at' => $today->copy()->subDays(17)->setTime(9, 0),
                'actual_ends_at' => $today->copy()->subDays(17)->setTime(10, 0),
                'daily_rate' => 50,
                'total_amount' => 200,
                'amount_paid' => 200,
                'start_mileage' => 37800,
                'end_mileage' => 38500,
                'notes' => 'Noleggio dimostrativo completato.',
            ]);

            //Noleggio attualmente attivo: il camper si trova fuori sede.
            $activeRental = Rental::create([
                'vehicle_id' => $ducato->id,
                'customer_id' => $anna->id,
                'status' => Rental::STATUS_ACTIVE,
                'starts_at' => $today->copy()->subDay()->setTime(9, 0),
                'actual_starts_at' => $today->copy()->subDay()->setTime(9, 15),
                'expected_ends_at' => $today->copy()->addDays(3)->setTime(9, 0),
                'actual_ends_at' => null,
                'daily_rate' => 140,
                'total_amount' => 560,
                'amount_paid' => 200,
                'start_mileage' => 22000,
                'end_mileage' => null,
                'notes' => 'Camper attualmente noleggiato.',
            ]);

            //Prenotazione futura.
            Rental::create([
                'vehicle_id' => $panda->id,
                'customer_id' => $marco->id,
                'status' => Rental::STATUS_RESERVED,
                'starts_at' => $today->copy()->addDays(7)->setTime(9, 0),
                'actual_starts_at' => null,
                'expected_ends_at' => $today->copy()->addDays(10)->setTime(9, 0),
                'actual_ends_at' => null,
                'daily_rate' => 50,
                'total_amount' => 150,
                'amount_paid' => 50,
                'start_mileage' => null,
                'end_mileage' => null,
                'notes' => 'Prenotazione futura dimostrativa.',
            ]);

            //Prenotazione annullata conservata nello storico.
            Rental::create([
                'vehicle_id' => $transit->id,
                'customer_id' => $luca->id,
                'status' => Rental::STATUS_CANCELLED,
                'starts_at' => $today->copy()->addDays(12)->setTime(9, 0),
                'actual_starts_at' => null,
                'expected_ends_at' => $today->copy()->addDays(14)->setTime(9, 0),
                'actual_ends_at' => null,
                'daily_rate' => 90,
                'total_amount' => 180,
                'amount_paid' => 0,
                'start_mileage' => null,
                'end_mileage' => null,
                'notes' => 'Prenotazione annullata dimostrativa.',
            ]);

            //Registra costi recenti, storici e relative scadenze.
            Expense::create([
                'vehicle_id' => $panda->id,
                'category' => Expense::CATEGORY_PURCHASE,
                'description' => 'Acquisto del veicolo',
                'amount' => 18000,
                'expense_date' => $today->copy()->subDays(400),
                'expires_on' => null,
                'mileage' => 0,
                'supplier' => 'Concessionaria Demo',
                'notes' => null,
            ]);

            Expense::create([
                'vehicle_id' => $panda->id,
                'category' => Expense::CATEGORY_MAINTENANCE,
                'description' => 'Tagliando ordinario',
                'amount' => 320,
                'expense_date' => $today->copy()->subDays(12),
                'expires_on' => null,
                'mileage' => 38200,
                'supplier' => 'Officina Centrale',
                'notes' => 'Sostituiti olio e filtri.',
            ]);

            Expense::create([
                'vehicle_id' => $transit->id,
                'category' => Expense::CATEGORY_INSURANCE,
                'description' => 'Assicurazione annuale',
                'amount' => 860,
                'expense_date' => $today->copy()->subDays(300),
                'expires_on' => $today->copy()->addDays(20),
                'mileage' => 69000,
                'supplier' => 'Assicurazioni Demo',
                'notes' => 'Scadenza prossima.',
            ]);

            Expense::create([
                'vehicle_id' => $ducato->id,
                'category' => Expense::CATEGORY_ROAD_TAX,
                'description' => 'Bollo del camper',
                'amount' => 390,
                'expense_date' => $today->copy()->subDays(370),
                'expires_on' => $today->copy()->subDays(5),
                'mileage' => 18500,
                'supplier' => 'Regione Marche',
                'notes' => 'Scadenza dimostrativa già superata.',
            ]);

            Expense::create([
                'vehicle_id' => $panda->id,
                'category' => Expense::CATEGORY_CLEANING,
                'description' => 'Pulizia completa',
                'amount' => 45,
                'expense_date' => $today->copy()->subDays(2),
                'expires_on' => null,
                'mileage' => 38500,
                'supplier' => 'Autolavaggio Demo',
                'notes' => null,
            ]);

            //Registra il rientro della Panda nella prima cella.
            ParkingMovement::create([
                'vehicle_id' => $panda->id,
                'vehicle_license_plate' => $panda->license_plate,
                'performed_by_user_id' => $user->id,
                'rental_id' => $completedRental->id,
                'type' => ParkingMovement::TYPE_RENTAL_RETURN,
                'from_parking_space_id' => null,
                'from_zone' => null,
                'from_row_number' => null,
                'from_column_number' => null,
                'to_parking_space_id' => $spaces->get('1-1')->id,
                'to_zone' => 'main',
                'to_row_number' => 1,
                'to_column_number' => 1,
                'parking_units' => 1,
                'notes' => 'Rientro dal noleggio dimostrativo.',
                'occurred_at' => $today->copy()->subDays(17)->setTime(10, 0),
            ]);

            //Registra il parcheggio manuale del Transit.
            ParkingMovement::create([
                'vehicle_id' => $transit->id,
                'vehicle_license_plate' => $transit->license_plate,
                'performed_by_user_id' => $user->id,
                'rental_id' => null,
                'type' => ParkingMovement::TYPE_PARKED,
                'from_parking_space_id' => null,
                'from_zone' => null,
                'from_row_number' => null,
                'from_column_number' => null,
                'to_parking_space_id' => $spaces->get('1-3')->id,
                'to_zone' => 'main',
                'to_row_number' => 1,
                'to_column_number' => 3,
                'parking_units' => 2,
                'notes' => 'Parcheggio iniziale dimostrativo.',
                'occurred_at' => $today->copy()->subDays(10)->setTime(16, 30),
            ]);

            //Registra la partenza del camper per il noleggio attivo.
            ParkingMovement::create([
                'vehicle_id' => $ducato->id,
                'vehicle_license_plate' => $ducato->license_plate,
                'performed_by_user_id' => $user->id,
                'rental_id' => $activeRental->id,
                'type' => ParkingMovement::TYPE_RENTAL_DEPARTURE,
                'from_parking_space_id' => $spaces->get('2-1')->id,
                'from_zone' => 'main',
                'from_row_number' => 2,
                'from_column_number' => 1,
                'to_parking_space_id' => null,
                'to_zone' => null,
                'to_row_number' => null,
                'to_column_number' => null,
                'parking_units' => 4,
                'notes' => 'Partenza del noleggio attivo dimostrativo.',
                'occurred_at' => $today->copy()->subDay()->setTime(9, 15),
            ]);
        });
    }
}
