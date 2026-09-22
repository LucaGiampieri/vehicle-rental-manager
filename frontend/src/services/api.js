import axios from "axios";

// Un solo client per le rotte /api/* e per /login e /sanctum/csrf-cookie.
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  // La sessione Sanctum usa i cookie; Axios invia anche il token CSRF.
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

export default api;
