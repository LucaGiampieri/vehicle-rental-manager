import { useState } from "react";
import { Link, NavLink } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

function Navbar() {
  const { user, logout } = useAuth();

  // Indica che la richiesta di logout è in corso.
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  // Conserva un eventuale errore durante il logout.
  const [logoutError, setLogoutError] = useState("");

  async function handleLogout() {
    setLogoutError("");
    setIsLoggingOut(true);

    try {
      await logout();
    } catch {
      setLogoutError("Uscita non riuscita. Riprova.");
    } finally {
      setIsLoggingOut(false);
    }
  }

  // Mostra la prima lettera del nome nell'avatar.
  const userInitial = user?.name?.trim().charAt(0).toUpperCase() || "U";

  return (
    <header className="app-navbar">
      <div className="container app-navbar__inner">
        {/* Marchio dell'applicazione. */}
        <Link to="/" className="app-brand">
          <span className="app-brand__icon">
            <i className="bi bi-car-front-fill" aria-hidden="true"></i>
          </span>

          <span className="app-brand__content">
            <span className="app-brand__name">Vehicle Rental Manager</span>

            <span className="app-brand__subtitle">Gestione flotta</span>
          </span>
        </Link>

        {/* Navigazione principale. */}
        <nav className="app-navigation" aria-label="Navigazione principale">
          <NavLink
            to="/"
            end
            className={({ isActive }) =>
              `app-navigation__link ${
                isActive ? "app-navigation__link--active" : ""
              }`
            }
          >
            <i className="bi bi-speedometer2" aria-hidden="true"></i>
            Dashboard
          </NavLink>

          <NavLink
            to="/vehicles"
            className={({ isActive }) =>
              `app-navigation__link ${
                isActive ? "app-navigation__link--active" : ""
              }`
            }
          >
            <i className="bi bi-car-front" aria-hidden="true"></i>
            Veicoli
          </NavLink>

          <NavLink
            to="/customers"
            className={({ isActive }) =>
              `app-navigation__link ${
                isActive ? "app-navigation__link--active" : ""
              }`
            }
          >
            <i className="bi bi-people" aria-hidden="true"></i>
            Clienti
          </NavLink>
        </nav>

        {/* Informazioni dell'utente autenticato. */}
        <div className="app-user">
          <span className="app-user__avatar" aria-hidden="true">
            {userInitial}
          </span>

          <div className="app-user__information">
            <span className="app-user__name">{user?.name ?? "Utente"}</span>

            <span className="app-user__role">Amministratore</span>
          </div>

          <button
            type="button"
            className="app-logout-button"
            onClick={handleLogout}
            disabled={isLoggingOut}
            aria-label="Esci dall'applicazione"
            title="Esci"
          >
            {isLoggingOut ? (
              <span
                className="spinner-border spinner-border-sm"
                aria-hidden="true"
              ></span>
            ) : (
              <i className="bi bi-box-arrow-right" aria-hidden="true"></i>
            )}
          </button>
        </div>

        {logoutError && (
          <div className="alert alert-danger w-100 mb-0" role="alert">
            {logoutError}
          </div>
        )}
      </div>
    </header>
  );
}

export default Navbar;
