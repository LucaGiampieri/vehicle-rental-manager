import { Link } from "react-router-dom";

/*
 * Form condiviso dalle pagine di creazione e modifica.
 *
 * I dati e la richiesta API rimangono nelle pagine genitore;
 * questo componente si occupa soltanto di mostrare i campi.
 */
function CustomerForm({
  values,
  fieldErrors,
  isSubmitting,
  submitLabel,
  cancelTo,
  onChange,
  onSubmit,
}) {
  /*
   * Aggiorna il campo corretto.
   *
   * Per la checkbox utilizza checked, che è booleano.
   * Per gli altri elementi utilizza value, che è testuale.
   */
  function handleChange(event) {
    const { name, type, value, checked } = event.target;

    onChange(name, type === "checkbox" ? checked : value);
  }

  // Aggiunge la classe Bootstrap quando Laravel segnala un errore.
  function getFieldClass(fieldName, baseClass) {
    return `${baseClass} ${fieldErrors[fieldName] ? "is-invalid" : ""}`;
  }

  /*
   * Impedisce di selezionare una data di nascita futura
   * e una data non da maggiorenne
   * Il backend esegue comunque lo stesso controllo.
   */

  const minimumAdultBirthDate = new Intl.DateTimeFormat("en-CA").format(
    new Date(
      new Date().getFullYear() - 18,
      new Date().getMonth(),
      new Date().getDate(),
    ),
  );

  return (
    <form onSubmit={onSubmit} noValidate>
      {/* Dati anagrafici principali. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <div className="mb-4">
            <span className="page-eyebrow">Anagrafica</span>

            <h2 className="h4 mb-1">Informazioni personali</h2>

            <p className="text-secondary mb-0">
              Inserisci i dati identificativi e i contatti del cliente.
            </p>
          </div>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-first-name"
                className="form-label fw-semibold"
              >
                Nome *
              </label>

              <input
                id="customer-first-name"
                name="first_name"
                type="text"
                className={getFieldClass("first_name", "form-control")}
                value={values.first_name}
                maxLength={100}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.first_name && (
                <div className="invalid-feedback">
                  {fieldErrors.first_name[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-last-name"
                className="form-label fw-semibold"
              >
                Cognome *
              </label>

              <input
                id="customer-last-name"
                name="last_name"
                type="text"
                className={getFieldClass("last_name", "form-control")}
                value={values.last_name}
                maxLength={100}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.last_name && (
                <div className="invalid-feedback">
                  {fieldErrors.last_name[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-birth-date"
                className="form-label fw-semibold"
              >
                Data di nascita
              </label>

              <input
                id="customer-birth-date"
                name="birth_date"
                type="date"
                className={getFieldClass("birth_date", "form-control")}
                value={values.birth_date}
                max={minimumAdultBirthDate}
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.birth_date && (
                <div className="invalid-feedback">
                  {fieldErrors.birth_date[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-tax-code"
                className="form-label fw-semibold"
              >
                Codice fiscale o identificativo
              </label>

              <input
                id="customer-tax-code"
                name="tax_code"
                type="text"
                className={getFieldClass("tax_code", "form-control")}
                value={values.tax_code}
                maxLength={32}
                disabled={isSubmitting}
                onChange={handleChange}
              />

              <div className="form-text">
                Sono accettati anche identificativi fiscali esteri.
              </div>

              {fieldErrors.tax_code && (
                <div className="invalid-feedback">
                  {fieldErrors.tax_code[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-email"
                className="form-label fw-semibold"
              >
                Email
              </label>

              <input
                id="customer-email"
                name="email"
                type="email"
                className={getFieldClass("email", "form-control")}
                value={values.email}
                maxLength={255}
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.email && (
                <div className="invalid-feedback">{fieldErrors.email[0]}</div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-phone"
                className="form-label fw-semibold"
              >
                Telefono
              </label>

              <input
                id="customer-phone"
                name="phone"
                type="tel"
                className={getFieldClass("phone", "form-control")}
                value={values.phone}
                maxLength={30}
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.phone && (
                <div className="invalid-feedback">{fieldErrors.phone[0]}</div>
              )}
            </div>

            <div className="col-12">
              <label
                htmlFor="customer-address"
                className="form-label fw-semibold"
              >
                Indirizzo
              </label>

              <input
                id="customer-address"
                name="address"
                type="text"
                className={getFieldClass("address", "form-control")}
                value={values.address}
                maxLength={255}
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.address && (
                <div className="invalid-feedback">{fieldErrors.address[0]}</div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* Dati della patente di guida. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <div className="mb-4">
            <span className="page-eyebrow">Documento di guida</span>

            <h2 className="h4 mb-1">Patente</h2>

            <p className="text-secondary mb-0">
              La patente è necessaria per associare il cliente a un noleggio.
            </p>
          </div>

          <div className="row g-4">
            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-license-number"
                className="form-label fw-semibold"
              >
                Numero patente *
              </label>

              <input
                id="customer-license-number"
                name="driving_license_number"
                type="text"
                className={getFieldClass(
                  "driving_license_number",
                  "form-control",
                )}
                value={values.driving_license_number}
                maxLength={50}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.driving_license_number && (
                <div className="invalid-feedback">
                  {fieldErrors.driving_license_number[0]}
                </div>
              )}
            </div>

            <div className="col-12 col-md-6">
              <label
                htmlFor="customer-license-expiry"
                className="form-label fw-semibold"
              >
                Scadenza patente *
              </label>

              <input
                id="customer-license-expiry"
                name="driving_license_expiry_date"
                type="date"
                className={getFieldClass(
                  "driving_license_expiry_date",
                  "form-control",
                )}
                value={values.driving_license_expiry_date}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              />

              {fieldErrors.driving_license_expiry_date && (
                <div className="invalid-feedback">
                  {fieldErrors.driving_license_expiry_date[0]}
                </div>
              )}
            </div>
          </div>
        </div>
      </section>

      {/* Note interne e stato del cliente. */}
      <section className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <div className="mb-4">
            <span className="page-eyebrow">Gestione interna</span>

            <h2 className="h4 mb-1">Stato e annotazioni</h2>
          </div>

          <div className="mb-4">
            <label htmlFor="customer-notes" className="form-label fw-semibold">
              Note
            </label>

            <textarea
              id="customer-notes"
              name="notes"
              className={getFieldClass("notes", "form-control")}
              value={values.notes}
              rows={5}
              maxLength={5000}
              disabled={isSubmitting}
              onChange={handleChange}
            ></textarea>

            <div className="d-flex justify-content-between form-text">
              <span>
                Annotazioni visibili soltanto all’interno del gestionale.
              </span>

              <span>{values.notes.length}/5000</span>
            </div>

            {fieldErrors.notes && (
              <div className="invalid-feedback">{fieldErrors.notes[0]}</div>
            )}
          </div>

          <div className="form-check form-switch">
            <input
              id="customer-is-active"
              name="is_active"
              type="checkbox"
              className="form-check-input"
              role="switch"
              checked={values.is_active}
              disabled={isSubmitting}
              onChange={handleChange}
            />

            <label
              htmlFor="customer-is-active"
              className="form-check-label fw-semibold"
            >
              Cliente attivo
            </label>

            <div className="form-text">
              Un cliente disattivato rimane nello storico ma non dovrebbe essere
              associato a nuovi noleggi.
            </div>
          </div>

          {fieldErrors.is_active && (
            <div className="text-danger small mt-2">
              {fieldErrors.is_active[0]}
            </div>
          )}
        </div>
      </section>

      {/* Azioni finali del form. */}
      <div className="d-flex flex-wrap justify-content-end gap-3">
        <Link
          to={cancelTo}
          className="btn btn-outline-secondary"
          aria-disabled={isSubmitting}
        >
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
            <>
              <i className="bi bi-check-lg me-2" aria-hidden="true"></i>
              {submitLabel}
            </>
          )}
        </button>
      </div>
    </form>
  );
}

export default CustomerForm;
