import { Outlet } from "react-router-dom";
import Navbar from "../components/Navbar";

function DefaultLayout() {
  return (
    <>
      <Navbar />

      <main className="container py-5">
        {/* La barra è comune; qui React Router inserisce la pagina corrente. */}
        <Outlet />
      </main>
    </>
  );
}

export default DefaultLayout;
