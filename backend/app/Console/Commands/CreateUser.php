<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    //Nome con cui eseguiremo il comando dal terminale
    protected $signature = 'app:create-user';

    //Descrizione mostrata nell'elenco dei comandi Artisan
    protected $description = 'Crea un account di gestione senza registrazione pubblica';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        //Legge il nome ed elimina gli spazi iniziali e finali
        $name = trim((string) $this->ask('Nome'));

        //Legge l'email, elimina gli spazi esterni e la salva in minuscolo
        $email = Str::lower(
            trim((string) $this->ask('Email'))
        );

        //Legge la password senza mostrarla nel terminale
        //false impedisce che venga mostrata se il terminale non supporta l'input nascosto
        $password = $this->secret(
            'Password (almeno 12 caratteri)',
            false
        );

        //Chiede nuovamente la password per evitare errori di battitura
        $passwordConfirmation = $this->secret(
            'Conferma password',
            false
        );

        //Controlla tutti i dati prima di scrivere nel database
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
            ],
        ], [
            'name.required' => 'Inserisci il nome.',
            'name.max' => 'Il nome non può superare 255 caratteri.',
            'email.required' => 'Inserisci l\'email.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'email.max' => 'L\'email non può superare 255 caratteri.',
            'email.unique' => 'Esiste già un account con questa email.',
            'password.required' => 'Inserisci la password.',
            'password.min' => 'La password deve contenere almeno 12 caratteri.',
            'password.confirmed' => 'Le due password non coincidono.',
        ]);

        //Interrompe il comando se uno dei dati non è valido
        if ($validator->fails()) {
            $message = $validator->errors()
                ->first();

            $this->error($message);

            return self::FAILURE;
        }

        //Evita che bcrypt tronchi password più lunghe di 72 byte
        //strlen conta i byte, che possono essere più dei caratteri
        if (strlen($password) > 72) {
            $this->error('La password non può superare 72 byte.');

            return self::FAILURE;
        }

        //Prepara il nuovo utente con i dati già controllati
        $user = new User();
        $user->name = $name;
        $user->email = $email;

        //Salva soltanto l'hash della password, mai la password leggibile
        $user->password = Hash::make($password);

        //Inserisce il nuovo account nella tabella users
        $user->save();

        //Conferma che il salvataggio è terminato
        $this->info('Account di gestione creato.');

        return self::SUCCESS;
    }
}
