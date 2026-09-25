import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import Loader from "../components/Loader";
import createSlug from "../utils/createSlug";
import { useAuth } from "../context/AuthContext";
import api from "../services/api";

// Traduce le categorie tecniche delle spese in etichette comprensibili.
const EXPENSE_CATEGORY_LABELS = {
  purchase: "Acquisto",
  maintenance: "Manutenzione",
  insurance: "Assicurazione",
  road_tax: "Bollo",
  inspection: "Revisione",
  fuel: "Carburante",
  cleaning: "Pulizia",
  repair: "Riparazione",
  other: "Altro",
};

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

// Mostra valori decimali brevi, evitando numeri come 16,48 giorni.
function formatNumber(value, maximumFractionDigits = 1) {
  return Number(value ?? 0).toLocaleString("it-IT", {
    maximumFractionDigits,
  });
}

// Restituisce una frase chiara per una scadenza.
function formatDeadlineDistance(daysRemaining) {
  const days = Number(daysRemaining);

  if (days < 0) {
    const overdueDays = Math.abs(days);
    return `Scaduta da ${overdueDays} ${overdueDays === 1 ? "giorno" : "giorni"}`;
  }

  if (days === 0) {
    return "Scade oggi";
  }

  return `Scade tra ${days} ${days === 1 ? "giorno" : "giorni"}`;
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

// Mostra una singola scadenza con veicolo, importo e data.
function DashboardDeadlineCard({ deadline }) {
  const vehicleSlug = createSlug(`${deadline.brand}-${deadline.model}`);

  return (
    <article
      className={`dashboard-deadline-card dashboard-deadline-card--${deadline.status}`}
    >
      <div className="dashboard-deadline-card__top">
        <div>
          <span>
            {EXPENSE_CATEGORY_LABELS[deadline.category] ?? deadline.category}
          </span>
          <strong>{deadline.description}</strong>
        </div>

        <strong>{formatCurrency(deadline.amount)}</strong>
      </div>

      <div className="dashboard-deadline-card__vehicle">
        <i className="bi bi-car-front" aria-hidden="true"></i>
        <span>
          {deadline.brand} {deadline.model} · {deadline.license_plate}
        </span>
      </div>

      <div className="dashboard-deadline-card__footer">
        <div>
          <strong>{formatDeadlineDistance(deadline.days_remaining)}</strong>
          <span>{formatDate(deadline.expires_on)}</span>
        </div>

        {deadline.vehicle_id && (
          <Link
            to={`/vehicles/${deadline.vehicle_id}/${vehicleSlug}`}
            aria-label={`Apri ${deadline.brand} ${deadline.model}`}
          >
            <i className="bi bi-arrow-right" aria-hidden="true"></i>
          </Link>
        )}
      </div>
    </article>
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

  // I fallback mantengono compatibilità durante gli aggiornamenti dell'API.
  const activeRentals = dashboard.active_rentals ?? [];
  const upcomingReservations = dashboard.upcoming_reservations?.items ?? [];
  const upcomingReservationsTotal = dashboard.upcoming_reservations?.total ?? 0;
  const recentExpenses = dashboard.financial.recent_expenses ?? [];
  const expenseCategories = dashboard.financial.expenses_by_category ?? [];
  const deadlines = dashboard.deadlines.items ?? [];
  const upcomingDeadlines = deadlines.filter(
    (deadline) => deadline.status === "upcoming",
  );
  const overdueDeadlines = deadlines.filter(
    (deadline) => deadline.status === "overdue",
  );
  const projectedProfit = Number(dashboard.financial.projected_profit ?? 0);
  const projectedResultLabel =
    projectedProfit >= 0 ? "Utile previsto" : "Perdita prevista";

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

      {/* Prenotazioni future, indipendenti dal periodo economico. */}
      <section
        className="dashboard-upcoming-reservations"
        aria-labelledby="upcoming-reservations-title"
      >
        <header className="dashboard-list-section__header">
          <div>
            <span className="dashboard-section-card__eyebrow">
              Programmazione futura
            </span>

            <h2 id="upcoming-reservations-title" className="h5 mb-1">
              Prossime prenotazioni
            </h2>

            <p className="text-secondary small mb-0">
              Contratti prenotati che devono ancora iniziare.
            </p>
          </div>

          <span className="dashboard-list-section__count">
            {upcomingReservationsTotal}{" "}
            {upcomingReservationsTotal === 1
              ? "prenotazione futura"
              : "prenotazioni future"}
          </span>
        </header>

        {upcomingReservations.length > 0 ? (
          <div className="dashboard-reservation-list">
            {upcomingReservations.map((reservation) => {
              const vehicleSlug = createSlug(
                `${reservation.brand}-${reservation.model}`,
              );

              return (
                <article
                  key={reservation.rental_id}
                  className="dashboard-reservation-row"
                >
                  <span className="dashboard-reservation-row__icon">
                    <i className="bi bi-calendar2-check" aria-hidden="true"></i>
                  </span>

                  <div className="dashboard-reservation-row__vehicle">
                    <strong>
                      {reservation.brand} {reservation.model}
                    </strong>
                    <span>{reservation.license_plate}</span>
                  </div>

                  <div className="dashboard-reservation-row__detail">
                    <span>Cliente</span>
                    <strong>
                      {reservation.customer_name ?? "Non disponibile"}
                    </strong>
                  </div>

                  <div className="dashboard-reservation-row__detail">
                    <span>Periodo prenotato</span>
                    <strong>
                      {formatDateTime(reservation.starts_at)} –{" "}
                      {formatDateTime(reservation.expected_ends_at)}
                    </strong>
                  </div>

                  <Link
                    to={`/vehicles/${reservation.vehicle_id}/${vehicleSlug}`}
                    className="btn btn-sm btn-outline-primary"
                  >
                    Apri veicolo
                  </Link>
                </article>
              );
            })}
          </div>
        ) : (
          <div className="dashboard-list-section__empty">
            <i className="bi bi-calendar2" aria-hidden="true"></i>
            <span>Non ci sono prenotazioni future.</span>
          </div>
        )}
      </section>

      {/* Dati principali. */}
      <div className="row g-3 mb-4">
        <DashboardMetricCard
          icon="bi-check2-circle"
          label="Disponibili ora"
          value={dashboard.fleet.available_for_rental}
          supportingText={`${dashboard.fleet.available_for_rental} su ${dashboard.fleet.active} mezzi attivi`}
          tone="success"
        />

        <DashboardMetricCard
          icon="bi-key-fill"
          label="Noleggiati ora"
          value={dashboard.fleet.rented_now}
          supportingText="Mezzi consegnati e non rientrati"
          tone="warm"
        />

        <DashboardMetricCard
          icon="bi-calendar2-check-fill"
          label="Prossime prenotazioni"
          value={upcomingReservationsTotal}
          supportingText={`${dashboard.rentals.reserved} nel periodo selezionato`}
          tone="primary"
        />

        <DashboardMetricCard
          icon="bi-car-front-fill"
          label="Mezzi attivi"
          value={dashboard.fleet.active}
          supportingText={`${dashboard.fleet.total} totali · ${dashboard.fleet.inactive} fuori servizio`}
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
                <strong>
                  {formatNumber(dashboard.utilization.rented_days)}
                </strong>
              </div>

              <div>
                <span>Giorni di giacenza</span>
                <strong>{formatNumber(dashboard.utilization.idle_days)}</strong>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div className="row g-4 align-items-start">
        {/* Riepilogo economico. */}
        <div className="col-12 col-lg-5">
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

            <div
              className={`dashboard-financial-result ${
                projectedProfit >= 0
                  ? "dashboard-financial-result--positive"
                  : "dashboard-financial-result--negative"
              }`}
            >
              <div>
                <span>{projectedResultLabel}</span>
                <small>
                  Ricavi dei contratti meno spese del periodo selezionato
                </small>
              </div>

              <strong>{formatCurrency(Math.abs(projectedProfit))}</strong>
            </div>

            <div className="dashboard-financial-list">
              <div>
                <span>Valore dei contratti</span>
                <strong>
                  {formatCurrency(dashboard.financial.contracted_revenue)}
                </strong>
              </div>

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

        {/* Suddivisione delle spese per categoria. */}
        <div className="col-12 col-lg-7">
          <section className="dashboard-section-card">
            <div className="dashboard-section-card__header">
              <div>
                <span className="dashboard-section-card__eyebrow">
                  Dettaglio dei costi
                </span>

                <h2 className="h5 mb-0">Suddivisione spese</h2>
              </div>

              <i
                className="bi bi-pie-chart dashboard-section-icon"
                aria-hidden="true"
              ></i>
            </div>

            <div className="dashboard-expense-breakdown dashboard-expense-breakdown--standalone">
              {expenseCategories.length > 0 ? (
                <div className="dashboard-expense-breakdown__list">
                  {expenseCategories.map((category) => (
                    <div key={category.category}>
                      <div>
                        <strong>
                          {EXPENSE_CATEGORY_LABELS[category.category] ??
                            category.category}
                        </strong>
                        <span>
                          {category.count}{" "}
                          {category.count === 1
                            ? "spesa registrata"
                            : "spese registrate"}
                        </span>
                      </div>

                      <strong>{formatCurrency(category.total)}</strong>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-secondary small mb-0">
                  Nessuna spesa registrata nel periodo selezionato.
                </p>
              )}
            </div>
          </section>
        </div>

        {/* Scadenze operative. */}
        <div className="col-12">
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

            <div className="dashboard-deadline-columns">
              {upcomingDeadlines.length > 0 && (
                <div className="dashboard-deadline-group">
                  <div className="dashboard-deadline-group__header">
                    <div>
                      <i className="bi bi-clock" aria-hidden="true"></i>
                      <strong>In scadenza</strong>
                    </div>
                    <span>{upcomingDeadlines.length}</span>
                  </div>

                  <p className="dashboard-deadline-group__description">
                    Da gestire entro 30 giorni, in ordine di urgenza.
                  </p>

                  <div className="dashboard-deadline-list">
                    {upcomingDeadlines.map((deadline) => (
                      <DashboardDeadlineCard
                        key={deadline.expense_id}
                        deadline={deadline}
                      />
                    ))}
                  </div>
                </div>
              )}

              {overdueDeadlines.length > 0 && (
                <div className="dashboard-deadline-group dashboard-deadline-group--overdue">
                  <div className="dashboard-deadline-group__header">
                    <div>
                      <i
                        className="bi bi-exclamation-triangle"
                        aria-hidden="true"
                      ></i>
                      <strong>Già scadute</strong>
                    </div>
                    <span>{overdueDeadlines.length}</span>
                  </div>

                  <p className="dashboard-deadline-group__description">
                    Scadenze superate che richiedono un controllo.
                  </p>

                  <div className="dashboard-deadline-list">
                    {overdueDeadlines.map((deadline) => (
                      <DashboardDeadlineCard
                        key={deadline.expense_id}
                        deadline={deadline}
                      />
                    ))}
                  </div>
                </div>
              )}
            </div>

            {deadlines.length === 0 && (
              <div className="dashboard-list-section__empty dashboard-list-section__empty--compact">
                <i className="bi bi-check-circle" aria-hidden="true"></i>
                <span>Nessuna scadenza urgente.</span>
              </div>
            )}
          </section>
        </div>
      </div>

      {/* Spese più recenti comprese nel periodo selezionato. */}
      <section className="dashboard-recent-expenses">
        <header className="dashboard-list-section__header">
          <div>
            <span className="dashboard-section-card__eyebrow">
              Dettaglio dei costi
            </span>

            <h2 className="h5 mb-1">Ultime spese del periodo</h2>

            <p className="text-secondary small mb-0">
              Costi più recenti con veicolo, data e importo.
            </p>
          </div>
        </header>

        {recentExpenses.length > 0 ? (
          <div className="dashboard-expense-list">
            {recentExpenses.map((expense) => {
              const vehicleSlug = createSlug(
                `${expense.brand}-${expense.model}`,
              );

              return (
                <article
                  key={expense.expense_id}
                  className="dashboard-expense-row"
                >
                  <span className="dashboard-expense-row__icon">
                    <i className="bi bi-receipt" aria-hidden="true"></i>
                  </span>

                  <div className="dashboard-expense-row__description">
                    <strong>{expense.description}</strong>
                    <span>
                      {EXPENSE_CATEGORY_LABELS[expense.category] ??
                        expense.category}
                    </span>
                  </div>

                  <div className="dashboard-expense-row__vehicle">
                    <span>Veicolo</span>
                    <strong>
                      {expense.brand} {expense.model} · {expense.license_plate}
                    </strong>
                  </div>

                  <div className="dashboard-expense-row__date">
                    <span>Registrata il</span>
                    <strong>{formatDate(expense.expense_date)}</strong>
                  </div>

                  <strong className="dashboard-expense-row__amount">
                    {formatCurrency(expense.amount)}
                  </strong>

                  {expense.vehicle_id && (
                    <Link
                      to={`/vehicles/${expense.vehicle_id}/${vehicleSlug}`}
                      className="dashboard-expense-row__link"
                      aria-label={`Apri ${expense.brand} ${expense.model}`}
                    >
                      <i className="bi bi-arrow-right" aria-hidden="true"></i>
                    </Link>
                  )}
                </article>
              );
            })}
          </div>
        ) : (
          <div className="dashboard-list-section__empty">
            <i className="bi bi-receipt" aria-hidden="true"></i>
            <span>Nessuna spesa registrata nel periodo.</span>
          </div>
        )}
      </section>
    </>
  );
}

export default DashboardPage;
