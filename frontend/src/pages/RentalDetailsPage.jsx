import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";

// Testo e colore utilizzati per distinguere gli stati del noleggio.
const RENTAL_STATUS = {
  reserved: { label: "Prenotato", badgeClass: "text-bg-primary" },
  active: { label: "In corso", badgeClass: "text-bg-success" },
  completed: { label: "Completato", badgeClass: "text-bg-secondary" },
  cancelled: { label: "Annullato", badgeClass: "text-bg-danger" },
};

function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

function formatDateTime(value) {
  if (!value) return "Non registrata";

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

// Estrae il primo errore di validazione restituito da Laravel.
function getFirstValidationError(error) {
  const errors = error.response?.data?.errors;

  if (!errors) return error.response?.data?.message ?? null;

  const firstMessages = Object.values(errors)[0];
  return Array.isArray(firstMessages) ? firstMessages[0] : firstMessages;
}

function RentalDetailsPage() {
  const { rentalId } = useParams();

  // Dati principali della pagina e stato comune a tutte le operazioni.
  const [rental, setRental] = useState(null);
  const [parkingSpaces, setParkingSpaces] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [successMessage, setSuccessMessage] = useState("");

  // Dati necessari per registrare la consegna del veicolo.
  const [activationValues, setActivationValues] = useState({
    start_mileage: "",
    amount_paid: "",
    notes: "",
  });

  // Dati necessari per registrare il rientro del veicolo.
  const [completionValues, setCompletionValues] = useState({
    end_mileage: "",
    amount_paid: "",
    parking_space_id: "",
    notes: "",
  });

  // Consente di aggiornare incasso e note anche dopo il rientro.
  const [accountingValues, setAccountingValues] = useState({
    amount_paid: "",
    notes: "",
  });

  // Carica nuovamente il noleggio dopo ogni azione completata.
  async function loadRental(signal) {
    const response = await api.get(`/api/rentals/${rentalId}`, { signal });
    const loadedRental = response.data.data;

    setRental(loadedRental);
    // Alla consegna propone i km attuali del mezzo se non sono ancora salvati.
    setActivationValues({
      start_mileage:
        loadedRental.start_mileage ?? loadedRental.vehicle?.mileage ?? "",
      amount_paid: loadedRental.amount_paid ?? "0.00",
      notes: loadedRental.notes ?? "",
    });
    /*
     * Al rientro propone i km iniziali come base di confronto e l'importo
     * totale come pagamento. L'operatore può comunque correggere entrambi.
     */
    setCompletionValues({
      end_mileage:
        loadedRental.end_mileage ?? loadedRental.start_mileage ?? "",
      amount_paid:
        loadedRental.status === "active"
          ? loadedRental.total_amount
          : (loadedRental.amount_paid ?? "0.00"),
      parking_space_id: "",
      notes: loadedRental.notes ?? "",
    });
    setAccountingValues({
      amount_paid: loadedRental.amount_paid ?? "0.00",
      notes: loadedRental.notes ?? "",
    });
  }

  // Primo caricamento della scheda e delle celle disponibili in autorimessa.
  useEffect(() => {
    const controller = new AbortController();

    async function loadPage() {
      setIsLoading(true);
      setErrorMessage("");

      try {
        // Le celle servono solamente per l'eventuale rientro in autorimessa.
        const [rentalResponse, spacesResponse] = await Promise.all([
          api.get(`/api/rentals/${rentalId}`, {
            signal: controller.signal,
          }),
          api.get("/api/parking-spaces", {
            signal: controller.signal,
          }),
        ]);

        const loadedRental = rentalResponse.data.data;
        setRental(loadedRental);
        setParkingSpaces(spacesResponse.data.data);

        // Inizializza i tre moduli operativi con i dati ricevuti dall'API.
        setActivationValues({
          start_mileage:
            loadedRental.start_mileage ?? loadedRental.vehicle?.mileage ?? "",
          amount_paid: loadedRental.amount_paid ?? "0.00",
          notes: loadedRental.notes ?? "",
        });
        setCompletionValues({
          end_mileage:
            loadedRental.end_mileage ?? loadedRental.start_mileage ?? "",
          amount_paid:
            loadedRental.status === "active"
              ? loadedRental.total_amount
              : (loadedRental.amount_paid ?? "0.00"),
          parking_space_id: "",
          notes: loadedRental.notes ?? "",
        });
        setAccountingValues({
          amount_paid: loadedRental.amount_paid ?? "0.00",
          notes: loadedRental.notes ?? "",
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage(
            error.response?.status === 404
              ? "Il noleggio richiesto non esiste."
              : "Impossibile caricare il dettaglio del noleggio.",
          );
        }
      } finally {
        if (!controller.signal.aborted) setIsLoading(false);
      }
    }

    loadPage();

    return () => controller.abort();
  }, [rentalId]);

  // Esegue un'azione e presenta in modo uniforme errori e conferme.
  async function performAction(action, successText) {
    setIsSubmitting(true);
    setErrorMessage("");
    setSuccessMessage("");

    try {
      await action();
      await loadRental();
      setSuccessMessage(successText);
    } catch (error) {
      setErrorMessage(
        getFirstValidationError(error) ??
          "Operazione non riuscita. Controlla i dati e riprova.",
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  function handleActivate(event) {
    event.preventDefault();

    // La consegna trasforma una prenotazione in un noleggio attivo.
    performAction(
      () =>
        api.patch(`/api/rentals/${rentalId}/activate`, {
          start_mileage: Number(activationValues.start_mileage),
          amount_paid: activationValues.amount_paid || 0,
          notes: activationValues.notes.trim() || null,
        }),
      "Consegna registrata: il noleggio è ora in corso.",
    );
  }

  function handleComplete(event) {
    event.preventDefault();

    // Il rientro registra km finali, incasso totale e collocazione del mezzo.
    const payload = {
      end_mileage: Number(completionValues.end_mileage),
      amount_paid: completionValues.amount_paid || 0,
      notes: completionValues.notes.trim() || null,
    };

    // La cella è facoltativa: il mezzo può rientrare direttamente in officina.
    if (completionValues.parking_space_id) {
      payload.parking_space_id = Number(completionValues.parking_space_id);
    }

    performAction(
      () => api.patch(`/api/rentals/${rentalId}/complete`, payload),
      "Rientro registrato: il noleggio è stato completato.",
    );
  }

  function handleAccountingUpdate(event) {
    event.preventDefault();

    // Consente di correggere incasso e note senza alterare il periodo.
    performAction(
      () =>
        api.patch(`/api/rentals/${rentalId}`, {
          amount_paid: accountingValues.amount_paid || 0,
          notes: accountingValues.notes.trim() || null,
        }),
      "Pagamento e note aggiornati.",
    );
  }

  function handleCancel() {
    // La conferma protegge dall'annullamento involontario della prenotazione.
    const confirmed = window.confirm(
      "Vuoi davvero annullare questa prenotazione? L'operazione resterà nello storico.",
    );

    if (!confirmed) return;

    performAction(
      () => api.patch(`/api/rentals/${rentalId}/cancel`),
      "Prenotazione annullata correttamente.",
    );
  }

  if (isLoading) {
    return <Loader message="Caricamento dettaglio noleggio..." />;
  }

  if (!rental) {
    return <div className="alert alert-danger">{errorMessage}</div>;
  }

  // Valori derivati usati in più sezioni della scheda.
  const status = RENTAL_STATUS[rental.status] ?? RENTAL_STATUS.reserved;
  const balanceDue = Number(rental.balance_due ?? 0);
  const isCancelled = rental.status === "cancelled";
  const customer = rental.customer;
  const vehicle = rental.vehicle;

  // Calcola in tempo reale la distanza percorsa durante il noleggio.
  const travelledKilometres =
    rental.start_mileage !== null && completionValues.end_mileage !== ""
      ? Math.max(
          0,
          Number(completionValues.end_mileage) - Number(rental.start_mileage),
        )
      : null;

  // Nel menu di rientro mostra soltanto celle attive e non occupate.
  const availableParkingSpaces = parkingSpaces.filter(
    (space) => space.is_active && !space.is_occupied,
  );

  return (
    <>
      {/* Navigazione di ritorno all'elenco generale. */}
      <Link
        to="/rentals"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai noleggi
      </Link>

      {/* Titolo, stato e azione di modifica principale. */}
      <header className="page-header d-flex flex-wrap justify-content-between gap-3">
        <div>
          <span className="page-eyebrow">Noleggio #{rental.id}</span>
          <div className="d-flex flex-wrap align-items-center gap-3">
            <h1 className="mb-0">
              {vehicle
                ? `${vehicle.brand} ${vehicle.model}`
                : "Dettaglio noleggio"}
            </h1>
            <span className={`badge ${status.badgeClass}`}>{status.label}</span>
          </div>
          <p className="text-secondary mt-2 mb-0">
            {customer
              ? `${customer.first_name} ${customer.last_name}`
              : "Cliente non disponibile"}
          </p>
        </div>

        {rental.status === "reserved" && (
          <Link
            to={`/rentals/${rental.id}/edit`}
            className="btn btn-outline-primary align-self-start"
          >
            <i className="bi bi-pencil me-2" aria-hidden="true"></i>
            Modifica prenotazione
          </Link>
        )}
      </header>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {successMessage && (
        <div className="alert alert-success" role="status">
          {successMessage}
        </div>
      )}

      <div className="row g-4 align-items-start">
        <div className="col-12 col-xl-8">
          {/* Riepilogo economico immediatamente leggibile. */}
          <section className="card border-0 shadow-sm mb-4">
            <div className="card-body p-4">
              <span className="page-eyebrow">Riepilogo</span>
              <h2 className="h4 mb-4">Periodo e pagamento</h2>

              <div className="row g-4">
                <div className="col-12 col-md-6">
                  <div className="small text-secondary">Consegna prevista</div>
                  <div className="fw-semibold">
                    {formatDateTime(rental.starts_at)}
                  </div>
                </div>
                <div className="col-12 col-md-6">
                  <div className="small text-secondary">Rientro previsto</div>
                  <div className="fw-semibold">
                    {formatDateTime(rental.expected_ends_at)}
                  </div>
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                  <div className="small text-secondary">Giornate</div>
                  <div className="fs-5 fw-semibold">
                    {rental.chargeable_days}
                  </div>
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                  <div className="small text-secondary">Tariffa al giorno</div>
                  <div className="fs-5 fw-semibold">
                    {formatCurrency(rental.daily_rate)}
                  </div>
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                  <div className="small text-secondary">
                    {isCancelled ? "Totale previsto" : "Totale"}
                  </div>
                  <div className="fs-5 fw-semibold">
                    {formatCurrency(rental.total_amount)}
                  </div>
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                  <div className="small text-secondary">Già incassato</div>
                  <div className="fs-5 fw-semibold text-success">
                    {formatCurrency(rental.amount_paid)}
                  </div>
                </div>
              </div>

              <div
                className={`alert mt-4 mb-0 ${
                  isCancelled
                    ? "alert-secondary"
                    : balanceDue > 0
                      ? "alert-warning"
                      : "alert-success"
                }`}
              >
                {isCancelled
                  ? "Prenotazione annullata: non risulta alcun saldo dovuto."
                  : balanceDue > 0
                    ? `Saldo ancora da incassare: ${formatCurrency(balanceDue)}`
                    : "Pagamento completato: non rimane alcun saldo."}
              </div>
            </div>
          </section>

          {/* Collegamenti alle anagrafiche coinvolte. */}
          <section className="card border-0 shadow-sm mb-4">
            <div className="card-body p-4">
              <span className="page-eyebrow">Assegnazione</span>
              <h2 className="h4 mb-4">Cliente e veicolo</h2>

              <div className="row g-4">
                <div className="col-12 col-md-6">
                  <div className="border rounded-3 p-3 h-100">
                    <div className="small text-secondary mb-1">Cliente</div>
                    {customer ? (
                      <>
                        <Link
                          to={`/customers/${customer.id}/${createSlug(
                            `${customer.first_name} ${customer.last_name}`,
                          )}`}
                          className="h5 text-decoration-none"
                        >
                          {customer.first_name} {customer.last_name}
                        </Link>
                        <div className="small text-secondary mt-2">
                          {customer.phone || customer.email || "Nessun contatto"}
                        </div>
                        <div className="small mt-1">
                          Patente {customer.driving_license_number}
                        </div>
                      </>
                    ) : (
                      <span className="text-secondary">Non disponibile</span>
                    )}
                  </div>
                </div>

                <div className="col-12 col-md-6">
                  <div className="border rounded-3 p-3 h-100">
                    <div className="small text-secondary mb-1">Veicolo</div>
                    {vehicle ? (
                      <>
                        <Link
                          to={`/vehicles/${vehicle.id}/${createSlug(
                            `${vehicle.brand} ${vehicle.model}`,
                          )}`}
                          className="h5 text-decoration-none"
                        >
                          {vehicle.brand} {vehicle.model}
                        </Link>
                        <div className="small text-secondary mt-2">
                          Targa {vehicle.license_plate}
                        </div>
                        <div className="small mt-1">Tipo: {vehicle.type}</div>
                      </>
                    ) : (
                      <span className="text-secondary">Non disponibile</span>
                    )}
                  </div>
                </div>
              </div>
            </div>
          </section>

          {/* Dati effettivi disponibili dopo consegna o rientro. */}
          <section className="card border-0 shadow-sm">
            <div className="card-body p-4">
              <span className="page-eyebrow">Operatività</span>
              <h2 className="h4 mb-4">Consegna e rientro effettivi</h2>

              <div className="row g-4">
                <div className="col-12 col-md-6">
                  <div className="small text-secondary">Consegna effettiva</div>
                  <div className="fw-semibold">
                    {formatDateTime(rental.actual_starts_at)}
                  </div>
                  <div className="small mt-1">
                    Km iniziali: {rental.start_mileage ?? "Non registrati"}
                  </div>
                </div>
                <div className="col-12 col-md-6">
                  <div className="small text-secondary">Rientro effettivo</div>
                  <div className="fw-semibold">
                    {formatDateTime(rental.actual_ends_at)}
                  </div>
                  <div className="small mt-1">
                    Km finali: {rental.end_mileage ?? "Non registrati"}
                  </div>
                </div>
              </div>

              <hr className="my-4" />
              <div className="small text-secondary mb-1">Note</div>
              <div className="text-break">
                {rental.notes || "Nessuna nota registrata."}
              </div>
            </div>
          </section>
        </div>

        {/* La colonna mostra esclusivamente le azioni coerenti con lo stato. */}
        <aside className="col-12 col-xl-4">
          {rental.status === "reserved" && (
            <section className="card border-0 shadow-sm">
              <div className="card-body p-4">
                <span className="page-eyebrow">Consegna</span>
                <h2 className="h4">Avvia il noleggio</h2>
                <p className="text-secondary small">
                  Registra chilometraggio e totale complessivamente incassato.
                </p>

                <form onSubmit={handleActivate}>
                  <label className="form-label fw-semibold" htmlFor="start-mileage">
                    Chilometraggio iniziale *
                  </label>
                  <input
                    id="start-mileage"
                    type="number"
                    min="0"
                    className="form-control mb-3"
                    value={activationValues.start_mileage}
                    required
                    disabled={isSubmitting}
                    onChange={(event) =>
                      setActivationValues((values) => ({
                        ...values,
                        start_mileage: event.target.value,
                      }))
                    }
                  />

                  <label className="form-label fw-semibold" htmlFor="activate-paid">
                    Totale incassato
                  </label>
                  <div className="input-group mb-3">
                    <span className="input-group-text">€</span>
                    <input
                      id="activate-paid"
                      type="number"
                      min="0"
                      max={rental.total_amount}
                      step="0.01"
                      className="form-control"
                      value={activationValues.amount_paid}
                      disabled={isSubmitting}
                      onChange={(event) =>
                        setActivationValues((values) => ({
                          ...values,
                          amount_paid: event.target.value,
                        }))
                      }
                    />
                  </div>

                  <label className="form-label fw-semibold" htmlFor="activate-notes">
                    Note di consegna
                  </label>
                  <textarea
                    id="activate-notes"
                    rows="3"
                    maxLength={5000}
                    className="form-control mb-3"
                    value={activationValues.notes}
                    disabled={isSubmitting}
                    onChange={(event) =>
                      setActivationValues((values) => ({
                        ...values,
                        notes: event.target.value,
                      }))
                    }
                  ></textarea>

                  <button
                    type="submit"
                    className="btn btn-success w-100"
                    disabled={isSubmitting}
                  >
                    <i className="bi bi-key me-2" aria-hidden="true"></i>
                    Registra consegna
                  </button>
                </form>

                <hr />
                <button
                  type="button"
                  className="btn btn-outline-danger w-100"
                  disabled={isSubmitting}
                  onClick={handleCancel}
                >
                  Annulla prenotazione
                </button>
              </div>
            </section>
          )}

          {rental.status === "active" && (
            <section className="card border-0 shadow-sm">
              <div className="card-body p-4">
                <span className="page-eyebrow">Rientro</span>
                <h2 className="h4">Completa il noleggio</h2>
                <p className="text-secondary small">
                  Registra chilometri, incasso totale e destinazione del mezzo.
                </p>

                <form onSubmit={handleComplete}>
                  <label className="form-label fw-semibold" htmlFor="end-mileage">
                    Chilometraggio finale *
                  </label>
                  <input
                    id="end-mileage"
                    type="number"
                    min={rental.start_mileage ?? 0}
                    className="form-control"
                    value={completionValues.end_mileage}
                    required
                    disabled={isSubmitting}
                    onChange={(event) =>
                      setCompletionValues((values) => ({
                        ...values,
                        end_mileage: event.target.value,
                      }))
                    }
                  />
                  <div className="form-text mb-3">
                    Chilometri alla consegna: {rental.start_mileage}. Percorsi:{" "}
                    <strong>{travelledKilometres ?? 0} km</strong>.
                  </div>

                  <label className="form-label fw-semibold" htmlFor="complete-paid">
                    Totale incassato al rientro
                  </label>
                  <div className="input-group mb-3">
                    <span className="input-group-text">€</span>
                    <input
                      id="complete-paid"
                      type="number"
                      min="0"
                      max={rental.total_amount}
                      step="0.01"
                      className="form-control"
                      value={completionValues.amount_paid}
                      disabled={isSubmitting}
                      onChange={(event) =>
                        setCompletionValues((values) => ({
                          ...values,
                          amount_paid: event.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="form-text mb-3">
                    È già proposto il totale del noleggio. Riducilo soltanto se
                    il cliente lascia una parte ancora da saldare.
                  </div>

                  <label className="form-label fw-semibold" htmlFor="return-space">
                    Cella di rientro
                  </label>
                  <select
                    id="return-space"
                    className="form-select mb-1"
                    value={completionValues.parking_space_id}
                    disabled={isSubmitting}
                    onChange={(event) =>
                      setCompletionValues((values) => ({
                        ...values,
                        parking_space_id: event.target.value,
                      }))
                    }
                  >
                    <option value="">Non parcheggiare ora</option>
                    {availableParkingSpaces.map((space) => (
                      <option key={space.id} value={space.id}>
                        {space.label} — zona {space.zone}
                      </option>
                    ))}
                  </select>
                  <div className="form-text mb-3">
                    Per mezzi grandi scegli la prima cella del blocco libero.
                  </div>

                  <label className="form-label fw-semibold" htmlFor="complete-notes">
                    Note di rientro
                  </label>
                  <textarea
                    id="complete-notes"
                    rows="3"
                    maxLength={5000}
                    className="form-control mb-3"
                    value={completionValues.notes}
                    disabled={isSubmitting}
                    onChange={(event) =>
                      setCompletionValues((values) => ({
                        ...values,
                        notes: event.target.value,
                      }))
                    }
                  ></textarea>

                  <button
                    type="submit"
                    className="btn btn-primary w-100"
                    disabled={isSubmitting}
                  >
                    <i className="bi bi-check2-circle me-2" aria-hidden="true"></i>
                    Registra rientro
                  </button>
                </form>
              </div>
            </section>
          )}

          {rental.status === "completed" && (
            <section className="card border-0 shadow-sm">
              <div className="card-body p-4">
                <span className="page-eyebrow">Contabilità</span>
                <h2 className="h4">Aggiorna pagamento</h2>
                <p className="text-secondary small">
                  Inserisci il totale complessivamente incassato, non soltanto
                  l'ultimo versamento.
                </p>

                <form onSubmit={handleAccountingUpdate}>
                  <label className="form-label fw-semibold" htmlFor="accounting-paid">
                    Totale incassato
                  </label>
                  <div className="input-group mb-3">
                    <span className="input-group-text">€</span>
                    <input
                      id="accounting-paid"
                      type="number"
                      min="0"
                      max={rental.total_amount}
                      step="0.01"
                      className="form-control"
                      value={accountingValues.amount_paid}
                      disabled={isSubmitting}
                      onChange={(event) =>
                        setAccountingValues((values) => ({
                          ...values,
                          amount_paid: event.target.value,
                        }))
                      }
                    />
                  </div>

                  <label className="form-label fw-semibold" htmlFor="accounting-notes">
                    Note
                  </label>
                  <textarea
                    id="accounting-notes"
                    rows="4"
                    maxLength={5000}
                    className="form-control mb-3"
                    value={accountingValues.notes}
                    disabled={isSubmitting}
                    onChange={(event) =>
                      setAccountingValues((values) => ({
                        ...values,
                        notes: event.target.value,
                      }))
                    }
                  ></textarea>

                  <button
                    type="submit"
                    className="btn btn-primary w-100"
                    disabled={isSubmitting}
                  >
                    Salva pagamento e note
                  </button>
                </form>
              </div>
            </section>
          )}

          {rental.status === "cancelled" && (
            <div className="alert alert-secondary mb-0">
              Questa prenotazione è annullata e rimane consultabile nello
              storico. Non sono disponibili altre azioni operative.
            </div>
          )}
        </aside>
      </div>
    </>
  );
}

export default RentalDetailsPage;
