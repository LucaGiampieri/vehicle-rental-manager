# Vehicle Rental Manager — Backend

**English** | [Italiano](README.it.md)

Laravel REST API backend for a vehicle rental management system.

The system manages vehicles, customers, reservations, rentals, expenses, garage spaces, photographs, and financial and operational statistics.

## Project status

The Laravel backend MVP is complete and covered by 161 automated tests.

The React frontend is currently under development.

## Technologies

## Technologies

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- MySQL
- PHPUnit
- Laravel Pint

## Features

- cookie-based authentication with Laravel Sanctum;
- public registration disabled;
- management account creation through an Artisan command;
- password recovery and reset;
- complete vehicle management;
- search, filters, and pagination;
- vehicle photo galleries;
- customer management;
- expense management;
- reservation and rental management;
- rental overlap prevention;
- vehicle pickup and return workflows;
- mileage tracking;
- cell-based visual garage management;
- vehicle movements and movement history;
- financial and operational dashboard;
- dates and times normalized to UTC.

## Installation

Enter the backend directory:

```powershell
cd backend
```

Install the dependencies:

```powershell
composer install
```

Create the `.env` file:

```powershell
Copy-Item .env.example .env
```

Generate the application key:

```powershell
php artisan key:generate
```

Configure the database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=autonoleggio
DB_USERNAME=root
DB_PASSWORD=
```

Configure the authorized frontend:

```env
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

Create the database tables:

```powershell
php artisan migrate
```

Create the public symbolic link for vehicle images:

```powershell
php artisan storage:link
```

Start Laravel:

```powershell
php artisan serve
```

The backend will be available at:

```text
http://127.0.0.1:8000
```

## PHP extensions

The GD extension must be enabled to upload and test images:

```ini
extension=gd
```

Check that it is enabled:

```powershell
php -m | Select-String "gd"
```

The standard Laravel extensions are also required, including:

- PDO MySQL;
- Mbstring;
- OpenSSL;
- Fileinfo.

## Creating an account

Public registration is disabled.

Create a management account with:

```powershell
php artisan app:create-user
```

The command asks for:

- name;
- email address;
- password containing at least 12 characters;
- password confirmation.

## Demo data

Demo data can be added in a local environment with:

```powershell
php artisan db:seed
```

Demo credentials:

```text
Email: admin@example.com
Password: PasswordDemo!2026
```

The demo seeder does not run automatically in production.

## Authentication from React

Before logging in, the frontend must request the CSRF cookie:

```http
GET /sanctum/csrf-cookie
```

It can then submit the login request:

```http
POST /login
```

JSON body:

```json
{
  "email": "admin@example.com",
  "password": "PasswordDemo!2026"
}
```

Axios requests must use:

```javascript
withCredentials: true;
```

End the authenticated session with:

```http
POST /logout
```

## Main APIs

All `/api/*` routes require Sanctum authentication.

### User

| Method | Route       | Description                    |
| ------ | ----------- | ------------------------------ |
| GET    | `/api/user` | Returns the authenticated user |

### Vehicles

| Method | Route                     | Description       |
| ------ | ------------------------- | ----------------- |
| GET    | `/api/vehicles`           | Lists vehicles    |
| POST   | `/api/vehicles`           | Creates a vehicle |
| GET    | `/api/vehicles/{vehicle}` | Shows a vehicle   |
| PATCH  | `/api/vehicles/{vehicle}` | Updates a vehicle |
| DELETE | `/api/vehicles/{vehicle}` | Deletes a vehicle |

Available filters:

```text
search
type
is_active
per_page
```

Example:

```http
GET /api/vehicles?search=Fiat&type=car&is_active=true&per_page=15
```

### Vehicle images

| Method | Route                                | Description             |
| ------ | ------------------------------------ | ----------------------- |
| GET    | `/api/vehicles/{vehicle}/images`     | Lists the image gallery |
| POST   | `/api/vehicles/{vehicle}/images`     | Uploads an image        |
| PATCH  | `/api/vehicle-images/{vehicleImage}` | Updates image metadata  |
| DELETE | `/api/vehicle-images/{vehicleImage}` | Deletes an image        |

Uploads use `multipart/form-data`.

Available fields:

| Field        | Description                 |
| ------------ | --------------------------- |
| `image`      | JPG, PNG, or WebP file      |
| `category`   | Image category              |
| `caption`    | Optional description        |
| `is_primary` | Sets the gallery cover      |
| `sort_order` | Position within the gallery |

Categories:

```text
exterior
interior
plate
damage
other
```

Limits and behavior:

- up to 10 images per vehicle;
- up to 5 MB per image;
- the first image automatically becomes the cover;
- deleting the cover promotes the first remaining image.

### Customers

| Method | Route                       | Description        |
| ------ | --------------------------- | ------------------ |
| GET    | `/api/customers`            | Lists customers    |
| POST   | `/api/customers`            | Creates a customer |
| GET    | `/api/customers/{customer}` | Shows a customer   |
| PATCH  | `/api/customers/{customer}` | Updates a customer |
| DELETE | `/api/customers/{customer}` | Deletes a customer |

Available filters:

```text
search
is_active
per_page
```

The search covers first name, last name, email address, telephone number, tax code, and driving license number.

### Rentals

| Method | Route                            | Description                |
| ------ | -------------------------------- | -------------------------- |
| GET    | `/api/rentals`                   | Lists rentals              |
| POST   | `/api/rentals`                   | Creates a reservation      |
| GET    | `/api/rentals/{rental}`          | Shows a rental             |
| PATCH  | `/api/rentals/{rental}`          | Updates a reservation      |
| DELETE | `/api/rentals/{rental}`          | Deletes when permitted     |
| PATCH  | `/api/rentals/{rental}/activate` | Records the vehicle pickup |
| PATCH  | `/api/rentals/{rental}/complete` | Records the vehicle return |
| PATCH  | `/api/rentals/{rental}/cancel`   | Cancels the reservation    |

Available statuses:

```text
reserved
active
completed
cancelled
```

Available filters:

```text
search
status
vehicle_id
customer_id
date_from
date_to
per_page
```

Date filters use the following format:

```text
YYYY-MM-DD
```

### Expenses

| Method | Route                     | Description        |
| ------ | ------------------------- | ------------------ |
| GET    | `/api/expenses`           | Lists expenses     |
| POST   | `/api/expenses`           | Records an expense |
| GET    | `/api/expenses/{expense}` | Shows an expense   |
| PATCH  | `/api/expenses/{expense}` | Updates an expense |
| DELETE | `/api/expenses/{expense}` | Deletes an expense |

Available filters:

```text
vehicle_id
category
date_from
date_to
expires_before
search
per_page
```

Main categories:

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

### Garage

| Method | Route                                      | Description                   |
| ------ | ------------------------------------------ | ----------------------------- |
| GET    | `/api/parking-spaces`                      | Lists parking cells           |
| POST   | `/api/parking-spaces`                      | Creates a parking cell        |
| GET    | `/api/parking-spaces/{parkingSpace}`       | Shows a parking cell          |
| PATCH  | `/api/parking-spaces/{parkingSpace}`       | Updates a parking cell        |
| DELETE | `/api/parking-spaces/{parkingSpace}`       | Deletes a parking cell        |
| POST   | `/api/garage/park`                         | Parks a vehicle               |
| PATCH  | `/api/garage/vehicles/{vehicle}/move`      | Moves a parked vehicle        |
| PATCH  | `/api/garage/vehicles/{vehicle}/unpark`    | Removes a vehicle from garage |
| GET    | `/api/garage/movements`                    | Lists all movements           |
| GET    | `/api/garage/vehicles/{vehicle}/movements` | Lists movements for a vehicle |

### Dashboard

```http
GET /api/dashboard
```

Available filters:

```text
date_from
date_to
vehicle_id
```

When no dates are provided, the dashboard automatically analyzes a 30-day period.

The dashboard returns:

- contracted revenue;
- completed rental revenue;
- collected amounts;
- outstanding amounts;
- expenses;
- projected and realized profit;
- fleet status;
- garage occupancy;
- vehicle usage and idle time;
- upcoming deadlines.

## Dates and times

The backend operates in UTC.

Date-time values are returned in ISO 8601 format:

```text
2026-09-12T08:00:00.000000Z
```

Date-only values are returned as:

```text
2026-09-12
```

The frontend can display the local time with JavaScript:

```javascript
new Date(value).toLocaleString();
```

## Payments

In the MVP, payments are managed through the following field:

```text
amount_paid
```

The API also returns:

```text
total_amount
balance_due
```

A detailed payment history can be added in a future version.

## Tests and code quality

Run the complete test suite:

```powershell
php artisan test
```

Check code formatting:

```powershell
.\vendor\bin\pint --test
```

Apply automatic formatting:

```powershell
.\vendor\bin\pint
```

Audit the dependencies:

```powershell
composer audit
```

## Image storage

In the local environment, images are stored in:

```text
storage/app/public/vehicles
```

They are made publicly available through:

```text
public/storage
```

On a hosting platform with an ephemeral filesystem, persistent storage must be configured, such as a persistent disk or an S3-compatible service.

## Possible future developments

- React frontend;
- detailed payment history;
- pickup and return photographs;
- vehicle damage records;
- roles and permissions for multiple operators;
- deadline notifications;
- cloud image storage.
