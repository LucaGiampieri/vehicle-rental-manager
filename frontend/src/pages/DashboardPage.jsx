import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import createSlug from "../utils/createSlug";
import { useAuth } from "../context/AuthContext";
import api from "../services/api";

// Formatta gli importi ricevuti dal backend.
function formatCurrency(value) {
  return Number(value ?? 0).toLocaleString("it-IT", {
    style: "currency",
    currency: "EUR",
  });
}

// Trasforma una data tecnica in un formato leggibile.
function formatDate(value) {
  if (!value) {
    return "Non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(new Date(`${value}T00:00:00`));
}

// Formatta data e ora dei noleggi attualmente attivi.
function formatDateTime(value) {
  if (!value) {
    return "Non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

// Limita una percentuale tra zero e cento.
function normalizePercentage(value) {
  return Math.min(Math.max(Number(value ?? 0), 0), 100);
}

// Scheda riutilizzabile per i dati principali.
function DashboardMetricCard({
  icon,
  label,
  value,
  supportingText,
  tone = "primary",
}) {
  return (
    <div className="col-12 col-sm-6 col-xl-3">
      <article className="dashboard-metric-card">
        <span
          className={`dashboard-metric-card__icon dashboard-metric-card__icon--${tone}`}
        >
          <i className={`bi ${icon}`} aria-hidden="true"></i>
        </span>

        <div>
          <div className="dashboard-metric-card__label">{label}</div>

          <div className="dashboard-metric-card__value">{value}</div>

          <div className="dashboard-metric-card__supporting">
            {supportingText}
          </div>
        </div>
      </article>
    </div>
  );
}

function DashboardPage() {
  const { user } = useAuth();

  // Conserva tutti i dati restituiti dall'API dashboard.
  const [dashboard, setDashboard] = useState(null);

  // Gestisce caricamento ed eventuali errori.
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState("");

  // Cambiando questo valore viene ripetuta la richiesta.
  const [reloadToken, setReloadToken] = useState(0);

  useEffect(() => {
    const controller = new AbortController();

    async function loadDashboard() {
      setIsLoading(true);
      setErrorMessage("");

      try {
        const response = await api.get("/api/dashboard", {
          signal: controller.signal,
        });

        setDashboard(response.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage(
            "Impossibile caricare la dashboard. Riprova più tardi.",
          );
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadDashboard();

    return () => controller.abort();
  }, [reloadToken]);

  if (isLoading) {
    return <Loader message="Preparazione della dashboard..." />;
  }

  if (errorMessage) {
    return (
      <div className="dashboard-error">
        <i className="bi bi-exclamation-circle" aria-hidden="true"></i>

        <h1 className="h4">Dashboard non disponibile</h1>

        <p className="text-secondary">{errorMessage}</p>

        <button
          type="button"
          className="btn btn-primary"
          onClick={() => {
            setReloadToken((currentToken) => currentToken + 1);
          }}
        >
          <i className="bi bi-arrow-clockwise me-2" aria-hidden="true"></i>
          Riprova
        </button>
      </div>
    );
  }

  if (!dashboard) {
    return null;
  }

  const firstName = user?.name ? user.name.trim().split(/\s+/)[0] : "Utente";

  const garageRate = normalizePercentage(dashboard.garage.occupancy_rate);

  const utilizationRate = normalizePercentage(
    dashboard.utilization.utilization_rate,
  );

  // Il fallback mantiene compatibilità anche con risposte prive dell'elenco.
  const activeRentals = dashboard.active_rentals ?? [];

  return (
    <>
      {/* Intestazione principale della dashboard. */}
      <section className="dashboard-hero">
        <div className="dashboard-hero__content">
          <span className="dashboard-hero__eyebrow">
            Panoramica della flotta
          </span>

          <h1>Bentornato, {firstName}</h1>

          <p>
            Situazione dal{" "}
            <strong>{formatDate(dashboard.period.date_from)}</strong> al{" "}
            <strong>{formatDate(dashboard.period.date_to)}</strong>.
          </p>
        </div>

        <div className="dashboard-hero__actions">
          <Link to="/vehicles" className="btn dashboard-hero__secondary-button">
            <i className="bi bi-car-front me-2" aria-hidden="true"></i>
            Visualizza flotta
          </Link>

          <Link
            to="/vehicles/new"
            className="btn dashboard-hero__primary-button"
          >
            <i className="bi bi-plus-lg me-2" aria-hidden="true"></i>
            Nuovo veicolo
          </Link>
        </div>
      </section>

      {/* Elenco dettagliato dei veicoli attualmente noleggiati. */}
      <section
        className="dashboard-active-rentals"
        aria-labelledby="active-rentals-title"
      >
        <header className="dashboard-active-rentals__header">
          <div>
            <span className="dashboard-section-card__eyebrow">
              Situazione in tempo reale
            </span>

            <h2 id="active-rentals-title" className="h5 mb-1">
              Veicoli attualmente noleggiati
            </h2>

            <p className="text-secondary small mb-0">
              Mezzi consegnati ai clienti e non ancora rientrati.
            </p>
          </div>

          <span className="dashboard-active-rentals__count">
            {activeRentals.length}{" "}
            {activeRentals.length === 1 ? "noleggio attivo" : "noleggi attivi"}
          </span>
        </header>

        {activeRentals.length > 0 ? (
          <div className="dashboard-active-rentals__grid">
            {activeRentals.map((rental) => {
              const vehicleSlug = createSlug(`${rental.brand}-${rental.model}`);

              return (
                <article
                  key={rental.rental_id}
                  className="dashboard-rental-card"
                >
                  <div className="dashboard-rental-card__top">
                    <span className="dashboard-rental-card__icon">
                      <i
                        className="bi bi-car-front-fill"
                        aria-hidden="true"
                      ></i>
                    </span>

                    <div>
                      <h3 className="h6 mb-1">
                        {rental.brand} {rental.model}
                      </h3>

                      <span className="dashboard-rental-card__plate">
                        {rental.license_plate}
                      </span>
                    </div>
                  </div>

                  <dl className="dashboard-rental-card__details">
                    <div>
                      <dt>
                        <i className="bi bi-person" aria-hidden="true"></i>
                        Cliente
                      </dt>

                      <dd>{rental.customer_name ?? "Non disponibile"}</dd>
                    </div>

                    <div>
                      <dt>
                        <i
                          className="bi bi-box-arrow-up-right"
                          aria-hidden="true"
                        ></i>
                        Consegnato
                      </dt>

                      <dd>{formatDateTime(rental.actual_starts_at)}</dd>
                    </div>

                    <div>
                      <dt>
                        <i
                          className="bi bi-box-arrow-in-down-left"
                          aria-hidden="true"
                        ></i>
                        Rientro previsto
                      </dt>

                      <dd>{formatDateTime(rental.expected_ends_at)}</dd>
                    </div>
                  </dl>

                  <div className="dashboard-rental-card__footer">
                    <div>
                      <span>Pagato</span>

                      <strong>
                        {formatCurrency(rental.amount_paid)} /{" "}
                        {formatCurrency(rental.total_amount)}
                      </strong>
                    </div>

                    <Link
                      to={`/vehicles/${rental.vehicle_id}/${vehicleSlug}`}
                      className="btn btn-sm btn-outline-primary"
                    >
                      Apri veicolo
                      <i
                        className="bi bi-arrow-right ms-2"
                        aria-hidden="true"
                      ></i>
                    </Link>
                  </div>
                </article>
              );
            })}
          </div>
        ) : (
          <div className="dashboard-active-rentals__empty">
            <i className="bi bi-check-circle" aria-hidden="true"></i>

            <div>
              <strong>Nessun veicolo attualmente noleggiato</strong>

              <p className="text-secondary small mb-0">
                Tutti i mezzi risultano rientrati oppure disponibili.
              </p>
            </div>
          </div>
        )}
      </section>

      {/* Dati principali. */}
      <div className="row g-3 mb-4">
        <DashboardMetricCard
          icon="bi-car-front-fill"
          label="Veicoli totali"
          value={dashboard.fleet.total}
          supportingText={`${dashboard.fleet.active} attivi`}
        />

        <DashboardMetricCard
          icon="bi-check2-circle"
          label="Disponibili"
          value={dashboard.fleet.available_for_rental}
          supportingText={`${dashboard.fleet.rented_now} attualmente noleggiati`}
          tone="success"
        />

        <DashboardMetricCard
          icon="bi-calendar-check"
          label="Noleggi attivi"
          value={dashboard.rentals.active}
          supportingText={`${dashboard.rentals.reserved} prenotazioni`}
          tone="warm"
        />

        <DashboardMetricCard
          icon="bi-graph-up-arrow"
          label="Utile previsto"
          value={formatCurrency(dashboard.financial.projected_profit)}
          supportingText={`${formatCurrency(
            dashboard.financial.contracted_revenue,
          )} di contratti`}
          tone={
            dashboard.financial.projected_profit >= 0 ? "primary" : "danger"
          }
        />
      </div>

      <div className="row g-4 mb-4">
        {/* Situazione dell'autorimessa. */}
        <div className="col-12 col-lg-6">
          <section className="dashboard-section-card">
            <div className="dashboard-section-card__header">
              <div>
                <span className="dashboard-section-card__eyebrow">
                  Situazione attuale
                </span>

                <h2 className="h5 mb-0">Autorimessa</h2>
              </div>

              <span className="dashboard-percentage">
                {garageRate.toLocaleString("it-IT")}%
              </span>
            </div>

            <div
              className="dashboard-progress"
              role="progressbar"
              aria-label="Occupazione dell'autorimessa"
              aria-valuemin="0"
              aria-valuemax="100"
              aria-valuenow={garageRate}
            >
              <span
                className="dashboard-progress__bar"
                style={{
                  width: `${garageRate}%`,
                }}
              ></span>
            </div>

            <div className="dashboard-summary-grid">
              <div>
                <span>Celle attive</span>
                <strong>{dashboard.garage.active_spaces}</strong>
              </div>

              <div>
                <span>Occupate</span>
                <strong>{dashboard.garage.occupied_spaces}</strong>
              </div>

              <div>
                <span>Libere</span>
                <strong>{dashboard.garage.free_spaces}</strong>
              </div>
            </div>
          </section>
        </div>

        {/* Percentuale di utilizzo della flotta. */}
        <div className="col-12 col-lg-6">
          <section className="dashboard-section-card">
            <div className="dashboard-section-card__header">
              <div>
                <span className="dashboard-section-card__eyebrow">
                  Periodo selezionato
                </span>

                <h2 className="h5 mb-0">Utilizzo flotta</h2>
              </div>

              <span className="dashboard-percentage">
                {utilizationRate.toLocaleString("it-IT")}%
              </span>
            </div>

            <div
              className="dashboard-progress"
              role="progressbar"
              aria-label="Utilizzo della flotta"
              aria-valuemin="0"
              aria-valuemax="100"
              aria-valuenow={utilizationRate}
            >
              <span
                className="dashboard-progress__bar dashboard-progress__bar--warm"
                style={{
                  width: `${utilizationRate}%`,
                }}
              ></span>
            </div>

            <div className="dashboard-summary-grid">
              <div>
                <span>Mezzi analizzati</span>
                <strong>{dashboard.utilization.vehicles_considered}</strong>
              </div>

              <div>
                <span>Giorni noleggiati</span>
                <strong>{dashboard.utilization.rented_days}</strong>
              </div>

              <div>
                <span>Giorni di giacenza</span>
                <strong>{dashboard.utilization.idle_days}</strong>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div className="row g-4">
        {/* Riepilogo economico. */}
        <div className="col-12 col-lg-7">
          <section className="dashboard-section-card">
            <div className="dashboard-section-card__header">
              <div>
                <span className="dashboard-section-card__eyebrow">
                  Riepilogo economico
                </span>

                <h2 className="h5 mb-0">Entrate e uscite</h2>
              </div>

              <i
                className="bi bi-wallet2 dashboard-section-icon"
                aria-hidden="true"
              ></i>
            </div>

            <div className="dashboard-financial-list">
              <div>
                <span>Importo incassato</span>
                <strong className="text-success">
                  {formatCurrency(dashboard.financial.amount_collected)}
                </strong>
              </div>

              <div>
                <span>Ancora da incassare</span>
                <strong>
                  {formatCurrency(dashboard.financial.amount_outstanding)}
                </strong>
              </div>

              <div>
                <span>Spese totali</span>
                <strong className="text-danger">
                  {formatCurrency(dashboard.financial.total_expenses)}
                </strong>
              </div>

              <div>
                <span>Saldo di cassa</span>
                <strong>
                  {formatCurrency(dashboard.financial.cash_balance)}
                </strong>
              </div>
            </div>
          </section>
        </div>

        {/* Scadenze operative. */}
        <div className="col-12 col-lg-5">
          <section className="dashboard-section-card">
            <div className="dashboard-section-card__header">
              <div>
                <span className="dashboard-section-card__eyebrow">
                  Prossimi controlli
                </span>

                <h2 className="h5 mb-0">Scadenze</h2>
              </div>

              <i
                className="bi bi-calendar-event dashboard-section-icon"
                aria-hidden="true"
              ></i>
            </div>

            <div className="dashboard-deadlines">
              <div className="dashboard-deadlines__item dashboard-deadlines__item--danger">
                <span>
                  <i
                    className="bi bi-exclamation-triangle"
                    aria-hidden="true"
                  ></i>
                  Scadute
                </span>

                <strong>{dashboard.deadlines.overdue_count}</strong>
              </div>

              <div className="dashboard-deadlines__item">
                <span>
                  <i className="bi bi-clock" aria-hidden="true"></i>
                  Entro 30 giorni
                </span>

                <strong>{dashboard.deadlines.upcoming_30_days_count}</strong>
              </div>
            </div>
          </section>
        </div>
      </div>
    </>
  );
}

export default DashboardPage;
