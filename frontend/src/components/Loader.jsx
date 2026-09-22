function Loader({ message = "Caricamento..." }) {
  return (
    // Il testo rende comprensibile lo stato anche senza vedere l'animazione.
    <div className="app-loader" role="status">
      <i
        className="bi bi-car-front-fill app-loader__car"
        aria-hidden="true"
      ></i>
      <span>{message}</span>
    </div>
  );
}

export default Loader;
