import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import CustomerForm from "../components/CustomerForm";
import api from "../services/api";

// Valori iniziali del form.
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

// Crea la parte leggibile dell’indirizzo del cliente.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function NewCustomerPage() {
  const navigate = useNavigate();

  // Conserva tutti i valori inseriti nel form.
  const [values, setValues] = useState(INITIAL_VALUES);

  // Conserva gli errori relativi ai singoli campi.
  const [fieldErrors, setFieldErrors] = useState({});

  // Conserva un errore generale della richiesta.
  const [errorMessage, setErrorMessage] = useState("");

  // Impedisce invii multipli durante il salvataggio.
  const [isSubmitting, setIsSubmitting] = useState(false);

  /*
   * Aggiorna un singolo campo senza ricreare manualmente
   * tutto l’oggetto dei valori.
   */
  function handleChange(fieldName, value) {
    setValues((currentValues) => ({
      ...currentValues,
      [fieldName]: value,
    }));

    /*
     * Quando l’utente corregge un campo,
     * rimuove il precedente errore di Laravel.
     */
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

    /*
     * I campi facoltativi vuoti vengono trasformati in null.
     * In questo modo il JSON esprime chiaramente l’assenza del dato.
     */
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
      const response = await api.post("/api/customers", payload);
      const customer = response.data.data;

      const customerSlug = createSlug(
        `${customer.first_name} ${customer.last_name}`,
      );

      /*
       * Dopo la creazione apre direttamente la scheda
       * del nuovo cliente.
       */
      navigate(`/customers/${customer.id}/${customerSlug}`, {
        replace: true,
      });
    } catch (error) {
      if (error.response?.status === 422) {
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage("Controlla i campi evidenziati prima di continuare.");
      } else {
        setErrorMessage("Impossibile creare il cliente. Riprova più tardi.");
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <>
      <Link
        to="/customers"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai clienti
      </Link>

      <header className="page-header">
        <div>
          <span className="page-eyebrow">Nuova anagrafica</span>

          <h1 className="mb-1">Nuovo cliente</h1>

          <p className="text-secondary mb-0">
            Registra i dati necessari per prenotazioni e noleggi.
          </p>
        </div>
      </header>

      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      <CustomerForm
        values={values}
        fieldErrors={fieldErrors}
        isSubmitting={isSubmitting}
        submitLabel="Crea cliente"
        cancelTo="/customers"
        onChange={handleChange}
        onSubmit={handleSubmit}
      />
    </>
  );
}

export default NewCustomerPage;
