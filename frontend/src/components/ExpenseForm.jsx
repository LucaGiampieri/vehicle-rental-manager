import { Link } from "react-router-dom";
import { EXPENSE_CATEGORIES } from "../utils/expenseOptions";

function ExpenseForm({
  values,
  vehicles,
  fieldErrors,
  isSubmitting,
  submitLabel,
  cancelTo,
  onChange,
  onSubmit,
}) {
  function handleChange(event) {
    onChange(event.target.name, event.target.value);
  }

  function fieldClass(fieldName, baseClass = "form-control") {
    return `${baseClass} ${fieldErrors[fieldName] ? "is-invalid" : ""}`;
  }

  // Recupera il mezzo selezionato per mostrare il contachilometri memorizzato.
  const selectedVehicle = vehicles.find(
    (vehicle) => String(vehicle.id) === String(values.vehicle_id),
  );

  return (
    <form onSubmit={onSubmit} noValidate>
      {/* Dati principali che rendono identificabile la spesa. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <span className="page-eyebrow">Costo sostenuto</span>
          <h2 className="h4 mb-1">Dati principali</h2>
          <p className="text-secondary mb-4">
            Indica il mezzo, la tipologia e il motivo preciso del pagamento.
          </p>

          <div className="row g-4">
            <div className="col-12 col-lg-6">
              <label htmlFor="expense-vehicle" className="form-label fw-semibold">
                Veicolo *
              </label>
              <select
                id="expense-vehicle"
                name="vehicle_id"
                className={fieldClass("vehicle_id", "form-select")}
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
              {fieldErrors.vehicle_id && (
                <div className="invalid-feedback">{fieldErrors.vehicle_id[0]}</div>
              )}
            </div>

            <div className="col-12 col-lg-6">
              <label htmlFor="expense-category" className="form-label fw-semibold">
                Categoria *
              </label>
              <select
                id="expense-category"
                name="category"
                className={fieldClass("category", "form-select")}
                value={values.category}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              >
                <option value="">Seleziona una categoria</option>
                {Object.entries(EXPENSE_CATEGORIES).map(([value, category]) => (
                  <option key={value} value={value}>
                    {category.label}
                  </option>
                ))}
              </select>
              {fieldErrors.category && (
                <div className="invalid-feedback">{fieldErrors.category[0]}</div>
              )}
            </div>

            <div className="col-12 col-lg-8">
              <label htmlFor="expense-description" className="form-label fw-semibold">
                Descrizione *
              </label>
              <input
                id="expense-description"
                name="description"
                type="text"
                maxLength={255}
                className={fieldClass("description")}
                placeholder="Esempio: tagliando completo con cambio olio e filtri"
                value={values.description}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />
              {fieldErrors.description && (
                <div className="invalid-feedback">{fieldErrors.description[0]}</div>
              )}
            </div>

            <div className="col-12 col-sm-6 col-lg-4">
              <label htmlFor="expense-amount" className="form-label fw-semibold">
                Importo pagato *
              </label>
              <div className="input-group">
                <span className="input-group-text">€</span>
                <input
                  id="expense-amount"
                  name="amount"
                  type="number"
                  min="0.01"
                  max="99999999.99"
                  step="0.01"
                  className={fieldClass("amount")}
                  value={values.amount}
                  required
                  disabled={isSubmitting}
                  onChange={handleChange}
                />
                {fieldErrors.amount && (
                  <div className="invalid-feedback">{fieldErrors.amount[0]}</div>
                )}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Il primo campo è il pagamento già avvenuto; il secondo è la validità. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <span className="page-eyebrow">Date</span>
          <h2 className="h4 mb-1">Pagamento e prossima scadenza</h2>
          <p className="text-secondary mb-4">
            La scadenza è facoltativa e indica quando termina la validità del
            servizio pagato.
          </p>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <label htmlFor="expense-date" className="form-label fw-semibold">
                Pagata il *
              </label>
              <input
                id="expense-date"
                name="expense_date"
                type="date"
                max={new Date().toISOString().slice(0, 10)}
                className={fieldClass("expense_date")}
                value={values.expense_date}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />
              {fieldErrors.expense_date && (
                <div className="invalid-feedback">{fieldErrors.expense_date[0]}</div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label htmlFor="expense-expiry" className="form-label fw-semibold">
                Valida fino al / prossima scadenza
              </label>
              <input
                id="expense-expiry"
                name="expires_on"
                type="date"
                min={values.expense_date || undefined}
                className={fieldClass("expires_on")}
                value={values.expires_on}
                disabled={isSubmitting}
                onChange={handleChange}
              />
              <div className="form-text">
                Utile per assicurazione, bollo, revisione o manutenzione periodica.
              </div>
              {fieldErrors.expires_on && (
                <div className="invalid-feedback">{fieldErrors.expires_on[0]}</div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* Informazioni tecniche e amministrative facoltative. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <span className="page-eyebrow">Dettagli</span>
          <h2 className="h4 mb-4">Fornitore e veicolo</h2>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <label htmlFor="expense-supplier" className="form-label fw-semibold">
                Fornitore
              </label>
              <input
                id="expense-supplier"
                name="supplier"
                type="text"
                maxLength={150}
                className={fieldClass("supplier")}
                placeholder="Officina, assicurazione o distributore"
                value={values.supplier}
                disabled={isSubmitting}
                onChange={handleChange}
              />
              {fieldErrors.supplier && (
                <div className="invalid-feedback">{fieldErrors.supplier[0]}</div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label htmlFor="expense-mileage" className="form-label fw-semibold">
                Chilometraggio al momento della spesa
              </label>
              <div className="input-group">
                <input
                  id="expense-mileage"
                  name="mileage"
                  type="number"
                  min="0"
                  className={fieldClass("mileage")}
                  value={values.mileage}
                  disabled={isSubmitting}
                  onChange={handleChange}
                />
                <span className="input-group-text">km</span>
                {fieldErrors.mileage && (
                  <div className="invalid-feedback">{fieldErrors.mileage[0]}</div>
                )}
              </div>
              {selectedVehicle && (
                <div className="form-text">
                  Chilometraggio attuale del veicolo:{" "}
                  <strong>
                    {Number(selectedVehicle.mileage).toLocaleString("it-IT")} km
                  </strong>
                  . Il valore è stato proposto automaticamente, ma puoi
                  correggerlo se la lettura reale è diversa.
                </div>
              )}
            </div>

            <div className="col-12">
              <label htmlFor="expense-notes" className="form-label fw-semibold">
                Note
              </label>
              <textarea
                id="expense-notes"
                name="notes"
                rows="4"
                maxLength={5000}
                className={fieldClass("notes")}
                value={values.notes}
                disabled={isSubmitting}
                onChange={handleChange}
              ></textarea>
              {fieldErrors.notes && (
                <div className="invalid-feedback">{fieldErrors.notes[0]}</div>
              )}
            </div>
          </div>
        </div>
      </section>

      <div className="d-flex flex-wrap justify-content-end gap-3">
        <Link to={cancelTo} className="btn btn-outline-secondary">
          Annulla
        </Link>
        <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
          {isSubmitting ? "Salvataggio..." : submitLabel}
        </button>
      </div>
    </form>
  );
}

export default ExpenseForm;
