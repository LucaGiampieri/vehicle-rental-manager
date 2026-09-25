import { useState } from "react";
import { Navigate, useNavigate } from "react-router-dom";
import Loader from "../components/Loader";
import { useAuth } from "../context/AuthContext";

function LoginPage() {
  const navigate = useNavigate();
  const { login, user, isCheckingSession } = useAuth();

  // Conserva i dati inseriti nel modulo.
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  // Controlla la visibilità della password.
  const [showPassword, setShowPassword] = useState(false);

  // Gestisce stato ed errori della richiesta.
  const [errorMessage, setErrorMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();

    setErrorMessage("");
    setIsSubmitting(true);

    try {
      await login({
        email,
        password,
      });

      navigate("/", {
        replace: true,
      });
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

  // Attende la verifica iniziale della sessione.
  if (isCheckingSession) {
    return <Loader message="Controllo della sessione..." />;
  }

  // Un utente già autenticato non deve rivedere il login.
  if (user) {
    return <Navigate to="/" replace />;
  }

  return (
    <main className="login-page">
      <div className="container">
        <div className="login-panel">
          {/* Presentazione del gestionale. */}
          <section className="login-hero">
            <div className="login-hero__content">
              <span className="login-hero__icon">
                <i className="bi bi-car-front-fill" aria-hidden="true"></i>
              </span>

              <span className="login-hero__eyebrow">
                Vehicle Rental Manager
              </span>

              <h1 className="login-hero__title">
                La tua flotta,
                <br />
                sotto controllo.
              </h1>

              <p className="login-hero__description">
                Gestisci veicoli, noleggi, clienti, spese e autorimessa da
                un’unica applicazione.
              </p>

              <ul className="login-benefits">
                <li>
                  <i className="bi bi-check-circle-fill" aria-hidden="true"></i>
                  Gestione completa dei mezzi
                </li>

                <li>
                  <i className="bi bi-check-circle-fill" aria-hidden="true"></i>
                  Controllo di noleggi e scadenze
                </li>

                <li>
                  <i className="bi bi-check-circle-fill" aria-hidden="true"></i>
                  Monitoraggio dell’autorimessa
                </li>
              </ul>
            </div>
          </section>

          {/* Modulo di autenticazione. */}
          <section className="login-form-panel">
            <div className="login-form-panel__header">
              <span className="login-form-panel__label">Area riservata</span>

              <h2 className="h3 mb-2">Bentornato</h2>

              <p className="text-secondary mb-0">
                Inserisci le tue credenziali per accedere al gestionale.
              </p>
            </div>

            <form className="login-form" onSubmit={handleSubmit}>
              <div>
                <label htmlFor="email" className="form-label">
                  Email
                </label>

                <div className="input-group">
                  <span className="input-group-text">
                    <i className="bi bi-envelope" aria-hidden="true"></i>
                  </span>

                  <input
                    id="email"
                    type="email"
                    className="form-control"
                    autoComplete="username"
                    required
                    disabled={isSubmitting}
                    value={email}
                    placeholder="nome@esempio.it"
                    onChange={(event) => {
                      setEmail(event.target.value);
                    }}
                  />
                </div>
              </div>

              <div>
                <label htmlFor="password" className="form-label">
                  Password
                </label>

                <div className="input-group">
                  <span className="input-group-text">
                    <i className="bi bi-lock" aria-hidden="true"></i>
                  </span>

                  <input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    className="form-control"
                    autoComplete="current-password"
                    required
                    disabled={isSubmitting}
                    value={password}
                    placeholder="Inserisci la password"
                    onChange={(event) => {
                      setPassword(event.target.value);
                    }}
                  />

                  <button
                    type="button"
                    className="login-password-button"
                    disabled={isSubmitting}
                    aria-label={
                      showPassword ? "Nascondi password" : "Mostra password"
                    }
                    aria-pressed={showPassword}
                    onClick={() => {
                      setShowPassword((isVisible) => !isVisible);
                    }}
                  >
                    <i
                      className={showPassword ? "bi bi-eye-slash" : "bi bi-eye"}
                      aria-hidden="true"
                    ></i>
                  </button>
                </div>
              </div>

              {errorMessage && (
                <div className="alert alert-danger mb-0" role="alert">
                  {errorMessage}
                </div>
              )}

              <button
                type="submit"
                className="btn btn-primary btn-lg w-100"
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <>
                    <span
                      className="spinner-border spinner-border-sm me-2"
                      aria-hidden="true"
                    ></span>
                    Accesso in corso...
                  </>
                ) : (
                  <>
                    Accedi
                    <i
                      className="bi bi-arrow-right ms-2"
                      aria-hidden="true"
                    ></i>
                  </>
                )}
              </button>
            </form>
          </section>
        </div>
      </div>
    </main>
  );
}

export default LoginPage;
