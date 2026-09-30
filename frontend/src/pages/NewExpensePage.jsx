import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import ExpenseForm from "../components/ExpenseForm";
import Loader from "../components/Loader";
import api from "../services/api";

const INITIAL_VALUES = {
  vehicle_id: "",
  category: "",
  description: "",
  amount: "",
  expense_date: new Date().toISOString().slice(0, 10),
  expires_on: "",
  mileage: "",
  supplier: "",
  notes: "",
};

function NewExpensePage() {
  const navigate = useNavigate();
  const [vehicles, setVehicles] = useState([]);
  const [values, setValues] = useState(INITIAL_VALUES);
  const [fieldErrors, setFieldErrors] = useState({});
  const [errorMessage, setErrorMessage] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Carica anche i mezzi disattivati, perché possono possedere costi storici.
  useEffect(() => {
    const controller = new AbortController();

    async function loadVehicles() {
      try {
        const response = await api.get("/api/vehicles", {
          params: { per_page: 100 },
          signal: controller.signal,
        });
        setVehicles(response.data.data);
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare i veicoli.");
        }
      } finally {
        if (!controller.signal.aborted) setIsLoading(false);
      }
    }

    loadVehicles();
    return () => controller.abort();
  }, []);

  function handleChange(fieldName, value) {
    setValues((current) => {
      const nextValues = { ...current, [fieldName]: value };

      // Quando viene scelto un mezzo propone il suo contachilometri attuale.
      if (fieldName === "vehicle_id") {
        const selectedVehicle = vehicles.find(
          (vehicle) => String(vehicle.id) === String(value),
        );

        nextValues.mileage = selectedVehicle?.mileage ?? "";
      }

      return nextValues;
    });
    setFieldErrors((current) => {
      const next = { ...current };
      delete next[fieldName];
      return next;
    });
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setIsSubmitting(true);
    setErrorMessage("");
    setFieldErrors({});

    const payload = {
      vehicle_id: Number(values.vehicle_id),
      category: values.category,
      description: values.description.trim(),
      amount: values.amount,
      expense_date: values.expense_date,
      expires_on: values.expires_on || null,
      mileage: values.mileage === "" ? null : Number(values.mileage),
      supplier: values.supplier.trim() || null,
      notes: values.notes.trim() || null,
    };

    try {
      const response = await api.post("/api/expenses", payload);
      navigate(`/expenses/${response.data.data.id}`, { replace: true });
    } catch (error) {
      if (error.response?.status === 422) {
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage("Controlla i campi evidenziati.");
      } else {
        setErrorMessage("Impossibile registrare la spesa.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) return <Loader message="Preparazione nuova spesa..." />;

  return (
    <>
      <Link
        to="/expenses"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna alle spese
      </Link>

      <header className="page-header">
        <span className="page-eyebrow">Nuovo costo</span>
        <h1 className="mb-1">Registra una spesa</h1>
        <p className="text-secondary mb-0">
          Inserisci un pagamento già sostenuto e l'eventuale prossima scadenza.
        </p>
      </header>

      {errorMessage && <div className="alert alert-danger">{errorMessage}</div>}

      <ExpenseForm
        values={values}
        vehicles={vehicles}
        fieldErrors={fieldErrors}
        isSubmitting={isSubmitting}
        submitLabel="Registra spesa"
        cancelTo="/expenses"
        onChange={handleChange}
        onSubmit={handleSubmit}
      />
    </>
  );
}

export default NewExpensePage;
