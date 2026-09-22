import { Link } from "react-router-dom";

function NotFoundPage() {
  // La rotta * mostra questa pagina per indirizzi che non esistono.
  return (
    <>
      <h1>Pagina non trovata</h1>
      <p>L'indirizzo richiesto non esiste.</p>
      <Link to="/">Torna alla dashboard</Link>
    </>
  );
}

export default NotFoundPage;
