// Etichette condivise da elenco, dettaglio e form delle spese.
export const EXPENSE_CATEGORIES = {
  purchase: { label: "Acquisto veicolo", icon: "bi-cart-check" },
  maintenance: { label: "Manutenzione", icon: "bi-tools" },
  repair: { label: "Riparazione", icon: "bi-wrench-adjustable" },
  road_tax: { label: "Bollo", icon: "bi-file-earmark-text" },
  insurance: { label: "Assicurazione", icon: "bi-shield-check" },
  fuel: { label: "Carburante", icon: "bi-fuel-pump" },
  cleaning: { label: "Pulizia", icon: "bi-stars" },
  inspection: { label: "Revisione", icon: "bi-clipboard-check" },
  other: { label: "Altra spesa", icon: "bi-three-dots" },
};

export function formatCurrency(value) {
  return new Intl.NumberFormat("it-IT", {
    style: "currency",
    currency: "EUR",
  }).format(Number(value ?? 0));
}

export function formatDate(value) {
  if (!value) return "Non prevista";

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
  }).format(new Date(`${value}T00:00:00`));
}

export function createSlug(value) {
  return value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
}

// Restituisce un messaggio operativo relativo alla scadenza.
export function getExpiryStatus(expiresOn) {
  if (!expiresOn) {
    return {
      label: "Nessuna scadenza",
      className: "text-bg-light",
      description: "Questa spesa non genera una scadenza successiva.",
    };
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const expiry = new Date(`${expiresOn}T00:00:00`);
  const difference = Math.round((expiry - today) / 86_400_000);

  if (difference < 0) {
    return {
      label: "Scaduta",
      className: "text-bg-danger",
      description: `Scaduta da ${Math.abs(difference)} ${
        Math.abs(difference) === 1 ? "giorno" : "giorni"
      }`,
    };
  }

  if (difference === 0) {
    return {
      label: "Scade oggi",
      className: "text-bg-danger",
      description: "La validità termina oggi.",
    };
  }

  if (difference <= 30) {
    return {
      label: "In scadenza",
      className: "text-bg-warning",
      description: `Mancano ${difference} ${difference === 1 ? "giorno" : "giorni"}`,
    };
  }

  return {
    label: "Valida",
    className: "text-bg-success",
    description: `Mancano ${difference} giorni`,
  };
}
