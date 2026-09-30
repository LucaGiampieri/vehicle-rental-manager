import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";
import {
  EXPENSE_CATEGORIES,
  createSlug,
  formatCurrency,
  formatDate,
  getExpiryStatus,
} from "../utils/expenseOptions";

function ExpenseDetailsPage() {
  const { expenseId } = useParams();
  const navigate = useNavigate();
  const [expense, setExpense] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isDeleting, setIsDeleting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function loadExpense() {
      try {
        const response = await api.get(`/api/expenses/${expenseId}`, {
          signal: controller.signal,
        });
        setExpense(response.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare la spesa.");
        }
      } finally {
        if (!controller.signal.aborted) setIsLoading(false);
      }
    }

    loadExpense();
    return () => controller.abort();
  }, [expenseId]);

  async function handleDelete() {
    const confirmed = window.confirm(
      "Vuoi eliminare definitivamente questa spesa? Fallo soltanto se è stata inserita per errore.",
    );
    if (!confirmed) return;

    setIsDeleting(true);
    setErrorMessage("");

    try {
      await api.delete(`/api/expenses/${expenseId}`);
      navigate("/expenses", { replace: true });
    } catch {
      setErrorMessage("Impossibile eliminare la spesa.");
      setIsDeleting(false);
    }
  }

  if (isLoading) return <Loader message="Caricamento dettaglio spesa..." />;
  if (!expense) return <div className="alert alert-danger">{errorMessage}</div>;

  const category =
    EXPENSE_CATEGORIES[expense.category] ?? EXPENSE_CATEGORIES.other;
  const expiry = getExpiryStatus(expense.expires_on);
  const vehicle = expense.vehicle;

  return (
    <>
      <Link
        to="/expenses"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna alle spese
      </Link>

      <header className="page-header d-flex flex-wrap justify-content-between gap-3">
        <div>
          <span className="page-eyebrow">Spesa #{expense.id}</span>
          <h1 className="mb-1">{expense.description}</h1>
          <p className="text-secondary mb-0">{category.label}</p>
        </div>
        <Link to={`/expenses/${expense.id}/edit`} className="btn btn-outline-primary align-self-start">
          <i className="bi bi-pencil me-2" aria-hidden="true"></i>
          Modifica
        </Link>
      </header>

      {errorMessage && <div className="alert alert-danger">{errorMessage}</div>}

      <div className="row g-4 align-items-start">
        <div className="col-12 col-xl-8">
          <section className="card border-0 shadow-sm mb-4">
            <div className="card-body p-4">
              <span className="page-eyebrow">Pagamento</span>
              <h2 className="h4 mb-4">Costo sostenuto</h2>
              <div className="row g-4">
                <div className="col-12 col-sm-6 col-lg-4">
                  <div className="small text-secondary">Importo pagato</div>
                  <div className="fs-3 fw-bold">{formatCurrency(expense.amount)}</div>
                </div>
                <div className="col-12 col-sm-6 col-lg-4">
                  <div className="small text-secondary">Pagata il</div>
                  <div className="fw-semibold">{formatDate(expense.expense_date)}</div>
                </div>
                <div className="col-12 col-sm-6 col-lg-4">
                  <div className="small text-secondary">Fornitore</div>
                  <div className="fw-semibold">{expense.supplier || "Non indicato"}</div>
                </div>
              </div>
            </div>
          </section>

          <section className="card border-0 shadow-sm mb-4">
            <div className="card-body p-4">
              <span className="page-eyebrow">Validità</span>
              <h2 className="h4 mb-3">Scadenza collegata</h2>
              <span className={`badge ${expiry.className} mb-3`}>{expiry.label}</span>
              <div className="fw-semibold">
                {expense.expires_on
                  ? `Valida fino al ${formatDate(expense.expires_on)}`
                  : "Non è stata indicata una scadenza."}
              </div>
              <div className="text-secondary mt-1">{expiry.description}</div>
              <div className="form-text mt-3">
                Questa data non indica una spesa non pagata: segnala quando
                termina la validità del servizio acquistato.
              </div>
            </div>
          </section>

          <section className="card border-0 shadow-sm">
            <div className="card-body p-4">
              <span className="page-eyebrow">Informazioni</span>
              <h2 className="h4 mb-4">Dettagli aggiuntivi</h2>
              <div className="row g-4">
                <div className="col-12 col-md-6">
                  <div className="small text-secondary">Chilometraggio</div>
                  <div className="fw-semibold">
                    {expense.mileage === null
                      ? "Non registrato"
                      : `${Number(expense.mileage).toLocaleString("it-IT")} km`}
                  </div>
                </div>
                <div className="col-12">
                  <div className="small text-secondary">Note</div>
                  <div className="text-break">{expense.notes || "Nessuna nota."}</div>
                </div>
              </div>
            </div>
          </section>
        </div>

        <aside className="col-12 col-xl-4">
          <section className="card border-0 shadow-sm mb-4">
            <div className="card-body p-4">
              <span className="page-eyebrow">Veicolo</span>
              {vehicle ? (
                <>
                  <h2 className="h4 mb-1">{vehicle.brand} {vehicle.model}</h2>
                  <p className="text-secondary">Targa {vehicle.license_plate}</p>
                  <Link
                    to={`/vehicles/${vehicle.id}/${createSlug(
                      `${vehicle.brand} ${vehicle.model}`,
                    )}`}
                    className="btn btn-outline-primary w-100"
                  >
                    Apri scheda veicolo
                  </Link>
                </>
              ) : (
                <p className="mb-0">Veicolo non disponibile.</p>
              )}
            </div>
          </section>

          <section className="card border-danger shadow-sm">
            <div className="card-body p-4">
              <h2 className="h5">Elimina registrazione</h2>
              <p className="small text-secondary">
                Utilizza questa funzione solo se la spesa è stata inserita per errore.
              </p>
              <button
                type="button"
                className="btn btn-outline-danger w-100"
                disabled={isDeleting}
                onClick={handleDelete}
              >
                {isDeleting ? "Eliminazione..." : "Elimina spesa"}
              </button>
            </div>
          </section>
        </aside>
      </div>
    </>
  );
}

export default ExpenseDetailsPage;
