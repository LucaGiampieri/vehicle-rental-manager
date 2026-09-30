import { BrowserRouter, Route, Routes } from "react-router-dom";
import AuthProvider from "./context/AuthProvider";
import RequireAuth from "./components/RequireAuth";
import DefaultLayout from "./layouts/DefaultLayout";
import DashboardPage from "./pages/DashboardPage";
import RentalsPage from "./pages/RentalsPage";
import NewRentalPage from "./pages/NewRentalPage";
import RentalDetailsPage from "./pages/RentalDetailsPage";
import EditRentalPage from "./pages/EditRentalPage";
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
      {/* Il provider condivide la sessione con login, layout e pagine. */}
      <AuthProvider>
        <Routes>
          {/* Il login rimane accessibile anche senza sessione. */}
          <Route path="login" element={<LoginPage />} />

          {/* Il controllo vale per tutte le pagine interne al gestionale. */}
          <Route element={<RequireAuth />}>
            <Route element={<DefaultLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="rentals" element={<RentalsPage />} />
              <Route path="rentals/new" element={<NewRentalPage />} />
              <Route
                path="rentals/:rentalId/edit"
                element={<EditRentalPage />}
              />
              <Route
                path="rentals/:rentalId"
                element={<RentalDetailsPage />}
              />
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
              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Route>
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
