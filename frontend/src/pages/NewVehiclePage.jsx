import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import VehicleForm from "../components/VehicleForm";
import api from "../services/api";
import createSlug from "../utils/createSlug";

function NewVehiclePage() {
  /*
   * useNavigate permette di cambiare pagina tramite JavaScript
   * dopo che Laravel ha creato correttamente il veicolo.
   */
  const navigate = useNavigate();

  // Indica che la richiesta di creazione è in corso.
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Conserva gli errori associati ai singoli campi.
  const [validationErrors, setValidationErrors] = useState({});

  // Conserva un eventuale errore generale.
  const [errorMessage, setErrorMessage] = useState("");

  // Invia a Laravel i dati preparati da VehicleForm.
  async function handleCreateVehicle(vehicleData) {
    setIsSubmitting(true);
    setValidationErrors({});
    setErrorMessage("");

    try {
      const response = await api.post("/api/vehicles", vehicleData);

      // Laravel restituisce il nuovo veicolo nella proprietà data.
      const createdVehicle = response.data.data;

      /*
       * Costruisce un indirizzo leggibile e apre immediatamente
       * la pagina di dettaglio del veicolo appena creato.
       */
      const vehicleSlug = createSlug(
        `${createdVehicle.brand}-${createdVehicle.model}`,
      );

      navigate(`/vehicles/${createdVehicle.id}/${vehicleSlug}`);
    } catch (error) {
      /*
       * Lo stato 422 indica che Laravel ha rifiutato
       * uno o più campi del modulo.
       */
      if (error.response?.status === 422) {
        setValidationErrors(error.response.data.errors ?? {});

        setErrorMessage("Controlla i campi evidenziati e riprova.");
      } else {
        setErrorMessage("Impossibile creare il veicolo. Riprova più tardi.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <>
      <Link to="/vehicles" className="btn btn-sm btn-outline-secondary mb-3">
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai veicoli
      </Link>

      <div className="mb-4">
        <h1 className="mb-1">Nuovo veicolo</h1>

        <p className="text-secondary mb-0">
          Inserisci i dati principali del mezzo. Le fotografie potranno essere
          aggiunte dopo la creazione.
        </p>
      </div>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      <VehicleForm
        validationErrors={validationErrors}
        isSubmitting={isSubmitting}
        submitLabel="Crea veicolo"
        onSubmit={handleCreateVehicle}
      />
    </>
  );
}

export default NewVehiclePage;
