import { Outlet } from "react-router-dom";
import Navbar from "../components/Navbar";

function DefaultLayout() {
  return (
    <div className="app-shell">
      <Navbar />

      {/* Contenitore principale condiviso da tutte le pagine protette. */}
      <main className="container app-main">
        <Outlet />
      </main>
    </div>
  );
}

export default DefaultLayout;
