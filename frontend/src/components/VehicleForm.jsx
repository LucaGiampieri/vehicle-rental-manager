import { useState } from "react";

// Valori utilizzati quando si crea un nuovo veicolo.
const DEFAULT_VALUES = {
  license_plate: "",
  brand: "",
  model: "",
  type: "car",
  parking_units: "2",
  year: "",
  mileage: "0",
  daily_rate: "",
  is_active: true,
};

// Tipologie accettate anche dal backend Laravel.
const VEHICLE_TYPES = [
  { value: "car", label: "Auto" },
  { value: "motorcycle", label: "Moto" },
  { value: "van", label: "Furgone" },
  { value: "camper", label: "Camper" },
  { value: "truck", label: "Camion" },
  { value: "bus", label: "Autobus" },
  { value: "other", label: "Altro" },
];

// Dimensioni ammesse per l'occupazione dell'autorimessa.
const PARKING_UNITS = [1, 2, 4, 8];

// Laravel permette anche l'anno successivo a quello attuale.
const MAX_VEHICLE_YEAR = new Date().getFullYear() + 1;

function VehicleForm({
  initialValues = DEFAULT_VALUES,
  validationErrors = {},
  isSubmitting = false,
  submitLabel = "Salva veicolo",
  onSubmit,
}) {
  /*
   * Lo stato appartiene al componente perché gli stessi campi
   * verranno utilizzati sia nella creazione sia nella modifica.
   */
  const [formValues, setFormValues] = useState(() => ({
    ...DEFAULT_VALUES,
    ...initialValues,
    year: initialValues.year ?? "",
    mileage: initialValues.mileage ?? "0",
    daily_rate: initialValues.daily_rate ?? "",
    parking_units: String(
      initialValues.parking_units ?? DEFAULT_VALUES.parking_units,
    ),
    is_active: initialValues.is_active ?? true,
  }));

  // Restituisce il primo errore Laravel associato a un campo.
  function getFieldError(fieldName) {
    return validationErrors[fieldName]?.[0] ?? "";
  }

  // Aggiorna il campo modificato dall'utente.
  function handleChange(event) {
    const { name, type, value, checked } = event.target;

    let nextValue = type === "checkbox" ? checked : value;

    // Mostra subito la targa in maiuscolo.
    if (name === "license_plate") {
      nextValue = value.toUpperCase();
    }

    setFormValues((currentValues) => ({
      ...currentValues,
      [name]: nextValue,
    }));
  }

  // Prepara i tipi corretti prima di consegnare i dati alla pagina.
  function handleSubmit(event) {
    event.preventDefault();

    onSubmit({
      license_plate: formValues.license_plate,
      brand: formValues.brand,
      model: formValues.model,
      type: formValues.type,
      parking_units: Number(formValues.parking_units),
      year: formValues.year === "" ? null : Number(formValues.year),
      mileage: Number(formValues.mileage),
      daily_rate:
        formValues.daily_rate === "" ? null : Number(formValues.daily_rate),
      is_active: formValues.is_active,
    });
  }

  return (
    <form onSubmit={handleSubmit}>
      <div className="card border-0 shadow-sm">
        <div className="card-body p-4">
          <div className="row g-4">
            <div className="col-12 col-md-4">
              <label htmlFor="license_plate" className="form-label">
                Targa
              </label>

              <input
                id="license_plate"
                name="license_plate"
                type="text"
                className={`form-control ${
                  getFieldError("license_plate") ? "is-invalid" : ""
                }`}
                value={formValues.license_plate}
                maxLength={20}
                required
                disabled={isSubmitting}
                placeholder="Esempio: AB123CD"
                onChange={handleChange}
              />

              {getFieldError("license_plate") && (
                <div className="invalid-feedback">
                  {getFieldError("license_plate")}
                </div>
              )}
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="brand" className="form-label">
                Marca
              </label>

              <input
                id="brand"
                name="brand"
                type="text"
                className={`form-control ${
                  getFieldError("brand") ? "is-invalid" : ""
                }`}
                value={formValues.brand}
                maxLength={50}
                required
                disabled={isSubmitting}
                placeholder="Esempio: Fiat"
                onChange={handleChange}
              />

              {getFieldError("brand") && (
                <div className="invalid-feedback">{getFieldError("brand")}</div>
              )}
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="model" className="form-label">
                Modello
              </label>

              <input
                id="model"
                name="model"
                type="text"
                className={`form-control ${
                  getFieldError("model") ? "is-invalid" : ""
                }`}
                value={formValues.model}
                maxLength={80}
                required
                disabled={isSubmitting}
                placeholder="Esempio: Panda"
                onChange={handleChange}
              />

              {getFieldError("model") && (
                <div className="invalid-feedback">{getFieldError("model")}</div>
              )}
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="type" className="form-label">
                Tipo
              </label>

              <select
                id="type"
                name="type"
                className={`form-select ${
                  getFieldError("type") ? "is-invalid" : ""
                }`}
                value={formValues.type}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              >
                {VEHICLE_TYPES.map((vehicleType) => (
                  <option key={vehicleType.value} value={vehicleType.value}>
                    {vehicleType.label}
                  </option>
                ))}
              </select>

              {getFieldError("type") && (
                <div className="invalid-feedback">{getFieldError("type")}</div>
              )}
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="year" className="form-label">
                Anno
              </label>

              <input
                id="year"
                name="year"
                type="number"
                className={`form-control ${
                  getFieldError("year") ? "is-invalid" : ""
                }`}
                value={formValues.year}
                min="1900"
                max={MAX_VEHICLE_YEAR}
                disabled={isSubmitting}
                placeholder={`Massimo ${MAX_VEHICLE_YEAR}`}
                onChange={handleChange}
              />

              {getFieldError("year") && (
                <div className="invalid-feedback">{getFieldError("year")}</div>
              )}
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="mileage" className="form-label">
                Chilometraggio
              </label>

              <div className="input-group">
                <input
                  id="mileage"
                  name="mileage"
                  type="number"
                  className={`form-control ${
                    getFieldError("mileage") ? "is-invalid" : ""
                  }`}
                  value={formValues.mileage}
                  min="0"
                  step="1"
                  required
                  disabled={isSubmitting}
                  onChange={handleChange}
                />

                <span className="input-group-text">km</span>

                {getFieldError("mileage") && (
                  <div className="invalid-feedback">
                    {getFieldError("mileage")}
                  </div>
                )}
              </div>
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="daily_rate" className="form-label">
                Tariffa giornaliera
              </label>

              <div className="input-group">
                <span className="input-group-text">€</span>

                <input
                  id="daily_rate"
                  name="daily_rate"
                  type="number"
                  className={`form-control ${
                    getFieldError("daily_rate") ? "is-invalid" : ""
                  }`}
                  value={formValues.daily_rate}
                  min="0"
                  max="99999999.99"
                  step="0.01"
                  disabled={isSubmitting}
                  placeholder="Facoltativa"
                  onChange={handleChange}
                />

                {getFieldError("daily_rate") && (
                  <div className="invalid-feedback">
                    {getFieldError("daily_rate")}
                  </div>
                )}
              </div>
            </div>

            <div className="col-12 col-md-4">
              <label htmlFor="parking_units" className="form-label">
                Celle necessarie al parcheggio
              </label>

              <select
                id="parking_units"
                name="parking_units"
                className={`form-select ${
                  getFieldError("parking_units") ? "is-invalid" : ""
                }`}
                value={formValues.parking_units}
                required
                disabled={isSubmitting}
                onChange={handleChange}
              >
                {PARKING_UNITS.map((units) => (
                  <option key={units} value={units}>
                    {units === 1 ? "1 cella" : `${units} celle`}
                  </option>
                ))}
              </select>

              {getFieldError("parking_units") && (
                <div className="invalid-feedback">
                  {getFieldError("parking_units")}
                </div>
              )}
            </div>

            <div className="col-12 col-md-4 d-flex align-items-end">
              <div className="form-check form-switch mb-2">
                <input
                  id="is_active"
                  name="is_active"
                  type="checkbox"
                  className="form-check-input"
                  checked={formValues.is_active}
                  disabled={isSubmitting}
                  onChange={handleChange}
                />

                <label htmlFor="is_active" className="form-check-label">
                  Veicolo attivo
                </label>
              </div>
            </div>
          </div>
        </div>

        <div className="card-footer bg-white border-0 px-4 pb-4">
          <div className="d-flex justify-content-end">
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
        </div>
      </div>
    </form>
  );
}

export default VehicleForm;
