import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import App from "./App.jsx";

// I nostri stili arrivano dopo Bootstrap e possono personalizzarlo.
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css";
import "./index.css";

createRoot(document.getElementById("root")).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
