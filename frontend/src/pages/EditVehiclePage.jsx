import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import Loader from "../components/Loader";
import VehicleForm from "../components/VehicleForm";
import api from "../services/api";
import createSlug from "../utils/createSlug";

function EditVehiclePage() {
  // Recupera l'identificativo del veicolo dall'indirizzo.
  const { vehicleId } = useParams();

  // Permette di cambiare pagina dopo il salvataggio.
  const navigate = useNavigate();

  // Conserva il veicolo recuperato da Laravel.
  const [vehicle, setVehicle] = useState(null);

  // Gestisce il caricamento iniziale.
  const [isLoading, setIsLoading] = useState(true);

  // Indica che il salvataggio è in corso.
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Conserva un eventuale errore di caricamento.
  const [loadErrorMessage, setLoadErrorMessage] = useState("");

  // Conserva un eventuale errore generale di salvataggio.
  const [submitErrorMessage, setSubmitErrorMessage] = useState("");

  // Conserva gli errori Laravel associati ai singoli campi.
  const [validationErrors, setValidationErrors] = useState({});

  useEffect(() => {
    // Permette di annullare la richiesta lasciando la pagina.
    const controller = new AbortController();

    async function loadVehicle() {
      setIsLoading(true);
      setLoadErrorMessage("");

      try {
        const response = await api.get(`/api/vehicles/${vehicleId}`, {
          signal: controller.signal,
        });

        setVehicle(response.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          if (error.response?.status === 404) {
            setLoadErrorMessage("Il veicolo da modificare non esiste.");
          } else {
            setLoadErrorMessage(
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

  // Invia a Laravel tutti i dati aggiornati del veicolo.
  async function handleUpdateVehicle(vehicleData) {
    setIsSubmitting(true);
    setValidationErrors({});
    setSubmitErrorMessage("");

    try {
      const response = await api.patch(
        `/api/vehicles/${vehicleId}`,
        vehicleData,
      );

      const updatedVehicle = response.data.data;

      // Aggiorna lo slug nel caso siano cambiati marca o modello.
      const updatedSlug = createSlug(
        `${updatedVehicle.brand}-${updatedVehicle.model}`,
      );

      navigate(`/vehicles/${updatedVehicle.id}/${updatedSlug}`);
    } catch (error) {
      if (error.response?.status === 422) {
        setValidationErrors(error.response.data.errors ?? {});

        setSubmitErrorMessage("Controlla i campi evidenziati e riprova.");
      } else {
        setSubmitErrorMessage(
          "Impossibile modificare il veicolo. Riprova più tardi.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  // Mostra il loader durante il caricamento iniziale.
  if (isLoading) {
    return <Loader message="Caricamento veicolo..." />;
  }

  // Mostra l'errore se il veicolo non può essere recuperato.
  if (loadErrorMessage) {
    return (
      <>
        <div className="alert alert-danger" role="alert">
          {loadErrorMessage}
        </div>

        <Link to="/vehicles" className="btn btn-outline-secondary">
          <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
          Torna ai veicoli
        </Link>
      </>
    );
  }

  // Evita di mostrare il modulo senza i dati del veicolo.
  if (!vehicle) {
    return null;
  }

  const vehicleSlug = createSlug(`${vehicle.brand}-${vehicle.model}`);

  const vehicleDetailsPath = `/vehicles/${vehicle.id}/${vehicleSlug}`;

  return (
    <>
      <Link
        to={vehicleDetailsPath}
        className="btn btn-sm btn-outline-secondary mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna al veicolo
      </Link>

      <div className="mb-4">
        <h1 className="mb-1">Modifica veicolo</h1>

        <p className="text-secondary mb-0">
          Aggiorna i dati di {vehicle.brand} {vehicle.model}.
        </p>
      </div>

      {submitErrorMessage && (
        <div className="alert alert-danger" role="alert">
          {submitErrorMessage}
        </div>
      )}

      <VehicleForm
        initialValues={{
          license_plate: vehicle.license_plate,
          brand: vehicle.brand,
          model: vehicle.model,
          type: vehicle.type,
          parking_units: vehicle.parking_units,
          year: vehicle.year,
          mileage: vehicle.mileage,
          daily_rate: vehicle.daily_rate,
          is_active: vehicle.is_active,
        }}
        validationErrors={validationErrors}
        isSubmitting={isSubmitting}
        submitLabel="Salva modifiche"
        onSubmit={handleUpdateVehicle}
      />
    </>
  );
}

export default EditVehiclePage;
