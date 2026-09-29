import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import Loader from "../components/Loader";
import RentalForm from "../components/RentalForm";
import api from "../services/api";

// Valori iniziali utilizzati dal form della nuova prenotazione.
const INITIAL_VALUES = {
  customer_id: "",
  vehicle_id: "",
  starts_at: "",
  expected_ends_at: "",
  daily_rate: "",
  amount_paid: "0.00",
  notes: "",
};

function NewRentalPage() {
  const navigate = useNavigate();

  // Conserva le opzioni disponibili nei due menu a tendina.
  const [customers, setCustomers] = useState([]);
  const [vehicles, setVehicles] = useState([]);

  // Conserva i valori e gli errori del form.
  const [values, setValues] = useState(INITIAL_VALUES);
  const [fieldErrors, setFieldErrors] = useState({});
  const [errorMessage, setErrorMessage] = useState("");

  // Distingue il caricamento delle opzioni dal salvataggio finale.
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  /*
   * Carica soltanto clienti e veicoli attivi.
   * Le due richieste vengono eseguite insieme per ridurre l'attesa.
   */
  useEffect(() => {
    const controller = new AbortController();

    async function loadOptions() {
      setIsLoading(true);
      setErrorMessage("");

      try {
        const [customersResponse, vehiclesResponse] = await Promise.all([
          api.get("/api/customers", {
            params: {
              is_active: true,
              sort: "name_asc",
              per_page: 100,
            },
            signal: controller.signal,
          }),
          api.get("/api/vehicles", {
            params: {
              is_active: true,
              per_page: 100,
            },
            signal: controller.signal,
          }),
        ]);

        setCustomers(customersResponse.data.data);
        setVehicles(vehiclesResponse.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage(
            "Impossibile caricare clienti e veicoli. Riprova più tardi.",
          );
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadOptions();

    return () => controller.abort();
  }, []);

  /*
   * Aggiorna il campo modificato. Quando viene scelto un veicolo,
   * propone automaticamente la sua tariffa giornaliera corrente.
   */
  function handleChange(fieldName, value) {
    setValues((currentValues) => {
      const nextValues = {
        ...currentValues,
        [fieldName]: value,
      };

      if (fieldName === "vehicle_id") {
        const selectedVehicle = vehicles.find(
          (vehicle) => String(vehicle.id) === String(value),
        );

        nextValues.daily_rate = selectedVehicle?.daily_rate ?? "";
      }

      return nextValues;
    });

    // Rimuove l'errore del campo non appena l'utente lo corregge.
    setFieldErrors((currentErrors) => {
      if (!currentErrors[fieldName]) {
        return currentErrors;
      }

      const nextErrors = { ...currentErrors };
      delete nextErrors[fieldName];

      return nextErrors;
    });
  }

  // Converte gli orari locali e invia la nuova prenotazione a Laravel.
  async function handleSubmit(event) {
    event.preventDefault();

    setIsSubmitting(true);
    setErrorMessage("");
    setFieldErrors({});

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
      await api.post("/api/rentals", payload);

      // Dopo la creazione torna all'elenco operativo dei noleggi.
      navigate("/rentals", { replace: true });
    } catch (error) {
      if (error.response?.status === 422) {
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage(
          "Controlla i campi evidenziati prima di continuare.",
        );
      } else {
        setErrorMessage(
          "Impossibile creare la prenotazione. Riprova più tardi.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  // Attende che entrambe le liste necessarie siano disponibili.
  if (isLoading) {
    return <Loader message="Preparazione nuova prenotazione..." />;
  }

  return (
    <>
      {/* Collegamento per abbandonare la creazione e tornare all'elenco. */}
      <Link
        to="/rentals"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai noleggi
      </Link>

      {/* Intestazione principale della nuova prenotazione. */}
      <header className="page-header">
        <div>
          <span className="page-eyebrow">Nuovo impegno</span>
          <h1 className="mb-1">Nuova prenotazione</h1>
          <p className="text-secondary mb-0">
            Assegna un cliente e un veicolo a un periodo futuro.
          </p>
        </div>
      </header>

      {/* Errore generale o riepilogo degli errori di validazione. */}
      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {/* Avvisa quando non esistono dati utilizzabili per la prenotazione. */}
      {(customers.length === 0 || vehicles.length === 0) && (
        <div className="alert alert-warning" role="alert">
          Per creare una prenotazione servono almeno un cliente attivo e un
          veicolo attivo.
        </div>
      )}

      <RentalForm
        values={values}
        customers={customers}
        vehicles={vehicles}
        fieldErrors={fieldErrors}
        isSubmitting={isSubmitting}
        submitLabel="Crea prenotazione"
        cancelTo="/rentals"
        onChange={handleChange}
        onSubmit={handleSubmit}
      />
    </>
  );
}

export default NewRentalPage;
