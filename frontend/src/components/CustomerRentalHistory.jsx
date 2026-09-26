import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import api from "../services/api";

// Configurazione visiva dei diversi stati.
const RENTAL_STATUS_CONFIG = {
  reserved: {
    label: "Prenotato",
    className: "text-bg-primary",
    icon: "bi-calendar-check",
  },
  active: {
    label: "In corso",
    className: "text-bg-success",
    icon: "bi-key",
  },
  completed: {
    label: "Completato",
    className: "text-bg-dark",
    icon: "bi-check-circle",
  },
  cancelled: {
    label: "Annullato",
    className: "text-bg-secondary",
    icon: "bi-x-circle",
  },
};

// Formatta data e ora secondo le convenzioni italiane.
function formatDateTime(value) {
  if (!value) {
    return "Non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

// Formatta un importo in euro.
function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

// Crea una parte leggibile dell’indirizzo del veicolo.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function CustomerRentalHistory({ customerId }) {
  // Conserva i noleggi della pagina corrente.
  const [rentals, setRentals] = useState([]);

  // Conserva il filtro scelto dall’utente.
  const [statusFilter, setStatusFilter] = useState("");

  // Conserva la pagina corrente.
  const [currentPage, setCurrentPage] = useState(1);

  // Conserva i dati della paginazione Laravel.
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Gestisce caricamento ed errore dello storico.
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function loadRentals() {
      setIsLoading(true);
      setErrorMessage("");

      const params = {
        customer_id: customerId,
        page: currentPage,
        per_page: 5,
      };

      if (statusFilter) {
        params.status = statusFilter;
      }

      try {
        const response = await api.get("/api/rentals", {
          params,
          signal: controller.signal,
        });

        setRentals(response.data.data);

        setPagination({
          currentPage: response.data.meta.current_page,
          lastPage: response.data.meta.last_page,
          total: response.data.meta.total,
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare lo storico dei noleggi.");
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadRentals();

    return () => controller.abort();
  }, [customerId, statusFilter, currentPage]);

  return (
    <section className="mb-4" aria-labelledby="rental-history-title">
      <div className="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div>
          <span className="page-eyebrow">Cronologia</span>

          <h2 id="rental-history-title" className="h4 mb-1">
            Storico noleggi
          </h2>

          <p className="text-secondary mb-0">
            Prenotazioni, noleggi in corso, conclusi e annullati.
          </p>
        </div>

        <div>
          <label
            htmlFor="rental-history-status"
            className="form-label small fw-semibold"
          >
            Filtra per stato
          </label>

          <select
            id="rental-history-status"
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
      </div>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {isLoading && (
        <div
          className="card border-0 shadow-sm"
          role="status"
          aria-live="polite"
        >
          <div className="card-body p-4 text-center">
            <span
              className="spinner-border spinner-border-sm text-primary me-2"
              aria-hidden="true"
            ></span>
            Caricamento storico...
          </div>
        </div>
      )}

      {!isLoading && !errorMessage && rentals.length === 0 && (
        <div className="alert alert-info" role="status">
          {statusFilter
            ? "Nessun noleggio corrisponde allo stato selezionato."
            : "Il cliente non possiede ancora noleggi registrati."}
        </div>
      )}

      {!isLoading && !errorMessage && rentals.length > 0 && (
        <>
          <div className="card border-0 shadow-sm overflow-hidden">
            <div className="card-body border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
              <div>
                <h3 className="h5 mb-1">Movimenti registrati</h3>

                <p className="text-secondary small mb-0">
                  Ordinati dalla data di inizio più recente.
                </p>
              </div>

              <span className="text-secondary small">
                Risultati: <strong>{pagination.total}</strong>
              </span>
            </div>

            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Stato</th>
                    <th scope="col">Veicolo</th>
                    <th scope="col">Periodo</th>
                    <th scope="col">Importi</th>
                    <th scope="col" className="text-end">
                      Azioni
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {rentals.map((rental) => {
                    const status =
                      RENTAL_STATUS_CONFIG[rental.status] ??
                      RENTAL_STATUS_CONFIG.cancelled;

                    const vehicle = rental.vehicle;

                    return (
                      <tr key={rental.id}>
                        <td>
                          <span className={`badge ${status.className}`}>
                            <i
                              className={`bi ${status.icon} me-2`}
                              aria-hidden="true"
                            ></i>

                            {status.label}
                          </span>
                        </td>

                        <td>
                          {vehicle ? (
                            <>
                              <div className="fw-semibold">
                                {vehicle.brand} {vehicle.model}
                              </div>

                              <div className="small text-secondary">
                                Targa: {vehicle.license_plate}
                              </div>
                            </>
                          ) : (
                            "Veicolo non disponibile"
                          )}
                        </td>

                        <td>
                          <div>
                            <span className="small text-secondary">Dal:</span>{" "}
                            {formatDateTime(rental.starts_at)}
                          </div>

                          <div>
                            <span className="small text-secondary">Al:</span>{" "}
                            {formatDateTime(rental.expected_ends_at)}
                          </div>
                        </td>

                        <td>
                          <div>
                            Totale:{" "}
                            <strong>
                              {formatCurrency(rental.total_amount)}
                            </strong>
                          </div>

                          <div className="small text-success">
                            Pagato: {formatCurrency(rental.amount_paid)}
                          </div>

                          <div
                            className={`small ${
                              Number(rental.balance_due) > 0
                                ? "text-danger"
                                : "text-secondary"
                            }`}
                          >
                            Da saldare: {formatCurrency(rental.balance_due)}
                          </div>
                        </td>

                        <td className="text-end">
                          {vehicle && (
                            <Link
                              to={`/vehicles/${vehicle.id}/${createSlug(
                                `${vehicle.brand} ${vehicle.model}`,
                              )}`}
                              className="btn btn-sm btn-outline-primary"
                              aria-label={`Apri ${vehicle.brand} ${vehicle.model}`}
                            >
                              Veicolo
                              <i
                                className="bi bi-arrow-right ms-2"
                                aria-hidden="true"
                              ></i>
                            </Link>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>

          {pagination.lastPage > 1 && (
            <nav
              className="d-flex justify-content-center align-items-center gap-3 mt-4"
              aria-label="Paginazione dello storico noleggi"
            >
              <button
                type="button"
                className={`btn btn-outline-primary ${
                  pagination.currentPage === 1 ? "invisible" : ""
                }`}
                disabled={pagination.currentPage === 1}
                onClick={() => {
                  setCurrentPage((page) => page - 1);
                }}
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
                onClick={() => {
                  setCurrentPage((page) => page + 1);
                }}
              >
                Successiva
              </button>
            </nav>
          )}
        </>
      )}
    </section>
  );
}

export default CustomerRentalHistory;
