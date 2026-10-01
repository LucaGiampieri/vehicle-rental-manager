/*
 * Traduce lo stato operativo ricevuto dal backend.
 *
 * Lo stato operativo non rappresenta sempre la posizione fisica:
 * un mezzo prenotato, per esempio, può trovarsi ancora in autorimessa.
 */
function getOperationalStatusInformation(status) {
  const statuses = {
    available: {
      label: "Disponibile",
      badgeClass: "text-bg-success",
      icon: "bi-check-circle",
    },
    reserved: {
      label: "Prenotato",
      badgeClass: "text-bg-warning",
      icon: "bi-calendar-check",
    },
    rented: {
      label: "Noleggio attivo",
      badgeClass: "text-bg-danger",
      icon: "bi-key",
    },
    inactive: {
      label: "Fuori servizio",
      badgeClass: "text-bg-secondary",
      icon: "bi-slash-circle",
    },
  };

  return (
    statuses[status] || {
      label: "Stato sconosciuto",
      badgeClass: "text-bg-secondary",
      icon: "bi-question-circle",
    }
  );
}

/*
 * Pannello operativo dell'autorimessa.
 *
 * Mostra separatamente:
 * - posizione fisica del veicolo;
 * - stato operativo;
 * - dimensioni richieste;
 * - posizione selezionata.
 *
 * Le richieste vengono eseguite dalla pagina GaragePage.
 */
function GarageOperationsPanel({
  vehicles,
  parkedVehicleIds,
  selectedVehicleId,
  selectedSpace,
  notes,
  isSubmitting,
  onVehicleChange,
  onNotesChange,
  onParkOrMove,
  onUnpark,
}) {
  // Filtro utilizzato per restringere l'elenco dei veicoli per posizione.
  const [locationFilter, setLocationFilter] = useState("all");

  // Determina la posizione fisica utilizzata dal filtro.
  function getVehicleLocationGroup(vehicle) {
    if (parkedVehicleIds.has(vehicle.id)) {
      return "garage";
    }

    if (vehicle.operational_status === "rented") {
      return "customer";
    }

    return "outside";
  }

  // Applica il filtro e ordina i risultati per marca e modello.
  const filteredVehicles = vehicles
    .filter(
      (vehicle) =>
        locationFilter === "all" ||
        getVehicleLocationGroup(vehicle) === locationFilter,
    )
    .sort((firstVehicle, secondVehicle) =>
      `${firstVehicle.brand} ${firstVehicle.model}`.localeCompare(
        `${secondVehicle.brand} ${secondVehicle.model}`,
        "it",
      ),
    );

  // Recupera l'oggetto completo del veicolo selezionato.
  const selectedVehicle = vehicles.find(
    (vehicle) => String(vehicle.id) === String(selectedVehicleId),
  );

  // Verifica se il veicolo occupa almeno una cella.
  const isParked = selectedVehicle
    ? parkedVehicleIds.has(selectedVehicle.id)
    : false;

  // Verifica se il mezzo è attualmente presso un cliente.
  const hasActiveRental = selectedVehicle?.operational_status === "rented";

  // Verifica se il mezzo è stato disattivato.
  const isInactive = selectedVehicle?.operational_status === "inactive";

  /*
   * Un mezzo può entrare manualmente nell'autorimessa soltanto
   * quando non possiede un noleggio attivo e non è disattivato.
   */
  const canEnterGarage = selectedVehicle && !hasActiveRental && !isInactive;

  /*
   * Controlla soltanto la prima cella selezionata.
   *
   * Il backend effettua comunque il controllo definitivo
   * dell'intero blocco richiesto dal veicolo.
   */
  const selectedSpaceCanBeUsed =
    selectedSpace &&
    selectedSpace.is_active &&
    (!selectedSpace.is_occupied ||
      selectedSpace.vehicle_id === selectedVehicle?.id);

  // Informazioni grafiche sullo stato operativo.
  const operationalStatus = selectedVehicle
    ? getOperationalStatusInformation(selectedVehicle.operational_status)
    : null;

  /*
   * Calcola la posizione fisica conosciuta.
   *
   * La presenza nelle celle ha la priorità.
   * In alternativa, un noleggio attivo indica che il mezzo
   * si trova presso il cliente.
   */
  let physicalLocation = {
    label: "Fuori autorimessa",
    badgeClass: "text-bg-light border text-dark",
    icon: "bi-geo-alt",
  };

  if (isParked) {
    physicalLocation = {
      label: "In autorimessa",
      badgeClass: "text-bg-primary",
      icon: "bi-p-square",
    };
  } else if (hasActiveRental) {
    physicalLocation = {
      label: "Presso il cliente",
      badgeClass: "text-bg-danger",
      icon: "bi-person-check",
    };
  } else if (isInactive) {
    physicalLocation = {
      label: "Posizione non indicata",
      badgeClass: "text-bg-secondary",
      icon: "bi-question-circle",
    };
  }

  // Testo del pulsante principale.
  const primaryActionLabel = isParked
    ? "Sposta nella posizione scelta"
    : "Parcheggia nella posizione scelta";

  /*
   * Il pulsante è utilizzabile quando:
   * - esiste un veicolo;
   * - il mezzo può entrare oppure è già parcheggiato;
   * - è stata scelta una cella utilizzabile;
   * - non è già in corso un salvataggio.
   */
  const primaryActionIsDisabled =
    !selectedVehicle ||
    (!isParked && !canEnterGarage) ||
    !selectedSpaceCanBeUsed ||
    isSubmitting;

  /*
   * Restituisce una breve indicazione della posizione
   * utilizzata anche all'interno della select.
   */
  function getVehicleLocationLabel(vehicle) {
    if (parkedVehicleIds.has(vehicle.id)) {
      return "in autorimessa";
    }

    if (vehicle.operational_status === "rented") {
      return "presso il cliente";
    }

    if (vehicle.operational_status === "inactive") {
      return "fuori servizio";
    }

    return "fuori autorimessa";
  }

  // Cambia filtro e pulisce una selezione non più visibile.
  function handleLocationFilterChange(event) {
    const nextFilter = event.target.value;

    setLocationFilter(nextFilter);

    if (
      selectedVehicle &&
      nextFilter !== "all" &&
      getVehicleLocationGroup(selectedVehicle) !== nextFilter
    ) {
      onVehicleChange("");
    }
  }

  return (
    <section className="card border-0 shadow-sm garage-operations">
      <div className="card-body p-4">
        {/* Intestazione del pannello. */}
        <div className="mb-4">
          <span className="page-eyebrow">Operazione</span>

          <h2 className="h4 mb-1">Gestisci la posizione</h2>

          <p className="text-secondary mb-0">
            Seleziona un veicolo e indica sulla mappa la prima cella del blocco.
          </p>
        </div>

        {/* Filtro rapido basato sulla posizione fisica del mezzo. */}
        <div className="mb-3">
          <label
            htmlFor="garage-location-filter"
            className="form-label fw-semibold"
          >
            Mostra mezzi
          </label>

          <select
            id="garage-location-filter"
            className="form-select"
            value={locationFilter}
            disabled={isSubmitting}
            onChange={handleLocationFilterChange}
          >
            <option value="all">Tutti i mezzi ({vehicles.length})</option>
            <option value="garage">In autorimessa</option>
            <option value="outside">Fuori autorimessa</option>
            <option value="customer">Dal cliente</option>
          </select>
        </div>

        {/* Selezione del veicolo filtrato. */}
        <div className="mb-3">
          <label htmlFor="garage-vehicle" className="form-label fw-semibold">
            Veicolo
          </label>

          <select
            id="garage-vehicle"
            className="form-select"
            value={selectedVehicleId}
            disabled={isSubmitting}
            onChange={(event) => onVehicleChange(event.target.value)}
          >
            <option value="">Seleziona un veicolo</option>

            {filteredVehicles.map((vehicle) => (
              <option key={vehicle.id} value={vehicle.id}>
                {vehicle.license_plate} — {vehicle.brand} {vehicle.model} (
                {getVehicleLocationLabel(vehicle)})
              </option>
            ))}
          </select>
        </div>

        {/* Riepilogo del veicolo selezionato. */}
        {selectedVehicle && (
          <div className="garage-vehicle-summary mb-3">
            <div>
              <span className="garage-vehicle-summary__label">
                Veicolo selezionato
              </span>

              <strong>
                {selectedVehicle.brand} {selectedVehicle.model}
              </strong>

              <small>Targa {selectedVehicle.license_plate}</small>
            </div>

            <div className="garage-vehicle-summary__status">
              {/* Posizione fisica attuale. */}
              <span className={`badge ${physicalLocation.badgeClass}`}>
                <i
                  className={`bi ${physicalLocation.icon} me-1`}
                  aria-hidden="true"
                ></i>

                {physicalLocation.label}
              </span>

              {/* Stato operativo del mezzo. */}
              <span className={`badge ${operationalStatus.badgeClass}`}>
                <i
                  className={`bi ${operationalStatus.icon} me-1`}
                  aria-hidden="true"
                ></i>

                {operationalStatus.label}
              </span>

              {/* Numero di celle necessarie. */}
              <span>
                <i className="bi bi-bounding-box" aria-hidden="true"></i>
                {selectedVehicle.parking_units}{" "}
                {selectedVehicle.parking_units === 1
                  ? "cella richiesta"
                  : "celle richieste"}
              </span>
            </div>
          </div>
        )}

        {/* Avviso per un mezzo consegnato al cliente. */}
        {selectedVehicle && hasActiveRental && !isParked && (
          <div className="alert alert-warning py-2" role="alert">
            <i
              className="bi bi-exclamation-triangle me-2"
              aria-hidden="true"
            ></i>
            Questo mezzo è presso un cliente. Potrà rientrare nell’autorimessa
            quando il noleggio verrà concluso.
          </div>
        )}

        {/* Avviso per un mezzo prenotato ma non ancora noleggiato. */}
        {selectedVehicle?.operational_status === "reserved" && (
          <div className="alert alert-info py-2" role="status">
            <i className="bi bi-calendar-check me-2" aria-hidden="true"></i>
            Il mezzo è prenotato, ma può ancora essere collocato
            nell’autorimessa fino alla consegna.
          </div>
        )}

        {/* Posizione selezionata sulla mappa. */}
        <div className="garage-selection-summary mb-3">
          <span className="garage-selection-summary__label">
            Posizione scelta
          </span>

          {selectedSpace ? (
            <>
              <strong>Zona {selectedSpace.zone}</strong>

              <span>
                {selectedSpace.label ||
                  `Riga ${selectedSpace.row_number}, posto ${selectedSpace.column_number}`}
              </span>

              {!selectedSpace.is_active && (
                <small className="text-danger">
                  Questa cella è disattivata.
                </small>
              )}

              {selectedSpace.is_occupied &&
                selectedSpace.vehicle_id !== selectedVehicle?.id && (
                  <small className="text-danger">
                    Questa cella è occupata da un altro veicolo.
                  </small>
                )}
            </>
          ) : (
            <strong>Nessuna cella selezionata</strong>
          )}
        </div>

        {/* Nota facoltativa del movimento. */}
        <div className="mb-4">
          <label htmlFor="garage-notes" className="form-label fw-semibold">
            Nota facoltativa
          </label>

          <textarea
            id="garage-notes"
            className="form-control"
            rows="3"
            maxLength="5000"
            value={notes}
            disabled={isSubmitting}
            onChange={(event) => onNotesChange(event.target.value)}
            placeholder="Esempio: rientro, lavaggio programmato, controllo..."
          ></textarea>

          <div className="form-text">
            La nota verrà conservata nello storico del movimento.
          </div>
        </div>

        {/* Pulsanti operativi. */}
        <div className="d-grid gap-2">
          <button
            type="button"
            className="btn btn-primary"
            disabled={primaryActionIsDisabled}
            onClick={onParkOrMove}
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
                <i
                  className={[
                    "bi",
                    isParked ? "bi-arrows-move" : "bi-p-square",
                    "me-2",
                  ].join(" ")}
                  aria-hidden="true"
                ></i>

                {primaryActionLabel}
              </>
            )}
          </button>

          {/* Uscita disponibile solo per un mezzo parcheggiato. */}
          {isParked && (
            <button
              type="button"
              className="btn btn-outline-danger"
              disabled={isSubmitting}
              onClick={onUnpark}
            >
              <i className="bi bi-box-arrow-right me-2" aria-hidden="true"></i>
              Registra uscita dall’autorimessa
            </button>
          )}
        </div>

        {/* Suggerimenti contestuali. */}
        {!selectedVehicle && (
          <p className="garage-operation-hint mb-0 mt-3">
            <i className="bi bi-info-circle" aria-hidden="true"></i>
            Inizia scegliendo il veicolo.
          </p>
        )}

        {selectedVehicle && !selectedSpace && !hasActiveRental && (
          <p className="garage-operation-hint mb-0 mt-3">
            <i className="bi bi-cursor" aria-hidden="true"></i>
            Ora seleziona una cella sulla mappa.
          </p>
        )}
      </div>
    </section>
  );
}

export default GarageOperationsPanel;
import { useState } from "react";
