import {
  GARAGE_MOVEMENT_TYPES,
  formatGarageDateTime,
  formatGaragePosition,
} from "../utils/garageOptions";

/*
 * Storico dei movimenti dell'autorimessa.
 *
 * Questo componente riceve i movimenti già caricati
 * dalla pagina principale e li trasforma in un elenco leggibile.
 *
 * Non effettua richieste API e non modifica alcun dato.
 */

function GarageMovementHistory({ movements, isLoading }) {
  return (
    <section className="card border-0 shadow-sm garage-history">
      <div className="card-body p-4">
        {/* Intestazione dello storico. */}
        <div className="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
          <div>
            <span className="page-eyebrow">Registro operativo</span>

            <h2 className="h4 mb-1">Ultimi movimenti</h2>

            <p className="text-secondary mb-0">
              Entrate, uscite e spostamenti registrati nell’autorimessa.
            </p>
          </div>

          <span className="badge text-bg-light">
            {movements.length} movimenti
          </span>
        </div>

        {/*
         * Mostra un piccolo caricamento interno.
         * Non blocca il resto della pagina mentre
         * viene aggiornato solamente lo storico.
         */}
        {isLoading && (
          <div className="garage-history__loading">
            <span
              className="spinner-border spinner-border-sm"
              aria-hidden="true"
            ></span>
            Aggiornamento dello storico...
          </div>
        )}

        {/*
         * Messaggio visualizzato quando il database
         * non contiene ancora alcun movimento.
         */}
        {!isLoading && movements.length === 0 && (
          <div className="garage-history__empty">
            <i className="bi bi-clock-history" aria-hidden="true"></i>

            <div>
              <h3>Nessun movimento registrato</h3>

              <p>
                Le operazioni effettuate sui veicoli compariranno
                automaticamente qui.
              </p>
            </div>
          </div>
        )}

        {/* Elenco cronologico dei movimenti disponibili. */}
        {movements.length > 0 && (
          <div className="garage-history__list">
            {movements.map((movement) => {
              /*
               * Recupera testo e icona del movimento.
               * Se il backend aggiungesse un nuovo tipo,
               * viene comunque mostrato con un valore generico.
               */
              const movementType = GARAGE_MOVEMENT_TYPES[movement.type] ?? {
                label: movement.type,
                icon: "bi-arrow-left-right",
              };

              /*
               * Preferisce i dati correnti del veicolo.
               * La targa storica rimane disponibile anche
               * se in futuro il veicolo venisse eliminato.
               */
              const vehicleName = movement.vehicle
                ? `${movement.vehicle.brand} ${movement.vehicle.model}`
                : "Veicolo non più disponibile";

              return (
                <article className="garage-history__item" key={movement.id}>
                  {/* Icona che distingue il tipo di movimento. */}
                  <span className="garage-history__icon">
                    <i
                      className={`bi ${movementType.icon}`}
                      aria-hidden="true"
                    ></i>
                  </span>

                  <div className="garage-history__content">
                    {/* Tipo di operazione e targa del mezzo. */}
                    <div className="garage-history__title">
                      <div>
                        <strong>{movementType.label}</strong>

                        <span>{vehicleName}</span>
                      </div>

                      <span className="garage-history__plate">
                        {movement.vehicle_license_plate}
                      </span>
                    </div>

                    {/*
                     * Posizione iniziale e finale.
                     *
                     * Primo parcheggio:
                     * Esterno → posizione
                     *
                     * Uscita:
                     * posizione → Esterno
                     *
                     * Spostamento:
                     * posizione → nuova posizione
                     */}
                    <div className="garage-history__route">
                      <span>{formatGaragePosition(movement.from)}</span>

                      <i className="bi bi-arrow-right" aria-hidden="true"></i>

                      <span>{formatGaragePosition(movement.to)}</span>
                    </div>

                    {/* Data, operatore e noleggio eventualmente collegato. */}
                    <div className="garage-history__metadata">
                      <span>
                        <i className="bi bi-calendar3" aria-hidden="true"></i>

                        {formatGarageDateTime(movement.occurred_at)}
                      </span>

                      {movement.performed_by?.name && (
                        <span>
                          <i className="bi bi-person" aria-hidden="true"></i>

                          {movement.performed_by.name}
                        </span>
                      )}

                      {movement.rental?.id && (
                        <span>
                          <i className="bi bi-receipt" aria-hidden="true"></i>
                          Noleggio #{movement.rental.id}
                        </span>
                      )}
                    </div>

                    {/* Nota inserita durante l'operazione. */}
                    {movement.notes && (
                      <p className="garage-history__notes">
                        <i
                          className="bi bi-chat-left-text"
                          aria-hidden="true"
                        ></i>

                        {movement.notes}
                      </p>
                    )}
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}

export default GarageMovementHistory;
