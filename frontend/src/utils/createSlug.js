// Trasforma un testo in una parte leggibile e sicura per un URL.
export default function createSlug(value) {
  return (
    value
      // Separa le lettere dagli eventuali accenti.
      .normalize("NFD")

      // Elimina i segni degli accenti separati dalla normalizzazione.
      .replace(/[\u0300-\u036f]/g, "")

      // Uniforma tutte le lettere in minuscolo.
      .toLowerCase()

      // Elimina gli spazi all'inizio e alla fine.
      .trim()

      // Sostituisce spazi e caratteri speciali con un trattino.
      .replace(/[^a-z0-9]+/g, "-")

      // Elimina eventuali trattini iniziali o finali.
      .replace(/^-+|-+$/g, "")
  );
}
