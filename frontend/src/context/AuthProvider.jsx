import { useEffect, useState } from "react";
import api from "../services/api";
import { AuthContext } from "./AuthContext";

function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isCheckingSession, setIsCheckingSession] = useState(true);

  useEffect(() => {
    // La sessione vive nel cookie Laravel: all'avvio chiediamo chi è entrato.
    let active = true;

    api
      .get("/api/user")
      .then(({ data }) => {
        if (active) setUser(data);
      })
      .catch(() => {
        if (active) setUser(null);
      })
      .finally(() => {
        if (active) setIsCheckingSession(false);
      });

    return () => {
      // Una risposta tardiva non deve aggiornare un componente già smontato.
      active = false;
    };
  }, []);

  async function login(credentials) {
    // Sanctum prepara il cookie CSRF prima di accettare il POST di login.
    await api.get("/sanctum/csrf-cookie");
    await api.post("/login", credentials);

    // Aggiorniamo il context subito, senza aspettare un nuovo caricamento.
    const { data } = await api.get("/api/user");
    setUser(data);
  }

  async function logout() {
    // Cancelliamo prima la sessione sul server, poi l'utente in React.
    await api.post("/logout");
    setUser(null);
  }

  return (
    <AuthContext.Provider value={{ user, isCheckingSession, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export default AuthProvider;
