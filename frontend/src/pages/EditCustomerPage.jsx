import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import CustomerForm from "../components/CustomerForm";
import Loader from "../components/Loader";
import api from "../services/api";

// Struttura iniziale del form, utilizzata prima del caricamento del cliente.
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

/*
 * Gli input HTML di tipo date accettano il formato YYYY-MM-DD.
 * L'API può invece restituire una data ISO completa: questa funzione
 * conserva soltanto i primi dieci caratteri necessari al form.
 */
function formatDateForInput(value) {
  if (!value) {
    return "";
  }

  return String(value).slice(0, 10);
}

// Trasforma nome e cognome in un testo adatto all'indirizzo della pagina.
function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

function EditCustomerPage() {
  // Recupera l'identificativo del cliente presente nell'indirizzo.
  const { customerId } = useParams();

  // Permette di tornare alla scheda dopo il salvataggio.
  const navigate = useNavigate();

  // Conserva i valori mostrati negli input del form condiviso.
  const [values, setValues] = useState(INITIAL_VALUES);

  // Conserva gli errori associati ai singoli campi restituiti da Laravel.
  const [fieldErrors, setFieldErrors] = useState({});

  // Conserva un eventuale errore generale di caricamento o salvataggio.
  const [errorMessage, setErrorMessage] = useState("");

  // Distingue il caricamento iniziale dal successivo invio del form.
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  /*
   * Carica il cliente quando si apre la pagina o cambia l'identificativo.
   * AbortController evita aggiornamenti di stato se la pagina viene chiusa
   * prima della conclusione della richiesta.
   */
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

        // Converte i valori null in stringhe vuote adatte agli input React.
        setValues({
          first_name: customer.first_name ?? "",
          last_name: customer.last_name ?? "",
          birth_date: formatDateForInput(customer.birth_date),
          email: customer.email ?? "",
          phone: customer.phone ?? "",
          tax_code: customer.tax_code ?? "",
          driving_license_number:
            customer.driving_license_number ?? "",
          driving_license_expiry_date: formatDateForInput(
            customer.driving_license_expiry_date,
          ),
          address: customer.address ?? "",
          notes: customer.notes ?? "",
          is_active: Boolean(customer.is_active),
        });
      } catch (error) {
        // Una richiesta annullata non rappresenta un errore per l'utente.
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

  /*
   * Aggiorna soltanto il campo modificato e rimuove il relativo
   * errore precedente non appena l'utente prova a correggerlo.
   */
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

  // Invia a Laravel tutti i dati aggiornati del cliente.
  async function handleSubmit(event) {
    event.preventDefault();

    setIsSubmitting(true);
    setErrorMessage("");
    setFieldErrors({});

    /*
     * I campi facoltativi vuoti diventano null, evitando di salvare
     * stringhe vuote quando il dato non è realmente presente.
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
      const response = await api.patch(
        `/api/customers/${customerId}`,
        payload,
      );

      const customer = response.data.data;
      const customerSlug = createSlug(
        `${customer.first_name} ${customer.last_name}`,
      );

      // Dopo il salvataggio apre la scheda con il nome eventualmente nuovo.
      navigate(`/customers/${customer.id}/${customerSlug}`, {
        replace: true,
      });
    } catch (error) {
      if (error.response?.status === 422) {
        // Gli errori di validazione vengono mostrati sotto i relativi campi.
        setFieldErrors(error.response.data.errors ?? {});
        setErrorMessage(
          "Controlla i campi evidenziati prima di continuare.",
        );
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

  // Durante la richiesta iniziale mostra il loader condiviso.
  if (isLoading) {
    return <Loader message="Caricamento cliente..." />;
  }

  return (
    <>
      {/* Collegamento rapido alla scheda senza salvare modifiche. */}
      <Link
        to={`/customers/${customerId}`}
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna alla scheda cliente
      </Link>

      {/* Intestazione della pagina di modifica. */}
      <header className="page-header">
        <div>
          <span className="page-eyebrow">Modifica anagrafica</span>

          <h1 className="mb-1">Modifica cliente</h1>

          <p className="text-secondary mb-0">
            Aggiorna i dati personali, i contatti e la patente.
          </p>
        </div>
      </header>

      {/* Mostra sia gli errori generali sia il riepilogo della validazione. */}
      {errorMessage && (
        <div className="alert alert-danger" role="alert">
          {errorMessage}
        </div>
      )}

      {/*
       * Se il cliente è stato caricato mostra il form condiviso.
       * In caso di errore 422 il form resta visibile per consentire
       * all'utente di correggere i campi evidenziati.
       */}
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
