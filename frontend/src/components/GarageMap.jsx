import { useRef, useState } from "react";

/*
 * Configurazione delle aree fisiche dell'autorimessa.
 *
 * rows e columns indicano quante celle tecniche compongono
 * un singolo stallo reale. Il frontend mostra quindi 38 stalli,
 * non le 88 celle utilizzate internamente dal database.
 */
const GARAGE_ZONES = [
  {
    name: "compact_top",
    label: "Auto e moto · fila superiore",
    shortLabel: "Auto e moto",
    prefix: "A",
    capacity: 10,
    rows: 1,
    columns: 1,
  },
  {
    name: "medium",
    label: "Furgoni",
    shortLabel: "Furgoni",
    prefix: "F",
    capacity: 8,
    rows: 1,
    columns: 2,
  },
  {
    name: "large",
    label: "Camper e autocarri",
    shortLabel: "Mezzi grandi",
    prefix: "G",
    capacity: 7,
    rows: 2,
    columns: 2,
  },
  {
    name: "extra_large",
    label: "Autobus e mezzi extra large",
    shortLabel: "Mezzi XL",
    prefix: "XL",
    capacity: 3,
    rows: 2,
    columns: 4,
  },
  {
    name: "compact_bottom",
    label: "Auto e moto · fila inferiore",
    shortLabel: "Auto e moto",
    prefix: "B",
    capacity: 10,
    rows: 1,
    columns: 1,
  },
];

// Restituisce la forma del blocco richiesto dal veicolo.
function getBlockDimensions(parkingUnits) {
  switch (Number(parkingUnits)) {
    case 1:
      return { rows: 1, columns: 1 };
    case 2:
      return { rows: 1, columns: 2 };
    case 4:
      return { rows: 2, columns: 2 };
    case 8:
      return { rows: 2, columns: 4 };
    default:
      return null;
  }
}

// Verifica che la cella sia l'inizio di uno stallo reale della zona.
function isBayStartingSpace(zoneConfiguration, space) {
  return (
    (space.row_number - 1) % zoneConfiguration.rows === 0 &&
    (space.column_number - 1) % zoneConfiguration.columns === 0
  );
}

// Verifica che il veicolo utilizzi una zona compatibile.
function isCompatibleParkingStart(vehicle, space) {
  const parkingUnits = Number(vehicle.parking_units);

  if (parkingUnits === 1) {
    return ["compact_top", "compact_bottom"].includes(space.zone);
  }

  if (parkingUnits === 2) {
    return space.zone === "medium" && space.column_number % 2 === 1;
  }

  if (parkingUnits === 4) {
    return (
      space.zone === "large" &&
      space.row_number === 1 &&
      space.column_number % 2 === 1
    );
  }

  if (parkingUnits === 8) {
    return (
      space.zone === "extra_large" &&
      space.row_number === 1 &&
      [1, 5, 9].includes(space.column_number)
    );
  }

  return false;
}

/*
 * Pianta unica dell'autorimessa.
 *
 * Mostra stalli reali proporzionati, permette la selezione
 * e gestisce il trascinamento con mouse oppure touch.
 */
function GarageMap({
  spaces,
  selectedSpaceId,
  isSubmitting,
  onSelectSpace,
  onMoveVehicle,
}) {
  const [draggedVehicleId, setDraggedVehicleId] = useState(null);
  const [validTargetIds, setValidTargetIds] = useState(new Set());
  const [hoveredTargetId, setHoveredTargetId] = useState(null);

  const dragState = useRef({
    startX: 0,
    startY: 0,
    hasMoved: false,
    startingSpace: null,
  });

  // Accesso rapido alle celle tramite zona, riga e colonna.
  const spacesByPosition = new Map(
    spaces.map((space) => [
      `${space.zone}:${space.row_number}-${space.column_number}`,
      space,
    ]),
  );

  // Raggruppa le celle appartenenti alla stessa zona.
  const spacesByZone = spaces.reduce((zones, space) => {
    if (!zones[space.zone]) {
      zones[space.zone] = [];
    }

    zones[space.zone].push(space);

    return zones;
  }, {});

  // Raggruppa le celle occupate dallo stesso veicolo.
  const vehicleGroups = spaces.reduce((groups, space) => {
    if (space.vehicle_id === null || !space.vehicle) {
      return groups;
    }

    const vehicleId = Number(space.vehicle_id);

    if (!groups[vehicleId]) {
      groups[vehicleId] = {
        vehicle: space.vehicle,
        spaces: [],
      };
    }

    groups[vehicleId].spaces.push(space);

    return groups;
  }, {});

  const parkedVehiclesCount = Object.keys(vehicleGroups).length;
  const totalCapacity = GARAGE_ZONES.reduce(
    (total, zone) => total + zone.capacity,
    0,
  );

  // Recupera tutte le celle tecniche comprese in un blocco.
  function getSpacesForBlock(startingSpace, dimensions) {
    const blockSpaces = [];

    for (let rowOffset = 0; rowOffset < dimensions.rows; rowOffset += 1) {
      for (
        let columnOffset = 0;
        columnOffset < dimensions.columns;
        columnOffset += 1
      ) {
        const space = spacesByPosition.get(
          `${startingSpace.zone}:${
            startingSpace.row_number + rowOffset
          }-${startingSpace.column_number + columnOffset}`,
        );

        if (!space) {
          return [];
        }

        blockSpaces.push(space);
      }
    }

    return blockSpaces;
  }

  // Recupera lo stallo presente sotto il puntatore.
  function findTargetAtPoint(clientX, clientY) {
    const element = document.elementFromPoint(clientX, clientY);
    const targetElement = element?.closest("[data-garage-space-id]");

    if (!targetElement) {
      return null;
    }

    const targetId = Number(targetElement.dataset.garageSpaceId);

    return spaces.find((space) => Number(space.id) === targetId) || null;
  }

  // Ripristina lo stato del trascinamento.
  function resetDragging() {
    setDraggedVehicleId(null);
    setValidTargetIds(new Set());
    setHoveredTargetId(null);

    dragState.current = {
      startX: 0,
      startY: 0,
      hasMoved: false,
      startingSpace: null,
    };
  }

  // Controlla se uno stallo può accogliere il veicolo trascinato.
  function isValidTarget(vehicle, vehicleSpaces, startingSpace) {
    const dimensions = getBlockDimensions(vehicle.parking_units);

    if (
      !dimensions ||
      !startingSpace.is_active ||
      !isCompatibleParkingStart(vehicle, startingSpace)
    ) {
      return false;
    }

    const requiredSpaces = getSpacesForBlock(startingSpace, dimensions);

    if (requiredSpaces.length !== dimensions.rows * dimensions.columns) {
      return false;
    }

    const blockIsAvailable = requiredSpaces.every(
      (space) =>
        space.is_active &&
        (space.vehicle_id === null ||
          Number(space.vehicle_id) === Number(vehicle.id)),
    );

    if (!blockIsAvailable) {
      return false;
    }

    const currentIds = vehicleSpaces
      .map((space) => Number(space.id))
      .sort((firstId, secondId) => firstId - secondId);

    const targetIds = requiredSpaces
      .map((space) => Number(space.id))
      .sort((firstId, secondId) => firstId - secondId);

    return (
      currentIds.length !== targetIds.length ||
      currentIds.some(
        (currentId, index) => currentId !== targetIds[index],
      )
    );
  }

  // Avvia il trascinamento del veicolo.
  function handleVehiclePointerDown(
    event,
    vehicle,
    vehicleSpaces,
    startingSpace,
  ) {
    if (isSubmitting) {
      return;
    }

    if (event.pointerType === "mouse" && event.button !== 0) {
      return;
    }

    event.preventDefault();
    event.currentTarget.setPointerCapture(event.pointerId);

    const targetIds = new Set(
      spaces
        .filter((space) => isValidTarget(vehicle, vehicleSpaces, space))
        .map((space) => Number(space.id)),
    );

    dragState.current = {
      startX: event.clientX,
      startY: event.clientY,
      hasMoved: false,
      startingSpace,
    };

    setDraggedVehicleId(Number(vehicle.id));
    setValidTargetIds(targetIds);
    setHoveredTargetId(null);
  }

  // Aggiorna lo stallo raggiunto durante il trascinamento.
  function handleVehiclePointerMove(event) {
    if (draggedVehicleId === null) {
      return;
    }

    event.preventDefault();

    const horizontalDistance = Math.abs(
      event.clientX - dragState.current.startX,
    );
    const verticalDistance = Math.abs(
      event.clientY - dragState.current.startY,
    );

    if (horizontalDistance > 5 || verticalDistance > 5) {
      dragState.current.hasMoved = true;
    }

    if (!dragState.current.hasMoved) {
      return;
    }

    const targetSpace = findTargetAtPoint(event.clientX, event.clientY);

    setHoveredTargetId(targetSpace?.id ?? null);
  }

  // Conclude il trascinamento e richiede il salvataggio.
  function handleVehiclePointerUp(event, vehicle) {
    if (draggedVehicleId === null) {
      return;
    }

    event.preventDefault();

    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }

    const targetSpace = findTargetAtPoint(event.clientX, event.clientY);
    const hasMoved = dragState.current.hasMoved;
    const startingSpace = dragState.current.startingSpace;

    if (
      hasMoved &&
      targetSpace &&
      validTargetIds.has(Number(targetSpace.id))
    ) {
      resetDragging();
      onMoveVehicle(vehicle.id, targetSpace);

      return;
    }

    resetDragging();

    if (!hasMoved && startingSpace) {
      onSelectSpace(startingSpace);
    }
  }

  function handleVehiclePointerCancel() {
    resetDragging();
  }

  // Disegna gli stalli reali di una zona e i veicoli presenti.
  function renderZone(zoneConfiguration) {
    const zoneSpaces = spacesByZone[zoneConfiguration.name] || [];

    if (zoneSpaces.length === 0) {
      return null;
    }

    const maximumColumn = Math.max(
      ...zoneSpaces.map((space) => space.column_number),
    );
    const maximumRow = Math.max(
      ...zoneSpaces.map((space) => space.row_number),
    );

    const bayStartingSpaces = zoneSpaces
      .filter((space) => isBayStartingSpace(zoneConfiguration, space))
      .sort(
        (firstSpace, secondSpace) =>
          firstSpace.row_number - secondSpace.row_number ||
          firstSpace.column_number - secondSpace.column_number,
      );

    const zoneVehicleGroups = Object.values(vehicleGroups).filter(
      ({ spaces: occupiedSpaces }) =>
        occupiedSpaces[0]?.zone === zoneConfiguration.name,
    );

    return (
      <div
        key={zoneConfiguration.name}
        className={[
          "garage-plan__zone",
          `garage-plan__zone--${zoneConfiguration.name}`,
        ].join(" ")}
      >
        <div className="garage-plan__zone-heading">
          <strong>{zoneConfiguration.label}</strong>
          <span>{zoneConfiguration.capacity} stalli</span>
        </div>

        <div
          className="garage-plan__zone-grid"
          style={{
            gridTemplateColumns: `repeat(${maximumColumn}, minmax(0, 1fr))`,
            gridTemplateRows: `repeat(${maximumRow}, minmax(0, 1fr))`,
          }}
        >
          {bayStartingSpaces.map((startingSpace, index) => {
            const baySpaces = getSpacesForBlock(startingSpace, {
              rows: zoneConfiguration.rows,
              columns: zoneConfiguration.columns,
            });

            const isOccupied = baySpaces.some(
              (space) => space.vehicle_id !== null,
            );
            const isActive = baySpaces.every((space) => space.is_active);
            const isSelected = baySpaces.some(
              (space) => Number(space.id) === Number(selectedSpaceId),
            );
            const isDragging = draggedVehicleId !== null;
            const isValidDropTarget =
              isDragging && validTargetIds.has(Number(startingSpace.id));
            const isHovered =
              Number(hoveredTargetId) === Number(startingSpace.id);
            const bayLabel = `${zoneConfiguration.prefix}${String(
              index + 1,
            ).padStart(2, "0")}`;

            return (
              <button
                key={startingSpace.id}
                type="button"
                data-garage-space-id={startingSpace.id}
                className={[
                  "garage-parking-bay",
                  isOccupied ? "garage-parking-bay--occupied" : "",
                  !isActive ? "garage-parking-bay--disabled" : "",
                  isSelected ? "garage-parking-bay--selected" : "",
                  isDragging && !isValidDropTarget
                    ? "garage-parking-bay--invalid-target"
                    : "",
                  isValidDropTarget
                    ? "garage-parking-bay--valid-target"
                    : "",
                  isHovered && isValidDropTarget
                    ? "garage-parking-bay--drop-ready"
                    : "",
                ]
                  .filter(Boolean)
                  .join(" ")}
                style={{
                  gridRow: `${startingSpace.row_number} / span ${zoneConfiguration.rows}`,
                  gridColumn: `${startingSpace.column_number} / span ${zoneConfiguration.columns}`,
                }}
                onClick={() => {
                  if (!isDragging) {
                    onSelectSpace(startingSpace);
                  }
                }}
                aria-label={`${bayLabel}, ${zoneConfiguration.shortLabel}`}
                aria-pressed={isSelected}
                title={`${bayLabel} · ${zoneConfiguration.label} · ${
                  isOccupied ? "occupato" : "libero"
                }`}
              >
                <span className="garage-parking-bay__label">
                  {bayLabel}
                </span>

                <span className="garage-parking-bay__state">
                  {isValidDropTarget
                    ? "Sposta qui"
                    : isOccupied
                      ? "Occupato"
                      : "Libero"}
                </span>
              </button>
            );
          })}

          {zoneVehicleGroups.map(({ vehicle, spaces: vehicleSpaces }) => {
            const minimumRow = Math.min(
              ...vehicleSpaces.map((space) => space.row_number),
            );
            const maximumVehicleRow = Math.max(
              ...vehicleSpaces.map((space) => space.row_number),
            );
            const minimumColumn = Math.min(
              ...vehicleSpaces.map((space) => space.column_number),
            );
            const maximumVehicleColumn = Math.max(
              ...vehicleSpaces.map((space) => space.column_number),
            );
            const startingSpace = [...vehicleSpaces].sort(
              (firstSpace, secondSpace) =>
                firstSpace.row_number - secondSpace.row_number ||
                firstSpace.column_number - secondSpace.column_number,
            )[0];
            const isSelected = vehicleSpaces.some(
              (space) => Number(space.id) === Number(selectedSpaceId),
            );
            const isBeingDragged =
              Number(draggedVehicleId) === Number(vehicle.id);

            return (
              <button
                key={vehicle.id}
                type="button"
                className={[
                  "garage-blueprint__vehicle",
                  `garage-blueprint__vehicle--units-${vehicle.parking_units}`,
                  isSelected ? "garage-blueprint__vehicle--selected" : "",
                  isBeingDragged
                    ? "garage-blueprint__vehicle--dragging"
                    : "",
                  isSubmitting
                    ? "garage-blueprint__vehicle--disabled"
                    : "",
                ]
                  .filter(Boolean)
                  .join(" ")}
                style={{
                  gridRow: `${minimumRow} / ${maximumVehicleRow + 1}`,
                  gridColumn: `${minimumColumn} / ${
                    maximumVehicleColumn + 1
                  }`,
                }}
                onPointerDown={(event) =>
                  handleVehiclePointerDown(
                    event,
                    vehicle,
                    vehicleSpaces,
                    startingSpace,
                  )
                }
                onPointerMove={handleVehiclePointerMove}
                onPointerUp={(event) =>
                  handleVehiclePointerUp(event, vehicle)
                }
                onPointerCancel={handleVehiclePointerCancel}
                onClick={(event) => {
                  if (event.detail === 0) {
                    onSelectSpace(startingSpace);
                  }
                }}
                disabled={isSubmitting}
                aria-pressed={isSelected}
                aria-label={`${vehicle.brand} ${vehicle.model}, targa ${vehicle.license_plate}`}
                title={`${vehicle.brand} ${vehicle.model} · ${vehicle.license_plate}`}
              >
                <span className="garage-top-car" aria-hidden="true">
                  <span className="garage-top-car__window garage-top-car__window--front"></span>
                  <span className="garage-top-car__roof"></span>
                  <span className="garage-top-car__window garage-top-car__window--rear"></span>
                  <span className="garage-top-car__wheel garage-top-car__wheel--front-left"></span>
                  <span className="garage-top-car__wheel garage-top-car__wheel--front-right"></span>
                  <span className="garage-top-car__wheel garage-top-car__wheel--rear-left"></span>
                  <span className="garage-top-car__wheel garage-top-car__wheel--rear-right"></span>
                </span>

                {/* Nella piantina mostriamo soltanto marca e modello. */}
                <span className="garage-blueprint__vehicle-label">
                  <span className="garage-blueprint__vehicle-name">
                    {vehicle.brand} {vehicle.model}
                  </span>
                </span>
              </button>
            );
          })}
        </div>
      </div>
    );
  }

  if (spaces.length === 0) {
    return (
      <div className="garage-empty-state">
        <i className="bi bi-grid-3x3-gap" aria-hidden="true"></i>
        <h3>Nessun posto configurato</h3>
        <p>Configura i posti dell'autorimessa per visualizzare la pianta.</p>
      </div>
    );
  }

  return (
    <section className="garage-blueprint garage-plan">
      <div className="garage-zone__heading">
        <div>
          <span className="page-eyebrow">Pianta dell'autorimessa</span>
          <h3>Parcheggio principale</h3>
        </div>

        <div className="garage-zone__numbers">
          <span className="garage-zone__number garage-zone__number--available">
            {totalCapacity - parkedVehiclesCount} liberi
          </span>
          <span className="garage-zone__number garage-zone__number--occupied">
            {parkedVehiclesCount} occupati
          </span>
          <span className="garage-zone__number">
            {totalCapacity} stalli reali
          </span>
        </div>
      </div>

      <div className="garage-drag-instruction">
        <i className="bi bi-hand-index-thumb" aria-hidden="true"></i>
        <span>
          Trascina un mezzo: si illuminano soltanto gli stalli compatibili.
        </span>
      </div>

      <div
        className={[
          "garage-plan__building",
          draggedVehicleId !== null
            ? "garage-plan__building--dragging"
            : "",
        ]
          .filter(Boolean)
          .join(" ")}
      >
        <div className="garage-plan__wall">Autorimessa</div>

        <div className="garage-plan__content">
          {renderZone(GARAGE_ZONES[0])}

          <div className="garage-plan__lane" aria-hidden="true">
            <i className="bi bi-arrow-left"></i>
            <span>Corsia di manovra</span>
            <i className="bi bi-arrow-right"></i>
          </div>

          {renderZone(GARAGE_ZONES[1])}

          <div className="garage-plan__lane" aria-hidden="true">
            <i className="bi bi-arrow-left"></i>
            <span>Corsia principale</span>
            <i className="bi bi-arrow-right"></i>
          </div>

          {renderZone(GARAGE_ZONES[2])}

          <div className="garage-plan__lane" aria-hidden="true">
            <i className="bi bi-arrow-left"></i>
            <span>Corsia mezzi pesanti</span>
            <i className="bi bi-arrow-right"></i>
          </div>

          {renderZone(GARAGE_ZONES[3])}

          <div className="garage-plan__lane" aria-hidden="true">
            <i className="bi bi-arrow-left"></i>
            <span>Corsia di uscita</span>
            <i className="bi bi-arrow-right"></i>
          </div>

          {renderZone(GARAGE_ZONES[4])}
        </div>

        <div className="garage-plan__access">
          <span>
            <i className="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            Entrata
          </span>
          <span className="garage-plan__access-road"></span>
          <span>
            Uscita
            <i className="bi bi-box-arrow-right" aria-hidden="true"></i>
          </span>
        </div>
      </div>

      <p className="garage-blueprint__help">
        Seleziona uno stallo per leggerne i dettagli oppure trascina un mezzo
        con mouse o touch. Ogni spostamento viene salvato automaticamente.
      </p>
    </section>
  );
}

export default GarageMap;
