import { useState } from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Loader from "../components/Loader";

function LoginPage() {
  const { login, user, isCheckingSession } = useAuth();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [errorMessage, setErrorMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event) {
    // Il form resta nella SPA: il browser non deve ricaricare la pagina.
    event.preventDefault();
    setErrorMessage("");
    setIsSubmitting(true);

    try {
      // Il provider gestisce CSRF, login Laravel e dati dell'utente.
      await login({ email, password });
    } catch (error) {
      if (error.response?.status === 422) {
        setErrorMessage("Email o password non corrette.");
      } else {
        setErrorMessage(
          "Accesso non riuscito. Verifica la connessione con Laravel.",
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isCheckingSession) {
    return <Loader message="Controllo della sessione..." />;
  }

  // Dopo login() il provider imposta user: questo redirect segue lo stato.
  if (user) {
    return <Navigate to="/" replace />;
  }

  return (
    <main className="container py-5">
      <div className="row justify-content-center">
        <div className="col-12 col-sm-8 col-md-5">
          <h1 className="mb-4">Accedi</h1>

          <form onSubmit={handleSubmit}>
            <div className="mb-3">
              <label htmlFor="email" className="form-label">
                Email
              </label>
              <input
                id="email"
                type="email"
                className="form-control"
                autoComplete="username"
                required
                value={email}
                onChange={(event) => setEmail(event.target.value)}
              />
            </div>

            <div className="mb-3">
              <label htmlFor="password" className="form-label">
                Password
              </label>
              <input
                id="password"
                type="password"
                className="form-control"
                autoComplete="current-password"
                required
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </div>

            {errorMessage && (
              <div className="alert alert-danger" role="alert">
                {errorMessage}
              </div>
            )}

            <button
              type="submit"
              className="btn btn-primary"
              disabled={isSubmitting}
            >
              {isSubmitting ? "Accesso in corso..." : "Accedi"}
            </button>
          </form>
        </div>
      </div>
    </main>
  );
}

export default LoginPage;
