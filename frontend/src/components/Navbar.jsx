import { useState } from "react";
import { Link, NavLink } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

function Navbar() {
  const { user, logout } = useAuth();
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [logoutError, setLogoutError] = useState("");

  async function handleLogout() {
    setLogoutError("");
    setIsLoggingOut(true);

    try {
      // Il provider svuota user: RequireAuth reindirizza da solo al login.
      await logout();
    } catch {
      setLogoutError("Uscita non riuscita. Riprova.");
    } finally {
      setIsLoggingOut(false);
    }
  }

  return (
    <header className="bg-dark text-white">
      <div className="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <Link to="/" className="text-white text-decoration-none fw-bold">
          <i className="bi bi-car-front-fill me-2" aria-hidden="true"></i>
          Vehicle Rental Manager
        </Link>

        <nav className="d-flex gap-3" aria-label="Navigazione principale">
          <NavLink to="/" end className="text-white">
            Dashboard
          </NavLink>
          <NavLink to="/vehicles" className="text-white">
            Veicoli
          </NavLink>
        </nav>

        <div className="d-flex align-items-center gap-3">
          {/* Il nome arriva da /api/user dopo il controllo della sessione. */}
          <span>Ciao, {user.name}</span>
          <button
            type="button"
            className="btn btn-outline-light btn-sm"
            onClick={handleLogout}
            disabled={isLoggingOut}
          >
            {isLoggingOut ? "Uscita..." : "Esci"}
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
