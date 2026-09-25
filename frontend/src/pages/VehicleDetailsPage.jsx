import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
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

// Traduce le categorie tecniche delle fotografie.
const IMAGE_CATEGORY_LABELS = {
  exterior: "Esterno",
  interior: "Interno",
  plate: "Targa",
  damage: "Danno",
  other: "Altro",
};

// Associa a ogni stato operativo un testo, un'icona e un colore distinti.
const OPERATIONAL_STATUS_CONFIG = {
  available: {
    label: "Disponibile",
    icon: "bi-check-circle-fill",
    className: "vehicle-status--available",
  },
  reserved: {
    label: "Prenotato",
    icon: "bi-calendar-check-fill",
    className: "vehicle-status--reserved",
  },
  rented: {
    label: "Noleggiato",
    icon: "bi-key-fill",
    className: "vehicle-status--rented",
  },
  inactive: {
    label: "Disattivato",
    icon: "bi-slash-circle-fill",
    className: "vehicle-status--inactive",
  },
};

// Formatta una cifra come importo in euro.
function formatCurrency(value) {
  if (value === null || value === undefined || value === "") {
    return "Non impostata";
  }

  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value));
}

// Formatta data e ora usando le convenzioni italiane.
function formatDateTime(value) {
  if (!value) {
    return "Non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

// Compone il nome del cliente presente nel riepilogo del noleggio.
function getCustomerName(rental) {
  if (!rental?.customer) {
    return "Cliente non disponibile";
  }

  return `${rental.customer.first_name} ${rental.customer.last_name}`.trim();
}

function VehicleDetailsPage() {
  // Legge il parametro dinamico presente nell'indirizzo.
  const { vehicleId } = useParams();

  // Permette di tornare all'elenco dopo l'eliminazione.
  const navigate = useNavigate();

  // Conserva il veicolo restituito da Laravel.
  const [vehicle, setVehicle] = useState(null);

  // Conserva la fotografia mostrata nel riquadro principale.
  const [selectedImage, setSelectedImage] = useState(null);

  // Conserva uno o più file scelti dall'utente.
  const [imageFiles, setImageFiles] = useState([]);

  // Indica che il caricamento di una fotografia è in corso.
  const [isUploading, setIsUploading] = useState(false);

  // Conserva un eventuale errore del caricamento.
  const [uploadError, setUploadError] = useState("");

  // Controlla l'apertura del pannello di gestione delle fotografie.
  const [isImageManagerOpen, setIsImageManagerOpen] = useState(false);

  // Conserva l'immagine che Laravel sta modificando.
  const [updatingImageId, setUpdatingImageId] = useState(null);

  // Conserva l'immagine che Laravel sta eliminando.
  const [deletingImageId, setDeletingImageId] = useState(null);

  // Conserva la fotografia attualmente in modifica.
  const [editingImage, setEditingImage] = useState(null);

  // Conserva la categoria selezionata nel modulo.
  const [editCategory, setEditCategory] = useState("exterior");

  // Conserva la descrizione inserita nel modulo.
  const [editCaption, setEditCaption] = useState("");

  // Indica che l'eliminazione del veicolo è in corso.
  const [isDeletingVehicle, setIsDeletingVehicle] = useState(false);

  // Conserva un eventuale errore di eliminazione.
  const [deleteVehicleError, setDeleteVehicleError] = useState("");

  // Indica che il salvataggio della modifica è in corso.
  const [isSavingImage, setIsSavingImage] = useState(false);

  // Conserva un eventuale errore delle operazioni sulla galleria.
  const [imageActionError, setImageActionError] = useState("");

  // Gestisce il caricamento iniziale della pagina.
  const [isLoading, setIsLoading] = useState(true);

  // Conserva un eventuale errore della richiesta principale.
  const [errorMessage, setErrorMessage] = useState("");

  // Invia una nuova fotografia al backend.
  async function handleImageUpload(event) {
    event.preventDefault();

    // Conserva il form per poterlo svuotare dopo il caricamento.
    const form = event.currentTarget;

    if (imageFiles.length === 0) {
      setUploadError("Seleziona almeno una fotografia da caricare.");

      return;
    }

    const currentImageCount = vehicle?.images?.length ?? 0;

    if (currentImageCount + imageFiles.length > 10) {
      setUploadError(
        `Puoi aggiungere al massimo ${10 - currentImageCount} ${
          10 - currentImageCount === 1 ? "fotografia" : "fotografie"
        }. Il limite complessivo è di 10.`,
      );

      return;
    }

    setIsUploading(true);
    setUploadError("");

    /*
     * FormData permette di inviare file tramite multipart/form-data.
     * Il nome "image" deve corrispondere a quello validato da Laravel.
     */
    try {
      const uploadedImages = [];

      // L'API riceve una foto per richiesta: il frontend le invia in sequenza.
      for (const imageFile of imageFiles) {
        const formData = new FormData();
        formData.append("image", imageFile);

        const response = await api.post(
          `/api/vehicles/${vehicleId}/images`,
          formData,
        );

        uploadedImages.push(response.data.data);
      }

      const lastUploadedImage = uploadedImages.at(-1);

      /*
       * Aggiunge la fotografia alla copia locale del veicolo,
       * senza dover eseguire immediatamente una seconda richiesta GET.
       */
      setVehicle((currentVehicle) => {
        if (!currentVehicle) {
          return currentVehicle;
        }

        const currentImages = currentVehicle.images ?? [];

        return {
          ...currentVehicle,
          images: [...currentImages, ...uploadedImages],
          images_count:
            (currentVehicle.images_count ?? currentImages.length) +
            uploadedImages.length,
          primary_image: uploadedImages.find((image) => image.is_primary)
            ? uploadedImages.find((image) => image.is_primary)
            : currentVehicle.primary_image,
        };
      });

      // Mostra immediatamente l'ultima fotografia caricata.
      setSelectedImage(lastUploadedImage);

      // Svuota i file selezionati e il campo del modulo.
      setImageFiles([]);
      form.reset();
    } catch (error) {
      // Recupera l'eventuale messaggio di validazione inviato da Laravel.
      const validationMessage = error.response?.data?.errors?.image?.[0];

      setUploadError(
        validationMessage || "Impossibile caricare la fotografia.",
      );
    } finally {
      setIsUploading(false);
    }
  }

  // Imposta una fotografia come nuova copertina del veicolo.
  async function handleSetPrimary(image) {
    // Evita una richiesta inutile se è già la copertina.
    if (image.is_primary) {
      return;
    }

    setUpdatingImageId(image.id);
    setImageActionError("");

    try {
      const response = await api.patch(`/api/vehicle-images/${image.id}`, {
        is_primary: true,
      });

      const updatedImage = response.data.data;

      /*
       * La fotografia selezionata diventa principale.
       * Tutte le altre fotografie perdono lo stato di copertina.
       */
      setVehicle((currentVehicle) => {
        if (!currentVehicle) {
          return currentVehicle;
        }

        const updatedImages = (currentVehicle.images ?? []).map(
          (currentImage) => {
            if (currentImage.id === updatedImage.id) {
              return updatedImage;
            }

            return {
              ...currentImage,
              is_primary: false,
            };
          },
        );

        return {
          ...currentVehicle,
          images: updatedImages,
          primary_image: updatedImage,
        };
      });

      // Mostra immediatamente la nuova copertina.
      setSelectedImage(updatedImage);
    } catch (error) {
      const validationMessage = error.response?.data?.errors?.is_primary?.[0];

      setImageActionError(
        validationMessage ||
          "Impossibile impostare la fotografia come copertina.",
      );
    } finally {
      setUpdatingImageId(null);
    }
  }

  // Apre il modulo utilizzando i dati attuali della fotografia.
  function handleStartImageEdit(image) {
    setEditingImage(image);
    setEditCategory(image.category);
    setEditCaption(image.caption ?? "");
    setImageActionError("");
  }

  // Invia a Laravel la nuova categoria e la nuova descrizione.
  async function handleImageUpdate(event) {
    event.preventDefault();

    if (!editingImage) {
      return;
    }

    setIsSavingImage(true);
    setImageActionError("");

    try {
      const response = await api.patch(
        `/api/vehicle-images/${editingImage.id}`,
        {
          category: editCategory,
          caption: editCaption,
        },
      );

      const updatedImage = response.data.data;

      // Sostituisce la fotografia modificata nella galleria locale.
      setVehicle((currentVehicle) => {
        if (!currentVehicle) {
          return currentVehicle;
        }

        const updatedImages = (currentVehicle.images ?? []).map(
          (currentImage) =>
            currentImage.id === updatedImage.id ? updatedImage : currentImage,
        );

        return {
          ...currentVehicle,
          images: updatedImages,
          primary_image:
            currentVehicle.primary_image?.id === updatedImage.id
              ? updatedImage
              : currentVehicle.primary_image,
        };
      });

      // Aggiorna anche la fotografia grande, se è quella modificata.
      setSelectedImage((currentSelectedImage) =>
        currentSelectedImage?.id === updatedImage.id
          ? updatedImage
          : currentSelectedImage,
      );

      // Chiude il modulo dopo il salvataggio.
      setEditingImage(null);
    } catch (error) {
      const validationErrors = error.response?.data?.errors;

      setImageActionError(
        validationErrors?.category?.[0] ||
          validationErrors?.caption?.[0] ||
          error.response?.data?.message ||
          "Impossibile modificare la fotografia.",
      );
    } finally {
      setIsSavingImage(false);
    }
  }

  // Elimina una fotografia dopo aver chiesto conferma all'utente.
  async function handleDeleteImage(image) {
    const isConfirmed = window.confirm(
      "Vuoi eliminare definitivamente questa fotografia?",
    );

    if (!isConfirmed) {
      return;
    }

    setDeletingImageId(image.id);
    setImageActionError("");

    try {
      await api.delete(`/api/vehicle-images/${image.id}`);

      // Chiude il modulo se era aperto sulla fotografia eliminata.
      if (editingImage?.id === image.id) {
        setEditingImage(null);
      }

      // Rimuove dalla galleria locale la fotografia eliminata.
      let updatedImages = (vehicle.images ?? []).filter(
        (currentImage) => currentImage.id !== image.id,
      );

      /*
       * Se è stata eliminata la copertina, individua la prima
       * fotografia rimasta seguendo lo stesso ordine del backend.
       */
      if (image.is_primary && updatedImages.length > 0) {
        const nextPrimary = [...updatedImages].sort(
          (firstImage, secondImage) =>
            Number(firstImage.sort_order) - Number(secondImage.sort_order) ||
            firstImage.id - secondImage.id,
        )[0];

        updatedImages = updatedImages.map((currentImage) => ({
          ...currentImage,
          is_primary: currentImage.id === nextPrimary.id,
        }));
      }

      const primaryImage =
        updatedImages.find((currentImage) => currentImage.is_primary) ?? null;

      // Aggiorna il veicolo senza eseguire una nuova richiesta GET.
      setVehicle((currentVehicle) => {
        if (!currentVehicle) {
          return currentVehicle;
        }

        return {
          ...currentVehicle,
          images: updatedImages,
          images_count: updatedImages.length,
          primary_image: primaryImage,
        };
      });

      /*
       * Se era visualizzata la fotografia eliminata, mostra la nuova
       * copertina oppure la prima fotografia ancora disponibile.
       */
      setSelectedImage((currentSelectedImage) => {
        if (!currentSelectedImage) {
          return primaryImage ?? updatedImages[0] ?? null;
        }

        if (currentSelectedImage.id === image.id) {
          return primaryImage ?? updatedImages[0] ?? null;
        }

        return (
          updatedImages.find(
            (currentImage) => currentImage.id === currentSelectedImage.id,
          ) ??
          primaryImage ??
          null
        );
      });
    } catch (error) {
      setImageActionError(
        error.response?.data?.message || "Impossibile eliminare la fotografia.",
      );
    } finally {
      setDeletingImageId(null);
    }
  }

  // Elimina definitivamente il veicolo dopo una conferma.
  async function handleDeleteVehicle() {
    const isConfirmed = window.confirm(
      "Vuoi eliminare definitivamente questo veicolo? Anche tutte le sue fotografie verranno eliminate.",
    );

    if (!isConfirmed) {
      return;
    }

    setIsDeletingVehicle(true);
    setDeleteVehicleError("");

    try {
      await api.delete(`/api/vehicles/${vehicle.id}`);

      /*
       * Replace impedisce di tornare con il browser
       * alla pagina del veicolo ormai eliminato.
       */
      navigate("/vehicles", {
        replace: true,
      });
    } catch (error) {
      /*
       * Laravel restituisce 409 quando il veicolo possiede
       * noleggi, spese oppure celle dell'autorimessa.
       */
      if (error.response?.status === 409) {
        setDeleteVehicleError(
          error.response.data.message ||
            "Il veicolo possiede dati collegati e non può essere eliminato.",
        );
      } else {
        setDeleteVehicleError(
          "Impossibile eliminare il veicolo. Riprova più tardi.",
        );
      }
    } finally {
      setIsDeletingVehicle(false);
    }
  }

  useEffect(() => {
    // Permette di annullare la richiesta quando si lascia la pagina.
    const controller = new AbortController();

    async function loadVehicle() {
      setIsLoading(true);
      setErrorMessage("");
      setVehicle(null);
      setSelectedImage(null);

      try {
        /*
         * vehicleId arriva dall'indirizzo.
         * Con /vehicles/4 chiamiamo /api/vehicles/4.
         */
        const response = await api.get(`/api/vehicles/${vehicleId}`, {
          signal: controller.signal,
        });

        // Laravel inserisce il veicolo nella proprietà data.
        const loadedVehicle = response.data.data;

        setVehicle(loadedVehicle);

        // Mostra la copertina oppure la prima fotografia disponibile.
        setSelectedImage(
          loadedVehicle.primary_image ?? loadedVehicle.images?.[0] ?? null,
        );
      } catch (error) {
        // Non mostra errori quando la richiesta è stata annullata.
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

    // Annulla la richiesta quando il componente viene smontato.
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

  const operationalStatus =
    OPERATIONAL_STATUS_CONFIG[vehicle.operational_status] ??
    OPERATIONAL_STATUS_CONFIG.available;

  return (
    <>
      <Link
        to="/vehicles"
        className="btn btn-link text-secondary text-decoration-none px-0 mb-3"
      >
        <i className="bi bi-arrow-left me-2" aria-hidden="true"></i>
        Torna ai veicoli
      </Link>

      {/* Intestazione: identità del mezzo e azioni principali. */}
      <header className="vehicle-detail-header">
        <div className="vehicle-detail-header__identity">
          <span className="page-eyebrow">Scheda veicolo</span>

          <h1 className="mb-2">
            {vehicle.brand} {vehicle.model}
          </h1>

          <span className="vehicle-detail-plate">
            <small>Targa</small>
            <strong>{vehicle.license_plate}</strong>
          </span>
        </div>

        <div className="vehicle-detail-header__actions">
          <Link
            to={`/vehicles/${vehicle.id}/edit`}
            className="btn btn-outline-secondary"
          >
            <i className="bi bi-pencil me-2" aria-hidden="true"></i>
            Modifica veicolo
          </Link>
        </div>
      </header>

      {/* Stato operativo immediatamente riconoscibile. */}
      <section
        className={`vehicle-detail-status ${operationalStatus.className}`}
        aria-label="Stato operativo del veicolo"
      >
        <span className="vehicle-detail-status__icon">
          <i className={`bi ${operationalStatus.icon}`} aria-hidden="true"></i>
        </span>

        <div>
          <span className="vehicle-detail-status__label">Stato attuale</span>
          <strong>{operationalStatus.label}</strong>
        </div>

        <p>
          {vehicle.operational_status === "rented"
            ? "Il mezzo è attualmente consegnato a un cliente."
            : vehicle.operational_status === "reserved"
              ? "Il mezzo ha una prenotazione futura già confermata."
              : vehicle.operational_status === "inactive"
                ? "Il mezzo è disattivato e non può essere noleggiato."
                : "Il mezzo è libero e può essere assegnato a un nuovo noleggio."}
        </p>
      </section>

      {/* Fotografia principale oppure segnaposto. */}
      <div className="card border-0 shadow-sm overflow-hidden mb-2 vehicle-detail-media">
        {selectedImage ? (
          <img
            src={selectedImage.url}
            alt={
              selectedImage.caption ||
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

      {/* Mostra le informazioni della fotografia selezionata. */}
      {selectedImage && (
        <div className="vehicle-detail-caption d-flex flex-wrap align-items-center gap-2 mb-4">
          <span className="badge text-bg-light border text-secondary">
            {IMAGE_CATEGORY_LABELS[selectedImage.category] ??
              selectedImage.category}
          </span>

          <span className="text-secondary">
            {selectedImage.caption || "Nessuna descrizione"}
          </span>
        </div>
      )}

      {/* Galleria e pannello di gestione delle fotografie. */}
      <section className="mb-4" aria-labelledby="vehicle-gallery-title">
        <div className="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
          <div>
            <h2 id="vehicle-gallery-title" className="h5 mb-1">
              Galleria fotografica
            </h2>

            <span className="small text-secondary">
              {vehicle.images?.length ?? 0}{" "}
              {(vehicle.images?.length ?? 0) === 1
                ? "fotografia"
                : "fotografie"}
            </span>
          </div>

          <button
            type="button"
            className="btn btn-sm btn-outline-secondary"
            onClick={() => {
              setIsImageManagerOpen((isOpen) => !isOpen);
            }}
            aria-expanded={isImageManagerOpen}
            aria-controls="vehicle-image-manager"
          >
            <i
              className={`bi ${
                isImageManagerOpen ? "bi-x-lg" : "bi-images"
              } me-2`}
              aria-hidden="true"
            ></i>

            {isImageManagerOpen ? "Chiudi gestione" : "Gestisci fotografie"}
          </button>
        </div>

        {/* Il modulo compare solamente quando si apre la gestione. */}
        {isImageManagerOpen && (
          <form
            id="vehicle-image-manager"
            className="card border-0 shadow-sm mb-4"
            onSubmit={handleImageUpload}
          >
            <div className="card-body">
              <h3 className="h6 mb-3">Aggiungi fotografie</h3>

              <div className="row g-3 align-items-end">
                <div className="col-12 col-md">
                  <label htmlFor="vehicle-image" className="form-label">
                    Scegli uno o più file
                  </label>

                  <input
                    id="vehicle-image"
                    type="file"
                    className="form-control"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    disabled={isUploading}
                    onChange={(event) => {
                      setImageFiles(Array.from(event.target.files ?? []));
                      setUploadError("");
                    }}
                  />

                  <div className="form-text">
                    JPG, PNG e WebP. Massimo 10 MB per fotografia e 10 foto
                    complessive per veicolo.
                  </div>
                </div>

                <div className="col-12 col-md-auto d-grid">
                  <button
                    type="submit"
                    className="btn btn-primary"
                    disabled={imageFiles.length === 0 || isUploading}
                  >
                    {isUploading ? (
                      <>
                        <span
                          className="spinner-border spinner-border-sm me-2"
                          aria-hidden="true"
                        ></span>
                        Caricamento...
                      </>
                    ) : (
                      <>
                        <i className="bi bi-upload me-2" aria-hidden="true"></i>
                        Carica {imageFiles.length > 1 ? "foto" : "fotografia"}
                      </>
                    )}
                  </button>
                </div>
              </div>

              {uploadError && (
                <div className="alert alert-danger mt-3 mb-0" role="alert">
                  {uploadError}
                </div>
              )}
            </div>
          </form>
        )}

        {isImageManagerOpen && imageActionError && (
          <div className="alert alert-danger" role="alert">
            {imageActionError}
          </div>
        )}

        {/* Mostra le miniature oppure un messaggio se la galleria è vuota. */}
        {vehicle.images?.length > 0 ? (
          <div className="d-flex flex-wrap align-items-start gap-3">
            {vehicle.images.map((image) => (
              <div key={image.id} className="vehicle-gallery-item">
                <button
                  type="button"
                  className={`vehicle-gallery-thumbnail ${
                    selectedImage?.id === image.id
                      ? "vehicle-gallery-thumbnail--active"
                      : ""
                  }`}
                  onClick={() => {
                    setSelectedImage(image);
                  }}
                  aria-pressed={selectedImage?.id === image.id}
                  aria-label={
                    image.caption ||
                    `Mostra la fotografia ${image.original_name}`
                  }
                >
                  <img src={image.url} alt="" loading="lazy" />
                </button>

                {/* I comandi compaiono solamente aprendo la gestione. */}
                {isImageManagerOpen && (
                  <div className="d-grid gap-2 mt-2">
                    {image.is_primary ? (
                      <span className="badge text-bg-secondary w-100 py-2">
                        <i
                          className="bi bi-star-fill me-1"
                          aria-hidden="true"
                        ></i>
                        Copertina
                      </span>
                    ) : (
                      <button
                        type="button"
                        className="btn btn-sm btn-light border w-100"
                        disabled={
                          updatingImageId !== null || deletingImageId !== null
                        }
                        onClick={() => {
                          handleSetPrimary(image);
                        }}
                      >
                        {updatingImageId === image.id ? (
                          <>
                            <span
                              className="spinner-border spinner-border-sm me-1"
                              aria-hidden="true"
                            ></span>
                            Attendi
                          </>
                        ) : (
                          <>
                            <i
                              className="bi bi-star me-1"
                              aria-hidden="true"
                            ></i>
                            Copertina
                          </>
                        )}
                      </button>
                    )}

                    <button
                      type="button"
                      className={`btn btn-sm w-100 ${
                        editingImage?.id === image.id
                          ? "btn-secondary"
                          : "btn-outline-secondary"
                      }`}
                      disabled={
                        deletingImageId !== null ||
                        updatingImageId !== null ||
                        isSavingImage
                      }
                      onClick={() => {
                        handleStartImageEdit(image);
                      }}
                    >
                      <i className="bi bi-pencil me-1" aria-hidden="true"></i>
                      {editingImage?.id === image.id
                        ? "In modifica"
                        : "Modifica"}
                    </button>

                    <button
                      type="button"
                      className="btn btn-sm btn-outline-danger w-100"
                      disabled={
                        deletingImageId !== null || updatingImageId !== null
                      }
                      onClick={() => {
                        handleDeleteImage(image);
                      }}
                    >
                      {deletingImageId === image.id ? (
                        <>
                          <span
                            className="spinner-border spinner-border-sm me-1"
                            aria-hidden="true"
                          ></span>
                          Elimina
                        </>
                      ) : (
                        <>
                          <i
                            className="bi bi-trash me-1"
                            aria-hidden="true"
                          ></i>
                          Elimina
                        </>
                      )}
                    </button>
                  </div>
                )}
              </div>
            ))}
          </div>
        ) : (
          <p className="text-secondary mb-0">
            Non sono ancora state caricate fotografie.
          </p>
        )}

        {isImageManagerOpen && editingImage && (
          <form
            className="card border-0 shadow-sm mt-4"
            onSubmit={handleImageUpdate}
          >
            <div className="card-body">
              <div className="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div>
                  <h3 className="h6 mb-1">Modifica fotografia</h3>

                  <p className="small text-secondary mb-0">
                    {editingImage.original_name}
                  </p>
                </div>

                <button
                  type="button"
                  className="btn-close"
                  aria-label="Chiudi modifica"
                  disabled={isSavingImage}
                  onClick={() => {
                    setEditingImage(null);
                    setImageActionError("");
                  }}
                ></button>
              </div>

              <div className="row g-3">
                <div className="col-12 col-md-4">
                  <label
                    htmlFor="vehicle-image-category"
                    className="form-label"
                  >
                    Categoria
                  </label>

                  <select
                    id="vehicle-image-category"
                    className="form-select"
                    value={editCategory}
                    disabled={isSavingImage}
                    onChange={(event) => {
                      setEditCategory(event.target.value);
                    }}
                  >
                    {Object.entries(IMAGE_CATEGORY_LABELS).map(
                      ([value, label]) => (
                        <option key={value} value={value}>
                          {label}
                        </option>
                      ),
                    )}
                  </select>
                </div>

                <div className="col-12 col-md-8">
                  <label htmlFor="vehicle-image-caption" className="form-label">
                    Descrizione
                  </label>

                  <input
                    id="vehicle-image-caption"
                    type="text"
                    className="form-control"
                    value={editCaption}
                    maxLength={255}
                    disabled={isSavingImage}
                    placeholder="Esempio: vista anteriore del veicolo"
                    onChange={(event) => {
                      setEditCaption(event.target.value);
                    }}
                  />

                  <div className="form-text">Massimo 255 caratteri.</div>
                </div>
              </div>

              <div className="d-flex flex-wrap justify-content-end gap-2 mt-4">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={isSavingImage}
                  onClick={() => {
                    setEditingImage(null);
                    setImageActionError("");
                  }}
                >
                  Annulla
                </button>

                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={isSavingImage}
                >
                  {isSavingImage ? (
                    <>
                      <span
                        className="spinner-border spinner-border-sm me-2"
                        aria-hidden="true"
                      ></span>
                      Salvataggio...
                    </>
                  ) : (
                    <>
                      <i className="bi bi-check-lg me-2" aria-hidden="true"></i>
                      Salva modifiche
                    </>
                  )}
                </button>
              </div>
            </div>
          </form>
        )}
      </section>

      {/* Noleggio corrente e prossima prenotazione, quando presenti. */}
      {(vehicle.active_rental || vehicle.next_reservation) && (
        <section
          className="vehicle-detail-commitments"
          aria-labelledby="vehicle-commitments-title"
        >
          <div className="vehicle-section-heading">
            <div>
              <span className="page-eyebrow">Programmazione</span>
              <h2 id="vehicle-commitments-title" className="h4 mb-1">
                Utilizzo del veicolo
              </h2>
              <p className="text-secondary mb-0">
                Noleggio in corso e prossimo impegno registrato.
              </p>
            </div>
          </div>

          <div className="vehicle-commitment-grid">
            {vehicle.active_rental && (
              <article className="vehicle-commitment-card vehicle-commitment-card--rented">
                <div className="vehicle-commitment-card__title">
                  <span>
                    <i className="bi bi-key-fill" aria-hidden="true"></i>
                  </span>
                  <div>
                    <small>Noleggio attuale</small>
                    <h3>{getCustomerName(vehicle.active_rental)}</h3>
                  </div>
                </div>

                <dl className="vehicle-commitment-card__details">
                  <div>
                    <dt>Consegna</dt>
                    <dd>
                      {formatDateTime(
                        vehicle.active_rental.actual_starts_at ??
                          vehicle.active_rental.starts_at,
                      )}
                    </dd>
                  </div>
                  <div>
                    <dt>Rientro previsto</dt>
                    <dd>
                      {formatDateTime(vehicle.active_rental.expected_ends_at)}
                    </dd>
                  </div>
                  <div>
                    <dt>Totale</dt>
                    <dd>
                      {formatCurrency(vehicle.active_rental.total_amount)}
                    </dd>
                  </div>
                  <div>
                    <dt>Da saldare</dt>
                    <dd>{formatCurrency(vehicle.active_rental.balance_due)}</dd>
                  </div>
                </dl>
              </article>
            )}

            {vehicle.next_reservation && (
              <article className="vehicle-commitment-card vehicle-commitment-card--reserved">
                <div className="vehicle-commitment-card__title">
                  <span>
                    <i
                      className="bi bi-calendar-check-fill"
                      aria-hidden="true"
                    ></i>
                  </span>
                  <div>
                    <small>Prossima prenotazione</small>
                    <h3>{getCustomerName(vehicle.next_reservation)}</h3>
                  </div>
                </div>

                <dl className="vehicle-commitment-card__details">
                  <div>
                    <dt>Inizio</dt>
                    <dd>
                      {formatDateTime(vehicle.next_reservation.starts_at)}
                    </dd>
                  </div>
                  <div>
                    <dt>Rientro previsto</dt>
                    <dd>
                      {formatDateTime(
                        vehicle.next_reservation.expected_ends_at,
                      )}
                    </dd>
                  </div>
                  <div>
                    <dt>Totale</dt>
                    <dd>
                      {formatCurrency(vehicle.next_reservation.total_amount)}
                    </dd>
                  </div>
                  <div>
                    <dt>Da saldare</dt>
                    <dd>
                      {formatCurrency(vehicle.next_reservation.balance_due)}
                    </dd>
                  </div>
                </dl>
              </article>
            )}
          </div>
        </section>
      )}

      {/* Informazioni principali del veicolo. */}
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

      {/* Riepilogo dei dati collegati al veicolo. */}
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

      {/* Operazione distruttiva mantenuta separata dalle azioni normali. */}
      <section
        className="card border-danger-subtle shadow-sm mb-4"
        aria-labelledby="delete-vehicle-title"
      >
        <div className="card-body p-4">
          <div className="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
              <h2 id="delete-vehicle-title" className="h5 text-danger mb-1">
                Elimina veicolo
              </h2>

              <p className="text-secondary mb-0">
                L’eliminazione è possibile solamente se il veicolo non possiede
                noleggi, spese o posti occupati.
              </p>
            </div>

            <button
              type="button"
              className="btn btn-outline-danger"
              disabled={isDeletingVehicle}
              onClick={handleDeleteVehicle}
            >
              {isDeletingVehicle ? (
                <>
                  <span
                    className="spinner-border spinner-border-sm me-2"
                    aria-hidden="true"
                  ></span>
                  Eliminazione...
                </>
              ) : (
                <>
                  <i className="bi bi-trash me-2" aria-hidden="true"></i>
                  Elimina veicolo
                </>
              )}
            </button>
          </div>

          {deleteVehicleError && (
            <div className="alert alert-danger mt-3 mb-0" role="alert">
              {deleteVehicleError}
            </div>
          )}
        </div>
      </section>
    </>
  );
}

export default VehicleDetailsPage;
