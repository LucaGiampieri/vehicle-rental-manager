import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import CustomerRentalHistory from "../components/CustomerRentalHistory";
import Loader from "../components/Loader";
import api from "../services/api";

// Formatta una data usando le convenzioni italiane.
function formatDate(value) {
  if (!value) {
    return "Non inserita";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
  }).format(new Date(value));
}

// Formatta data e ora usando le convenzioni italiane.
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

// Crea la parte leggibile dell’indirizzo del veicolo.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

/*
 * Mostra un noleggio attivo oppure una prenotazione futura.
 * Lo stesso componente evita di duplicare tutto il codice JSX.
 */
function RentalCommitmentCard({ rental, type }) {
  const isActive = type === "active";
  const vehicle = rental.vehicle;

  return (
    <article
      className={`card h-100 border-0 shadow-sm ${
        isActive ? "border-start border-success border-4" : ""
      }`}
    >
      <div className="card-body p-4">
        <div className="d-flex align-items-start justify-content-between gap-3 mb-4">
          <div className="d-flex align-items-center gap-3">
            <span
              className={`d-inline-flex align-items-center justify-content-center rounded-circle ${
                isActive
                  ? "bg-success-subtle text-success"
                  : "bg-primary-subtle text-primary"
              }`}
              style={{ width: "44px", height: "44px" }}
            >
              <i
                className={
                  isActive ? "bi bi-key-fill" : "bi bi-calendar-check-fill"
                }
                aria-hidden="true"
              ></i>
            </span>

            <div>
              <div className="small text-secondary">
                {isActive ? "Noleggio attuale" : "Prossima prenotazione"}
              </div>

              <h3 className="h5 mb-0">
                {vehicle
                  ? `${vehicle.brand} ${vehicle.model}`
                  : "Veicolo non disponibile"}
              </h3>
            </div>
          </div>

          <span
            className={
              isActive ? "badge text-bg-success" : "badge text-bg-primary"
            }
          >
            {isActive ? "In corso" : "Prenotato"}
          </span>
        </div>

        {vehicle && (
          <div className="mb-4">
            <div className="small text-secondary">Targa</div>
            <div className="fw-semibold">{vehicle.license_plate}</div>
          </div>
        )}

        <dl className="row g-3 mb-4">
          <div className="col-12 col-sm-6">
            <dt className="small text-secondary fw-normal">
              {isActive ? "Consegna" : "Inizio previsto"}
            </dt>

            <dd className="fw-semibold mb-0">
              {formatDateTime(rental.actual_starts_at ?? rental.starts_at)}
            </dd>
          </div>

          <div className="col-12 col-sm-6">
            <dt className="small text-secondary fw-normal">Rientro previsto</dt>

            <dd className="fw-semibold mb-0">
              {formatDateTime(rental.expected_ends_at)}
            </dd>
          </div>

          <div className="col-12 col-sm-4">
            <dt className="small text-secondary fw-normal">Totale</dt>

            <dd className="fw-semibold mb-0">
              {formatCurrency(rental.total_amount)}
            </dd>
          </div>

          <div className="col-12 col-sm-4">
            <dt className="small text-secondary fw-normal">Già pagato</dt>

            <dd className="fw-semibold text-success mb-0">
              {formatCurrency(rental.amount_paid)}
            </dd>
          </div>

          <div className="col-12 col-sm-4">
            <dt className="small text-secondary fw-normal">Da saldare</dt>

            <dd className="fw-semibold text-danger mb-0">
              {formatCurrency(rental.balance_due)}
            </dd>
          </div>
        </dl>

        {vehicle && (
          <Link
            to={`/vehicles/${vehicle.id}/${createSlug(
              `${vehicle.brand} ${vehicle.model}`,
            )}`}
            className="btn btn-sm btn-outline-primary"
          >
            Apri scheda veicolo
            <i className="bi bi-arrow-right ms-2" aria-hidden="true"></i>
          </Link>
        )}
      </div>
    </article>
  );
}

function CustomerDetailsPage() {
  // Legge l’identificativo presente nell’indirizzo.
  const { customerId } = useParams();

  // Conserva il cliente restituito da Laravel.
  const [customer, setCustomer] = useState(null);

  // Gestisce il caricamento iniziale.
  const [isLoading, setIsLoading] = useState(true);

  // Conserva un eventuale errore della richiesta.
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function loadCustomer() {
      setIsLoading(true);
      setErrorMessage("");

      try {
        const response = await api.get(`/api/customers/${customerId}`, {
          signal: controller.signal,
        });

        setCustomer(response.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          if (error.response?.status === 404) {
            setErrorMessage("Il cliente richiesto non esiste.");
          } else {
            setErrorMessage(
              "Impossibile caricare il cliente. Riprova più tardi.",
            );
          }
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadCustomer();

    return () => controller.abort();
  }, [customerId]);

  if (isLoading) {
    return <Loader message="Caricamento cliente..." />;
  }

  if (errorMessage) {
    return (
      <>
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>

        <Link to="/customers" className="btn btn-outline-primary">
          Torna ai clienti
        </Link>
      </>
    );
  }

  if (!customer) {
    return null;
  }

  const financialSummary = customer.financial_summary ?? {
    completed_total: "0.00",
    open_total: "0.00",
    paid_total: "0.00",
    balance_due: "0.00",
  };

  const rentalSummary = customer.rental_summary ?? {
    total: 0,
    reserved: 0,
    active: 0,
    completed: 0,
    cancelled: 0,
  };

  return (
    <>
      <Link
        to="/customers"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai clienti
      </Link>

      {/* Identità a sinistra e stato allineato a destra. */}
      <header className="page-header d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
          <span className="page-eyebrow">Scheda cliente</span>

          <h1 className="mb-1">
            {customer.first_name} {customer.last_name}
          </h1>

          <p className="text-secondary mb-0">
            {customer.email || "Email non inserita"}
          </p>
        </div>

        <span
          className={
            customer.is_active
              ? "badge text-bg-success fs-6"
              : "badge text-bg-secondary fs-6"
          }
        >
          {customer.is_active ? "Cliente attivo" : "Cliente disattivato"}
        </span>
      </header>

      {/* Noleggio corrente e prossima prenotazione. */}
      {(customer.active_rental || customer.next_reservation) && (
        <section className="mb-4" aria-labelledby="customer-commitments-title">
          <div className="mb-3">
            <span className="page-eyebrow">Situazione attuale</span>

            <h2 id="customer-commitments-title" className="h4 mb-1">
              Noleggi e prenotazioni aperte
            </h2>

            <p className="text-secondary mb-0">
              Mezzi attualmente consegnati e prossimi impegni del cliente.
            </p>
          </div>

          <div className="row g-4">
            {customer.active_rental && (
              <div className="col-12 col-xl-6">
                <RentalCommitmentCard
                  rental={customer.active_rental}
                  type="active"
                />
              </div>
            )}

            {customer.next_reservation && (
              <div className="col-12 col-xl-6">
                <RentalCommitmentCard
                  rental={customer.next_reservation}
                  type="reserved"
                />
              </div>
            )}
          </div>
        </section>
      )}

      {/* Importi complessivi di tutti i noleggi non annullati. */}
      <section className="mb-4" aria-labelledby="financial-summary-title">
        <div className="mb-3">
          <span className="page-eyebrow">Situazione economica</span>

          <h2 id="financial-summary-title" className="h4 mb-1">
            Riepilogo pagamenti
          </h2>

          <p className="text-secondary mb-0">
            Valore dei noleggi, pagamenti ricevuti e saldo residuo.
          </p>
        </div>

        <div className="row g-3">
          <div className="col-12 col-sm-6 col-xl-3">
            <article className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="small text-secondary mb-2">
                  Noleggi conclusi
                </div>

                <div className="h4 mb-0">
                  {formatCurrency(financialSummary.completed_total)}
                </div>
              </div>
            </article>
          </div>

          <div className="col-12 col-sm-6 col-xl-3">
            <article className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="small text-secondary mb-2">Impegni aperti</div>

                <div className="h4 mb-0">
                  {formatCurrency(financialSummary.open_total)}
                </div>
              </div>
            </article>
          </div>

          <div className="col-12 col-sm-6 col-xl-3">
            <article className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="small text-secondary mb-2">
                  Totale già pagato
                </div>

                <div className="h4 text-success mb-0">
                  {formatCurrency(financialSummary.paid_total)}
                </div>
              </div>
            </article>
          </div>

          <div className="col-12 col-sm-6 col-xl-3">
            <article className="card border-0 shadow-sm h-100">
              <div className="card-body">
                <div className="small text-secondary mb-2">
                  Ancora da saldare
                </div>

                <div
                  className={`h4 mb-0 ${
                    Number(financialSummary.balance_due) > 0
                      ? "text-danger"
                      : "text-success"
                  }`}
                >
                  {formatCurrency(financialSummary.balance_due)}
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>

      {/* Conteggi separati in base allo stato dei noleggi. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <h2 className="h5 mb-4">Storico in sintesi</h2>

          <div className="row g-3">
            <div className="col-6 col-md">
              <div className="small text-secondary">Totali</div>
              <div className="h4 mb-0">{rentalSummary.total}</div>
            </div>

            <div className="col-6 col-md">
              <div className="small text-secondary">Prenotati</div>
              <div className="h4 text-primary mb-0">
                {rentalSummary.reserved}
              </div>
            </div>

            <div className="col-6 col-md">
              <div className="small text-secondary">In corso</div>
              <div className="h4 text-success mb-0">{rentalSummary.active}</div>
            </div>

            <div className="col-6 col-md">
              <div className="small text-secondary">Completati</div>
              <div className="h4 mb-0">{rentalSummary.completed}</div>
            </div>

            <div className="col-6 col-md">
              <div className="small text-secondary">Annullati</div>
              <div className="h4 text-secondary mb-0">
                {rentalSummary.cancelled}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Elenco completo e paginato dei noleggi del cliente. */}
      <CustomerRentalHistory customerId={customer.id} />

      {/* Informazioni anagrafiche e di contatto. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <h2 className="h5 mb-4">Informazioni personali</h2>

          <div className="row g-4">
            <div className="col-12 col-md-6 col-lg-4">
              <div className="small text-secondary">Data di nascita</div>
              <div className="fw-semibold">
                {formatDate(customer.birth_date)}
              </div>
            </div>

            <div className="col-12 col-md-6 col-lg-4">
              <div className="small text-secondary">Codice fiscale</div>
              <div className="fw-semibold">
                {customer.tax_code || "Non inserito"}
              </div>
            </div>

            <div className="col-12 col-md-6 col-lg-4">
              <div className="small text-secondary">Telefono</div>
              <div className="fw-semibold">
                {customer.phone || "Non inserito"}
              </div>
            </div>

            <div className="col-12">
              <div className="small text-secondary">Indirizzo</div>
              <div className="fw-semibold">
                {customer.address || "Non inserito"}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Informazioni relative alla patente. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <h2 className="h5 mb-4">Patente di guida</h2>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <div className="small text-secondary">Numero patente</div>
              <div className="fw-semibold">
                {customer.driving_license_number || "Non inserito"}
              </div>
            </div>

            <div className="col-12 col-md-6">
              <div className="small text-secondary">Data di scadenza</div>
              <div className="fw-semibold">
                {formatDate(customer.driving_license_expiry_date)}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Eventuali annotazioni interne. */}
      {customer.notes && (
        <section className="card border-0 shadow-sm">
          <div className="card-body p-4">
            <h2 className="h5 mb-3">Note</h2>
            <p className="mb-0">{customer.notes}</p>
          </div>
        </section>
      )}
    </>
  );
}

export default CustomerDetailsPage;
