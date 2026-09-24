import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import createSlug from "../utils/createSlug";
import api from "../services/api";

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

function VehiclesPage() {
  // Conserva i veicoli presenti nella pagina corrente.
  const [vehicles, setVehicles] = useState([]);

  // Indica quale pagina vogliamo richiedere al backend.
  const [currentPage, setCurrentPage] = useState(1);

  // Conserva quello che l'utente sta scrivendo nel campo.
  const [searchInput, setSearchInput] = useState("");

  // Conserva la ricerca effettivamente inviata al backend.
  const [search, setSearch] = useState("");

  // Conserva il tipo di veicolo selezionato.
  const [typeFilter, setTypeFilter] = useState("");

  // Conserva lo stato attivo/disattivato selezionato.
  const [activeFilter, setActiveFilter] = useState("");

  // Conserva le informazioni sulla paginazione restituite da Laravel.
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Gestisce il caricamento e gli eventuali errori.
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  // Avvia la ricerca quando viene inviato il modulo.
  function handleSearchSubmit(event) {
    // Impedisce al browser di ricaricare tutta la pagina.
    event.preventDefault();

    // Una nuova ricerca deve sempre ripartire dalla prima pagina.
    setCurrentPage(1);

    // Rimuove gli spazi esterni e applica il testo della ricerca.
    setSearch(searchInput.trim());
  }

  // Elimina la ricerca e ricarica l'elenco completo.
  function handleSearchReset() {
    // Svuota il campo visibile.
    setSearchInput("");

    // Rimuove il parametro inviato al backend.
    setSearch("");

    // Ripristina tutti i tipi e tutti gli stati.
    setTypeFilter("");
    setActiveFilter("");

    // Dopo l'azzeramento torniamo alla prima pagina.
    setCurrentPage(1);
  }

  useEffect(() => {
    const controller = new AbortController();

    async function loadVehicles() {
      // Il loader viene riattivato anche quando cambiamo pagina.
      setIsLoading(true);
      setErrorMessage("");

      try {
        const response = await api.get("/api/vehicles", {
          // Axios trasforma questo valore in ?page=1, ?page=2 e così via.
          params: {
            page: currentPage,
            per_page: 10,
            search: search || undefined,
            type: typeFilter || undefined,
            is_active: activeFilter || undefined,
          },
          signal: controller.signal,
        });

        // Salva i veicoli appartenenti solamente alla pagina richiesta.
        setVehicles(response.data.data);

        // Salva i metadati della paginazione restituiti da Laravel.
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
      <div className="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <h1 className="mb-0">Veicoli</h1>

        {!errorMessage && (
          <span className="text-secondary align-self-center">
            Totale: {pagination.total}
          </span>
        )}
      </div>

      <form
        className="card border-0 shadow-sm mb-4"
        onSubmit={handleSearchSubmit}
      >
        <div className="card-body">
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
                  onChange={(event) => {
                    setSearchInput(event.target.value);
                  }}
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
                htmlFor="vehicle-status"
                className="form-label fw-semibold"
              >
                Stato
              </label>

              <select
                id="vehicle-status"
                className="form-select"
                value={activeFilter}
                onChange={(event) => {
                  setActiveFilter(event.target.value);
                  setCurrentPage(1);
                }}
              >
                <option value="">Tutti gli stati</option>
                <option value="true">Attivi</option>
                <option value="false">Disattivati</option>
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
        <>
          <div className="table-responsive">
            <table className="table table-striped align-middle">
              <thead>
                <tr>
                  <th scope="col">Foto</th>
                  <th scope="col">Targa</th>
                  <th scope="col">Veicolo</th>
                  <th scope="col">Tipo</th>
                  <th scope="col">Chilometri</th>
                  <th scope="col">Stato</th>
                  <th scope="col" className="text-end">
                    Azioni
                  </th>
                </tr>
              </thead>

              <tbody>
                {vehicles.map((vehicle) => (
                  <tr key={vehicle.id}>
                    <td>
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
                          <i className="bi bi-car-front" aria-hidden="true"></i>
                        </div>
                      )}
                    </td>
                    <td className="fw-semibold">{vehicle.license_plate}</td>

                    <td>
                      {vehicle.brand} {vehicle.model}
                    </td>

                    <td>{VEHICLE_TYPE_LABELS[vehicle.type] ?? vehicle.type}</td>

                    <td>
                      {Number(vehicle.mileage).toLocaleString("it-IT")} km
                    </td>

                    <td>
                      <span
                        className={
                          vehicle.is_active
                            ? "badge text-bg-success"
                            : "badge text-bg-secondary"
                        }
                      >
                        {vehicle.is_active ? "Attivo" : "Disattivato"}
                      </span>
                    </td>
                    <td className="text-end">
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
              className="d-flex justify-content-center align-items-center gap-3"
              aria-label="Paginazione dei veicoli"
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
    </>
  );
}

export default VehiclesPage;
