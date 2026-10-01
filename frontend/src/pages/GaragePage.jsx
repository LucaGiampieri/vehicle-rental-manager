import { useCallback, useEffect, useMemo, useState } from "react";
import GarageMap from "../components/GarageMap";
import GarageMovementHistory from "../components/GarageMovementHistory";
import GarageOperationsPanel from "./GarageOperationsPanel";
import Loader from "../components/Loader";
import api from "../services/api";

/*
 * Pagina principale dell'autorimessa.
 *
 * Coordina:
 * - caricamento delle celle;
 * - elenco dei veicoli;
 * - ultimi movimenti;
 * - selezione di veicolo e cella;
 * - parcheggio;
 * - spostamento;
 * - uscita dall'autorimessa.
 *
 * La logica più delicata resta comunque nel backend Laravel.
 */

function GaragePage() {
  // Dati ricevuti dalle API.
  const [spaces, setSpaces] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [movements, setMovements] = useState([]);

  // Selezioni utilizzate dal pannello operativo.
  const [selectedVehicleId, setSelectedVehicleId] = useState("");
  const [selectedSpace, setSelectedSpace] = useState(null);
  const [notes, setNotes] = useState("");

  // Stati dell'interfaccia.
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshingHistory, setIsRefreshingHistory] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Messaggi mostrati dopo caricamenti e operazioni.
  const [errorMessage, setErrorMessage] = useState("");
  const [successMessage, setSuccessMessage] = useState("");

  /*
   * Estrae un messaggio leggibile dagli errori Axios.
   *
   * Gli errori 409 dell'autorimessa possiedono normalmente
   * un messaggio preciso restituito da GarageService.
   */
  function getErrorMessage(error) {
    const validationErrors = error.response?.data?.errors;

    if (validationErrors) {
      const firstMessages = Object.values(validationErrors)[0];

      if (Array.isArray(firstMessages)) {
        return firstMessages[0];
      }
    }

    return (
      error.response?.data?.message ||
      "Operazione non riuscita. Controlla i dati e riprova."
    );
  }

  /*
   * Carica contemporaneamente:
   * - mappa completa delle celle;
   * - veicoli attivi;
   * - ultimi trenta movimenti.
   *
   * useCallback permette di riutilizzare la stessa funzione
   * dopo ogni operazione senza creare dipendenze instabili.
   */
  const loadGarageData = useCallback(
    async ({
      signal,
      showMainLoader = false,
      showHistoryLoader = false,
    } = {}) => {
      if (showMainLoader) {
        setIsLoading(true);
      }

      if (showHistoryLoader) {
        setIsRefreshingHistory(true);
      }

      setErrorMessage("");

      try {
        const [spacesResponse, vehiclesResponse, movementsResponse] =
          await Promise.all([
            api.get("/api/parking-spaces", {
              signal,
            }),

            api.get("/api/vehicles", {
              params: {
                is_active: true,
                per_page: 100,
              },
              signal,
            }),

            api.get("/api/garage/movements", {
              signal,
            }),
          ]);

        setSpaces(spacesResponse.data.data);
        setVehicles(vehiclesResponse.data.data);
        setMovements(movementsResponse.data.data);
      } catch (error) {
        /*
         * Non mostra errori quando la richiesta viene annullata
         * perché l'utente ha lasciato la pagina.
         */
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage(
            "Impossibile caricare l’autorimessa. Riprova più tardi.",
          );
        }
      } finally {
        if (!signal?.aborted) {
          setIsLoading(false);
          setIsRefreshingHistory(false);
        }
      }
    },
    [],
  );

  /*
   * Primo caricamento della pagina.
   *
   * Il caricamento viene programmato nel ciclo successivo
   * dell'event loop. Questo evita di modificare lo stato
   * direttamente durante l'esecuzione dell'effect.
   *
   * AbortController interrompe le richieste se l'utente
   * abbandona la pagina prima della risposta del backend.
   */
  useEffect(() => {
    const controller = new AbortController();

    const loadingTimer = window.setTimeout(() => {
      loadGarageData({
        signal: controller.signal,
        showMainLoader: true,
      });
    }, 0);

    return () => {
      window.clearTimeout(loadingTimer);
      controller.abort();
    };
  }, [loadGarageData]);

  /*
   * Crea l'insieme degli identificativi dei veicoli
   * che occupano almeno una cella.
   *
   * Set elimina automaticamente i duplicati:
   * un camper da 8 celle viene contato una sola volta.
   */
  const parkedVehicleIds = useMemo(
    () =>
      new Set(
        spaces
          .filter((space) => space.vehicle_id !== null)
          .map((space) => space.vehicle_id),
      ),
    [spaces],
  );

  /*
   * Calcola i numeri mostrati nelle schede riepilogative.
   */
  const summary = useMemo(() => {
    const activeSpaces = spaces.filter((space) => space.is_active);

    const occupiedSpaces = activeSpaces.filter((space) => space.is_occupied);

    const availableSpaces = activeSpaces.filter((space) => !space.is_occupied);

    const disabledSpaces = spaces.filter((space) => !space.is_active);

    return {
      total: spaces.length,
      available: availableSpaces.length,
      occupied: occupiedSpaces.length,
      disabled: disabledSpaces.length,
      parkedVehicles: parkedVehicleIds.size,
    };
  }, [spaces, parkedVehicleIds]);

  /*
   * Gestisce il clic su una cella della mappa.
   *
   * Se la cella è occupata, seleziona automaticamente
   * anche il veicolo presente. In questo modo diventa
   * immediato preparare uno spostamento o un'uscita.
   */
  function handleSpaceSelection(space) {
    setSelectedSpace(space);
    setErrorMessage("");
    setSuccessMessage("");

    if (space.vehicle_id !== null) {
      setSelectedVehicleId(String(space.vehicle_id));
    }
  }

  /*
   * Cambiando veicolo elimina la precedente cella selezionata.
   *
   * Evita che l'utente esegua accidentalmente
   * un'operazione utilizzando una vecchia selezione.
   */
  function handleVehicleChange(vehicleId) {
    setSelectedVehicleId(vehicleId);
    setSelectedSpace(null);
    setErrorMessage("");
    setSuccessMessage("");
  }

  /*
   * Esegue una richiesta di modifica e poi ricarica
   * mappa, veicoli e storico.
   */
  async function performGarageAction(action, successText) {
    setIsSubmitting(true);
    setErrorMessage("");
    setSuccessMessage("");

    try {
      await action();

      setSuccessMessage(successText);
      setSelectedSpace(null);
      setNotes("");

      await loadGarageData({
        showHistoryLoader: true,
      });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setIsSubmitting(false);
    }
  }

  /*
   * Decide automaticamente se effettuare un parcheggio
   * oppure uno spostamento.
   */
  function handleParkOrMove() {
    if (!selectedVehicleId || !selectedSpace) {
      setErrorMessage("Seleziona un veicolo e una posizione.");

      return;
    }

    const vehicleId = Number(selectedVehicleId);

    const payload = {
      parking_space_id: selectedSpace.id,
      notes: notes.trim() || null,
    };

    if (parkedVehicleIds.has(vehicleId)) {
      performGarageAction(
        () => api.patch(`/api/garage/vehicles/${vehicleId}/move`, payload),
        "Veicolo spostato correttamente.",
      );

      return;
    }

    performGarageAction(
      () =>
        api.post("/api/garage/park", {
          vehicle_id: vehicleId,
          ...payload,
        }),
      "Veicolo parcheggiato correttamente.",
    );
  }

  /*
   * Sposta un veicolo tramite trascinamento sulla piantina.
   *
   * GarageMap comunica:
   * - l'identificativo del veicolo trascinato;
   * - la cella iniziale del nuovo blocco.
   *
   * Il backend controlla nuovamente dimensioni, celle occupate,
   * posti disattivati e attraversamento della corsia.
   */
  function handleVehicleDrop(vehicleId, targetSpace) {
    // Impedisce richieste multiple durante un salvataggio.
    if (isSubmitting || !vehicleId || !targetSpace) {
      return;
    }

    // Aggiorna anche le selezioni visibili nel pannello operativo.
    setSelectedVehicleId(String(vehicleId));
    setSelectedSpace(targetSpace);

    const payload = {
      parking_space_id: targetSpace.id,

      // Lo spostamento tramite trascinamento non richiede note.
      notes: null,
    };

    return performGarageAction(
      () => api.patch(`/api/garage/vehicles/${vehicleId}/move`, payload),
      "Veicolo spostato correttamente.",
    );
  }

  /*
   * Registra l'uscita del veicolo.
   *
   * La conferma evita di liberare accidentalmente
   * tutte le celle occupate dal mezzo.
   */
  function handleUnpark() {
    if (!selectedVehicleId) {
      return;
    }

    const selectedVehicle = vehicles.find(
      (vehicle) => String(vehicle.id) === String(selectedVehicleId),
    );

    const confirmed = window.confirm(
      `Vuoi registrare l’uscita di ${
        selectedVehicle
          ? `${selectedVehicle.brand} ${selectedVehicle.model} (${selectedVehicle.license_plate})`
          : "questo veicolo"
      } dall’autorimessa?`,
    );

    if (!confirmed) {
      return;
    }

    performGarageAction(
      () =>
        api.patch(`/api/garage/vehicles/${selectedVehicleId}/unpark`, {
          notes: notes.trim() || null,
        }),
      "Uscita dall’autorimessa registrata correttamente.",
    );
  }

  /*
   * Durante il primo caricamento mostra il Loader
   * condiviso con il resto dell'applicazione.
   */
  if (isLoading) {
    return <Loader message="Caricamento autorimessa..." />;
  }

  return (
    <>
      {/* Intestazione della pagina. */}
      <header className="page-header">
        <div>
          <span className="page-eyebrow">Gestione spazi</span>

          <h1>Autorimessa</h1>

          <p>
            Controlla la disposizione dei mezzi, registra spostamenti, entrate e
            uscite.
          </p>
        </div>
      </header>

      {/* Riepilogo immediato della situazione attuale. */}
      <section className="garage-summary" aria-label="Riepilogo autorimessa">
        <article className="garage-summary-card garage-summary-card--available">
          <span className="garage-summary-card__icon">
            <i className="bi bi-check-circle" aria-hidden="true"></i>
          </span>

          <div>
            <strong>{summary.available}</strong>
            <span>Celle libere</span>
          </div>
        </article>

        <article className="garage-summary-card garage-summary-card--occupied">
          <span className="garage-summary-card__icon">
            <i className="bi bi-car-front-fill" aria-hidden="true"></i>
          </span>

          <div>
            <strong>{summary.occupied}</strong>
            <span>Celle occupate</span>
          </div>
        </article>

        <article className="garage-summary-card garage-summary-card--vehicles">
          <span className="garage-summary-card__icon">
            <i className="bi bi-p-square" aria-hidden="true"></i>
          </span>

          <div>
            <strong>{summary.parkedVehicles}</strong>
            <span>Veicoli presenti</span>
          </div>
        </article>

        <article className="garage-summary-card garage-summary-card--disabled">
          <span className="garage-summary-card__icon">
            <i className="bi bi-slash-circle" aria-hidden="true"></i>
          </span>

          <div>
            <strong>{summary.disabled}</strong>
            <span>Celle disattivate</span>
          </div>
        </article>
      </section>

      {/* Messaggi generali delle operazioni. */}
      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          <i className="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>

          {errorMessage}
        </div>
      )}

      {successMessage && (
        <div className="alert alert-success" role="status">
          <i className="bi bi-check-circle me-2" aria-hidden="true"></i>

          {successMessage}
        </div>
      )}

      {/*
       * Area principale:
       * la mappa occupa la parte più ampia;
       * il pannello operativo rimane a destra.
       */}
      <div className="garage-workspace">
        <section className="card border-0 shadow-sm garage-map-card">
          <div className="card-body p-4">
            <div className="garage-map-card__heading">
              <div>
                <span className="page-eyebrow">Disposizione attuale</span>

                <h2 className="h4 mb-1">Mappa dei posti</h2>

                <p className="text-secondary mb-0">
                  Seleziona una cella oppure trascina un mezzo in una nuova
                  posizione.
                </p>
              </div>

              {/* Legenda dei colori usati nella mappa. */}
              <div className="garage-legend">
                <span>
                  <i className="garage-legend__dot garage-legend__dot--available"></i>
                  Libera
                </span>

                <span>
                  <i className="garage-legend__dot garage-legend__dot--occupied"></i>
                  Occupata
                </span>

                <span>
                  <i className="garage-legend__dot garage-legend__dot--disabled"></i>
                  Disattivata
                </span>

                <span>
                  <i className="garage-legend__dot garage-legend__dot--selected"></i>
                  Selezionata
                </span>
              </div>
            </div>

            <GarageMap
              spaces={spaces}
              selectedSpaceId={selectedSpace?.id}
              isSubmitting={isSubmitting}
              onSelectSpace={handleSpaceSelection}
              onMoveVehicle={handleVehicleDrop}
            />
          </div>
        </section>

        <GarageOperationsPanel
          vehicles={vehicles}
          parkedVehicleIds={parkedVehicleIds}
          selectedVehicleId={selectedVehicleId}
          selectedSpace={selectedSpace}
          notes={notes}
          isSubmitting={isSubmitting}
          onVehicleChange={handleVehicleChange}
          onNotesChange={setNotes}
          onParkOrMove={handleParkOrMove}
          onUnpark={handleUnpark}
        />
      </div>

      {/* Storico generale degli ultimi movimenti. */}
      <GarageMovementHistory
        movements={movements}
        isLoading={isRefreshingHistory}
      />
    </>
  );
}

export default GaragePage;
