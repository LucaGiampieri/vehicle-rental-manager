import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import Loader from "../components/Loader";
import RentalForm from "../components/RentalForm";
import api from "../services/api";

// Converte un orario ISO nel formato richiesto da datetime-local.
function toLocalDateTimeInput(value) {
  if (!value) return "";

  const date = new Date(value);
  const offset = date.getTimezoneOffset() * 60_000;

  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

function EditRentalPage() {
  const { rentalId } = useParams();
  const navigate = useNavigate();

  // Conserva il noleggio originale e le opzioni disponibili nei menu.
  const [rental, setRental] = useState(null);
  const [customers, setCustomers] = useState([]);
  const [vehicles, setVehicles] = useState([]);

  // Valori modificabili della prenotazione.
  const [values, setValues] = useState({
    customer_id: "",
    vehicle_id: "",
    starts_at: "",
    expected_ends_at: "",
    daily_rate: "",
    amount_paid: "0.00",
    notes: "",
  });

  const [fieldErrors, setFieldErrors] = useState({});
  const [errorMessage, setErrorMessage] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  /*
   * Carica insieme noleggio, clienti e veicoli.
   * Il backend consentirà comunque la modifica soltanto se è prenotato.
   */
  useEffect(() => {
    const controller = new AbortController();

    async function loadPage() {
      try {
        // Le tre richieste indipendenti vengono eseguite contemporaneamente.
        const [rentalResponse, customersResponse, vehiclesResponse] =
          await Promise.all([
            api.get(`/api/rentals/${rentalId}`, {
              signal: controller.signal,
            }),
            api.get("/api/customers", {
              params: { is_active: true, sort: "name_asc", per_page: 100 },
              signal: controller.signal,
            }),
            api.get("/api/vehicles", {
              params: { is_active: true, per_page: 100 },
              signal: controller.signal,
            }),
          ]);

        const loadedRental = rentalResponse.data.data;

        // Conserva l'originale e prepara i valori compatibili con il form.
        setRental(loadedRental);
        setCustomers(customersResponse.data.data);
        setVehicles(vehiclesResponse.data.data);
        setValues({
          customer_id: String(loadedRental.customer_id),
          vehicle_id: String(loadedRental.vehicle_id),
          starts_at: toLocalDateTimeInput(loadedRental.starts_at),
          expected_ends_at: toLocalDateTimeInput(
            loadedRental.expected_ends_at,
          ),
          daily_rate: loadedRental.daily_rate ?? "",
          amount_paid: loadedRental.amount_paid ?? "0.00",
          notes: loadedRental.notes ?? "",
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage(
            error.response?.status === 404
              ? "La prenotazione richiesta non esiste."
              : "Impossibile caricare la prenotazione.",
          );
        }
      } finally {
        if (!controller.signal.aborted) setIsLoading(false);
      }
    }

    loadPage();

    return () => controller.abort();
  }, [rentalId]);

  // Aggiorna il campo e propone la tariffa del veicolo appena selezionato.
  function handleChange(fieldName, value) {
    setValues((currentValues) => {
      const nextValues = { ...currentValues, [fieldName]: value };

      if (fieldName === "vehicle_id") {
        const selectedVehicle = vehicles.find(
          (vehicle) => String(vehicle.id) === String(value),
        );
        // Cambiando mezzo viene proposta la sua tariffa corrente.
        nextValues.daily_rate = selectedVehicle?.daily_rate ?? "";
      }

      return nextValues;
    });

    setFieldErrors((currentErrors) => {
      const nextErrors = { ...currentErrors };
      delete nextErrors[fieldName];
      return nextErrors;
    });
  }

  // Invia tutti i dati modificabili e lascia al backend i controlli finali.
  async function handleSubmit(event) {
    event.preventDefault();
    setIsSubmitting(true);
    setErrorMessage("");
    setFieldErrors({});

    // Prepara tipi e date nel formato previsto dall'API Laravel.
    const payload = {
      vehicle_id: Number(values.vehicle_id),
      customer_id: Number(values.customer_id),
      starts_at: values.starts_at
        ? new Date(values.starts_at).toISOString()
        : "",
      expected_ends_at: values.expected_ends_at
        ? new Date(values.expected_ends_at).toISOString()
        : "",
      daily_rate: values.daily_rate,
      amount_paid: values.amount_paid || 0,
      notes: values.notes.trim() || null,
    };

    try {
      // Il backend impedisce modifiche non ammesse a noleggi già iniziati.
      await api.patch(`/api/rentals/${rentalId}`, payload);
      navigate(`/rentals/${rentalId}`, { replace: true });
    } catch (error) {
      if (error.response?.status === 422) {
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage("Controlla i campi evidenziati prima di continuare.");
      } else {
        setErrorMessage("Impossibile modificare la prenotazione.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <Loader message="Caricamento prenotazione..." />;
  }

  if (errorMessage && !rental) {
    return <div className="alert alert-danger">{errorMessage}</div>;
  }

  // Impedisce di mostrare un form inutilizzabile per noleggi già iniziati.
  if (rental.status !== "reserved") {
    return (
      <div className="alert alert-warning">
        Questo noleggio non è più prenotato e i dati principali non possono
        essere modificati. <Link to={`/rentals/${rentalId}`}>Torna al dettaglio</Link>.
      </div>
    );
  }

  return (
    <>
      <Link
        to={`/rentals/${rentalId}`}
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna al dettaglio
      </Link>

      <header className="page-header">
        <span className="page-eyebrow">Prenotazione #{rental.id}</span>
        <h1 className="mb-1">Modifica prenotazione</h1>
        <p className="text-secondary mb-0">
          Aggiorna cliente, veicolo, periodo, tariffa e caparra.
        </p>
      </header>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      <RentalForm
        values={values}
        customers={customers}
        vehicles={vehicles}
        fieldErrors={fieldErrors}
        isSubmitting={isSubmitting}
        submitLabel="Salva modifiche"
        cancelTo={`/rentals/${rentalId}`}
        onChange={handleChange}
        onSubmit={handleSubmit}
      />
    </>
  );
}

export default EditRentalPage;
