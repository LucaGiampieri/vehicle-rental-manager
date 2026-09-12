# Vehicle Rental Manager — Backend

Backend REST API per un gestionale di autonoleggio sviluppato con Laravel.

Il sistema gestisce veicoli, clienti, prenotazioni, noleggi, costi, autorimessa, fotografie e statistiche economiche e operative.

## Tecnologie

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL
- PHPUnit
- Laravel Pint

## Funzionalità

- autenticazione tramite cookie e Laravel Sanctum;
- registrazione pubblica disabilitata;
- creazione degli account tramite comando Artisan;
- recupero e reimpostazione della password;
- gestione completa dei veicoli;
- ricerca, filtri e paginazione;
- galleria fotografica dei veicoli;
- gestione dei clienti;
- gestione delle spese;
- gestione delle prenotazioni e dei noleggi;
- controllo delle sovrapposizioni;
- consegna e rientro dei veicoli;
- aggiornamento del chilometraggio;
- autorimessa grafica basata su celle;
- spostamenti e cronologia dei mezzi;
- dashboard economica e operativa;
- date e orari normalizzati in UTC.

## Installazione

Entrare nella cartella del backend:

```powershell
cd backend
```

Installare le dipendenze:

```powershell
composer install
```

Creare il file `.env`:

```powershell
Copy-Item .env.example .env
```

Generare la chiave dell’applicazione:

```powershell
php artisan key:generate
```

Configurare il database nel file `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autonoleggio
DB_USERNAME=root
DB_PASSWORD=
```

Configurare il frontend autorizzato:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

Creare le tabelle:

```powershell
php artisan migrate
```

Creare il collegamento pubblico per le immagini:

```powershell
php artisan storage:link
```

Avviare Laravel:

```powershell
php artisan serve
```

Il backend sarà disponibile all’indirizzo:

```text
http://127.0.0.1:8000
```

## Estensioni PHP

Per il caricamento e i test delle immagini deve essere attiva l’estensione GD:

```ini
extension=gd
```

Per controllarla:

```powershell
php -m | Select-String "gd"
```

Sono inoltre necessarie le normali estensioni Laravel, tra cui:

- PDO MySQL;
- Mbstring;
- OpenSSL;
- Fileinfo.

## Creazione di un account

La registrazione pubblica è disabilitata.

Per creare un account di gestione:

```powershell
php artisan app:create-user
```

Il comando richiede:

- nome;
- email;
- password di almeno 12 caratteri;
- conferma della password.

## Dati dimostrativi

In ambiente locale è possibile inserire i dati demo:

```powershell
php artisan db:seed
```

Credenziali demo:

```text
Email: admin@example.com
Password: PasswordDemo!2026
```

Il Seeder non viene eseguito automaticamente in produzione.

## Autenticazione da React

Prima del login il frontend deve richiedere il cookie CSRF:

```http
GET /sanctum/csrf-cookie
```

Successivamente può effettuare il login:

```http
POST /login
```

Corpo JSON:

```json
{
    "email": "admin@example.com",
    "password": "PasswordDemo!2026"
}
```

Le richieste Axios devono utilizzare:

```javascript
withCredentials: true;
```

Per terminare la sessione:

```http
POST /logout
```

## API principali

Tutte le rotte `/api/*` richiedono autenticazione tramite Sanctum.

### Utente

| Metodo | Rotta       | Descrizione                      |
| ------ | ----------- | -------------------------------- |
| GET    | `/api/user` | Restituisce l’utente autenticato |

### Veicoli

| Metodo | Rotta                     | Descrizione         |
| ------ | ------------------------- | ------------------- |
| GET    | `/api/vehicles`           | Elenco dei veicoli  |
| POST   | `/api/vehicles`           | Crea un veicolo     |
| GET    | `/api/vehicles/{vehicle}` | Mostra un veicolo   |
| PATCH  | `/api/vehicles/{vehicle}` | Modifica un veicolo |
| DELETE | `/api/vehicles/{vehicle}` | Elimina un veicolo  |

Filtri disponibili:

```text
search
type
is_active
per_page
```

Esempio:

```http
GET /api/vehicles?search=Fiat&type=car&is_active=true&per_page=15
```

### Immagini dei veicoli

| Metodo | Rotta                                | Descrizione        |
| ------ | ------------------------------------ | ------------------ |
| GET    | `/api/vehicles/{vehicle}/images`     | Elenca la galleria |
| POST   | `/api/vehicles/{vehicle}/images`     | Carica un’immagine |
| PATCH  | `/api/vehicle-images/{vehicleImage}` | Modifica i dati    |
| DELETE | `/api/vehicle-images/{vehicleImage}` | Elimina l’immagine |

Il caricamento utilizza `multipart/form-data`.

Campi disponibili:

| Campo        | Descrizione              |
| ------------ | ------------------------ |
| `image`      | File JPG, PNG o WebP     |
| `category`   | Categoria dell’immagine  |
| `caption`    | Descrizione facoltativa  |
| `is_primary` | Imposta la copertina     |
| `sort_order` | Posizione nella galleria |

Categorie:

```text
exterior
interior
plate
damage
other
```

Limiti:

- massimo 10 immagini per veicolo;
- massimo 5 MB per immagine;
- la prima immagine diventa automaticamente la copertina;
- eliminando la copertina viene promossa la prima immagine rimasta.

### Clienti

| Metodo | Rotta                       | Descrizione         |
| ------ | --------------------------- | ------------------- |
| GET    | `/api/customers`            | Elenco dei clienti  |
| POST   | `/api/customers`            | Crea un cliente     |
| GET    | `/api/customers/{customer}` | Mostra un cliente   |
| PATCH  | `/api/customers/{customer}` | Modifica un cliente |
| DELETE | `/api/customers/{customer}` | Elimina un cliente  |

Filtri disponibili:

```text
search
is_active
per_page
```

La ricerca controlla nome, cognome, email, telefono, codice fiscale e patente.

### Noleggi

| Metodo | Rotta                            | Descrizione               |
| ------ | -------------------------------- | ------------------------- |
| GET    | `/api/rentals`                   | Elenco dei noleggi        |
| POST   | `/api/rentals`                   | Crea una prenotazione     |
| GET    | `/api/rentals/{rental}`          | Mostra un noleggio        |
| PATCH  | `/api/rentals/{rental}`          | Modifica una prenotazione |
| DELETE | `/api/rentals/{rental}`          | Elimina quando consentito |
| PATCH  | `/api/rentals/{rental}/activate` | Consegna il veicolo       |
| PATCH  | `/api/rentals/{rental}/complete` | Registra il rientro       |
| PATCH  | `/api/rentals/{rental}/cancel`   | Annulla la prenotazione   |

Stati disponibili:

```text
reserved
active
completed
cancelled
```

Filtri disponibili:

```text
search
status
vehicle_id
customer_id
date_from
date_to
per_page
```

Le date dei filtri utilizzano il formato:

```text
YYYY-MM-DD
```

### Spese

| Metodo | Rotta                     | Descrizione        |
| ------ | ------------------------- | ------------------ |
| GET    | `/api/expenses`           | Elenco delle spese |
| POST   | `/api/expenses`           | Registra una spesa |
| GET    | `/api/expenses/{expense}` | Mostra una spesa   |
| PATCH  | `/api/expenses/{expense}` | Modifica una spesa |
| DELETE | `/api/expenses/{expense}` | Elimina una spesa  |

Filtri disponibili:

```text
vehicle_id
category
date_from
date_to
expires_before
search
per_page
```

Categorie principali:

```text
purchase
maintenance
repair
road_tax
insurance
fuel
cleaning
inspection
other
```

### Autorimessa

| Metodo | Rotta                                      | Descrizione            |
| ------ | ------------------------------------------ | ---------------------- |
| GET    | `/api/parking-spaces`                      | Elenca le celle        |
| POST   | `/api/parking-spaces`                      | Crea una cella         |
| GET    | `/api/parking-spaces/{parkingSpace}`       | Mostra una cella       |
| PATCH  | `/api/parking-spaces/{parkingSpace}`       | Modifica una cella     |
| DELETE | `/api/parking-spaces/{parkingSpace}`       | Elimina una cella      |
| POST   | `/api/garage/park`                         | Parcheggia un veicolo  |
| PATCH  | `/api/garage/vehicles/{vehicle}/move`      | Sposta un veicolo      |
| PATCH  | `/api/garage/vehicles/{vehicle}/unpark`    | Fa uscire un veicolo   |
| GET    | `/api/garage/movements`                    | Cronologia generale    |
| GET    | `/api/garage/vehicles/{vehicle}/movements` | Cronologia del veicolo |

### Dashboard

```http
GET /api/dashboard
```

Filtri disponibili:

```text
date_from
date_to
vehicle_id
```

Senza date viene analizzato automaticamente un periodo di 30 giorni.

La dashboard restituisce:

- ricavi contrattuali;
- ricavi completati;
- importi incassati;
- importi ancora dovuti;
- spese;
- utile previsto e realizzato;
- situazione della flotta;
- occupazione dell’autorimessa;
- giorni di utilizzo e giacenza;
- scadenze imminenti.

## Date e orari

Il backend lavora in UTC.

Le date con orario vengono restituite in formato ISO 8601:

```text
2026-09-12T08:00:00.000000Z
```

Le date senza orario vengono restituite come:

```text
2026-09-12
```

Il frontend può mostrare l’orario locale usando JavaScript:

```javascript
new Date(value).toLocaleString();
```

## Pagamenti

Nell’MVP il pagamento viene gestito tramite il campo:

```text
amount_paid
```

L’API restituisce anche:

```text
total_amount
balance_due
```

Lo storico dei singoli pagamenti potrà essere aggiunto in una versione successiva.

## Test e qualità

Eseguire tutti i test:

```powershell
php artisan test
```

Controllare la formattazione:

```powershell
.\vendor\bin\pint --test
```

Correggere automaticamente la formattazione:

```powershell
.\vendor\bin\pint
```

Controllare le dipendenze:

```powershell
composer audit
```

## Archiviazione delle immagini

In locale le immagini vengono salvate in:

```text
storage/app/public/vehicles
```

e rese accessibili tramite:

```text
public/storage
```

Su un hosting con filesystem temporaneo sarà necessario utilizzare uno storage persistente, per esempio un disco permanente o un servizio compatibile con S3.

## Possibili sviluppi futuri

- frontend React;
- storico dettagliato dei pagamenti;
- fotografie alla consegna e al rientro;
- registrazione dei danni;
- ruoli e permessi per più operatori;
- notifiche delle scadenze;
- archiviazione cloud delle immagini.
