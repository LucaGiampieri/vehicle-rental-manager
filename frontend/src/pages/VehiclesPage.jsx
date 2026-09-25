import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";
import createSlug from "../utils/createSlug";

// Associa i valori tecnici del backend alle etichette italiane.
const VEHICLE_TYPE_LABELS = {
  car: "Auto",
  motorcycle: "Moto",
  van: "Furgone",
  camper: "Camper",
  truck: "Camion",
  bus: "Autobus",
  other: "Altro",
};

// Definisce etichetta, icona e stile di ogni stato operativo.
const OPERATIONAL_STATUS_CONFIG = {
  available: {
    label: "Disponibile",
    icon: "bi-check-circle-fill",
    className: "vehicle-operational-status--available",
  },
  reserved: {
    label: "Prenotato",
    icon: "bi-calendar-check-fill",
    className: "vehicle-operational-status--reserved",
  },
  rented: {
    label: "Noleggiato",
    icon: "bi-key-fill",
    className: "vehicle-operational-status--rented",
  },
  inactive: {
    label: "Disattivato",
    icon: "bi-slash-circle-fill",
    className: "vehicle-operational-status--inactive",
  },
};

// Formatta gli importi utilizzando euro e convenzioni italiane.
function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

// Formatta una data completa soltanto quando è disponibile.
function formatDateTime(value) {
  if (!value) {
    return "Data non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "short",
    timeStyle: "short",
  }).format(new Date(value));
}

// Compone il nome completo del cliente collegato al noleggio.
function getCustomerName(rental) {
  const customer = rental?.customer;

  if (!customer) {
    return "Cliente non disponibile";
  }

  return `${customer.first_name} ${customer.last_name}`.trim();
}

// Mostra lo stato e, solo quando serve, il prossimo momento importante.
function VehicleOperationalStatus({ vehicle }) {
  const config =
    OPERATIONAL_STATUS_CONFIG[vehicle.operational_status] ??
    OPERATIONAL_STATUS_CONFIG.available;

  let detail = null;
  let dateLabel = null;
  let dateValue = null;

  if (vehicle.operational_status === "rented" && vehicle.active_rental) {
    detail = getCustomerName(vehicle.active_rental);
    dateLabel = "Rientro";
    dateValue = vehicle.active_rental.expected_ends_at;
  }

  if (
    vehicle.operational_status === "reserved" &&
    vehicle.next_reservation
  ) {
    detail = getCustomerName(vehicle.next_reservation);
    dateLabel = "Inizio";
    dateValue = vehicle.next_reservation.starts_at;
  }

  return (
    <div className={`vehicle-operational-status ${config.className}`}>
      <div className="vehicle-operational-status__heading">
        <i className={`bi ${config.icon}`} aria-hidden="true"></i>
        <strong>{config.label}</strong>
      </div>

      {detail && (
        <span className="vehicle-operational-status__detail">{detail}</span>
      )}

      {dateLabel && dateValue && (
        <span className="vehicle-operational-status__date">
          {dateLabel}: {formatDateTime(dateValue)}
        </span>
      )}
    </div>
  );
}

function VehiclesPage() {
  // Conserva i veicoli presenti nella pagina corrente.
  const [vehicles, setVehicles] = useState([]);

  // Indica quale pagina vogliamo richiedere al backend.
  const [currentPage, setCurrentPage] = useState(1);

  // Conserva il testo digitato e quello effettivamente ricercato.
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");

  // Conserva i filtri selezionati.
  const [typeFilter, setTypeFilter] = useState("");
  const [activeFilter, setActiveFilter] = useState("");

  // Conserva i metadati della paginazione Laravel.
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Gestisce caricamento ed eventuali errori della richiesta.
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  // Applica la ricerca senza ricaricare l'intera pagina.
  function handleSearchSubmit(event) {
    event.preventDefault();
    setCurrentPage(1);
    setSearch(searchInput.trim());
  }

  // Ripristina ricerca, filtri e prima pagina.
  function handleSearchReset() {
    setSearchInput("");
    setSearch("");
    setTypeFilter("");
    setActiveFilter("");
    setCurrentPage(1);
  }

  useEffect(() => {
    const controller = new AbortController();

    async function loadVehicles() {
      setIsLoading(true);
      setErrorMessage("");

      try {
        const response = await api.get("/api/vehicles", {
          params: {
            page: currentPage,
            per_page: 10,
            search: search || undefined,
            type: typeFilter || undefined,
            is_active: activeFilter || undefined,
          },
          signal: controller.signal,
        });

        setVehicles(response.data.data);
        setPagination({
          currentPage: response.data.meta.current_page,
          lastPage: response.data.meta.last_page,
          total: response.data.meta.total,
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare i veicoli. Riprova più tardi.");
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadVehicles();

    return () => controller.abort();
  }, [currentPage, search, typeFilter, activeFilter]);

  if (isLoading) {
    return <Loader message="Caricamento veicoli..." />;
  }

  return (
    <>
      {/* Intestazione principale della gestione della flotta. */}
      <header className="vehicles-page-header">
        <div>
          <span className="page-eyebrow">Gestione flotta</span>
          <h1 className="mb-2">Veicoli</h1>
          <p className="text-secondary mb-0">
            Controlla disponibilità, prenotazioni e noleggi dell'intera flotta.
          </p>
        </div>

        <Link to="/vehicles/new" className="btn btn-primary">
          <i className="bi bi-plus-lg me-2" aria-hidden="true"></i>
          Nuovo veicolo
        </Link>
      </header>

      {/* Ricerca e filtri inviati alle API Laravel. */}
      <form
        className="card border-0 shadow-sm mb-4 vehicles-filter-card"
        onSubmit={handleSearchSubmit}
      >
        <div className="card-body">
          <div className="vehicles-filter-card__header">
            <div>
              <span className="page-eyebrow">Ricerca e filtri</span>
              <h2 className="h5 mb-1">Trova un veicolo</h2>
              <p className="text-secondary small mb-0">
                Cerca per targa, marca o modello e restringi i risultati.
              </p>
            </div>

            <i
              className="bi bi-funnel vehicles-filter-card__icon"
              aria-hidden="true"
            ></i>
          </div>

          <div className="row g-3 align-items-end">
            <div className="col-12 col-lg">
              <label
                htmlFor="vehicle-search"
                className="form-label fw-semibold"
              >
                Cerca un veicolo
              </label>

              <div className="input-group">
                <span className="input-group-text bg-white">
                  <i className="bi bi-search" aria-hidden="true"></i>
                </span>

                <input
                  id="vehicle-search"
                  type="search"
                  className="form-control"
                  placeholder="Targa, marca o modello"
                  value={searchInput}
                  onChange={(event) => setSearchInput(event.target.value)}
                />
              </div>
            </div>

            <div className="col-12 col-md-6 col-lg-2">
              <label htmlFor="vehicle-type" className="form-label fw-semibold">
                Tipo
              </label>

              <select
                id="vehicle-type"
                className="form-select"
                value={typeFilter}
                onChange={(event) => {
                  setTypeFilter(event.target.value);
                  setCurrentPage(1);
                }}
              >
                <option value="">Tutti i tipi</option>
                <option value="car">Auto</option>
                <option value="motorcycle">Moto</option>
                <option value="van">Furgone</option>
                <option value="camper">Camper</option>
                <option value="truck">Camion</option>
                <option value="bus">Autobus</option>
                <option value="other">Altro</option>
              </select>
            </div>

            <div className="col-12 col-md-6 col-lg-2">
              <label
                htmlFor="vehicle-activation"
                className="form-label fw-semibold"
              >
                Attivazione
              </label>

              <select
                id="vehicle-activation"
                className="form-select"
                value={activeFilter}
                onChange={(event) => {
                  setActiveFilter(event.target.value);
                  setCurrentPage(1);
                }}
              >
                <option value="">Tutti</option>
                <option value="true">Mezzi attivi</option>
                <option value="false">Mezzi disattivati</option>
              </select>
            </div>

            <div className="col-12 col-sm-auto d-grid">
              <button type="submit" className="btn btn-primary">
                Cerca
              </button>
            </div>

            {(searchInput || search || typeFilter || activeFilter) && (
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
            Puoi cercare utilizzando anche solo una parte del testo.
          </div>
        </div>
      </form>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {!errorMessage && vehicles.length === 0 && (
        <div className="alert alert-info" role="status">
          {search ? (
            <>
              Nessun veicolo trovato per <strong>“{search}”</strong>.
            </>
          ) : typeFilter || activeFilter ? (
            "Nessun veicolo corrisponde ai filtri selezionati."
          ) : (
            "Non ci sono ancora veicoli registrati."
          )}
        </div>
      )}

      {!errorMessage && vehicles.length > 0 && (
        <section
          className="vehicles-list-section"
          aria-labelledby="vehicles-list-title"
        >
          <header className="vehicles-list-section__header">
            <div>
              <span className="page-eyebrow">Archivio flotta</span>
              <h2 id="vehicles-list-title" className="h5 mb-1">
                Elenco veicoli
              </h2>
              <p className="text-secondary small mb-0">
                Ogni riga mostra disponibilità e prossimo impegno del mezzo.
              </p>
            </div>

            <span className="vehicles-list-section__total">
              {pagination.total}{" "}
              {pagination.total === 1
                ? "veicolo registrato"
                : "veicoli registrati"}
            </span>
          </header>

          <div className="table-responsive vehicles-table-wrapper">
            <table className="table align-middle vehicles-table">
              {/* Le larghezze rendono le informazioni regolari sul desktop. */}
              <colgroup>
                <col className="vehicles-table__vehicle-column" />
                <col className="vehicles-table__rate-column" />
                <col className="vehicles-table__status-column" />
                <col className="vehicles-table__actions-column" />
              </colgroup>

              <thead>
                <tr>
                  <th scope="col">Veicolo</th>
                  <th scope="col">Tariffa</th>
                  <th scope="col">Stato</th>
                  <th scope="col" className="text-end">
                    Azioni
                  </th>
                </tr>
              </thead>

              <tbody>
                {vehicles.map((vehicle) => (
                  <tr key={vehicle.id}>
                    <td data-label="Veicolo">
                      <div className="vehicle-table-identity">
                        {vehicle.primary_image ? (
                          <img
                            src={vehicle.primary_image.url}
                            alt={
                              vehicle.primary_image.caption ||
                              `Foto di ${vehicle.brand} ${vehicle.model}`
                            }
                            className="vehicle-thumbnail"
                            loading="lazy"
                          />
                        ) : (
                          <div
                            className="vehicle-thumbnail vehicle-thumbnail--placeholder"
                            aria-label="Fotografia non disponibile"
                          >
                            <i
                              className="bi bi-car-front"
                              aria-hidden="true"
                            ></i>
                          </div>
                        )}

                        <div>
                          <span className="vehicle-table__name">
                            {vehicle.brand} {vehicle.model}
                          </span>
                          <span className="vehicle-table__plate">
                            <small>Targa</small>
                            {vehicle.license_plate}
                          </span>
                          <span className="vehicle-table__meta">
                            <span>
                              {VEHICLE_TYPE_LABELS[vehicle.type] ?? vehicle.type}
                            </span>
                            <span aria-hidden="true">·</span>
                            <span>
                              {Number(vehicle.mileage).toLocaleString("it-IT")} km
                            </span>
                          </span>
                        </div>
                      </div>
                    </td>

                    <td data-label="Tariffa">
                      <div className="vehicle-table-rate">
                        <strong>
                          {formatCurrency(vehicle.daily_rate)}
                          <small>/giorno</small>
                        </strong>
                      </div>
                    </td>

                    <td data-label="Stato">
                      <VehicleOperationalStatus vehicle={vehicle} />
                    </td>

                    <td className="text-end vehicle-table__actions">
                      <Link
                        to={`/vehicles/${vehicle.id}/${createSlug(
                          [vehicle.brand, vehicle.model].join(" "),
                        )}`}
                        className="btn btn-sm btn-outline-primary"
                        aria-label={`Visualizza ${vehicle.brand} ${vehicle.model}`}
                      >
                        <i className="bi bi-eye me-2" aria-hidden="true"></i>
                        Dettagli
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pagination.lastPage > 1 && (
            <nav
              className="vehicles-pagination d-flex justify-content-center align-items-center gap-3"
              aria-label="Paginazione dei veicoli"
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
        </section>
      )}
    </>
  );
}

export default VehiclesPage;
