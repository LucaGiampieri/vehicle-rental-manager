# Frontend · Vehicle Rental Manager

Interfaccia React del gestionale autonoleggio. Il backend Laravel si trova nella cartella `../backend` del progetto completo.

## Stato attuale

- Accesso e uscita tramite cookie di sessione Laravel Sanctum.
- Controllo della sessione su `/api/user` quando si apre l'applicazione.
- Rotte interne protette, con nome dell'utente nella barra di navigazione.
- Dashboard e pagina Veicoli ancora introduttive: dati, filtri e grafica saranno aggiunti nei prossimi passi.

Non c'è una pagina di registrazione pubblica: l'utente deve essere creato nel backend.

## Avvio in locale

Apri due terminali dalla radice del progetto. Avvia prima Laravel:

```powershell
cd backend
php artisan serve --host=localhost --port=8000
```

Nel secondo terminale prepara e avvia React:

```powershell
cd frontend
Copy-Item .env.example .env.local
npm ci
npm run dev
```

Apri `http://localhost:5173`. Se hai già installato le dipendenze e creato `.env.local`, bastano `npm run dev` e il server backend. Crea l'utente con il comando `php artisan app:create-user` dalla cartella backend, seguendo le indicazioni a schermo.

Usa `localhost` sia per il frontend sia per il backend: passando da `localhost` a `127.0.0.1` potresti non condividere i cookie di sessione. Dopo aver cambiato `.env.local`, riavvia Vite. Le variabili `VITE_` sono visibili nel browser: inserisci solo l'indirizzo pubblico dell'API, mai chiavi o password.

## Dove trovare cosa

| File | Responsabilità |
| --- | --- |
| `src/main.jsx` | Avvio di React e caricamento degli stili. |
| `src/App.jsx` | Rotte pubbliche e protette. |
| `src/context/AuthProvider.jsx` | Controllo sessione, accesso e uscita. |
| `src/context/AuthContext.js` | Hook `useAuth()` per leggere la sessione. |
| `src/components/RequireAuth.jsx` | Reindirizzamento al login delle pagine riservate. |
| `src/layouts/DefaultLayout.jsx` | Barra di navigazione condivisa dalle pagine interne. |
| `src/services/api.js` | Client Axios con cookie e token CSRF. |
| `src/pages/` | Schermate dell'applicazione. |
| `src/index.css` | Stili locali, caricati dopo Bootstrap. |

## Controlli

```powershell
npm run lint
npm run build
```

Controlla anche nel browser: aprendo direttamente `/vehicles` senza sessione devi arrivare al login; dopo un accesso valido devi vedere il tuo nome; uscendo devi tornare al login. Il server Laravel deve essere attivo per queste verifiche.
