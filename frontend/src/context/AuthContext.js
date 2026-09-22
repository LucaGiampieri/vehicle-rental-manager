import { createContext, useContext } from "react";

// Il provider rende disponibile l'utente a tutte le pagine senza prop drilling.
export const AuthContext = createContext(null);

export function useAuth() {
  const context = useContext(AuthContext);

  if (context === null) {
    // Segnaliamo subito un componente montato fuori da AuthProvider.
    throw new Error("useAuth deve essere usato dentro AuthProvider");
  }

  return context;
}
