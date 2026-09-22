import { BrowserRouter, Route, Routes } from "react-router-dom";
import AuthProvider from "./context/AuthProvider";
import RequireAuth from "./components/RequireAuth";
import DefaultLayout from "./layouts/DefaultLayout";
import DashboardPage from "./pages/DashboardPage";
import VehiclesPage from "./pages/VehiclesPage";
import NotFoundPage from "./pages/NotFoundPage";
import LoginPage from "./pages/LoginPage";

function App() {
  return (
    <BrowserRouter>
      {/* Il provider condivide la sessione con login, layout e pagine. */}
      <AuthProvider>
        <Routes>
          {/* Il login rimane accessibile anche senza sessione. */}
          <Route path="login" element={<LoginPage />} />

          {/* Il controllo vale per tutte le pagine interne al gestionale. */}
          <Route element={<RequireAuth />}>
            <Route element={<DefaultLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="vehicles" element={<VehiclesPage />} />
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
