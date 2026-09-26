import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";

// Trasforma nome e cognome in un testo utilizzabile nell’indirizzo.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function CustomersPage() {
  // Conserva i clienti della pagina corrente.
  const [customers, setCustomers] = useState([]);

  // Contiene ciò che l’utente sta scrivendo nel campo di ricerca.
  const [searchInput, setSearchInput] = useState("");

  // Contiene la ricerca effettivamente inviata a Laravel.
  const [search, setSearch] = useState("");

  // Conserva il filtro relativo allo stato del cliente.
  const [activeFilter, setActiveFilter] = useState("");

  // Conserva l’ordinamento scelto dall’utente.
  const [sortOrder, setSortOrder] = useState("activity_desc");

  // Conserva la pagina richiesta dall’utente.
  const [currentPage, setCurrentPage] = useState(1);

  // Conserva le informazioni di paginazione restituite da Laravel.
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Indica che una richiesta è in corso.
  const [isLoading, setIsLoading] = useState(true);

  // Conserva un eventuale messaggio di errore.
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    // Permette di annullare la richiesta lasciando la pagina.
    const controller = new AbortController();

    async function loadCustomers() {
      setIsLoading(true);
      setErrorMessage("");

      /*
       * Questi parametri diventano la query string:
       * /api/customers?page=1&per_page=8
       */
      const params = {
        page: currentPage,
        per_page: 8,
        sort: sortOrder,
      };

      // Invia la ricerca soltanto se contiene effettivamente del testo.
      if (search) {
        params.search = search;
      }

      // Invia il filtro soltanto se è stato selezionato.
      if (activeFilter) {
        params.is_active = activeFilter;
      }

      try {
        const response = await api.get("/api/customers", {
          params,
          signal: controller.signal,
        });

        setCustomers(response.data.data);

        setPagination({
          currentPage: response.data.meta.current_page,
          lastPage: response.data.meta.last_page,
          total: response.data.meta.total,
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare i clienti. Riprova più tardi.");
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadCustomers();

    return () => controller.abort();
  }, [search, activeFilter, sortOrder, currentPage]);

  // Conferma la ricerca quando viene inviato il form.
  function handleSearchSubmit(event) {
    event.preventDefault();

    /*
     * trim elimina gli spazi inutili all’inizio e alla fine.
     * Non serve trasformare in minuscolo perché MySQL esegue già
     * questa ricerca senza distinguere maiuscole e minuscole.
     */
    const normalizedSearch = searchInput.trim();

    setSearch(normalizedSearch);
    setSearchInput(normalizedSearch);
    setCurrentPage(1);
  }

  // Elimina ricerca e filtri e torna alla prima pagina.
  function handleSearchReset() {
    setSearchInput("");
    setSearch("");
    setActiveFilter("");
    setSortOrder("activity_desc");
    setCurrentPage(1);
  }

  // Durante la richiesta mostra il loader condiviso.
  if (isLoading) {
    return <Loader message="Caricamento clienti..." />;
  }

  return (
    <>
      {/* Intestazione principale della sezione clienti. */}
      <header className="page-header">
        <div>
          <span className="page-eyebrow">Anagrafica</span>

          <h1 className="mb-1">Clienti</h1>

          <p className="text-secondary mb-0">
            Consulta e gestisci le persone registrate nell’autonoleggio.
          </p>
        </div>

        {!errorMessage && (
          <span className="text-secondary">
            Totale: <strong>{pagination.total}</strong>
          </span>
        )}
      </header>

      {/* Ricerca e filtro vengono inviati tramite questo form. */}
      <form
        className="card border-0 shadow-sm mb-4"
        onSubmit={handleSearchSubmit}
      >
        <div className="card-body">
          <div className="row g-3 align-items-end">
            <div className="col-12 col-lg">
              <label
                htmlFor="customer-search"
                className="form-label fw-semibold"
              >
                Cerca un cliente
              </label>

              <div className="input-group">
                <span className="input-group-text bg-white">
                  <i className="bi bi-search" aria-hidden="true"></i>
                </span>

                <input
                  id="customer-search"
                  type="search"
                  className="form-control"
                  placeholder="Nome, email, telefono, codice fiscale o patente"
                  value={searchInput}
                  onChange={(event) => {
                    setSearchInput(event.target.value);
                  }}
                />
              </div>
            </div>

            <div className="col-12 col-md-5 col-lg-3">
              <label
                htmlFor="customer-status"
                className="form-label fw-semibold"
              >
                Stato
              </label>

              <select
                id="customer-status"
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

            <div className="col-12 col-md-6 col-lg-3">
              <label htmlFor="customer-sort" className="form-label fw-semibold">
                Ordina per
              </label>

              <select
                id="customer-sort"
                className="form-select"
                value={sortOrder}
                onChange={(event) => {
                  setSortOrder(event.target.value);
                  setCurrentPage(1);
                }}
              >
                <option value="activity_desc">
                  Noleggio/prenotazione più recente
                </option>
                <option value="name_asc">Cognome A–Z</option>
                <option value="name_desc">Cognome Z–A</option>
                <option value="newest">Inseriti più recentemente</option>
                <option value="rentals_desc">Maggior numero di noleggi</option>
              </select>
            </div>

            <div className="col-12 col-sm-auto d-grid">
              <button type="submit" className="btn btn-primary">
                Cerca
              </button>
            </div>

            {(searchInput ||
              search ||
              activeFilter ||
              sortOrder !== "activity_desc") && (
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
            Puoi utilizzare anche solo una parte del nome o del dato cercato.
          </div>
        </div>
      </form>

      {/* Mostra un errore quando Laravel non risponde correttamente. */}
      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {/* Messaggio personalizzato quando la ricerca non produce risultati. */}
      {!errorMessage && customers.length === 0 && (
        <div className="alert alert-info" role="status">
          {search ? (
            <>
              Nessun cliente trovato per <strong>“{search}”</strong>.
            </>
          ) : activeFilter ? (
            "Nessun cliente corrisponde allo stato selezionato."
          ) : (
            "Non ci sono ancora clienti registrati."
          )}
        </div>
      )}

      {/* Elenco visualizzato soltanto quando esistono clienti. */}
      {!errorMessage && customers.length > 0 && (
        <>
          <section
            className="card border-0 shadow-sm overflow-hidden"
            aria-labelledby="customers-list-title"
          >
            <div className="card-body border-bottom">
              <h2 id="customers-list-title" className="h5 mb-1">
                Elenco clienti
              </h2>

              <p className="text-secondary small mb-0">
                Informazioni anagrafiche e numero di noleggi registrati.
              </p>
            </div>

            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Cliente</th>
                    <th scope="col">Contatti</th>
                    <th scope="col">Patente</th>
                    <th scope="col">Noleggi</th>
                    <th scope="col">Stato</th>
                    <th scope="col" className="text-end">
                      Azioni
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {customers.map((customer) => (
                    <tr key={customer.id}>
                      <td>
                        <div className="fw-semibold">
                          {customer.first_name} {customer.last_name}
                        </div>

                        <div className="small text-secondary">
                          {customer.tax_code || "Codice fiscale non inserito"}
                        </div>
                      </td>

                      <td>
                        <div>{customer.email || "Email non inserita"}</div>

                        <div className="small text-secondary">
                          {customer.phone || "Telefono non inserito"}
                        </div>
                      </td>

                      <td>
                        {customer.driving_license_number || "Non inserita"}
                      </td>

                      <td>{customer.rentals_count}</td>

                      <td>
                        <span
                          className={
                            customer.is_active
                              ? "badge text-bg-success"
                              : "badge text-bg-secondary"
                          }
                        >
                          {customer.is_active ? "Attivo" : "Disattivato"}
                        </span>
                      </td>

                      <td className="text-end">
                        <Link
                          to={`/customers/${customer.id}/${createSlug(
                            `${customer.first_name} ${customer.last_name}`,
                          )}`}
                          className="btn btn-sm btn-outline-primary"
                          aria-label={`Visualizza ${customer.first_name} ${customer.last_name}`}
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
          </section>

          {/* La navigazione compare soltanto se esiste più di una pagina. */}
          {pagination.lastPage > 1 && (
            <nav
              className="d-flex justify-content-center align-items-center gap-3 mt-4"
              aria-label="Paginazione dei clienti"
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

export default CustomersPage;
