import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import CustomerForm from "../components/CustomerForm";
import Loader from "../components/Loader";
import api from "../services/api";

const INITIAL_VALUES = {
  first_name: "",
  last_name: "",
  birth_date: "",
  email: "",
  phone: "",
  tax_code: "",
  driving_license_number: "",
  driving_license_expiry_date: "",
  address: "",
  notes: "",
  is_active: true,
};

// Estrae soltanto YYYY-MM-DD da una data ricevuta dall’API.
function formatDateForInput(value) {
  if (!value) {
    return "";
  }

  return String(value).slice(0, 10);
}

// Crea la parte leggibile dell’indirizzo del cliente.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function EditCustomerPage() {
  const { customerId } = useParams();
  const navigate = useNavigate();

  const [values, setValues] = useState(INITIAL_VALUES);
  const [fieldErrors, setFieldErrors] = useState({});
  const [errorMessage, setErrorMessage] = useState("");
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    async function loadCustomer() {
      setIsLoading(true);
      setErrorMessage("");

      try {
        const response = await api.get(`/api/customers/${customerId}`, {
          signal: controller.signal,
        });

        const customer = response.data.data;

        setValues({
          first_name: customer.first_name ?? "",
          last_name: customer.last_name ?? "",
          birth_date: formatDateForInput(customer.birth_date),
          email: customer.email ?? "",
          phone: customer.phone ?? "",
          tax_code: customer.tax_code ?? "",
          driving_license_number: customer.driving_license_number ?? "",
          driving_license_expiry_date: formatDateForInput(
            customer.driving_license_expiry_date,
          ),
          address: customer.address ?? "",
          notes: customer.notes ?? "",
          is_active: Boolean(customer.is_active),
        });
      } catch (error) {
        if (error.code !== "ERR_CANCELED") {
          if (error.response?.status === 404) {
            setErrorMessage("Il cliente richiesto non esiste.");
          } else {
            setErrorMessage(
              "Impossibile caricare il cliente. Riprova più tardi.",
            );
          }
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
        }
      }
    }

    loadCustomer();

    return () => controller.abort();
  }, [customerId]);

  function handleChange(fieldName, value) {
    setValues((currentValues) => ({
      ...currentValues,
      [fieldName]: value,
    }));

    setFieldErrors((currentErrors) => {
      if (!currentErrors[fieldName]) {
        return currentErrors;
      }

      const nextErrors = { ...currentErrors };
      delete nextErrors[fieldName];

      return nextErrors;
    });
  }

  async function handleSubmit(event) {
    event.preventDefault();

    setIsSubmitting(true);
    setErrorMessage("");
    setFieldErrors({});

    const payload = {
      ...values,
      birth_date: values.birth_date || null,
      email: values.email || null,
      phone: values.phone || null,
      tax_code: values.tax_code || null,
      address: values.address || null,
      notes: values.notes || null,
    };

    try {
      const response = await api.patch(`/api/customers/${customerId}`, payload);

      const customer = response.data.data;
      const customerSlug = createSlug(
        `${customer.first_name} ${customer.last_name}`,
      );

      navigate(`/customers/${customer.id}/${customerSlug}`, {
        replace: true,
      });
    } catch (error) {
      if (error.response?.status === 422) {
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage("Controlla i campi evidenziati prima di continuare.");
      } else if (error.response?.status === 404) {
        setErrorMessage("Il cliente richiesto non esiste.");
      } else {
        setErrorMessage(
          "Impossibile modificare il cliente. Riprova più tardi.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoading) {
    return <Loader message="Caricamento cliente..." />;
  }

  return (
    <>
      <Link
        to={`/customers/${customerId}`}
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna alla scheda cliente
      </Link>

      <header className="page-header">
        <div>
          <span className="page-eyebrow">Modifica anagrafica</span>

          <h1 className="mb-1">Modifica cliente</h1>

          <p className="text-secondary mb-0">
            Aggiorna i dati personali, i contatti e la patente.
          </p>
        </div>
      </header>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {!errorMessage || Object.keys(fieldErrors).length > 0 ? (
        <CustomerForm
          values={values}
          fieldErrors={fieldErrors}
          isSubmitting={isSubmitting}
          submitLabel="Salva modifiche"
          cancelTo={`/customers/${customerId}`}
          onChange={handleChange}
          onSubmit={handleSubmit}
        />
      ) : (
        <Link to="/customers" className="btn btn-outline-primary">
          Torna ai clienti
        </Link>
      )}
    </>
  );
}

export default EditCustomerPage;
