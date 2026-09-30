import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";

// Associa a ogni stato il testo italiano e il relativo colore Bootstrap.
const RENTAL_STATUS = {
  reserved: { label: "Prenotato", badgeClass: "text-bg-primary" },
  active: { label: "In corso", badgeClass: "text-bg-success" },
  completed: { label: "Completato", badgeClass: "text-bg-secondary" },
  cancelled: { label: "Annullato", badgeClass: "text-bg-danger" },
};

// Formatta data e ora ricevute dall'API secondo le convenzioni italiane.
function formatDateTime(value) {
  if (!value) {
    return "Non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

// Mostra gli importi con simbolo dell'euro e separatori italiani.
function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

// Crea la parte leggibile degli indirizzi di clienti e veicoli.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function RentalsPage() {
  /*
   * Gli stati degli input sono separati dai filtri applicati.
   * Così digitare una data o una ricerca non avvia una chiamata API a ogni
   * carattere: l'elenco cambia soltanto quando si preme il pulsante Cerca.
   */
  // Conserva i noleggi restituiti per la pagina corrente.
  const [rentals, setRentals] = useState([]);

  // Separa il testo scritto da quello effettivamente inviato alla ricerca.
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");

  // Conserva i filtri operativi scelti dall'utente.
  const [statusFilter, setStatusFilter] = useState("");
  // Queste date rappresentano ciò che l'utente sta ancora scrivendo.
  const [dateFromInput, setDateFromInput] = useState("");
  const [dateToInput, setDateToInput] = useState("");

  // Queste date vengono inviate all'API soltanto premendo Cerca.
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  // Conserva la pagina richiesta e i metadati restituiti da Laravel.
  const [currentPage, setCurrentPage] = useState(1);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Gestisce il caricamento iniziale e gli eventuali errori della richiesta.
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  /*
   * Ricarica l'elenco ogni volta che cambiano ricerca, filtri o pagina.
   * AbortController interrompe la richiesta se il componente viene chiuso.
   */
  useEffect(() => {
    const controller = new AbortController();

    async function loadRentals() {
      setIsLoading(true);
      setErrorMessage("");

      // Questi valori vengono trasformati nella query string della richiesta.
      const params = {
        page: currentPage,
        per_page: 10,
      };

      // I filtri vuoti non vengono inviati al backend.
      if (search) params.search = search;
      if (statusFilter) params.status = statusFilter;
      if (dateFrom) params.date_from = dateFrom;
      if (dateTo) params.date_to = dateTo;

      try {
        // Laravel applica filtri, ordinamento e paginazione sul dataset intero.
        const response = await api.get("/api/rentals", {
          params,
          signal: controller.signal,
        });

        setRentals(response.data.data);

        // Salva i dati necessari ai pulsanti Precedente e Successiva.
        setPagination({
          currentPage: response.data.meta.current_page,
          lastPage: response.data.meta.last_page,
          total: response.data.meta.total,
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          if (error.response?.status === 422) {
            setErrorMessage(
              "Controlla che l'intervallo di date selezionato sia corretto.",
            );
          } else {
            setErrorMessage(
              "Impossibile caricare i noleggi. Riprova più tardi.",
            );
          }
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadRentals();

    return () => controller.abort();
  }, [search, statusFilter, dateFrom, dateTo, currentPage]);

  // Applica ricerca e date soltanto quando l’utente preme Cerca.
  function handleSearchSubmit(event) {
    event.preventDefault();

    const normalizedSearch = searchInput.trim();

    // La data finale non può essere precedente a quella iniziale.
    if (dateFromInput && dateToInput && dateToInput < dateFromInput) {
      setErrorMessage(
        "La data finale non può essere precedente alla data iniziale.",
      );
      return;
    }

    setErrorMessage("");
    setSearch(normalizedSearch);
    setSearchInput(normalizedSearch);

    // Gli input date restituiscono già il formato YYYY-MM-DD.
    setDateFrom(dateFromInput);
    setDateTo(dateToInput);

    setCurrentPage(1);
  }

  // Ripristina ricerca, filtri e prima pagina.
  function handleSearchReset() {
    setSearchInput("");
    setSearch("");
    setStatusFilter("");
    setDateFromInput("");
    setDateToInput("");
    setDateFrom("");
    setDateTo("");
    setCurrentPage(1);
  }

  // Durante la richiesta mostra il loader condiviso dell'applicazione.
  if (isLoading) {
    return <Loader message="Caricamento noleggi..." />;
  }

  // Permette di decidere quando mostrare il pulsante Azzera.
  const hasFilters =
    searchInput ||
    search ||
    statusFilter ||
    dateFromInput ||
    dateToInput ||
    dateFrom ||
    dateTo;

  /*
   * Da qui inizia la parte visuale: intestazione, filtri, tabella e
   * paginazione. Tutti i valori economici e temporali arrivano già dall'API;
   * le funzioni di formato cambiano soltanto il modo in cui vengono mostrati.
   */

  return (
    <>
      {/* Intestazione principale e quantità complessiva dei risultati. */}
      <header className="page-header d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
          <span className="page-eyebrow">Operatività</span>
          <h1 className="mb-1">Noleggi e prenotazioni</h1>
          <p className="text-secondary mb-0">
            Controlla prenotazioni, consegne, rientri e pagamenti.
          </p>
        </div>

        <div className="d-flex flex-wrap align-items-center gap-3">
          {!errorMessage && (
            <span className="text-secondary">
              Totale: <strong>{pagination.total}</strong>
            </span>
          )}

          <Link to="/rentals/new" className="btn btn-primary">
            <i className="bi bi-calendar-plus me-2" aria-hidden="true"></i>
            Nuova prenotazione
          </Link>
        </div>
      </header>

      {/* Form dedicato a ricerca, stato e intervallo temporale. */}
      <form
        className="card border-0 shadow-sm mb-4"
        onSubmit={handleSearchSubmit}
      >
        <div className="card-body">
          <div className="row g-3 align-items-end">
            <div className="col-12 col-xl">
              <label htmlFor="rental-search" className="form-label fw-semibold">
                Cerca
              </label>
              <div className="input-group">
                <span className="input-group-text bg-white">
                  <i className="bi bi-search" aria-hidden="true"></i>
                </span>
                <input
                  id="rental-search"
                  type="search"
                  className="form-control"
                  placeholder="Cliente, targa, veicolo o numero noleggio"
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                />
              </div>
            </div>

            <div className="col-12 col-sm-6 col-lg-3 col-xl-2">
              <label htmlFor="rental-status" className="form-label fw-semibold">
                Stato
              </label>
              <select
                id="rental-status"
                className="form-select"
                value={statusFilter}
                onChange={(event) => {
                  setStatusFilter(event.target.value);
                  setCurrentPage(1);
                }}
              >
                <option value="">Tutti gli stati</option>
                <option value="reserved">Prenotati</option>
                <option value="active">In corso</option>
                <option value="completed">Completati</option>
                <option value="cancelled">Annullati</option>
              </select>
            </div>

            <div className="col-12 col-sm-6 col-lg-3 col-xl-2">
              <label
                htmlFor="rental-date-from"
                className="form-label fw-semibold"
              >
                Dal
              </label>
              <input
                id="rental-date-from"
                type="date"
                className="form-control"
                value={dateFromInput}
                max={dateToInput || undefined}
                onChange={(event) => {
                  // Aggiorna il campo senza avviare la richiesta API.
                  setDateFromInput(event.target.value);
                }}
              />
            </div>

            <div className="col-12 col-sm-6 col-lg-3 col-xl-2">
              <label
                htmlFor="rental-date-to"
                className="form-label fw-semibold"
              >
                Al
              </label>
              <input
                id="rental-date-to"
                type="date"
                className="form-control"
                value={dateToInput}
                min={dateFromInput || undefined}
                onChange={(event) => {
                  // Aggiorna il campo senza avviare la richiesta API.
                  setDateToInput(event.target.value);
                }}
              />
            </div>

            <div className="col-12 col-sm-auto d-grid">
              <button type="submit" className="btn btn-primary">
                Cerca
              </button>
            </div>

            {hasFilters && (
              <div className="col-12 col-sm-auto d-grid">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={handleSearchReset}
                >
                  <i className="bi bi-x-lg me-2" aria-hidden="true"></i>
                  Azzera
                </button>
              </div>
            )}
          </div>

          <div className="form-text mt-2">
            L'intervallo mostra i noleggi che occupano almeno una parte del
            periodo selezionato.
          </div>
        </div>
      </form>

      {/* Mostra gli errori generali della richiesta. */}
      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {/* Messaggio mostrato quando non esistono risultati. */}
      {!errorMessage && rentals.length === 0 && (
        <div className="alert alert-info" role="status">
          {hasFilters
            ? "Nessun noleggio corrisponde alla ricerca o ai filtri selezionati."
            : "Non ci sono ancora noleggi o prenotazioni registrati."}
        </div>
      )}

      {/* Elenco principale mostrato soltanto quando sono presenti risultati. */}
      {!errorMessage && rentals.length > 0 && (
        <>
          <section
            className="card border-0 shadow-sm overflow-hidden"
            aria-labelledby="rentals-list-title"
          >
            <div className="card-body border-bottom">
              <h2 id="rentals-list-title" className="h5 mb-1">
                Elenco operativo
              </h2>
              <p className="text-secondary small mb-0">
                Periodi, clienti, veicoli e situazione dei pagamenti.
              </p>
            </div>

            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Noleggio</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Veicolo</th>
                    <th scope="col">Periodo</th>
                    <th scope="col">Pagamento</th>
                    <th scope="col">Stato</th>
                  </tr>
                </thead>
                <tbody>
                  {rentals.map((rental) => {
                    // Prepara i dati derivati usati dalla singola riga.
                    const status =
                      RENTAL_STATUS[rental.status] ?? RENTAL_STATUS.reserved;
                    const customer = rental.customer;
                    const vehicle = rental.vehicle;
                    const balanceDue = Number(rental.balance_due ?? 0);

                    // Gli annullati conservano il preventivo ma non un debito.
                    const isCancelled = rental.status === "cancelled";

                    return (
                      <tr key={rental.id}>
                        <td>
                          <Link
                            to={`/rentals/${rental.id}`}
                            className="fw-semibold text-decoration-none"
                          >
                            #{rental.id}
                          </Link>
                          <div className="small text-secondary">
                            {rental.chargeable_days}{" "}
                            {rental.chargeable_days === 1 ? "giorno" : "giorni"}{" "}
                            {isCancelled ? "previsti" : "addebitati"}
                          </div>
                        </td>

                        {/* Cliente collegato alla relativa scheda. */}
                        <td>
                          {customer ? (
                            <>
                              <Link
                                to={`/customers/${customer.id}/${createSlug(
                                  `${customer.first_name} ${customer.last_name}`,
                                )}`}
                                className="fw-semibold text-decoration-none"
                              >
                                {customer.first_name} {customer.last_name}
                              </Link>
                              <div className="small text-secondary">
                                {customer.phone ||
                                  customer.email ||
                                  "Contatto non inserito"}
                              </div>
                            </>
                          ) : (
                            <span className="text-secondary">
                              Cliente non disponibile
                            </span>
                          )}
                        </td>

                        {/* Veicolo collegato alla relativa scheda. */}
                        <td>
                          {vehicle ? (
                            <>
                              <Link
                                to={`/vehicles/${vehicle.id}/${createSlug(
                                  `${vehicle.brand} ${vehicle.model}`,
                                )}`}
                                className="fw-semibold text-decoration-none"
                              >
                                {vehicle.brand} {vehicle.model}
                              </Link>
                              <div className="small text-secondary">
                                Targa {vehicle.license_plate}
                              </div>
                            </>
                          ) : (
                            <span className="text-secondary">
                              Veicolo non disponibile
                            </span>
                          )}
                        </td>

                        {/* Periodo previsto della prenotazione o del noleggio. */}
                        <td>
                          <div>
                            <span className="small text-secondary">Dal </span>
                            <span className="fw-semibold">
                              {formatDateTime(rental.starts_at)}
                            </span>
                          </div>
                          <div>
                            <span className="small text-secondary">Al </span>
                            <span className="fw-semibold">
                              {formatDateTime(rental.expected_ends_at)}
                            </span>
                          </div>
                        </td>

                        {/* Totale, importo già versato e saldo residuo. */}
                        <td>
                          {isCancelled ? (
                            <>
                              <div className="small text-secondary">
                                Importo previsto
                              </div>
                              <div className="fw-semibold text-secondary">
                                {formatCurrency(rental.total_amount)}
                              </div>
                              {Number(rental.amount_paid) > 0 && (
                                <div className="small text-warning-emphasis">
                                  Versato prima dell'annullamento:{" "}
                                  {formatCurrency(rental.amount_paid)}
                                </div>
                              )}
                              <div className="small fw-semibold text-secondary">
                                Nessun saldo dovuto
                              </div>
                            </>
                          ) : (
                            <>
                              <div className="fw-semibold">
                                {formatCurrency(rental.total_amount)}
                              </div>
                              <div className="small text-secondary">
                                Pagato: {formatCurrency(rental.amount_paid)}
                              </div>
                              <div
                                className={`small fw-semibold ${
                                  balanceDue > 0
                                    ? "text-danger"
                                    : "text-success"
                                }`}
                              >
                                {balanceDue > 0
                                  ? `Da saldare: ${formatCurrency(balanceDue)}`
                                  : "Saldato"}
                              </div>
                            </>
                          )}
                        </td>

                        {/* Stato operativo tradotto e distinto per colore. */}
                        <td>
                          <div className="d-flex align-items-center gap-2">
                            <span className={`badge ${status.badgeClass}`}>
                              {status.label}
                            </span>

                            <Link
                              to={`/rentals/${rental.id}`}
                              className="btn btn-sm btn-outline-primary"
                              aria-label={`Apri il noleggio numero ${rental.id}`}
                              title="Apri dettaglio"
                            >
                              <i
                                className="bi bi-arrow-right"
                                aria-hidden="true"
                              ></i>
                            </Link>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </section>

          {/* La paginazione compare soltanto quando esiste più di una pagina. */}
          {pagination.lastPage > 1 && (
            <nav
              className="d-flex justify-content-center align-items-center gap-3 mt-4"
              aria-label="Paginazione dei noleggi"
            >
              <button
                type="button"
                className={`btn btn-outline-primary ${
                  pagination.currentPage === 1 ? "invisible" : ""
                }`}
                disabled={pagination.currentPage === 1}
                onClick={() => setCurrentPage((page) => page - 1)}
              >
                Precedente
              </button>

              <span>
                Pagina {pagination.currentPage} di {pagination.lastPage}
              </span>

              <button
                type="button"
                className={`btn btn-outline-primary ${
                  pagination.currentPage === pagination.lastPage
                    ? "invisible"
                    : ""
                }`}
                disabled={pagination.currentPage === pagination.lastPage}
                onClick={() => setCurrentPage((page) => page + 1)}
              >
                Successiva
              </button>
            </nav>
          )}
        </>
      )}
    </>
  );
}

export default RentalsPage;
