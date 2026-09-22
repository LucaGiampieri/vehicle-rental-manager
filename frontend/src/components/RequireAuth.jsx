import { Navigate, Outlet } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Loader from "./Loader";

function RequireAuth() {
  const { user, isCheckingSession } = useAuth();

  // Evita che una pagina privata appaia prima della risposta di /api/user.
  if (isCheckingSession) {
    return <Loader message="Controllo della sessione..." />;
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // Outlet mostra la pagina figlia scelta dalle rotte in App.jsx.
  return <Outlet />;
}

export default RequireAuth;
