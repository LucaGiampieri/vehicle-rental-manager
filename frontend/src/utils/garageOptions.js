/*
 * Configurazione condivisa del modulo Autorimessa.
 *
 * Questo file raccoglie:
 * - i testi italiani dei diversi movimenti;
 * - le icone Bootstrap associate;
 * - le funzioni per mostrare date e posizioni.
 *
 * Centralizzare questi valori evita di ripeterli in più componenti.
 */

// Associa ogni tipo di movimento salvato da Laravel
// al testo e all'icona visualizzati nel frontend.
export const GARAGE_MOVEMENT_TYPES = {
  parked: {
    label: "Parcheggiato",
    icon: "bi-p-square",
  },

  moved: {
    label: "Spostato",
    icon: "bi-arrows-move",
  },

  unparked: {
    label: "Uscito",
    icon: "bi-box-arrow-right",
  },

  rental_departure: {
    label: "Partenza noleggio",
    icon: "bi-key",
  },

  rental_return: {
    label: "Rientro noleggio",
    icon: "bi-arrow-return-left",
  },
};

/*
 * Converte la data ISO restituita dall'API
 * in una data leggibile secondo il formato italiano.
 *
 * Esempio:
 * 2026-09-30T12:30:00.000Z
 * diventa
 * 30 set 2026, 14:30
 */
export function formatGarageDateTime(value) {
  // Evita di mostrare "Invalid Date" quando il valore non esiste.
  if (!value) {
    return "Data non disponibile";
  }

  return new Intl.DateTimeFormat("it-IT", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

/*
 * Trasforma la posizione registrata nel movimento
 * in un testo comprensibile.
 *
 * Quando la posizione è null significa che il mezzo:
 * - era fuori prima di essere parcheggiato;
 * - oppure è uscito dopo essere stato rimosso.
 */
export function formatGaragePosition(position) {
  if (!position) {
    return "Esterno";
  }

  return `${position.zone} · riga ${position.row_number}, posto ${position.column_number}`;
}
