import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import ExpenseForm from "../components/ExpenseForm";
import Loader from "../components/Loader";
import api from "../services/api";

function EditExpensePage() {
  const { expenseId } = useParams();
  const navigate = useNavigate();
  const [expense, setExpense] = useState(null);
  const [vehicles, setVehicles] = useState([]);
  const [values, setValues] = useState(null);
  const [fieldErrors, setFieldErrors] = useState({});
  const [errorMessage, setErrorMessage] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    async function loadPage() {
      try {
        const [expenseResponse, vehiclesResponse] = await Promise.all([
          api.get(`/api/expenses/${expenseId}`, { signal: controller.signal }),
          api.get("/api/vehicles", {
            params: { per_page: 100 },
            signal: controller.signal,
          }),
        ]);

        const loaded = expenseResponse.data.data;
        setExpense(loaded);
        setVehicles(vehiclesResponse.data.data);
        setValues({
          vehicle_id: String(loaded.vehicle_id),
          category: loaded.category,
          description: loaded.description,
          amount: loaded.amount,
          expense_date: loaded.expense_date,
          expires_on: loaded.expires_on ?? "",
          mileage: loaded.mileage ?? "",
          supplier: loaded.supplier ?? "",
          notes: loaded.notes ?? "",
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          setErrorMessage("Impossibile caricare la spesa.");
        }
      } finally {
        if (!controller.signal.aborted) setIsLoading(false);
      }
    }

    loadPage();
    return () => controller.abort();
  }, [expenseId]);

  function handleChange(fieldName, value) {
    setValues((current) => {
      const nextValues = { ...current, [fieldName]: value };

      // Cambiando veicolo propone il chilometraggio del nuovo mezzo.
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
      await api.patch(`/api/expenses/${expenseId}`, payload);
      navigate(`/expenses/${expenseId}`, { replace: true });
    } catch (error) {
      if (error.response?.status === 422) {
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage("Controlla i campi evidenziati.");
      } else {
        setErrorMessage("Impossibile modificare la spesa.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) return <Loader message="Caricamento spesa..." />;
  if (!expense || !values) return <div className="alert alert-danger">{errorMessage}</div>;

  return (
    <>
      <Link
        to={`/expenses/${expenseId}`}
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna al dettaglio
      </Link>

      <header className="page-header">
        <span className="page-eyebrow">Spesa #{expense.id}</span>
        <h1 className="mb-1">Modifica spesa</h1>
        <p className="text-secondary mb-0">
          Correggi i dati del costo e della scadenza collegata.
        </p>
      </header>

      {errorMessage && <div className="alert alert-danger">{errorMessage}</div>}

      <ExpenseForm
        values={values}
        vehicles={vehicles}
        fieldErrors={fieldErrors}
        isSubmitting={isSubmitting}
        submitLabel="Salva modifiche"
        cancelTo={`/expenses/${expenseId}`}
        onChange={handleChange}
        onSubmit={handleSubmit}
      />
    </>
  );
}

export default EditExpensePage;
