import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";
import {
  EXPENSE_CATEGORIES,
  createSlug,
  formatCurrency,
  formatDate,
  getExpiryStatus,
} from "../utils/expenseOptions";

function ExpensesPage() {
  const [expenses, setExpenses] = useState([]);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("");
  const [dateFromInput, setDateFromInput] = useState("");
  const [dateToInput, setDateToInput] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function loadExpenses() {
      setIsLoading(true);
      setErrorMessage("");

      const params = { page: currentPage, per_page: 10 };
      if (search) params.search = search;
      if (category) params.category = category;
      if (dateFrom) params.date_from = dateFrom;
      if (dateTo) params.date_to = dateTo;

      try {
        const response = await api.get("/api/expenses", {
          params,
          signal: controller.signal,
        });
        setExpenses(response.data.data);
        setPagination({
          currentPage: response.data.meta.current_page,
          lastPage: response.data.meta.last_page,
          total: response.data.meta.total,
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare le spese.");
        }
      } finally {
        if (!controller.signal.aborted) setIsLoading(false);
      }
    }

    loadExpenses();
    return () => controller.abort();
  }, [search, category, dateFrom, dateTo, currentPage]);

  function handleSubmit(event) {
    event.preventDefault();

    if (dateFromInput && dateToInput && dateToInput < dateFromInput) {
      setErrorMessage("La data finale non può precedere quella iniziale.");
      return;
    }

    const normalizedSearch = searchInput.trim();
    setSearchInput(normalizedSearch);
    setSearch(normalizedSearch);
    setDateFrom(dateFromInput);
    setDateTo(dateToInput);
    setCurrentPage(1);
  }

  function resetFilters() {
    setSearchInput("");
    setSearch("");
    setCategory("");
    setDateFromInput("");
    setDateToInput("");
    setDateFrom("");
    setDateTo("");
    setCurrentPage(1);
  }

  if (isLoading) return <Loader message="Caricamento spese..." />;

  const hasFilters =
    searchInput || search || category || dateFromInput || dateToInput;

  return (
    <>
      <header className="page-header d-flex flex-wrap justify-content-between gap-3">
        <div>
          <span className="page-eyebrow">Contabilità flotta</span>
          <h1 className="mb-1">Spese</h1>
          <p className="text-secondary mb-0">
            Consulta i costi pagati e le scadenze generate per ogni veicolo.
          </p>
        </div>

        <div className="d-flex flex-wrap align-items-center gap-3">
          {!errorMessage && (
            <span className="text-secondary">
              Totale registrazioni: <strong>{pagination.total}</strong>
            </span>
          )}
          <Link to="/expenses/new" className="btn btn-primary">
            <i className="bi bi-plus-lg me-2" aria-hidden="true"></i>
            Nuova spesa
          </Link>
        </div>
      </header>

      {/* I filtri vengono applicati soltanto premendo Cerca. */}
      <form className="card border-0 shadow-sm mb-4" onSubmit={handleSubmit}>
        <div className="card-body">
          <div className="row g-3 align-items-end">
            <div className="col-12 col-xl">
              <label htmlFor="expense-search" className="form-label fw-semibold">
                Cerca
              </label>
              <input
                id="expense-search"
                type="search"
                className="form-control"
                placeholder="Descrizione o fornitore"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
              />
            </div>

            <div className="col-12 col-sm-6 col-lg-3">
              <label htmlFor="expense-category" className="form-label fw-semibold">
                Categoria
              </label>
              <select
                id="expense-category"
                className="form-select"
                value={category}
                onChange={(event) => {
                  setCategory(event.target.value);
                  setCurrentPage(1);
                }}
              >
                <option value="">Tutte le categorie</option>
                {Object.entries(EXPENSE_CATEGORIES).map(([value, item]) => (
                  <option key={value} value={value}>
                    {item.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-12 col-sm-6 col-lg-2">
              <label htmlFor="expense-from" className="form-label fw-semibold">
                Pagata dal
              </label>
              <input
                id="expense-from"
                type="date"
                className="form-control"
                value={dateFromInput}
                max={dateToInput || undefined}
                onChange={(event) => setDateFromInput(event.target.value)}
              />
            </div>

            <div className="col-12 col-sm-6 col-lg-2">
              <label htmlFor="expense-to" className="form-label fw-semibold">
                Pagata fino al
              </label>
              <input
                id="expense-to"
                type="date"
                className="form-control"
                value={dateToInput}
                min={dateFromInput || undefined}
                onChange={(event) => setDateToInput(event.target.value)}
              />
            </div>

            <div className="col-12 col-sm-auto d-grid">
              <button type="submit" className="btn btn-primary">Cerca</button>
            </div>

            {hasFilters && (
              <div className="col-12 col-sm-auto d-grid">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={resetFilters}
                >
                  Azzera
                </button>
              </div>
            )}
          </div>
        </div>
      </form>

      {errorMessage && <div className="alert alert-danger">{errorMessage}</div>}

      {!errorMessage && expenses.length === 0 && (
        <div className="alert alert-info">
          {hasFilters
            ? "Nessuna spesa corrisponde ai filtri selezionati."
            : "Non è ancora stata registrata alcuna spesa."}
        </div>
      )}

      {!errorMessage && expenses.length > 0 && (
        <>
          <section className="card border-0 shadow-sm overflow-hidden">
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th>Spesa</th>
                    <th>Veicolo</th>
                    <th>Pagamento</th>
                    <th>Importo</th>
                    <th>Scadenza collegata</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {expenses.map((expense) => {
                    const categoryData =
                      EXPENSE_CATEGORIES[expense.category] ??
                      EXPENSE_CATEGORIES.other;
                    const expiry = getExpiryStatus(expense.expires_on);
                    const vehicle = expense.vehicle;

                    return (
                      <tr key={expense.id}>
                        <td>
                          <div className="d-flex gap-2 align-items-start">
                            <i className={`bi ${categoryData.icon} text-primary`} aria-hidden="true"></i>
                            <div>
                              <Link
                                to={`/expenses/${expense.id}`}
                                className="fw-semibold text-decoration-none"
                              >
                                {expense.description}
                              </Link>
                              <div className="small text-secondary">
                                {categoryData.label}
                                {expense.supplier ? ` · ${expense.supplier}` : ""}
                              </div>
                            </div>
                          </div>
                        </td>
                        <td>
                          {vehicle ? (
                            <Link
                              to={`/vehicles/${vehicle.id}/${createSlug(
                                `${vehicle.brand} ${vehicle.model}`,
                              )}`}
                              className="text-decoration-none"
                            >
                              <span className="fw-semibold">
                                {vehicle.brand} {vehicle.model}
                              </span>
                              <span className="d-block small text-secondary">
                                Targa {vehicle.license_plate}
                              </span>
                            </Link>
                          ) : (
                            "Veicolo non disponibile"
                          )}
                        </td>
                        <td>
                          <span className="small text-secondary">Pagata il</span>
                          <div className="fw-semibold">
                            {formatDate(expense.expense_date)}
                          </div>
                        </td>
                        <td className="fw-semibold">
                          {formatCurrency(expense.amount)}
                        </td>
                        <td>
                          <span className={`badge ${expiry.className}`}>
                            {expiry.label}
                          </span>
                          <div className="small mt-1">
                            {expense.expires_on
                              ? `${formatDate(expense.expires_on)} · ${expiry.description}`
                              : expiry.description}
                          </div>
                        </td>
                        <td className="text-end">
                          <Link
                            to={`/expenses/${expense.id}`}
                            className="btn btn-sm btn-outline-primary"
                            aria-label={`Apri la spesa ${expense.description}`}
                          >
                            <i className="bi bi-arrow-right" aria-hidden="true"></i>
                          </Link>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </section>

          {pagination.lastPage > 1 && (
            <nav className="d-flex justify-content-center align-items-center gap-3 mt-4">
              <button
                type="button"
                className="btn btn-outline-primary"
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
                className="btn btn-outline-primary"
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

export default ExpensesPage;
