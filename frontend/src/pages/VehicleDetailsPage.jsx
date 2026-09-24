import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import Loader from "../components/Loader";
import api from "../services/api";

// Traduce i valori tecnici restituiti dal backend.
const VEHICLE_TYPE_LABELS = {
  car: "Auto",
  motorcycle: "Moto",
  van: "Furgone",
  camper: "Camper",
  truck: "Camion",
  bus: "Autobus",
  other: "Altro",
};

function VehicleDetailsPage() {
  // Legge il parametro dinamico presente nell'indirizzo.
  const { vehicleId } = useParams();

  // Conserva il veicolo restituito da Laravel.
  const [vehicle, setVehicle] = useState(null);

  // Gestisce il caricamento iniziale.
  const [isLoading, setIsLoading] = useState(true);

  // Conserva un eventuale errore della richiesta.
  const [errorMessage, setErrorMessage] = useState("");

  useEffect(() => {
    // Permette di annullare la richiesta lasciando la pagina.
    const controller = new AbortController();

    async function loadVehicle() {
      setIsLoading(true);
      setErrorMessage("");
      setVehicle(null);

      try {
        /*
         * vehicleId arriva dall'indirizzo.
         * Con /vehicles/4 chiamiamo /api/vehicles/4.
         */
        const response = await api.get(`/api/vehicles/${vehicleId}`, {
          signal: controller.signal,
        });

        // Laravel inserisce il veicolo nella proprietà data.
        setVehicle(response.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          if (error.response?.status === 404) {
            setErrorMessage("Il veicolo richiesto non esiste.");
          } else {
            setErrorMessage(
              "Impossibile caricare il veicolo. Riprova più tardi.",
            );
          }
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadVehicle();

    return () => controller.abort();
  }, [vehicleId]);

  // Mostra il loader mentre Laravel sta rispondendo.
  if (isLoading) {
    return <Loader message="Caricamento veicolo..." />;
  }

  // Mostra l'errore se la richiesta non è riuscita.
  if (errorMessage) {
    return (
      <>
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>

        <Link to="/vehicles" className="btn btn-outline-primary">
          <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
          Torna ai veicoli
        </Link>
      </>
    );
  }

  // Evita di leggere i dati finché vehicle è ancora null.
  if (!vehicle) {
    return null;
  }

  return (
    <>
      <Link to="/vehicles" className="btn btn-link px-0 mb-3">
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai veicoli
      </Link>

      <div className="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
          <h1 className="mb-1">
            {vehicle.brand} {vehicle.model}
          </h1>

          <p className="text-secondary mb-0">
            Targa: <strong>{vehicle.license_plate}</strong>
          </p>
        </div>

        <span
          className={
            vehicle.is_active
              ? "badge text-bg-success fs-6"
              : "badge text-bg-secondary fs-6"
          }
        >
          {vehicle.is_active ? "Attivo" : "Disattivato"}
        </span>
      </div>

      <div className="card border-0 shadow-sm overflow-hidden mb-4">
        {vehicle.primary_image ? (
          <img
            src={vehicle.primary_image.url}
            alt={
              vehicle.primary_image.caption ||
              `Foto di ${vehicle.brand} ${vehicle.model}`
            }
            className="vehicle-detail-image"
          />
        ) : (
          <div className="vehicle-detail-image vehicle-detail-image--placeholder">
            <i className="bi bi-car-front" aria-hidden="true"></i>

            <span>Nessuna fotografia disponibile</span>
          </div>
        )}
      </div>

      <div className="card border-0 shadow-sm mb-4">
        <div className="card-body p-4">
          <h2 className="h4 mb-4">Dati del veicolo</h2>

          <div className="row g-4">
            <div className="col-12 col-sm-6 col-lg-4">
              <div className="text-secondary small">Tipo</div>

              <div className="fw-semibold">
                {VEHICLE_TYPE_LABELS[vehicle.type] ?? vehicle.type}
              </div>
            </div>

            <div className="col-12 col-sm-6 col-lg-4">
              <div className="text-secondary small">Anno</div>

              <div className="fw-semibold">
                {vehicle.year ?? "Non indicato"}
              </div>
            </div>

            <div className="col-12 col-sm-6 col-lg-4">
              <div className="text-secondary small">Chilometraggio</div>

              <div className="fw-semibold">
                {Number(vehicle.mileage).toLocaleString("it-IT")} km
              </div>
            </div>

            <div className="col-12 col-sm-6 col-lg-4">
              <div className="text-secondary small">Tariffa giornaliera</div>

              <div className="fw-semibold">
                {vehicle.daily_rate !== null
                  ? Number(vehicle.daily_rate).toLocaleString("it-IT", {
                      style: "currency",
                      currency: "EUR",
                    })
                  : "Non impostata"}
              </div>
            </div>

            <div className="col-12 col-sm-6 col-lg-4">
              <div className="text-secondary small">
                Celle necessarie al parcheggio
              </div>

              <div className="fw-semibold">
                {vehicle.parking_units === 1
                  ? "1 cella"
                  : `${vehicle.parking_units} celle`}
              </div>
            </div>

            <div className="col-12 col-sm-6 col-lg-4">
              <div className="text-secondary small">Fotografie</div>

              <div className="fw-semibold">
                {vehicle.images_count ?? vehicle.images?.length ?? 0}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="row g-3 mb-4">
        <div className="col-12 col-md-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="d-flex align-items-center gap-3">
                <i
                  className="bi bi-calendar-check fs-2 text-primary"
                  aria-hidden="true"
                ></i>

                <div>
                  <div className="text-secondary small">Noleggi registrati</div>

                  <div className="fs-4 fw-semibold">
                    {vehicle.rentals_count ?? 0}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-md-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="d-flex align-items-center gap-3">
                <i
                  className="bi bi-receipt fs-2 text-danger"
                  aria-hidden="true"
                ></i>

                <div>
                  <div className="text-secondary small">Voci di spesa</div>

                  <div className="fs-4 fw-semibold">
                    {vehicle.expenses_count ?? 0}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-md-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="d-flex align-items-center gap-3">
                <i
                  className={`bi bi-p-square fs-2 ${
                    vehicle.parking_spaces_count > 0
                      ? "text-success"
                      : "text-secondary"
                  }`}
                  aria-hidden="true"
                ></i>

                <div>
                  <div className="text-secondary small">Autorimessa</div>

                  <div className="fs-5 fw-semibold">
                    {vehicle.parking_spaces_count > 0
                      ? "Parcheggiato"
                      : "Fuori autorimessa"}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

export default VehicleDetailsPage;
