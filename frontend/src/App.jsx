import { BrowserRouter, Route, Routes } from "react-router-dom";
import AuthProvider from "./context/AuthProvider";
import RequireAuth from "./components/RequireAuth";
import DefaultLayout from "./layouts/DefaultLayout";
import DashboardPage from "./pages/DashboardPage";
import GaragePage from "./pages/GaragePage";
import RentalsPage from "./pages/RentalsPage";
import NewRentalPage from "./pages/NewRentalPage";
import RentalDetailsPage from "./pages/RentalDetailsPage";
import EditRentalPage from "./pages/EditRentalPage";
import ExpensesPage from "./pages/ExpensesPage";
import NewExpensePage from "./pages/NewExpensePage";
import EditExpensePage from "./pages/EditExpensePage";
import ExpenseDetailsPage from "./pages/ExpenseDetailsPage";
import CustomersPage from "./pages/CustomersPage";
import CustomerDetailsPage from "./pages/CustomerDetailsPage";
import NewCustomerPage from "./pages/NewCustomerPage";
import EditCustomerPage from "./pages/EditCustomerPage";
import VehiclesPage from "./pages/VehiclesPage";
import NewVehiclePage from "./pages/NewVehiclePage";
import EditVehiclePage from "./pages/EditVehiclePage";
import VehicleDetailsPage from "./pages/VehicleDetailsPage";
import NotFoundPage from "./pages/NotFoundPage";
import LoginPage from "./pages/LoginPage";

function App() {
  return (
    <BrowserRouter>
      {/*
       * AuthProvider conserva l'utente autenticato
       * e condivide la sessione con tutte le pagine.
       */}
      <AuthProvider>
        <Routes>
          {/*
           * Il login rimane accessibile senza sessione.
           * La registrazione pubblica non è prevista.
           */}
          <Route path="login" element={<LoginPage />} />

          {/*
           * RequireAuth protegge tutte le pagine
           * interne del gestionale.
           */}
          <Route element={<RequireAuth />}>
            <Route element={<DefaultLayout />}>
              {/* Dashboard principale. */}
              <Route index element={<DashboardPage />} />

              {/* Autorimessa e mappa dei posti. */}
              <Route path="garage" element={<GaragePage />} />

              {/* Elenco, creazione e dettaglio noleggi. */}
              <Route path="rentals" element={<RentalsPage />} />

              <Route path="rentals/new" element={<NewRentalPage />} />

              <Route
                path="rentals/:rentalId/edit"
                element={<EditRentalPage />}
              />

              <Route path="rentals/:rentalId" element={<RentalDetailsPage />} />

              {/* Elenco, creazione e dettaglio spese. */}
              <Route path="expenses" element={<ExpensesPage />} />

              <Route path="expenses/new" element={<NewExpensePage />} />

              <Route
                path="expenses/:expenseId/edit"
                element={<EditExpensePage />}
              />

              <Route
                path="expenses/:expenseId"
                element={<ExpenseDetailsPage />}
              />

              {/* Elenco e gestione clienti. */}
              <Route path="customers" element={<CustomersPage />} />

              <Route path="customers/new" element={<NewCustomerPage />} />

              <Route
                path="customers/:customerId/edit"
                element={<EditCustomerPage />}
              />

              <Route
                path="customers/:customerId/:customerSlug?"
                element={<CustomerDetailsPage />}
              />

              {/* Elenco e gestione veicoli. */}
              <Route path="vehicles" element={<VehiclesPage />} />

              <Route path="vehicles/new" element={<NewVehiclePage />} />

              <Route
                path="vehicles/:vehicleId/edit"
                element={<EditVehiclePage />}
              />

              <Route
                path="vehicles/:vehicleId/:vehicleSlug?"
                element={<VehicleDetailsPage />}
              />

              {/*
               * Qualsiasi indirizzo non riconosciuto
               * mostra la pagina 404 interna.
               */}
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
