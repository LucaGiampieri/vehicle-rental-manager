import { Link } from "react-router-dom";

// Formatta gli importi dell'anteprima secondo le convenzioni italiane.
function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

/*
 * Replica il calcolo utilizzato dal Model Rental di Laravel:
 * ogni frazione di 24 ore viene arrotondata a una giornata intera.
 */
function calculateRentalPreview(startsAt, endsAt, dailyRate) {
  if (!startsAt || !endsAt || !dailyRate) {
    return null;
  }

  const start = new Date(startsAt);
  const end = new Date(endsAt);
  const duration = end.getTime() - start.getTime();

  if (Number.isNaN(duration) || duration <= 0) {
    return null;
  }

  const millisecondsInDay = 24 * 60 * 60 * 1000;
  const days = Math.max(1, Math.ceil(duration / millisecondsInDay));

  // Arrotonda prima la tariffa in centesimi come avviene nel backend.
  const dailyRateInCents = Math.round(Number(dailyRate) * 100);
  const totalInCents = dailyRateInCents * days;

  return {
    days,
    total: totalInCents / 100,
  };
}

/*
 * Form condivisibile dalle future pagine di creazione e modifica.
 * La pagina genitore gestisce dati e richieste; questo componente
 * si occupa della visualizzazione dei campi e dell'anteprima.
 */
function RentalForm({
  values,
  customers,
  vehicles,
  fieldErrors,
  isSubmitting,
  submitLabel,
  cancelTo,
  onChange,
  onSubmit,
}) {
  // Aggiorna il campo indicato attraverso la funzione della pagina genitore.
  function handleChange(event) {
    onChange(event.target.name, event.target.value);
  }

  // Evidenzia con Bootstrap i campi rifiutati dalla validazione Laravel.
  function getFieldClass(fieldName, baseClass) {
    return `${baseClass} ${fieldErrors[fieldName] ? "is-invalid" : ""}`;
  }

  const preview = calculateRentalPreview(
    values.starts_at,
    values.expected_ends_at,
    values.daily_rate,
  );

  const amountPaid = Number(values.amount_paid || 0);
  const previewBalance = preview
    ? Math.max(0, preview.total - amountPaid)
    : 0;

  return (
    <form onSubmit={onSubmit} noValidate>
      {/* Selezione delle due anagrafiche collegate alla prenotazione. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <div className="mb-4">
            <span className="page-eyebrow">Assegnazione</span>
            <h2 className="h4 mb-1">Cliente e veicolo</h2>
            <p className="text-secondary mb-0">
              Seleziona chi effettua la prenotazione e il mezzo richiesto.
            </p>
          </div>

          <div className="row g-4">
            <div className="col-12 col-lg-6">
              <label
                htmlFor="rental-customer"
                className="form-label fw-semibold"
              >
                Cliente *
              </label>
              <select
                id="rental-customer"
                name="customer_id"
                className={getFieldClass("customer_id", "form-select")}
                value={values.customer_id}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              >
                <option value="">Seleziona un cliente</option>
                {customers.map((customer) => (
                  <option key={customer.id} value={customer.id}>
                    {customer.last_name} {customer.first_name} — patente {customer.driving_license_number}
                  </option>
                ))}
              </select>
              {fieldErrors.customer_id && (
                <div className="invalid-feedback">
                  {fieldErrors.customer_id[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-lg-6">
              <label
                htmlFor="rental-vehicle"
                className="form-label fw-semibold"
              >
                Veicolo *
              </label>
              <select
                id="rental-vehicle"
                name="vehicle_id"
                className={getFieldClass("vehicle_id", "form-select")}
                value={values.vehicle_id}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              >
                <option value="">Seleziona un veicolo</option>
                {vehicles.map((vehicle) => (
                  <option key={vehicle.id} value={vehicle.id}>
                    {vehicle.brand} {vehicle.model} — {vehicle.license_plate}
                  </option>
                ))}
              </select>
              <div className="form-text">
                La disponibilità definitiva viene verificata al salvataggio.
              </div>
              {fieldErrors.vehicle_id && (
                <div className="invalid-feedback">
                  {fieldErrors.vehicle_id[0]}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* Periodo previsto della prenotazione. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <div className="mb-4">
            <span className="page-eyebrow">Periodo</span>
            <h2 className="h4 mb-1">Consegna e rientro</h2>
            <p className="text-secondary mb-0">
              Indica data e ora previste per l'utilizzo del mezzo.
            </p>
          </div>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <label
                htmlFor="rental-starts-at"
                className="form-label fw-semibold"
              >
                Inizio previsto *
              </label>
              <input
                id="rental-starts-at"
                name="starts_at"
                type="datetime-local"
                className={getFieldClass("starts_at", "form-control")}
                value={values.starts_at}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />
              {fieldErrors.starts_at && (
                <div className="invalid-feedback">
                  {fieldErrors.starts_at[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="rental-expected-ends-at"
                className="form-label fw-semibold"
              >
                Rientro previsto *
              </label>
              <input
                id="rental-expected-ends-at"
                name="expected_ends_at"
                type="datetime-local"
                className={getFieldClass(
                  "expected_ends_at",
                  "form-control",
                )}
                value={values.expected_ends_at}
                min={values.starts_at || undefined}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />
              {fieldErrors.expected_ends_at && (
                <div className="invalid-feedback">
                  {fieldErrors.expected_ends_at[0]}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* Tariffa concordata, caparra e riepilogo calcolato. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <div className="mb-4">
            <span className="page-eyebrow">Importi</span>
            <h2 className="h4 mb-1">Prezzo e pagamento iniziale</h2>
            <p className="text-secondary mb-0">
              La tariffa viene proposta dal veicolo ma può essere concordata.
            </p>
          </div>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <label
                htmlFor="rental-daily-rate"
                className="form-label fw-semibold"
              >
                Tariffa giornaliera *
              </label>
              <div className="input-group">
                <span className="input-group-text">€</span>
                <input
                  id="rental-daily-rate"
                  name="daily_rate"
                  type="number"
                  min="0.01"
                  max="99999999.99"
                  step="0.01"
                  className={getFieldClass("daily_rate", "form-control")}
                  value={values.daily_rate}
                  required
                  disabled={isSubmitting}
                  onChange={handleChange}
                />
                {fieldErrors.daily_rate && (
                  <div className="invalid-feedback">
                    {fieldErrors.daily_rate[0]}
                  </div>
                )}
              </div>
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="rental-amount-paid"
                className="form-label fw-semibold"
              >
                Caparra o importo già pagato
              </label>
              <div className="input-group">
                <span className="input-group-text">€</span>
                <input
                  id="rental-amount-paid"
                  name="amount_paid"
                  type="number"
                  min="0"
                  max={preview?.total ?? 99999999.99}
                  step="0.01"
                  className={getFieldClass("amount_paid", "form-control")}
                  value={values.amount_paid}
                  disabled={isSubmitting}
                  onChange={handleChange}
                />
                {fieldErrors.amount_paid && (
                  <div className="invalid-feedback">
                    {fieldErrors.amount_paid[0]}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* L'anteprima compare soltanto quando il periodo è valido. */}
          {preview && (
            <div className="alert alert-primary mt-4 mb-0" role="status">
              <div className="row g-3">
                <div className="col-12 col-sm-4">
                  <div className="small">Giornate addebitate</div>
                  <strong>{preview.days}</strong>
                </div>
                <div className="col-12 col-sm-4">
                  <div className="small">Totale previsto</div>
                  <strong>{formatCurrency(preview.total)}</strong>
                </div>
                <div className="col-12 col-sm-4">
                  <div className="small">Saldo previsto</div>
                  <strong>{formatCurrency(previewBalance)}</strong>
                </div>
              </div>
            </div>
          )}
        </div>
      </section>

      {/* Annotazioni operative facoltative. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <label htmlFor="rental-notes" className="form-label fw-semibold">
            Note
          </label>
          <textarea
            id="rental-notes"
            name="notes"
            rows="4"
            maxLength={5000}
            className={getFieldClass("notes", "form-control")}
            value={values.notes}
            disabled={isSubmitting}
            onChange={handleChange}
          ></textarea>
          {fieldErrors.notes && (
            <div className="invalid-feedback">{fieldErrors.notes[0]}</div>
          )}
        </div>
      </section>

      {/* Azioni finali del form. */}
      <div className="d-flex flex-wrap justify-content-end gap-3">
        <Link to={cancelTo} className="btn btn-outline-secondary">
          Annulla
        </Link>
        <button
          type="submit"
          className="btn btn-primary"
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <>
              <span
                className="spinner-border spinner-border-sm me-2"
                aria-hidden="true"
              ></span>
              Salvataggio...
            </>
          ) : (
            submitLabel
          )}
        </button>
      </div>
    </form>
  );
}

export default RentalForm;
